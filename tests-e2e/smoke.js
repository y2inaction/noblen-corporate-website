// Front-end smoke check: no console errors, no third-party requests, no horizontal overflow, nav works.
// Usage: BASE_URL=http://127.0.0.1:8099 node smoke.js [screenshot-dir]
const { chromium } = require('playwright');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const shots = process.argv[2];
const paths = ['/', '/projects/', '/group/', '/industries/', '/solutions/', '/products/', '/?s=fixture', '/missing-page-check/'];
const widths = [[1280, 900], [768, 1024], [360, 740]]; // C13: 360, 768 and 1280 px
const tokens = /lorem ipsum|\bTODO\b|\bTBD\b|\bFIXME\b|\[placeholder|\{\{|\}\}|your (company|name) here|coming soon/i;
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
      const body = await page.locator('body').innerText();
      if (tokens.test(body)) fail(`${w} ${p} placeholder token in rendered text: ${body.match(tokens)[0]}`);
      if (shots && (p === '/' || p.startsWith('/projects/'))) await page.screenshot({ path: `${shots}/${p === '/' ? 'home' : 'archive'}-${w}.png`, fullPage: true });
    }
    if (w === 360) {
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
