const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const missingPath = path.join(ROOT_DIR, 'tests', 'missing_in_en.json');
const missing = JSON.parse(fs.readFileSync(missingPath, 'utf8'));

Object.assign(missing, {
  "projects.project_ideas": "project ideas",
  "projects.cached": "(cached)",
  "projects.generated": "generated",
  "projects.powered_by": "Powered by",
  "projects.components_from_your_lab": "Components from your lab",
  "projects.parts_to_acquire": "Parts to acquire",
  "projects.generate_full_blueprint": "Generate Full Blueprint & Code",
  "projects.regenerate_btn": "Regenerate"
});

fs.writeFileSync(missingPath, JSON.stringify(missing, null, 2), 'utf8');
console.log('Appended JS template keys');
