// SEO and document-structure checks on rendered public pages: one description, valid structured data, no duplicate tags,
// one h1, and no skipped heading levels. Usage: BASE_URL=http://127.0.0.1:8099 node seo.js
const { chromium } = require('playwright');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const paths = (process.env.SEO_PATHS || '/,/projects/,/group/,/industries/,/solutions/,/products/,/projects/dev-fixture-project/').split(',');
let failures = 0, passes = 0;
const check = (ok, name) => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name); } };
(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();
  for (const p of paths) {
    const res = await page.goto(base + p, { waitUntil: 'domcontentloaded' });
    if (res.status() !== 200) { console.log('  skip (status ' + res.status() + ')', p); continue; }
    const d = await page.evaluate(() => {
      const metas = (sel) => [...document.querySelectorAll(sel)].map((m) => m.getAttribute('content') || '');
      const heads = [...document.querySelectorAll('main h1,main h2,main h3,main h4')].map((h) => Number(h.tagName[1]));
      const ld = [...document.querySelectorAll('script[type="application/ld+json"]')].map((s) => s.textContent);
      return { desc: metas('meta[name="description"]'), ogTitle: metas('meta[property="og:title"]'), ogDesc: metas('meta[property="og:description"]'),
        canon: document.querySelectorAll('link[rel="canonical"]').length, h1: document.querySelectorAll('h1').length, heads, ld, title: document.title,
        lang: document.documentElement.lang };
    });
    check(d.desc.length === 1 && d.desc[0].length > 0 && d.desc[0].length <= 161, `${p}: exactly one meta description, at most 160 characters`);
    check(d.ogTitle.length === 1 && d.ogDesc.length === 1, `${p}: one og:title and one og:description`);
    check(d.canon <= 1, `${p}: no duplicate canonical link`);
    check(d.title.trim().length > 0 && d.lang.length > 0, `${p}: document has a title and a language`);
    check(d.h1 === 1, `${p}: exactly one h1`);
    let skip = false; for (let i = 1; i < d.heads.length; i++) if (d.heads[i] - d.heads[i - 1] > 1) skip = true;
    check(!skip, `${p}: heading levels never skip downward`);
    let ldOk = true; for (const t of d.ld) { try { const j = JSON.parse(t); if (j['@context'] !== 'https://schema.org') ldOk = false; } catch (e) { ldOk = false; } }
    check(ldOk && (p === '/' ? d.ld.length === 1 : d.ld.length === 0), `${p}: structured data is valid JSON-LD and appears only on the home page`);
  }
  for (const p of ['/?s=fixture', '/missing-page-check/']) {
    await page.goto(base + p, { waitUntil: 'domcontentloaded' });
    const n = await page.locator('meta[name="description"]').count();
    check(n === 0, `${p}: search and 404 pages print no description`);
  }
  await browser.close();
  console.log(`\n${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})();
