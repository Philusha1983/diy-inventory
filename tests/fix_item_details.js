const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 1. Fix item_details.php
const filepath = path.join(ROOT_DIR, 'item_details.php');
let content = fs.readFileSync(filepath, 'utf8');

content = content.replace(
    'unit<?= (int)$item[\'quantity\'] !== 1 ? \'s\' : \'\' ?>',
    '<span data-i18n-text="<?= (int)$item[\'quantity\'] !== 1 ? \'item_details.units\' : \'item_details.unit\' ?>">unit<?= (int)$item[\'quantity\'] !== 1 ? \'s\' : \'\' ?></span>'
);

content = content.replace(
    '<span>🤖</span> AI Enrichment',
    '<span>🤖</span> <span data-i18n-text="item_details.ai_enrichment">AI Enrichment</span>'
);

content = content.replace(
    '✓ Enriched <?= date(\'d M Y\', strtotime($item[\'enriched_at\'])) ?>',
    '✓ <span data-i18n-text="item_details.enriched">Enriched</span> <?= date(\'d M Y\', strtotime($item[\'enriched_at\'])) ?>'
);

content = content.replace(
    `<?= $item['enriched_data'] ? 'Re-fetch &amp; Refresh' : 'Enrich from Web' ?>`,
    `<?= $item['enriched_data'] ? '<span data-i18n-text="item_details.refetch_refresh">Re-fetch &amp; Refresh</span>' : '<span data-i18n-text="item_details.enrich_from_web">Enrich from Web</span>' ?>`
);

content = content.replace(
    'Find projects using this component',
    '<span data-i18n-text="item_details.find_projects">Find projects using this component</span>'
);

content = content.replace(
    'Ask AI about this component',
    '<span data-i18n-text="item_details.ask_ai">Ask AI about this component</span>'
);

content = content.replace(
    'Add photos →',
    '<span data-i18n-text="item_details.add_photos">Add photos →</span>'
);

content = content.replace(
    'status.textContent = \'Connecting to URLs…\';',
    'status.textContent = localizationController.t(\'item_details.connecting_to_urls\') || \'Connecting to URLs…\';'
);

content = content.replace(
    'btn.innerHTML = \'<span class="spinner"></span> Fetching…\';',
    'btn.innerHTML = \'<span class="spinner"></span> \' + (localizationController.t(\'item_details.fetching\') || \'Fetching…\');'
);

content = content.replace(
    'btn.innerHTML    = \'✓ Enriched — Reload to see\';',
    'btn.innerHTML    = \'✓ \' + (localizationController.t(\'item_details.enriched_reload\') || \'Enriched — Reload to see\');'
);

content = content.replace(
    /btn\.innerHTML = '🔄 Retry';/g,
    'btn.innerHTML = \'🔄 \' + (localizationController.t(\'item_details.retry\') || \'Retry\');'
);

fs.writeFileSync(filepath, content, 'utf8');

console.log('Fixed item_details.php translation strings');
