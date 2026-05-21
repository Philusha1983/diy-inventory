const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const missingPath = path.join(ROOT_DIR, 'tests', 'missing_in_en.json');
const missing = JSON.parse(fs.readFileSync(missingPath, 'utf8'));

missing['print_labels.small'] = 'Small (38×21)';
missing['print_labels.medium'] = 'Medium (63×38)';
missing['print_labels.large'] = 'Large (99×57)';

fs.writeFileSync(missingPath, JSON.stringify(missing, null, 2), 'utf8');
console.log('Appended button keys');
