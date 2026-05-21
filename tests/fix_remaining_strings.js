const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 1. Fix container_manifest.php
const manifestPath = path.join(ROOT_DIR, 'container_manifest.php');
let manifest = fs.readFileSync(manifestPath, 'utf8');

manifest = manifest.replace(
    '<p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> component types · <?= $total_qty ?> total units</p>',
    '<p class="text-xs text-slate-500 mt-0.5"><?= $total_items ?> <span data-i18n-text="container_manifest.component_types_word">component types</span> &middot; <?= $total_qty ?> <span data-i18n-text="container_manifest.total_units_word">total units</span></p>'
);

manifest = manifest.replace(
    '<?= $total_items ?> component types &middot; <?= $total_qty ?> total units &middot;',
    '<?= $total_items ?> <span data-i18n-text="container_manifest.component_types_word">component types</span> &middot; <?= $total_qty ?> <span data-i18n-text="container_manifest.total_units_word">total units</span> &middot;'
);

fs.writeFileSync(manifestPath, manifest, 'utf8');


// 2. Fix projects.php
const projectsPath = path.join(ROOT_DIR, 'projects.php');
let projects = fs.readFileSync(projectsPath, 'utf8');

projects = projects.replace(
    '<span class="hidden sm:inline"><?= $has_cache ? \'Regenerate\' : \'Brainstorm Projects\' ?></span>',
    '<span class="hidden sm:inline"><?= $has_cache ? \'<span data-i18n-text="projects.regenerate_btn">Regenerate</span>\' : \'<span data-i18n-text="projects.brainstorm_projects">Brainstorm Projects</span>\' ?></span>'
);

projects = projects.replace(
    '<p class="text-sm text-slate-400">Showing results from <span class="text-purple-300 font-medium"><?= htmlspecialchars($cache_age) ?></span>. Regenerate any time for fresh ideas.</p>',
    '<p class="text-sm text-slate-400"><span data-i18n-text="projects.showing_results_from">Showing results from</span> <span class="text-purple-300 font-medium"><?= htmlspecialchars($cache_age) ?></span>. <span data-i18n-text="projects.regenerate_any_time">Regenerate any time for fresh ideas.</span></p>'
);

projects = projects.replace(
    '<p class="text-slate-400 text-sm mb-6"><?= $item_count ?> components in your lab.<br>Click below to let AI suggest creative projects.</p>',
    '<p class="text-slate-400 text-sm mb-6"><?= $item_count ?> <span data-i18n-text="projects.components_in_your_lab">components in your lab.</span><br><span data-i18n-text="projects.click_below_to_let_ai">Click below to let AI suggest creative projects.</span></p>'
);

projects = projects.replace(
    '<p class="text-sm text-slate-500">${projects.length} project ideas${fromCache ? \' (cached)\' : \' generated\'}</p>',
    '<p class="text-sm text-slate-500">${projects.length} <span data-i18n-text="projects.project_ideas">project ideas</span>${fromCache ? \' <span data-i18n-text="projects.cached">(cached)</span>\' : \' <span data-i18n-text="projects.generated">generated</span>\'}</p>'
);

projects = projects.replace(
    '<span class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 px-3 py-1 rounded-full">Powered by ${esc(provider)}</span>',
    '<span class="text-xs text-purple-400 bg-purple-500/10 border border-purple-500/20 px-3 py-1 rounded-full"><span data-i18n-text="projects.powered_by">Powered by</span> ${esc(provider)}</span>'
);

projects = projects.replace(
    '<p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Components from your lab</p>',
    '<p class="text-xs text-slate-500 uppercase tracking-wider mb-2" data-i18n-text="projects.components_from_your_lab">Components from your lab</p>'
);

projects = projects.replace(
    '<p class="text-xs text-slate-500 uppercase tracking-wider mb-2">Parts to acquire</p>',
    '<p class="text-xs text-slate-500 uppercase tracking-wider mb-2" data-i18n-text="projects.parts_to_acquire">Parts to acquire</p>'
);

projects = projects.replace(
    '          📐 Generate Full Blueprint &amp; Code',
    '          📐 <span data-i18n-text="projects.generate_full_blueprint">Generate Full Blueprint &amp; Code</span>'
);

projects = projects.replace(
    /document\.getElementById\('results-area'\)\.innerHTML = html;/g,
    'document.getElementById(\'results-area\').innerHTML = html;\n  localizationController.applyTranslations();'
);

fs.writeFileSync(projectsPath, projects, 'utf8');

// 3. Fix chat.php
const chatPath = path.join(ROOT_DIR, 'chat.php');
let chat = fs.readFileSync(chatPath, 'utf8');

chat = chat.replace(
    'I have full access to your current inventory. Ask me anything — project ideas, component questions, wiring help, code snippets, or troubleshooting!',
    '<span data-i18n-text="chat.ai_welcome_message">I have full access to your current inventory. Ask me anything — project ideas, component questions, wiring help, code snippets, or troubleshooting!</span>'
);

fs.writeFileSync(chatPath, chat, 'utf8');

// 4. Fix locations.php popup "Print Sticker" button
const locPath = path.join(ROOT_DIR, 'locations.php');
let locs = fs.readFileSync(locPath, 'utf8');

locs = locs.replace(
    'class="loc-action-btn cyan">🖨️ Print Sticker</button>',
    'class="loc-action-btn cyan" data-i18n-text="locations.print_sticker">🖨️ Print Sticker</button>'
);

fs.writeFileSync(locPath, locs, 'utf8');

console.log('Fixed all strings.');
