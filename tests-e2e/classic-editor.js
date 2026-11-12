// C21: Classic Editor compatibility. CONFIG=B (default): the Classic Editor plugin is NOT installed or active.
// CONFIG=A: the Classic Editor plugin is active (needs an approved, pinned dependency; see documentation).
// Tests the ACTUAL editor behaviour in a browser, not only stored settings.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const CONFIG = (process.env.CONFIG || 'B').toUpperCase();
const stamp = Date.now();
let failures = 0, passes = 0;
const check = (ok, name, extra = '') => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name, extra); } };
const clearLocks = () => { try { execSync(process.env.CLEAR_LOCKS_CMD || `mysql -e "DELETE FROM noblen_test.wp_postmeta WHERE meta_key='_edit_lock'"`); } catch (e) { /* best effort */ } };
const id = (n) => `C21-${String(n).padStart(3, '0')}`;
const off = CONFIG === 'A' ? 0 : 5; // criterion numbering: A = 001..005, B = 006..010
const TYPES = [['noblen_project', 'project'], ['noblen_product', 'product'], ['noblen_company', 'company'], ['noblen_industry', 'industry'], ['noblen_service', 'service']];

async function login(browser, user, pass) {
  const ctx = await browser.newContext(); const page = await ctx.newPage();
  page.on('dialog', (d) => d.accept());
  await page.goto(`${base}/wp-login.php`); await page.fill('#user_login', user); await page.fill('#user_pass', pass);
  await page.click('#wp-submit'); await page.waitForURL(/wp-admin/);
  return page;
}

(async () => {
  const browser = await chromium.launch();
  const admin = await login(browser, 'admin', 'e2e-admin-dev-only');
  const editor = await login(browser, 'fx_editor', 'e2e-editor-dev-only');

  // Configuration state, observed in the real plugins screen.
  await admin.goto(`${base}/wp-admin/plugins.php`);
  const ce = admin.locator('tr[data-slug="classic-editor"]');
  const ceActive = (await ce.count()) > 0 && /\bactive\b/.test((await ce.first().getAttribute('class')) || '') && !/inactive/.test((await ce.first().getAttribute('class')) || '');
  check(CONFIG === 'A' ? ceActive : !ceActive, `Configuration ${CONFIG}: Classic Editor plugin is ${CONFIG === 'A' ? 'active' : 'not active'}`);
  const noblenActive = (await admin.locator('tr[data-slug="noblen-corporate-core"]').first().getAttribute('class')) || '';
  check(/\bactive\b/.test(noblenActive) && !/inactive/.test(noblenActive), `Configuration ${CONFIG}: Noblen core plugin is active`);

  // Custom types open in the classic editor (no block editor screen), with structured fields.
  for (const [i, [type, label]] of TYPES.entries()) {
    await editor.goto(`${base}/wp-admin/post-new.php?post_type=${type}`);
    const isBlock = await editor.evaluate(() => document.body.classList.contains('block-editor-page'));
    check(!isBlock && await editor.locator('form#post').count() === 1 && await editor.locator('#noblen_fields').count() === 1, `${id(1 + i + off)} ${label} opens in the classic editor (Configuration ${CONFIG})`);
  }

  // REST stays off for the custom types.
  const types = await (await fetch(`${base}/?rest_route=/wp/v2/types`)).json();
  const routes = await (await fetch(`${base}/?rest_route=/`)).json();
  const leaked = Object.keys(types).filter((k) => k.startsWith('noblen')).concat(Object.keys(routes.routes || {}).filter((r) => r.includes('noblen')));
  check(leaked.length === 0, `${id(CONFIG === 'A' ? 11 : 12)} custom types are not exposed through REST (Configuration ${CONFIG})`, leaked.join(','));

  // Structured fields save and validate.
  await editor.goto(`${base}/wp-admin/post-new.php?post_type=noblen_project`);
  await editor.fill('#title', `C21 ${CONFIG} ${stamp}`);
  await editor.fill('#noblen_f_overview', `Overview ${stamp}`);
  await editor.selectOption('#noblen_f_status', 'completed_assignment');
  await Promise.all([editor.waitForNavigation(), editor.click('#save-post')]);
  check(await editor.inputValue('#noblen_f_overview') === `Overview ${stamp}` && await editor.inputValue('#noblen_f_status') === 'completed_assignment', `${id(CONFIG === 'A' ? 13 : 14)} structured fields save (Configuration ${CONFIG})`);
  await editor.fill('#noblen_f_video_url', 'javascript:alert(1)');
  await Promise.all([editor.waitForNavigation(), editor.click('#save-post')]);
  const notices = (await editor.locator('.notice').allInnerTexts()).join(' ');
  check(/valid http or https/i.test(notices) && (await editor.inputValue('#noblen_f_video_url')) === '', `${id(CONFIG === 'A' ? 15 : 16)} invalid input is rejected and not saved (Configuration ${CONFIG})`, notices.slice(0, 120));
  clearLocks();

  // Standard posts are tested as an administrator: the Noblen content-editor role has no standard-post capabilities by design.
  // Standard posts: no Noblen boxes or fields, and they genuinely open in the BLOCK admin.
  await admin.goto(`${base}/wp-admin/post-new.php`);
  check(await admin.locator('#noblen_fields, #noblen_publication, #noblen_clearance').count() === 0, `${id(CONFIG === 'A' ? 17 : 18)} standard post has no Noblen boxes or fields (Configuration ${CONFIG})`);
  const std = await admin.evaluate(() => document.body.classList.contains('block-editor-page'));
  let blockUi = false;
  try { await admin.waitForSelector('.edit-post-layout, .interface-interface-skeleton', { timeout: 30000 }); blockUi = true; } catch (e) { /* reported below */ }
  check(std && blockUi, `${id(CONFIG === 'A' ? 19 : 20)} standard post opens in the block editor (Configuration ${CONFIG})`, `bodyClass=${std} ui=${blockUi}`);
  if (blockUi) {
    await admin.keyboard.press('Escape'); // welcome guide
    const sel = '.editor-post-title__input, h1.editor-post-title, [aria-label="Add title"], .wp-block-post-title';
    const framed = (await admin.locator('iframe[name="editor-canvas"]').count()) > 0;
    const titleBox = (framed ? admin.frameLocator('iframe[name="editor-canvas"]') : admin).locator(sel).first();
    await titleBox.click({ timeout: 30000 }); await admin.keyboard.type(`Block editor post ${stamp}`);
    await admin.locator('button.editor-post-save-draft').first().click();
    await admin.waitForFunction(() => new URL(location.href).searchParams.get('post'), null, { timeout: 30000 }).catch(() => {});
    check(!!new URL(admin.url()).searchParams.get('post'), `${id(CONFIG === 'A' ? 17 : 18)} standard post saves normally from the block editor (Configuration ${CONFIG})`);
  }
  clearLocks();

  // Record of the run (C21-022 content): what was observed.
  console.log('RECORD ' + JSON.stringify({ configuration: CONFIG, classicEditorPluginActive: ceActive, noblenActive: /\bactive\b/.test(noblenActive), wordpress: await admin.evaluate(() => (window.wp && wp.version) || 'n/a').catch(() => 'n/a') }));
  await browser.close();
  console.log(`${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})();
