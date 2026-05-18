<?php
/**
 * project_blueprint.php — Full Blueprint & Code Generator (Phase 7)
 * Generates step-by-step assembly guide + firmware code for a selected project.
 */
require 'db.php';
require 'ai_helper.php';
require_once 'site_config.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['title'])) {
    header('Location: projects.php');
    exit;
}

$project_title = htmlspecialchars($_POST['title']);
$project_desc  = htmlspecialchars($_POST['description'] ?? '');

// Fetch full inventory for context
$stmt  = $pdo->query("SELECT name, model, specs FROM inventory ORDER BY category, name");
$items = $stmt->fetchAll();
$context = '';
foreach ($items as $i) {
    $context .= "- {$i['name']} ({$i['model']}): {$i['specs']}\n";
}

$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$provider = $settings['ai_provider'] ?? 'gemini';

$prompt = <<<PROMPT
Generate a comprehensive technical blueprint for this DIY project: "$project_title"

Available components from my inventory:
$context

Provide a detailed technical guide formatted in clean Markdown with:

## 📋 Project Overview
A concise summary with key objectives.

## 🛠 Required Components
Table with component name, quantity, and role in the project.

## 🔌 Wiring & Circuit Diagram
Step-by-step wiring instructions with specific pin numbers. Use a text-based ASCII or table diagram.

## 📝 Step-by-Step Assembly Guide
Numbered steps with clear instructions. Include photos hints where helpful.

## 💻 Complete Code
Full, production-ready code block (Arduino C++ or MicroPython) with:
- All pin definitions matching the wiring above
- Comments explaining key sections
- Any necessary library imports

## 🧪 Testing & Troubleshooting
How to test the build and solve common issues.
PROMPT;

$response      = call_ai_api($prompt);
$guide_markdown = extract_ai_text($response, $provider);
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $project_title ?> — Blueprint</title>
  <meta name="description" content="AI-generated technical blueprint for <?= $project_title ?>.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    /* Markdown rendering styles */
    .markdown-body { color:#cbd5e1; line-height:1.75; }
    .markdown-body h1,.markdown-body h2 { color:#f1f5f9; font-weight:700; margin:1.75rem 0 .75rem; padding-bottom:.5rem; border-bottom:1px solid rgba(255,255,255,.07); }
    .markdown-body h1 { font-size:1.5rem; }
    .markdown-body h2 { font-size:1.2rem; }
    .markdown-body h3 { color:#e2e8f0; font-weight:600; margin:1.25rem 0 .5rem; font-size:1rem; }
    .markdown-body p { margin:.75rem 0; }
    .markdown-body ul,.markdown-body ol { margin:.75rem 0 .75rem 1.5rem; }
    .markdown-body li { margin:.3rem 0; }
    .markdown-body code { font-family:'JetBrains Mono',monospace; font-size:.8rem; background:rgba(124,58,237,.15); border:1px solid rgba(124,58,237,.2); color:#c4b5fd; padding:.15rem .4rem; border-radius:.3rem; }
    .markdown-body pre { background:#0d0d1f; border:1px solid rgba(124,58,237,.25); border-left:3px solid #7c3aed; border-radius:12px; padding:1.25rem; overflow-x:auto; margin:1.25rem 0; }
    .markdown-body pre code { background:none; border:none; color:#a5f3fc; padding:0; font-size:.82rem; }
    .markdown-body table { width:100%; border-collapse:collapse; margin:1rem 0; font-size:.9rem; }
    .markdown-body th { background:rgba(124,58,237,.15); color:#e2e8f0; font-weight:600; }
    .markdown-body th,.markdown-body td { border:1px solid rgba(255,255,255,.08); padding:.6rem 1rem; text-align:left; }
    .markdown-body tr:nth-child(even) td { background:rgba(255,255,255,.02); }
    .markdown-body blockquote { border-left:3px solid #7c3aed; padding:.75rem 1rem; margin:1rem 0; background:rgba(124,58,237,.07); border-radius:0 8px 8px 0; color:#94a3b8; }
    .markdown-body strong { color:#f1f5f9; }
    .markdown-body a { color:#67e8f9; text-decoration:underline; }
    .markdown-body hr { border:none; border-top:1px solid rgba(255,255,255,.08); margin:1.5rem 0; }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
  <!-- Sidebar (compact) -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
        </div>
        <?php if (!empty($site_logo_url)): ?>
          <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" class="w-9 h-9 rounded-lg object-cover flex-shrink-0">
        <?php else: ?>
          <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
          </div>
        <?php endif; ?>
        <div>
          <p class="font-semibold text-white text-sm"><?= htmlspecialchars($site_name) ?></p>
          <p class="text-xs text-slate-500"><?= htmlspecialchars($site_mini_tagline) ?></p>
        </div>
      </div>
    </div>
    <nav class="flex-1 p-4 space-y-1">
      <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.dashboard"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard</a>
      <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.add_component"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component</a>
      <a href="locations.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.locations"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> Locations</a>
      <a href="projects.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium" data-i18n="nav.creative_engine"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707"/></svg> Creative Engine</a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.lab_assistant"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.user_settings"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> User Settings</a>
    </nav>
    <div class="p-4 border-t border-white/5">
      <div class="theme-toggle-wrap mb-2" onclick="toggleTheme()" role="button" aria-label="Toggle light mode" title="Toggle light/dark mode">
        <span class="theme-toggle-label">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
          <span data-i18n-text="nav.light_mode">Light Mode</span>
        </span>
        <span class="toggle-pill"></span>
      </div>
      <a href="dashboard.php?logout=1" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition-colors text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        <span data-i18n-text="nav.logout">Logout</span>
      </a>
    </div>
  </div>

  <!-- Main -->
  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="projects.php" class="flex-shrink-0 text-slate-500 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div class="min-w-0">
          <h1 class="text-base lg:text-lg font-bold text-white truncate">📐 <?= $project_title ?></h1>
          <p class="text-xs text-slate-500">AI-Generated Blueprint</p>
        </div>
      </div>
      <button onclick="window.print()" class="btn-primary flex-shrink-0 text-sm px-3 lg:px-4 py-2 rounded-xl text-white font-medium flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span class="hidden sm:inline">Print / Save PDF</span>
      </button>
    </header>

    <div class="p-4 lg:p-8 max-w-4xl">
      <div class="glass rounded-2xl p-8 markdown-body" id="blueprint-content">
        <p class="text-slate-500 text-sm">Loading blueprint…</p>
      </div>

      <div class="mt-6 flex gap-3">
        <a href="projects.php" class="text-sm text-slate-400 hover:text-white border border-white/10 hover:border-white/20 px-4 py-2 rounded-xl transition-all">
          ← Back to Projects
        </a>
        <a href="chat.php" class="btn-primary text-sm text-white px-4 py-2 rounded-xl font-medium">
          💬 Discuss with Lab Assistant
        </a>
      </div>
    </div>
  </main>

  <script>
  // Raw markdown from PHP
  const rawMarkdown = <?= json_encode($guide_markdown) ?>;
  document.getElementById('blueprint-content').innerHTML = marked.parse(rawMarkdown);
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  localizationController.init();
  </script>
</body>
</html>
