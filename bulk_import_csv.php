<?php
/**
 * bulk_import_csv.php — Import inventory items from a CSV/TSV spreadsheet.
 * No AI required — maps columns directly to DB fields.
 * Two-step: upload → preview → confirm insert.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

// ── Known column aliases ──────────────────────────────────────────────────────
// Maps common spreadsheet header names → inventory DB columns
$COLUMN_MAP = [
    'name'          => ['name','component','part','title','item','component name','part name'],
    'model'         => ['model','model number','part number','sku','mpn','part#','model#'],
    'category'      => ['category','type','kind','group','component type'],
    'quantity'      => ['quantity','qty','count','amount','stock','units','pcs'],
    'status'        => ['status','condition','state'],
    'location'      => ['location','storage','box','bin','shelf','place','where'],
    'notes'         => ['notes','note','description','comment','comments','remarks'],
    'purchase_price'=> ['price','cost','purchase price','unit price','paid','value'],
    'product_url'   => ['product url','url','link','product link','buy link','shop url'],
    'datasheet_url' => ['datasheet','datasheet url','datasheet link','pdf'],
    'specs'         => ['specs','specifications','spec','details','attributes'],
];

function autoMapHeaders(array $headers, array $columnMap): array {
    $mapping = [];
    foreach ($headers as $i => $h) {
        $h_lower = strtolower(trim($h));
        foreach ($columnMap as $dbCol => $aliases) {
            if (in_array($h_lower, $aliases)) {
                $mapping[$i] = $dbCol;
                break;
            }
        }
    }
    return $mapping;
}

$step    = $_POST['step'] ?? 'upload';
$error   = '';
$preview = [];
$headers = [];
$mapping = [];
$raw_csv = '';

// ── Step 2: Parse uploaded CSV and show preview ───────────────────────────────
if ($step === 'preview' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Upload failed (error code ' . $file['error'] . ')';
        $step  = 'upload';
    } else {
        $raw_csv = file_get_contents($file['tmp_name']);
        // Detect delimiter: comma vs tab vs semicolon
        $first_line = strtok($raw_csv, "\n");
        $delimiters = [',', "\t", ';', '|'];
        $counts = array_map(fn($d) => substr_count($first_line, $d), $delimiters);
        $delim  = $delimiters[array_search(max($counts), $counts)];

        $lines = array_filter(explode("\n", str_replace("\r", "", $raw_csv)));
        $rows  = array_map(fn($l) => str_getcsv($l, $delim), array_values($lines));

        if (count($rows) < 2) {
            $error = 'CSV has no data rows.';
            $step  = 'upload';
        } else {
            $headers = $rows[0];
            $mapping = autoMapHeaders($headers, $COLUMN_MAP);
            // Preview first 10 data rows
            $preview = array_slice($rows, 1, 10);
        }
    }
}

// ── Step 3: Execute import ────────────────────────────────────────────────────
$import_results = [];
$enrich_queued  = [];
if ($step === 'import' && isset($_POST['raw_csv'])) {
    $raw_csv     = $_POST['raw_csv'];
    $delim       = $_POST['delim'] ?? ',';
    $mapping     = json_decode($_POST['mapping'] ?? '{}', true);
    $do_enrich   = !empty($_POST['enrich_urls']); // checkbox

    $lines    = array_filter(explode("\n", str_replace("\r", "", $raw_csv)));
    $rows     = array_map(fn($l) => str_getcsv($l, $delim), array_values($lines));
    $dataRows = array_slice($rows, 1);

    $stmt = $pdo->prepare("INSERT INTO inventory
        (name, model, category, quantity, status, location, notes, purchase_price, product_url, datasheet_url, specs)
        VALUES (:name, :model, :category, :quantity, :status, :location, :notes, :purchase_price, :product_url, :datasheet_url, :specs)");

    $done = 0; $skipped = 0; $failed = 0;
    foreach ($dataRows as $row) {
        $fields = [];
        foreach ($mapping as $col_idx => $db_col) {
            $fields[$db_col] = trim($row[$col_idx] ?? '');
        }

        $name = $fields['name'] ?? '';
        if (!$name) { $skipped++; $import_results[] = ['skip', '(empty name row)']; continue; }

        $qty = isset($fields['quantity']) ? (int)preg_replace('/[^0-9]/', '', $fields['quantity']) : 1;
        if ($qty < 1) $qty = 1;

        $valid_status = ['New', 'Used', 'Refurbished'];
        $status = isset($fields['status']) ? ucfirst(strtolower($fields['status'])) : 'New';
        if (!in_array($status, $valid_status)) $status = 'New';

        $price = isset($fields['purchase_price']) && $fields['purchase_price'] !== ''
            ? (float)preg_replace('/[^0-9.]/', '', $fields['purchase_price']) : null;

        $product_url = $fields['product_url'] ?? null;

        try {
            $stmt->execute([
                ':name'           => $name,
                ':model'          => $fields['model']         ?? null,
                ':category'       => $fields['category']      ?? 'Uncategorised',
                ':quantity'       => $qty,
                ':status'         => $status,
                ':location'       => $fields['location']      ?? null,
                ':notes'          => $fields['notes']          ?? null,
                ':purchase_price' => $price,
                ':product_url'    => $product_url,
                ':datasheet_url'  => $fields['datasheet_url'] ?? null,
                ':specs'          => $fields['specs']          ?? null,
            ]);
            $id = $pdo->lastInsertId();
            $done++;
            // Queue enrichment if checkbox ticked and URL exists
            if ($do_enrich && $product_url) {
                $enrich_queued[] = ['id' => $id, 'name' => $name];
            }
            $import_results[] = ['ok', $name, $id, $do_enrich && $product_url];
        } catch (\Throwable $e) {
            $failed++;
            $import_results[] = ['fail', $name, $e->getMessage()];
        }
    }
    $step = 'done';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CSV Import — DIY Lab</title>
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
    .container { max-width:900px; margin:0 auto; padding:40px 24px; }
    .card { background:var(--surface); border:1px solid var(--border); border-radius:20px; padding:32px; margin-bottom:24px; }
    .card h2 { font-size:1.1rem; font-weight:700; margin-bottom:6px; }
    .card p  { color:var(--muted); font-size:.875rem; margin-bottom:20px; line-height:1.6; }
    /* Upload zone */
    .upload-zone { border:2px dashed rgba(139,92,246,.4); border-radius:16px; padding:48px 24px;
                   text-align:center; cursor:pointer; transition:all .2s; }
    .upload-zone:hover, .upload-zone.drag { border-color:var(--purple); background:rgba(139,92,246,.06); }
    .upload-zone input { display:none; }
    .upload-zone .icon { font-size:2.5rem; margin-bottom:12px; }
    .upload-zone .hint { color:var(--muted); font-size:.82rem; margin-top:8px; }
    /* Supported columns */
    .col-grid { display:flex; flex-wrap:wrap; gap:6px; margin-bottom:20px; }
    .col-tag  { background:rgba(139,92,246,.1); border:1px solid rgba(139,92,246,.2);
                color:#c4b5fd; border-radius:8px; padding:3px 10px; font-size:.72rem; }
    .col-tag.required { background:rgba(34,197,94,.1); border-color:rgba(34,197,94,.25); color:#4ade80; }
    /* Preview table */
    .preview-wrap { overflow-x:auto; margin:16px 0; }
    table.preview { border-collapse:collapse; width:100%; font-size:.8rem; }
    table.preview th { background:rgba(139,92,246,.12); color:#c4b5fd; padding:8px 12px;
                       text-align:left; border-bottom:1px solid var(--border); white-space:nowrap; }
    table.preview th .mapped { font-size:.65rem; color:#4ade80; display:block; font-weight:400; }
    table.preview td { padding:7px 12px; border-bottom:1px solid rgba(255,255,255,.04);
                       color:var(--muted); max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    table.preview tr:hover td { background:rgba(139,92,246,.04); }
    /* Column mapping dropdowns */
    .mapping-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:10px; margin:16px 0; }
    .map-item label { font-size:.72rem; color:var(--muted); display:block; margin-bottom:4px; }
    .map-item select { width:100%; background:rgba(255,255,255,.06); border:1px solid var(--border);
                       border-radius:8px; padding:6px 10px; color:var(--text); font-size:.8rem;
                       outline:none; cursor:pointer; }
    .map-item select:focus { border-color:rgba(139,92,246,.5); }
    /* Buttons */
    .btn { border:none; border-radius:12px; cursor:pointer; font-family:inherit;
           font-size:.9rem; font-weight:600; padding:11px 26px; transition:all .2s; }
    .btn-primary { background:linear-gradient(135deg,#7c3aed,#4f46e5); color:#fff; }
    .btn-primary:hover { transform:translateY(-1px); box-shadow:0 8px 24px rgba(124,58,237,.35); }
    .btn-secondary { background:var(--surface); border:1px solid var(--border); color:var(--text); }
    .btn-secondary:hover { border-color:rgba(139,92,246,.4); }
    /* Result list */
    .result-item { display:flex; align-items:center; gap:10px; padding:8px 0;
                   border-bottom:1px solid rgba(255,255,255,.04); font-size:.85rem; }
    .result-item:last-child { border:none; }
    .result-icon { font-size:1rem; width:20px; text-align:center; flex-shrink:0; }
    .error-box { background:rgba(248,113,113,.08); border:1px solid rgba(248,113,113,.25);
                 border-radius:12px; padding:14px 16px; color:#f87171; font-size:.875rem; margin-bottom:20px; }
    .info-box  { background:rgba(251,191,36,.06); border:1px solid rgba(251,191,36,.2);
                 border-radius:12px; padding:14px 16px; color:#fbbf24; font-size:.8rem; margin-bottom:16px; }
    .steps { display:flex; gap:8px; align-items:center; margin-bottom:32px; font-size:.8rem; }
    .step { padding:5px 14px; border-radius:99px; border:1px solid var(--border); color:var(--muted); }
    .step.active { background:rgba(139,92,246,.15); border-color:rgba(139,92,246,.4); color:#c4b5fd; font-weight:600; }
    .step.done   { background:rgba(34,197,94,.1);   border-color:rgba(34,197,94,.3);   color:#4ade80; }
    .arrow { color:var(--muted); }
  </style>
  <script src="assets/i18n.js"></script>
</head>
<body>
<div class="top-bar">
  <a href="bulk_import.php" data-i18n-text="bulk_import_csv.import_hub">← Import Hub</a>
  <h1 data-i18n-text="bulk_import_csv.csv_import">📊 CSV Import</h1>
</div>
<div class="container">

  <!-- Steps indicator -->
  <div class="steps">
    <div class="step <?= $step==='upload'?'active':($step!=='upload'?'done':'') ?>"><span data-i18n-text="bulk_import_csv.step_1_upload">1 Upload</span></div>
    <span class="arrow">→</span>
    <div class="step <?= $step==='preview'?'active':($step==='done'?'done':'') ?>"><span data-i18n-text="bulk_import_csv.step_2_preview_map">2 Preview & Map</span></div>
    <span class="arrow">→</span>
    <div class="step <?= $step==='done'?'active':'' ?>"><span data-i18n-text="bulk_import_csv.step_3_done">3 Done</span></div>
  </div>

  <?php if ($error): ?>
  <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <!-- ── Step 1: Upload ──────────────────────────────────────────────────────── -->
  <?php if ($step === 'upload'): ?>
  <div class="card">
    <h2 data-i18n-text="bulk_import_csv.upload_your_spreadsheet">Upload your spreadsheet</h2>
    <p>Accepts <strong>.csv</strong>, <strong>.tsv</strong>, or any plain-text delimited file. The first row must be column headers. Commas, tabs, semicolons, and pipe <code>|</code> delimiters are all auto-detected.</span></p>

    <div class="info-box">
      💡 <strong>Tip:</strong> Export from Google Sheets → File → Download → CSV. From Excel → Save As → CSV UTF-8.
    </div>

    <p style="font-size:.8rem;color:var(--muted);margin-bottom:8px;" data-i18n-text="bulk_import_csv.recognised_column_names_auto_m">Recognised column names (auto-mapped):</p>
    <div class="col-grid">
      <span class="col-tag required" data-i18n-text="bulk_import_csv.name">name ✦</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.model_part_number">model / part number</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.category_type">category / type</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.quantity_qty">quantity / qty</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.location_bin_shelf">location / bin / shelf</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.status">status</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.notes_description">notes / description</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.price_cost">price / cost</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.product_url_link">product url / link</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.datasheet_pdf">datasheet / pdf</span>
      <span class="col-tag" data-i18n-text="bulk_import_csv.specs_specifications">specs / specifications</span>
    </div>
    <p style="font-size:.72rem;color:var(--muted);margin-bottom:20px;" data-i18n-text="bulk_import_csv.required_unrecognised_columns_">✦ Required. Unrecognised columns can be manually mapped in step 2.</p>

    <form method="post" enctype="multipart/form-data" id="upload-form">
      <input type="hidden" name="step" value="preview">
      <div class="upload-zone" id="drop-zone" onclick="document.getElementById('csv-file-input').click()">
        <div class="icon">📄</div>
        <div style="font-weight:600;" data-i18n-text="bulk_import_csv.click_to_select_file_or_drag_d">Click to select file or drag & drop here</div>
        <div class="hint" data-i18n-text="bulk_import_csv.csv_tsv_or_any_delimited_text_">CSV, TSV, or any delimited text file · max 10 MB</div>
        <input type="file" name="csv_file" id="csv-file-input" accept=".csv,.tsv,.txt" onchange="autoSubmit(this)">
      </div>
    </form>
  </div>

  <?php elseif ($step === 'preview'): ?>
  <!-- ── Step 2: Preview & column mapping ───────────────────────────────────── -->
  <?php
    // Detect delimiter for passing to step 3
    $first_line_raw = strtok($raw_csv, "\n");
    $delimiters2 = [',', "\t", ';', '|'];
    $counts2 = array_map(fn($d) => substr_count($first_line_raw, $d), $delimiters2);
    $delim_char = $delimiters2[array_search(max($counts2), $counts2)];
    $delim_escape = addslashes($delim_char);
  ?>
  <div class="card">
    <h2 data-i18n-text="bulk_import_csv.map_columns_inventory_fields">Map columns → inventory fields</h2>
    <p><span data-i18n-text="bulk_import_csv.auto_mapped">Auto-mapped columns are pre-selected (shown in green below the header). Adjust any that were not recognised. Columns left as <em>— skip —</em> will be ignored.</p>

    <!-- Column mapping UI -->
    <form method="post" id="import-form">
      <input type="hidden" name="step" value="import">
      <input type="hidden" name="raw_csv" value="<?= htmlspecialchars($raw_csv) ?>">
      <input type="hidden" name="delim" value="<?= htmlspecialchars($delim_char) ?>">

      <div class="mapping-grid">
        <?php foreach ($headers as $i => $h): ?>
        <?php $mapped = $mapping[$i] ?? ''; ?>
        <div class="map-item">
          <label>"<?= htmlspecialchars($h) ?>" →</label>
          <select name="col_map[<?= $i ?>]">
            <option value="">— skip —</option>
            <?php foreach (array_keys($COLUMN_MAP) as $dbcol): ?>
            <option value="<?= $dbcol ?>" <?= $mapped===$dbcol?'selected':'' ?>><?= $dbcol ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Data preview table -->
      <h3 style="font-size:.85rem;color:var(--muted);margin:20px 0 10px;">Preview (first <?= count($preview) ?> <span data-i18n-text="bulk_import_csv.rows_of_data">rows of data)</span></h3>
      <div class="preview-wrap">
        <table class="preview">
          <thead><tr>
            <?php foreach ($headers as $i => $h): ?>
            <th>
              <?= htmlspecialchars($h) ?>
              <?php if (isset($mapping[$i])): ?>
              <span class="mapped">↳ <?= $mapping[$i] ?></span>
              <?php endif; ?>
            </th>
            <?php endforeach; ?>
          </tr></thead>
          <tbody>
            <?php foreach ($preview as $row): ?>
            <tr><?php foreach ($row as $cell): ?><td title="<?= htmlspecialchars($cell) ?>"><?= htmlspecialchars(mb_strimwidth($cell,0,40,'…')) ?></td><?php endforeach; ?></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Enrich option -->
      <?php
        // Check if any column is mapped to product_url
        $has_url_col = in_array('product_url', $mapping);
      ?>
      <div style="margin:20px 0;padding:16px;background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.2);border-radius:12px;">
        <label style="display:flex;align-items:flex-start;gap:12px;cursor:pointer;">
          <input type="checkbox" name="enrich_urls" id="enrich-cb" value="1" style="margin-top:2px;accent-color:#06b6d4;"
            <?= !$has_url_col ? 'disabled' : '' ?>>
          <div>
            <div style="font-size:.875rem;font-weight:600;color:<?= $has_url_col?'#67e8f9':'var(--muted)'?>;">
              <span data-i18n-text="bulk_import_csv.auto_enrich"><span data-i18n-text="bulk_import_csv.auto_enrich">🔗 Auto-enrich via Product URL after import</span></span>
            </div>
            <div style="font-size:.78rem;color:var(--muted);margin-top:3px;">
              <?= $has_url_col
                ? 'For each imported item with a product URL, calls the AI enrichment endpoint to fill in specs, category, and additional details.'
                : 'No column mapped to <code>product_url</code> — map one above to enable this option.' ?>
            </div>
          </div>
        </label>
      </div>

      <div style="display:flex;gap:12px;margin-top:16px;">
        <button type="submit" class="btn btn-primary" onclick="buildMapping()" data-i18n-text="bulk_import_csv.import_to_inventory">✅ Import to Inventory</button>
        <a href="bulk_import_csv.php" class="btn btn-secondary" data-i18n-text="bulk_import_csv.start_over">← Start over</a>
      </div>
    </form>
  </div>

  <?php elseif ($step === 'done'): ?>
  <!-- ── Step 3: Results ────────────────────────────────────────────────────── -->
  <div class="card">
    <h2 data-i18n-text="bulk_import_csv.import_complete">✅ Import complete</h2>
    <p>
      <strong style="color:var(--green)"><?= $done ?> items imported</strong>
      <?php if ($skipped): ?> · <span style="color:var(--yellow)"><?= $skipped ?> skipped</span><?php endif; ?>
      <?php if ($failed): ?>  · <span style="color:var(--red)"><?= $failed ?> failed</span><?php endif; ?>
      <?php if ($enrich_queued): ?> · <span style="color:#67e8f9"><?= count($enrich_queued) ?> queued for enrichment</span><?php endif; ?>
    </p>
    <div style="margin:20px 0;max-height:400px;overflow-y:auto;" id="result-list">
      <?php foreach ($import_results as $r): ?>
      <div class="result-item" id="res-<?= $r[2] ?? '' ?>">
        <span class="result-icon"><?= $r[0]==='ok'?'✅':($r[0]==='skip'?'⏭':'❌') ?></span>
        <span><?php if ($r[0]==='ok'): ?>
          <a href="item_details.php?id=<?= $r[2] ?>" style="color:#c4b5fd;text-decoration:none;"><?= htmlspecialchars($r[1]) ?></a>
          <?php if (!empty($r[3])): ?><span style="font-size:.72rem;color:#67e8f9;margin-left:6px;" data-i18n-text="bulk_import_csv.enriching">⏳ enriching…</span><?php endif; ?>
        <?php elseif ($r[0]==='skip'): ?><span style="color:var(--muted)"><?= htmlspecialchars($r[1]) ?></span>
        <?php else: ?><span style="color:var(--red)"><?= htmlspecialchars($r[1]) ?></span>
          <span style="color:var(--muted);font-size:.72rem;"><?= htmlspecialchars($r[2]) ?></span>
        <?php endif; ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php if ($enrich_queued): ?>
    <div id="enrich-status" style="font-size:.82rem;color:#67e8f9;margin-bottom:16px;"><span data-i18n-text="bulk_import_csv.running_enrichment">🔗 Running enrichment for </span><?= count($enrich_queued) ?> items…</div>
    <?php endif; ?>
    <div style="display:flex;gap:12px;">
      <a href="dashboard.php" class="btn btn-primary" data-i18n-text="bulk_import_csv.view_inventory">View Inventory →</a>
      <a href="bulk_import_csv.php" class="btn btn-secondary" data-i18n-text="bulk_import_csv.import_another_file">Import another file</a>
    </div>
  </div>
  <?php if ($enrich_queued): ?>
  <script>
  // Run enrichment sequentially after page load
  const toEnrich = <?= json_encode($enrich_queued) ?>;
  (async () => {
    for (const item of toEnrich) {
      try {
        const r = await fetch('enrich_api.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ item_id: item.id })
        });
        const d = await r.json();
        const row = document.getElementById('res-' + item.id);
        if (row) {
          const tag = row.querySelector('[style*="67e8f9"]');
          if (tag) tag.textContent = d.ok ? '✅ enriched' : '⚠️ ' + (d.error || 'failed');
          if (tag) tag.style.color = d.ok ? '#4ade80' : '#fbbf24';
        }
      } catch(e) { /* ignore */ }
      await new Promise(r => setTimeout(r, 2500)); // rate limit buffer
    }
    const status = document.getElementById('enrich-status');
    if (status) status.textContent = '✅ Enrichment complete.';
  })();
  </script>
  <?php endif; ?>
  <?php endif; ?>

</div>

<script>
// Drag-and-drop
const zone = document.getElementById('drop-zone');
if (zone) {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('drag'); });
  zone.addEventListener('dragleave', () => zone.classList.remove('drag'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('drag');
    const file = e.dataTransfer.files[0];
    if (file) {
      const input = document.getElementById('csv-file-input');
      const dt = new DataTransfer(); dt.items.add(file); input.files = dt.files;
      autoSubmit(input);
    }
  });
}

function autoSubmit(input) {
  if (input.files[0]) {
    input.closest('form').submit();
  }
}

// Build hidden mapping inputs from selects before submitting
function buildMapping() {
  const selects = document.querySelectorAll('select[name^="col_map"]');
  const form = document.getElementById('import-form');
  selects.forEach(sel => {
    const hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = 'mapping[' + sel.name.match(/\[(\d+)\]/)[1] + ']';
    hidden.value = sel.value;
    form.appendChild(hidden);
  });
}
</script>
  <script>localizationController.init();</script>
</body>
</html>
