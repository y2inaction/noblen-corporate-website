// C12-001: crawl the rendered public site and scan every page for unfinished-placeholder tokens.
// Starts from the home page, the search page, the core sitemap and a 404 address, follows same-site links, and checks
// visible text, the page title and the meta description. WordPress's own default sample content is reported as a warning
// (the owner deletes it before launch); it is not a theme or plugin placeholder.
const { chromium } = require('playwright');
const base = (process.env.BASE_URL || 'http://127.0.0.1:8099').replace(/\/$/, '');
const host = new URL(base).host;
const MAX = Number(process.env.CRAWL_MAX || 300);
const TOKENS = /lorem ipsum|\bTODO\b|\bTBD\b|\bFIXME\b|\[placeholder|\{\{|\}\}|your (company|name|text) here|coming soon|insert (text|image|logo)|click to edit|undefined|\bNaN\b|\[object Object\]/i;
const DEFAULT_CONTENT = /Hello world!|Sample Page|This is an example page|Welcome to WordPress\. This is your first post/;
const SKIP = /\/wp-admin|\/wp-login|\/wp-json|\/feed|\/xmlrpc|\/wp-content\/|\/wp-includes\/|[?&]replytocom|\/embed\/?$|\.(jpg|jpeg|png|gif|svg|webp|pdf|zip|xml)(\?|$)/i;
let failures = 0, warnings = 0;
const fail = (m) => { failures++; console.log('FAIL', m); };
const norm = (u) => { const x = new URL(u, base + '/'); x.hash = ''; return x.host === host ? x.href : null; };

(async () => {
  const seen = new Set(), queue = [];
  const add = (u) => { const n = u && norm(u); if (n && !SKIP.test(n) && !seen.has(n)) { seen.add(n); queue.push(n); } };
  for (const p of ['/', '/?s=a', '/?s=zzzz-no-such-term']) add(base + p);
  // Core sitemap: index, then every sub-sitemap's URLs.
  try {
    const idx = await (await fetch(base + '/wp-sitemap.xml')).text();
    for (const m of idx.matchAll(/<loc>([^<]+)<\/loc>/g)) {
      const sub = await (await fetch(m[1])).text();
      for (const l of sub.matchAll(/<loc>([^<]+)<\/loc>/g)) add(l[1]);
    }
  } catch (e) { console.log('note: sitemap not read:', e.message); }
  if (queue.length <= 3) {
    const msg = 'no sitemap URLs found (PHP built-in server cannot serve .xml routes; Apache in wp-env can)';
    if (process.env.CRAWL_REQUIRE_SITEMAP === '1') fail(msg); else console.log('note:', msg);
  }
  const browser = await chromium.launch();
  const page = await browser.newPage();
  page.setDefaultTimeout(20000);
  const crawled = [];
  while (queue.length && crawled.length < MAX) {
    const url = queue.shift();
    if (process.env.CRAWL_VERBOSE) console.log('visit', url);
    const res = await page.goto(url, { waitUntil: 'load', timeout: 20000 }).catch((e) => { fail(`${url} did not load: ${e.message}`); return null; });
    if (!res) continue;
    if (res.status() >= 400) { fail(`${url} returned ${res.status()}`); continue; }
    crawled.push(url);
    const text = await page.locator('body').innerText();
    const title = await page.title();
    const meta = await page.evaluate(() => (document.querySelector('meta[name="description"]') || {}).content || '');
    for (const [where, s] of [['text', text], ['title', title], ['meta description', meta]]) {
      const m = s.match(TOKENS);
      if (m) fail(`${url}: placeholder token "${m[0]}" in ${where}`);
    }
    if (DEFAULT_CONTENT.test(text)) { warnings++; console.log(`warn ${url}: WordPress default sample content (delete before launch)`); }
    for (const href of await page.$$eval('a[href]', (as) => as.map((a) => a.href))) add(href);
  }
  // A 404 address must render the theme's 404 page, not a placeholder.
  const nf = await page.goto(base + '/definitely-missing-page-for-crawl/');
  if (nf.status() !== 404) fail('missing address did not return 404');
  const nfText = await page.locator('body').innerText();
  if (TOKENS.test(nfText)) fail('404 page contains a placeholder token');
  await browser.close();

  const has = (re) => crawled.some((u) => re.test(u));
  console.log(`crawled ${crawled.length} pages from ${host}`);
  if (crawled.length < 8) fail(`only ${crawled.length} pages crawled; expected at least 8`);
  for (const [re, what] of [[/\/projects\/$/, 'projects archive'], [/\/group\/$/, 'group archive'], [/\/industries\/$/, 'industries archive'], [/\/projects\/[^/]+\/$/, 'a project page'], [/\/group\/[^/]+\/$/, 'a business unit page'], [/\/industries\/[^/]+\/$/, 'an industry page'], [/[?&]s=/, 'a search page']]) {
    if (!has(re)) fail(`crawl did not reach ${what}`);
  }
  console.log(failures ? `${failures} failure(s), ${warnings} warning(s)` : `PASS (${crawled.length} pages, ${warnings} warning(s))`);
  process.exit(failures ? 1 : 0);
})();
