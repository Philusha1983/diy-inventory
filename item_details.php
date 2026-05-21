<?php
/**
 * item_details.php — Component Detail Page with Gallery (Phase 3)
 */
require 'db.php';
require 'image_helper.php';
require_once 'site_config.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = $pdo->prepare("SELECT * FROM inventory WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    header('Location: dashboard.php');
    exit;
}

$images = json_decode($item['image_paths'] ?? '[]', true) ?: [];

$badge_class = match($item['status']) {
    'New'         => 'badge-new',
    'Used'        => 'badge-used',
    'Refurbished' => 'badge-refurbished',
    default       => 'badge-used',
};
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($item['name']) ?> — DIY Lab</title>
  <meta name="description" content="Component detail page for <?= htmlspecialchars($item['name']) ?> in your DIY Lab inventory.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if(localStorage.getItem('theme')==='light')document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    .gallery-thumb {
      width:80px; height:80px; object-fit:cover; border-radius:10px;
      border:2px solid transparent; cursor:pointer; transition:all .2s;
    }
    @media(min-width:768px){.gallery-thumb{width:110px;height:110px;}}
    .gallery-thumb:hover, .gallery-thumb.active { border-color:#7c3aed; box-shadow:0 0 20px rgba(124,58,237,.3); }
    .main-image { width:100%; max-height:320px; object-fit:contain; border-radius:16px; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); }
    @media(min-width:768px){.main-image{max-height:400px;}}
    .spec-block { font-family:'JetBrains Mono',monospace; font-size:.8rem; }
    .info-row { display:flex; align-items:baseline; gap:.75rem; padding:.75rem 0; border-bottom:1px solid rgba(255,255,255,.05); }
    .info-row:last-child { border-bottom:none; }
    .info-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.1em; color:#64748b; min-width:90px; flex-shrink:0; }
    .info-value { color:#e2e8f0; font-size:.9rem; }
    .enrich-log { font-family:'JetBrains Mono',monospace; font-size:.72rem; line-height:1.6;
      background:#0d0d1f; border:1px solid rgba(124,58,237,.2); border-radius:10px; padding:.75rem 1rem;
      color:#94a3b8; max-height:150px; overflow-y:auto; white-space:pre-wrap; }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">
  <?php include 'includes/sidebar.php'; ?>


  <main class="lg:ml-64 min-h-screen">
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-2">
      <div class="flex items-center gap-2 min-w-0">
        <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <a href="dashboard.php" class="flex-shrink-0 text-slate-500 hover:text-white transition-colors">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="text-base lg:text-xl font-bold text-white truncate"><?= htmlspecialchars($item['name']) ?></h1>
        <span class="<?= $badge_class ?> flex-shrink-0 text-xs px-2.5 py-1 rounded-full font-medium hidden sm:inline"><?= htmlspecialchars($item['status']) ?></span>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">
        <a href="print_labels.php?id=<?= $item['id'] ?>" target="_blank" class="text-xs sm:text-sm text-cyan-400 hover:text-cyan-300 border border-cyan-500/20 hover:border-cyan-500/40 px-2 sm:px-3 py-1.5 rounded-lg transition-all">🏷️ <span class="hidden sm:inline" data-i18n-text="item_details.print_label">Print Label</span></a>
        <a href="add_item.php?edit=<?= $item['id'] ?>" class="text-xs sm:text-sm text-purple-400 hover:text-purple-300 border border-purple-500/20 hover:border-purple-500/40 px-2 sm:px-3 py-1.5 rounded-lg transition-all">✏️ <span class="hidden sm:inline" data-i18n-text="item_details.edit">Edit</span></a>
        <a href="delete_item.php?id=<?= $item['id'] ?>" onclick="return confirm('Delete this component and all its images?')" class="text-xs sm:text-sm text-red-400 hover:text-red-300 border border-red-500/20 hover:border-red-500/40 px-2 sm:px-3 py-1.5 rounded-lg transition-all">🗑 <span class="hidden sm:inline" data-i18n-text="common.delete">Delete</span></a>
      </div>
    </header>

    <div class="p-4 lg:p-8">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Left: Gallery -->
        <div class="col-span-1 lg:col-span-3 space-y-4">
          <?php if (!empty($images)): ?>
          <!-- Main viewer: load the full 1200px version -->
          <div class="glass rounded-2xl p-4">
            <img id="main-image" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="main-image" loading="lazy" decoding="async">
          </div>
          <!-- Thumbnails strip: load the small 400px thumb -->
          <?php if (count($images) > 1): ?>
          <div class="flex flex-wrap gap-3">
            <?php foreach ($images as $i => $img): ?>
            <?php $thumb_src = derive_thumb($img); ?>
            <img src="<?= htmlspecialchars($thumb_src) ?>"
                 alt="Angle <?= $i+1 ?>"
                 class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                 onclick="setMainImage(this, '<?= htmlspecialchars($img) ?>')"
                 loading="lazy" decoding="async">
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php else: ?>
          <div class="glass rounded-2xl p-12 flex flex-col items-center justify-center text-slate-600">
            <svg class="w-16 h-16 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm" data-i18n-text="item_details.no_photos_yet">No photos yet</p>
            <a href="add_item.php?edit=<?= $item['id'] ?>" class="text-purple-400 text-xs mt-2 hover:underline"><span data-i18n-text="item_details.add_photos">Add photos →</span></a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right: Details -->
        <div class="col-span-1 lg:col-span-2 space-y-5">
          <!-- Core info -->
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-4 text-sm uppercase tracking-wider" data-i18n-text="item_details.component_info">Component Info</h2>
            <div>
              <div class="info-row"><span class="info-label" data-i18n-text="item_details.model">Model</span><span class="info-value font-mono text-xs"><?= htmlspecialchars($item['model'] ?: '—') ?></span></div>
              <div class="info-row"><span class="info-label" data-i18n-text="item_details.category">Category</span><span class="info-value"><?= htmlspecialchars($item['category'] ?: '—') ?></span></div>
              <div class="info-row">
                <span class="info-label" data-i18n-text="item_details.quantity">Quantity</span>
                <span class="info-value">
                  <span class="text-2xl font-bold text-white"><?= (int)$item['quantity'] ?></span>
                  <span class="text-slate-500 text-xs ml-1"><span data-i18n-text="<?= (int)$item['quantity'] !== 1 ? 'item_details.units' : 'item_details.unit' ?>">unit<?= (int)$item['quantity'] !== 1 ? 's' : '' ?></span></span>
                </span>
              </div>
              <div class="info-row"><span class="info-label" data-i18n-text="item_details.condition">Condition</span><span class="<?= $badge_class ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= htmlspecialchars($item['status']) ?></span></div>
              <div class="info-row"><span class="info-label" data-i18n-text="item_details.location">Location</span><span class="info-value font-mono text-xs"><?= htmlspecialchars($item['location'] ?: '—') ?></span></div>
              <div class="info-row"><span class="info-label" data-i18n-text="item_details.added">Added</span><span class="info-value text-xs"><?= date('d M Y', strtotime($item['created_at'])) ?></span></div>
            </div>
          </div>

          <?php if ($item['specs']): ?>
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3 text-sm uppercase tracking-wider" data-i18n-text="inventory.specs">Technical Specifications</h2>
            <div class="spec-block text-slate-300 leading-relaxed whitespace-pre-wrap bg-black/20 rounded-xl p-4 border border-white/5">
              <?= htmlspecialchars($item['specs']) ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Product Details -->
          <?php $has_urls = !empty($item['product_url']) || !empty($item['datasheet_url']); ?>
          <?php if ($has_urls || !empty($item['notes']) || !empty($item['purchase_price'])): ?>
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3 text-sm uppercase tracking-wider" data-i18n-text="item_details.product_details">Product Details</h2>
            <?php if (!empty($item['product_url'])): ?>
            <div class="info-row">
              <span class="info-label" data-i18n-text="item_details.product">Product</span>
              <a href="<?= htmlspecialchars($item['product_url']) ?>" target="_blank" rel="noopener"
                 class="info-value text-cyan-400 hover:text-cyan-300 truncate max-w-xs transition-colors">
                <?= htmlspecialchars(parse_url($item['product_url'], PHP_URL_HOST) ?: $item['product_url']) ?> ↗
              </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['datasheet_url'])): ?>
            <div class="info-row">
              <span class="info-label" data-i18n-text="item_details.datasheet">Datasheet</span>
              <a href="<?= htmlspecialchars($item['datasheet_url']) ?>" target="_blank" rel="noopener"
                 class="info-value text-purple-400 hover:text-purple-300 truncate max-w-xs transition-colors">
                <?= htmlspecialchars(parse_url($item['datasheet_url'], PHP_URL_HOST) ?: $item['datasheet_url']) ?> ↗
              </a>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['purchase_price'])): ?>
            <div class="info-row">
              <span class="info-label" data-i18n-text="item_details.price">Price</span>
              <span class="info-value"><?= '$' . number_format((float)$item['purchase_price'], 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($item['notes'])): ?>
            <div class="mt-3 pt-3 border-t border-white/5">
              <p class="text-xs text-slate-500 uppercase tracking-wider mb-2" data-i18n-text="item_details.notes">Notes</p>
              <p class="text-slate-300 text-sm leading-relaxed whitespace-pre-wrap"><?= htmlspecialchars($item['notes']) ?></p>
            </div>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- AI Enrichment panel -->
          <?php if ($has_urls): ?>
          <div class="glass rounded-2xl p-5" id="enrich-panel">
            <div class="flex items-center justify-between mb-3">
              <h2 class="font-semibold text-white text-sm uppercase tracking-wider flex items-center gap-2">
                <span>🤖</span> <span data-i18n-text="item_details.ai_enrichment">AI Enrichment</span>
              </h2>
              <?php if ($item['enriched_at']): ?>
              <span class="text-xs text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                ✓ <span data-i18n-text="item_details.enriched">Enriched</span> <?= date('d M Y', strtotime($item['enriched_at'])) ?>
              </span>
              <?php else: ?>
              <span class="text-xs text-slate-500" data-i18n-text="item_details.not_yet_enriched">Not yet enriched</span>
              <?php endif; ?>
            </div>
            <?php if ($item['enriched_data']): ?>
            <p class="text-xs text-slate-500 mb-2" data-i18n-text="item_details.cached_data_used_in_ai_prompts">Cached data used in AI prompts:</p>
            <div class="enrich-log"><?= htmlspecialchars(mb_substr($item['enriched_data'], 0, 400)) ?>…</div>
            <?php endif; ?>
            <button id="btn-enrich" onclick="runEnrichment()"
              class="mt-3 flex items-center gap-2 px-4 py-2 rounded-xl text-sm font-semibold text-white btn-primary shadow-lg shadow-purple-900/30 transition-all">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
              <?= $item['enriched_data'] ? '<span data-i18n-text="item_details.refetch_refresh">Re-fetch &amp; Refresh</span>' : '<span data-i18n-text="item_details.enrich_from_web">Enrich from Web</span>' ?>
            </button>
            <div id="enrich-status" class="mt-3 hidden"></div>
          </div>
          <?php endif; ?>

          <!-- Quick actions -->
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3 text-sm uppercase tracking-wider" data-i18n-text="item_details.actions">Actions</h2>
            <div class="space-y-2">
              <a href="projects.php" class="flex items-center gap-2 text-sm text-purple-400 hover:text-purple-300 transition-colors py-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3"/></svg>
                <span data-i18n-text="item_details.find_projects">Find projects using this component</span>
              </a>
              <a href="chat.php" class="flex items-center gap-2 text-sm text-cyan-400 hover:text-cyan-300 transition-colors py-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                <span data-i18n-text="item_details.ask_ai">Ask AI about this component</span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script>
  function setMainImage(thumb, src) {
    document.getElementById('main-image').src = src;
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
  }

  async function runEnrichment() {
    const btn    = document.getElementById('btn-enrich');
    const status = document.getElementById('enrich-status');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> ' + (localizationController.t('item_details.fetching') || 'Fetching…');
    status.className = 'mt-3 text-xs text-slate-400';
    status.textContent = localizationController.t('item_details.connecting_to_urls') || 'Connecting to URLs…';
    status.classList.remove('hidden');

    try {
      const res  = await fetch('enrich_api.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ item_id: <?= (int)$item['id'] ?> })
      });
      const data = await res.json();

      if (data.ok) {
        status.innerHTML =
          data.log.map(l => `<div>${l}</div>`).join('') +
          `<div class="mt-1 text-emerald-400 font-semibold">✅ ${data.chars} chars cached from ${data.sources} source(s). Reload to see the badge.</div>`;
        status.className = 'mt-3 enrich-log';
        btn.innerHTML    = '✓ ' + (localizationController.t('item_details.enriched_reload') || 'Enriched — Reload to see');
        btn.classList.remove('btn-primary');
        btn.style.cssText = 'background:rgba(34,197,94,.2);border:1px solid rgba(34,197,94,.3);color:#4ade80;cursor:default;';
      } else {
        status.textContent = '❌ ' + (data.error || 'Enrichment failed.');
        status.className = 'mt-3 text-xs text-red-400';
        btn.disabled = false;
        btn.innerHTML = '🔄 ' + (localizationController.t('item_details.retry') || 'Retry');
      }
    } catch(e) {
      status.textContent = '❌ Network error: ' + e.message;
      status.className = 'mt-3 text-xs text-red-400';
      btn.disabled = false;
      btn.innerHTML = '🔄 ' + (localizationController.t('item_details.retry') || 'Retry');
    }
  }

  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  function toggleTheme(){const h=document.getElementById('html-root');const l=h.classList.toggle('light');localStorage.setItem('theme',l?'light':'dark');}
  localizationController.init();
  </script>
</body>
</html>
