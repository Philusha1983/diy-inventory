const fs = require('fs');
const path = require('path');

const ROOT_DIR = path.resolve(__dirname, '..');
const missingPath = path.join(ROOT_DIR, 'tests', 'missing_in_en.json');
let missing = {};
if (fs.existsSync(missingPath)) {
  missing = JSON.parse(fs.readFileSync(missingPath, 'utf8'));
}

Object.assign(missing, {
  "projects.hours": "hours",
  "projects.hour": "hour",
  "projects.days": "days",
  "projects.day": "day",
  "projects.minutes": "minutes",
  "projects.minute": "minute",
  "projects.weeks": "weeks",
  "projects.week": "week",
  "projects.complexity_beginner": "Beginner",
  "projects.complexity_intermediate": "Intermediate",
  "projects.complexity_expert": "Expert"
});

fs.writeFileSync(missingPath, JSON.stringify(missing, null, 2), 'utf8');
console.log('Appended dynamic project JS keys');
