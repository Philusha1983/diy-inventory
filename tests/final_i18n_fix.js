const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 1. Add i18n.js to bulk_import files
const bulkFiles = ['bulk_import.php', 'bulk_import_csv.php', 'bulk_import_folder.php', 'bulk_import_zip.php', 'bulk_import_wizard.php'];
bulkFiles.forEach(file => {
  let content = fs.readFileSync(path.join(ROOT_DIR, file), 'utf8');
  if (!content.includes('assets/i18n.js')) {
    // Inject just before </head>
    content = content.replace('</head>', '  <script src="assets/i18n.js"></script>\n</head>');
    
    // Inject init just before </body>
    if (content.includes('</body>')) {
      content = content.replace('</body>', '  <script>localizationController.init();</script>\n</body>');
    } else {
      content += '\n<script>localizationController.init();</script>';
    }
    fs.writeFileSync(path.join(ROOT_DIR, file), content, 'utf8');
    console.log(`Injected i18n.js into ${file}`);
  }
});

// 2. Fix dashboard.php missed dynamic strings
let dashboardContent = fs.readFileSync(path.join(ROOT_DIR, 'dashboard.php'), 'utf8');
const dReplacements = [
  {
    find: '<?= $total_items ?> components &middot; <?= $total_qty ?> units',
    replace: '<?= $total_items ?> <span data-i18n-text="dashboard.components">components</span> &middot; <?= $total_qty ?> <span data-i18n-text="dashboard.units">units</span>'
  },
  {
    find: 'Showing <?= count($items) ?> of <?= $total_items ?> components',
    replace: '<span data-i18n-text="dashboard.showing">Showing </span><?= count($items) ?> <span data-i18n-text="dashboard.of">of</span> <?= $total_items ?> <span data-i18n-text="dashboard.components_word">components</span>'
  }
];

let updatedD = false;
dReplacements.forEach(r => {
  if (dashboardContent.includes(r.find)) {
    dashboardContent = dashboardContent.replace(r.find, r.replace);
    updatedD = true;
  }
});
if (updatedD) {
  fs.writeFileSync(path.join(ROOT_DIR, 'dashboard.php'), dashboardContent, 'utf8');
  console.log('Fixed dynamic strings in dashboard.php');
}
