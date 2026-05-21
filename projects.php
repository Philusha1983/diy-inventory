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
require_once 'site_config.php';
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
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
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
    <p class="text-slate-400 text-sm text-center" data-i18n-text="projects.this_takes_15_40_seconds">This takes 15–40 seconds</p>
  </div>
  <div class="loading-dots"><span></span><span></span><span></span></div>
</div>
  <?php include 'includes/sidebar.php'; ?>



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
      <span class="text-xs text-slate-500 hidden sm:block"><span data-i18n-text="projects.generated">Generated </span><?= htmlspecialchars($cache_age) ?></span>
      <?php endif; ?>
      <button id="discover-btn" onclick="brainstorm()"
        class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
        <span class="hidden sm:inline"><?= $has_cache ? '<span data-i18n-text="projects.regenerate_btn">Regenerate</span>' : '<span data-i18n-text="projects.brainstorm_projects">Brainstorm Projects</span>' ?></span>
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
        <p class="text-slate-300 font-semibold mb-2" data-i18n-text="projects.no_inventory_yet">No inventory yet</p>
        <p class="text-slate-500 text-sm mb-4" data-i18n-text="projects.add_components_first_then_come">Add components first, then come back to discover projects.</p>
        <a href="add_item.php" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white text-sm" data-i18n-text="projects.add_components">+ Add Components</a>
      </div>
      <?php elseif ($has_cache): ?>
      <div class="flex items-center justify-between mb-6 px-4 py-3 rounded-xl bg-purple-500/5 border border-purple-500/20">
        <p class="text-sm text-slate-400"><span data-i18n-text="projects.showing_results_from">Showing results from</span> <span class="text-purple-300 font-medium"><?= htmlspecialchars($cache_age) ?></span>. <span data-i18n-text="projects.regenerate_any_time">Regenerate any time for fresh ideas.</span></p>
        <button onclick="brainstorm()" class="flex-shrink-0 ml-4 text-xs text-purple-300 border border-purple-500/30 px-3 py-1.5 rounded-lg hover:bg-purple-500/10 transition-all" data-i18n-text="projects.regenerate">↺ Regenerate</button>
      </div>
      <?php else: ?>
      <div class="big-cta cursor-pointer" onclick="brainstorm()">
        <div class="text-5xl mb-4">🚀</div>
        <p class="text-white font-bold text-xl mb-2" data-i18n-text="projects.ready_to_discover">Ready to Discover</p>
        <p class="text-slate-400 text-sm mb-6"><?= $item_count ?> <span data-i18n-text="projects.components_in_your_lab">components in your lab.</span><br><span data-i18n-text="projects.click_below_to_let_ai">Click below to let AI suggest creative projects.</span></p>
        <div class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white btn-primary shadow-lg shadow-purple-900/30 text-sm" data-i18n-text="projects.generate_project_ideas">✨ Generate Project Ideas</div>
        <p class="text-xs text-slate-600 mt-4"><span data-i18n-text="projects.takes_seconds">Takes 15–40 seconds</span> · Uses <?= ucfirst($provider) ?></p>
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


function formatDuration(d) {
  if (!d) return '';
  return d.replace(/hours/gi, '<span data-i18n-text="projects.hours">hours</span>')
          .replace(/hour/gi, '<span data-i18n-text="projects.hour">hour</span>')
          .replace(/days/gi, '<span data-i18n-text="projects.days">days</span>')
          .replace(/day/gi, '<span data-i18n-text="projects.day">day</span>')
          .replace(/minutes/gi, '<span data-i18n-text="projects.minutes">minutes</span>')
          .replace(/minute/gi, '<span data-i18n-text="projects.minute">minute</span>')
          .replace(/weeks/gi, '<span data-i18n-text="projects.weeks">weeks</span>')
          .replace(/week/gi, '<span data-i18n-text="projects.week">week</span>');
}

function esc(s) {
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function renderProjects(projects, provider, fromCache = false) {
  // If fresh results arrived, clear the "<span data-i18n-text="projects.generated">Generated </span>X ago" notice strip
  if (!fromCache) {
    const notice = document.querySelector('#results-area .border-purple-500\\/20');
    if (notice) notice.remove();
  }

  let html = `<div class="mb-4 flex items-center justify-between">
    <p class="text-sm text-slate-500">${projects.length} <span data-i18n-text="projects.project_ideas">project ideas</span>${fromCache ? ' <span data-i18n-text="projects.cached">(cached)</span>' : ' <span data-i18n-text="projects.generated">generated</span>'}</p>
    <span class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 px-3 py-1 rounded-full"><span data-i18n-text="projects.powered_by">Powered by</span> ${esc(provider)}</span>
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
        <span class="${complexityClass(p.complexity)} text-xs px-3 py-1 rounded-full font-medium flex-shrink-0">
          ${['beginner','intermediate','expert'].includes((p.complexity||'').toLowerCase()) ? 
            `<span data-i18n-text="projects.complexity_${(p.complexity||'').toLowerCase()}">${esc(p.complexity)}</span>` : 
            esc(p.complexity)}
        </span>
      </div>
      ${p.description ? `<p class="text-sm text-slate-400 mb-4">${esc(p.description)}</p>` : ''}
      <div class="flex flex-wrap gap-2 mb-4">
        ${p.duration     ? `<span class="tag">⏱ ${formatDuration(esc(p.duration))}</span>` : ''}
        ${p.skill_domain ? `<span class="tag">🔧 ${esc(p.skill_domain)}</span>` : ''}
        ${hasSafe        ? `<span class="tag" style="color:#f97316;border-color:rgba(249,115,22,.3)">⚡ ${esc(p.safety)}</span>` : ''}
      </div>
      ${stock ? `<div class="mb-4"><p class="text-xs text-slate-500 uppercase tracking-wider mb-2" data-i18n-text="projects.components_from_your_lab">Components from your lab</p><div class="flex flex-wrap gap-1.5">${stock}</div></div>` : ''}
      ${missing ? `<div class="mb-5"><p class="text-xs text-slate-500 uppercase tracking-wider mb-2" data-i18n-text="projects.parts_to_acquire">Parts to acquire</p><div class="space-y-2">${missing}</div></div>` : ''}
      <form action="project_blueprint.php" method="POST">
        <input type="hidden" name="title"       value="${esc(p.title)}">
        <input type="hidden" name="stock"       value="${esc(JSON.stringify(p.stock||[]))}">
        <input type="hidden" name="description" value="${esc(p.description||'')}">
        <button type="submit" class="w-full mt-1 py-2.5 rounded-xl text-sm font-semibold text-white btn-primary shadow-md shadow-purple-900/20">
          📐 <span data-i18n-text="projects.generate_full_blueprint">Generate Full Blueprint &amp; Code</span>
        </button>
      </form>
    </div>`;
  });

  html += '</div>';
  document.getElementById('results-area').innerHTML = html;
  localizationController.applyTranslations();
}

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
localizationController.init();
</script>
</body>
</html>
