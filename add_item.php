<?php
/**
 * add_item.php — Add / Edit Component (Phases 2, 3, 5)
 * Handles new item creation AND editing existing items.
 * Includes multi-angle image upload + AI Auto-Identify button.
 */
require 'db.php';
require 'image_helper.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$edit_id   = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$item      = null;
$existing_images = [];
$page_title = $edit_id ? 'Edit Component' : 'Add Component';

// Load existing item for editing
if ($edit_id) {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->execute([$edit_id]);
    $item = $stmt->fetch();
    if (!$item) { header('Location: dashboard.php'); exit; }
    $existing_images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
}

$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle image deletion
    if (isset($_POST['delete_image'])) {
        $del_path = $_POST['delete_image'];
        $idx = array_search($del_path, $existing_images);
        if ($idx !== false) {
            if (file_exists($del_path)) @unlink($del_path);
            unset($existing_images[$idx]);
            $existing_images = array_values($existing_images);
        }
        $stmt = $pdo->prepare("UPDATE inventory SET image_paths = ? WHERE id = ?");
        $stmt->execute([json_encode($existing_images), $edit_id]);
        $item['image_paths'] = json_encode($existing_images);
        // Reload
        $stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
        $stmt->execute([$edit_id]);
        $item = $stmt->fetch();
        $existing_images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
    } else {
        // Save / Update
        $name     = trim($_POST['name']     ?? '');
        $model    = trim($_POST['model']    ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 1);
        $status   = $_POST['status']   ?? 'New';
        $specs    = trim($_POST['specs']    ?? '');
        $location = trim($_POST['location'] ?? '');

        if ($name === '') $errors[] = 'Component name is required.';

        if (empty($errors)) {
            $upload_dir = 'uploads/';
            if (!is_dir($upload_dir)) { mkdir($upload_dir, 0755, true); }

            $new_paths = $existing_images; // keep existing images

            if (!empty($_FILES['images']['name'][0])) {
                foreach ($_FILES['images']['tmp_name'] as $k => $tmp) {
                    if ($_FILES['images']['error'][$k] !== UPLOAD_ERR_OK) continue;
                    $base_name = time() . '_' . uniqid();
                    $result    = process_image($tmp, $upload_dir, $base_name);
                    if ($result) {
                        $new_paths[] = $result['full']; // store the full-res path
                    }
                }
            }

            $image_json = json_encode($new_paths);

            if ($edit_id) {
                $stmt = $pdo->prepare("UPDATE inventory SET name=?, model=?, category=?, quantity=?, status=?, specs=?, location=?, image_paths=? WHERE id=?");
                $stmt->execute([$name, $model, $category, $quantity, $status, $specs, $location, $image_json, $edit_id]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO inventory (name, model, category, quantity, status, specs, location, image_paths) VALUES (?,?,?,?,?,?,?,?)");
                $stmt->execute([$name, $model, $category, $quantity, $status, $specs, $location, $image_json]);
            }

            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?> — DIY Lab</title>
  <meta name="description" content="Add or edit a component in your DIY Lab inventory with optional AI auto-identification.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #0a0a1a; }
    .bg-grid {
      background-image: linear-gradient(rgba(124,58,237,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(124,58,237,.04) 1px, transparent 1px);
      background-size: 40px 40px;
    }
    .glass { background: rgba(255,255,255,.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.07); }
    .input-field {
      background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.1); color: #e2e8f0;
      transition: border-color .2s, box-shadow .2s;
    }
    .input-field:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.2); }
    .input-field::placeholder { color: #4b5563; }
    .btn-primary { background: linear-gradient(135deg, #7c3aed, #06b6d4); transition: all .2s; }
    .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
    .drop-zone {
      border: 2px dashed rgba(124,58,237,.3); border-radius:12px; padding:2rem; text-align:center;
      transition: border-color .2s, background .2s; cursor:pointer;
    }
    .drop-zone.drag-over { border-color:#7c3aed; background: rgba(124,58,237,.08); }
    .nav-link { color:#94a3b8; transition:color .2s; }
    .nav-link:hover { color:#c4b5fd; }
    .preview-img { width:80px; height:80px; object-fit:cover; border-radius:8px; border:1px solid rgba(255,255,255,.1); }
    label.form-label { display:block; font-size:.8rem; font-weight:500; color:#94a3b8; margin-bottom:.4rem; text-transform:uppercase; letter-spacing:.05em; }
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
      <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard
      </a>
      <a href="add_item.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component
      </a>
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> Creative Engine
      </a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant
      </a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings
      </a>
    </nav>
  </div>

  <!-- Main -->
  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center gap-3">
      <button onclick="openSidebar()" class="lg:hidden p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <a href="dashboard.php" class="text-slate-500 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <h1 class="text-lg lg:text-xl font-bold text-white truncate"><?= $page_title ?></h1>
    </header>

    <div class="p-4 lg:p-8 max-w-2xl mx-auto">

      <?php if (!empty($errors)): ?>
      <div class="mb-6 px-4 py-3 rounded-xl bg-red-500/10 border border-red-500/20 text-red-400 text-sm space-y-1">
        <?php foreach ($errors as $e): ?><p>⚠ <?= htmlspecialchars($e) ?></p><?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- AI Auto-Identify Section -->
      <div class="glass rounded-2xl p-6 mb-6">
        <h2 class="font-semibold text-white mb-1 flex items-center gap-2">
          <span class="text-xl">✨</span> AI Auto-Identify
        </h2>
        <p class="text-xs text-slate-500 mb-4">Upload component photos and let AI fill in the details automatically.</p>

        <div id="drop-zone" class="drop-zone" onclick="document.getElementById('ai-file-input').click()">
          <svg class="w-8 h-8 text-purple-500/50 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
          <p class="text-sm text-slate-400">Drop photos here or <span class="text-purple-400">browse</span></p>
          <p class="text-xs text-slate-600 mt-1">Multiple angles = better accuracy (top, labels, packaging)</p>
        </div>
        <input type="file" id="ai-file-input" multiple accept="image/*" class="hidden">

        <div id="ai-previews" class="flex flex-wrap gap-2 mt-3"></div>

        <div class="flex items-center gap-3 mt-4">
          <button type="button" id="btn-ai-identify"
            class="flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white btn-primary shadow-lg shadow-purple-900/30">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            Auto-Identify with AI
          </button>
          <span id="ai-status" class="text-xs text-slate-500"></span>
        </div>
      </div>

      <!-- Main Form -->
      <form method="POST" enctype="multipart/form-data" class="glass rounded-2xl p-6 space-y-5">
        <?php if ($edit_id): ?>
          <input type="hidden" name="edit_id" value="<?= $edit_id ?>">
        <?php endif; ?>

        <div>
          <label for="name" class="form-label">Component Name *</label>
          <input type="text" id="name" name="name" required
            value="<?= htmlspecialchars($item['name'] ?? '') ?>"
            placeholder="e.g. ESP32 Development Board"
            class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="model" class="form-label">Model / Part Number</label>
            <input type="text" id="model" name="model"
              value="<?= htmlspecialchars($item['model'] ?? '') ?>"
              placeholder="e.g. ESP32-WROOM-32"
              class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
          </div>
          <div>
            <label for="category" class="form-label">Category</label>
            <input type="text" id="category" name="category"
              value="<?= htmlspecialchars($item['category'] ?? '') ?>"
              placeholder="e.g. Microcontroller"
              class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label for="quantity" class="form-label">Quantity</label>
            <input type="number" id="quantity" name="quantity" min="0"
              value="<?= (int)($item['quantity'] ?? 1) ?>"
              class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
          </div>
          <div>
            <label for="status" class="form-label">Condition</label>
            <select id="status" name="status" class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
              <?php foreach (['New','Used','Refurbished'] as $s): ?>
              <option value="<?= $s ?>" <?= ($item['status'] ?? 'New') === $s ? 'selected' : '' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div>
          <label for="specs" class="form-label">Technical Specifications / Notes</label>
          <textarea id="specs" name="specs" rows="4" placeholder="Voltage, pinout, special notes…"
            class="input-field w-full rounded-xl px-4 py-2.5 text-sm resize-none"><?= htmlspecialchars($item['specs'] ?? '') ?></textarea>
        </div>

        <div>
          <label for="location" class="form-label">Physical Location</label>
          <input type="text" id="location" name="location"
            value="<?= htmlspecialchars($item['location'] ?? '') ?>"
            placeholder="e.g. BIN-A3, Drawer-2"
            class="input-field w-full rounded-xl px-4 py-2.5 text-sm">
        </div>

        <!-- Additional images to store -->
        <div>
          <label class="form-label">Upload Component Photos</label>
          <?php if (!empty($existing_images)): ?>
          <div class="flex flex-wrap gap-3 mb-3">
            <?php foreach ($existing_images as $img_path): ?>
            <div class="relative group">
              <img src="<?= htmlspecialchars($img_path) ?>" alt="" class="preview-img">
              <form method="POST" class="absolute -top-2 -right-2 opacity-0 group-hover:opacity-100 transition-opacity">
                <input type="hidden" name="delete_image" value="<?= htmlspecialchars($img_path) ?>">
                <button type="submit" onclick="return confirm('Remove this image?')"
                  class="w-6 h-6 bg-red-500 rounded-full flex items-center justify-center text-white text-xs hover:bg-red-400">✕</button>
              </form>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <input type="file" name="images[]" id="form-images" multiple accept="image/*"
            class="input-field w-full rounded-xl px-4 py-2.5 text-sm file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-purple-600/20 file:text-purple-300 hover:file:bg-purple-600/30">
          <p class="text-xs text-slate-600 mt-1.5">Upload the photos from AI identification to save them with this item.</p>
        </div>

        <div class="flex items-center gap-3 pt-2">
          <button type="submit" class="btn-primary flex-1 py-3 rounded-xl font-semibold text-white text-sm shadow-lg shadow-purple-900/30">
            <?= $edit_id ? '💾 Save Changes' : '➕ Add to Inventory' ?>
          </button>
          <a href="dashboard.php" class="px-5 py-3 rounded-xl text-sm text-slate-400 border border-white/10 hover:border-white/20 hover:text-white transition-all">
            Cancel
          </a>
        </div>
      </form>
    </div>
  </main>

  <script>
  // ── File preview for AI identification ────────────────────────────────
  const aiInput    = document.getElementById('ai-file-input');
  const dropZone   = document.getElementById('drop-zone');
  const aiPreviews = document.getElementById('ai-previews');

  let aiFiles = [];

  function renderAIPreviews() {
    aiPreviews.innerHTML = '';
    aiFiles.forEach((f, i) => {
      const reader = new FileReader();
      reader.onload = e => {
        const wrap = document.createElement('div');
        wrap.className = 'relative group';
        wrap.innerHTML = `
          <img src="${e.target.result}" class="w-20 h-20 object-cover rounded-lg border border-white/10">
          <button type="button" onclick="removeAIFile(${i})"
            class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 rounded-full text-white text-xs opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">✕</button>`;
        aiPreviews.appendChild(wrap);
      };
      reader.readAsDataURL(f);
    });
  }

  window.removeAIFile = (i) => { aiFiles.splice(i, 1); renderAIPreviews(); };

  aiInput.addEventListener('change', () => {
    const newFiles = Array.from(aiInput.files);
    // Warn if any file is over 20 MB
    const oversized = newFiles.filter(f => f.size > 20 * 1024 * 1024);
    if (oversized.length > 0) {
      alert('⚠️ One or more photos exceed 20 MB. Please compress them before uploading\n\n' + oversized.map(f => f.name + ' (' + (f.size/1024/1024).toFixed(1) + ' MB)').join('\n'));
      aiInput.value = '';
      return;
    }
    aiFiles = [...aiFiles, ...newFiles];
    aiInput.value = '';
    renderAIPreviews();
  });

  // Drag and drop
  dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('drag-over'); });
  dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
  dropZone.addEventListener('drop', e => {
    e.preventDefault();
    dropZone.classList.remove('drag-over');
    aiFiles = [...aiFiles, ...Array.from(e.dataTransfer.files).filter(f => f.type.startsWith('image/'))];
    renderAIPreviews();
  });

  // ── AI Identification ─────────────────────────────────────────────────
  document.getElementById('btn-ai-identify').addEventListener('click', async function () {
    if (aiFiles.length === 0) {
      alert('Please select or drop at least one component photo first.');
      return;
    }

    const status = document.getElementById('ai-status');
    this.disabled = true;
    status.textContent = '🔍 Analysing images… please wait.';
    status.style.color = '#94a3b8';

    const formData = new FormData();
    aiFiles.forEach(f => formData.append('images[]', f));

    try {
      const res  = await fetch('identify_api.php', { method: 'POST', body: formData });
      // Guard against non-JSON responses (e.g. PHP fatal errors returning HTML)
      const contentType = res.headers.get('content-type') || '';
      if (!contentType.includes('application/json')) {
        const text = await res.text();
        throw new Error('Server returned an unexpected response. Check the browser console for details.');
      }
      const data = await res.json();

      if (data.error) throw new Error(data.error);

      // Auto-fill form fields
      if (data.name)     document.getElementById('name').value     = data.name;
      if (data.model)    document.getElementById('model').value    = data.model;
      if (data.category) document.getElementById('category').value = data.category;
      if (data.specs)    document.getElementById('specs').value    = data.specs;

      // Copy files to the form upload input so they get saved
      const dt = new DataTransfer();
      aiFiles.forEach(f => dt.items.add(f));
      document.getElementById('form-images').files = dt.files;

      status.textContent = '✅ Identification complete! Review the fields below and click Save.';
      status.style.cssText = 'color:#4ade80; display:block; margin-top:8px; font-size:.85rem;';
    } catch (err) {
      // Show error as a styled readable block
      const msg = err.message || 'Identification failed. Please try again.';
      status.innerHTML = '<span style="color:#f87171;font-weight:600;">❌ Auto-Identify failed</span><br>' +
        '<span style="color:#fca5a5;font-size:.82rem;line-height:1.5;">' + msg.replace(/\n/g,'<br>') + '</span>';
      status.style.cssText = 'display:block; margin-top:8px; padding:10px 14px; background:rgba(239,68,68,.08); border:1px solid rgba(239,68,68,.2); border-radius:10px; max-width:480px;';
      console.error('AI Identify error:', err);
    } finally {
      this.disabled = false;
    }
  });
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  </script>
</body>
</html>
