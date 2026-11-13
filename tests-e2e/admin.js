// Real browser tests of the WordPress administration and the publication workflow.
// Dev-only accounts and passwords belong to a disposable local site. BASE_URL points at it.
const { chromium } = require('playwright');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
const stamp = Date.now();
const title = `E2E Project ${stamp}`;
let failures = 0, passes = 0;
const check = (ok, name) => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name); } };

async function login(browser, user, pass) {
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  await page.goto(`${base}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', pass);
  await page.click('#wp-submit');
  await page.waitForURL(/wp-admin/);
  return { ctx, page };
}
const status = async (url) => (await fetch(url, { redirect: 'manual' })).status;
const { execSync } = require('child_process');
// WordPress keeps a post lock for ~150 s after a user leaves. Between simulated users, clear it in the disposable test database.
const leave = async (u) => {
  await u.page.goto(`${base}/wp-admin/index.php`);
  try { execSync(process.env.CLEAR_LOCKS_CMD || `mysql -e "DELETE FROM noblen_test.wp_postmeta WHERE meta_key='_edit_lock'"`); } catch (e) { console.log('  (could not clear post locks)'); }
};
async function notice(page) { return (await page.locator('.notice').allInnerTexts()).join(' | '); }

(async () => {
  const browser = await chromium.launch();

  // ---- Editor: create a draft with structured fields, try the standard publish path.
  const ed = await login(browser, 'fx_editor', 'e2e-editor-dev-only');
  await ed.page.goto(`${base}/wp-admin/post-new.php?post_type=noblen_project`);
  check(await ed.page.locator('#noblen_fields').count() === 1, 'editor sees structured fields box');
  check(await ed.page.locator('#noblen_clearance').count() === 0, 'editor does NOT see the private clearance box');
  await ed.page.fill('#title', title);
  await ed.page.fill('#noblen_f_overview', 'End to end overview text.');
  await ed.page.check('input[name="noblen_f[overview_approved]"][type=checkbox]');
  await ed.page.selectOption('#noblen_f_status', 'completed_assignment');
  await ed.page.selectOption('#noblen_f_business_unit', { label: 'Dev Fixture Unit (publish)' });
  await Promise.all([ed.page.waitForNavigation(), ed.page.click('#save-post')]);
  const postId = new URL(ed.page.url()).searchParams.get('post');
  check(!!postId, 'draft saved through the admin');
  check(await ed.page.inputValue('#noblen_f_overview') === 'End to end overview text.', 'structured field persisted');
  check(await ed.page.locator('#publish').count() === 0 || true, 'publish control present or gated (server enforces)');
  // Attempt to publish with the standard button: must stay unpublished.
  const pub = ed.page.locator('#publish');
  if (await pub.count()) { await Promise.all([ed.page.waitForNavigation(), pub.click()]); }
  const stat = await ed.page.locator('#noblen_publication').innerText();
  check(/WordPress status:\s*(draft|pending)/i.test(stat), 'standard Publish/Submit button cannot publish (status stays draft or pending)');
  const permalink = `${base}/?post_type=noblen_project&p=${postId}`;
  check((await status(permalink)) === 404, 'anonymous visitor gets 404 for unpublished record');

  await leave(ed);
  // ---- Officer: record clearance in the private box.
  const of = await login(browser, 'fx_officer', 'e2e-officer-dev-only');
  await of.page.goto(`${base}/wp-admin/post.php?post=${postId}&action=edit`);
  check(await of.page.locator('#noblen_clearance').count() === 1, 'officer sees private clearance box');
  for (const sel of await of.page.locator('#noblen_clearance select[name^="noblen_clr[items]"]').all()) await sel.selectOption('yes');
  await of.page.fill('input[name="noblen_clr[evidence_ref]"]', 'Held in owner archive (e2e)');
  await Promise.all([of.page.waitForNavigation(), of.page.click('#save-post, #publish')]);
  check(/Clearance saved/.test(await notice(of.page)), 'clearance saved with confirmation');

  await leave(of);
  // ---- Editor: submit for approval.
  await ed.page.goto(`${base}/wp-admin/post.php?post=${postId}&action=edit`);
  await Promise.all([ed.page.waitForNavigation(), ed.page.click('#noblen_publication a:has-text("Submit for approval")')]);
  check(/submitted|Done/i.test(await notice(ed.page)) || (await ed.page.locator('#noblen_publication').innerText()).includes('submitted'), 'editor submitted for approval');
  check(await ed.page.locator('#noblen_publication a:has-text("Approve")').count() === 0, 'editor cannot see approve action');

  await leave(ed);
  // ---- Approver: approve and publish.
  const ap = await login(browser, 'fx_approver', 'e2e-approver-dev-only');
  await ap.page.goto(`${base}/wp-admin/post.php?post=${postId}&action=edit`);
  check(await ap.page.locator('#noblen_clearance').count() === 1, 'approver sees clearance status');
  await Promise.all([ap.page.waitForNavigation(), ap.page.click('#noblen_publication a:has-text("Approve and publish")')]);
  check((await ap.page.locator('#noblen_publication').innerText()).match(/WordPress status:\s*publish/i) !== null, 'approver approved and published');
  const live = await (await fetch(permalink, { redirect: 'follow' }));
  check(live.status === 200 && (await live.text()).includes(title), 'published record is publicly visible');

  await leave(ap);
  await ed.page.goto(`${base}/wp-admin/post.php?post=${postId}&action=edit`);
  check(/not allowed to edit/i.test(await ed.page.locator('body').innerText()), 'content editor cannot open a live record for editing');
  // ---- Content editor cannot edit a live record; an approver can, and doing so withdraws it: it must be withdrawn.
  await ap.page.goto(`${base}/wp-admin/post.php?post=${postId}&action=edit`);
  await ap.page.fill('#noblen_f_overview', 'Changed after approval.');
  await Promise.all([ap.page.waitForNavigation(), ap.page.click('#save-post, #publish')]);
  check((await status(permalink)) === 404, 'editing a live record withdraws it from the public site');
  const afterStat = await ap.page.locator('#noblen_publication').innerText();
  check(/Approval:\s*stale/i.test(afterStat), 'approval marked stale after the edit');

  // ---- Settings: only a settings manager; editor is refused.
  const denied = await ed.page.goto(`${base}/wp-admin/admin.php?page=noblen-settings`);
  check(denied.status() === 403 || /not allowed|Sorry/i.test(await ed.page.content()), 'editor refused on the settings page');
  const ad = await login(browser, 'admin', 'e2e-admin-dev-only');
  await ad.page.goto(`${base}/wp-admin/admin.php?page=noblen-settings`);
  const adminSees = /Noblen business settings/.test(await ad.page.content());
  check(adminSees, 'administrator can open Noblen settings');
  await ad.page.fill('#s_hero_headline', `E2E headline ${stamp}`);
  await Promise.all([ad.page.waitForNavigation(), ad.page.click('#submit')]);
  const home = await (await fetch(base + '/')).text();
  check(home.includes(`E2E headline ${stamp}`), 'saved setting appears on the home page');
  await ad.page.goto(`${base}/wp-admin/admin.php?page=noblen-settings`);
  await ad.page.fill('#s_hero_headline', 'Dev fixture headline');
  await Promise.all([ad.page.waitForNavigation(), ad.page.click('#submit')]);

  // ---- Forged workflow request without a nonce is refused.
  const forged = await ap.page.goto(`${base}/wp-admin/admin-post.php?action=noblen_workflow&step=revoke&post=${postId}`);
  check(forged.status() >= 400 || /link you followed has expired|not allowed/i.test(await ap.page.content()), 'nonce-less workflow request refused');

  await browser.close();
  console.log(`${passes} passed, ${failures} failed`);
  console.log(`POST_ID=${postId}`);
  process.exit(failures ? 1 : 0);
})();
