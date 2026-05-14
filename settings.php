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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>AI Settings — DIY Lab</title>
  <meta name="description" content="Configure your AI provider and API credentials for the DIY Lab Inventory system.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family:'Inter',sans-serif; background-color:#0a0a1a; }
    .bg-grid { background-image:linear-gradient(rgba(124,58,237,.04) 1px,transparent 1px),linear-gradient(90deg,rgba(124,58,237,.04) 1px,transparent 1px); background-size:40px 40px; }
    .glass { background:rgba(255,255,255,.03); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,.07); }
    .input-field { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1); color:#e2e8f0; transition:border-color .2s,box-shadow .2s; }
    .input-field:focus { outline:none; border-color:#7c3aed; box-shadow:0 0 0 3px rgba(124,58,237,.2); }
    .input-field::placeholder { color:#4b5563; }
    .btn-primary { background:linear-gradient(135deg,#7c3aed,#06b6d4); transition:all .2s; }
    .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
    .nav-link { color:#94a3b8; transition:color .2s; }
    .nav-link:hover { color:#c4b5fd; }
    .provider-card {
      border:2px solid rgba(255,255,255,.07); border-radius:16px; padding:1.25rem; cursor:pointer;
      transition:all .2s; background:rgba(255,255,255,.03);
    }
    .provider-card.selected { border-color:#7c3aed; background:rgba(124,58,237,.1); }
    .provider-card:hover:not(.selected) { border-color:rgba(255,255,255,.15); }
    label.form-label { display:block; font-size:.75rem; font-weight:500; color:#94a3b8; margin-bottom:.4rem; text-transform:uppercase; letter-spacing:.05em; }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

  <!-- Sidebar -->
  <div class="fixed inset-y-0 left-0 w-60 glass border-r border-white/5 flex flex-col z-40">
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
  </div>

  <!-- Main -->
  <main class="ml-60 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-8 py-4">
      <h1 class="text-xl font-bold text-white">AI Configuration</h1>
      <p class="text-xs text-slate-500 mt-0.5">Manage your AI provider and credentials</p>
    </header>

    <div class="p-8 max-w-xl">

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
          <div class="grid grid-cols-2 gap-3" id="provider-cards">
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
  </script>
</body>
</html>
