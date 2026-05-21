<?php
/**
 * container_manifest.php — Live manifest of all components at a location.
 * ?loc=Box+3   → by location name (existing system, no schema change)
 * Also generates a printable QR sticker for the container.
 */
require 'db.php';
require_once 'site_config.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$loc = trim($_GET['loc'] ?? '');
if (!$loc) { header('Location: locations.php'); exit; }

$stmt = $pdo->prepare(
    "SELECT id,name,model,category,quantity,status,specs,location
     FROM inventory WHERE location=? ORDER BY category,name"
);
$stmt->execute([$loc]);
$items = $stmt->fetchAll();

$total_items = count($items);
$total_qty   = array_sum(array_column($items, 'quantity'));

// Group by category for the manifest view
$by_category = [];
foreach ($items as $i) {
    $by_category[$i['category']][] = $i;
}
ksort($by_category);

// (updated_at not in schema — omitted)
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📦 <?= htmlspecialchars($loc) ?> — Container Manifest</title>
  <meta name="description" content="Live inventory manifest for container: <?= htmlspecialchars($loc) ?>">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    .manifest-table { width:100%; border-collapse:collapse; }
    .manifest-table th { text-align:left; padding:.5rem .75rem; font-size:.7rem; text-transform:uppercase;
      letter-spacing:.05em; color:#64748b; border-bottom:1px solid rgba(255,255,255,.06); }
    .manifest-table td { padding:.55rem .75rem; font-size:.82rem; border-bottom:1px solid rgba(255,255,255,.04); }
    .manifest-table tr:hover td { background:rgba(124,58,237,.05); }
    .cat-header { font-size:.65rem; text-transform:uppercase; letter-spacing:.08em;
      color:#94a3b8; padding:.4rem .75rem; background:rgba(255,255,255,.02); }
    .qty-badge { display:inline-block; background:rgba(34,197,94,.12); color:#4ade80;
      border:1px solid rgba(34,197,94,.25); border-radius:8px; padding:.1rem .5rem; font-size:.75rem; font-weight:600; }
    /* QR sticker panel */
    .sticker-preview {
      background:#fff; color:#111; border-radius:12px; padding:16px;
      display:flex; align-items:center; gap:12px; max-width:320px;
    }
    .sticker-info { flex:1; min-width:0; }
    .sticker-title { font-size:14px; font-weight:700; }
    .sticker-sub   { font-size:10px; color:#555; margin-top:2px; }
    /* ── Print styles — strict black-on-white, no colours ───────────── */
    @media print {
      /* Nuclear colour reset — overrides ALL Tailwind colour classes */
      * { color:#000 !important; background:#fff !important;
          -webkit-print-color-adjust:exact; print-color-adjust:exact; }
      .no-print { display:none !important; }
      body { font-family:'Inter',system-ui,sans-serif; margin:0; padding:0; }
      /* Hide all app chrome */
      #sidebar, #sidebar-overlay, #sticker-panel,
      #print-sticker-overlay { display:none !important; }
      main  { margin-left:0 !important; }
      header{ display:none !important; }
      .p-4, .p-8 { padding:0 !important; }
      /* Show print-only sections */
      .print-header { display:block !important; }
      /* Table — clean borders, no colour */
      .glass { border:none !important; border-radius:0 !important;
               box-shadow:none !important; }
      .manifest-table { border-collapse:collapse; width:100%; font-size:9pt; }
      .manifest-table th { border:1px solid #000 !important; padding:4pt 6pt;
        font-size:8pt; text-transform:uppercase; letter-spacing:.04em;
        background:#e5e7eb !important; /* light grey header — prints fine */ }
      .manifest-table td { border:1px solid #555 !important; padding:4pt 6pt; font-size:9pt; }
      .cat-header { background:#e5e7eb !important; font-weight:700;
        font-size:8pt; border:1px solid #000 !important; }
      .qty-badge  { border:1px solid #000 !important; border-radius:3px;
        padding:1pt 4pt; font-weight:600; }
      /* Verified column visible only on paper */
      .col-verify { display:table-cell !important; }
      /* Force cols hidden by Tailwind's responsive prefix to show */
      .hidden, [class*="sm:"] { display:table-cell !important; }
      tr { page-break-inside:avoid; }
      .cat-header { page-break-after:avoid; }
    }
    @media screen {
      .print-header { display:none; }
      .col-verify   { display:none; }
    }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">
  <?php include 'includes/sidebar.php'; ?>



<main class="lg:ml-64 min-h-screen">
  <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3 no-print">
    <div class="flex items-center gap-2 min-w-0">
      <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <a href="locations.php" class="text-slate-500 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
      </a>
      <div>
        <h1 class="text-lg lg:text-xl font-bold text-white">📦 <?= htmlspecialchars($loc) ?></h1>
        <p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> <span data-i18n-text="container_manifest.component_types_word">component types</span> &middot; <?= $total_qty ?> <span data-i18n-text="container_manifest.total_units_word">total units</span></p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank"
         class="text-xs text-cyan-400 border border-cyan-500/30 px-3 py-1.5 rounded-lg hover:bg-cyan-500/10 transition-all no-print">
        <span data-i18n-text="container_manifest.print_item_labels"><span data-i18n-text="container_manifest.print_item_labels">🏷️ Print Item Labels</span></span>
      </a>
      <button onclick="window.print()" class="text-xs text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-500/10 transition-all no-print" data-i18n-text="container_manifest.print_manifest">
        🖨️ Print Manifest
      </button>
      <button onclick="toggleSticker()" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-semibold text-white no-print" data-i18n-text="container_manifest.container_qr_sticker_1">
        <span data-i18n-text="container_manifest.container_qr">📄 Container QR Sticker</span>
      </button>
    </div>
  </header>

  <!-- Print-only manifest header (hidden on screen) -->
  <div class="print-header" style="padding:12mm 12mm 6mm;border-bottom:2px solid #111;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div>
        <div style="font-size:18pt;font-weight:700;">📦 <?= htmlspecialchars($loc) ?></div>
        <div style="font-size:9pt;color:#555;margin-top:3pt;">
          <?= $total_items ?> <span data-i18n-text="container_manifest.component_types_word">component types</span> &middot; <?= $total_qty ?> <span data-i18n-text="container_manifest.total_units_word">total units</span> &middot;
          <?= count($by_category) ?> categories
        </div>
      </div>
      <div style="text-align:right;font-size:8pt;color:#777;">
        <div><span data-i18n-text="container_manifest.printed">Printed: </span><?= date('d M Y, H:i') ?></div>
        <div style="margin-top:2pt;" data-i18n-text="container_manifest.diy_lab_inventory">DIY Lab Inventory</div>
      </div>
    </div>
  </div>

  <div class="p-4 lg:p-8">

    <!-- Container QR sticker panel (hidden by default) -->
    <div id="sticker-panel" class="hidden mb-6 glass rounded-2xl p-6">
      <div class="flex flex-col lg:flex-row gap-6 items-start">
        <div>
          <p class="text-sm font-semibold text-white mb-3"><span data-i18n-text="container_manifest.container_qr">📄 Container QR Sticker</span> — <?= htmlspecialchars($loc) ?></p>
          <div class="sticker-preview" id="sticker-preview-box">
            <div id="container-qr"></div>
            <div class="sticker-info">
              <div class="sticker-title">📦 <?= htmlspecialchars($loc) ?></div>
              <div class="sticker-sub"><?= $total_items ?> types · <?= $total_qty ?> <span data-i18n-text="container_manifest.units">units</span></div>
              <div class="sticker-sub" style="margin-top:4px;color:#7c3aed;font-weight:600;" data-i18n-text="container_manifest.scan_for_live_manifest">Scan for live manifest →</div>
            </div>
          </div>
        </div>
        <div class="flex-1">
          <p class="text-xs text-slate-500 mb-3"><span data-i18n-text="container_manifest.qr_links">This QR code links to the live manifest for</span> <strong class="text-slate-300"><?= htmlspecialchars($loc) ?></strong>. <span data-i18n-text="container_manifest.qr_desc_2">Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</span></p>
          <div class="flex gap-2 flex-wrap">
            <button onclick="printSticker()" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold text-white" data-i18n-text="container_manifest.print_sticker">🖨️ Print Sticker</button>
            <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank"
               class="px-4 py-2 rounded-lg text-sm font-semibold text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500/10 transition-all">
               <span data-i18n-text="container_manifest.print_item_labels"><span data-i18n-text="container_manifest.print_labels_instead">🏷️ Print Item Labels Instead</span></span>
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="text-center py-16 text-slate-500">
      <p class="text-3xl mb-3">📭</p>
      <p class="font-semibold" data-i18n-text="container_manifest.no_components_at_this_location">No components at this location</p>
      <p class="text-sm mt-1"><span data-i18n-text="container_manifest.items_with_loc">Items with location "</span><?= htmlspecialchars($loc) ?>" <span data-i18n-text="container_manifest.will_appear">will appear here.</span></p>
    </div>
    <?php else: ?>

    <!-- Summary stats — screen only -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 no-print">
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-white"><?= $total_items ?></p>
        <p class="text-xs text-slate-500 mt-1" data-i18n-text="container_manifest.component_types">Component Types</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-emerald-400"><?= $total_qty ?></p>
        <p class="text-xs text-slate-500 mt-1" data-i18n-text="container_manifest.total_units">Total Units</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-purple-400"><?= count($by_category) ?></p>
        <p class="text-xs text-slate-500 mt-1" data-i18n-text="container_manifest.categories">Categories</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank" class="block">
          <p class="text-2xl font-bold text-cyan-400">🖨️</p>
          <p class="text-xs text-slate-500 mt-1" data-i18n-text="container_manifest.print_all_labels">Print All Labels</p>
        </a>
      </div>
    </div>

    <!-- Component manifest table -->
    <div class="glass rounded-2xl overflow-hidden">
      <table class="manifest-table">
        <thead>
          <tr>
            <th data-i18n-text="container_manifest.component">Component</th>
            <th data-i18n-text="container_manifest.model">Model</th>
            <th class="hidden sm:table-cell" data-i18n-text="container_manifest.category">Category</th>
            <th data-i18n-text="container_manifest.qty">Qty</th>
            <th class="col-verify" style="width:60px;text-align:center;" data-i18n-text="container_manifest.verified">Verified</th>
            <th class="no-print" data-i18n-text="container_manifest.action">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($by_category as $cat => $cat_items): ?>
          <tr><td colspan="6" class="cat-header">📁 <?= htmlspecialchars($cat) ?> (<?= count($cat_items) ?>)</td></tr>
          <?php foreach ($cat_items as $item): ?>
          <tr>
            <td>
              <span class="text-white text-sm font-medium"><?= htmlspecialchars($item['name']) ?></span>
            </td>
            <td class="text-slate-400"><?= htmlspecialchars($item['model'] ?? '—') ?></td>
            <td class="text-slate-500 hidden sm:table-cell"><?= htmlspecialchars($item['category']) ?></td>
            <td><span class="qty-badge"><?= (int)$item['quantity'] ?></span></td>
            <td class="col-verify" style="text-align:center;">☐</td>
            <td class="no-print">
              <a href="item_details.php?id=<?= $item['id'] ?>"
                 class="text-xs text-purple-400 hover:text-purple-300 transition-colors">View →</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="col-verify" style="display:none;">
            <?php /* tfoot only visible in print via CSS col-verify */ ?>
          </tr>
        </tfoot>
      </table>
    </div>
    <!-- Print footer -->
    <div class="print-header" style="padding:4mm 12mm;font-size:7pt;color:#9ca3af;border-top:1px solid #e5e7eb;margin-top:4mm;display:flex;justify-content:space-between;">
      <span><span data-i18n-text="container_manifest.verify_item_is_in_container"><span data-i18n-text="container_manifest.verify_item_is_in_container">☐ = verify item is in container &nbsp;|&nbsp; Generated by DIY Lab Inventory System</span></span></span>
      <span><?= htmlspecialchars($loc) ?> — <?= date('d M Y') ?></span>
    </div>
    <?php endif; ?>

  </div>
</main>

<!-- Print sticker overlay -->
<div id="print-sticker-overlay" class="hidden">
  <div class="sticker-preview" id="print-sticker-box" style="border:2px dashed #aaa;width:fit-content;margin:2rem auto;padding:20px;gap:16px;">
    <div id="print-qr"></div>
    <div class="sticker-info">
      <div class="sticker-title" style="font-size:16px;">📦 <?= htmlspecialchars($loc) ?></div>
      <div class="sticker-sub" style="font-size:11px;"><?= $total_items ?> types · <?= $total_qty ?> <span data-i18n-text="container_manifest.units">units</span></div>
      <div class="sticker-sub" style="font-size:10px;color:#7c3aed;font-weight:700;margin-top:4px;" data-i18n-text="container_manifest.scan_for_live_manifest">Scan for live manifest →</div>
    </div>
  </div>
</div>

<script>
const manifestUrl = window.location.href;
const locName     = <?= json_encode($loc) ?>;
const totalTypes  = <?= $total_items ?>;
const totalQty    = <?= $total_qty ?>;
const categories  = <?= json_encode(implode(', ', array_keys($by_category))) ?>;
let qrGenerated = false;

// Self-contained QR text — readable offline from any QR scanner.
// URL appended so online users can tap/visit.
function buildContainerQrText() {
  return [
    `CONTAINER: ${locName}`,
    `${totalTypes} types | ${totalQty} units`,
    categories ? `Categories: ${categories}` : null,
    '---',
    manifestUrl,
  ].filter(Boolean).join('\n');
}

function toggleSticker() {
  const panel = document.getElementById('sticker-panel');
  panel.classList.toggle('hidden');
  if (!qrGenerated) {
    new QRCode(document.getElementById('container-qr'), {
      text: buildContainerQrText(), width:100, height:100,
      colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M
    });
    qrGenerated = true;
  }
}

function printSticker() {
  if (!document.getElementById('print-qr').innerHTML) {
    new QRCode(document.getElementById('print-qr'), {
      text: buildContainerQrText(), width:130, height:130,
      colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M
    });
  }
  const html = `<!DOCTYPE html><html><head>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg"><style>
    body{margin:2cm;font-family:sans-serif;}
    .sticker{display:flex;align-items:center;gap:16px;border:2px dashed #aaa;padding:16px;width:fit-content;border-radius:8px;}
    .title{font-size:16px;font-weight:700;}
    .sub{font-size:10px;color:#555;margin-top:3px;}
    .accent{color:#7c3aed;font-weight:700;}
    .url{font-size:8px;color:#9ca3af;margin-top:6px;word-break:break-all;}
    p.note{font-size:9px;color:#888;margin-top:1rem;}
  </style></head><body>
  <div class="sticker">
    ${document.getElementById('print-qr').innerHTML}
    <div>
      <div class="title">📦 <?= htmlspecialchars(addslashes($loc)) ?></div>
      <div class="sub"><?= $total_items ?> types · <?= $total_qty ?> <span data-i18n-text="container_manifest.units">units</span></div>
      <div class="sub accent">Scan to view full manifest</div>
      <div class="url">${manifestUrl}</div>
    </div>
  </div>
  <p class="note">QR contains item list — readable offline. URL opens live manifest when connected.</p>
  </body></html>`;
  const w = window.open('','_blank','width=540,height=420');
  w.document.write(html);
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 500);
}

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
localizationController.init();
</script>
</body>
</html>
