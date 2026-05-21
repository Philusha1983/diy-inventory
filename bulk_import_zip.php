<?php
/**
 * bulk_import_zip.php — Import from a ZIP file containing subfolders (one per component).
 * Extracts the ZIP to a temp directory, then redirects to bulk_import_folder.php
 * which runs the existing AI-powered folder import pipeline.
 *
 * ZIP structure expected:
 *   my-components.zip
 *   ├── ESP32-C3/
 *   │   ├── image_01.jpg
 *   │   ├── image_02.jpg
 *   │   └── description.txt   (optional)
 *   └── SG90 Servo/
 *       └── image_01.jpg
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$error    = '';
$step     = 'upload';
$folders  = [];

// ── Handle ZIP upload ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['zip_file'])) {
    $file = $_FILES['zip_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error code ' . $file['error'] . '). Check PHP upload size limits.';
    } elseif (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
        $error = 'Only .zip files are accepted.';
    } else {
        $zip = new ZipArchive();
        if ($zip->open($file['tmp_name']) !== true) {
            $error = 'Could not open ZIP file. It may be corrupt or password-protected.';
        } else {
            // ── Security: validate all entries for path traversal (ZipSlip) ──────
            $safe = true;
            $base_check = realpath(sys_get_temp_dir()) . DIRECTORY_SEPARATOR;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                // Reject absolute paths, double-dots, or null bytes
                if (str_contains($entry, '..') || str_starts_with($entry, '/') ||
                    str_contains($entry, "\0") || str_contains($entry, '\\')) {
                    $safe  = false;
                    $error = "ZIP contains unsafe path: " . htmlspecialchars($entry);
                    break;
                }
            }

            if ($safe) {
                // ── Extract to a unique temp subfolder ───────────────────────────
                $tmp_id  = 'zip_' . uniqid();
                $tmp_dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tmp_id;
                mkdir($tmp_dir, 0755, true);
                $zip->extractTo($tmp_dir);
                $zip->close();

                // ── Detect ZIP mode: flat (images in root) vs subfolder-per-component ──
                $root_imgs = glob($tmp_dir . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                $is_flat   = !empty($root_imgs);

                // ── Copy into bulk import directory ──────────────────────────────
                $import_base = __DIR__ . '/bulk import';
                if (!is_dir($import_base)) mkdir($import_base, 0755, true);

                $copied = 0; $skipped_dirs = [];

                if ($is_flat) {
                    // Flat mode: all root images = one component named after the ZIP
                    $component_name = pathinfo($file['name'], PATHINFO_FILENAME);
                    $dst = $import_base . DIRECTORY_SEPARATOR . $component_name;
                    if (is_dir($dst)) {
                        $skipped_dirs[] = $component_name . ' (already exists)';
                    } else {
                        mkdir($dst, 0755, true);
                        foreach ($root_imgs as $img) {
                            copy($img, $dst . DIRECTORY_SEPARATOR . basename($img));
                        }
                        // Copy description.txt if present
                        $desc_src = $tmp_dir . DIRECTORY_SEPARATOR . 'description.txt';
                        if (file_exists($desc_src)) copy($desc_src, $dst . DIRECTORY_SEPARATOR . 'description.txt');
                        $copied = 1;
                        $folders[] = $component_name;
                    }
                } else {
                    // Subfolder mode: one subfolder per component
                    $extracted = scandir($tmp_dir);
                    foreach ($extracted as $entry) {
                        if ($entry === '.' || $entry === '..') continue;
                        $src = $tmp_dir . DIRECTORY_SEPARATOR . $entry;
                        if (!is_dir($src)) continue;

                        $imgs = glob($src . '/*.{jpg,jpeg,png,webp,JPG,JPEG,PNG,WEBP}', GLOB_BRACE);
                        if (empty($imgs)) { $skipped_dirs[] = $entry . ' (no images)'; continue; }

                        $dst = $import_base . DIRECTORY_SEPARATOR . $entry;
                        if (is_dir($dst)) { $skipped_dirs[] = $entry . ' (already exists)'; continue; }

                        $iter = new RecursiveIteratorIterator(
                            new RecursiveDirectoryIterator($src, RecursiveDirectoryIterator::SKIP_DOTS),
                            RecursiveIteratorIterator::SELF_FIRST
                        );
                        mkdir($dst, 0755, true);
                        foreach ($iter as $item) {
                            $target = $dst . DIRECTORY_SEPARATOR . $iter->getSubPathname();
                            if ($item->isDir()) mkdir($target, 0755, true);
                            else copy($item->getPathname(), $target);
                        }
                        $copied++;
                        $folders[] = $entry;
                    }
                }

                // Cleanup temp dir
                $cleanup = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tmp_dir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($cleanup as $item) {
                    $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
                }
                rmdir($tmp_dir);

                if ($copied === 0 && empty($error)) {
                    $error = 'No valid component folders found in ZIP. Each subfolder needs at least one image.';
                } else {
                    $_SESSION['zip_import_skipped'] = $skipped_dirs;
                    // Redirect to folder importer with the newly added folders
                    header('Location: bulk_import_folder.php?from_zip=1&added=' . $copied);
                    exit;
                }
            } else {
                $zip->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZIP Import — DIY Lab</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --bg:#0a0a0f; --surface:rgba(255,255,255,.04); --border:rgba(255,255,255,.08);
            --purple:#8b5cf6; --green:#4ade80; --red:#f87171; --yellow:#fbbf24;
            --text:#e2e8f0; --muted:#64748b; }
    body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; }
    .top-bar { display:flex; align-items:center; gap:16px; padding:18px 28px;
               background:rgba(255,255,255,.02); border-bottom:1px solid var(--border);
               position:sticky; top:0; z-index:50; backdrop-filter:blur(12px); }
    .top-bar a { color:var(--muted); text-decoration:none; font-size:.85rem; transition:color .2s; }
    .top-bar a:hover { color:var(--text); }
    .top-bar h1 { font-size:1.1rem; font-weight:600; }
    .container { max-width:800px; margin:0 auto; padding:40px 24px; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:32px; margin-bottom:24px; }
    .card h2 { font-size:1.1rem; font-weight:700; margin-bottom:8px; }
    .card p  { color:var(--muted); font-size:.875rem; margin-bottom:16px; line-height:1.6; }
    .upload-zone { border:2px dashed rgba(139,92,246,.4); border-radius:16px; padding:56px 24px;
                   text-align:center; cursor:pointer; transition:all .2s; }
    .upload-zone:hover, .upload-zone.drag { border-color:var(--purple); background:rgba(139,92,246,.06); }
    .upload-zone input { display:none; }
    .upload-zone .icon { font-size:3rem; margin-bottom:12px; }
    .upload-zone .hint { color:var(--muted); font-size:.82rem; margin-top:8px; }
    .structure-box { background:rgba(255,255,255,.03); border:1px solid var(--border);
                     border-radius:12px; padding:16px 20px; font-family:monospace;
                     font-size:.8rem; color:#a5b4fc; line-height:1.8; margin:16px 0; }
    .structure-box .comment { color:var(--muted); font-style:italic; }
    .info-box { background:rgba(251,191,36,.06); border:1px solid rgba(251,191,36,.2);
                border-radius:12px; padding:14px 16px; color:#fbbf24; font-size:.82rem; margin-bottom:20px; }
    .error-box { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25);
                 border-radius:12px; padding:14px 16px; color:#f87171; font-size:.875rem; margin-bottom:20px; }
    .btn-primary { display:inline-block; border:none; border-radius:12px; cursor:pointer; font-family:inherit;
                   font-size:.9rem; font-weight:600; padding:12px 28px;
                   background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; transition:all .2s; }
    .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(124,58,237,.35); }
    #progress { display:none; margin-top:16px; }
    .progress-bar-wrap { background:rgba(255,255,255,.06); border-radius:99px; height:8px; overflow:hidden; }
    .progress-bar-fill { height:100%; background:linear-gradient(90deg,#7c3aed,#a78bfa); width:0; transition:width .3s; }
  </style>
  <script src="assets/i18n.js"></script>
</head>
<body>
<div class="top-bar">
  <a href="bulk_import.php" data-i18n-text="bulk_import_zip.import_hub">← Import Hub</a>
  <h1 data-i18n-text="bulk_import_zip.zip_import">📦 ZIP Import</h1>
</div>
<div class="container">

  <?php if ($error): ?>
  <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <div class="card">
    <h2 data-i18n-text="bulk_import_zip.upload_a_zip_of_component_fold">Upload a ZIP of component folders</h2>
    <p data-i18n-text="bulk_import_zip.each_subfolder_in_the_zip_beco">Each subfolder in the ZIP becomes one inventory item. The AI analyses the photos and description (if present) to identify the component — the same pipeline as the existing folder import.</p>

    <p style="font-size:.82rem;color:var(--muted);margin-bottom:8px;" data-i18n-text="bulk_import_zip.supported_zip_structures">Supported ZIP structures:</p>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:16px 0;">
      <div>
        <div style="font-size:.72rem;color:#67e8f9;font-weight:600;margin-bottom:6px;" data-i18n-text="bulk_import_zip.subfolder_mode_multiple_compon">📁 Subfolder mode (multiple components)</div>
        <div class="structure-box" style="font-size:.75rem;">
          my-parts.zip<br>
          ├── <span style="color:#4ade80;" data-i18n-text="bulk_import_zip.esp32_c3">ESP32-C3/</span><br>
          │   ├── image_01.jpg<br>
          │   └── description.txt<br>
          └── <span style="color:#4ade80;" data-i18n-text="bulk_import_zip.sg90_servo">SG90 Servo/</span><br>
          &nbsp;&nbsp;&nbsp;&nbsp;└── image_01.jpg
        </div>
      </div>
      <div>
        <div style="font-size:.72rem;color:#a78bfa;font-weight:600;margin-bottom:6px;" data-i18n-text="bulk_import_zip.flat_mode_single_component">🖼️ Flat mode (single component)</div>
        <div class="structure-box" style="font-size:.75rem;">
          my-sensor.zip<br>
          ├── photo1.jpg<br>
          ├── photo2.jpg<br>
          ├── photo3.jpg<br>
          └── description.txt <span class="comment" data-i18n-text="bulk_import_zip.optional">← optional</span>
        </div>
      </div>
    </div>
    <div class="info-box">
      💡 <strong>Flat ZIP</strong> (images directly in the root) = one component named after the ZIP file.<br>
      <strong>Subfolder ZIP</strong> (one folder per component) = multiple components imported at once.<br>
      <strong>description.txt</strong> is optional but improves AI accuracy. Lines like: <code>Product Name: ESP32-C3 | Category: Microcontroller</code>
    </div>

    <form method="post" enctype="multipart/form-data" id="zip-form">
      <div class="upload-zone" id="drop-zone" onclick="document.getElementById('zip-input').click()">
        <div class="icon">🗜️</div>
        <div style="font-weight:600;" data-i18n-text="bulk_import_zip.click_to_select_zip_or_drag_dr">Click to select ZIP or drag & drop here</div>
        <div class="hint" data-i18n-text="bulk_import_zip.max_size_depends_on_your_php_u">Max size depends on your PHP upload_max_filesize setting</div>
        <input type="file" name="zip_file" id="zip-input" accept=".zip" onchange="submitForm()">
      </div>
      <div id="progress">
        <p style="font-size:.85rem;color:var(--muted);margin-bottom:8px;" data-i18n-text="bulk_import_zip.uploading_and_extracting">Uploading and extracting…</p>
        <div class="progress-bar-wrap"><div class="progress-bar-fill" id="prog-fill"></div></div>
      </div>
    </form>
  </div>

</div>
<script>
const zone = document.getElementById('drop-zone');
zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
zone.addEventListener('drop', e => {
  e.preventDefault(); zone.classList.remove('drag');
  const file = e.dataTransfer.files[0];
  if (file) {
    const dt = new DataTransfer(); dt.items.add(file);
    document.getElementById('zip-input').files = dt.files;
    submitForm();
  }
});

function submitForm() {
  document.getElementById('progress').style.display = 'block';
  // Animate progress bar (indeterminate)
  let w = 0;
  const fill = document.getElementById('prog-fill');
  const iv = setInterval(() => { w = Math.min(w + 2, 85); fill.style.width = w + '%'; }, 150);
  document.getElementById('zip-form').submit();
}
</script>
  <script>localizationController.init();</script>
</body>
</html>
