/**
 * pre_merge_check.js
 * Pre-merge test suite for multilanguage_support → main
 * Run: node tests/pre_merge_check.js
 *
 * Tests:
 *  1.  Locale files exist and are valid JSON
 *  2.  Key parity — he.json and es.json 100% match en.json
 *  3.  No null/empty values in any locale
 *  4.  Locale schema — required top-level namespaces present
 *  5.  i18n.js syntax (via require)
 *  6.  i18n.js exports correct public API
 *  7.  data-i18n attributes wired in all 7 primary PHP pages
 *  8.  localizationController.init() called in all 7 primary PHP pages
 *  9.  i18n.js loaded (<script src="assets/i18n.js">) in all 7 pages
 * 10.  RTL CSS rules present in app.css
 * 11.  No bare "Logout" text nodes remaining in PHP files
 * 12.  package.json has validate:i18n script
 * 13.  Sidebar RTL patch present in i18n.js
 * 14.  _isolateLTRContent present in i18n.js
 * 15.  CHANGELOG updated with i18n entry
 */

const fs   = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');

let passed = 0;
let failed = 0;
const failures = [];

function pass(name) {
  console.log(`  ✅ ${name}`);
  passed++;
}

function fail(name, reason) {
  console.log(`  ❌ ${name}`);
  console.log(`     → ${reason}`);
  failed++;
  failures.push({ name, reason });
}

function section(title) {
  console.log(`\n── ${title} ${'─'.repeat(Math.max(0, 55 - title.length))}`);
}

function readFile(rel) {
  try { return fs.readFileSync(path.join(ROOT, rel), 'utf8'); }
  catch { return null; }
}

function readJSON(rel) {
  const raw = readFile(rel);
  if (!raw) return null;
  try { return JSON.parse(raw); } catch { return null; }
}

// ─────────────────────────────────────────────────────────────────────────────
// Helper: flatten a nested object to dot-notation keys
function flatKeys(obj, prefix = '') {
  return Object.entries(obj).flatMap(([k, v]) => {
    const key = prefix ? `${prefix}.${k}` : k;
    return typeof v === 'object' && v !== null ? flatKeys(v, key) : [key];
  });
}

// ─────────────────────────────────────────────────────────────────────────────
section('1. Locale files — existence and valid JSON');

const LOCALES = ['en', 'he', 'es'];
const dicts = {};
for (const lang of LOCALES) {
  const rel = `assets/locales/${lang}.json`;
  const obj = readJSON(rel);
  if (!obj) {
    fail(`${rel} exists and parses`, 'File missing or invalid JSON');
  } else {
    dicts[lang] = obj;
    pass(`${rel} — valid JSON`);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('2. Key parity — he.json and es.json vs en.json');

if (dicts.en) {
  const enKeys = flatKeys(dicts.en);
  for (const lang of ['he', 'es']) {
    if (!dicts[lang]) { fail(`${lang}.json key parity`, 'File unavailable'); continue; }
    const langKeys = flatKeys(dicts[lang]);
    const missing  = enKeys.filter(k => !langKeys.includes(k));
    const extra    = langKeys.filter(k => !enKeys.includes(k));
    if (missing.length === 0 && extra.length === 0) {
      pass(`${lang}.json — ${enKeys.length}/${enKeys.length} keys (100%)`);
    } else {
      if (missing.length) fail(`${lang}.json missing keys`, missing.slice(0,5).join(', ') + (missing.length > 5 ? `… +${missing.length-5}` : ''));
      if (extra.length)   fail(`${lang}.json extra keys`,   extra.slice(0,5).join(', '));
    }
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('3. No null or empty string values in any locale');

for (const lang of LOCALES) {
  if (!dicts[lang]) continue;
  const empties = flatKeys(dicts[lang]).filter(k => {
    const parts = k.split('.');
    let v = dicts[lang];
    for (const p of parts) v = v?.[p];
    return v === null || v === '' || v === undefined;
  });
  empties.length === 0
    ? pass(`${lang}.json — no null/empty values`)
    : fail(`${lang}.json has empty values`, empties.slice(0,5).join(', '));
}

// ─────────────────────────────────────────────────────────────────────────────
section('4. Locale schema — required namespaces');

const REQUIRED_NS = ['nav', 'common', 'login', 'settings', 'dashboard', 'inventory', 'chat', 'projects', 'locations'];
for (const lang of LOCALES) {
  if (!dicts[lang]) continue;
  const missing = REQUIRED_NS.filter(ns => !dicts[lang][ns]);
  missing.length === 0
    ? pass(`${lang}.json — all ${REQUIRED_NS.length} namespaces present`)
    : fail(`${lang}.json missing namespaces`, missing.join(', '));
}

// ─────────────────────────────────────────────────────────────────────────────
section('5–6. i18n.js — syntax and public API');

const i18nSrc = readFile('assets/i18n.js');
if (!i18nSrc) {
  fail('assets/i18n.js exists', 'File not found');
} else {
  // Syntax + API check: wrap in a function that returns the controller
  try {
    // The IIFE assigns localizationController to its enclosing scope.
    // Wrap it so we can capture the return value in Node.
    const wrapped = `
      const _window   = { openSidebar(){}, closeSidebar(){} };
      const _document = {
        documentElement: { dir: 'ltr', lang: 'en', classList: { add(){}, remove(){} } },
        querySelectorAll: () => [],
        getElementById: () => null,
        body: { style: {} },
      };
      const _localStorage = { getItem: () => null, setItem: () => {} };
      const _navigator   = { language: 'en' };
      // Inject globals the IIFE references
      const window        = _window;
      const document      = _document;
      const localStorage  = _localStorage;
      const navigator     = _navigator;
      const fetch         = async () => ({ ok: true, json: async () => ({}) });
      ${i18nSrc}
      localizationController
    `;
    const controller = eval(wrapped);
    pass('i18n.js — no syntax errors');

    // API surface check
    const EXPECTED_API = ['init', 't', 'loadLocale', 'applyTranslations', 'getCurrentLang', 'getSupportedLangs'];
    const missingAPI = EXPECTED_API.filter(fn => typeof controller?.[fn] !== 'function');
    missingAPI.length === 0
      ? pass(`i18n.js — exports all ${EXPECTED_API.length} public methods`)
      : fail('i18n.js public API', `Missing: ${missingAPI.join(', ')}`);
  } catch (e) {
    fail('i18n.js — syntax/runtime', e.message);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('7. data-i18n attributes present in all primary pages');

const PRIMARY_PAGES = [
  'dashboard.php', 'settings.php', 'chat.php',
  'projects.php', 'locations.php', 'add_item.php', 'item_details.php',
];

for (const page of PRIMARY_PAGES) {
  const src = readFile(page);
  if (!src) { fail(`${page} exists`, 'File not found'); continue; }
  const count = (src.match(/data-i18n/g) || []).length;
  count >= 5
    ? pass(`${page} — ${count} data-i18n* attributes`)
    : fail(`${page} — insufficient data-i18n attrs`, `Only ${count} found (expected ≥ 5)`);
}

// ─────────────────────────────────────────────────────────────────────────────
section('8. localizationController.init() called in all primary pages');

for (const page of PRIMARY_PAGES) {
  const src = readFile(page);
  if (!src) continue;
  src.includes('localizationController.init()')
    ? pass(`${page} — calls localizationController.init()`)
    : fail(`${page}`, 'Missing localizationController.init() call');
}

// ─────────────────────────────────────────────────────────────────────────────
section('9. i18n.js script tag in all primary pages');

for (const page of PRIMARY_PAGES) {
  const src = readFile(page);
  if (!src) continue;
  src.includes('assets/i18n.js')
    ? pass(`${page} — loads assets/i18n.js`)
    : fail(`${page}`, 'Missing <script src="assets/i18n.js">');
}

// ─────────────────────────────────────────────────────────────────────────────
section('10. RTL CSS rules present in app.css');

const css = readFile('assets/app.css');
if (!css) {
  fail('assets/app.css exists', 'File not found');
} else {
  const RTL_CHECKS = [
    ['html[dir="rtl"] #sidebar',          'Sidebar RTL anchor rule'],
    ['translateX(0) !important',           'Sidebar visible-state rule'],
    ['sidebar-open',                       'sidebar-open class rule'],
    ['margin-right: 16rem',               'Main content right margin'],
    ['flex-direction: row-reverse',        'Nav link row-reverse'],
  ];
  for (const [pattern, label] of RTL_CHECKS) {
    css.includes(pattern)
      ? pass(`app.css — ${label}`)
      : fail(`app.css — ${label}`, `Pattern "${pattern}" not found`);
  }
  // Chat bubble isolation lives in i18n.js (_isolateLTRContent), not app.css
  if (i18nSrc) {
    (i18nSrc.includes('msg-user') && i18nSrc.includes("setAttribute('dir'") && i18nSrc.includes("'ltr'"))
      ? pass("i18n.js — chat bubble dir=\"ltr\" isolation (_isolateLTRContent)")
      : fail("i18n.js", "Chat bubble LTR isolation not found in _isolateLTRContent");
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('11. No bare "Logout" text node remaining');

const ALL_PHP = fs.readdirSync(ROOT)
  .filter(f => f.endsWith('.php'))
  .map(f => ({ file: f, src: readFile(f) }))
  .filter(({ src }) => src);

const bareLogout = ALL_PHP.filter(({ src }) =>
  /^\s+Logout\s*$/m.test(src)
);
bareLogout.length === 0
  ? pass(`All PHP files — no bare "Logout" text nodes (${ALL_PHP.length} files checked)`)
  : fail('Bare Logout text remaining', bareLogout.map(f => f.file).join(', '));

// ─────────────────────────────────────────────────────────────────────────────
section('12. package.json has validate:i18n script');

const pkg = readJSON('package.json');
pkg?.scripts?.['validate:i18n']
  ? pass('package.json — validate:i18n script present')
  : fail('package.json', 'Missing validate:i18n script');

// ─────────────────────────────────────────────────────────────────────────────
section('13–14. i18n.js internal functions');

if (i18nSrc) {
  i18nSrc.includes('_patchSidebar')
    ? pass('i18n.js — _patchSidebar RTL sidebar patching function present')
    : fail('i18n.js', '_patchSidebar function missing');

  i18nSrc.includes('_isolateLTRContent')
    ? pass('i18n.js — _isolateLTRContent BiDi isolation function present')
    : fail('i18n.js', '_isolateLTRContent function missing');

  i18nSrc.includes("RTL_LANGS.includes(langCode)")
    ? pass('i18n.js — RTL direction detection active')
    : fail('i18n.js', 'RTL_LANGS direction check missing');

  i18nSrc.includes("'he'")
    ? pass("i18n.js — Hebrew ('he') in SUPPORTED list")
    : fail('i18n.js', "Hebrew locale not in SUPPORTED");
}

// ─────────────────────────────────────────────────────────────────────────────
section('15. CHANGELOG documents i18n feature');

const changelog = readFile('CHANGELOG.md');
if (!changelog) {
  fail('CHANGELOG.md exists', 'File not found');
} else {
  ['i18n.js', 'he.json', 'es.json', 'validate:i18n', 'RTL'].every(token => changelog.includes(token))
    ? pass('CHANGELOG.md — i18n feature fully documented')
    : fail('CHANGELOG.md', 'Missing expected i18n tokens');
}

// ─────────────────────────────────────────────────────────────────────────────
// SUMMARY
console.log('\n' + '═'.repeat(60));
console.log(`  RESULTS: ${passed} passed, ${failed} failed`);
console.log('═'.repeat(60));

if (failed > 0) {
  console.log('\n  FAILURES:');
  failures.forEach(({ name, reason }) => {
    console.log(`  ✗ ${name}`);
    console.log(`    ${reason}`);
  });
  process.exit(1);
} else {
  console.log('\n  ✅ All checks passed — safe to merge into main.\n');
  process.exit(0);
}
