// The populated website in a real browser: one-step installation from the dashboard, then every public page at desktop, tablet and
// mobile widths: renders, one h1, no console or page errors, no broken images or internal links, no horizontal scroll, working
// navigation, brand colours, and axe. Legal pages stay unpublished. Run after setup.js. Dev-only accounts on a disposable site.
const H = require('./brand-lib-e2e.js');
const AxeBuilder = require('@axe-core/playwright').default;
const { chromium, base } = H;
const R = H.reporter();
const SETUP = `${base}/wp-admin/admin.php?page=noblen-setup`;
const PAGES = ['', 'about/', 'our-businesses/', 'industries-we-serve/', 'solutions-and-services/', 'portfolio/', 'products-and-platforms/', 'partnerships/', 'insights/', 'contact/'];

(async () => {
  const browser = await chromium.launch();
  const { ctx, page } = await H.login(browser, 'admin', 'e2e-admin-dev-only');
  await page.goto(SETUP);
  await page.locator('input[name="confirm_quickstart"]').check();
  await page.locator('.noblen-step--quick input[type="submit"], .noblen-step--quick button[type="submit"]').first().click();
  await page.waitForURL(/page=noblen-setup/);
  R.check(await page.locator('.notice-error').count() === 0, 'installing the complete website from the dashboard succeeds');
  R.check(await page.locator('text=Content to review and replace').count() >= 1, 'the screen lists what to review and replace');
  await ctx.close();

  const pub = await browser.newContext();
  for (const path of ['privacy/', 'terms/']) {
    R.check((await pub.request.get(`${base}/${path}`)).status() === 404, `/${path} (legal draft) is not public`);
  }
  const brokenGlobal = new Set();
  for (const [name, vp] of [['desktop', { width: 1280, height: 900 }], ['tablet', { width: 820, height: 1000 }], ['mobile', { width: 390, height: 800 }]]) {
    const c = await browser.newContext({ viewport: vp });
    const pg = await c.newPage();
    const errors = [];
    pg.on('pageerror', (e) => errors.push(e.message));
    pg.on('console', (m) => { if (m.type() === 'error' && !/ERR_NAME_NOT_RESOLVED/.test(m.text())) { errors.push(m.text()); } });
    const failed = [];
    pg.on('response', (r) => { if (r.url().startsWith(base) && r.status() >= 400) { failed.push(`${r.status()} ${r.url()}`); } });
    for (const path of PAGES) {
      const label = `${name} /${path}`;
      const resp = await pg.goto(`${base}/${path}`, { waitUntil: 'networkidle' });
      R.check(resp.status() === 200, `${label} returns 200`);
      R.check(await pg.locator('h1').count() === 1, `${label} has exactly one h1`);
      const overflow = await pg.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      R.check(overflow <= 1, `${label} has no horizontal scroll`, String(overflow));
      const brokenImgs = await pg.evaluate(() => Array.from(document.images).filter((i) => !i.complete || i.naturalWidth === 0).map((i) => i.src));
      R.check(brokenImgs.length === 0, `${label} has no broken images`, brokenImgs.join(' '));
      const empty = await pg.evaluate(() => Array.from(document.querySelectorAll('a')).filter((a) => !a.getAttribute('href') || a.getAttribute('href') === '#').length);
      R.check(empty === 0, `${label} has no empty links`);
      const hasPlaceholder = await pg.evaluate(() => /\[Owner to complete/i.test(document.body.innerText));
      R.check(!hasPlaceholder, `${label} shows no unresolved placeholder text`);
      if (name !== 'tablet') {
        const ax = await new AxeBuilder({ page: pg }).withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
        R.check(ax.violations.length === 0, `${label} axe: no violations`, ax.violations.map((v) => `${v.id}[${v.nodes.length}]`).join(', '));
      }
    }
    R.check(errors.length === 0, `${name}: no console or page errors across all pages`, errors.join('; '));
    R.check(failed.length === 0, `${name}: no failed requests (assets, images, scripts)`, failed.join('; '));
    await c.close();
  }

  // Navigation works: desktop links reach their pages; the mobile menu opens and reaches a page.
  const d = await (await browser.newContext({ viewport: { width: 1280, height: 900 } })).newPage();
  await d.goto(`${base}/`);
  const navHrefs = await d.$$eval('header nav a', (as) => as.map((a) => a.getAttribute('href')));
  R.check(navHrefs.length >= 8, `primary navigation lists the pages (${navHrefs.length})`);
  for (const h of navHrefs) {
    const r = await d.request.get(new URL(h, base).href);
    R.check(r.status() === 200, `navigation link ${h} loads`);
  }
  const footerHrefs = await d.$$eval('footer a[href^="/"], footer a[href^="http://127"]', (as) => as.map((a) => a.getAttribute('href')));
  for (const h of footerHrefs) {
    const r = await d.request.get(new URL(h, base).href);
    R.check(r.status() === 200, `footer link ${h} loads`);
  }
  const m = await (await browser.newContext({ viewport: { width: 390, height: 800 } })).newPage();
  await m.goto(`${base}/`);
  const toggle = m.locator('button[aria-controls], .menu-toggle, .nav-toggle').first();
  R.check(await toggle.count() === 1 && await toggle.isVisible(), 'mobile menu button is visible at 390px');
  await toggle.click();
  R.check(await m.locator('header nav a').first().isVisible(), 'mobile menu opens and shows links');

  // Brand: navy and blue with green accents; the header, buttons and footer use the design tokens, not gold or yellow.
  const colours = await d.evaluate(() => {
    const cs = (sel, prop) => { const e = document.querySelector(sel); return e ? getComputedStyle(e)[prop] : ''; };
    return { footer: cs('footer', 'backgroundColor'), btn: cs('.btn, .button', 'backgroundColor') };
  });
  const isWarm = (css) => { const m2 = /rgba?\((\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec(css); if (!m2) { return false; } const [r, g, b] = [+m2[1], +m2[2], +m2[3]]; return r > 180 && g > 140 && b < 90; };
  R.check(!isWarm(colours.footer) && !isWarm(colours.btn), 'no gold or yellow as a primary brand colour', JSON.stringify(colours));
  await browser.close();
  R.done();
})().catch((e) => { console.log('FAIL (exception)', e.message); process.exit(1); });
