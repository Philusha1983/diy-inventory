const fs = require('fs');
const path = require('path');
const cheerio = require('cheerio');

const ROOT_DIR = path.resolve(__dirname, '..');

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
const extracted = {};

function toSnakeCase(str) {
  return str.replace(/[^a-zA-Z0-9]/g, ' ')
    .trim()
    .replace(/\s+/g, '_')
    .toLowerCase()
    .substring(0, 30);
}

sourceFiles.forEach(file => {
  let originalContent = fs.readFileSync(file, 'utf8');
  let content = originalContent.replace(/<\?php[\s\S]*?\?>/g, '');
  content = content.replace(/<\?=[\s\S]*?\?>/g, '');

  const $ = cheerio.load(content, { xmlMode: false });
  const tagsToCheck = ['p', 'span', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'a', 'label', 'th', 'td', 'div', 'li'];

  tagsToCheck.forEach(tag => {
    $(tag).each((i, el) => {
      const $el = $(el);
      
      if ($el.attr('data-i18n') || $el.attr('data-i18n-text') || $el.attr('data-i18n-placeholder')) return;
      
      const text = $el.contents().filter(function() {
        return this.type === 'text';
      }).text().trim();
      
      // Basic text validation
      if (text.length > 2 && /[A-Za-z]/.test(text) && !text.includes('<?')) {
        const basename = path.basename(file, '.php');
        const key = `${basename}.${toSnakeCase(text)}`;
        
        // Ensure uniqueness
        let finalKey = key;
        let count = 1;
        while (extracted[finalKey] && extracted[finalKey].original !== text) {
           finalKey = `${key}_${count}`;
           count++;
        }
        
        extracted[finalKey] = {
           file: file,
           tag: tag,
           original: text,
           originalHtml: $.html(el)
        };
      }
    });
  });
});

fs.writeFileSync(path.join(ROOT_DIR, 'tests', 'extracted_strings.json'), JSON.stringify(extracted, null, 2));
console.log('Extracted to tests/extracted_strings.json');
