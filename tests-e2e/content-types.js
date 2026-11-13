// C06-001 and C06-002: every Noblen content type (project, product, company, industry, service) can be CREATED and
// EDITED through the real WordPress admin by a content editor, and the structured fields persist.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const stamp = Date.now();
let failures = 0, passes = 0;
const check = (ok, name, extra = '') => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name, extra); } };
const clearLocks = () => { try { execSync(process.env.CLEAR_LOCKS_CMD || `mysql -e "DELETE FROM noblen_test.wp_postmeta WHERE meta_key='_edit_lock'"`); } catch (e) { /* best effort */ } };

const TYPES = [
  ['noblen_project', 'C06-001', 'project'], ['noblen_product', 'C06-001', 'product'],
  ['noblen_company', 'C06-002', 'company'], ['noblen_industry', 'C06-002', 'industry'], ['noblen_service', 'C06-002', 'service'],
];

let retries = 0;
// Save Draft. WordPress ignores a click that lands while its permalink or autosave request is in flight, as a person
// would notice and click again. So: wait for the in-flight request, click, and if the page has not reloaded, click once
// more WITHOUT reloading (reloading would discard the typed fields). Every re-click is logged and counted.
async function saveDraft(page) {
  await page.waitForLoadState('networkidle').catch(() => {});
  for (let attempt = 1; attempt <= 3; attempt++) {
    try { await Promise.all([page.waitForNavigation({ timeout: 15000 }), page.click('#save-post')]); return; }
    catch (e) { if (attempt === 3) throw e; retries++; console.log('note: Save Draft click was ignored, clicking again (#' + retries + ')'); await page.waitForLoadState('networkidle').catch(() => {}); }
  }
}
async function fillFields(page, round) {
  const filled = {};
  for (const el of await page.locator('[id^="noblen_f_"]').all()) {
    const id = await el.getAttribute('id');
    if (id.endsWith('_approved') || id === 'noblen_f_gallery' || id === 'noblen_f_featured_home') continue;
    const tag = await el.evaluate((n) => n.tagName.toLowerCase());
    const type = await el.getAttribute('type');
    if (tag === 'textarea') { filled[id] = `Round ${round} text for ${id} ${stamp}`; await el.fill(filled[id]); }
    else if (tag === 'select') {
      const multiple = await el.getAttribute('multiple');
      const opts = await el.locator('option').evaluateAll((os) => os.map((o) => ({ v: o.value, t: o.textContent })));
      const pick = opts.find((o) => o.v && /Dev Fixture/.test(o.t)) || opts.find((o) => o.v);
      if (pick) { await el.selectOption(pick.v); filled[id] = [pick.v]; }
    } else if (type === 'text') {
      filled[id] = id === 'noblen_f_video_url' ? 'https://example.test/video' : `Round ${round} ${id} ${stamp}`;
      await el.fill(filled[id]);
    }
  }
  return filled;
}
async function valueOf(page, id) {
  const el = page.locator('#' + id);
  const tag = await el.evaluate((n) => n.tagName.toLowerCase());
  if (tag === 'select') return (await el.evaluate((n) => [...n.selectedOptions].map((o) => o.value)));
  return el.inputValue();
}
async function setBody(page, text) {
  if (await page.locator('#content').count()) {
    if (await page.locator('#content-html').count()) await page.click('#content-html');
    await page.fill('#content', text);
  }
}

(async () => {
  const browser = await chromium.launch();
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept()); // WordPress may ask to confirm leaving a changed form
  await page.goto(`${base}/wp-login.php`);
  await page.fill('#user_login', 'fx_editor'); await page.fill('#user_pass', 'e2e-editor-dev-only');
  await page.click('#wp-submit'); await page.waitForURL(/wp-admin/);

  for (const [type, cid, label] of TYPES) {
    const title = `E2E ${label} ${stamp}`;
    await page.goto(`${base}/wp-admin/post-new.php?post_type=${type}`);
    check(await page.locator('form#post').count() === 1 && await page.locator('#noblen_fields').count() === 1, `${cid} ${label}: new-record screen with structured fields opens`);
    await page.fill('#title', title);
    await setBody(page, `Body for ${label} ${stamp}`);
    const first = await fillFields(page, 1);
    await saveDraft(page);
    const id = new URL(page.url()).searchParams.get('post');
    check(!!id, `${cid} ${label}: created as a draft (post ${id})`);
    check(await page.inputValue('#title') === title, `${cid} ${label}: title persisted`);
    let bad = [];
    for (const [fid, want] of Object.entries(first)) {
      const got = await valueOf(page, fid);
      const same = Array.isArray(want) ? JSON.stringify(got) === JSON.stringify(want) : got === want;
      if (!same) bad.push(fid);
    }
    check(bad.length === 0, `${cid} ${label}: ${Object.keys(first).length} structured field(s) persisted after create`, bad.join(','));
    // Edit and save again.
    await page.fill('#title', title + ' edited');
    const second = await fillFields(page, 2);
    await saveDraft(page);
    check(await page.inputValue('#title') === title + ' edited', `${cid} ${label}: title edit persisted`);
    bad = [];
    for (const [fid, want] of Object.entries(second)) {
      const got = await valueOf(page, fid);
      const same = Array.isArray(want) ? JSON.stringify(got) === JSON.stringify(want) : got === want;
      if (!same) bad.push(fid);
    }
    check(bad.length === 0, `${cid} ${label}: edited field(s) persisted after update`, bad.join(','));
    check(!/status:\s*publish/i.test(await page.locator('#noblen_publication').innerText()), `${cid} ${label}: remains unpublished after editor saves`);
    await page.goto(`${base}/wp-admin/edit.php?post_type=${type}`);
    check((await page.locator('table.wp-list-table').innerText()).includes(title + ' edited'), `${cid} ${label}: appears in the admin list`);
    clearLocks();
  }
  check(retries <= 2, `stalled saves retried: ${retries} (at most 2 tolerated)`);
  await browser.close();
  console.log(`${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})();
