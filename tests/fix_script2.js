const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const EN = path.join(ROOT, 'assets/locales/en.json');
let en = JSON.parse(fs.readFileSync(EN, 'utf8'));
function setK(n, k, v) { if(!en[n]) en[n]={}; en[n][k]=v; }

function rep(file, search, replace, n, k, v) {
  let p = path.join(ROOT, file);
  let c = fs.readFileSync(p, 'utf8');
  if(c.includes(search)) {
    fs.writeFileSync(p, c.split(search).join(replace));
    if(n) setK(n, k, v);
    console.log('Fixed', file, '->', k);
  }
}

rep('bulk_import_csv.php', 'Accepts <code>.csv</code>', '<span data-i18n-text="bulk_import_csv.accepts">Accepts <code>.csv</code>', 'bulk_import_csv', 'accepts', 'Accepts .csv');
rep('bulk_import_csv.php', '>skipped</span>', ' data-i18n-text="bulk_import_csv.skipped">skipped</span>', 'bulk_import_csv', 'skipped', 'skipped');
rep('bulk_import_csv.php', '>failed</span>', ' data-i18n-text="bulk_import_csv.failed">failed</span>', 'bulk_import_csv', 'failed', 'failed');
rep('bulk_import_csv.php', '>queued for enrichment</span>', ' data-i18n-text="bulk_import_csv.queued">queued for enrichment</span>', 'bulk_import_csv', 'queued', 'queued for enrichment');
rep('bulk_import_csv.php', '<h3>Preview (first', '<h3><span data-i18n-text="bulk_import_csv.preview">Preview (first</span>', 'bulk_import_csv', 'preview', 'Preview (first');
rep('bulk_import_csv.php', '💡 <strong>Pro Tip:</strong>', '<span data-i18n-text="bulk_import_csv.pro_tip">💡 <strong>Pro Tip:</strong>', 'bulk_import_csv', 'pro_tip', '💡 Pro Tip:');
rep('bulk_import_csv.php', ' items&hellip;</div>', ' <span data-i18n-text="bulk_import_csv.items">items&hellip;</span></div>', 'bulk_import_csv', 'items', 'items...');

rep('bulk_import_folder.php', '>items</span>', ' data-i18n-text="bulk_import_folder.items">items</span>', 'bulk_import_folder', 'items', 'items');
rep('bulk_import_folder.php', 'Ready &mdash;', '<span data-i18n-text="bulk_import_folder.ready">Ready &mdash;</span>', 'bulk_import_folder', 'ready', 'Ready —');
rep('bulk_import_folder.php', '📁 <?= count($files)', '<span data-i18n-text="bulk_import_folder.image_pending">📁 <?= count($files) ?> image(s) &middot; pending</span>', 'bulk_import_folder', 'image_pending', '📁 images pending');

rep('bulk_import_zip.php', 'my-parts.zip', '<span data-i18n-text="bulk_import_zip.my_parts">my-parts.zip</span>', 'bulk_import_zip', 'my_parts', 'my-parts.zip');
rep('bulk_import_zip.php', 'my-sensor.zip', '<span data-i18n-text="bulk_import_zip.my_sensor">my-sensor.zip</span>', 'bulk_import_zip', 'my_sensor', 'my-sensor.zip');

rep('container_manifest.php', '📄 Container QR Sticker', '<span data-i18n-text="container_manifest.container_qr">📄 Container QR Sticker</span>', 'container_manifest', 'container_qr', '📄 Container QR Sticker');
rep('container_manifest.php', 'This QR code links to the live manifest for', '<span data-i18n-text="container_manifest.qr_links">This QR code links to the live manifest for</span>', 'container_manifest', 'qr_links', 'This QR code links to the live manifest for');
rep('container_manifest.php', 'View &rarr;</a>', '<span data-i18n-text="common.view_arrow">View &rarr;</span></a>', 'common', 'view_arrow', 'View →');
rep('container_manifest.php', 'categories</div>', '<span data-i18n-text="container_manifest.categories">categories</span></div>', 'container_manifest', 'categories', 'categories');
rep('container_manifest.php', 'types &middot;', '<span data-i18n-text="container_manifest.types">types</span> &middot;', 'container_manifest', 'types', 'types');

rep('dashboard.php', '>Name\n', ' data-i18n-text="dashboard.name">Name\n', 'dashboard', 'name', 'Name');
rep('dashboard.php', '>Model\n', ' data-i18n-text="dashboard.model">Model\n', 'dashboard', 'model', 'Model');
rep('dashboard.php', '>Category\n', ' data-i18n-text="dashboard.category">Category\n', 'dashboard', 'category', 'Category');
rep('dashboard.php', '>Qty\n', ' data-i18n-text="dashboard.qty">Qty\n', 'dashboard', 'qty', 'Qty');
rep('dashboard.php', '>Status\n', ' data-i18n-text="dashboard.status">Status\n', 'dashboard', 'status', 'Status');
rep('dashboard.php', '>Location\n', ' data-i18n-text="dashboard.location">Location\n', 'dashboard', 'location', 'Location');
rep('dashboard.php', '>View</a>', ' data-i18n-text="common.view">View</a>', 'common', 'view', 'View');
rep('dashboard.php', '>Edit</a>', ' data-i18n-text="common.edit">Edit</a>', 'common', 'edit', 'Edit');
rep('dashboard.php', '>Delete</a>', ' data-i18n-text="common.delete">Delete</a>', 'common', 'delete', 'Delete');

rep('includes/bug_reporter.php', '>Report a Bug</h3>', ' data-i18n-text="common.report_bug">Report a Bug</h3>', 'common', 'report_bug', 'Report a Bug');
rep('includes/bug_reporter.php', '>Capturing screen...</div>', ' data-i18n-text="common.capturing_screen">Capturing screen...</div>', 'common', 'capturing_screen', 'Capturing screen...');

rep('index.php', 'Self-hosted &middot; Powered by Gemini &amp; OpenAI', '<span data-i18n-text="login.powered_by">Self-hosted &middot; Powered by Gemini &amp; OpenAI</span>', 'login', 'powered_by', 'Self-hosted &middot; Powered by Gemini & OpenAI');

rep('item_details.php', '>unit<?= $item', ' data-i18n-text="common.unit">unit<?= $item', 'common', 'unit', 'unit');

rep('locations.php', '>📄 Container QR</button>', ' data-i18n-text="locations.container_qr">📄 Container QR</button>', 'locations', 'container_qr', '📄 Container QR');

rep('projects.php', 'Takes 15–40 seconds', '<span data-i18n-text="projects.takes_seconds">Takes 15–40 seconds</span>', 'projects', 'takes_seconds', 'Takes 15–40 seconds');

rep('settings.php', 'Provider:</p>', '<span data-i18n-text="settings.provider">Provider:</span></p>', 'settings', 'provider', 'Provider:');
rep('settings.php', '>API Key Saved</span>', ' data-i18n-text="settings.api_key_saved">API Key Saved</span>', 'settings', 'api_key_saved', 'API Key Saved');
rep('settings.php', '>No API Key</span>', ' data-i18n-text="settings.no_api_key">No API Key</span>', 'settings', 'no_api_key', 'No API Key');

fs.writeFileSync(EN, JSON.stringify(en, null, 4));
