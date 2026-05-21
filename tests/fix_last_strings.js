const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const EN_JSON = path.join(ROOT_DIR, 'assets', 'locales', 'en.json');
let enLocale = JSON.parse(fs.readFileSync(EN_JSON, 'utf8'));

// Helper to add to en.json
function addKey(namespace, key, value) {
    if (!enLocale[namespace]) enLocale[namespace] = {};
    if (!enLocale[namespace][key]) {
        enLocale[namespace][key] = value;
    }
}

function processFile(file, replacements) {
    const filePath = path.join(ROOT_DIR, file);
    let content = fs.readFileSync(filePath, 'utf8');
    
    replacements.forEach(rep => {
        const { search, replace, namespace, key, enValue } = rep;
        if (content.includes(search)) {
            content = content.replace(search, replace);
            if (namespace && key && enValue) {
                addKey(namespace, key, enValue);
            }
        }
    });
    
    fs.writeFileSync(filePath, content);
}

// dashboard.php
processFile('dashboard.php', [
    { search: '<a href="item_details.php?id=<?= $item[\'id\'] ?>" class="text-slate-400 hover:text-cyan-300 transition-colors p-1" title="View">', replace: '<a href="item_details.php?id=<?= $item[\'id\'] ?>" class="text-slate-400 hover:text-cyan-300 transition-colors p-1" title="View" data-i18n-title="common.view">' },
    { search: '<a href="item_details.php?id=<?= $item[\'id\'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 hover:text-cyan-300 hover:bg-cyan-500/10 transition-colors">', replace: '<a href="item_details.php?id=<?= $item[\'id\'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 hover:text-cyan-300 hover:bg-cyan-500/10 transition-colors" data-i18n-text="common.view">' },
    { search: '<a href="add_item.php?edit=<?= $item[\'id\'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 hover:text-cyan-300 hover:bg-cyan-500/10 transition-colors">', replace: '<a href="add_item.php?edit=<?= $item[\'id\'] ?>" class="flex items-center gap-2 px-3 py-2 text-sm text-slate-300 hover:text-cyan-300 hover:bg-cyan-500/10 transition-colors" data-i18n-text="common.edit">' },
    { search: '<a href="#" onclick="deleteItem(<?= $item[\'id\'] ?>); return false;" class="flex items-center gap-2 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors">', replace: '<a href="#" onclick="deleteItem(<?= $item[\'id\'] ?>); return false;" class="flex items-center gap-2 px-3 py-2 text-sm text-red-400 hover:text-red-300 hover:bg-red-500/10 transition-colors" data-i18n-text="common.delete">' },
    // Table headers in dashboard.php (they have sorting arrows so we need to target the span inside them or add data-i18n to the a tag and wrap the text)
    // Actually the <a> tags have text directly mixed with SVG.
]);

// Let's just fix everything by doing a regex pass for specific patterns.
fs.writeFileSync(EN_JSON, JSON.stringify(enLocale, null, 4));
console.log('Fixed dashboard links');
