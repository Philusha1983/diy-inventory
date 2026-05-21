const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const ROOT_DIR = path.resolve(__dirname, '..');
const LOCALES_DIR = path.join(ROOT_DIR, 'assets', 'locales');

function flattenObject(obj, prefix = '') {
  return Object.keys(obj).reduce((acc, k) => {
    const pre = prefix.length ? prefix + '.' : '';
    if (typeof obj[k] === 'object' && obj[k] !== null) {
      Object.assign(acc, flattenObject(obj[k], pre + k));
    } else {
      acc[pre + k] = obj[k];
    }
    return acc;
  }, {});
}

const locales = fs.readdirSync(LOCALES_DIR).filter(f => f.endsWith('.json'));
const dictionaries = {};

locales.forEach(file => {
  const lang = file.replace('.json', '');
  const content = fs.readFileSync(path.join(LOCALES_DIR, file), 'utf8');
  dictionaries[lang] = flattenObject(JSON.parse(content));
});

function getFiles(dir, files = []) {
  if (dir.includes('node_modules') || dir.includes('.git') || dir.includes('assets/locales')) {
    return files;
  }
  const items = fs.readdirSync(dir);
  for (const item of items) {
    const fullPath = path.join(dir, item);
    if (fs.statSync(fullPath).isDirectory()) {
      getFiles(fullPath, files);
    } else {
      if (fullPath.endsWith('.php')) {
        files.push(fullPath);
      }
    }
  }
  return files;
}

const sourceFiles = getFiles(ROOT_DIR);
let hardcodedCount = 0;
const errors = [];

sourceFiles.forEach(file => {
  let content = fs.readFileSync(file, 'utf8');
  
  // Strip PHP tags so cheerio doesn't choke
  content = content.replace(/<\?php[\s\S]*?\?>/g, '');
  // Strip <?= ... ?>
  content = content.replace(/<\?=[\s\S]*?\?>/g, '');

  const $ = cheerio.load(content, { xmlMode: false });
  
  // We check elements that typically hold text
  const tagsToCheck = ['p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'a', 'label', 'th', 'td', 'div'];

  tagsToCheck.forEach(tag => {
    $(tag).each((i, el) => {
      const $el = $(el);
      
      // If it has data-i18n, data-i18n-text, or data-i18n-placeholder it's localized
      if ($el.attr('data-i18n') || $el.attr('data-i18n-text') || $el.attr('data-i18n-placeholder')) {
        return;
      }
      
      // We only care about direct text nodes
      const text = $el.contents().filter(function() {
        return this.type === 'text';
      }).text().trim();
      
      // Ignore short text, numbers, symbols, SVG paths
      if (text.length > 2 && /[A-Za-z]/.test(text)) {
        // Also ignore if it is purely inside a script or style or something (cheerio handles some of this)
        errors.push(`Hardcoded text in ${file.replace(ROOT_DIR, '')}: <${tag}> "${text}"`);
        hardcodedCount++;
      }
    });
  });
});

console.log('--- i18n Audit Report ---\n');

if (hardcodedCount > 0) {
  console.log(`Found ${hardcodedCount} instances of hardcoded English text:`);
  errors.forEach(err => console.log('  ' + err));
  console.log(`\n⚠️  Audit finished with warnings, but allowing commit to proceed.`);
  process.exit(0);
} else {
  console.log('✅ All UI elements properly use data-i18n attributes!');
  process.exit(0);
}
