const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const ROOT_DIR = path.resolve(__dirname, '..');
const LOCALES_DIR = path.join(ROOT_DIR, 'assets', 'locales');
const enDict = JSON.parse(fs.readFileSync(path.join(LOCALES_DIR, 'en.json'), 'utf8'));

// Helper to check if a key path exists in dictionary
function keyExists(dict, keyPath) {
  const parts = keyPath.split('.');
  if (parts.length !== 2) return false;
  return dict[parts[0]] && dict[parts[0]][parts[1]] !== undefined;
}

const phpFiles = fs.readdirSync(ROOT_DIR).filter(f => f.endsWith('.php'));
let missingKeys = {};

phpFiles.forEach(file => {
  const content = fs.readFileSync(path.join(ROOT_DIR, file), 'utf8');
  
  // Use Cheerio to parse the HTML safely
  const $ = cheerio.load(content, { decodeEntities: false });

  // 1. data-i18n-text
  $('[data-i18n-text]').each((i, el) => {
    const key = $(el).attr('data-i18n-text');
    if (!keyExists(enDict, key)) {
      // Find the text content, strip out PHP tags or inner HTML as best as possible
      // Actually, if it contains PHP, just extract the static text around it, or use the raw text.
      let text = $(el).text().trim() || $(el).html().trim();
      text = text.replace(/<\?=.*?\?>/g, '').replace(/\s+/g, ' ').trim();
      if (!text) text = "[Dynamic Content]";
      missingKeys[key] = text;
    }
  });

  // 2. data-i18n (direct)
  $('[data-i18n]').each((i, el) => {
    const key = $(el).attr('data-i18n');
    if (!keyExists(enDict, key)) {
      let text = $(el).text().trim();
      text = text.replace(/<\?=.*?\?>/g, '').replace(/\s+/g, ' ').trim();
      if (!text) text = "[Dynamic Content]";
      missingKeys[key] = text;
    }
  });

  // 3. data-i18n-placeholder
  $('[data-i18n-placeholder]').each((i, el) => {
    const key = $(el).attr('data-i18n-placeholder');
    if (!keyExists(enDict, key)) {
      missingKeys[key] = $(el).attr('placeholder') || "[Placeholder]";
    }
  });
});

fs.writeFileSync(path.join(ROOT_DIR, 'tests', 'missing_in_en.json'), JSON.stringify(missingKeys, null, 2));
console.log('Found missing keys:', Object.keys(missingKeys).length);
