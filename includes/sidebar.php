<?php
$current_page = basename($_SERVER['PHP_SELF']);
$nav_items = [
    'dashboard.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>', 'text' => 'Dashboard', 'i18n' => 'nav.dashboard'],
    'add_item.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>', 'text' => 'Add Component', 'i18n' => 'nav.add_component'],
    'locations.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'text' => 'Locations', 'i18n' => 'nav.locations'],
    'projects.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>', 'text' => 'Creative Engine', 'i18n' => 'nav.creative_engine'],
    'chat.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>', 'text' => 'Lab Assistant', 'i18n' => 'nav.lab_assistant'],
    'settings.php' => ['icon' => '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>', 'text' => 'User Settings', 'i18n' => 'nav.user_settings'],
];

// Ensure site config variables exist if this file is included before or differently.
$sidebar_site_name = $site_name ?? 'DIY Lab';
$sidebar_site_tagline = $site_mini_tagline ?? 'Inventory System';
?>
<!-- ── Sidebar ─────────────────────────────────────────────────────────── -->
<div id="sidebar-overlay" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-40 hidden" onclick="closeSidebar()"></div>
<div id="sidebar" class="fixed inset-y-0 left-0 w-64 glass border-r border-white/5 flex flex-col z-50 -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out">
  <div class="p-5 border-b border-white/5">
    <div class="flex items-center gap-3">
      <?php if (!empty($site_logo_url)): ?>
        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" class="w-9 h-9 rounded-lg object-cover flex-shrink-0">
      <?php else: ?>
        <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-purple-600 to-cyan-500 flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.104v5.714a2.25 2.25 0 01-.659 1.591L5 14.5M9.75 3.104c-.251.023-.501.05-.75.082m.75-.082a24.3 24.3 0 014.5 0m0 0v5.714c0 .597.237 1.17.659 1.591L19.8 15.3M14.25 3.104c.251.023.501.05.75.082" />
          </svg>
        </div>
      <?php endif; ?>
      <div>
        <p class="font-semibold text-white text-sm"><?= htmlspecialchars($sidebar_site_name) ?></p>
        <p class="text-xs text-slate-500"><?= htmlspecialchars($sidebar_site_tagline) ?></p>
      </div>
    </div>
  </div>

  <nav class="flex-1 p-4 space-y-1">
    <?php foreach ($nav_items as $url => $item): 
      $isActive = ($current_page === $url);
      $activeClasses = $isActive ? 'active bg-purple-600/15 text-purple-300 font-medium' : 'hover:bg-white/5';
    ?>
      <a href="<?= $url ?>" data-i18n="<?= $item['i18n'] ?>" class="nav-link flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm <?= $activeClasses ?>">
        <?= $item['icon'] ?>
        <?= $item['text'] ?>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="p-4 border-t border-white/5">
    <a href="#" onclick="openBugReporter(); return false;" class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-white/5 text-slate-400 hover:text-white transition-colors text-sm mb-2">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
      </svg>
      <span data-i18n-text="nav.submit_ticket">Submit a Ticket</span>
    </a>

    <!-- Theme toggle -->
    <div class="theme-toggle-wrap mb-2" id="theme-toggle-btn" onclick="toggleTheme()" role="button" aria-label="Toggle light mode" title="Toggle light/dark mode">
      <span class="theme-toggle-label">
        <svg id="theme-icon" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
        </svg>
        <span data-i18n-text="nav.light_mode">Light Mode</span>
      </span>
      <span class="toggle-pill" id="toggle-pill"></span>
    </div>
    <a href="logout.php"
      onclick="<?php if (isset($_GET['logout'])) { session_destroy(); header('Location: index.php'); exit; } ?>"
      class="flex items-center gap-3 px-3 py-2.5 rounded-lg hover:bg-red-500/10 text-slate-500 hover:text-red-400 transition-colors text-sm"
      onclick="return confirm('Log out?')">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
      </svg>
      <span data-i18n-text="nav.logout">Logout</span>
    </a>
  </div>
</div>

<?php require_once 'bug_reporter.php'; ?>
