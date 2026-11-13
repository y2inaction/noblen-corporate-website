// Accessibility checks: axe-core on public pages (WCAG 2.0/2.1 A and AA), token contrast, keyboard behaviour.
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const path = require('path');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const paths = (process.env.A11Y_PATHS || '/,/projects/,/group/,/industries/,/solutions/,/products/,/?s=fixture,/missing-page-check/,/projects/dev-fixture-project/,/group/dev-fixture-unit/,/industries/dev-fixture-industry/').split(',');
let failures = 0, passes = 0;
const check = (ok, name, extra = '') => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name, extra); } };

// ---- C15-001: contrast of every text/background token pair used by the theme (WCAG AA).
function lum(hex) { const c = hex.replace('#', ''); const v = [0, 2, 4].map((i) => parseInt(c.substr(i, 2), 16) / 255).map((x) => x <= 0.03928 ? x / 12.92 : Math.pow((x + 0.055) / 1.055, 2.4)); return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2]; }
const ratio = (a, b) => { const [l1, l2] = [lum(a), lum(b)].sort((x, y) => y - x); return (l1 + 0.05) / (l2 + 0.05); };
const cssDir = path.join(__dirname, '../wordpress-theme/noblen-corporate/assets/css');
const css = ['tokens.css', 'main.css'].map((f) => fs.readFileSync(path.join(cssDir, f), 'utf8')).join('\n');
const tok = {}; for (const m of css.matchAll(/--([a-z-]+):(#[0-9a-fA-F]{3,6})\b/g)) tok[m[1]] = m[2].length === 4 ? '#' + [...m[2].slice(1)].map((c) => c + c).join('') : m[2];
const pairs = [['ink', 'white', 4.5], ['muted', 'white', 4.5], ['muted', 'grey', 4.5], ['navy', 'white', 4.5], ['navy', 'grey', 4.5], ['blue-d', 'white', 4.5], ['blue-d', 'grey', 4.5], ['white', 'blue', 4.5], ['white', 'blue-d', 4.5], ['white', 'navy', 4.5], ['teal', 'white', 4.5]];
for (const [fg, bg, min] of pairs) { const r = ratio(tok[fg], tok[bg]); check(r >= min, `contrast ${fg} on ${bg} = ${r.toFixed(2)} (min ${min})`); }
// Specific literal pairs used in CSS.
for (const [fg, bg, label] of [['#dbe6ff', '#12306b', 'hero intro on gradient end'], ['#b9c8ee', '#0a1b3d', 'footer text on navy'], ['#0a5d58', '#e5f4f3', 'badge text on badge background']]) check(ratio(fg, bg) >= 4.5, `contrast ${label} = ${ratio(fg, bg).toFixed(2)}`);

(async () => {
  const browser = await chromium.launch();
  for (const [w, h] of [[1280, 900], [375, 800]]) {
    const page = await (await browser.newContext({ viewport: { width: w, height: h } })).newPage();
    for (const p of paths) {
      await page.goto(base + p, { waitUntil: 'networkidle' });
      const r = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
      check(r.violations.length === 0, `axe ${w}px ${p} (${r.passes.length} rules passed)`, r.violations.map((v) => `${v.id}[${v.nodes.length}]`).join(', '));
      if (r.violations.length) for (const v of r.violations) console.log('     ', v.id, v.impact, v.help, v.nodes.slice(0, 2).map((n) => n.target.join(' ')).join(' ; '));
    }
    await page.close();
  }
  // ---- Keyboard (C14): skip link first, visible focus, menu operable.
  const page = await browser.newPage({ viewport: { width: 1280, height: 900 } });
  await page.goto(base + '/');
  await page.keyboard.press('Tab');
  const first = await page.evaluate(() => document.activeElement && document.activeElement.className);
  check(/skip-link/.test(first || ''), 'first Tab stop is the skip link');
  const outline = await page.evaluate(() => { const s = getComputedStyle(document.activeElement); return s.outlineStyle + ' ' + s.outlineWidth; });
  check(!/none/.test(outline) && !/^solid 0/.test(outline), 'focused skip link has a visible outline', outline);
  await page.keyboard.press('Enter');
  check(await page.evaluate(() => location.hash) === '#main', 'skip link moves to main content');
  let reached = 0;
  for (let i = 0; i < 25; i++) { await page.keyboard.press('Tab'); const ok = await page.evaluate(() => { const e = document.activeElement; if (!e || e === document.body) return false; const s = getComputedStyle(e); return s.outlineStyle !== 'none' || e.matches('a,button,input,select,textarea'); }); if (ok) reached++; }
  check(reached >= 15, `Tab reaches interactive elements in order (${reached}/25 stops)`);
  const mob = await browser.newPage({ viewport: { width: 375, height: 800 } });
  await mob.goto(base + '/');
  await mob.focus('.nav-toggle'); await mob.keyboard.press('Enter');
  check(await mob.getAttribute('.nav-toggle', 'aria-expanded') === 'true', 'mobile menu opens with Enter');
  await mob.keyboard.press('Escape');
  check(await mob.getAttribute('.nav-toggle', 'aria-expanded') === 'false', 'Escape closes the mobile menu and returns focus');
  check(await mob.evaluate(() => document.activeElement.classList.contains('nav-toggle')), 'focus returns to the toggle');
  await browser.close();
  console.log(`${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})();
