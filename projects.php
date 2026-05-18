<?php
/**
 * projects.php — Creative Engine / Project Discovery
 * AJAX mode: POST action=brainstorm → returns JSON
 * Normal GET:  renders page shell; JS drives the fetch call
 */
ob_start();
ini_set('display_errors', '0');
ini_set('memory_limit', '512M');
set_time_limit(120);

require 'db.php';
require 'ai_helper.php';
session_start();

if (!isset($_SESSION['authenticated'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not authenticated.']);
        exit;
    }
    header('Location: index.php'); exit;
}

// ── AJAX brainstorm endpoint ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'brainstorm') {
    ob_end_clean();
    header('Content-Type: application/json; charset=utf-8');

    // Shutdown handler: catches fatal/OOM before JSON can be echoed
    register_shutdown_function(function () {
        $e = error_get_last();
        $fatals = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if ($e && in_array($e['type'], $fatals)) {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Server fatal: ' . $e['message']]);
        }
    });

    try {
        $settings = [];
        foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
            $settings[$r['setting_key']] = $r['setting_value'];
        }
        $provider = $settings['ai_provider'] ?? 'gemini';
        $api_key  = $settings['api_key']     ?? '';

        if (!$api_key) {
            echo json_encode(['error' => 'No API key configured. Go to ⚙️ AI Settings and save your key.']);
            exit;
        }

        // Build inventory context — exclude notes (contains import errors), cap per-item length
        $items = $pdo->query(
            "SELECT name, model, category, quantity, specs FROM inventory ORDER BY category, name"
        )->fetchAll();

        if (empty($items)) {
            echo json_encode(['error' => 'No inventory items found. Add components first.']);
            exit;
        }

        $inv_ctx = '';
        foreach ($items as $i) {
            $name = mb_substr(trim($i['name']), 0, 80);
            $spec = mb_substr(trim($i['specs'] ?? ''), 0, 120);
            $inv_ctx .= "- {$name} ({$i['model']}), Qty:{$i['quantity']}, Cat:{$i['category']}, Specs:{$spec}\n";
            if (strlen($inv_ctx) > 10000) { $inv_ctx .= "...(list truncated)\n"; break; }
        }

        $enrich_ctx = build_enrichment_context($pdo);
        if (strlen($enrich_ctx) > 4000) $enrich_ctx = mb_substr($enrich_ctx, 0, 4000) . "\n...(truncated)";

        $prompt = <<<PROMPT
You are an expert DIY electronics project advisor.
Review this lab inventory:

{$inv_ctx}
{$enrich_ctx}
Suggest exactly 5 creative, buildable projects using the components above.
Respond ONLY with a valid JSON array — no markdown, no extra text:
[
  {
    "title": "descriptive project title",
    "complexity": "Beginner|Intermediate|Expert",
    "duration": "e.g. 2-3 hours",
    "skill_domain": "e.g. IoT, Robotics, Embedded Systems",
    "safety": "none|low voltage|high voltage|heat|LiPo batteries",
    "stock": ["component1", "component2"],
    "missing": [
      {"part": "name", "amazon": "https://www.amazon.com/s?k=name", "aliexpress": "https://www.aliexpress.com/wholesale?SearchText=name"}
    ],
    "description": "2-sentence project description"
  }
]
PROMPT;

        $response = call_ai_api($prompt);
        $text     = extract_ai_text($response, $provider);

        if (str_starts_with($text, '[AI Error]')) {
            $raw = substr($text, strlen('[AI Error] '));
            if (str_contains($raw, '429') || str_contains($raw, 'quota') || str_contains($raw, 'RESOURCE_EXHAUSTED')) {
                echo json_encode(['error' => '⏳ Rate limit reached. Wait a minute then try again, or enable billing on your Google Cloud project.']);
            } else {
                echo json_encode(['error' => 'AI error: ' . substr($raw, 0, 300)]);
            }
            exit;
        }

        if (empty(trim($text))) {
            echo json_encode(['error' => 'AI returned an empty response. Try again.']);
            exit;
        }

        $clean   = clean_json_response($text);
        $results = json_decode($clean, true);
        if (!$results) {
            preg_match('/\[.*\]/s', $text, $m);
            $results = !empty($m[0]) ? json_decode($m[0], true) : null;
        }

        if (!$results || !is_array($results)) {
            echo json_encode([
                'error' => 'Could not parse AI response as JSON. Raw preview: ' . htmlspecialchars(substr($text, 0, 200)),
            ]);
            exit;
        }

        // ── Persist to settings cache so the page survives navigation ──────────
        $ts  = date('c');
        $upsert = $pdo->prepare(
            "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"
        );
        $upsert->execute(['project_ideas_cache',    json_encode($results)]);
        $upsert->execute(['project_ideas_provider',  ucfirst($provider)]);
        $upsert->execute(['project_ideas_cached_at', $ts]);

        echo json_encode(['projects' => $results, 'provider' => ucfirst($provider), 'count' => count($items), 'cached_at' => $ts]);

    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Exception: ' . $e->getMessage()]);
    }
    exit;
}

// ── Normal page render ────────────────────────────────────────────────────────
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $r) {
    $settings[$r['setting_key']] = $r['setting_value'];
}
$provider   = $settings['ai_provider'] ?? 'gemini';
$item_count = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();

// Load cached projects (if any)
$cached_projects  = $settings['project_ideas_cache']    ?? null;
$cached_provider  = $settings['project_ideas_provider']  ?? ucfirst($provider);
$cached_at_raw    = $settings['project_ideas_cached_at'] ?? null;
$has_cache        = ($cached_projects && $cached_at_raw);

// Human-readable age
$cache_age = '';
if ($cached_at_raw) {
    $diff = time() - strtotime($cached_at_raw);
    if    ($diff < 60)        $cache_age = 'just now';
    elseif($diff < 3600)      $cache_age = floor($diff/60)  . 'm ago';
    elseif($diff < 86400)     $cache_age = floor($diff/3600) . 'h ago';
    else                      $cache_age = floor($diff/86400) . 'd ago';
}
ob_end_flush();
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Creative Engine — DIY Lab</title>
  <meta name="description" content="AI-powered project discovery based on your current component inventory.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .project-card {
      background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07);
      border-radius:20px; padding:1.5rem; transition:all .25s;
      animation:fadeInUp .4s ease forwards;
    }
    .project-card:hover { border-color:rgba(124,58,237,.35); transform:translateY(-3px); box-shadow:0 12px 40px rgba(124,58,237,.12); }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .complexity-beginner     { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.3); }
    .complexity-intermediate { background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
    .complexity-expert       { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
    .tag { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); color:#94a3b8; font-size:.7rem; padding:.2rem .6rem; border-radius:999px; }
    .big-cta {
      background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(6,182,212,.1));
      border:2px dashed rgba(124,58,237,.3);
      border-radius:24px; padding:3rem; text-align:center; transition:all .3s;
    }
    .big-cta:hover { border-color:rgba(124,58,237,.6); }
    /* Loading overlay */
    #loading-overlay {
      display:none; position:fixed; inset:0; background:rgba(10,10,15,.85);
      backdrop-filter:blur(8px); z-index:100;
      flex-direction:column; align-items:center; justify-content:center; gap:20px;
    }
    #loading-overlay.active { display:flex; }
    .spinner-ring {
      width:56px; height:56px; border-radius:50%;
      border:3px solid rgba(139,92,246,.2);
      border-top-color:#8b5cf6;
      animation:spin 1s linear infinite;
    }
    @keyframes spin { to{transform:rotate(360deg)} }
    .loading-dots span {
      display:inline-block; width:6px; height:6px; border-radius:50%; background:#8b5cf6; margin:0 3px;
      animation:bounce .8s ease-in-out infinite;
    }
    .loading-dots span:nth-child(2){animation-delay:.15s}
    .loading-dots span:nth-child(3){animation-delay:.3s}
    @keyframes bounce{0%,80%,100%{transform:translateY(0)}40%{transform:translateY(-8px)}}
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

<!-- Loading overlay -->
<div id="loading-overlay">
  <div class="spinner-ring"></div>
  <div>
    <p class="text-white font-semibold text-center mb-1" data-i18n-text="projects.loading">Analysing your inventory…</p>
    <p class="text-slate-400 text-sm text-center">This takes 15–40 seconds</p>
  </div>
  <div class="loading-dots"><span></span><span></span><span></span></div>
</div>

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
<div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
  <div class="p-5 border-b border-white/5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
      </div>
      <div><p class="font-semibold text-white text-sm" data-i18n-text="nav.brand_name">DIY Lab</p><p class="text-xs text-slate-500" data-i18n-text="nav.brand_sub">Inventory System</p></div>
    </div>
  </div>
  <nav class="flex-1 p-4 space-y-1">
    <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard</a>
    <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component</a>
    <a href="projects.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> Creative Engine</a>
    <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
    <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings</a>
  </nav>
  <div class="p-4 border-t border-white/5">
    <div class="theme-toggle-wrap mb-2" onclick="toggleTheme()" role="button" aria-label="Toggle light mode" title="Toggle light/dark mode">
      <span class="theme-toggle-label">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
        Light Mode
      </span>
      <span class="toggle-pill"></span>
    </div>
    <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition-colors text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</div>

<main class="lg:ml-64 min-h-screen">
  <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2 min-w-0">
      <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5" aria-label="Open menu">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div>
        <h1 class="text-lg lg:text-xl font-bold text-white" data-i18n-text="projects.title">Creative Engine</h1>
        <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="projects.subtitle">AI-powered project discovery from your inventory</p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <?php if ($has_cache): ?>
      <span class="text-xs text-slate-500 hidden sm:block">Generated <?= htmlspecialchars($cache_age) ?></span>
      <?php endif; ?>
      <button id="discover-btn" onclick="brainstorm()"
        class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span class="hidden sm:inline"><?= $has_cache ? 'Regenerate' : 'Brainstorm Projects' ?></span>
        <span class="sm:hidden"><?= $has_cache ? '↺' : 'Go' ?></span>
      </button>
    </div>
  </header>

  <div class="p-4 lg:p-8">
    <!-- Error banner -->
    <div id="error-banner" class="hidden mb-6 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm"></div>

    <!-- Results area -->
    <div id="results-area">
      <?php if ($item_count === 0): ?>
      <div class="big-cta text-center">
        <p class="text-slate-300 font-semibold mb-2">No inventory yet</p>
        <p class="text-slate-500 text-sm mb-4">Add components first, then come back to discover projects.</p>
        <a href="add_item.php" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white text-sm">+ Add Components</a>
      </div>
      <?php elseif ($has_cache): ?>
      <div class="flex items-center justify-between mb-6 px-4 py-3 rounded-xl bg-purple-500/5 border border-purple-500/20">
        <p class="text-sm text-slate-400">Showing results from <span class="text-purple-300 font-medium"><?= htmlspecialchars($cache_age) ?></span>. Regenerate any time for fresh ideas.</p>
        <button onclick="brainstorm()" class="flex-shrink-0 ml-4 text-xs text-purple-300 border border-purple-500/30 px-3 py-1.5 rounded-lg hover:bg-purple-500/10 transition-all">↺ Regenerate</button>
      </div>
      <?php else: ?>
      <div class="big-cta cursor-pointer" onclick="brainstorm()">
        <div class="text-5xl mb-4">🚀</div>
        <p class="text-white font-bold text-xl mb-2">Ready to Discover</p>
        <p class="text-slate-400 text-sm mb-6"><?= $item_count ?> components in your lab.<br>Click below to let AI suggest creative projects.</p>
        <div class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white btn-primary shadow-lg shadow-purple-900/30 text-sm">✨ Generate Project Ideas</div>
        <p class="text-xs text-slate-600 mt-4">Takes 15–40 seconds · Uses <?= ucfirst($provider) ?></p>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<script>
// ── Cached results injected by PHP ───────────────────────────────────────────
const CACHED = <?= $has_cache ? $cached_projects : 'null' ?>;
const CACHED_PROVIDER = <?= json_encode($cached_provider) ?>;

document.addEventListener('DOMContentLoaded', () => {
  if (CACHED && Array.isArray(CACHED) && CACHED.length) {
    renderProjects(CACHED, CACHED_PROVIDER, true /* fromCache */);
  }
});

async function brainstorm() {
  // Show loading overlay
  document.getElementById('loading-overlay').classList.add('active');
  document.getElementById('discover-btn').disabled = true;
  document.getElementById('error-banner').classList.add('hidden');

  try {
    const fd = new FormData();
    fd.append('action', 'brainstorm');

    const res  = await fetch('projects.php', { method: 'POST', body: fd });
    const text = await res.text();

    let data;
    try { data = JSON.parse(text); }
    catch(e) {
      showError('Server returned non-JSON: ' + text.substring(0, 300));
      return;
    }

    if (data.error) { showError(data.error); return; }
    renderProjects(data.projects, data.provider, false);

  } catch(e) {
    showError('Network error: ' + e.message);
  } finally {
    document.getElementById('loading-overlay').classList.remove('active');
    document.getElementById('discover-btn').disabled = false;
  }
}

function showError(msg) {
  const el = document.getElementById('error-banner');
  el.innerHTML = '❌ ' + msg +
    (msg.includes('API') || msg.includes('key') ? ' <a href="settings.php" class="underline ml-1">Configure key →</a>' : '');
  el.classList.remove('hidden');
  document.getElementById('results-area').innerHTML = '';
}

function complexityClass(c) {
  c = (c||'').toLowerCase();
  if (c.includes('beginner'))    return 'complexity-beginner';
  if (c.includes('intermediate') || c.includes('int')) return 'complexity-intermediate';
  if (c.includes('expert'))      return 'complexity-expert';
  return 'complexity-beginner';
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderProjects(projects, provider, fromCache = false) {
  // If fresh results arrived, clear the "Generated X ago" notice strip
  if (!fromCache) {
    const notice = document.querySelector('#results-area .border-purple-500\\/20');
    if (notice) notice.remove();
  }

  let html = `<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">${projects.length} project ideas${fromCache ? ' (cached)' : ' generated'}</p>
    <span class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 px-3 py-1 rounded-full">Powered by ${esc(provider)}</span>
  </div><div class="space-y-5">`;

  projects.forEach((p, idx) => {
    const safe    = (p.safety||'').toLowerCase();
    const hasSafe = !['none','low voltage',''].includes(safe);
    const stock   = (p.stock||[]).map(s => `<span class="text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2.5 py-1 rounded-lg">✓ ${esc(s)}</span>`).join('');
    const missing = (p.missing||[]).map(m => `
      <div class="flex items-center justify-between bg-orange-500/5 border border-orange-500/15 rounded-lg px-3 py-2">
        <span class="text-sm text-slate-300">📦 ${esc(m.part)}</span>
        <div class="flex gap-2">
          ${m.amazon    ? `<a href="${esc(m.amazon)}"    target="_blank" rel="noopener" class="text-xs text-orange-400 hover:text-orange-300 border border-orange-500/20 px-2 py-1 rounded hover:border-orange-400/40 transition-all">Amazon →</a>` : ''}
          ${m.aliexpress? `<a href="${esc(m.aliexpress)}" target="_blank" rel="noopener" class="text-xs text-red-400 hover:text-red-300 border border-red-500/20 px-2 py-1 rounded hover:border-red-400/40 transition-all">AliExpress →</a>` : ''}
        </div>
      </div>`).join('');

    html += `
    <div class="project-card" style="animation-delay:${idx*0.08}s">
      <div class="flex items-start justify-between gap-4 mb-3">
        <h2 class="text-lg font-bold text-white">${esc(p.title)}</h2>
        <span class="${complexityClass(p.complexity)} text-xs px-3 py-1 rounded-full font-medium flex-shrink-0">${esc(p.complexity)}</span>
      </div>
      ${p.description ? `<p class="text-sm text-slate-400 mb-4">${esc(p.description)}</p>` : ''}
      <div class="flex flex-wrap gap-2 mb-4">
        ${p.duration     ? `<span class="tag">⏱ ${esc(p.duration)}</span>` : ''}
        ${p.skill_domain ? `<span class="tag">🔧 ${esc(p.skill_domain)}</span>` : ''}
        ${hasSafe        ? `<span class="tag" style="color:#f97316;border-color:rgba(249,115,22,.3)">⚡ ${esc(p.safety)}</span>` : ''}
      </div>
      ${stock ? `<div class="mb-4"><p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Components from your lab</p><div class="flex flex-wrap gap-1.5">${stock}</div></div>` : ''}
      ${missing ? `<div class="mb-5"><p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Parts to acquire</p><div class="space-y-2">${missing}</div></div>` : ''}
      <form action="project_blueprint.php" method="POST">
        <input type="hidden" name="title"       value="${esc(p.title)}">
        <input type="hidden" name="stock"       value="${esc(JSON.stringify(p.stock||[]))}">
        <input type="hidden" name="description" value="${esc(p.description||'')}">
        <button type="submit" class="w-full mt-1 py-2.5 rounded-xl text-sm font-semibold text-white btn-primary shadow-md shadow-purple-900/20">
          📐 Generate Full Blueprint &amp; Code
        </button>
      </form>
    </div>`;
  });

  html += '</div>';
  document.getElementById('results-area').innerHTML = html;
}

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
localizationController.init();
</script>
</body>
</html>
