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

// bulk_import.php
rep('bulk_import.php', '<?php if ($zip_skipped): ?> Skipped:', '<?php if ($zip_skipped): ?> <span data-i18n-text="bulk_import.skipped">Skipped:</span>', 'bulk_import', 'skipped', 'Skipped:');

// bulk_import_csv.php
rep('bulk_import_csv.php', 'Accepts <code>.csv</code>', '<span data-i18n-text="bulk_import_csv.accepts_csv">Accepts <code>.csv</code>', 'bulk_import_csv', 'accepts_csv', 'Accepts .csv');
rep('bulk_import_csv.php', 'delimiters are all auto-detected.</p>', 'delimiters are all auto-detected.</span></p>', null, null, null);

rep('bulk_import_csv.php', 'Auto-mapped columns are pre-selected', '<span data-i18n-text="bulk_import_csv.auto_mapped">Auto-mapped columns are pre-selected', 'bulk_import_csv', 'auto_mapped', 'Auto-mapped columns are pre-selected');
rep('bulk_import_csv.php', '<code>---</code> will be ignored.</p>', '<code>---</code> will be ignored.</span></p>', null, null, null);

rep('bulk_import_csv.php', '>skipped<', ' data-i18n-text="bulk_import_csv.skipped">skipped<', 'bulk_import_csv', 'skipped', 'skipped');
rep('bulk_import_csv.php', '>failed<', ' data-i18n-text="bulk_import_csv.failed">failed<', 'bulk_import_csv', 'failed', 'failed');
rep('bulk_import_csv.php', '>queued for enrichment<', ' data-i18n-text="bulk_import_csv.queued_enrichment">queued for enrichment<', 'bulk_import_csv', 'queued_enrichment', 'queued for enrichment');
rep('bulk_import_csv.php', '<h3>Preview (first ', '<h3><span data-i18n-text="bulk_import_csv.preview">Preview (first</span> ', 'bulk_import_csv', 'preview', 'Preview (first');
rep('bulk_import_csv.php', ' rows of data)</h3>', ' <span data-i18n-text="bulk_import_csv.rows_of_data">rows of data)</span></h3>', 'bulk_import_csv', 'rows_of_data', 'rows of data)');
rep('bulk_import_csv.php', '💡 <strong>Pro Tip:</strong> Export from Google Sheets', '<span data-i18n-text="bulk_import_csv.pro_tip">💡 <strong>Pro Tip:</strong> Export from Google Sheets', 'bulk_import_csv', 'pro_tip', '💡 Pro Tip: Export from Google Sheets');
rep('bulk_import_csv.php', 'Save As &rarr; CSV UTF-8.</div>', 'Save As &rarr; CSV UTF-8.</span></div>', null, null, null);
rep('bulk_import_csv.php', '🔗 Auto-enrich via Product URL after import', '<span data-i18n-text="bulk_import_csv.auto_enrich">🔗 Auto-enrich via Product URL after import</span>', 'bulk_import_csv', 'auto_enrich', '🔗 Auto-enrich via Product URL after import');
rep('bulk_import_csv.php', '🔗 Running enrichment for ', '<span data-i18n-text="bulk_import_csv.running_enrichment">🔗 Running enrichment for </span>', 'bulk_import_csv', 'running_enrichment', '🔗 Running enrichment for ');
rep('bulk_import_csv.php', ' items&hellip;</div>', ' <span data-i18n-text="bulk_import_csv.items">items&hellip;</span></div>', 'bulk_import_csv', 'items', 'items…');

// bulk_import_folder.php
rep('bulk_import_folder.php', '>items<', ' data-i18n-text="bulk_import_folder.items">items<', 'bulk_import_folder', 'items', 'items');
rep('bulk_import_folder.php', 'Ready &mdash; ', '<span data-i18n-text="bulk_import_folder.ready">Ready &mdash; </span>', 'bulk_import_folder', 'ready', 'Ready — ');
rep('bulk_import_folder.php', '</span> items queued</div>', '</span> <span data-i18n-text="bulk_import_folder.items_queued">items queued</span></div>', 'bulk_import_folder', 'items_queued', 'items queued');
rep('bulk_import_folder.php', '📁 <?= count($files) ?> image<?= count($files)!==1?\'s\':\'\' ?> &middot; pending', '📁 <?= count($files) ?> <span data-i18n-text="bulk_import_folder.images">image(s)</span> &middot; <span data-i18n-text="bulk_import_folder.pending">pending</span>', 'bulk_import_folder', 'images', 'image(s)');
setK('bulk_import_folder', 'pending', 'pending');

// bulk_import_zip.php
rep('bulk_import_zip.php', 'my-parts.zip\n          ├── <strong>resistors/</strong>', '<span data-i18n-text="bulk_import_zip.example_1">my-parts.zip\n          ├── <strong>resistors/</strong>', 'bulk_import_zip', 'example_1', 'my-parts.zip example');
rep('bulk_import_zip.php', '&nbsp;&nbsp;&nbsp;&nbsp;└── image_01.jpg</div>', '&nbsp;&nbsp;&nbsp;&nbsp;└── image_01.jpg</span></div>', null, null, null);
rep('bulk_import_zip.php', 'my-sensor.zip\n          ├── photo1.jpg', '<span data-i18n-text="bulk_import_zip.example_2">my-sensor.zip\n          ├── photo1.jpg', 'bulk_import_zip', 'example_2', 'my-sensor.zip example');
rep('bulk_import_zip.php', '└── description.txt</div>', '└── description.txt</span></div>', null, null, null);
rep('bulk_import_zip.php', '💡 <strong>Flat ZIP</strong>', '<span data-i18n-text="bulk_import_zip.pro_tip">💡 <strong>Flat ZIP</strong>', 'bulk_import_zip', 'pro_tip', 'Flat ZIP tip');
rep('bulk_import_zip.php', '<code>category: sensor</code></div>', '<code>category: sensor</code></span></div>', null, null, null);

// container_manifest.php
rep('container_manifest.php', '📄 Container QR Sticker &mdash;', '📄 <span data-i18n-text="container_manifest.container_qr">Container QR Sticker &mdash;</span>', 'container_manifest', 'container_qr', 'Container QR Sticker —');
rep('container_manifest.php', 'This QR code links to the live manifest for <strong>', '<span data-i18n-text="container_manifest.qr_desc">This QR code links to the live manifest for </span><strong>', 'container_manifest', 'qr_desc', 'This QR code links to the live manifest for');
rep('container_manifest.php', '</strong>. Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</p>', '</strong>. <span data-i18n-text="container_manifest.qr_desc_2">Print it, laminate it, and stick it on the container. Scanning with any phone camera shows the current contents in real-time.</span></p>', 'container_manifest', 'qr_desc_2', 'Print it, laminate it, and stick it on the container.');
rep('container_manifest.php', 'Items with location "', '<span data-i18n-text="container_manifest.items_with_loc">Items with location "</span>', 'container_manifest', 'items_with_loc', 'Items with location');
rep('container_manifest.php', '" will appear here.</p>', '" <span data-i18n-text="container_manifest.will_appear">will appear here.</span></p>', 'container_manifest', 'will_appear', 'will appear here.');
rep('container_manifest.php', '🏷️ Print Item Labels Instead', '<span data-i18n-text="container_manifest.print_labels_instead">🏷️ Print Item Labels Instead</span>', 'container_manifest', 'print_labels_instead', '🏷️ Print Item Labels Instead');
rep('container_manifest.php', 'View &rarr;', '<span data-i18n-text="common.view_arrow">View &rarr;</span>', 'common', 'view_arrow', 'View →');
rep('container_manifest.php', ' types &middot; ', ' <span data-i18n-text="container_manifest.types">types</span> &middot; ', 'container_manifest', 'types', 'types');
rep('container_manifest.php', ' units</div>', ' <span data-i18n-text="container_manifest.units">units</span></div>', 'container_manifest', 'units', 'units');
rep('container_manifest.php', ' categories</div>', ' <span data-i18n-text="container_manifest.categories">categories</span></div>', 'container_manifest', 'categories', 'categories');
rep('container_manifest.php', 'Printed: ', '<span data-i18n-text="container_manifest.printed">Printed: </span>', 'container_manifest', 'printed', 'Printed:');

// dashboard.php
rep('dashboard.php', '>Edit</a>', ' data-i18n-text="common.edit">Edit</a>', 'common', 'edit', 'Edit');
rep('dashboard.php', '>Delete</a>', ' data-i18n-text="common.delete">Delete</a>', 'common', 'delete', 'Delete');
rep('dashboard.php', 'Name\n', '<span data-i18n-text="dashboard.table_name">Name</span>\n', 'dashboard', 'table_name', 'Name');
rep('dashboard.php', 'Model\n', '<span data-i18n-text="dashboard.table_model">Model</span>\n', 'dashboard', 'table_model', 'Model');
rep('dashboard.php', 'Category\n', '<span data-i18n-text="dashboard.table_category">Category</span>\n', 'dashboard', 'table_category', 'Category');
rep('dashboard.php', 'Qty\n', '<span data-i18n-text="dashboard.table_qty">Qty</span>\n', 'dashboard', 'table_qty', 'Qty');
rep('dashboard.php', 'Status\n', '<span data-i18n-text="dashboard.table_status">Status</span>\n', 'dashboard', 'table_status', 'Status');
rep('dashboard.php', 'Location\n', '<span data-i18n-text="dashboard.table_location">Location</span>\n', 'dashboard', 'table_location', 'Location');

// includes/bug_reporter.php
rep('includes/bug_reporter.php', '>Report a Bug</h3>', ' data-i18n-text="common.report_bug">Report a Bug</h3>', 'common', 'report_bug', 'Report a Bug');
rep('includes/bug_reporter.php', '>Capturing screen...</div>', ' data-i18n-text="common.capturing_screen">Capturing screen...</div>', 'common', 'capturing_screen', 'Capturing screen...');

// index.php
rep('index.php', 'Self-hosted &middot; Powered by Gemini &amp; OpenAI', '<span data-i18n-text="login.powered_by">Self-hosted &middot; Powered by Gemini &amp; OpenAI</span>', 'login', 'powered_by', 'Self-hosted &middot; Powered by Gemini & OpenAI');

// item_details.php
rep('item_details.php', 'unit<?= $item[\'quantity\']!=1?\'s\':\'\' ?>', '<span data-i18n-text="common.units">units</span>', 'common', 'units', 'units');

// locations.php
rep('locations.php', '>📄 Container QR</button>', ' data-i18n-text="locations.container_qr">📄 Container QR</button>', 'locations', 'container_qr', '📄 Container QR');
rep('locations.php', '>📋 Manifest</a>', ' data-i18n-text="locations.manifest">📋 Manifest</a>', 'locations', 'manifest', '📋 Manifest');
rep('locations.php', '>🏷️ Item Labels</a>', ' data-i18n-text="locations.item_labels">🏷️ Item Labels</a>', 'locations', 'item_labels', '🏷️ Item Labels');

// print_labels.php
rep('print_labels.php', 'Qty: ', '<span data-i18n-text="common.qty">Qty: </span>', 'common', 'qty', 'Qty:');

// projects.php
rep('projects.php', 'Takes 15–40 seconds &middot; Uses ', '<span data-i18n-text="projects.takes_seconds">Takes 15–40 seconds &middot; Uses </span>', 'projects', 'takes_seconds', 'Takes 15–40 seconds · Uses');
rep('projects.php', 'Generated ', '<span data-i18n-text="projects.generated">Generated </span>', 'projects', 'generated', 'Generated');

// settings.php
rep('settings.php', 'Provider:</p>', '<span data-i18n-text="settings.provider">Provider:</span></p>', 'settings', 'provider', 'Provider:');
rep('settings.php', '>API Key Saved</span>', ' data-i18n-text="settings.api_key_saved">API Key Saved</span>', 'settings', 'api_key_saved', 'API Key Saved');
rep('settings.php', '>No API Key</span>', ' data-i18n-text="settings.no_api_key">No API Key</span>', 'settings', 'no_api_key', 'No API Key');
rep('settings.php', 'aistudio.google.com/app/apikey &rarr;', '<span data-i18n-text="settings.google_link">aistudio.google.com/app/apikey &rarr;</span>', 'settings', 'google_link', 'aistudio.google.com/app/apikey →');
rep('settings.php', 'platform.openai.com/api-keys &rarr;', '<span data-i18n-text="settings.openai_link">platform.openai.com/api-keys &rarr;</span>', 'settings', 'openai_link', 'platform.openai.com/api-keys →');

fs.writeFileSync(EN, JSON.stringify(en, null, 4));
