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

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
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
    <a href="locations.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium" data-i18n="nav.locations"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> Locations</a>
    <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.creative_engine"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707"/></svg> Creative Engine</a>
    <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.lab_assistant"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant</a>
    <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm" data-i18n="nav.user_settings"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> User Settings</a>
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
        <p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> component types · <?= $total_qty ?> total units</p>
      </div>
    </div>
    <div class="flex items-center gap-2 flex-shrink-0">
      <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank"
         class="text-xs text-cyan-400 border border-cyan-500/30 px-3 py-1.5 rounded-lg hover:bg-cyan-500/10 transition-all no-print">
        🏷️ Print Item Labels
      </a>
      <button onclick="window.print()" class="text-xs text-emerald-400 border border-emerald-500/30 px-3 py-1.5 rounded-lg hover:bg-emerald-500/10 transition-all no-print">
        🖨️ Print Manifest
      </button>
      <button onclick="toggleSticker()" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-semibold text-white no-print">
        📄 Container QR Sticker
      </button>
    </div>
  </header>

  <!-- Print-only manifest header (hidden on screen) -->
  <div class="print-header" style="padding:12mm 12mm 6mm;border-bottom:2px solid #111;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;">
      <div>
        <div style="font-size:18pt;font-weight:700;">📦 <?= htmlspecialchars($loc) ?></div>
        <div style="font-size:9pt;color:#555;margin-top:3pt;">
          <?= $total_items ?> component types &middot; <?= $total_qty ?> total units &middot;
          <?= count($by_category) ?> categories
        </div>
      </div>
      <div style="text-align:right;font-size:8pt;color:#777;">
        <div>Printed: <?= date('d M Y, H:i') ?></div>
        <div style="margin-top:2pt;">DIY Lab Inventory</div>
      </div>
    </div>
  </div>

  <div class="p-4 lg:p-8">

    <!-- Container QR sticker panel (hidden by default) -->
    <div id="sticker-panel" class="hidden mb-6 glass rounded-2xl p-6">
      <div class="flex flex-col lg:flex-row gap-6 items-start">
        <div>
          <p class="text-sm font-semibold text-white mb-3">📄 Container QR Sticker — <?= htmlspecialchars($loc) ?></p>
          <div class="sticker-preview" id="sticker-preview-box">
            <div id="container-qr"></div>
            <div class="sticker-info">
              <div class="sticker-title">📦 <?= htmlspecialchars($loc) ?></div>
              <div class="sticker-sub"><?= $total_items ?> types · <?= $total_qty ?> units</div>
              <div class="sticker-sub" style="margin-top:4px;color:#7c3aed;font-weight:600;">Scan for live manifest →</div>
            </div>
          </div>
        </div>
        <div class="flex-1">
          <p class="text-xs text-slate-500 mb-3">This QR code links to the live manifest for <strong class="text-slate-300"><?= htmlspecialchars($loc) ?></strong>. Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</p>
          <div class="flex gap-2 flex-wrap">
            <button onclick="printSticker()" class="btn-primary px-4 py-2 rounded-lg text-sm font-semibold text-white">🖨️ Print Sticker</button>
            <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank"
               class="px-4 py-2 rounded-lg text-sm font-semibold text-cyan-400 border border-cyan-500/30 hover:bg-cyan-500/10 transition-all">
               🏷️ Print Item Labels Instead
            </a>
          </div>
        </div>
      </div>
    </div>

    <?php if (empty($items)): ?>
    <div class="text-center py-16 text-slate-500">
      <p class="text-3xl mb-3">📭</p>
      <p class="font-semibold">No components at this location</p>
      <p class="text-sm mt-1">Items with location "<?= htmlspecialchars($loc) ?>" will appear here.</p>
    </div>
    <?php else: ?>

    <!-- Summary stats — screen only -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6 no-print">
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-white"><?= $total_items ?></p>
        <p class="text-xs text-slate-500 mt-1">Component Types</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-emerald-400"><?= $total_qty ?></p>
        <p class="text-xs text-slate-500 mt-1">Total Units</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <p class="text-2xl font-bold text-purple-400"><?= count($by_category) ?></p>
        <p class="text-xs text-slate-500 mt-1">Categories</p>
      </div>
      <div class="glass rounded-xl p-4 text-center">
        <a href="print_labels.php?loc=<?= urlencode($loc) ?>" target="_blank" class="block">
          <p class="text-2xl font-bold text-cyan-400">🖨️</p>
          <p class="text-xs text-slate-500 mt-1">Print All Labels</p>
        </a>
      </div>
    </div>

    <!-- Component manifest table -->
    <div class="glass rounded-2xl overflow-hidden">
      <table class="manifest-table">
        <thead>
          <tr>
            <th>Component</th>
            <th>Model</th>
            <th class="hidden sm:table-cell">Category</th>
            <th>Qty</th>
            <th class="col-verify" style="width:60px;text-align:center;">Verified</th>
            <th class="no-print">Action</th>
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
      <span>☐ = verify item is in container &nbsp;|&nbsp; Generated by DIY Lab Inventory System</span>
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
      <div class="sticker-sub" style="font-size:11px;"><?= $total_items ?> types · <?= $total_qty ?> units</div>
      <div class="sticker-sub" style="font-size:10px;color:#7c3aed;font-weight:700;margin-top:4px;">Scan for live manifest →</div>
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
  const html = `<!DOCTYPE html><html><head><style>
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
      <div class="sub"><?= $total_items ?> types · <?= $total_qty ?> units</div>
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
