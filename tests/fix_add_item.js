const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');

// 2. Fix add_item.php
const filepathAdd = path.join(ROOT_DIR, 'add_item.php');
let contentAdd = fs.readFileSync(filepathAdd, 'utf8');

contentAdd = contentAdd.replace(
    '<?= $edit_id ? \'💾 Save Changes\' : \'➕ Add to Inventory\' ?>',
    '<span data-i18n-text="<?= $edit_id ? \'add_item.save_changes\' : \'add_item.add_to_inventory\' ?>"><?= $edit_id ? \'💾 Save Changes\' : \'➕ Add to Inventory\' ?></span>'
);

contentAdd = contentAdd.replace(
    /alert\('Please select or drop at least one component photo first\.'\);/,
    'alert(localizationController.t(\'add_item.please_select_photo\') || \'Please select or drop at least one component photo first.\');'
);

contentAdd = contentAdd.replace(
    /status\.textContent = '🔍 Analysing images… please wait\.';/,
    'status.textContent = \'🔍 \' + (localizationController.t(\'add_item.analysing_images\') || \'Analysing images… please wait.\');'
);

contentAdd = contentAdd.replace(
    /status\.textContent = '✅ Identification complete! Review the fields below and click Save\.';/,
    'status.textContent = \'✅ \' + (localizationController.t(\'add_item.identification_complete\') || \'Identification complete! Review the fields below and click Save.\');'
);

contentAdd = contentAdd.replace(
    /const msg = err\.message \|\| 'Identification failed\. Please try again\.';/,
    'const msg = err.message || (localizationController.t(\'add_item.identification_failed\') || \'Identification failed. Please try again.\');'
);

contentAdd = contentAdd.replace(
    /alert\('Save failed: ' \+ err\.message\);/,
    'alert((localizationController.t(\'add_item.save_failed\') || \'Save failed: \') + err.message);'
);

fs.writeFileSync(filepathAdd, contentAdd, 'utf8');

console.log('Fixed add_item.php translation strings');
