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
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
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
  <?php include 'includes/sidebar.php'; ?>


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
          <p class="text-xs text-slate-500" data-i18n-text="project_blueprint.ai_generated_blueprint">AI-Generated Blueprint</p>
        </div>
      </div>
      <button onclick="window.print()" class="btn-primary flex-shrink-0 text-sm px-3 lg:px-4 py-2 rounded-xl text-white font-medium flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        <span class="hidden sm:inline" data-i18n-text="project_blueprint.print_save_pdf">Print / Save PDF</span>
      </button>
    </header>

    <div class="p-4 lg:p-8 max-w-4xl">
      <div class="glass rounded-2xl p-8 markdown-body" id="blueprint-content">
        <p class="text-slate-500 text-sm" data-i18n-text="project_blueprint.loading_blueprint">Loading blueprint…</p>
      </div>

      <div class="mt-6 flex gap-3">
        <a href="projects.php" class="text-sm text-slate-400 hover:text-white border border-white/10 hover:border-white/20 px-4 py-2 rounded-xl transition-all" data-i18n-text="project_blueprint.back_to_projects">
          ← Back to Projects
        </a>
        <a href="chat.php" class="btn-primary text-sm text-white px-4 py-2 rounded-xl font-medium" data-i18n-text="project_blueprint.discuss_with_lab_assistant">
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
