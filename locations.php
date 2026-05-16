<?php
/**
 * locations.php — Locations Manager.
 * Lists all distinct location values, item counts, container QR shortcuts.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

// All distinct locations with counts
$locs = $pdo->query(
    "SELECT location,
            COUNT(*)         AS item_types,
            SUM(quantity)    AS total_qty,
            GROUP_CONCAT(DISTINCT category ORDER BY category SEPARATOR ', ') AS categories
     FROM inventory
     WHERE location IS NOT NULL AND location != ''
     GROUP BY location
     ORDER BY location"
)->fetchAll();

// Items with no location
$no_loc_count = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE location IS NULL OR location=''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Locations — DIY Lab</title>
  <meta name="description" content="Manage and browse physical storage locations in your DIY lab inventory.">
  <link rel="stylesheet" href="assets/app.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <style>
    .loc-card {
      background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07);
      border-radius:16px; padding:1.25rem; transition:all .2s;
    }
    .loc-card:hover { border-color:rgba(124,58,237,.35); transform:translateY(-2px); box-shadow:0 8px 30px rgba(124,58,237,.1); }
    .loc-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin-top:.75rem; }
    .loc-action-btn {
      font-size:.72rem; font-weight:600; padding:.3rem .7rem; border-radius:8px;
      border:1px solid; cursor:pointer; transition:all .15s; text-decoration:none;
      display:inline-flex; align-items:center; gap:.3rem;
    }
    .loc-action-btn.purple { color:#c4b5fd; border-color:rgba(124,58,237,.35); }
    .loc-action-btn.purple:hover { background:rgba(124,58,237,.15); }
    .loc-action-btn.cyan { color:#67e8f9; border-color:rgba(6,182,212,.35); }
    .loc-action-btn.cyan:hover { background:rgba(6,182,212,.12); }
    .loc-action-btn.emerald { color:#4ade80; border-color:rgba(34,197,94,.35); }
    .loc-action-btn.emerald:hover { background:rgba(34,197,94,.12); }
    /* QR mini popup */
    .qr-popup { display:none; margin-top:.75rem; background:rgba(255,255,255,.04);
      border:1px solid rgba(124,58,237,.2); border-radius:12px; padding:1rem; }
    .qr-popup.open { display:flex; gap:1rem; align-items:center; flex-wrap:wrap; }
    .qr-popup-sticker { background:#fff; padding:8px; border-radius:6px; display:flex; align-items:center; gap:8px; }
    .qr-popup-sticker .qs-title { font-size:10px; font-weight:700; color:#111; }
    .qr-popup-sticker .qs-sub   { font-size:8px; color:#555; }
    .qr-popup-sticker .qs-link  { font-size:8px; color:#7c3aed; font-weight:700; }
    .search-input { background:rgba(255,255,255,.05); border:1px solid rgba(255,255,255,.1);
      border-radius:10px; padding:.5rem 1rem; color:#e2e8f0; font-size:.875rem;
      outline:none; transition:border-color .2s; width:100%; max-width:300px; }
    .search-input:focus { border-color:rgba(124,58,237,.5); }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
<div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
  <div class="p-5 border-b border-white/5">
    <div class="flex items-center gap-3">
      <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
      </div>
      <div><p class="font-semibold text-white text-sm">DIY Lab</p><p class="text-xs text-slate-500">Inventory System</p></div>
    </div>
  </div>
  <nav class="flex-1 p-4 space-y-1">
    <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
      Dashboard</a>
    <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Add Component</a>
    <a href="locations.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Locations</a>
    <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
      Creative Engine</a>
    <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
      Lab Assistant</a>
    <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      AI Settings</a>
  </nav>
</div>

<main class="lg:ml-64 min-h-screen">
  <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3">
    <div class="flex items-center gap-2 min-w-0">
      <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
      <div>
        <h1 class="text-lg lg:text-xl font-bold text-white">📍 Locations</h1>
        <p class="text-xs text-slate-500 mt-0.5"><?= count($locs) ?> storage location<?= count($locs)!==1?'s':'' ?> · Generate QR stickers for containers</p>
      </div>
    </div>
    <input type="text" id="loc-search" class="search-input" placeholder="🔍 Filter locations…" oninput="filterLocs(this.value)">
  </header>

  <div class="p-4 lg:p-8">

    <?php if (empty($locs)): ?>
    <div class="text-center py-16 text-slate-500">
      <p class="text-4xl mb-3">📭</p>
      <p class="font-semibold text-lg">No locations set yet</p>
      <p class="text-sm mt-1">Add a location when editing any component to see it here.</p>
      <a href="add_item.php" class="mt-4 inline-block btn-primary px-5 py-2.5 rounded-xl text-sm font-semibold text-white">+ Add Component</a>
    </div>
    <?php else: ?>

    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4" id="loc-grid">
      <?php foreach ($locs as $idx => $loc): ?>
      <div class="loc-card" data-loc="<?= htmlspecialchars(strtolower($loc['location'])) ?>">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h2 class="text-white font-bold text-base truncate">📦 <?= htmlspecialchars($loc['location']) ?></h2>
            <p class="text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($loc['categories']) ?></p>
          </div>
          <div class="flex-shrink-0 text-right">
            <p class="text-lg font-bold text-emerald-400"><?= (int)$loc['total_qty'] ?></p>
            <p class="text-xs text-slate-600"><?= (int)$loc['item_types'] ?> types</p>
          </div>
        </div>
        <div class="loc-actions">
          <a href="container_manifest.php?loc=<?= urlencode($loc['location']) ?>"
             class="loc-action-btn purple">📋 Manifest</a>
          <button class="loc-action-btn cyan" onclick="toggleQR(<?= $idx ?>, '<?= addslashes(htmlspecialchars($loc['location'])) ?>', <?= (int)$loc['item_types'] ?>, <?= (int)$loc['total_qty'] ?>)">
            📄 Container QR
          </button>
          <a href="print_labels.php?loc=<?= urlencode($loc['location']) ?>" target="_blank"
             class="loc-action-btn emerald">🏷️ Item Labels</a>
        </div>
        <!-- Inline QR popup -->
        <div class="qr-popup" id="qr-popup-<?= $idx ?>">
          <div class="qr-popup-sticker">
            <div id="qr-<?= $idx ?>"></div>
            <div>
              <div class="qs-title">📦 <?= htmlspecialchars($loc['location']) ?></div>
              <div class="qs-sub"><?= (int)$loc['item_types'] ?> types · <?= (int)$loc['total_qty'] ?> units</div>
              <div class="qs-link">Scan for live manifest →</div>
            </div>
          </div>
          <div>
            <p class="text-xs text-slate-400 mb-2 max-w-xs">Print this sticker and stick it on the container. Scanning opens the live manifest.</p>
            <button onclick="printContainerQR('<?= addslashes(htmlspecialchars($loc['location'])) ?>', <?= (int)$loc['item_types'] ?>, <?= (int)$loc['total_qty'] ?>, '<?= addslashes(urlencode($loc['location'])) ?>')"
              class="loc-action-btn cyan">🖨️ Print Sticker</button>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <?php if ($no_loc_count > 0): ?>
    <div class="mt-6 px-4 py-3 rounded-xl bg-amber-500/8 border border-amber-500/20 text-sm text-amber-400 flex items-center justify-between gap-3">
      <span>⚠️ <strong><?= $no_loc_count ?></strong> component<?= $no_loc_count!==1?'s':'' ?> have no location set.</span>
      <a href="dashboard.php?filter_loc=unset" class="text-xs border border-amber-500/30 px-3 py-1 rounded-lg hover:bg-amber-500/10 transition-all">View →</a>
    </div>
    <?php endif; ?>

    <?php endif; ?>
  </div>
</main>

<script>
const origin = window.location.origin;
const generatedQRs = new Set();

function toggleQR(idx, locName, itemTypes, totalQty) {
  const popup = document.getElementById('qr-popup-' + idx);
  const isOpen = popup.classList.contains('open');
  // Close all
  document.querySelectorAll('.qr-popup.open').forEach(p => p.classList.remove('open'));
  if (isOpen) return;

  popup.classList.add('open');

  if (!generatedQRs.has(idx)) {
    const url = `${origin}/container_manifest.php?loc=${encodeURIComponent(locName)}`;
    new QRCode(document.getElementById('qr-' + idx), {
      text: url, width:80, height:80,
      colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M
    });
    generatedQRs.add(idx);
  }
}

function printContainerQR(locName, itemTypes, totalQty, locEncoded) {
  const url = `${origin}/container_manifest.php?loc=${locEncoded}`;
  // Generate a temp QR for the print window
  const tmp = document.createElement('div');
  tmp.id = 'tmp-qr-print';
  document.body.appendChild(tmp);
  const qr = new QRCode(tmp, { text: url, width:130, height:130,
    colorDark:'#111', colorLight:'#fff', correctLevel: QRCode.CorrectLevel.M });
  setTimeout(() => {
    const html = `<!DOCTYPE html><html><head><style>
      body{margin:2cm;font-family:sans-serif;background:#fff;}
      .sticker{display:inline-flex;align-items:center;gap:16px;border:2px dashed #aaa;padding:16px;border-radius:8px;}
      .title{font-size:16px;font-weight:700;color:#111;}
      .sub{font-size:10px;color:#555;margin-top:4px;}
      .link{font-size:9px;color:#7c3aed;font-weight:700;margin-top:4px;}
      p{font-size:10px;color:#888;margin-top:1rem;}
    </style></head><body>
    <div class="sticker">
      ${tmp.innerHTML}
      <div>
        <div class="title">📦 ${locName}</div>
        <div class="sub">${itemTypes} types · ${totalQty} units</div>
        <div class="link">Scan for live manifest →</div>
      </div>
    </div>
    <p>Cut out and laminate this sticker. Affix to the container.<br>Scanning with any phone camera opens the live manifest.</p>
    </body></html>`;
    tmp.remove();
    const w = window.open('','_blank','width=500,height=450');
    w.document.write(html);
    w.document.close();
    w.focus();
    setTimeout(() => w.print(), 500);
  }, 300);
}

function filterLocs(q) {
  q = q.toLowerCase();
  document.querySelectorAll('#loc-grid .loc-card').forEach(card => {
    card.style.display = card.dataset.loc.includes(q) ? '' : 'none';
  });
}

function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
</script>
</body>
</html>
