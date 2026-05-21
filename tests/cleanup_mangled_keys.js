const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const localesDir = path.join(ROOT_DIR, 'assets', 'locales');
const files = fs.readdirSync(localesDir);

for (const file of files) {
  if (file.endsWith('.json')) {
    const filePath = path.join(localesDir, file);
    let dict = JSON.parse(fs.readFileSync(filePath, 'utf8'));

    // Remove mangled PHP keys
    const keysToDelete = Object.keys(dict).filter(k => k.includes('<?='));
    for (const k of keysToDelete) {
      delete dict[k];
    }
    
    // Also remove the mangled one in add_item.php
    delete dict["<?= $edit_id ? 'add_item"];
    delete dict["<?= (int)$item['quantity'] !== 1 ? 'item_details"];

    fs.writeFileSync(filePath, JSON.stringify(dict, null, 4), 'utf8');
  }
}

console.log('Cleaned up mangled keys from locale dictionaries.');
