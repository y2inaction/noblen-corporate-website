// Front-end smoke check: no console errors, no third-party requests, no horizontal overflow, nav works.
// Usage: BASE_URL=http://127.0.0.1:8099 node smoke.js [screenshot-dir]
const { chromium } = require('playwright');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const shots = process.argv[2];
const paths = ['/', '/projects/', '/group/', '/industries/', '/solutions/', '/products/', '/?s=fixture', '/missing-page-check/'];
const widths = [[1440, 900], [820, 1100], [375, 800]];
(async () => {
  const browser = await chromium.launch();
  const host = new URL(base).host;
  let failures = 0;
  const fail = (m) => { failures++; console.log('FAIL', m); };
  for (const [w, h] of widths) {
    const page = await browser.newPage({ viewport: { width: w, height: h } });
    const errors = []; const foreign = [];
    page.on('console', (m) => { if (m.type() === 'error' && !(m.location().url || '').includes('/missing-page-check/')) errors.push(m.text() + ' @ ' + (m.location().url || '')); });
    page.on('pageerror', (e) => errors.push(String(e)));
    page.on('request', (r) => { if (new URL(r.url()).host !== host && !r.url().startsWith('data:')) foreign.push(r.url()); });
    for (const p of paths) {
      const res = await page.goto(base + p, { waitUntil: 'networkidle' });
      const expected = p.includes('missing') ? 404 : 200;
      if (res.status() !== expected) fail(`${w} ${p} status ${res.status()}`);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
      if (overflow) fail(`${w} ${p} horizontal overflow`);
      if (shots && p === '/') await page.screenshot({ path: `${shots}/home-${w}.png`, fullPage: true });
    }
    if (w === 375) {
      await page.goto(base + '/');
      const t = page.locator('.nav-toggle');
      await t.click();
      if ((await t.getAttribute('aria-expanded')) !== 'true') fail('menu toggle did not open');
      await page.keyboard.press('Escape');
      if ((await t.getAttribute('aria-expanded')) !== 'false') fail('Escape did not close menu');
    }
    if (errors.length) fail(`${w} console errors: ${errors.join(' | ')}`);
    if (foreign.length) fail(`${w} third-party requests: ${foreign.join(' | ')}`);
    await page.close();
  }
  await browser.close();
  console.log(failures ? `${failures} failure(s)` : 'PASS');
  process.exit(failures ? 1 : 0);
})();
