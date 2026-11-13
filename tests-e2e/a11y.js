// Accessibility checks: axe-core on public pages (WCAG 2.0/2.1 A and AA), token contrast, keyboard behaviour.
const { chromium } = require('playwright');
const AxeBuilder = require('@axe-core/playwright').default;
const fs = require('fs');
const path = require('path');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const paths = (process.env.A11Y_PATHS || '/,/projects/,/group/,/industries/,/solutions/,/products/,/?s=fixture,/missing-page-check/,/projects/dev-fixture-project/,/group/dev-fixture-unit/,/industries/dev-fixture-industry/').split(',');
let failures = 0, passes = 0;
const check = (ok, name, extra = '') => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name, extra); } };

// ---- C15-001: contrast of every text/background pair the theme uses (WCAG AA), for the default palette, every built-in preset and
// their dark derivations. The pair list and maths are shared with the Customizer (assets/js/brand-lib.js).
const brand = require('../wordpress-theme/noblen-corporate/assets/js/brand-lib.js');
const brandDef = require('../wordpress-theme/noblen-corporate/assets/brand-defaults.json');
for (const pr of brandDef.presets) {
  const light = brand.complete(brandDef, pr.colors);
  const dark = Object.assign({}, light, brand.darkPalette(light));
  for (const [mode, pal] of [['light', light], ['dark', dark]]) {
    for (const row of brand.contrastReport(pal)) check(row.pass, `contrast [${pr.id}, ${mode}] ${row.label} = ${row.ratio} (min ${row.min})`);
  }
}

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
