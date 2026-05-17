/**
 * contrast_audit.js — WCAG 2.1 Contrast Audit for DIY Lab Light Mode
 *
 * Usage:  node contrast_audit.js
 *
 * Checks every foreground/background pair defined in the light mode CSS,
 * reports AA (4.5:1 normal / 3:1 large) and AAA (7:1 / 4.5:1) compliance,
 * and outputs the minimum corrected hex that just passes AA.
 */

// ─── WCAG maths ──────────────────────────────────────────────────────────────

function hexToRgb(hex) {
  const h = hex.replace('#', '');
  const len = h.length === 3 ? 1 : 2;
  return [0, 1, 2].map(i => parseInt(h.slice(i * len, i * len + len).padEnd(2, h[i * len]), 16));
}

function linearise(c) {
  const s = c / 255;
  return s <= 0.04045 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
}

function luminance([r, g, b]) {
  return 0.2126 * linearise(r) + 0.7152 * linearise(g) + 0.0722 * linearise(b);
}

function contrastRatio(hex1, hex2) {
  const l1 = luminance(hexToRgb(hex1));
  const l2 = luminance(hexToRgb(hex2));
  const [light, dark] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (light + 0.05) / (dark + 0.05);
}

function grade(ratio, isLarge = false) {
  const aaMin  = isLarge ? 3   : 4.5;
  const aaaMin = isLarge ? 4.5 : 7;
  if (ratio >= aaaMin) return 'AAA ✅';
  if (ratio >= aaMin)  return 'AA  ✅';
  if (isLarge && ratio >= 3) return 'AA-large ✅';
  return 'FAIL ❌';
}

/**
 * Darken a hex colour towards black until its contrast against bg meets `target`.
 * Returns the darkened hex (or the original if it already passes).
 */
function autoFix(fg, bg, target = 4.5) {
  let [r, g, b] = hexToRgb(fg);
  for (let step = 0; step < 255; step++) {
    const ratio = contrastRatio(`#${toHex(r)}${toHex(g)}${toHex(b)}`, bg);
    if (ratio >= target) return `#${toHex(r)}${toHex(g)}${toHex(b)}`;
    r = Math.max(0, r - 2);
    g = Math.max(0, g - 2);
    b = Math.max(0, b - 2);
  }
  return '#000000';
}

function toHex(n) { return Math.round(n).toString(16).padStart(2, '0'); }

// ─── Colour pairs to audit ────────────────────────────────────────────────────
// Format: { label, fg, bg, isLarge }
// bg defaults to the main page background (#f0f2f8)
// isLarge = true for headings, labels ≥ 18 px or ≥ 14 px bold

const BG      = '#f0f2f8'; // page background
const GLASS   = '#ffffffd9'; // rgba(255,255,255,.85) → approx #ffffffd9 → use #f5f5ff
const GLASS_B = '#f5f6ff'; // approximation of glass card in light mode
const WHITE   = '#ffffff';

const pairs = [
  // ── Body text ──────────────────────────────────────────────────────────────
  { label: 'Body text (.text-white → #0f172a) on BG',     fg: '#0f172a', bg: BG },
  { label: 'Subtext (.text-slate-200 → #334155) on BG',   fg: '#334155', bg: BG },
  { label: 'Muted (.text-slate-300 → #475569) on BG',     fg: '#475569', bg: BG },
  { label: 'Secondary (.text-slate-400 → #64748b) on BG', fg: '#64748b', bg: BG },
  { label: 'Faint (.text-slate-500 → #94a3b8) on BG',     fg: '#94a3b8', bg: BG },
  { label: 'Lightest (.text-slate-600 → #cbd5e1) on BG',  fg: '#cbd5e1', bg: BG },

  // ── On glass cards ─────────────────────────────────────────────────────────
  { label: 'Heading (#0f172a) on glass card',              fg: '#0f172a', bg: GLASS_B },
  { label: 'Muted text (#64748b) on glass card',           fg: '#64748b', bg: GLASS_B },
  { label: 'Faint text (#94a3b8) on glass card',           fg: '#94a3b8', bg: GLASS_B },
  { label: 'Lightest (#cbd5e1) on glass card',             fg: '#cbd5e1', bg: GLASS_B },

  // ── Nav links ──────────────────────────────────────────────────────────────
  { label: 'Nav link (#64748b) on sidebar glass',          fg: '#64748b', bg: GLASS_B },
  { label: 'Nav link active (#7c3aed) on sidebar glass',   fg: '#7c3aed', bg: GLASS_B },

  // ── Form labels ────────────────────────────────────────────────────────────
  { label: 'Form label (#7c3aed) on glass',                fg: '#7c3aed', bg: GLASS_B, isLarge: false },

  // ── Badges ─────────────────────────────────────────────────────────────────
  { label: 'Badge-new text (#15803d) on badge bg',         fg: '#15803d', bg: '#d1fae5' },  // approx rgba(22,163,74,.14) on white-ish
  { label: 'Badge-used text (#b45309) on badge bg',        fg: '#b45309', bg: '#fef3c7' },
  { label: 'Badge-refurb text (#1d4ed8) on badge bg',      fg: '#1d4ed8', bg: '#dbeafe' },

  // ── Action buttons ─────────────────────────────────────────────────────────
  { label: 'View button (#0e7490) on BG',                  fg: '#0e7490', bg: BG },
  { label: 'Edit button (#6d28d9) on BG',                  fg: '#6d28d9', bg: BG },
  { label: 'Delete button (#b91c1c) on BG',                fg: '#b91c1c', bg: BG },
  { label: 'View button (#0e7490) on glass card',          fg: '#0e7490', bg: GLASS_B },
  { label: 'Edit button (#6d28d9) on glass card',          fg: '#6d28d9', bg: GLASS_B },
  { label: 'Delete button (#b91c1c) on glass card',        fg: '#b91c1c', bg: GLASS_B },

  // ── Accent purple ──────────────────────────────────────────────────────────
  { label: 'Purple accent (#7c3aed) on BG',                fg: '#7c3aed', bg: BG },
  { label: 'Cyan accent (#06b6d4) on BG',                  fg: '#06b6d4', bg: BG },
  { label: 'Emerald accent (#10b981) on BG',               fg: '#10b981', bg: BG },

  // ── Stat card labels ───────────────────────────────────────────────────────
  { label: 'Stat label (#94a3b8) on stat card (#fff opacity .7)', fg: '#94a3b8', bg: '#f0f3fb' },

  // ── Table header ───────────────────────────────────────────────────────────
  { label: 'TH text (#64748b) on table header (BG)',       fg: '#64748b', bg: BG },

  // ── Toggle label ───────────────────────────────────────────────────────────
  { label: 'Theme toggle label (#64748b) on sidebar',      fg: '#64748b', bg: GLASS_B },

  // ── Input placeholder ──────────────────────────────────────────────────────
  { label: 'Input placeholder (#94a3b8) on input (#fff)',  fg: '#94a3b8', bg: WHITE },

  // ── Input text ─────────────────────────────────────────────────────────────
  { label: 'Input text (#1e293b) on input (#fff)',         fg: '#1e293b', bg: WHITE },
];

// ─── Run audit ───────────────────────────────────────────────────────────────

const RESET  = '\x1b[0m';
const RED    = '\x1b[31m';
const GREEN  = '\x1b[32m';
const YELLOW = '\x1b[33m';
const BOLD   = '\x1b[1m';
const DIM    = '\x1b[2m';

console.log(`\n${BOLD}╔══════════════════════════════════════════════════════════════════╗${RESET}`);
console.log(`${BOLD}║        DIY Lab — WCAG 2.1 Light Mode Contrast Audit              ║${RESET}`);
console.log(`${BOLD}╚══════════════════════════════════════════════════════════════════╝${RESET}`);
console.log(`  AA  standard: 4.5:1 (normal text) · 3:1 (large/UI)`);
console.log(`  AAA standard: 7.0:1 (normal text) · 4.5:1 (large/UI)\n`);

const failures = [];
const warnings = [];

for (const { label, fg, bg, isLarge = false } of pairs) {
  const ratio = contrastRatio(fg, bg);
  const aaMin = isLarge ? 3 : 4.5;
  const passes = ratio >= aaMin;
  const g = grade(ratio, isLarge);
  const ratioStr = ratio.toFixed(2).padStart(5);
  const fgBg = `${DIM}${fg} / ${bg}${RESET}`;

  if (!passes) {
    const fixed = autoFix(fg, bg, aaMin);
    const fixedRatio = contrastRatio(fixed, bg);
    failures.push({ label, fg, bg, ratio, fixed, fixedRatio });
    console.log(`  ${RED}${g}${RESET}  ${ratioStr}:1  ${label}`);
    console.log(`         ${fgBg}`);
    console.log(`         ${YELLOW}→ fix: ${fixed} (${fixedRatio.toFixed(2)}:1)${RESET}\n`);
  } else {
    console.log(`  ${GREEN}${g}${RESET}  ${ratioStr}:1  ${label}`);
    console.log(`         ${fgBg}\n`);
  }
}

// ─── Summary ─────────────────────────────────────────────────────────────────
console.log(`${BOLD}─────────────────────────────────────────────────────────────────────${RESET}`);
console.log(`  Total pairs checked : ${pairs.length}`);
console.log(`  ${GREEN}Passing AA          : ${pairs.length - failures.length}${RESET}`);
console.log(`  ${failures.length > 0 ? RED : GREEN}Failing AA          : ${failures.length}${RESET}\n`);

if (failures.length > 0) {
  console.log(`${BOLD}━━━ CSS FIXES (copy into html.light section of app.css) ━━━━━━━━━━━━${RESET}\n`);
  for (const { label, fg, bg, ratio, fixed, fixedRatio } of failures) {
    console.log(`  /* ${label} */`);
    console.log(`  /* was: ${fg} (${ratio.toFixed(2)}:1) — fixed: ${fixed} (${fixedRatio.toFixed(2)}:1) */`);
  }
  console.log('');
}
