<?php
/**
 * settings.php — User Settings (Phase 4+)
 * Manage API provider, key, and lab password.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$message      = '';
$message_type = 'success';
$pw_message   = '';
$pw_type      = 'success';

// --- AI provider / API key save ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_config') {
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

// --- Change password ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current  = $_POST['current_password'] ?? '';
    $new_pw   = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    // Load stored hash (or fall back to default '1234')
    $row = $pdo->query("SELECT setting_value FROM settings WHERE setting_key = 'lab_password'")->fetchColumn();
    $use_hash = !empty($row);
    $current_ok = $use_hash ? password_verify($current, $row) : ($current === '1234');

    if (!$current_ok) {
        $pw_message = 'Current password is incorrect.';
        $pw_type    = 'error';
    } elseif (strlen($new_pw) < 6) {
        $pw_message = 'New password must be at least 6 characters.';
        $pw_type    = 'error';
    } elseif ($new_pw !== $confirm) {
        $pw_message = 'Passwords do not match.';
        $pw_type    = 'error';
    } else {
        $hash = password_hash($new_pw, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('lab_password', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        $stmt->execute([$hash, $hash]);
        $pw_message = 'Password changed successfully!';
        $pw_type    = 'success';
    }
}

// --- Save personalization ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_personalization') {
    $lab_name         = trim($_POST['lab_name'] ?? 'DIY Lab') ?: 'DIY Lab';
    $lab_tagline      = trim($_POST['lab_tagline'] ?? 'Inventory & AI Orchestrator');
    $lab_mini_tagline = trim($_POST['lab_mini_tagline'] ?? 'Inventory System');
    $lab_logo_url     = trim($_POST['lab_logo_url'] ?? '');
    foreach ([
        'lab_name'         => $lab_name,
        'lab_tagline'      => $lab_tagline,
        'lab_mini_tagline' => $lab_mini_tagline,
        'lab_logo_url'     => $lab_logo_url,
    ] as $key => $val) {
        $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")
            ->execute([$key, $val, $val]);
    }
    $message = 'Personalization saved!';
}

// Fetch current settings
$settings = [];
foreach ($pdo->query("SELECT setting_key, setting_value FROM settings")->fetchAll() as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Auto-migrate: seed lab_password with hash of '1234' if not yet stored
if (empty($settings['lab_password'])) {
    $default_hash = password_hash('1234', PASSWORD_BCRYPT);
    $pdo->prepare("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('lab_password', ?)")
        ->execute([$default_hash]);
    $settings['lab_password'] = $default_hash;
}

// Load site personalization vars (uses already-loaded $settings)
require_once 'site_config.php';

$current_provider = $settings['ai_provider'] ?? 'gemini';
$has_key          = !empty($settings['api_key']);

?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Settings — DIY Lab</title>
  <meta name="description" content="Manage your AI provider, API credentials, personalization, and security settings for the DIY Lab.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
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
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.creative_engine"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707"/></svg> Creative Engine</a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.lab_assistant"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
      <a href="settings.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium" data-i18n="nav.user_settings"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> User Settings</a>
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
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4">
      <div class="flex items-center gap-2">
        <button onclick="openSidebar()" class="lg:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
          <h1 class="text-lg lg:text-xl font-bold text-white">User Settings</h1>
          <p class="text-xs text-slate-500 mt-0.5">Manage your Lab, AI provider, and security</p>
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
        <input type="hidden" name="action" value="save_config">

        <!-- Language Selection -->
        <div>
          <label class="form-label" data-i18n-text="settings.lang_section">Language</label>
          <div class="relative">
            <select id="lang-select"
              class="input-field w-full rounded-xl px-4 py-3 text-sm appearance-none cursor-pointer"
              onchange="localizationController.loadLocale(this.value)">
              <option value="en">🇬🇧 English</option>
              <option value="he">🇮🇱 עברית (Hebrew)</option>
              <option value="es">🇪🇸 Español (Spanish)</option>
            </select>
            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
          </div>
          <p class="text-xs text-slate-600 mt-1.5">Changes apply instantly and persist across sessions.</p>
        </div>

        <!-- Provider selection -->
        <div>
          <label class="form-label" data-i18n-text="settings.ai_provider">AI Provider</label>
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
          <label for="api_key" class="form-label" data-i18n-text="settings.api_key_label">API Key</label>
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
          💾 <span data-i18n-text="settings.save_config">Save Configuration</span>
        </button>
      </form>

      <!-- Help -->
      <div class="glass rounded-2xl p-5 mt-6 space-y-3">
        <h3 class="text-sm font-semibold text-white" data-i18n-text="settings.where_to_get_key">Where to get your API key</h3>
        <div class="space-y-2 text-sm">
          <div class="flex items-center gap-3">
            <span class="text-blue-400 font-medium w-16">Gemini</span>
            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              aistudio.google.com/app/apikey &rarr;
            </a>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-emerald-400 font-medium w-16">OpenAI</span>
            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              platform.openai.com/api-keys &rarr;
            </a>
          </div>
        </div>
      </div>

      <!-- Personalization Section -->
      <div class="glass rounded-2xl p-6 mt-6">
        <!-- Section header -->
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 rounded-lg bg-purple-500/15 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-white">Personalization</p>
            <p class="text-xs text-slate-500 mt-0.5">Customize your Lab's identity and branding</p>
          </div>
        </div>

        <form method="POST" class="space-y-5" id="form-personalization">
          <input type="hidden" name="action" value="save_personalization">

          <!-- Logo URL -->
          <div>
            <label for="lab_logo_url" class="form-label">Logo URL
              <span class="text-slate-600 font-normal ml-1">(optional — leave blank for default icon)</span>
            </label>
            <div class="flex items-center gap-3">
              <!-- Live preview -->
              <div id="logo-preview-wrap" class="w-10 h-10 rounded-lg flex-shrink-0 overflow-hidden border border-white/10 bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
                <?php if (!empty($settings['lab_logo_url'])): ?>
                  <img id="logo-preview-img" src="<?= htmlspecialchars($settings['lab_logo_url']) ?>" alt="Logo" class="w-full h-full object-cover">
                <?php else: ?>
                  <svg id="logo-preview-svg" class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
                <?php endif; ?>
              </div>
              <input type="url" id="lab_logo_url" name="lab_logo_url"
                value="<?= htmlspecialchars($settings['lab_logo_url'] ?? '') ?>"
                placeholder="https://example.com/logo.png"
                class="input-field flex-1 rounded-xl px-4 py-3 text-sm"
                oninput="previewLogo(this.value)">
            </div>
            <p class="text-xs text-slate-600 mt-1.5">Displayed in the sidebar and login screen. Recommended: square image, at least 80×80 px.</p>
          </div>

          <!-- Lab Name -->
          <div>
            <label for="lab_name" class="form-label">Lab Name</label>
            <input type="text" id="lab_name" name="lab_name"
              value="<?= htmlspecialchars($settings['lab_name'] ?? 'DIY Lab') ?>"
              placeholder="DIY Lab"
              maxlength="60"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5">Shown in the sidebar and login screen header.</p>
          </div>

          <!-- Tag Line -->
          <div>
            <label for="lab_tagline" class="form-label">Tag Line
              <span class="text-slate-600 font-normal ml-1">(login screen)</span>
            </label>
            <input type="text" id="lab_tagline" name="lab_tagline"
              value="<?= htmlspecialchars($settings['lab_tagline'] ?? 'Inventory & AI Orchestrator') ?>"
              placeholder="Inventory & AI Orchestrator"
              maxlength="100"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5">The subtitle shown below the Lab Name on the login screen.</p>
          </div>

          <!-- Mini Tag Line -->
          <div>
            <label for="lab_mini_tagline" class="form-label">Mini Tag Line
              <span class="text-slate-600 font-normal ml-1">(sidebar)</span>
            </label>
            <input type="text" id="lab_mini_tagline" name="lab_mini_tagline"
              value="<?= htmlspecialchars($settings['lab_mini_tagline'] ?? 'Inventory System') ?>"
              placeholder="Inventory System"
              maxlength="60"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5">Short subtitle shown under the Lab Name in the sidebar.</p>
          </div>

          <button type="submit"
            class="w-full btn-primary py-3 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
            🎨 Save Personalization
          </button>
        </form>
      </div>

      <!-- Change Password Section -->

      <div class="glass rounded-2xl p-6 mt-6 border border-red-500/10">
        <!-- Section header -->
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 rounded-lg bg-red-500/15 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-white">Change Lab Password</p>
            <p class="text-xs text-slate-500 mt-0.5">Update your login credentials</p>
          </div>
        </div>

        <?php if ($pw_message): ?>
        <div class="mb-5 px-4 py-3 rounded-xl text-sm flex items-center gap-2
          <?= $pw_type === 'success'
            ? 'bg-emerald-500/10 border border-emerald-500/20 text-emerald-400'
            : 'bg-red-500/10 border border-red-500/20 text-red-400' ?>">
          <?php if ($pw_type === 'success'): ?>
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
          <?php else: ?>
            <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/></svg>
          <?php endif; ?>
          <?= htmlspecialchars($pw_message) ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-4" id="form-change-password">
          <input type="hidden" name="action" value="change_password">

          <!-- Current Password -->
          <div>
            <label for="current_password" class="form-label">Current Password</label>
            <div class="relative">
              <input type="password" id="current_password" name="current_password"
                placeholder="Enter your current password"
                autocomplete="current-password"
                class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm">
              <button type="button" onclick="togglePwField('current_password', 'eye-current')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                aria-label="Toggle current password visibility">
                <svg id="eye-current" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- New Password -->
          <div>
            <label for="new_password" class="form-label">New Password
              <span class="text-slate-600 font-normal ml-1">(min. 6 characters)</span>
            </label>
            <div class="relative">
              <input type="password" id="new_password" name="new_password"
                placeholder="Enter your new password"
                autocomplete="new-password"
                class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm">
              <button type="button" onclick="togglePwField('new_password', 'eye-new')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                aria-label="Toggle new password visibility">
                <svg id="eye-new" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
          </div>

          <!-- Confirm Password -->
          <div>
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="relative">
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your new password"
                autocomplete="new-password"
                class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm"
                oninput="checkPasswordMatch()">
              <button type="button" onclick="togglePwField('confirm_password', 'eye-confirm')"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors"
                aria-label="Toggle confirm password visibility">
                <svg id="eye-confirm" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
              </button>
            </div>
            <p id="pw-match-hint" class="text-xs mt-1.5 hidden"></p>
          </div>

          <button type="submit"
            class="w-full py-3 rounded-xl font-semibold text-sm text-white transition-all duration-200"
            style="background: linear-gradient(135deg, #dc2626, #b91c1c); box-shadow: 0 4px 20px rgba(220,38,38,.25);"
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'">
            🔐 Update Password
          </button>
        </form>
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

  // Shared password field visibility toggle (used by Change Password section)
  const EYE_OPEN  = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>';
  const EYE_CLOSE = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
  function togglePwField(inputId, eyeId) {
    const inp = document.getElementById(inputId);
    const eye = document.getElementById(eyeId);
    const isText = inp.type === 'text';
    inp.type = isText ? 'password' : 'text';
    eye.innerHTML = isText ? EYE_OPEN : EYE_CLOSE;
  }

  // Live password match hint
  function checkPasswordMatch() {
    const np    = document.getElementById('new_password').value;
    const cp    = document.getElementById('confirm_password').value;
    const hint  = document.getElementById('pw-match-hint');
    if (!cp) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (np === cp) {
      hint.textContent = '✓ Passwords match';
      hint.className = 'text-xs mt-1.5 text-emerald-400';
    } else {
      hint.textContent = '✗ Passwords do not match';
      hint.className = 'text-xs mt-1.5 text-red-400';
    }
  }
  // Live logo URL preview
  function previewLogo(url) {
    const wrap = document.getElementById('logo-preview-wrap');
    if (!url) {
      wrap.innerHTML = '<svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>';
      return;
    }
    const img = new Image();
    img.onload  = () => { wrap.innerHTML = `<img src="${url}" class="w-full h-full object-cover" alt="Logo">`; };
    img.onerror = () => { wrap.innerHTML = '<svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>'; };
    img.src = url;
  }
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  // Initialise i18n engine
  localizationController.init();
  </script>
</body>
</html>
