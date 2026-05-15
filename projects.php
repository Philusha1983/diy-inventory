<?php
/**
 * projects.php — Creative Engine / Project Discovery (Phase 7)
 * Analyses current inventory via AI and returns project ideas.
 */
require 'db.php';
require 'ai_helper.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';

// Fetch inventory
$stmt = $pdo->query("SELECT name, model, category, quantity, specs FROM inventory ORDER BY category, name");
$items = $stmt->fetchAll();

$inventory_context = '';
foreach ($items as $i) {
    $inventory_context .= "- {$i['name']} ({$i['model']}), Qty: {$i['quantity']}, Category: {$i['category']}, Specs: {$i['specs']}\n";
}

$ai_results = null;
$ai_error   = '';
$is_loading = false;

if (isset($_POST['discover'])) {
    $is_loading = true;

    $prompt = <<<PROMPT
You are an expert DIY electronics project advisor.
Review the following lab inventory:

$inventory_context

Suggest exactly 5 creative projects. For each, provide structured data.
Respond ONLY with a valid JSON array — no markdown, no extra text:
[
  {
    "title": "descriptive project title",
    "complexity": "Beginner|Intermediate|Expert",
    "duration": "e.g. 2-3 hours, Weekend project, Multi-day build",
    "skill_domain": "e.g. IoT, Robotics, Embedded Systems, Data Visualisation",
    "safety": "none|low voltage|high voltage|heat|LiPo batteries",
    "stock": ["list", "of", "components", "from", "inventory"],
    "missing": [
      {"part": "component name", "amazon": "https://www.amazon.com/s?k=component+name", "aliexpress": "https://www.aliexpress.com/wholesale?SearchText=component+name"}
    ],
    "description": "2-sentence description of the project"
  }
]
PROMPT;

    $response   = call_ai_api($prompt);
    $text       = extract_ai_text($response, $provider);

    if (!str_starts_with($text, '[AI Error]')) {
        $json_clean = clean_json_response($text);
        $ai_results = json_decode($json_clean, true);

        if (!$ai_results) {
            preg_match('/\[.*\]/s', $text, $m);
            $ai_results = $m ? json_decode($m[0], true) : null;
        }
        if (!$ai_results) {
            $ai_error = 'Could not parse AI response. Ensure your API key is configured in Settings.';
        }
    } else {
        $ai_error = $text;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Creative Engine — DIY Lab</title>
  <meta name="description" content="AI-powered project discovery based on your current component inventory.">
  <link rel="stylesheet" href="assets/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Inter',sans-serif; background-color:#0a0a1a; }
    .bg-grid { background-image:linear-gradient(rgba(124,58,237,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.04) 1px,transparent 1px); background-size:40px 40px; }
    .glass { background:rgba(255,255,255,.03); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.07); }
    .nav-link { color:#94a3b8; transition:color .2s; }
    .nav-link:hover,.nav-link.active { color:#c4b5fd; }
    .btn-primary { background:linear-gradient(135deg,#7c3aed,#06b6d4); transition:all .2s; }
    .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
    .project-card {
      background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07);
      border-radius:20px; padding:1.5rem; transition:all .25s;
      animation:fadeInUp .4s ease forwards;
    }
    .project-card:hover { border-color:rgba(124,58,237,.35); transform:translateY(-3px); box-shadow:0 12px 40px rgba(124,58,237,.12); }
    @keyframes fadeInUp { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }
    .complexity-beginner  { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.3); }
    .complexity-intermediate { background:rgba(251,191,36,.15);color:#fbbf24;border:1px solid rgba(251,191,36,.3);}
    .complexity-expert    { background:rgba(239,68,68,.15);  color:#f87171; border:1px solid rgba(239,68,68,.3); }
    .tag { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.08); color:#94a3b8; font-size:.7rem; padding:.2rem .6rem; border-radius:999px; }
    .spinner { display:inline-block; width:20px; height:20px; border:2px solid rgba(255,255,255,.2); border-top-color:white; border-radius:50%; animation:spin .7s linear infinite; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .big-cta {
      background:linear-gradient(135deg,rgba(124,58,237,.2),rgba(6,182,212,.1));
      border:2px dashed rgba(124,58,237,.3);
      border-radius:24px; padding:3rem; text-align:center;
      transition:all .3s;
    }
    .big-cta:hover { border-color:rgba(124,58,237,.6); background:linear-gradient(135deg,rgba(124,58,237,.25),rgba(6,182,212,.15)); }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
  <!-- Sidebar -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
        </div>
        <div><p class="font-semibold text-white text-sm">DIY Lab</p><p class="text-xs text-slate-500">Inventory System</p></div>
      </div>
    </div>
    <nav class="flex-1 p-4 space-y-1">
      <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard</a>
      <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component</a>
      <a href="projects.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> Creative Engine</a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings</a>
    </nav>
  </div>

  <!-- Main -->
  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
          <h1 class="text-lg lg:text-xl font-bold text-white">Creative Engine</h1>
          <p class="text-xs text-slate-500 mt-0.5">AI-powered project discovery from your inventory</p>
        </div>
      </div>
      <form method="POST" id="discover-form">
        <button type="submit" name="discover" id="discover-btn"
          class="btn-primary flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
          <span class="hidden sm:inline">Brainstorm Projects</span><span class="sm:hidden">Go</span>
        </button>
      </form>
    </header>

    <div class="p-4 lg:p-8">

      <?php if ($ai_error): ?>
      <div class="mb-6 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm">
        ❌ <?= htmlspecialchars($ai_error) ?>
        <?php if (str_contains($ai_error, 'API')): ?>
        <a href="settings.php" class="underline ml-1">Configure API key →</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if (empty($items)): ?>
      <div class="big-cta">
        <svg class="w-16 h-16 text-purple-500/30 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        <p class="text-slate-300 font-semibold mb-2">No inventory yet</p>
        <p class="text-slate-500 text-sm mb-4">Add components first, then come back to discover projects.</p>
        <a href="add_item.php" class="btn-primary inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-semibold text-white text-sm">+ Add Components</a>
      </div>

      <?php elseif (!$ai_results && !$ai_error): ?>
      <div class="big-cta cursor-pointer" onclick="document.getElementById('discover-btn').click()">
        <div class="text-5xl mb-4">🚀</div>
        <p class="text-white font-bold text-xl mb-2">Ready to Discover</p>
        <p class="text-slate-400 text-sm mb-6">
          <?= count($items) ?> components in your lab.<br>
          Click below to let AI suggest creative projects.
        </p>
        <div class="inline-flex items-center gap-2 px-6 py-3 rounded-xl font-semibold text-white btn-primary shadow-lg shadow-purple-900/30 text-sm">
          ✨ Generate Project Ideas
        </div>
        <p class="text-xs text-slate-600 mt-4">Takes 10-20 seconds · Uses Gemini/OpenAI</p>
      </div>

      <?php elseif ($ai_results): ?>
      <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500"><?= count($ai_results) ?> project ideas generated</p>
        <span class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 px-3 py-1 rounded-full">Powered by <?= ucfirst($provider) ?></span>
      </div>

      <div class="space-y-5">
        <?php foreach ($ai_results as $idx => $project): ?>
        <?php
          $complexity_key = strtolower($project['complexity'] ?? 'beginner');
          $complexity_class = match(true) {
              str_contains($complexity_key, 'beginner')     => 'complexity-beginner',
              str_contains($complexity_key, 'intermediate') => 'complexity-intermediate',
              str_contains($complexity_key, 'int')          => 'complexity-intermediate',
              str_contains($complexity_key, 'expert')       => 'complexity-expert',
              default => 'complexity-beginner',
          };
          $safety = strtolower($project['safety'] ?? 'none');
          $has_safety_warning = !in_array($safety, ['none', 'low voltage', '']);
        ?>
        <div class="project-card" style="animation-delay:<?= $idx * 0.08 ?>s">
          <div class="flex items-start justify-between gap-4 mb-3">
            <h2 class="text-lg font-bold text-white"><?= htmlspecialchars($project['title'] ?? '') ?></h2>
            <span class="<?= $complexity_class ?> text-xs px-3 py-1 rounded-full font-medium flex-shrink-0">
              <?= htmlspecialchars($project['complexity'] ?? 'Beginner') ?>
            </span>
          </div>

          <?php if (!empty($project['description'])): ?>
          <p class="text-sm text-slate-400 mb-4"><?= htmlspecialchars($project['description']) ?></p>
          <?php endif; ?>

          <div class="flex flex-wrap gap-2 mb-4">
            <?php if (!empty($project['duration'])): ?>
            <span class="tag">⏱ <?= htmlspecialchars($project['duration']) ?></span>
            <?php endif; ?>
            <?php if (!empty($project['skill_domain'])): ?>
            <span class="tag">🔧 <?= htmlspecialchars($project['skill_domain']) ?></span>
            <?php endif; ?>
            <?php if ($has_safety_warning): ?>
            <span class="tag" style="color:#f97316; border-color:rgba(249,115,22,.3);">⚡ <?= htmlspecialchars($project['safety']) ?></span>
            <?php endif; ?>
          </div>

          <?php if (!empty($project['stock'])): ?>
          <div class="mb-4">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Components from your lab</p>
            <div class="flex flex-wrap gap-1.5">
              <?php foreach ((array)$project['stock'] as $part): ?>
              <span class="text-xs bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 px-2.5 py-1 rounded-lg">✓ <?= htmlspecialchars($part) ?></span>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <?php if (!empty($project['missing'])): ?>
          <div class="mb-5">
            <p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Parts to acquire</p>
            <div class="space-y-2">
              <?php foreach ($project['missing'] as $m): ?>
              <div class="flex items-center justify-between bg-orange-500/5 border border-orange-500/15 rounded-lg px-3 py-2">
                <span class="text-sm text-slate-300">📦 <?= htmlspecialchars($m['part'] ?? '') ?></span>
                <div class="flex gap-2">
                  <?php if (!empty($m['amazon'])): ?>
                  <a href="<?= htmlspecialchars($m['amazon']) ?>" target="_blank" rel="noopener"
                     class="text-xs text-orange-400 hover:text-orange-300 border border-orange-500/20 px-2 py-1 rounded hover:border-orange-400/40 transition-all">
                    Amazon →
                  </a>
                  <?php endif; ?>
                  <?php if (!empty($m['aliexpress'])): ?>
                  <a href="<?= htmlspecialchars($m['aliexpress']) ?>" target="_blank" rel="noopener"
                     class="text-xs text-red-400 hover:text-red-300 border border-red-500/20 px-2 py-1 rounded hover:border-red-400/40 transition-all">
                    AliExpress →
                  </a>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

          <form action="project_blueprint.php" method="POST">
            <input type="hidden" name="title"       value="<?= htmlspecialchars($project['title'] ?? '') ?>">
            <input type="hidden" name="stock"       value="<?= htmlspecialchars(json_encode($project['stock'] ?? [])) ?>">
            <input type="hidden" name="description" value="<?= htmlspecialchars($project['description'] ?? '') ?>">
            <button type="submit"
              class="w-full mt-1 py-2.5 rounded-xl text-sm font-semibold text-white btn-primary shadow-md shadow-purple-900/20">
              📐 Generate Full Blueprint &amp; Code
            </button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </main>

  <script>
  document.getElementById('discover-form').addEventListener('submit', function () {
    const btn = document.getElementById('discover-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Analysing…';
  });
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  </script>
</body>
</html>
