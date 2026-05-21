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

// --- Save integrations ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_integrations') {
    $admin_email = trim($_POST['admin_email'] ?? '');
    $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('admin_email', ?) ON DUPLICATE KEY UPDATE setting_value = ?")->execute([$admin_email, $admin_email]);
    $message = 'Bug reporting settings saved successfully!';
}

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

    // ── Handle logo file upload (overrides URL field if a file was chosen) ────
    if (!empty($_FILES['lab_logo_file']['tmp_name']) && $_FILES['lab_logo_file']['error'] === UPLOAD_ERR_OK) {
        $file     = $_FILES['lab_logo_file'];
        $allowed  = ['image/jpeg','image/png','image/webp','image/gif'];
        $mime     = mime_content_type($file['tmp_name']);

        if (!in_array($mime, $allowed, true)) {
            $message = 'Logo upload failed: only JPEG, PNG, WebP, or GIF images are allowed.';
            goto skip_personalization_save;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $message = 'Logo upload failed: file must be under 5 MB.';
            goto skip_personalization_save;
        }

        @ini_set('memory_limit', '256M');
        $raw = @file_get_contents($file['tmp_name']);
        $src = @imagecreatefromstring($raw);
        if (!$src) {
            $message = 'Logo upload failed: could not decode image.';
            goto skip_personalization_save;
        }

        // Crop to square (centre crop), then resize to 256×256
        $orig_w = imagesx($src);
        $orig_h = imagesy($src);
        $side   = min($orig_w, $orig_h);
        $off_x  = (int)(($orig_w - $side) / 2);
        $off_y  = (int)(($orig_h - $side) / 2);

        $square = imagecreatetruecolor(256, 256);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        $white = imagecolorallocate($square, 255, 255, 255);
        imagefill($square, 0, 0, $white);
        imagecopyresampled($square, $src, 0, 0, $off_x, $off_y, 256, 256, $side, $side);

        $logo_dir = 'uploads/logo/';
        if (!is_dir($logo_dir)) mkdir($logo_dir, 0755, true);

        // Delete previous uploaded logo if it exists
        $prev = $settings['lab_logo_url'] ?? '';
        if ($prev && str_starts_with($prev, 'uploads/logo/') && file_exists($prev)) {
            @unlink($prev);
        }

        $dest = $logo_dir . 'logo_' . time() . '.jpg';
        imagejpeg($square, $dest, 90);
        $lab_logo_url = $dest;  // store relative path
    }

    // ── Handle "Remove logo" request ──────────────────────────────────────────
    if (!empty($_POST['remove_logo'])) {
        $prev = $settings['lab_logo_url'] ?? '';
        if ($prev && str_starts_with($prev, 'uploads/logo/') && file_exists($prev)) {
            @unlink($prev);
        }
        $lab_logo_url = '';
    }

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
    skip_personalization_save:
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
  <?php include 'includes/sidebar.php'; ?>


  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4">
      <div class="flex items-center gap-2">
        <button onclick="openSidebar()" class="lg:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div>
          <h1 class="text-lg lg:text-xl font-bold text-white" data-i18n-text="settings.user_settings">User Settings</h1>
          <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="settings.manage_your_lab_ai_provider_an">Manage your Lab, AI provider, and security</p>
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

      <!-- 1. Language -->
      <div class="glass rounded-2xl p-6 mb-6">
        <div class="flex items-center gap-3 mb-4">
          <div class="w-8 h-8 rounded-lg bg-cyan-500/15 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-cyan-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-white" data-i18n-text="settings.lang_section">Language</p>
            <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="settings.interface_language_changes_app">Interface language — changes apply instantly</p>
          </div>
        </div>
        <div class="relative">
          <select id="lang-select"
            class="input-field w-full rounded-xl px-4 py-3 text-sm appearance-none cursor-pointer"
            onchange="localizationController.loadLocale(this.value)">
            <option value="en">🇬🇧 English</option>
            <option value="uk">🇺🇦 Українська (Ukrainian)</option>
            <option value="he">🇮🇱 עברית (Hebrew)</option>
            <option value="es">🇪🇸 Español (Spanish)</option>
          </select>
          <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500 pointer-events-none" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
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
            <p class="text-sm font-semibold text-white" data-i18n-text="settings.personalization">Personalization</p>
            <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="settings.customize_your_lab_s_identity_">Customize your Lab's identity and branding</p>
          </div>
        </div>

        <form method="POST" enctype="multipart/form-data" class="space-y-5" id="form-personalization">
          <input type="hidden" name="action" value="save_personalization">
          <input type="hidden" name="remove_logo" id="remove_logo_flag" value="">

          <!-- ── Logo Upload ─────────────────────────────────────────── -->
          <div>
            <label class="form-label" data-i18n-text="settings.lab_logo">Lab Logo</label>

            <!-- Current logo + remove -->
            <?php $cur_logo = $settings['lab_logo_url'] ?? ''; ?>
            <div id="logo-current-wrap" class="<?= empty($cur_logo) ? 'hidden' : '' ?> mb-3 flex items-center gap-3 p-3 rounded-xl bg-white/5 border border-white/8">
              <div id="logo-current-thumb" class="w-16 h-16 rounded-xl overflow-hidden flex-shrink-0 border border-white/10 bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
                <?php if (!empty($cur_logo)): ?>
                  <img id="logo-current-img" src="<?= htmlspecialchars($cur_logo) ?>" alt="Current logo" class="w-full h-full object-cover">
                <?php endif; ?>
              </div>
              <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-white" data-i18n-text="settings.current_logo">Current logo</p>
                <p class="text-xs text-slate-500 truncate mt-0.5"><?= htmlspecialchars(basename($cur_logo)) ?></p>
              </div>
              <button type="button" id="btn-remove-logo"
                onclick="removeLogo()"
                class="flex-shrink-0 text-xs text-red-400 hover:text-red-300 border border-red-500/30 hover:border-red-500/60 px-3 py-1.5 rounded-lg transition-all" data-i18n-text="settings.remove">
                🗑️ Remove
              </button>
            </div>

            <!-- Drop zone -->
            <div id="logo-dropzone"
              class="relative flex flex-col items-center justify-center gap-2 border-2 border-dashed border-white/15 rounded-xl p-6 cursor-pointer transition-all duration-200 hover:border-purple-500/60 hover:bg-purple-500/5"
              onclick="document.getElementById('lab_logo_file').click()"
              ondragover="event.preventDefault();this.classList.add('border-purple-500/70','bg-purple-500/8')"
              ondragleave="this.classList.remove('border-purple-500/70','bg-purple-500/8')"
              ondrop="handleLogoDrop(event)">
              <!-- Preview inside dropzone (shown after file pick) -->
              <div id="dz-preview" class="hidden flex-col items-center gap-2">
                <img id="dz-preview-img" src="" alt="Preview" class="w-20 h-20 rounded-xl object-cover border border-white/15 shadow-lg">
                <p id="dz-preview-name" class="text-xs text-slate-400 max-w-full truncate"></p>
              </div>
              <!-- Placeholder (hidden when preview shown) -->
              <div id="dz-placeholder" class="flex flex-col items-center gap-2">
                <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center">
                  <svg class="w-6 h-6 text-purple-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                  </svg>
                </div>
                <p class="text-sm text-slate-400"><span data-i18n-text="settings.drop_an_image_or">Drop an image or</span> <span class="text-purple-400 font-medium" data-i18n-text="settings.click_to_browse">click to browse</span></p>
                <p class="text-xs text-slate-600" data-i18n-text="settings.image_upload_specs">JPEG · PNG · WebP · GIF &mdash; max 5 MB &mdash; auto-cropped to square</p>
              </div>
              <input type="file" id="lab_logo_file" name="lab_logo_file"
                accept="image/jpeg,image/png,image/webp,image/gif"
                class="hidden" onchange="handleLogoFile(this.files[0])">
            </div>

            <!-- URL fallback (collapsed by default) -->
            <div class="mt-2">
              <button type="button" id="btn-toggle-url"
                class="text-xs text-slate-500 hover:text-slate-300 transition-colors flex items-center gap-1"
                onclick="toggleLogoUrl()">
                <svg id="url-chevron" class="w-3 h-3 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                <span data-i18n-text="settings.or_paste_an_image_url">Or paste an image URL instead</span>
              </button>
              <div id="logo-url-row" class="hidden mt-2">
                <input type="url" id="lab_logo_url" name="lab_logo_url"
                  value="<?= htmlspecialchars($cur_logo) ?>"
                  placeholder="https://example.com/logo.png"
                  class="input-field w-full rounded-xl px-4 py-3 text-sm"
                  oninput="previewLogoUrl(this.value)">
                <p class="text-xs text-slate-600 mt-1" data-i18n-text="settings.paste_a_direct_image_url_uploa">Paste a direct image URL. Uploading a file above takes priority over this field.</p>
              </div>
            </div>
          </div>

          <!-- Lab Name -->
          <div>
            <label for="lab_name" class="form-label" data-i18n-text="settings.lab_name">Lab Name</label>
            <input type="text" id="lab_name" name="lab_name"
              value="<?= htmlspecialchars($settings['lab_name'] ?? 'DIY Lab') ?>"
              placeholder="DIY Lab"
              maxlength="60"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5" data-i18n-text="settings.shown_in_the_sidebar_and_login">Shown in the sidebar and login screen header.</p>
          </div>

          <!-- Tag Line -->
          <div>
            <label for="lab_tagline" class="form-label" data-i18n-text="settings.tag_line">Tag Line
              <span class="text-slate-600 font-normal ml-1" data-i18n-text="settings.login_screen">(login screen)</span>
            </label>
            <input type="text" id="lab_tagline" name="lab_tagline"
              value="<?= htmlspecialchars($settings['lab_tagline'] ?? 'Inventory & AI Orchestrator') ?>"
              placeholder="Inventory & AI Orchestrator"
              maxlength="100"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5" data-i18n-text="settings.the_subtitle_shown_below_the_l">The subtitle shown below the Lab Name on the login screen.</p>
          </div>

          <!-- Mini Tag Line -->
          <div>
            <label for="lab_mini_tagline" class="form-label" data-i18n-text="settings.mini_tag_line">Mini Tag Line
              <span class="text-slate-600 font-normal ml-1" data-i18n-text="settings.sidebar">(sidebar)</span>
            </label>
            <input type="text" id="lab_mini_tagline" name="lab_mini_tagline"
              value="<?= htmlspecialchars($settings['lab_mini_tagline'] ?? 'Inventory System') ?>"
              placeholder="Inventory System"
              maxlength="60"
              class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5" data-i18n-text="settings.short_subtitle_shown_under_the">Short subtitle shown under the Lab Name in the sidebar.</p>
          </div>

          <button type="submit"
            class="w-full btn-primary py-3 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30" data-i18n-text="settings.save_personalization">
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
            <p class="text-sm font-semibold text-white" data-i18n-text="settings.change_lab_password">Change Lab Password</p>
            <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="settings.update_your_login_credentials">Update your login credentials</p>
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
            <label for="current_password" class="form-label" data-i18n-text="settings.current_password">Current Password</label>
            <div class="relative">
              <input type="password" id="current_password" name="current_password"
                placeholder="Enter your current password" data-i18n-placeholder="settings.enter_current_password"
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
            <label for="new_password" class="form-label" data-i18n-text="settings.new_password">New Password
              <span class="text-slate-600 font-normal ml-1" data-i18n-text="settings.min_6_characters">(min. 6 characters)</span>
            </label>
            <div class="relative">
              <input type="password" id="new_password" name="new_password"
                placeholder="Enter your new password" data-i18n-placeholder="settings.enter_new_password"
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
            <label for="confirm_password" class="form-label" data-i18n-text="settings.confirm_new_password">Confirm New Password</label>
            <div class="relative">
              <input type="password" id="confirm_password" name="confirm_password"
                placeholder="Repeat your new password" data-i18n-placeholder="settings.repeat_new_password"
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
            onmouseover="this.style.opacity='.85'" onmouseout="this.style.opacity='1'" data-i18n-text="settings.update_password">
            🔐 Update Password
          </button>
        </form>
      </div>


      <!-- Bug Reporting Section -->
      <div class="glass rounded-2xl p-6 mt-6 border border-emerald-500/10">
        <div class="flex items-center gap-3 mb-5">
          <div class="w-8 h-8 rounded-lg bg-emerald-500/15 flex items-center justify-center flex-shrink-0">
            <svg class="w-4 h-4 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
            </svg>
          </div>
          <div>
            <p class="text-sm font-semibold text-white" data-i18n-text="settings.bug_reporting_notifications">Bug Reporting & Notifications</p>
            <p class="text-xs text-slate-500 mt-0.5" data-i18n-text="settings.configure_where_local_bug_repo">Configure where local bug reports should be sent</p>
          </div>
        </div>

        <form method="POST" class="space-y-4">
          <input type="hidden" name="action" value="save_integrations">
          
          <div>
            <label class="form-label" data-i18n-text="settings.admin_email">Admin Email</label>
            <input type="email" name="admin_email" value="<?= htmlspecialchars($settings['admin_email'] ?? '') ?>" placeholder="Leave blank for system default" data-i18n-placeholder="settings.leave_blank_for_system_default" class="input-field w-full rounded-xl px-4 py-3 text-sm">
            <p class="text-xs text-slate-600 mt-1.5" data-i18n-text="settings.when_users_submit_a_ticket_via">When users submit a ticket via the sidebar, an email notification with the screenshot will be sent here.</p>
          </div>

          <button type="submit" class="w-full py-3 rounded-xl font-semibold text-emerald-400 border border-emerald-500/30 hover:bg-emerald-500/10 text-sm shadow-lg shadow-emerald-900/10 mt-2 transition-colors" data-i18n-text="settings.save_notification_settings">
            🔗 Save Notification Settings
          </button>
        </form>
      </div>


      <!-- 4. AI Configuration -->
      <div class="glass rounded-2xl p-6 mt-6">
        <div class="flex items-center justify-between gap-3 mb-5">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-blue-500/15 flex items-center justify-center flex-shrink-0">
              <svg class="w-4 h-4 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-semibold text-white" data-i18n-text="settings.page_title">AI Configuration</p>
              <p class="text-xs text-slate-500 mt-0.5">Provider: <span class="text-slate-300"><?= ucfirst($current_provider) ?></span></p>
            </div>
          </div>
          <?php if ($has_key): ?>
          <span class="flex items-center gap-1.5 text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-3 py-1.5 rounded-full flex-shrink-0">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span> API Key Saved
          </span>
          <?php else: ?>
          <span class="flex items-center gap-1.5 text-xs text-red-400 bg-red-500/10 border border-red-500/20 px-3 py-1.5 rounded-full flex-shrink-0">
            <span class="w-2 h-2 rounded-full bg-red-400"></span> No API Key
          </span>
          <?php endif; ?>
        </div>

        <form method="POST" class="space-y-6">
          <input type="hidden" name="action" value="save_config">
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
                  <p class="font-semibold text-white text-sm" data-i18n-text="settings.gemini">Gemini</p>
                  <p class="text-xs text-slate-500" data-i18n-text="settings.google">Google</p>
                </div>
              </div>
              <p class="text-xs text-slate-500" data-i18n-text="settings.gemini_1_5_flash_vision_text">gemini-1.5-flash — Vision + text</p>
            </label>
            <label class="provider-card <?= $current_provider === 'openai' ? 'selected' : '' ?>" data-provider="openai">
              <input type="radio" name="ai_provider" value="openai" <?= $current_provider === 'openai' ? 'checked' : '' ?> class="hidden">
              <div class="flex items-center gap-3 mb-2">
                <div class="w-8 h-8 rounded-lg bg-emerald-500/20 flex items-center justify-center">
                  <span class="text-lg">⊕</span>
                </div>
                <div>
                  <p class="font-semibold text-white text-sm" data-i18n-text="settings.openai">OpenAI</p>
                  <p class="text-xs text-slate-500" data-i18n-text="settings.gpt_4o">GPT-4o</p>
                </div>
              </div>
              <p class="text-xs text-slate-500" data-i18n-text="settings.gpt_4o_vision_text">gpt-4o — Vision + text</p>
            </label>
          </div>
        </div>

        <!-- API Key -->
        <div>
          <label for="api_key" class="form-label" data-i18n-text="settings.api_key_label">API Key</label>
          <div class="relative">
            <input type="password" id="api_key" name="api_key"
              <?= $has_key ? 'placeholder="●●●●●●●●●●●● (key saved — re-enter to change)" data-i18n-placeholder="settings.key_saved_reenter"' : 'placeholder="Enter your API key…" data-i18n-placeholder="settings.enter_api_key"' ?>
              autocomplete="off"
              class="input-field w-full rounded-xl px-4 py-3 pr-12 text-sm font-mono">
            <button type="button" id="toggle-key" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-500 hover:text-slate-300 transition-colors" aria-label="Toggle API key visibility">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            </button>
          </div>
          <p class="text-xs text-slate-600 mt-1.5" data-i18n-text="settings.key_is_stored_in_your_database">Key is stored in your database, not in code. Leave blank to keep existing key.</p>
        </div>

        <button type="submit" class="w-full btn-primary py-3 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
          💾 <span data-i18n-text="settings.save_config">Save Configuration</span>
        </button>
      </form>

        </form>

        <!-- Where to get your API key -->
        <div class="mt-5 pt-5 border-t border-white/5 space-y-2">
          <p class="text-xs font-semibold text-slate-400" data-i18n-text="settings.where_to_get_key">Where to get your API key</p>
          <div class="flex items-center gap-3">
            <span class="text-blue-400 font-medium text-xs w-16" data-i18n-text="settings.gemini">Gemini</span>
            <a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              <span data-i18n-text="settings.google_link">aistudio.google.com/app/apikey &rarr;</span>
            </a>
          </div>
          <div class="flex items-center gap-3">
            <span class="text-emerald-400 font-medium text-xs w-16" data-i18n-text="settings.openai">OpenAI</span>
            <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener"
               class="text-cyan-400 hover:text-cyan-300 hover:underline text-xs">
              <span data-i18n-text="settings.openai_link">platform.openai.com/api-keys &rarr;</span>
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
  // ── Logo upload helpers ────────────────────────────────────────────────
  function handleLogoFile(file) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      const img   = document.getElementById('dz-preview-img');
      const name  = document.getElementById('dz-preview-name');
      const prev  = document.getElementById('dz-preview');
      const place = document.getElementById('dz-placeholder');
      img.src = e.target.result;
      name.textContent = file.name;
      prev.classList.remove('hidden');
      prev.classList.add('flex');
      place.classList.add('hidden');
      // Clear URL field so the file takes priority
      const urlField = document.getElementById('lab_logo_url');
      if (urlField) urlField.value = '';
      // Cancel any remove_logo flag
      document.getElementById('remove_logo_flag').value = '';
    };
    reader.readAsDataURL(file);
  }

  function handleLogoDrop(e) {
    e.preventDefault();
    const dz = document.getElementById('logo-dropzone');
    dz.classList.remove('border-purple-500/70','bg-purple-500/8');
    const file = e.dataTransfer.files[0];
    if (!file || !file.type.startsWith('image/')) return;
    // Feed the file into the hidden input so it submits with the form
    const dt = new DataTransfer();
    dt.items.add(file);
    document.getElementById('lab_logo_file').files = dt.files;
    handleLogoFile(file);
  }

  function toggleLogoUrl() {
    const row     = document.getElementById('logo-url-row');
    const chevron = document.getElementById('url-chevron');
    const hidden  = row.classList.toggle('hidden');
    chevron.style.transform = hidden ? '' : 'rotate(90deg)';
  }

  function previewLogoUrl(url) {
    // When URL is typed, show it in the dropzone preview
    if (!url) {
      resetDzPreview(); return;
    }
    const img = new Image();
    img.onload = () => {
      document.getElementById('dz-preview-img').src = url;
      document.getElementById('dz-preview-name').textContent = url;
      document.getElementById('dz-preview').classList.remove('hidden');
      document.getElementById('dz-preview').classList.add('flex');
      document.getElementById('dz-placeholder').classList.add('hidden');
    };
    img.onerror = () => resetDzPreview();
    img.src = url;
  }

  function resetDzPreview() {
    document.getElementById('dz-preview').classList.add('hidden');
    document.getElementById('dz-preview').classList.remove('flex');
    document.getElementById('dz-placeholder').classList.remove('hidden');
  }

  function removeLogo() {
    document.getElementById('remove_logo_flag').value = '1';
    // Clear the file input
    const fi = document.getElementById('lab_logo_file');
    fi.value = '';
    // Clear the URL input
    const urlField = document.getElementById('lab_logo_url');
    if (urlField) urlField.value = '';
    // Hide the current logo strip
    document.getElementById('logo-current-wrap').classList.add('hidden');
    // Reset dropzone to placeholder
    resetDzPreview();
  }
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  // Initialise i18n engine
  localizationController.init();
  </script>
</body>
</html>