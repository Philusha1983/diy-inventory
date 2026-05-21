const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const LOCALES_DIR = path.join(ROOT_DIR, 'assets', 'locales');
const newKeys = require('./dynamic_keys.json');

const locales = ['en', 'es', 'he', 'uk'];

locales.forEach(lang => {
  const filePath = path.join(LOCALES_DIR, `${lang}.json`);
  const dict = JSON.parse(fs.readFileSync(filePath, 'utf8'));

  Object.entries(newKeys).forEach(([keyPath, enText]) => {
    const parts = keyPath.split('.');
    const group = parts[0];
    const key = parts.slice(1).join('.');

    if (!dict[group]) {
      dict[group] = {};
    }

    // Don't overwrite existing
    if (dict[group][key] === undefined) {
      // If it's english, just put the text. If not, prefix it or just put English for now.
      dict[group][key] = enText; 
    }
  });

  fs.writeFileSync(filePath, JSON.stringify(dict, null, 2));
  console.log(`Merged new keys into ${lang}.json`);
});
