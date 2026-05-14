<?php
/**
 * dashboard.php — Main Inventory Dashboard (Phase 2)
 * Lists all inventory items with search, filter, and actions.
 */
require 'db.php';
session_start();
if (!isset($_SESSION['authenticated'])) { header('Location: index.php'); exit; }

// Search / filter
$search   = trim($_GET['q']    ?? '');
$cat_filter = trim($_GET['cat'] ?? '');

$where_clauses = [];
$params        = [];

if ($search !== '') {
    $where_clauses[] = '(name LIKE ? OR model LIKE ? OR specs LIKE ? OR location LIKE ?)';
    $like = '%' . $search . '%';
    $params = array_merge($params, [$like, $like, $like, $like]);
}
if ($cat_filter !== '') {
    $where_clauses[] = 'category = ?';
    $params[] = $cat_filter;
}

$where_sql = $where_clauses ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
$stmt = $pdo->prepare("SELECT * FROM inventory $where_sql ORDER BY id DESC");
$stmt->execute($params);
$items = $stmt->fetchAll();

// Distinct categories for the filter dropdown
$cats = $pdo->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Stats
$total_items = (int)$pdo->query("SELECT COUNT(*) FROM inventory")->fetchColumn();
$total_qty   = (int)($pdo->query("SELECT SUM(quantity) FROM inventory")->fetchColumn() ?: 0);
$total_cats  = (int)$pdo->query("SELECT COUNT(DISTINCT category) FROM inventory WHERE category IS NOT NULL AND category != ''")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — DIY Lab Inventory</title>
  <meta name="description" content="Main control centre for your DIY lab hardware inventory.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #0a0a1a; }

    .bg-grid {
      background-image:
        linear-gradient(rgba(124,58,237,.04) 1px, transparent 1px),
        linear-gradient(90deg, rgba(124,58,237,.04) 1px, transparent 1px);
      background-size: 40px 40px;
    }

    .glass {
      background: rgba(255,255,255,.03);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,.07);
    }

    .glass-card {
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.07);
      transition: border-color .2s, transform .2s, box-shadow .2s;
    }
    .glass-card:hover {
      border-color: rgba(124,58,237,.4);
      transform: translateY(-2px);
      box-shadow: 0 8px 32px rgba(124,58,237,.15);
    }

    .badge-new         { background: rgba(34,197,94,.15);  color: #4ade80; border: 1px solid rgba(34,197,94,.3); }
    .badge-used        { background: rgba(251,191,36,.15); color: #fbbf24; border: 1px solid rgba(251,191,36,.3); }
    .badge-refurbished { background: rgba(99,179,237,.15); color: #60a5fa; border: 1px solid rgba(99,179,237,.3); }

    .btn-primary   { background: linear-gradient(135deg, #7c3aed, #06b6d4); }
    .btn-primary:hover { opacity: .9; transform: translateY(-1px); }

    .input-field {
      background: rgba(255,255,255,.05);
      border: 1px solid rgba(255,255,255,.1);
      color: #e2e8f0;
      transition: border-color .2s, box-shadow .2s;
    }
    .input-field:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 3px rgba(124,58,237,.2); }
    .input-field::placeholder { color: #4b5563; }

    .nav-link { color: #94a3b8; transition: color .2s; }
    .nav-link:hover, .nav-link.active { color: #c4b5fd; }

    .mobile-card { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.07); border-radius:12px; padding:1rem; transition:border-color .2s; }
    .mobile-card:active { border-color:rgba(124,58,237,.4); }

    .stat-card {
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.07);
      border-radius: 1rem;
      padding: 1.25rem 1.5rem;
      position: relative;
      overflow: hidden;
    }
    .stat-card::before {
      content: '';
      position: absolute;
      top: 0; left: 0; right: 0;
      height: 2px;
    }
    .stat-card.purple::before  { background: linear-gradient(90deg, #7c3aed, transparent); }
    .stat-card.cyan::before    { background: linear-gradient(90deg, #06b6d4, transparent); }
    .stat-card.emerald::before { background: linear-gradient(90deg, #10b981, transparent); }

    tr.item-row:hover td { background: rgba(124,58,237,.05); }
    td, th { transition: background .15s; }

    .thumbnail { width: 44px; height: 44px; object-fit: cover; border-radius: 8px; border: 1px solid rgba(255,255,255,.1); }
    .thumb-placeholder {
      width: 44px; height: 44px; border-radius: 8px;
      background: rgba(124,58,237,.15); border: 1px solid rgba(124,58,237,.2);
      display: flex; align-items: center; justify-content: center;
    }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
  <!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="p-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082"/>
          </svg>
        </div>
        <div>
          <p class="font-semibold text-white text-sm">DIY Lab</p>
          <p class="text-xs text-slate-500">Inventory System</p>
        </div>
      </div>
    </div>

    <nav class="flex-1 p-4 space-y-1">
      <a href="dashboard.php" class="nav-link active flex items-center gap-3 px-3 py-2.5 rounded-lg bg-purple-600/15 text-purple-300 text-sm font-medium">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
        Dashboard
      </a>
      <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
        Add Component
      </a>
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
        Creative Engine
      </a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
        Lab Assistant
      </a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
        AI Settings
      </a>
    </nav>

    <div class="p-4 border-t border-white/5">
      <a href="?logout=1" onclick="<?php if(isset($_GET['logout'])){ session_destroy(); header('Location: index.php'); exit; } ?>"
         class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition-colors text-sm"
         onclick="return confirm('Log out?')">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
        Logout
      </a>
    </div>
  </div>

  <!-- ── Main Content ─────────────────────────────────────────────────────── -->
  <main class="lg:ml-64 min-h-screen">
    <?php
    // Handle logout
    if (isset($_GET['logout'])) {
        session_destroy();
        header('Location: index.php');
        exit;
    }
    ?>

    <!-- Header -->
    <header class="sticky top-0 z-30 glass border-b border-white/5 px-4 lg:px-8 py-4 flex items-center justify-between gap-3">
      <div class="flex items-center gap-2 min-w-0">
        <button onclick="openSidebar()" class="lg:hidden flex-shrink-0 p-2 -ml-1 rounded-lg text-slate-400 hover:text-white hover:bg-white/5 transition-colors" aria-label="Open menu">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div class="min-w-0">
          <h1 class="text-lg lg:text-xl font-bold text-white truncate">Inventory Dashboard</h1>
          <p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> components &middot; <?= $total_qty ?> units</p>
        </div>
      </div>
      <a href="add_item.php" class="btn-primary flex-shrink-0 flex items-center gap-2 px-3 lg:px-4 py-2 rounded-xl text-sm font-semibold text-white transition-all shadow-lg shadow-purple-900/30">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
        <span class="hidden sm:inline">Add Component</span><span class="sm:hidden">Add</span>
      </a>
    </header>

    <div class="p-4 lg:p-8">

      <!-- Stats Row -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="stat-card purple">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Component Types</p>
          <p class="text-3xl font-bold text-white"><?= $total_items ?></p>
          <p class="text-xs text-purple-400 mt-1">unique parts</p>
        </div>
        <div class="stat-card cyan">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Total Units</p>
          <p class="text-3xl font-bold text-white"><?= $total_qty ?></p>
          <p class="text-xs text-cyan-400 mt-1">across all items</p>
        </div>
        <div class="stat-card emerald">
          <p class="text-xs text-slate-500 uppercase tracking-wider mb-1">Categories</p>
          <p class="text-3xl font-bold text-white"><?= $total_cats ?></p>
          <p class="text-xs text-emerald-400 mt-1">component groups</p>
        </div>
      </div>

      <!-- Search & Filter Bar -->
      <form method="GET" action="" class="flex flex-col sm:flex-row gap-3 mb-5">
        <div class="relative flex-1">
          <svg class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="q" id="search-input" value="<?= htmlspecialchars($search) ?>" placeholder="Search name, model, location…" class="input-field w-full rounded-xl pl-10 pr-4 py-2.5 text-sm">
        </div>
        <div class="flex gap-3">
          <select name="cat" id="cat-filter" class="input-field flex-1 sm:flex-none rounded-xl px-4 py-2.5 text-sm sm:min-w-[150px]">
            <option value="">All Categories</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= htmlspecialchars($c) ?>" <?= $cat_filter === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn-primary px-4 py-2.5 rounded-xl text-sm font-medium text-white">Filter</button>
          <?php if ($search || $cat_filter): ?>
            <a href="dashboard.php" class="px-4 py-2.5 rounded-xl text-sm text-slate-400 hover:text-white border border-white/10 hover:border-white/20 transition-all">Clear</a>
          <?php endif; ?>
        </div>
      </form>

      <!-- Mobile Cards (< md) -->
      <div class="md:hidden space-y-3">
        <?php if (empty($items)): ?>
          <div class="glass rounded-2xl p-10 text-center text-slate-600">
            <svg class="w-10 h-10 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <?= $search || $cat_filter ? 'No items match.' : 'No inventory yet. <a href="add_item.php" class="text-purple-400">Add first component →</a>' ?>
          </div>
        <?php else: foreach ($items as $item):
          $images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
          $thumb  = $images[0] ?? null;
          $badge_class = match($item['status']) { 'New' => 'badge-new', 'Used' => 'badge-used', 'Refurbished' => 'badge-refurbished', default => 'badge-used' };
        ?>
          <div class="mobile-card">
            <div class="flex items-start gap-3">
              <?php if ($thumb): ?>
                <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="w-12 h-12 rounded-lg object-cover flex-shrink-0 border border-white/10">
              <?php else: ?>
                <div class="w-12 h-12 rounded-lg bg-purple-600/15 border border-purple-600/20 flex items-center justify-center flex-shrink-0">
                  <svg class="w-5 h-5 text-purple-500/40" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
              <?php endif; ?>
              <div class="flex-1 min-w-0">
                <a href="item_details.php?id=<?= $item['id'] ?>" class="font-semibold text-white hover:text-purple-300 block truncate text-sm"><?= htmlspecialchars($item['name']) ?></a>
                <?php if ($item['model']): ?><p class="text-xs text-slate-500 font-mono truncate"><?= htmlspecialchars($item['model']) ?></p><?php endif; ?>
                <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                  <span class="<?= $badge_class ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= $item['status'] ?></span>
                  <span class="text-xs text-slate-500">×<?= (int)$item['quantity'] ?></span>
                  <?php if ($item['category']): ?><span class="text-xs text-slate-600">· <?= htmlspecialchars($item['category']) ?></span><?php endif; ?>
                </div>
                <?php if ($item['location']): ?><p class="text-xs text-slate-600 mt-1">📍 <?= htmlspecialchars($item['location']) ?></p><?php endif; ?>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-2 mt-3 pt-3 border-t border-white/5">
              <a href="item_details.php?id=<?= $item['id'] ?>" class="text-center text-xs text-cyan-400 border border-cyan-500/20 py-2 rounded-lg hover:bg-cyan-500/10 transition-colors">View</a>
              <a href="add_item.php?edit=<?= $item['id'] ?>" class="text-center text-xs text-purple-400 border border-purple-500/20 py-2 rounded-lg hover:bg-purple-500/10 transition-colors">Edit</a>
              <a href="delete_item.php?id=<?= $item['id'] ?>" onclick="return confirm('Delete this item?')" class="text-center text-xs text-red-400 border border-red-500/20 py-2 rounded-lg hover:bg-red-500/10 transition-colors">Delete</a>
            </div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Desktop Table (>= md) -->
      <div class="glass rounded-2xl overflow-hidden hidden md:block">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-white/5">
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider w-12">Img</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Name</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Model</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Category</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider w-16">Qty</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Status</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Location</th>
              <th class="text-left px-5 py-3.5 text-slate-500 font-medium text-xs uppercase tracking-wider">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($items)): ?>
            <tr>
              <td colspan="8" class="text-center py-16 text-slate-600">
                <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                <?= $search || $cat_filter ? 'No items match your search.' : 'No inventory yet. <a href="add_item.php" class="text-purple-400 hover:underline">Add your first component →</a>' ?>
              </td>
            </tr>
            <?php else: ?>
            <?php foreach ($items as $item): ?>
            <?php
              $images = json_decode($item['image_paths'] ?? '[]', true) ?: [];
              $thumb  = $images[0] ?? null;
              $badge_class = match($item['status']) {
                'New'         => 'badge-new',
                'Used'        => 'badge-used',
                'Refurbished' => 'badge-refurbished',
                default       => 'badge-used',
              };
            ?>
            <tr class="item-row border-b border-white/5 last:border-0">
              <td class="px-5 py-3.5">
                <?php if ($thumb): ?>
                  <img src="<?= htmlspecialchars($thumb) ?>" alt="" class="thumbnail">
                <?php else: ?>
                  <div class="thumb-placeholder">
                    <svg class="w-5 h-5 text-purple-500/50" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                  </div>
                <?php endif; ?>
              </td>
              <td class="px-5 py-3.5">
                <a href="item_details.php?id=<?= $item['id'] ?>" class="font-semibold text-white hover:text-purple-300 transition-colors">
                  <?= htmlspecialchars($item['name']) ?>
                </a>
              </td>
              <td class="px-5 py-3.5 text-slate-400 font-mono text-xs"><?= htmlspecialchars($item['model'] ?? '—') ?></td>
              <td class="px-5 py-3.5 text-slate-400"><?= htmlspecialchars($item['category'] ?? '—') ?></td>
              <td class="px-5 py-3.5">
                <span class="text-white font-bold"><?= (int)$item['quantity'] ?></span>
              </td>
              <td class="px-5 py-3.5">
                <span class="<?= $badge_class ?> text-xs px-2.5 py-1 rounded-full font-medium">
                  <?= htmlspecialchars($item['status']) ?>
                </span>
              </td>
              <td class="px-5 py-3.5 text-slate-400 font-mono text-xs"><?= htmlspecialchars($item['location'] ?? '—') ?></td>
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
        Showing <?= count($items) ?> of <?= $total_items ?> components
        <?= $search ? '· Search: <em>' . htmlspecialchars($search) . '</em>' : '' ?>
      </p>
    </div>
  </main>

  <script>
  function openSidebar(){
    document.getElementById('sidebar').classList.remove('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.remove('hidden');
    document.body.style.overflow='hidden';
  }
  function closeSidebar(){
    document.getElementById('sidebar').classList.add('-translate-x-full');
    document.getElementById('sidebar-overlay').classList.add('hidden');
    document.body.style.overflow='';
  }
  </script>
</body>
</html>
