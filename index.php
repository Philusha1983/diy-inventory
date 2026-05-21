<?php
/**
 * index.php — Password Gate (Phase 1)
 * The sole entry point to the application.
 */
session_start();

// Already authenticated → go straight to dashboard
if (isset($_SESSION['authenticated'])) {
    header('Location: dashboard.php');
    exit;
}

// Load DB to read the lab password + personalization from settings table
require 'db.php';
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('lab_password','lab_name','lab_tagline','lab_logo_url')");
$_idx = [];
if ($stmt) foreach ($stmt->fetchAll() as $r) $_idx[$r['setting_key']] = $r['setting_value'];

$stored_hash = $_idx['lab_password'] ?? null;
$use_hash    = !empty($stored_hash);

// Personalization
$site_name     = !empty($_idx['lab_name'])    ? $_idx['lab_name']    : 'DIY Lab';
$site_tagline  = !empty($_idx['lab_tagline']) ? $_idx['lab_tagline'] : 'Inventory & AI Orchestrator';
$site_logo_url = $_idx['lab_logo_url'] ?? '';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $entered = $_POST['pass'] ?? '';
    $valid   = $use_hash
        ? password_verify($entered, $stored_hash)
        : ($entered === '1234');
    if ($valid) {
        $_SESSION['authenticated'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Incorrect password. Try again.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DIY Lab — Login</title>
  <meta name="description" content="Secure access portal for the DIY Lab Inventory & AI Orchestrator.">
  <link rel="stylesheet" href="assets/app.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    /* Login page overrides — glass is more opaque here for better contrast */
    .glass {
      background: rgba(255,255,255,.04);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid rgba(255,255,255,.08);
    }
    .glow-purple {
      box-shadow: 0 0 60px rgba(124,58,237,.35), 0 0 120px rgba(124,58,237,.12);
    }
    @keyframes float {
      0%, 100% { transform: translateY(0); }
      50%       { transform: translateY(-12px); }
    }
    .float-anim { animation: float 5s ease-in-out infinite; }
    @keyframes pulse-ring {
      0%   { transform: scale(.9); opacity: 1; }
      100% { transform: scale(1.5); opacity: 0; }
    }
    .pulse-ring {
      position: absolute; inset: -6px; border-radius: 50%;
      border: 2px solid rgba(124,58,237,.5);
      animation: pulse-ring 2s ease-out infinite;
    }
  </style>
</head>
<body class="bg-grid min-h-screen flex items-center justify-center p-4">

  <!-- Ambient orbs -->
  <div class="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
    <div class="absolute -top-40 -left-40 w-80 h-80 rounded-full bg-purple-700/20 blur-3xl"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 rounded-full bg-cyan-500/15 blur-3xl"></div>
  </div>

  <div class="relative w-full max-w-md">

    <!-- Logo / icon -->
    <div class="flex justify-center mb-8">
      <div class="relative float-anim">
        <div class="pulse-ring"></div>
        <?php if (!empty($site_logo_url)): ?>
          <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo"
            class="w-20 h-20 rounded-2xl object-cover glow-purple">
        <?php else: ?>
        <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center glow-purple">
          <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082M19.8 15.3l-1.57.393A9.065 9.065 0 0112 15a9.065 9.065 0 00-6.23-.693L5 14.5m14.8.8l1.402 1.402c1 1 .03 2.798-1.332 2.798H4.13c-1.362 0-2.332-1.798-1.332-2.798L4 14.5"/>
          </svg>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Card -->
    <div class="glass rounded-3xl p-8 glow-purple">
      <div class="text-center mb-8">
        <h1 class="text-3xl font-bold text-white mb-2"><?= htmlspecialchars($site_name) ?></h1>
        <p class="text-slate-400 text-sm"><?= htmlspecialchars($site_tagline) ?></p>
      </div>

      <?php if ($error): ?>
      <div class="mb-5 px-4 py-3 rounded-xl bg-red-500/15 border border-red-500/30 text-red-400 text-sm flex items-center gap-2">
        <svg class="w-4 h-4 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
          <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd"/>
        </svg>
        <?= htmlspecialchars($error) ?>
      </div>
      <?php endif; ?>

      <form method="POST" action="" novalidate>
        <div class="mb-6">
          <label for="pass" class="block text-sm font-medium text-slate-300 mb-2" data-i18n-text="index.lab_password">
            Lab Password
          </label>
          <div class="relative">
            <input
              type="password"
              id="pass"
              name="pass"
              placeholder="••••••••"
              required
              autocomplete="current-password"
              class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm"
            >
            <button type="button" id="toggle-pass" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors" aria-label="Toggle password visibility">
              <svg id="eye-icon" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
            </button>
          </div>
        </div>

        <button
          type="submit"
          name="login"
          id="btn-enter"
          class="w-full py-3 rounded-xl font-semibold text-white bg-gradient-to-r from-purple-600 to-cyan-500 hover:from-purple-500 hover:to-cyan-400 transition-all duration-300 transform hover:scale-[1.02] active:scale-[.98] shadow-lg shadow-purple-900/40"
         data-i18n-text="index.enter_lab">
          <span data-i18n-text="index.enter_lab">Enter Lab</span>
        </button>
      </form>

      <p class="text-center text-slate-600 text-xs mt-6">
        Self-hosted · Powered by Gemini &amp; OpenAI
      </p>
    </div>
  </div>

  <script>
    // Toggle password visibility
    document.getElementById('toggle-pass').addEventListener('click', function () {
      const input = document.getElementById('pass');
      const isText = input.type === 'text';
      input.type = isText ? 'password' : 'text';
      document.getElementById('eye-icon').innerHTML = isText
        ? '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>'
        : '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>';
    });
  </script>
</body>
</html>
