<?php
/**
 * container_manifest.php — Live manifest of all components at a location.
 * ?loc=Box+3   → by location name (existing system, no schema change)
 * Also generates a printable QR sticker for the container.
 */
require 'db.php';
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

// Updated timestamp
$updated_stmt = $pdo->prepare("SELECT MAX(updated_at) FROM inventory WHERE location=?");
// Fallback if no updated_at column
try { $updated_stmt->execute([$loc]); $last_updated = $updated_stmt->fetchColumn(); }
catch(Exception $e) { $last_updated = null; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>📦 <?= htmlspecialchars($loc) ?> — Container Manifest</title>
  <meta name="description" content="Live inventory manifest for container: <?= htmlspecialchars($loc) ?>">
  <link rel="stylesheet" href="assets/app.css">
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
    @media print {
      .no-print { display:none !important; }
      body { background:#fff; color:#111; }
      .glass { background:#f9fafb; border:1px solid #e5e7eb; }
    }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
<div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
  <div class="p-5 border-b border-white/5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center text-white text-lg">📦</div>
      <div><p class="font-semibold text-white text-sm">DIY Lab</p><p class="text-xs text-slate-500">Container Manifest</p></div>
    </div>
  </div>
  <nav class="flex-1 p-4 space-y-1">
    <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard</a>
    <a href="locations.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg> Locations</a>
  </nav>
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
         class="text-xs text-cyan-400 border border-cyan-500/30 px-3 py-1.5 rounded-lg hover:bg-cyan-500/10 transition-all">
        🏷️ Print Item Labels
      </a>
      <button onclick="toggleSticker()" class="btn-primary px-3 py-1.5 rounded-lg text-xs font-semibold text-white">
        📄 Container QR Sticker
      </button>
    </div>
  </header>

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

    <!-- Summary stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
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
            <th class="no-print">Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($by_category as $cat => $cat_items): ?>
          <tr><td colspan="5" class="cat-header">📁 <?= htmlspecialchars($cat) ?> (<?= count($cat_items) ?>)</td></tr>
          <?php foreach ($cat_items as $item): ?>
          <tr>
            <td>
              <span class="text-white text-sm font-medium"><?= htmlspecialchars($item['name']) ?></span>
            </td>
            <td class="text-slate-400"><?= htmlspecialchars($item['model'] ?? '—') ?></td>
            <td class="text-slate-500 hidden sm:table-cell"><?= htmlspecialchars($item['category']) ?></td>
            <td><span class="qty-badge"><?= (int)$item['quantity'] ?></span></td>
            <td class="no-print">
              <a href="item_details.php?id=<?= $item['id'] ?>"
                 class="text-xs text-purple-400 hover:text-purple-300 transition-colors">View →</a>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
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
let qrGenerated = false;

function toggleSticker() {
  const panel = document.getElementById('sticker-panel');
  panel.classList.toggle('hidden');
  if (!qrGenerated) {
    new QRCode(document.getElementById('container-qr'), {
      text: manifestUrl, width:100, height:100,
      colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M
    });
    qrGenerated = true;
  }
}

function printSticker() {
  const overlay = document.getElementById('print-sticker-overlay');
  // Generate print QR if not done
  if (!document.getElementById('print-qr').innerHTML) {
    new QRCode(document.getElementById('print-qr'), {
      text: manifestUrl, width:130, height:130,
      colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M
    });
  }
  const html = `<!DOCTYPE html><html><head><style>
    body{margin:2cm;font-family:sans-serif;}
    .sticker{display:flex;align-items:center;gap:16px;border:2px dashed #aaa;padding:16px;width:fit-content;border-radius:8px;}
    .title{font-size:16px;font-weight:700;}
    .sub{font-size:10px;color:#555;margin-top:3px;}
    .accent{color:#7c3aed;font-weight:700;}
  </style></head><body>
  <div class="sticker">
    ${document.getElementById('print-qr').innerHTML}
    <div>
      <div class="title">📦 <?= htmlspecialchars(addslashes($loc)) ?></div>
      <div class="sub"><?= $total_items ?> types · <?= $total_qty ?> units</div>
      <div class="sub accent">Scan for live manifest →</div>
    </div>
  </div>
  </body></html>`;
  const w = window.open('','_blank','width=500,height=400');
  w.document.write(html);
  w.document.close();
  w.focus();
  setTimeout(() => w.print(), 500);
}

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
</script>
</body>
</html>
