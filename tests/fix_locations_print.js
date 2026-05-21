const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// Fix print_labels.php
let printLabelsContent = fs.readFileSync(path.join(ROOT_DIR, 'print_labels.php'), 'utf8');

// Inject i18n
if (!printLabelsContent.includes('assets/i18n.js')) {
    printLabelsContent = printLabelsContent.replace('</head>', '  <script src="assets/i18n.js"></script>\n</head>');
    printLabelsContent = printLabelsContent.replace('</body>', '  <script>localizationController.init();</script>\n</body>');
}

// Add data-i18n-text to the size buttons
printLabelsContent = printLabelsContent.replace(
    />Small \(38×21\)</g,
    ' data-i18n-text="print_labels.small">Small (38×21)<'
);
printLabelsContent = printLabelsContent.replace(
    />Medium \(63×38\)</g,
    ' data-i18n-text="print_labels.medium">Medium (63×38)<'
);
printLabelsContent = printLabelsContent.replace(
    />Large \(99×57\)</g,
    ' data-i18n-text="print_labels.large">Large (99×57)<'
);
// Fix the dynamic count of labels
printLabelsContent = printLabelsContent.replace(
    '<span class="size-label"><?= count($items) ?> label<?= count($items)!==1?\'s\':\'\' ?></span>',
    '<span class="size-label"><?= count($items) ?> <span data-i18n-text="print_labels.labels_count_word">labels</span></span>'
);

fs.writeFileSync(path.join(ROOT_DIR, 'print_labels.php'), printLabelsContent, 'utf8');
console.log('Fixed print_labels.php');

// Fix locations.php
let locationsContent = fs.readFileSync(path.join(ROOT_DIR, 'locations.php'), 'utf8');

// Header subtitle
locationsContent = locationsContent.replace(
    '<p class="text-xs text-slate-500 mt-0.5"><?= count($locs) ?> storage location<?= count($locs)!==1?\'s\':\'\' ?> · Generate QR stickers for containers</p>',
    '<p class="text-xs text-slate-500 mt-0.5"><?= count($locs) ?> <span data-i18n-text="locations.storage_locations">storage locations</span> &middot; <span data-i18n-text="locations.generate_qr_stickers">Generate QR stickers for containers</span></p>'
);

// Search input
locationsContent = locationsContent.replace(
    'placeholder="🔍 Filter locations…"',
    'placeholder="🔍 Filter locations…" data-i18n-placeholder="locations.filter_locations"'
);

// Types count
locationsContent = locationsContent.replace(
    '<p class="text-xs text-slate-600"><?= (int)$loc[\'item_types\'] ?> types</p>',
    '<p class="text-xs text-slate-600"><?= (int)$loc[\'item_types\'] ?> <span data-i18n-text="locations.types">types</span></p>'
);

// Types and units in loc card
locationsContent = locationsContent.replace(
    '<div class="qs-sub"><?= (int)$loc[\'item_types\'] ?> types · <?= (int)$loc[\'total_qty\'] ?> units</div>',
    '<div class="qs-sub"><?= (int)$loc[\'item_types\'] ?> <span data-i18n-text="locations.types">types</span> &middot; <?= (int)$loc[\'total_qty\'] ?> <span data-i18n-text="locations.units">units</span></div>'
);

// No location set banner
locationsContent = locationsContent.replace(
    '<span>⚠️ <strong><?= $no_loc_count ?></strong> component<?= $no_loc_count!==1?\'s\':\'\' ?> have no location set.</span>',
    '<span>⚠️ <strong><?= $no_loc_count ?></strong> <span data-i18n-text="locations.no_location_set">components have no location set.</span></span>'
);

// Wait, the dashboard stat block from screenshot 4: "40 types" 
// Oh, the screenshot is actually the location card in locations.php! The emerald number 40 and "40 types" under it.
// Which is exactly this line:
// <p class="text-lg font-bold text-emerald-400"><?= (int)$loc['total_qty'] ?></p>
// <p class="text-xs text-slate-600"><?= (int)$loc['item_types'] ?> types</p>

fs.writeFileSync(path.join(ROOT_DIR, 'locations.php'), locationsContent, 'utf8');
console.log('Fixed locations.php');
