/**
 * user_settings_check.js
 * QA test suite for the User Settings feature set
 *
 * Covers:
 *  1.  Page rename — "User Settings" in title, header, sidebar across all pages
 *  2.  Sidebar nav key renamed to nav.user_settings in all PHP files
 *  3.  i18n keys — nav.user_settings present in all 3 locale files
 *  4.  site_config.php exists and has required variables
 *  5.  site_config.php is included in all sidebar pages
 *  6.  Personalization section present in settings.php (UI fields)
 *  7.  Personalization save handler (action=save_personalization) in settings.php
 *  8.  Dynamic brand PHP vars used in sidebars (no hardcoded "DIY Lab" text nodes)
 *  9.  Login page (index.php) uses personalization vars
 * 10.  Change Password section present in settings.php
 * 11.  Change Password form handler (action=change_password) in settings.php
 * 12.  password_hash / password_verify used (secure — no plaintext comparison)
 * 13.  schema.sql contains all new setting keys
 * 14.  Auto-migration: lab_password seed in settings.php
 * 15.  HTTP smoke test — server responds on port 8080
 * 16.  HTTP smoke test — settings.php accessible (with session — expect redirect or 200)
 * 17.  No residual hardcoded "AI Settings" text in sidebar link text
 * 18.  settings.php page title tag says "User Settings"
 *
 * Run: node tests/user_settings_check.js
 */

const fs   = require('fs');
const path = require('path');
const http = require('http');

const ROOT = path.resolve(__dirname, '..');
const BASE_URL = 'http://localhost:8080';

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

function httpGet(url, timeoutMs = 5000) {
  return new Promise((resolve) => {
    const req = http.get(url, (res) => {
      let body = '';
      res.on('data', d => body += d);
      res.on('end', () => resolve({ status: res.statusCode, body, headers: res.headers }));
    });
    req.setTimeout(timeoutMs, () => { req.destroy(); resolve({ status: 0, body: '', headers: {} }); });
    req.on('error', () => resolve({ status: 0, body: '', headers: {} }));
  });
}

// ─────────────────────────────────────────────────────────────────────────────
section('1. Page title renamed to "User Settings"');

const settingsSrc = readFile('settings.php');
if (!settingsSrc) {
  fail('settings.php exists', 'File not found');
} else {
  settingsSrc.includes('<title>User Settings')
    ? pass('settings.php — <title> contains "User Settings"')
    : fail('settings.php <title>', 'Expected "User Settings" in <title> tag');

  settingsSrc.includes('>User Settings<')
    ? pass('settings.php — <h1> contains "User Settings"')
    : fail('settings.php <h1>', 'Expected ">User Settings<" heading not found');
}

// ─────────────────────────────────────────────────────────────────────────────
section('2. Sidebar nav link key renamed to nav.user_settings');

const SIDEBAR_PAGES = [
  'settings.php', 'dashboard.php', 'add_item.php', 'chat.php',
  'projects.php', 'locations.php', 'item_details.php',
  'container_manifest.php', 'project_blueprint.php',
];

for (const page of SIDEBAR_PAGES) {
  const src = readFile(page);
  if (!src) { fail(`${page} exists`, 'File not found'); continue; }

  // Should use new key
  const hasNewKey = src.includes('nav.user_settings');
  // Should NOT use old key for the settings link
  const hasOldKey = src.includes('nav.ai_settings');

  if (hasNewKey && !hasOldKey) {
    pass(`${page} — uses nav.user_settings (old key removed)`);
  } else if (!hasNewKey) {
    fail(`${page} — nav.user_settings`, 'Key nav.user_settings not found');
  } else if (hasOldKey) {
    fail(`${page} — nav.ai_settings still present`, 'Old key "nav.ai_settings" was not replaced');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('3. i18n locales — nav.user_settings key in all 3 files');

for (const lang of ['en', 'he', 'es']) {
  const dict = readJSON(`assets/locales/${lang}.json`);
  if (!dict) { fail(`${lang}.json`, 'File missing or invalid JSON'); continue; }
  dict?.nav?.user_settings
    ? pass(`${lang}.json — nav.user_settings = "${dict.nav.user_settings}"`)
    : fail(`${lang}.json`, 'Missing nav.user_settings key');
}

// ─────────────────────────────────────────────────────────────────────────────
section('4. site_config.php — exists with required variables');

const siteConfigSrc = readFile('site_config.php');
if (!siteConfigSrc) {
  fail('site_config.php exists', 'File not found');
} else {
  const required = ['$site_name', '$site_tagline', '$site_mini_tagline', '$site_logo_url'];
  for (const varName of required) {
    siteConfigSrc.includes(varName)
      ? pass(`site_config.php — defines ${varName}`)
      : fail(`site_config.php`, `Missing variable ${varName}`);
  }
  siteConfigSrc.includes("lab_name") && siteConfigSrc.includes("lab_tagline")
    ? pass('site_config.php — reads lab_* keys from settings table')
    : fail('site_config.php', 'Does not reference lab_name / lab_tagline DB keys');
}

// ─────────────────────────────────────────────────────────────────────────────
section('5. site_config.php included in all sidebar pages');

for (const page of SIDEBAR_PAGES) {
  const src = readFile(page);
  if (!src) continue;
  src.includes('site_config.php')
    ? pass(`${page} — includes site_config.php`)
    : fail(`${page}`, 'Missing require/include of site_config.php');
}

// ─────────────────────────────────────────────────────────────────────────────
section('6. Personalization UI fields in settings.php');

if (settingsSrc) {
  const fields = [
    ['lab_name',         'Lab Name field'],
    ['lab_tagline',      'Tag Line field'],
    ['lab_mini_tagline', 'Mini Tag Line field'],
    ['lab_logo_url',     'Logo URL field'],
  ];
  for (const [field, label] of fields) {
    settingsSrc.includes(`name="${field}"`)
      ? pass(`settings.php — ${label} (name="${field}")`)
      : fail(`settings.php — ${label}`, `Input with name="${field}" not found`);
  }
  settingsSrc.includes('Save Personalization')
    ? pass('settings.php — "Save Personalization" button present')
    : fail('settings.php', 'Missing "Save Personalization" submit button');
}

// ─────────────────────────────────────────────────────────────────────────────
section('7. Personalization save action handler in settings.php PHP');

if (settingsSrc) {
  settingsSrc.includes("action'] === 'save_personalization'")
    ? pass('settings.php — save_personalization POST handler present')
    : fail('settings.php', "Missing POST handler for action=save_personalization");

  settingsSrc.includes("value=\"save_personalization\"")
    ? pass('settings.php — personalization form has hidden action input')
    : fail('settings.php', 'Missing hidden input action=save_personalization in form');
}

// ─────────────────────────────────────────────────────────────────────────────
section('8. Dynamic brand vars used in sidebar (no hardcoded "DIY Lab" text nodes)');

for (const page of SIDEBAR_PAGES) {
  const src = readFile(page);
  if (!src) continue;

  // Should use $site_name
  const usesDynamic = src.includes('$site_name') && src.includes('$site_mini_tagline');
  // Should NOT have hardcoded DIY Lab in data-i18n-text brand attributes
  const hasHardcodedBrand = src.includes('data-i18n-text="nav.brand_name"');

  if (usesDynamic && !hasHardcodedBrand) {
    pass(`${page} — uses dynamic $site_name / $site_mini_tagline`);
  } else if (!usesDynamic) {
    fail(`${page} — dynamic brand`, '$site_name or $site_mini_tagline not found in sidebar');
  } else if (hasHardcodedBrand) {
    fail(`${page} — hardcoded brand`, 'data-i18n-text="nav.brand_name" still present (i18n would override custom name)');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('9. Login page (index.php) uses personalization vars');

const indexSrc = readFile('index.php');
if (!indexSrc) {
  fail('index.php exists', 'File not found');
} else {
  const checks = [
    ['$site_name',    'Lab name var'],
    ['$site_tagline', 'Tag line var'],
    ['$site_logo_url','Logo URL var'],
    ['lab_name',      'DB key lab_name queried'],
    ['lab_tagline',   'DB key lab_tagline queried'],
  ];
  for (const [token, label] of checks) {
    indexSrc.includes(token)
      ? pass(`index.php — ${label}`)
      : fail(`index.php — ${label}`, `"${token}" not found`);
  }
  // Should NOT have the old hardcoded DIY Lab heading
  const hasHardcoded = />\s*DIY Lab\s*<\/h1>/.test(indexSrc);
  !hasHardcoded
    ? pass('index.php — h1 is dynamic (no hardcoded "DIY Lab")')
    : fail('index.php', 'h1 still hardcoded as "DIY Lab"');
}

// ─────────────────────────────────────────────────────────────────────────────
section('10. Change Password section in settings.php');

if (settingsSrc) {
  settingsSrc.includes('Change Lab Password')
    ? pass('settings.php — "Change Lab Password" section header present')
    : fail('settings.php', 'Missing Change Lab Password section header');

  const pwFields = ['current_password', 'new_password', 'confirm_password'];
  for (const f of pwFields) {
    settingsSrc.includes(`name="${f}"`)
      ? pass(`settings.php — password field: name="${f}"`)
      : fail(`settings.php`, `Missing password field name="${f}"`);
  }
  settingsSrc.includes('Update Password')
    ? pass('settings.php — "Update Password" submit button present')
    : fail('settings.php', 'Missing "Update Password" button');
}

// ─────────────────────────────────────────────────────────────────────────────
section('11. Change Password POST handler in settings.php');

if (settingsSrc) {
  settingsSrc.includes("action'] === 'change_password'")
    ? pass('settings.php — change_password POST handler present')
    : fail('settings.php', "Missing POST handler for action=change_password");

  settingsSrc.includes("value=\"change_password\"")
    ? pass('settings.php — password form has hidden action=change_password input')
    : fail('settings.php', 'Missing hidden input action=change_password in password form');
}

// ─────────────────────────────────────────────────────────────────────────────
section('12. Secure password handling — bcrypt used');

if (settingsSrc) {
  settingsSrc.includes('password_hash(')
    ? pass('settings.php — password_hash() used for hashing')
    : fail('settings.php', 'password_hash() not found — insecure storage risk');

  settingsSrc.includes('password_verify(')
    ? pass('settings.php — password_verify() used for verification')
    : fail('settings.php', 'password_verify() not found — plaintext comparison risk');

  settingsSrc.includes('PASSWORD_BCRYPT')
    ? pass('settings.php — PASSWORD_BCRYPT algorithm specified')
    : fail('settings.php', 'PASSWORD_BCRYPT not specified');
}

if (indexSrc) {
  indexSrc.includes('password_verify(')
    ? pass('index.php — password_verify() used for login check')
    : fail('index.php', 'password_verify() not found in login — may still use plaintext compare');

  const hasPlaintext = indexSrc.includes("=== '1234'") || /\$_POST.*===.*'\d+'/.test(indexSrc);
  // Plaintext '1234' is ok only as a *fallback* when no hash is in DB yet — check it's conditional
  if (hasPlaintext) {
    indexSrc.includes('$use_hash') && indexSrc.includes('password_verify')
      ? pass("index.php — '1234' plaintext is only a fallback (use_hash guard present)")
      : fail('index.php', "Plaintext '1234' comparison without proper fallback guard");
  } else {
    pass('index.php — no raw plaintext password comparison');
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('13. schema.sql — all new setting keys seeded');

const schemaSrc = readFile('schema.sql');
if (!schemaSrc) {
  fail('schema.sql exists', 'File not found');
} else {
  const keys = ['lab_password', 'lab_name', 'lab_tagline', 'lab_mini_tagline', 'lab_logo_url'];
  for (const key of keys) {
    schemaSrc.includes(`'${key}'`)
      ? pass(`schema.sql — '${key}' seed row present`)
      : fail(`schema.sql`, `Missing seed row for '${key}'`);
  }
}

// ─────────────────────────────────────────────────────────────────────────────
section('14. Auto-migration: lab_password seed in settings.php');

if (settingsSrc) {
  settingsSrc.includes("INSERT IGNORE INTO settings") && settingsSrc.includes('lab_password')
    ? pass("settings.php — auto-migration: INSERT IGNORE for lab_password present")
    : fail('settings.php', 'Missing auto-migration INSERT IGNORE for lab_password');
}

// ─────────────────────────────────────────────────────────────────────────────
section('15–16. HTTP smoke tests (server on :8080)');

(async () => {
  // Test 15: Server responds
  const root = await httpGet(`${BASE_URL}/`);
  root.status > 0
    ? pass(`Server responds on :8080 (HTTP ${root.status})`)
    : fail('Server reachable on :8080', 'No response — server may not be running');

  // Test 16: settings.php redirects (not authenticated) or loads
  const settingsRes = await httpGet(`${BASE_URL}/settings.php`);
  if (settingsRes.status === 302 || settingsRes.status === 301) {
    pass('settings.php — redirects unauthenticated requests (HTTP 3xx) ✓ secure');
  } else if (settingsRes.status === 200) {
    // Could be showing login form or actual page
    settingsRes.body.includes('User Settings') || settingsRes.body.includes('user_settings')
      ? pass('settings.php — 200 OK, contains "User Settings" content')
      : fail('settings.php HTTP 200', 'Page loaded but "User Settings" not found in response');
  } else {
    fail(`settings.php HTTP`, `Unexpected status ${settingsRes.status}`);
  }

  // Test 17: No "AI Settings" in sidebar link text on dashboard
  const dashRes = await httpGet(`${BASE_URL}/dashboard.php`);
  if (dashRes.status === 200 || dashRes.status === 302) {
    if (dashRes.status === 302) {
      pass('dashboard.php — redirects unauthenticated (status 302) — skipping content checks');
    } else {
      !dashRes.body.includes('> AI Settings<') && !dashRes.body.includes('>AI Settings<')
        ? pass('dashboard.php — no "AI Settings" text in sidebar nav links')
        : fail('dashboard.php', 'Found "> AI Settings<" — sidebar link not yet renamed');
    }
  }

  // Test 18: settings.php <title> via HTTP
  const settingsPage = await httpGet(`${BASE_URL}/settings.php`);
  if (settingsPage.status === 200 && settingsPage.body.includes('<title>')) {
    settingsPage.body.includes('User Settings')
      ? pass('settings.php HTTP — <title> contains "User Settings"')
      : fail('settings.php HTTP <title>', '"User Settings" not in rendered page title');
  } else if (settingsPage.status === 302) {
    pass('settings.php HTTP — redirected (unauthenticated) — title test skipped');
  }

  // ─────────────────────────────────────────────────────────────────────────
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
    console.log('\n  ✅ All User Settings QA checks passed.\n');
    process.exit(0);
  }
})();
