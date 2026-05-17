<?php
/**
 * settings.php — AI Configuration (Phase 4)
 * Manage API provider and key without touching code.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$message      = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provider = $_POST['ai_provider'] ?? 'gemini';
    $api_key  = trim($_POST['api_key'] ?? '');

    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('ai_provider', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$provider, $provider]);

    if ($api_key !== '') {
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('api_key', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$api_key, $api_key]);
    }

    $message = 'Settings saved successfully!';
}

// Fetch current settings
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$current_provider = $settings['ai_provider'] ?? 'gemini';
$has_key          = !empty($settings['api_key']);
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Settings — DIY Lab</title>
  <meta name="description" content="Configure your AI provider and API credentials for the DIY Lab Inventory system.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .provider-card {
      border:2px solid rgba(255,255,255,.07); border-radius:16px; padding:1.25rem; cursor:pointer;
      transition:all .2s; background:rgba(255,255,255,.03);
    }
    .provider-card.selected { border-color:#7c3aed; background:rgba(124,58,237,.1); }
    .provider-card:hover:not(.selected) { border-color:rgba(255,255,255,.15); }
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
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707"/></svg> Creative Engine</a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
      <a href="settings.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings</a>
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

  <!-- Main -->
  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4">
      <div class="flex items-center gap-2">
        <button onclick="openSidebar()" class="lg:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
          <h1 class="text-lg lg:text-xl font-bold text-white">AI Configuration</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manage your AI provider and credentials</p>
        </div>
      </div>
    </header>

    <div class="p-4 lg:p-8 max-w-xl">

      <?php if ($message): ?>
      <div class="mb-6 px-4 py-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-center gap-2">
        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
        <?= htmlspecialchars($message) ?>
      </div>
      <?php endif; ?>

      <!-- Status badge -->
      <div class="glass rounded-2xl p-5 mb-6 flex items-center justify-between">
        <div>
          <p class="text-sm font-medium text-white">API Status</p>
          <p class="text-xs text-slate-500 mt-0.5">
            Provider: <span class="text-slate-300"><?= ucfirst($current_provider) ?></span>
          </p>
        </div>
        <?php if ($has_key): ?>
        <span class="flex items-center gap-1.5 text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1.5 rounded-full">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> API Key Saved
        </span>
        <?php else: ?>
        <span class="flex items-center gap-1.5 text-xs text-red-400 bg-red-500/10 border border-red-500/20 px-3 py-1.5 rounded-full">
          <span class="w-2 h-2 rounded-full bg-red-400"></span> No API Key
        </span>
        <?php endif; ?>
      </div>

      <form method="POST" class="glass rounded-2xl p-6 space-y-6">

        <!-- Provider selection -->
        <div>
          <label class="form-label">AI Provider</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" id="provider-cards">
            <label class="provider-card <?= $current_provider === 'gemini' ? 'selected' : '' ?>" data-provider="gemini">
              <input type="radio" name="ai_provider" value="gemini" <?= $current_provider === 'gemini' ? 'checked' : '' ?> class="hidden">
              <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-blue-500/20 flex items-center justify-center">
                  <span class="text-lg">G</span>
                </div>
                <div>
                  <p class="font-semibold text-white text-sm">Gemini</p>
                  <p class="text-xs text-slate-500">Google</p>
                </div>
              </div>
              <p class="text-xs text-slate-500">gemini-1.5-flash — Vision + text</p>
            </label>
            <label class="provider-card <?= $current_provider === 'openai' ? 'selected' : '' ?>" data-provider="openai">
              <input type="radio" name="ai_provider" value="openai" <?= $current_provider === 'openai' ? 'checked' : '' ?> class="hidden">
              <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <span class="text-lg">⊕</span>
                </div>
                <div>
                  <p class="font-semibold text-white text-sm">OpenAI</p>
                  <p class="text-xs text-slate-500">GPT-4o</p>
                </div>
              </div>
              <p class="text-xs text-slate-500">gpt-4o — Vision + text</p>
            </label>
          </div>
        </div>

        <!-- API Key -->
        <div>
          <label for="api_key" class="form-label">API Key</label>
          <div class="relative">
            <input type="password" id="api_key" name="api_key"
              placeholder="<?= $has_key ? '●●●●●●●●●●●● (key saved — re-enter to change)' : 'Enter your API key…' ?>"
              autocomplete="off"
              class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm font-mono">
            <button type="button" id="toggle-key" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors" aria-label="Toggle API key visibility">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
          <p class="text-xs text-slate-600 mt-1.5">Key is stored in your database, not in code. Leave blank to keep existing key.</p>
        </div>

        <button type="submit" class="w-full btn-primary py-3 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
          💾 Save Configuration
        </button>
      </form>

      <!-- Help -->
      <div class="glass rounded-2xl p-5 mt-6 space-y-3">
        <h3 class="text-sm font-semibold text-white">Where to get your API key</h3>
        <div class="space-y-2 text-sm">
          <div class="flex items-center gap-3">
            <span class="text-blue-400 font-medium w-16">Gemini</span>
            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              aistudio.google.com/app/apikey →
            </a>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-emerald-400 font-medium w-16">OpenAI</span>
            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              platform.openai.com/api-keys →
            </a>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
  // Provider card selection
  document.querySelectorAll('.provider-card').forEach(card => {
    card.addEventListener('click', () => {
      document.querySelectorAll('.provider-card').forEach(c => c.classList.remove('selected'));
      card.classList.add('selected');
      card.querySelector('input[type="radio"]').checked = true;
    });
  });

  // Toggle API key visibility
  document.getElementById('toggle-key').addEventListener('click', () => {
    const inp = document.getElementById('api_key');
    inp.type = inp.type === 'password' ? 'text' : 'password';
  });
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  </script>
</body>
</html>
