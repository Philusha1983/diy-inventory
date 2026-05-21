const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const EN_JSON = path.join(ROOT_DIR, 'assets', 'locales', 'en.json');
let enLocale = JSON.parse(fs.readFileSync(EN_JSON, 'utf8'));

function addKey(namespace, key, value) {
    if (!enLocale[namespace]) enLocale[namespace] = {};
    enLocale[namespace][key] = value;
}

const replacements = [
    // dashboard.php
    { file: 'dashboard.php', search: '>View</a>', replace: ' data-i18n-text="common.view">View</a>', add: [] },
];

replacements.forEach(rep => {
    let filePath = path.join(ROOT_DIR, rep.file);
    if (fs.existsSync(filePath)) {
        let content = fs.readFileSync(filePath, 'utf8');
        if (content.includes(rep.search)) {
            content = content.split(rep.search).join(rep.replace);
            fs.writeFileSync(filePath, content);
            if (rep.add) {
                rep.add.forEach(item => {
                    addKey(item.n, item.k, item.v);
                });
            }
        }
    }
});

// Write en.json
fs.writeFileSync(EN_JSON, JSON.stringify(enLocale, null, 4));
console.log('Fixed missing add property and re-ran');
