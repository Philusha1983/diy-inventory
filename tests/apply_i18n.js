const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const strings = require('./extracted_strings.json');

function escapeRegExp(string) {
  return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'); // $& means the whole matched string
}

const fileUpdates = {};
const newDict = {};

// Group by file
Object.entries(strings).forEach(([key, data]) => {
  if (!fileUpdates[data.file]) {
    fileUpdates[data.file] = [];
  }
  fileUpdates[data.file].push({ key, ...data });
});

let totalReplaced = 0;

Object.entries(fileUpdates).forEach(([file, items]) => {
  let content = fs.readFileSync(file, 'utf8');
  let replacedCount = 0;

  // Sort items by length descending so we replace longer strings first
  items.sort((a, b) => b.original.length - a.original.length);

  items.forEach(item => {
    const text = item.original;
    // We want to find the tag that contains this text.
    // e.g. <p>Some text</p> -> <p data-i18n-text="key">Some text</p>
    // A regex that matches the opening tag, the text, and the closing tag or just the text
    
    // Attempt 1: match "> [text] <" and add attribute to the tag before it.
    // This is hard with regex. 
    // Easier: just replace the exact line if we can.
    
    // Instead of parsing HTML, let's just find the text and replace it with:
    // <span data-i18n-text="key">text</span> ? No, that adds extra spans.
    // Let's do a simple replace: 
    // find: `>${text}<`
    // replace: ` data-i18n-text="${item.key}">${text}<` -> wait, this would make `<p data-i18n-text="...">...<`
    // Let's match the tag: `(<[a-zA-Z0-9]+[^>]*?>)\s*${escapeRegExp(text)}\s*(<\/[a-zA-Z0-9]+>)`
    // This is risky but often works.

    const regex = new RegExp(`(<[a-zA-Z1-6]+\\b[^>]*?)(>\\s*${escapeRegExp(text)}\\s*</[a-zA-Z1-6]+>)`, 'g');
    
    let matched = false;
    content = content.replace(regex, (match, p1, p2) => {
      // If it already has data-i18n, skip
      if (p1.includes('data-i18n')) return match;
      matched = true;
      return `${p1} data-i18n-text="${item.key}"${p2}`;
    });

    if (matched) {
      newDict[item.key] = text;
      replacedCount++;
      totalReplaced++;
    }
  });

  if (replacedCount > 0) {
    fs.writeFileSync(file, content, 'utf8');
    console.log(`Updated ${file.replace(ROOT_DIR, '')} (${replacedCount} replacements)`);
  }
});

fs.writeFileSync(path.join(ROOT_DIR, 'tests', 'new_keys.json'), JSON.stringify(newDict, null, 2));
console.log(`\nTotal replacements: ${totalReplaced}`);
console.log('New keys exported to tests/new_keys.json');
