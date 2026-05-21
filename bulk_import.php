<?php
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$zip_added   = (int)($_GET['added'] ?? 0);
$zip_skipped = $_SESSION['zip_import_skipped'] ?? [];
unset($_SESSION['zip_import_skipped']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Bulk Import — DIY Lab</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
    :root { --bg:#0a0a0f; --surface:rgba(255,255,255,.04); --border:rgba(255,255,255,.08);
            --purple:#8b5cf6; --green:#4ade80; --text:#e2e8f0; --muted:#64748b; }
    body { background:var(--bg); color:var(--text); font-family:'Inter',sans-serif; min-height:100vh; }
    .top-bar { display:flex; align-items:center; gap:16px; padding:18px 28px;
               background:rgba(255,255,255,.02); border-bottom:1px solid var(--border);
               position:sticky; top:0; z-index:50; backdrop-filter:blur(12px); }
    .top-bar a { color:var(--muted); text-decoration:none; font-size:.85rem; }
    .top-bar h1 { font-size:1.1rem; font-weight:600; }
    .container { max-width:960px; margin:0 auto; padding:48px 24px; }
    .page-title { font-size:1.8rem; font-weight:700; margin-bottom:8px; }
    .page-sub   { color:var(--muted); font-size:.9rem; margin-bottom:40px; }
    /* Option grid */
    .option-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:20px; }
    .option-card { background:var(--surface); border:1px solid var(--border); border-radius:20px;
                   padding:28px; text-decoration:none; color:var(--text); display:flex;
                   flex-direction:column; gap:12px; transition:all .25s; position:relative; overflow:hidden; }
    .option-card::before { content:''; position:absolute; inset:0; opacity:0; transition:opacity .25s;
                           background:radial-gradient(circle at 30% 30%, var(--card-glow, rgba(139,92,246,.12)), transparent 70%); }
    .option-card:hover { border-color:var(--card-border, rgba(139,92,246,.5));
                         transform:translateY(-3px); box-shadow:0 16px 40px rgba(0,0,0,.4); }
    .option-card:hover::before { opacity:1; }
    /* Colour variants */
    .card-purple { --card-glow:rgba(139,92,246,.15); --card-border:rgba(139,92,246,.5); }
    .card-green  { --card-glow:rgba(74,222,128,.12);  --card-border:rgba(74,222,128,.4);  }
    .card-cyan   { --card-glow:rgba(6,182,212,.12);   --card-border:rgba(6,182,212,.4);   }
    .card-amber  { --card-glow:rgba(251,191,36,.1);   --card-border:rgba(251,191,36,.4);  }
    .card-icon   { font-size:2.2rem; line-height:1; }
    .card-title  { font-size:1.05rem; font-weight:700; }
    .card-desc   { font-size:.82rem; color:var(--muted); line-height:1.65; flex:1; }
    .card-badge  { display:inline-block; font-size:.68rem; font-weight:600; padding:3px 10px;
                   border-radius:99px; align-self:flex-start; }
    .badge-easy  { background:rgba(74,222,128,.12); color:#4ade80; border:1px solid rgba(74,222,128,.25); }
    .badge-med   { background:rgba(251,191,36,.1);  color:#fbbf24; border:1px solid rgba(251,191,36,.2); }
    .badge-adv   { background:rgba(139,92,246,.12); color:#c4b5fd; border:1px solid rgba(139,92,246,.25); }
    .card-arrow  { font-size:1.2rem; color:var(--muted); transition:transform .2s; align-self:flex-end; }
    .option-card:hover .card-arrow { transform:translateX(4px); }
    .pending-badge { position:absolute; top:16px; right:16px; background:rgba(251,191,36,.15);
                     border:1px solid rgba(251,191,36,.3); color:#fbbf24; font-size:.7rem;
                     font-weight:700; padding:3px 9px; border-radius:99px; }
    /* Flash */
    .flash-ok { background:rgba(74,222,128,.08); border:1px solid rgba(74,222,128,.25);
                border-radius:14px; padding:14px 18px; margin-bottom:28px;
                color:#4ade80; font-size:.875rem; }
    /* Tips */
    .tips { margin-top:40px; background:var(--surface); border:1px solid var(--border);
            border-radius:16px; padding:24px 28px; }
    .tips h3 { font-size:.9rem; font-weight:600; margin-bottom:12px; color:var(--muted); text-transform:uppercase; letter-spacing:.06em; }
    .tips ul  { list-style:none; display:flex; flex-direction:column; gap:8px; }
    .tips li  { font-size:.82rem; color:var(--muted); display:flex; gap:10px; }
    .tips li::before { content:'→'; color:var(--purple); flex-shrink:0; }
  </style>
  <script src="assets/i18n.js"></script>
</head>
<body>
<div class="top-bar">
  <a href="dashboard.php" data-i18n-text="bulk_import.dashboard">← Dashboard</a>
  <h1 data-i18n-text="bulk_import.bulk_import">📦 Bulk Import</h1>
</div>
<div class="container">

  <div class="page-title" data-i18n-text="bulk_import.choose_import_method">Choose Import Method</div>
  <div class="page-sub" data-i18n-text="bulk_import.three_ways_to_add_multiple_com">Three ways to add multiple components at once. Pick the one that fits your data.</div>

  <?php if ($zip_added): ?>
  <div class="flash-ok">
    <span data-i18n-text="bulk_import.zip_extracted">✅ ZIP extracted — </span><strong><?= $zip_added ?> <span data-i18n-text="bulk_import.folders">folder(s)</span></strong> <span data-i18n-text="bulk_import.added_to_queue">added to the import queue.</span>
    <?php if ($zip_skipped): ?> <span data-i18n-text="bulk_import.skipped">Skipped:</span> <?= implode(', ', array_map('htmlspecialchars', $zip_skipped)) ?>.<?php endif; ?>
    <a href="bulk_import_folder.php" style="color:#4ade80;font-weight:700;margin-left:8px;" data-i18n-text="bulk_import.start_ai_import">Start AI Import →</a>
  </div>
  <?php endif; ?>

  <div class="option-grid">

    <!-- CSV -->
    <a href="bulk_import_csv.php" class="option-card card-green">
      <div class="card-icon">📊</div>
      <div class="card-title" data-i18n-text="bulk_import.csv_spreadsheet">CSV / Spreadsheet</div>
      <div class="card-desc" data-i18n-text="bulk_import.upload_a_csv_or_tsv_file_from_">
        Upload a CSV or TSV file from Excel, Google Sheets, Notion, or any inventory tool.
        Column names are auto-detected. No AI required — fastest import method.
      </div>
      <span class="card-badge badge-easy" data-i18n-text="bulk_import.easiest_no_ai_cost">Easiest · No AI cost</span>
      <div class="card-arrow">→</div>
    </a>

    <!-- ZIP -->
    <a href="bulk_import_zip.php" class="option-card card-cyan">
      <div class="card-icon">🗜️</div>
      <div class="card-title" data-i18n-text="bulk_import.zip_of_photo_folders">ZIP of Photo Folders</div>
      <div class="card-desc" data-i18n-text="bulk_import.create_a_zip_where_each_subfol">
        Create a ZIP where each subfolder contains photos of one component.
        Upload it here — the AI identifies each component from its photos.
        Matches the existing folder-import workflow.
      </div>
      <span class="card-badge badge-med" data-i18n-text="bulk_import.medium_uses_ai">Medium · Uses AI</span>
      <div class="card-arrow">→</div>
    </a>

    <!-- Wizard -->
    <a href="bulk_import_wizard.php" class="option-card card-purple">
      <div class="card-icon">🧙</div>
      <div class="card-title" data-i18n-text="bulk_import.image_group_wizard">Image Group Wizard</div>
      <div class="card-desc" data-i18n-text="bulk_import.drag_photos_directly_into_your">
        Drag photos directly into your browser and group them by component.
        No folder structure needed — just drop your photos and let AI do the rest.
        Best for ad-hoc imports straight from a camera roll.
      </div>
      <span class="card-badge badge-adv" data-i18n-text="bulk_import.most_flexible_uses_ai">Most flexible · Uses AI</span>
      <div class="card-arrow">→</div>
    </a>

  </div>

  <div class="tips">
    <h3 data-i18n-text="bulk_import.which_method_should_i_use">Which method should I use?</h3>
    <ul>
      <li>You have a spreadsheet of parts — <strong>CSV Import</strong></li>
      <li>You have photos organised in folders on your computer — <strong>ZIP Import</strong></li>
      <li>You want to photograph components one-by-one right now — <strong>Image Wizard</strong></li>
    </ul>
  </div>

</div>
  <script>localizationController.init();</script>
</body>
</html>
