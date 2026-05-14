<?php
/**
 * item_details.php — Component Detail Page with Gallery (Phase 3)
 */
require 'db.php';
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
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($item['name']) ?> — DIY Lab</title>
  <meta name="description" content="Component detail page for <?= htmlspecialchars($item['name']) ?> in your DIY Lab inventory.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; background-color: #0a0a1a; }
    .bg-grid { background-image: linear-gradient(rgba(124,58,237,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(124,58,237,.04) 1px, transparent 1px); background-size: 40px 40px; }
    .glass { background: rgba(255,255,255,.03); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,.07); }
    .badge-new         { background:rgba(34,197,94,.15);  color:#4ade80; border:1px solid rgba(34,197,94,.3); }
    .badge-used        { background:rgba(251,191,36,.15); color:#fbbf24; border:1px solid rgba(251,191,36,.3); }
    .badge-refurbished { background:rgba(99,179,237,.15); color:#60a5fa; border:1px solid rgba(99,179,237,.3); }
    .nav-link { color:#94a3b8; transition:color .2s; }
    .nav-link:hover { color:#c4b5fd; }
    .gallery-thumb {
      width:80px; height:80px; object-fit:cover; border-radius:10px;
      border:2px solid transparent; cursor:pointer; transition:all .2s;
    }
    @media(min-width:768px){.gallery-thumb{width:110px;height:110px;}}
    .gallery-thumb:hover, .gallery-thumb.active { border-color:#7c3aed; box-shadow:0 0 20px rgba(124,58,237,.3); }
    .main-image { width:100%; max-height:320px; object-fit:contain; border-radius:16px; background:rgba(255,255,255,.03); border:1px solid rgba(255,255,255,.07); }
    @media(min-width:768px){.main-image{max-height:400px;}}
    .spec-block { font-family:'JetBrains Mono',monospace; font-size:.8rem; }
    .btn-primary { background:linear-gradient(135deg,#7c3aed,#06b6d4); transition:all .2s; }
    .btn-primary:hover { opacity:.9; transform:translateY(-1px); }
    .info-row { display:flex; align-items:baseline; gap:.75rem; padding:.75rem 0; border-bottom:1px solid rgba(255,255,255,.05); }
    .info-row:last-child { border-bottom:none; }
    .info-label { font-size:.7rem; text-transform:uppercase; letter-spacing:.1em; color:#64748b; min-width:90px; flex-shrink:0; }
    .info-value { color:#e2e8f0; font-size:.9rem; }
  </style>
</head>
<body class="bg-grid min-h-screen text-slate-200">

  <div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
  <!-- Sidebar -->
  <div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300">
    <div class="p-5 border-b border-white/5">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5"/></svg>
        </div>
        <div><p class="font-semibold text-white text-sm">DIY Lab</p><p class="text-xs text-slate-500">Inventory System</p></div>
      </div>
    </div>
    <nav class="flex-1 p-4 space-y-1">
      <a href="dashboard.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg> Dashboard
      </a>
      <a href="add_item.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg> Add Component
      </a>
      <a href="projects.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg> Creative Engine
      </a>
      <a href="chat.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg> Lab Assistant
      </a>
      <a href="settings.php" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-sm">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg> AI Settings
      </a>
    </nav>
  </div>

  <!-- Main -->
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
        <a href="add_item.php?edit=<?= $item['id'] ?>" class="text-xs sm:text-sm text-purple-400 hover:text-purple-300 border border-purple-500/20 hover:border-purple-500/40 px-2 sm:px-3 py-1.5 rounded-lg transition-all">✏️ <span class="hidden sm:inline">Edit</span></a>
        <a href="delete_item.php?id=<?= $item['id'] ?>" onclick="return confirm('Delete this component and all its images?')" class="text-xs sm:text-sm text-red-400 hover:text-red-300 border border-red-500/20 hover:border-red-500/40 px-2 sm:px-3 py-1.5 rounded-lg transition-all">🗑 <span class="hidden sm:inline">Delete</span></a>
      </div>
    </header>

    <div class="p-4 lg:p-8">
      <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

        <!-- Left: Gallery -->
        <div class="col-span-1 lg:col-span-3 space-y-4">
          <?php if (!empty($images)): ?>
          <!-- Main viewer -->
          <div class="glass rounded-2xl p-4">
            <img id="main-image" src="<?= htmlspecialchars($images[0]) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="main-image">
          </div>
          <!-- Thumbnails -->
          <?php if (count($images) > 1): ?>
          <div class="flex flex-wrap gap-3">
            <?php foreach ($images as $i => $img): ?>
            <img src="<?= htmlspecialchars($img) ?>"
                 alt="Angle <?= $i+1 ?>"
                 class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>"
                 onclick="setMainImage(this, '<?= htmlspecialchars($img) ?>')">
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <?php else: ?>
          <div class="glass rounded-2xl p-12 flex flex-col items-center justify-center text-slate-600">
            <svg class="w-16 h-16 mb-3 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
            <p class="text-sm">No photos yet</p>
            <a href="add_item.php?edit=<?= $item['id'] ?>" class="text-purple-400 text-xs mt-2 hover:underline">Add photos →</a>
          </div>
          <?php endif; ?>
        </div>

        <!-- Right: Details -->
        <div class="col-span-1 lg:col-span-2 space-y-5">
          <!-- Core info -->
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-4 text-sm uppercase tracking-wider">Component Info</h2>
            <div>
              <div class="info-row"><span class="info-label">Model</span><span class="info-value font-mono text-xs"><?= htmlspecialchars($item['model'] ?: '—') ?></span></div>
              <div class="info-row"><span class="info-label">Category</span><span class="info-value"><?= htmlspecialchars($item['category'] ?: '—') ?></span></div>
              <div class="info-row">
                <span class="info-label">Quantity</span>
                <span class="info-value">
                  <span class="text-2xl font-bold text-white"><?= (int)$item['quantity'] ?></span>
                  <span class="text-slate-500 text-xs ml-1">unit<?= (int)$item['quantity'] !== 1 ? 's' : '' ?></span>
                </span>
              </div>
              <div class="info-row"><span class="info-label">Condition</span><span class="<?= $badge_class ?> text-xs px-2 py-0.5 rounded-full font-medium"><?= htmlspecialchars($item['status']) ?></span></div>
              <div class="info-row"><span class="info-label">Location</span><span class="info-value font-mono text-xs"><?= htmlspecialchars($item['location'] ?: '—') ?></span></div>
              <div class="info-row"><span class="info-label">Added</span><span class="info-value text-xs"><?= date('d M Y', strtotime($item['created_at'])) ?></span></div>
            </div>
          </div>

          <!-- Specs -->
          <?php if ($item['specs']): ?>
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3 text-sm uppercase tracking-wider">Technical Specifications</h2>
            <div class="spec-block text-slate-300 leading-relaxed whitespace-pre-wrap bg-black/20 rounded-xl p-4 border border-white/5">
              <?= htmlspecialchars($item['specs']) ?>
            </div>
          </div>
          <?php endif; ?>

          <!-- Quick actions -->
          <div class="glass rounded-2xl p-5">
            <h2 class="font-semibold text-white mb-3 text-sm uppercase tracking-wider">Actions</h2>
            <div class="space-y-2">
              <a href="projects.php" class="flex items-center gap-2 text-sm text-purple-400 hover:text-purple-300 transition-colors py-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3"/></svg>
                Find projects using this component
              </a>
              <a href="chat.php" class="flex items-center gap-2 text-sm text-cyan-400 hover:text-cyan-300 transition-colors py-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                Ask AI about this component
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
  function openSidebar(){document.getElementById('sidebar').classList.remove('-translate-x-full');document.getElementById('sidebar-overlay').classList.remove('hidden');document.body.style.overflow='hidden';}
  function closeSidebar(){document.getElementById('sidebar').classList.add('-translate-x-full');document.getElementById('sidebar-overlay').classList.add('hidden');document.body.style.overflow='';}
  </script>
</body>
</html>
