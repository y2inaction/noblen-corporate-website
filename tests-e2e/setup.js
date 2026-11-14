// Website Setup in a real browser: the screen, the Prepare and Activate actions with their nonces, repeat safety, the public site staying
// unchanged until activation, the capability gate, and axe on the screen. Dev-only accounts on a disposable site.
// It only adds drafts, empty menus and name-only draft records; it deletes nothing. Run it after the content-type suites.
const H = require('./brand-lib-e2e.js');
const AxeBuilder = require('@axe-core/playwright').default;
const { chromium, base } = H;
const R = H.reporter();
const SETUP = `${base}/wp-admin/admin.php?page=noblen-setup`;

(async () => {
  const browser = await chromium.launch();
  const { ctx, page } = await H.login(browser, 'admin', 'e2e-admin-dev-only');
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));

  await page.goto(SETUP);
  R.check((await page.title()).includes('Website Setup'), 'the screen opens for an administrator');
  R.check(await page.locator('h1.screen-reader-text', { hasText: 'Website Setup' }).count() === 1, 'one page heading, before the banner (notices stay readable)');
  const intro = await page.locator('.noblen-setup .notice', { hasText: 'does not create a finished website' }).first().innerText();
  R.check(/does not create a finished website/i.test(intro) && /drafts/i.test(intro), 'the screen says that installing alone does not create a finished website');
  R.check(await page.locator('text=Step 1. Prepare').count() > 0 && await page.locator('text=Step 2. Activate').count() > 0, 'both stages are explained');
  R.check(await page.locator('.noblen-legend .noblen-badge').count() === 4, 'the four content states are distinguished in the legend');

  // Capture the public state before preparing.
  const pub = await browser.newContext();
  const pubPage = await pub.newPage();
  const before = { home: await (await pubPage.request.get(`${base}/`)).text(), about: (await pubPage.request.get(`${base}/about/`)).status() };

  // Prepare.
  await page.locator('button:has-text("Prepare website structure"), input[value="Prepare website structure"], input[value="Run preparation again"]').first().click();
  await page.waitForURL(/page=noblen-setup/);
  const result = await page.locator('.notice-success').first().innerText();
  R.check(/Preparation finished/.test(result) && /not a sign that the website is complete/i.test(result), 'preparation reports a record of what happened, not a finished site');
  const pagesTable = await page.locator('table').first().innerText();
  for (const t of ['About Noblen', 'Partnerships', 'Contact', 'Terms and Conditions']) { R.check(pagesTable.includes(t), `page listed: ${t}`); }
  R.check(await page.locator('text=Install the complete website').count() >= 1 || await page.locator('text=Install or complete the website').count() >= 1, 'the one-step website installation is offered');
  R.check(await page.locator('text=Record archives').count() >= 1, 'record archives are explained as filling when records are approved');
  R.check(await page.locator('.noblen-chip').count() > 10, 'the proposed menu entries are previewed');

  // Public site unchanged by preparation.
  const after = { home: await (await pubPage.request.get(`${base}/`)).text(), about: (await pubPage.request.get(`${base}/about/`)).status() };
  R.check(after.about === before.about && after.about !== 200, 'a prepared page is not public');
  R.check(!/Partnerships<\/a>/.test(after.home) || /Partnerships<\/a>/.test(before.home), 'no new navigation link appeared on the public site');

  // A second run creates nothing new.
  const countBefore = await page.locator('.noblen-badge--generated_draft').count();
  await page.locator('input[value="Run preparation again"]').click();
  await page.waitForURL(/page=noblen-setup/);
  const second = await page.locator('.notice-success').first().innerText();
  R.check(!/Created: [1-9]/.test(second), 'a second preparation creates nothing', second.slice(0, 120));
  R.check(await page.locator('.noblen-badge--generated_draft').count() === countBefore, 'no duplicate items after a second run');

  // The activation form: a page with placeholders cannot be selected, and an empty activation changes nothing public.
  const legalBox = page.locator('input[name="publish[privacy]"]');
  R.check(await legalBox.count() === 1 && await legalBox.isDisabled(), 'a legal page that still has a placeholder cannot be selected for publication');
  await page.locator('input[name="confirm_review"]').check();
  await page.locator('input[value="Activate selected items"]').click();
  await page.waitForURL(/page=noblen-setup/);
  R.check(/Activation finished/.test(await page.locator('.notice-success').first().innerText()), 'activation with nothing selected finishes without publishing anything');
  R.check((await pubPage.request.get(`${base}/about/`)).status() !== 200, 'About is still not public');

  // Activation needs the review confirmation.
  await page.locator('input[value="Activate selected items"]').click();
  await page.waitForURL(/page=noblen-setup/);
  R.check(await page.locator('.notice-error').count() === 1, 'activation without the review confirmation is refused');

  // Nonce and capability protection on the endpoints.
  const noNonce = await page.request.post(`${base}/wp-admin/admin-post.php`, { form: { action: 'noblen_setup_prepare' }, maxRedirects: 0 });
  R.check(noNonce.status() === 403, 'prepare without a nonce is refused (403)', String(noNonce.status()));
  const badNonce = await page.request.post(`${base}/wp-admin/admin-post.php`, { form: { action: 'noblen_setup_activate', _wpnonce: 'bad' }, maxRedirects: 0 });
  R.check(badNonce.status() === 403, 'activate with a bad nonce is refused (403)', String(badNonce.status()));
  const anon = await pubPage.request.post(`${base}/wp-admin/admin-post.php`, { form: { action: 'noblen_setup_prepare' }, maxRedirects: 0 });
  R.check(anon.status() !== 200 && anon.status() !== 302 || /login/.test(anon.headers().location || ''), 'a visitor cannot run the setup', String(anon.status()));

  // Accessibility of the screen, desktop and mobile.
  for (const [w, h] of [[1280, 900], [390, 800]]) {
    await page.setViewportSize({ width: w, height: h });
    await page.goto(SETUP);
    const r = await new AxeBuilder({ page }).include('.noblen-setup').withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']).analyze();
    R.check(r.violations.length === 0, `axe ${w}px: no violations on the setup screen`, r.violations.map((v) => `${v.id}[${v.nodes.length}]`).join(', '));
  }
  await page.setViewportSize({ width: 1400, height: 900 });
  const horizontal = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
  R.check(errors.length === 0, 'no JavaScript errors on the screen', errors.join('; '));
  R.check(horizontal <= 1, 'no horizontal scroll at desktop width');

  // An editor cannot open it.
  const ed = await H.login(browser, 'fx_editor', 'e2e-editor-dev-only');
  const resp = await ed.page.goto(SETUP);
  R.check(resp && resp.status() === 403, 'an editor is refused (403)', String(resp && resp.status()));
  await ed.ctx.close();
  await ctx.close();
  await pub.close();
  await browser.close();
  R.done();
})().catch((e) => { console.log('FAIL (exception)', e.message); process.exit(1); });
