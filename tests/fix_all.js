const fs = require('fs');
const path = require('path');

const EN_JSON = path.join(__dirname, '../assets/locales/en.json');
let en = JSON.parse(fs.readFileSync(EN_JSON, 'utf8'));
function setK(n, k, v) { if(!en[n]) en[n]={}; en[n][k]=v; }

function rxp(file, pattern, replacement, ns, k, v) {
  let p = path.join(__dirname, '..', file);
  if (!fs.existsSync(p)) return;
  let c = fs.readFileSync(p, 'utf8');
  let m = c.match(pattern);
  if (m) {
    c = c.replace(pattern, replacement);
    fs.writeFileSync(p, c);
    if(ns) setK(ns, k, v);
    console.log('Fixed', file, k);
  }
}

// bulk_import_csv.php
rxp('bulk_import_csv.php', />skipped<\/span>/g, ' data-i18n-text="bulk_import_csv.skipped">skipped</span>', 'bulk_import_csv', 'skipped', 'skipped');
rxp('bulk_import_csv.php', />failed<\/span>/g, ' data-i18n-text="bulk_import_csv.failed">failed</span>', 'bulk_import_csv', 'failed', 'failed');
rxp('bulk_import_csv.php', />queued for enrichment<\/span>/g, ' data-i18n-text="bulk_import_csv.queued_enrichment">queued for enrichment</span>', 'bulk_import_csv', 'queued_enrichment', 'queued for enrichment');
rxp('bulk_import_csv.php', /<h3>Preview \(first/g, '<h3><span data-i18n-text="bulk_import_csv.preview">Preview (first</span>', 'bulk_import_csv', 'preview', 'Preview (first');
rxp('bulk_import_csv.php', /💡 <strong>Pro Tip:<\/strong>/g, '<span data-i18n-text="bulk_import_csv.pro_tip_badge">💡 <strong>Pro Tip:</strong></span>', 'bulk_import_csv', 'pro_tip_badge', '💡 Pro Tip:');
rxp('bulk_import_csv.php', /items&hellip;<\/span><\/div>/g, '<span data-i18n-text="bulk_import_csv.items_ellipsis">items&hellip;</span></div>', 'bulk_import_csv', 'items_ellipsis', 'items...');
rxp('bulk_import_csv.php', /Accepts <code>\.csv<\/code>, <code>\.tsv<\/code>, or any plain-text delimited file\. The first row must be column headers\. Commas, tabs, semicolons, and pipe <code>\|<\/code> delimiters are all auto-detected\.<\/span><\/p>/g, '<span data-i18n-text="bulk_import_csv.accepts_full">Accepts <code>.csv</code>, <code>.tsv</code>, or any plain-text delimited file. The first row must be column headers. Commas, tabs, semicolons, and pipe <code>|</code> delimiters are all auto-detected.</span></p>', 'bulk_import_csv', 'accepts_full', 'Accepts .csv, .tsv, or any plain-text delimited file. The first row must be column headers. Commas, tabs, semicolons, and pipe | delimiters are all auto-detected.');

// bulk_import_folder.php
rxp('bulk_import_folder.php', />items<\/span>/g, ' data-i18n-text="bulk_import_folder.items">items</span>', 'bulk_import_folder', 'items', 'items');
rxp('bulk_import_folder.php', /Ready &mdash;<\/span>/g, 'Ready &mdash;</span>', null, null, null); // already has it?
rxp('bulk_import_folder.php', /📁 <\?= count\(\$files\) \?> image\(s\) &middot; pending<\/span>/g, '📁 <?= count($files) ?> <span data-i18n-text="bulk_import_folder.images">image(s)</span> &middot; <span data-i18n-text="bulk_import_folder.pending">pending</span>', 'bulk_import_folder', 'images', 'image(s)');

// bulk_import_zip.php
rxp('bulk_import_zip.php', /├── <strong>resistors\/<\/strong>\n          │   ├── image_01\.jpg\n          │   └── description\.txt\n          └── <strong>capacitors\/<\/strong>\n          &nbsp;&nbsp;&nbsp;&nbsp;└── image_01\.jpg<\/span><\/div>/g, '├── <strong>resistors/</strong>\n          │   ├── image_01.jpg\n          │   └── description.txt\n          └── <strong>capacitors/</strong>\n          &nbsp;&nbsp;&nbsp;&nbsp;└── image_01.jpg</span></div>', null, null, null);

// container_manifest.php
rxp('container_manifest.php', /This QR code links to the live manifest for <\/span><strong><\?= htmlspecialchars\(\$location\) \?><\/strong>\. <span data-i18n-text="container_manifest\.qr_desc_2">Print it, laminate it, and stick it on the container\. Scanning with any phone camera shows the current contents in real-time\.<\/span><\/p>/g, '<span data-i18n-text="container_manifest.qr_links">This QR code links to the live manifest for</span> <strong><?= htmlspecialchars($location) ?></strong>. <span data-i18n-text="container_manifest.qr_desc_2">Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</span></p>', 'container_manifest', 'qr_links', 'This QR code links to the live manifest for');

// dashboard.php
rxp('dashboard.php', /<\?= \$si\('name'\) \?> Name<\/a><\/th>/g, '<?= $si(\'name\') ?> <span data-i18n-text="dashboard.table_name">Name</span></a></th>', 'dashboard', 'table_name', 'Name');
rxp('dashboard.php', /<\?= \$si\('model'\) \?> Model<\/a><\/th>/g, '<?= $si(\'model\') ?> <span data-i18n-text="dashboard.table_model">Model</span></a></th>', 'dashboard', 'table_model', 'Model');
rxp('dashboard.php', /<\?= \$si\('category'\) \?> Category<\/a><\/th>/g, '<?= $si(\'category\') ?> <span data-i18n-text="dashboard.table_category">Category</span></a></th>', 'dashboard', 'table_category', 'Category');
rxp('dashboard.php', /<\?= \$si\('quantity'\) \?> Qty<\/a><\/th>/g, '<?= $si(\'quantity\') ?> <span data-i18n-text="dashboard.table_qty">Qty</span></a></th>', 'dashboard', 'table_qty', 'Qty');
rxp('dashboard.php', /<\?= \$si\('status'\) \?> Status<\/a><\/th>/g, '<?= $si(\'status\') ?> <span data-i18n-text="dashboard.table_status">Status</span></a></th>', 'dashboard', 'table_status', 'Status');
rxp('dashboard.php', /<\?= \$si\('location'\) \?> Location<\/a><\/th>/g, '<?= $si(\'location\') ?> <span data-i18n-text="dashboard.table_location">Location</span></a></th>', 'dashboard', 'table_location', 'Location');

rxp('dashboard.php', /                       View\n                      <\/a>/g, '                       <span data-i18n-text="common.view">View</span>\n                      </a>', 'common', 'view', 'View');
rxp('dashboard.php', /                       Edit\n                      <\/a>/g, '                       <span data-i18n-text="common.edit">Edit</span>\n                      </a>', 'common', 'edit', 'Edit');
rxp('dashboard.php', /                       Delete\n                      <\/a>/g, '                       <span data-i18n-text="common.delete">Delete</span>\n                      </a>', 'common', 'delete', 'Delete');

// includes/bug_reporter.php
rxp('includes/bug_reporter.php', /<h3 class="font-semibold text-white text-base mb-4">Report a Bug<\/h3>/g, '<h3 class="font-semibold text-white text-base mb-4" data-i18n-text="common.report_bug">Report a Bug</h3>', 'common', 'report_bug', 'Report a Bug');
rxp('includes/bug_reporter.php', /<div class="text-sm font-medium">Capturing screen\.\.\.<\/div>/g, '<div class="text-sm font-medium" data-i18n-text="common.capturing_screen">Capturing screen...</div>', 'common', 'capturing_screen', 'Capturing screen...');

// index.php
rxp('index.php', /<p class="text-slate-500 text-sm mt-4">Self-hosted &middot; Powered by Gemini &amp; OpenAI<\/p>/g, '<p class="text-slate-500 text-sm mt-4" data-i18n-text="login.powered_by">Self-hosted &middot; Powered by Gemini &amp; OpenAI</p>', 'login', 'powered_by', 'Self-hosted &middot; Powered by Gemini & OpenAI');

// item_details.php
rxp('item_details.php', /<span class="text-slate-500">unit<\?= \$item\['quantity'\]!=1\?'s':'' \?><\/span>/g, '<span class="text-slate-500"><span data-i18n-text="common.unit">unit</span><?= $item[\'quantity\']!=1?\'s\':\'\' ?></span>', 'common', 'unit', 'unit');

// locations.php
rxp('locations.php', /<button onclick="openQrModal\('<\?= htmlspecialchars\(\$l\['location'\]\) \?>'\)" class="flex-1 text-center text-xs text-purple-400 border border-purple-500\/20 py-2 rounded-lg hover:bg-purple-500\/10 transition-colors">📄 Container QR<\/button>/g, '<button onclick="openQrModal(\'<?= htmlspecialchars($l[\'location\']) ?>\')" class="flex-1 text-center text-xs text-purple-400 border border-purple-500/20 py-2 rounded-lg hover:bg-purple-500/10 transition-colors" data-i18n-text="locations.container_qr">📄 Container QR</button>', 'locations', 'container_qr', '📄 Container QR');

// projects.php
rxp('projects.php', /&middot; Uses <span id="model-name">/g, '&middot; <span data-i18n-text="projects.uses">Uses</span> <span id="model-name">', 'projects', 'uses', 'Uses');

// settings.php
rxp('settings.php', /Provider:<\/span><\/p>/g, 'Provider:</span></p>', null, null, null);
rxp('settings.php', /<span class="text-xs font-medium text-emerald-400 bg-emerald-400\/10 px-2 py-0\.5 rounded">API Key Saved<\/span>/g, '<span class="text-xs font-medium text-emerald-400 bg-emerald-400/10 px-2 py-0.5 rounded" data-i18n-text="settings.api_key_saved">API Key Saved</span>', 'settings', 'api_key_saved', 'API Key Saved');
rxp('settings.php', /<span class="text-xs font-medium text-red-400 bg-red-400\/10 px-2 py-0\.5 rounded">No API Key<\/span>/g, '<span class="text-xs font-medium text-red-400 bg-red-400/10 px-2 py-0.5 rounded" data-i18n-text="settings.no_api_key">No API Key</span>', 'settings', 'no_api_key', 'No API Key');

fs.writeFileSync(EN_JSON, JSON.stringify(en, null, 4));
