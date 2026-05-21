<?php
/**
 * dashboard.php — Main Inventory Dashboard (Phase 2)
 * Lists all inventory items with search, filter, and actions.
 */
require 'db.php';
require 'image_helper.php';
require_once 'site_config.php';
session_start();
if (!isset($_SESSION['authenticated'])) {
  header('Location: index.php');
  exit;
}

// Bulk action feedback
$bulk_ok = $_GET['bulk_ok'] ?? '';
$bulk_count = (int) ($_GET['count'] ?? 0);
$bulk_error = $_GET['bulk_error'] ?? '';

// Search / filter
$search = trim($_GET['q'] ?? '');
$cat_filter = trim($_GET['cat'] ?? '');

$where_clauses = [];
$params = [];

if ($search !== '') {
  $where_clauses[] = '(name LIKE ? OR model LIKE ? OR specs LIKE ? OR location LIKE ?)';
  $like = '%' . $search . '%';
  $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($cat_filter !== '') {
  $where_clauses[] = 'category = ?';
  $params[] = $cat_filter;
}

// Sort
$allowed_sort = ['name', 'model', 'category', 'quantity', 'status', 'location', 'id'];
$sort_col = in_array($_GET['sort'] ?? '', $allowed_sort, true) ? $_GET['sort'] : 'id';
$sort_dir = (strtolower($_GET['dir'] ?? '') === 'asc') ? 'asc' : 'desc';

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
$stmt = $pdo->prepare("SELECT * FROM inventory $where_sql ORDER BY `$sort_col` $sort_dir");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Sort link + icon helpers
$sl = function (string $col) use ($sort_col, $sort_dir, $search, $cat_filter): string {
  $dir = ($sort_col === $col && $sort_dir === 'asc') ? 'desc' : 'asc';
  return '?' . http_build_query(array_filter(['q' => $search, 'cat' => $cat_filter, 'sort' => $col, 'dir' => $dir], fn($v) => $v !== ''));
};
$si = function (string $col) use ($sort_col, $sort_dir): string {
  if ($col !== $sort_col)
    return '<svg class="sort-icon" viewBox="0 0 10 14" fill="currentColor"><path d="M5 1l3 4H2l3-4zM5 13l-3-4h6l-3 4z"/></svg>';
  return $sort_dir === 'asc'
    ? '<svg class="sort-icon active" viewBox="0 0 10 14" fill="currentColor"><path d="M5 1l3 4H2l3-4z"/></svg>'
    : '<svg class="sort-icon active" viewBox="0 0 10 14" fill="currentColor"><path d="M5 13l-3-4h6l-3 4z"/></svg>';
};


// Distinct categories & locations for dropdowns / datalists
$cats = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$locations = $pdo->query("SELECT DISTINCT location  FROM inventory WHERE location  IS NOT NULL AND location  != '' ORDER BY location")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total_items = (int) $pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$total_qty = (int) ($pdo->query("SELECT SUM(quantity) FROM inventory")->fetchColumn() ?: 0);
$total_cats = (int) $pdo->query("SELECT COUNT(DISTINCT category) FROM inventory WHERE category IS NOT NULL AND category != ''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en" id="html-root">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — DIY Lab Inventory</title>
  <meta name="description" content="Main control centre for your DIY lab hardware inventory.">
  <link rel="stylesheet" href="assets/app.css">
  <script>if (localStorage.getItem('theme') === 'light') document.getElementById('html-root').classList.add('light');</script>
  <script src="assets/i18n.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    .glass-card {
      background: rgba(255, 255, 255, .04);
      border: 1px solid rgba(255, 255, 255, .07);
      transition: border-color .2s, transform .2s, box-shadow .2s;
    }

    .glass-card:hover {
      border-color: rgba(124, 58, 237, .4);
      transform: translateY(-2px);
      box-shadow: 0 8px 32px rgba(124, 58, 237, .15);
    }

    .mobile-card {
      background: rgba(255, 255, 255, .04);
      border: 1px solid rgba(255, 255, 255, .07);
      border-radius: 12px;
      padding: 1rem;
      transition: border-color .2s;
    }

    .mobile-card:active {
      border-color: rgba(124, 58, 237, .4);
    }

    .mobile-card.selected {
      border-color: rgba(124, 58, 237, .5);
      background: rgba(124, 58, 237, .06);
    }

    .stat-card {
      background: rgba(255, 255, 255, .03);
      border: 1px solid rgba(255, 255, 255, .07);
      border-radius: 1rem;
      padding: 1.25rem 1.5rem;
      position: relative;
      overflow: hidden;
    }

    .stat-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 2px;
    }

    .stat-card.purple::before {
      background: linear-gradient(90deg, #7c3aed, transparent);
    }

    .stat-card.cyan::before {
      background: linear-gradient(90deg, #06b6d4, transparent);
    }

    .stat-card.emerald::before {
      background: linear-gradient(90deg, #10b981, transparent);
    }

    tr.item-row:hover td {
      background: rgba(124, 58, 237, .05);
    }

    tr.item-row.selected td {
      background: rgba(124, 58, 237, .08) !important;
    }

    td,
    th {
      transition: background .15s;
    }

    .thumbnail {
      width: 44px;
      height: 44px;
      object-fit: cover;
      border-radius: 8px;
      border: 1px solid rgba(255, 255, 255, .1);
    }

    .thumb-placeholder {
      width: 44px;
      height: 44px;
      border-radius: 8px;
      background: rgba(124, 58, 237, .15);
      border: 1px solid rgba(124, 58, 237, .2);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* checkbox */
    .row-cb {
      width: 16px;
      height: 16px;
      accent-color: #7c3aed;
      cursor: pointer;
      flex-shrink: 0;
    }

    /* bulk bar */
    #bulk-bar {
      position: fixed;
      bottom: 1.5rem;
      left: 50%;
      transform: translateX(-50%) translateY(120%);
      z-index: 60;
      transition: transform .25s cubic-bezier(.34, 1.56, .64, 1);
      white-space: nowrap;
    }

    #bulk-bar.visible {
      transform: translateX(-50%) translateY(0);
    }

    .bulk-select {
      background: rgba(15, 15, 30, .95);
      border: 1px solid rgba(124, 58, 237, .4);
      backdrop-filter: blur(16px);
      border-radius: 16px;
      padding: .6rem .75rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      flex-wrap: wrap;
      box-shadow: 0 8px 40px rgba(0, 0, 0, .5), 0 0 0 1px rgba(124, 58, 237, .15);
    }

    .bulk-btn {
      font-size: .75rem;
      font-weight: 600;
      padding: .4rem .85rem;
      border-radius: 10px;
      border: 1px solid;
      cursor: pointer;
      transition: all .15s;
      background: transparent;
    }

    .bulk-btn.purple {
      color: #c4b5fd;
      border-color: rgba(124, 58, 237, .4);
    }

    .bulk-btn.purple:hover {
      background: rgba(124, 58, 237, .2);
    }

    .bulk-btn.cyan {
      color: #67e8f9;
      border-color: rgba(6, 182, 212, .4);
    }

    .bulk-btn.cyan:hover {
      background: rgba(6, 182, 212, .15);
    }

    .bulk-btn.emerald {
      color: #4ade80;
      border-color: rgba(34, 197, 94, .4);
    }

    .bulk-btn.emerald:hover {
      background: rgba(34, 197, 94, .15);
    }

    .bulk-btn.red {
      color: #f87171;
      border-color: rgba(239, 68, 68, .4);
    }

    .bulk-btn.red:hover {
      background: rgba(239, 68, 68, .15);
    }

    .bulk-btn.slate {
      color: #94a3b8;
      border-color: rgba(148, 163, 184, .2);
    }

    .bulk-btn.slate:hover {
      background: rgba(148, 163, 184, .08);
    }

    /* modal */
    #bulk-modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, .7);
      z-index: 70;
      align-items: center;
      justify-content: center;
      padding: 1rem;
    }

    #bulk-modal.open {
      display: flex;
    }

    .bulk-modal-box {
      background: #0f0f1e;
      border: 1px solid rgba(124, 58, 237, .3);
      border-radius: 20px;
      padding: 1.5rem;
      width: 100%;
      max-width: 380px;
    }

    /* sort icons */
    .sort-icon {
      width: 10px;
      height: 14px;
      flex-shrink: 0;
      opacity: .25;
      vertical-align: middle;
      transition: opacity .15s;
    }

    .sort-icon.active {
      opacity: 1;
      color: #a78bfa;
    }

    th.sortable a {
      color: inherit;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      cursor: pointer;
    }

    th.sortable a:hover {
      color: #e2e8f0;
    }

    th.sortable a:hover .sort-icon {
      opacity: .6;
    }

    th.sortable.sorted a {
      color: #c4b5fd;
    }

    #toast {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 80;
      padding: .65rem 1.1rem;
      border-radius: 12px;
      font-size: .82rem;
      font-weight: 500;
      transition: opacity .4s;
    }
  </style>
</head>

<body class="bg-grid min-h-screen text-slate-200">
  <?php include 'includes/sidebar.php'; ?>



  <!-- ── Main Content ─────────────────────────────────────────────────────── -->
  <main class="lg:ml-64 min-h-screen">


    <!-- Header -->
    <header
      class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <button onclick="openSidebar()"
          class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors"
          aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>
        <div class="min-w-0">
          <h1 class="text-lg lg:text-xl font-bold text-white truncate" data-i18n-text="dashboard.title">Inventory Dashboard</h1>
          <p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> <span data-i18n-text="dashboard.components">components</span> &middot; <?= $total_qty ?> <span data-i18n-text="dashboard.units">units</span></p>
        </div>
      </div>
      <div class="flex items-center gap-2 flex-shrink-0">

        <a href="bulk_import.php"
          class="btn-secondary flex items-center gap-2 px-3 lg:px-4 py-2 rounded-xl text-sm font-semibold transition-all"
          title="Bulk import from folder">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
          </svg>
          <span class="hidden sm:inline" data-i18n-text="nav.bulk_import">Bulk Import</span>
        </a>
        <a href="add_item.php"
          class="btn-primary flex items-center gap-2 px-3 lg:px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all shadow-lg shadow-purple-900/30">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4" />
          </svg>
          <span class="hidden sm:inline" data-i18n-text="nav.add_component">Add Component</span><span class="sm:hidden" data-i18n-text="common.add">Add</span>
        </a>
      </div>
    </header>

    <?php
    // Toast message from bulk action
    $toast_msg = '';
    $toast_cls = '';
    if ($bulk_ok) {
      $labels = ['category' => 'Category', 'status' => 'Status', 'location' => 'Location', 'delete' => 'Deleted'];
      $lbl = $labels[$bulk_ok] ?? ucfirst($bulk_ok);
      $toast_msg = $bulk_ok === 'delete' ? "🗑 {$bulk_count} item(s) deleted." : "✅ {$lbl} updated on {$bulk_count} item(s).";
      $toast_cls = 'bg-emerald-500/20 border border-emerald-500/30 text-emerald-300';
    } elseif ($bulk_error) {
      $toast_msg = '❌ Bulk action failed: ' . htmlspecialchars($bulk_error);
      $toast_cls = 'bg-red-500/20 border border-red-500/30 text-red-300';
    }
    ?>
    <?php if ($toast_msg): ?>
      <div id="toast" class="<?= $toast_cls ?>"><?= $toast_msg ?></div>
    <?php endif; ?>

    <div class="p-4 lg:p-8">

      <!-- Stats Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card purple">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1" data-i18n-text="dashboard.stat_component_types">Component Types</p>
          <p class="text-3xl font-bold text-white"><?= $total_items ?></p>
          <p class="text-xs text-purple-400 mt-1" data-i18n-text="dashboard.stat_unique_parts">unique parts</p>
        </div>
        <div class="stat-card cyan">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1" data-i18n-text="dashboard.stat_total_units">Total Units</p>
          <p class="text-3xl font-bold text-white"><?= $total_qty ?></p>
          <p class="text-xs text-cyan-400 mt-1" data-i18n-text="dashboard.stat_across_all">across all items</p>
        </div>
        <div class="stat-card emerald">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1" data-i18n-text="dashboard.stat_categories">Categories</p>
          <p class="text-3xl font-bold text-white"><?= $total_cats ?></p>
          <p class="text-xs text-emerald-400 mt-1" data-i18n-text="dashboard.stat_component_groups">component groups</p>
        </div>
      </div>

      <!-- Search & Filter Bar -->
      <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1">
          <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24"
            stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
          </svg>
          <input type="text" name="q" id="search-input" value="<?= htmlspecialchars($search) ?>"
            data-i18n-placeholder="dashboard.search_placeholder" placeholder="Search name, model, location…" class="input-field w-full rounded-xl pl-10 pr-4 py-2.5 text-sm">
        </div>
        <div class="flex gap-3">
          <select name="cat" id="cat-filter"
            class="input-field flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm sm:min-w-[150px]">
            <option value="" data-i18n-text="dashboard.all_categories">All Categories</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $cat_filter === $c ? 'selected' : '' ?>>
                <?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit"
            class="btn-primary px-4 py-2.5 rounded-xl text-sm font-medium text-white" data-i18n-text="common.filter">Filter</button>
          <?php if ($search || $cat_filter): ?>
            <a href="dashboard.php"
              class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white border border-white/10 hover:border-white/20 transition-all" data-i18n-text="dashboard.clear">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Mobile Cards (< md) -->
      <div class="md:hidden space-y-3">
        <?php if (empty($items)): ?>
          <div class="glass rounded-2xl p-10 text-center text-slate-600">
            <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <?= $search || $cat_filter ? 'No items match.' : 'No inventory yet. <a href="add_item.php" class="text-purple-400">Add first component →</a>' ?>
          </div>
        <?php else:
          foreach ($items as $item):
            $images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
            $thumb = $images[0] ? derive_thumb($images[0]) : null;
            $badge_class = match ($item['status']) { 'New' => 'badge-new', 'Used' => 'badge-used', 'Refurbished' => 'badge-refurbished', default => 'badge-used'};
            ?>
            <div class="mobile-card" data-id="<?= $item['id'] ?>">
              <div class="flex items-start gap-3">
                <input type="checkbox" class="row-cb item-cb mt-1" value="<?= $item['id'] ?>"
                  onclick="event.stopPropagation()">
                <?php if ($thumb): ?>
                  <img src="<?= htmlspecialchars($thumb) ?>" alt=""
                    class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-white/10">
                <?php else: ?>
                  <div
                    class="w-12 h-12 rounded-lg bg-purple-600/15 border border-purple-600/20 flex items-center justify-center flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-500/40" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                  </div>
                <?php endif; ?>
                <div class="flex-1 min-w-0">
                  <a href="item_details.php?id=<?= $item['id'] ?>"
                    class="font-semibold text-white hover:text-purple-300 block truncate text-sm"><?= htmlspecialchars($item['name']) ?></a>
                  <?php if ($item['model']): ?>
                    <p class="text-xs text-slate-500 font-mono truncate"><?= htmlspecialchars($item['model']) ?></p>
                  <?php endif; ?>
                  <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span
                      class="<?= $badge_class ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= $item['status'] ?></span>
                    <span class="text-xs text-slate-500">×<?= (int) $item['quantity'] ?></span>
                    <?php if ($item['category']): ?><span class="text-xs text-slate-600">·
                        <?= htmlspecialchars($item['category']) ?></span><?php endif; ?>
                  </div>
                  <?php if ($item['location']): ?>
                    <p class="text-xs text-slate-600 mt-1">📍 <?= htmlspecialchars($item['location']) ?></p><?php endif; ?>
                </div>
              </div>

              <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-white/5">
                <a href="item_details.php?id=<?= $item['id'] ?>"
                  class="text-center text-xs text-cyan-400 border border-cyan-500/20 py-2 rounded-lg hover:bg-cyan-500/10 transition-colors">View</a>
                <a href="add_item.php?edit=<?= $item['id'] ?>"
                  class="text-center text-xs text-purple-400 border border-purple-500/20 py-2 rounded-lg hover:bg-purple-500/10 transition-colors">Edit</a>
                <a href="delete_item.php?id=<?= $item['id'] ?>" onclick="return confirm('Delete this item?')"
                  class="text-center text-xs text-red-400 border border-red-500/20 py-2 rounded-lg hover:bg-red-500/10 transition-colors">Delete</a>
              </div>
            </div>
          <?php endforeach; endif; ?>
      </div>

      <!-- Desktop Table (>= md) -->
      <div class="glass rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/5">
              <th class="px-4 py-3.5 w-10">
                <input type="checkbox" id="cb-all" class="row-cb" title="Select all">
              </th>
              <th class="text-left px-3 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider w-12" data-i18n-text="dashboard.img">Img
              </th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable <?= $sort_col === 'name' ? 'sorted' : '' ?>">
                <a href="<?= $sl('name') ?>"><?= $si('name') ?> Name</a></th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable <?= $sort_col === 'model' ? 'sorted' : '' ?>">
                <a href="<?= $sl('model') ?>"><?= $si('model') ?> Model</a></th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable <?= $sort_col === 'category' ? 'sorted' : '' ?>">
                <a href="<?= $sl('category') ?>"><?= $si('category') ?> Category</a></th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable w-16 <?= $sort_col === 'quantity' ? 'sorted' : '' ?>">
                <a href="<?= $sl('quantity') ?>"><?= $si('quantity') ?> Qty</a></th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable <?= $sort_col === 'status' ? 'sorted' : '' ?>">
                <a href="<?= $sl('status') ?>"><?= $si('status') ?> Status</a></th>
              <th
                class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider sortable <?= $sort_col === 'location' ? 'sorted' : '' ?>">
                <a href="<?= $sl('location') ?>"><?= $si('location') ?> Location</a></th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider" data-i18n-text="dashboard.actions">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
              <tr>
                <td colspan="8" class="text-center py-16 text-slate-600">
                  <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                  </svg>
                  <?= $search || $cat_filter ? 'No items match your search.' : 'No inventory yet. <a href="add_item.php" class="text-purple-400 hover:underline">Add your first component →</a>' ?>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($items as $item): ?>
                <?php
                $images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
                $thumb = $images[0] ? derive_thumb($images[0]) : null;
                $badge_class = match ($item['status']) {
                  'New' => 'badge-new',
                  'Used' => 'badge-used',
                  'Refurbished' => 'badge-refurbished',
                  default => 'badge-used',
                };
                ?>
                <tr class="item-row border-b border-white/5 last:border-0" data-id="<?= $item['id'] ?>">
                  <td class="px-4 py-3.5">
                    <input type="checkbox" class="row-cb item-cb" value="<?= $item['id'] ?>">
                  </td>
                  <td class="px-3 py-3.5">
                    <?php if ($thumb): ?>
                      <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="thumbnail">
                    <?php else: ?>
                      <div class="thumb-placeholder">
                        <svg class="w-5 h-5 text-purple-500/50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td class="px-5 py-3.5">
                    <a href="item_details.php?id=<?= $item['id'] ?>"
                      class="font-semibold text-white hover:text-purple-300 transition-colors">
                      <?= htmlspecialchars($item['name']) ?>
                    </a>
                  </td>
                  <td class="px-5 py-3.5 text-slate-400 font-mono text-xs"><?= htmlspecialchars($item['model'] ?? '—') ?>
                  </td>
                  <td class="px-5 py-3.5 text-slate-400"><?= htmlspecialchars($item['category'] ?? '—') ?></td>
                  <td class="px-5 py-3.5">
                    <span class="text-white font-bold"><?= (int) $item['quantity'] ?></span>
                  </td>
                  <td class="px-5 py-3.5">
                    <span class="<?= $badge_class ?> text-xs px-2.5 py-1 rounded-full font-medium">
                      <?= htmlspecialchars($item['status']) ?>
                    </span>
                  </td>
                  <td class="px-5 py-3.5 text-slate-400 font-mono text-xs"><?= htmlspecialchars($item['location'] ?? '—') ?>
                  </td>
                  <td class="px-5 py-3.5">
                    <div class="flex items-center gap-2">
                      <a href="item_details.php?id=<?= $item['id'] ?>"
                        class="text-xs text-cyan-400 hover:text-cyan-300 transition-colors px-2.5 py-1 rounded-lg border border-cyan-500/20 hover:border-cyan-500/40">
                        View
                      </a>
                      <a href="add_item.php?edit=<?= $item['id'] ?>"
                        class="text-xs text-purple-400 hover:text-purple-300 transition-colors px-2.5 py-1 rounded-lg border border-purple-500/20 hover:border-purple-500/40">
                        Edit
                      </a>
                      <a href="delete_item.php?id=<?= $item['id'] ?>"
                        onclick="return confirm('Delete \'<?= htmlspecialchars(addslashes($item['name'])) ?>\'? This cannot be undone.')"
                        class="text-xs text-red-400 hover:text-red-300 transition-colors px-2.5 py-1 rounded-lg border border-red-500/20 hover:border-red-500/40">
                        Delete
                      </a>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <p class="text-xs text-slate-600 mt-4">
        <span data-i18n-text="dashboard.showing">Showing </span><?= count($items) ?> <span data-i18n-text="dashboard.of">of</span> <?= $total_items ?> <span data-i18n-text="dashboard.components_word">components</span>
        <?= $search ? '· Search: <em>' . htmlspecialchars($search) . '</em>' : '' ?>
      </p>
    </div>
  </main>

  <!-- ── Bulk Action Bar ───────────────────────────────────────────────── -->
  <div id="bulk-bar" role="toolbar" aria-label="Bulk actions">
    <div class="bulk-select">
      <span id="bulk-count-label" class="text-xs text-slate-400 px-1"></span>
      <div class="w-px h-5 bg-white/10"></div>
      <button class="bulk-btn purple" onclick="openModal('category')" data-i18n-text="dashboard.category">📁 Category</button>
      <button class="bulk-btn cyan" onclick="openModal('status')" data-i18n-text="dashboard.status">🔖 Status</button>
      <button class="bulk-btn emerald" onclick="openModal('location')" data-i18n-text="dashboard.location">📍 Location</button>
      <button class="bulk-btn cyan" onclick="printSelected()" title="Print QR labels for selected items" data-i18n-text="dashboard.print_labels">🏷️ Print
        Labels</button>
      <button class="bulk-btn slate" onclick="submitBulk('export_csv')" data-i18n-text="dashboard.export_csv">⬇ Export CSV</button>
      <button class="bulk-btn red" onclick="confirmDelete()" data-i18n-text="dashboard.delete">🗑 Delete</button>
      <button class="bulk-btn slate" onclick="clearSelection()" title="Clear selection">✕</button>
    </div>
  </div>

  <!-- ── Bulk Modal ────────────────────────────────────────────────────── -->
  <div id="bulk-modal">
    <div class="bulk-modal-box">
      <h3 id="modal-title" class="font-semibold text-white text-base mb-4"></h3>

      <!-- Category -->
      <div id="modal-category" class="modal-pane hidden">
        <label class="form-label" for="val-category" data-i18n-text="dashboard.new_category">New Category</label>
        <input id="val-category" list="cat-list" autocomplete="off"
          class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1" placeholder="e.g. Microcontroller">
        <datalist id="cat-list">
          <?php foreach ($cats as $c): ?>
            <option value="<?= htmlspecialchars($c) ?>"><?php endforeach; ?>
        </datalist>
      </div>

      <!-- Status -->
      <div id="modal-status" class="modal-pane hidden">
        <label class="form-label" for="val-status" data-i18n-text="dashboard.new_status">New Status</label>
        <select id="val-status" class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1">
          <option>New</option>
          <option>Used</option>
          <option>Refurbished</option>
        </select>
      </div>

      <!-- Location -->
      <div id="modal-location" class="modal-pane hidden">
        <label class="form-label" for="val-location" data-i18n-text="dashboard.new_location">New Location</label>
        <input id="val-location" list="loc-list" autocomplete="off"
          class="input-field w-full rounded-xl px-4 py-2.5 text-sm mt-1" placeholder="e.g. BIN-A3">
        <datalist id="loc-list">
          <?php foreach ($locations as $l): ?>
            <option value="<?= htmlspecialchars($l) ?>"><?php endforeach; ?>
        </datalist>
      </div>

      <div class="flex gap-3 mt-5">
        <button id="modal-ok" onclick="applyModal()"
          class="btn-primary flex-1 py-2.5 rounded-xl font-semibold text-white text-sm" data-i18n-text="dashboard.apply">Apply</button>
        <button onclick="closeModal()"
          class="flex-1 py-2.5 rounded-xl text-sm text-slate-400 border border-white/10 hover:border-white/20 transition-all" data-i18n-text="dashboard.cancel">Cancel</button>
      </div>
    </div>
  </div>

  <!-- Hidden form used for POST submissions -->
  <form id="bulk-form" method="POST" action="bulk_action.php" style="display:none">
    <input type="hidden" name="bulk_op" id="bulk-action-field">
    <input type="hidden" name="value" id="bulk-value-field">
    <div id="bulk-ids-container"></div>
  </form>

  <script>
    // ── Sidebar ───────────────────────────────────────────────────────────
    function openSidebar() { document.getElementById('sidebar').classList.remove('-translate-x-full'); document.getElementById('sidebar-overlay').classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
    function closeSidebar() { document.getElementById('sidebar').classList.add('-translate-x-full'); document.getElementById('sidebar-overlay').classList.add('hidden'); document.body.style.overflow = ''; }
    // ── Theme toggle ──────────────────────────────────────────────────────
    function toggleTheme() {
      const html = document.getElementById('html-root');
      const isLight = html.classList.toggle('light');
      localStorage.setItem('theme', isLight ? 'light' : 'dark');
    }
    // ── i18n ──────────────────────────────────────────────────────────────
    localizationController.init();

    // ── Toast auto-dismiss ────────────────────────────────────────────────
    const toastEl = document.getElementById('toast');
    if (toastEl) setTimeout(() => { toastEl.style.opacity = '0'; setTimeout(() => toastEl.remove(), 400); }, 3500);

    // ── Bulk selection state ──────────────────────────────────────────────
    const bar = document.getElementById('bulk-bar');
    const cbAll = document.getElementById('cb-all');
    const countLbl = document.getElementById('bulk-count-label');
    let selected = new Set();

    function updateBar() {
      const n = selected.size;
      const totalItems = new Set([...document.querySelectorAll('.item-cb')].map(cb => cb.value)).size;
      countLbl.textContent = n + ' item' + (n !== 1 ? 's' : '') + ' selected';
      bar.classList.toggle('visible', n > 0);
      if (cbAll) cbAll.indeterminate = n > 0 && n < totalItems;
      if (cbAll) cbAll.checked = totalItems > 0 && n === totalItems;
    }

    function toggleRow(cb) {
      const id = cb.value;
      const row = cb.closest('[data-id]');
      if (cb.checked) { selected.add(id); row?.classList.add('selected'); }
      else { selected.delete(id); row?.classList.remove('selected'); }
      updateBar();
    }

    // Per-row checkboxes
    document.querySelectorAll('.item-cb').forEach(cb => {
      cb.addEventListener('change', () => toggleRow(cb));
    });

    // Clicking a mobile card toggles its checkbox
    document.querySelectorAll('.mobile-card').forEach(card => {
      card.addEventListener('click', e => {
        if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
        const cb = card.querySelector('.item-cb');
        if (!cb) return;
        cb.checked = !cb.checked;
        toggleRow(cb);
      });
    });

    // Select-all
    if (cbAll) cbAll.addEventListener('change', () => {
      const allCbs = document.querySelectorAll('.item-cb');
      const totalItems = new Set([...allCbs].map(cb => cb.value)).size;
      // If anything is unselected → select all; otherwise deselect all
      const shouldSelect = selected.size < totalItems;
      cbAll.checked = shouldSelect;
      allCbs.forEach(cb => {
        cb.checked = shouldSelect;
        const id = cb.value;
        const row = cb.closest('[data-id]');
        if (shouldSelect) { selected.add(id); row?.classList.add('selected'); }
        else { selected.delete(id); row?.classList.remove('selected'); }
      });
      updateBar(); // single call after all rows updated
    });

    function clearSelection() {
      selected.clear();
      document.querySelectorAll('.item-cb').forEach(cb => { cb.checked = false; });
      document.querySelectorAll('[data-id]').forEach(el => el.classList.remove('selected'));
      updateBar();
    }

    // ── Print QR labels for selected items ───────────────────────────────────
    function printSelected() {
      if (!selected.size) return;
      const ids = [...selected].join(',');
      window.open(`print_labels.php?ids=${ids}`, '_blank');
    }

    function submitBulk(action, value = '') {
      document.getElementById('bulk-action-field').value = action;
      document.getElementById('bulk-value-field').value = value;
      const container = document.getElementById('bulk-ids-container');
      container.innerHTML = '';
      selected.forEach(id => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'ids[]'; inp.value = id;
        container.appendChild(inp);
      });
      document.getElementById('bulk-form').submit();
    }

    // ── Delete confirm ────────────────────────────────────────────────────
    function confirmDelete() {
      const n = selected.size;
      if (!confirm(`Delete ${n} item${n !== 1 ? 's' : ''}? This will also remove all associated images and cannot be undone.`)) return;
      submitBulk('delete');
    }

    // ── Modal ─────────────────────────────────────────────────────────────
    let activePane = null;
    const modal = document.getElementById('bulk-modal');
    const titles = { category: 'Set Category', status: 'Set Status', location: 'Set Location' };

    function openModal(type) {
      document.querySelectorAll('.modal-pane').forEach(p => p.classList.add('hidden'));
      const pane = document.getElementById('modal-' + type);
      if (!pane) return;
      pane.classList.remove('hidden');
      activePane = type;
      document.getElementById('modal-title').textContent = titles[type] || type;
      modal.classList.add('open');
      pane.querySelector('input,select')?.focus();
    }

    function closeModal() { modal.classList.remove('open'); activePane = null; }

    function applyModal() {
      if (!activePane) return;
      const pane = activePane;          // capture before closeModal clears it
      let value = '';
      if (pane === 'category') value = document.getElementById('val-category').value.trim();
      if (pane === 'status') value = document.getElementById('val-status').value;
      if (pane === 'location') value = document.getElementById('val-location').value.trim();
      closeModal();
      submitBulk('set_' + pane, value);
    }

    // Close modal on backdrop click
    modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });

    // Allow Enter key inside modal inputs to apply
    document.querySelectorAll('.modal-pane input').forEach(inp => {
      inp.addEventListener('keydown', e => { if (e.key === 'Enter') applyModal(); });
    });
  </script>
</body>

</html>