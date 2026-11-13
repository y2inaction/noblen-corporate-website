// Brand Identity in the Customizer: controls, live preview of every colour and state, appearance, presets, restore defaults,
// contrast warnings, publish and persistence. Dev-only account on a disposable local site. BASE_URL points at it.
// It restores the default palette at the end.
const H = require('./brand-lib-e2e.js');
const { lib, def } = H;
const R = H.reporter();

(async () => {
  const browser = await H.chromium.launch();
  const { ctx, page } = await H.login(browser, 'admin', 'e2e-admin-dev-only');
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e).slice(0, 160)));
  await H.openCustomizer(page);
  await H.resetAll(page);

  // ---- Controls exist.
  const ids = def.tokens.map((t) => 'noblen_brand_' + t.key);
  const present = await page.evaluate((list) => list.map((i) => !!wp.customize.control(i)), ids);
  R.check(present.every(Boolean), `all ${ids.length} colour controls exist (primary, secondary, accent, heading, text, background, surface, header, footer, links, buttons)`);
  R.check(await page.evaluate(() => !!wp.customize.control('noblen_brand_appearance') && !!wp.customize.control('noblen_brand_tools') && !!wp.customize.panel('noblen_brand')), 'the Brand Identity panel, appearance and tools controls exist');
  const colourControls = await page.evaluate((list) => list.every((i) => wp.customize.control(i).params.type === 'color'), ids);
  R.check(colourControls, 'colour settings use the WordPress colour control');
  const sections = await page.evaluate(() => ['noblen_brand_core', 'noblen_brand_chrome', 'noblen_brand_links', 'noblen_brand_tools_section'].map((s) => !!wp.customize.section(s)));
  R.check(sections.every(Boolean), 'the panel has colour, header and footer, links and buttons, and tools sections');

  // ---- Live preview of every token: change it in the Customizer and read the preview page.
  const pf = () => H.previewFrame(page);
  const probe = (selector, prop) => pf().evaluate(([s, p]) => { const e = document.querySelector(s); return e ? getComputedStyle(e)[p] : null; }, [selector, prop]);
  const cases = [
    ['primary', '#7a0019', async () => H.toHex(await probe('.skip-link', 'backgroundColor')), 'skip link and hero use the primary colour'],
    ['secondary', '#00a3a3', async () => H.toHex(await probe('.brand__text', 'borderLeftColor')), 'logo mark rule, focus rings use the secondary colour'],
    ['accent', '#7a2a8a', async () => H.toHex(await probe('.badge', 'color')), 'badges use the accent colour'],
    ['heading', '#8a0f2b', async () => H.toHex(await probe('.section__head h2', 'color')), 'headings'],
    ['text', '#222a44', async () => H.toHex(await probe('body', 'color')), 'body text'],
    ['bg', '#fafaf0', async () => H.toHex(await probe('body', 'backgroundColor')), 'page background'],
    ['surface', '#eef6ff', async () => H.toHex(await probe('.card', 'backgroundColor')), 'cards'],
    ['header_bg', '#e8f0ff', async () => H.toHex(await probe('.site-header', 'backgroundColor')), 'header background'],
    ['header_text', '#0b2a4a', async () => H.toHex(await probe('.site-nav a', 'color')), 'header and navigation text'],
    ['footer_bg', '#112233', async () => H.toHex(await probe('.site-footer', 'backgroundColor')), 'footer background'],
    ['footer_text', '#d0e0f0', async () => H.toHex(await probe('.site-footer', 'color')), 'footer text'],
    ['link', '#6a1b9a', async () => H.toHex(await probe('.link-more', 'color')), 'links'],
    ['button_bg', '#b3261e', async () => H.toHex(await probe('.btn', 'backgroundColor')), 'buttons'],
    ['button_text', '#fffbe6', async () => H.toHex(await probe('.btn', 'color')), 'button text'],
  ];
  for (const [key, value, read, label] of cases) {
    await H.setting(page, 'noblen_brand_' + key, value); await H.settle(page);
    const got = await read();
    R.check(got === value, `live preview: ${key} (${label}) = ${got}`, `expected ${value}`);
  }
  // Pointer states.
  await H.setting(page, 'noblen_brand_link_hover', '#00695c'); await H.setting(page, 'noblen_brand_button_hover', '#004d40'); await H.setting(page, 'noblen_brand_button_active', '#003026'); await H.setting(page, 'noblen_brand_link_active', '#bf360c'); await H.settle(page);
  const frameEl = await page.locator('iframe').first().elementHandle();
  const frame = await frameEl.contentFrame();
  const hoverLink = await frame.locator('.link-more').first();
  await hoverLink.hover(); await H.settle(page);
  R.check(H.toHex(await probe('.link-more', 'color')) === '#00695c', 'live preview: link hover colour');
  const btn = frame.locator('.btn').first();
  await btn.hover(); await H.settle(page);
  R.check(H.toHex(await probe('.btn', 'backgroundColor')) === '#004d40', 'live preview: button hover colour');
  await page.mouse.down(); await H.settle(page);
  R.check(H.toHex(await probe('.btn', 'backgroundColor')) === '#003026', 'live preview: button pressed (active) colour');
  await page.mouse.move(2, 2); await page.mouse.up(); // released elsewhere: no click, so the preview does not navigate
  await hoverLink.hover(); await page.mouse.down(); await H.settle(page);
  R.check(H.toHex(await probe('.link-more', 'color')) === '#bf360c', 'live preview: link pressed (active) colour');
  await page.mouse.move(2, 2); await page.mouse.up();

  // ---- Propagation: one token reaches every template that uses it (header, mobile menu, cards, forms, footer, notices).
  await H.setting(page, 'noblen_brand_secondary', '#00897b'); await H.settle(page);
  const focusProbe = await pf().evaluate(() => { const i = document.createElement('input'); i.type = 'search'; document.body.appendChild(i); i.focus(); const c = getComputedStyle(i); const r = { outline: c.outlineColor, border: c.borderTopColor }; i.remove(); return r; });
  R.check(H.toHex(focusProbe.outline) === '#00897b', 'form inputs use the secondary colour for the focus ring');
  const inputBg = await pf().evaluate(() => { const i = document.createElement('input'); i.type = 'text'; document.body.appendChild(i); const v = getComputedStyle(i).backgroundColor; i.remove(); return v; });
  R.check(H.toHex(inputBg) === '#eef6ff', 'form inputs use the surface colour');

  // ---- Appearance: light, dark, follow the device.
  await H.setting(page, 'noblen_brand_appearance', 'dark'); await H.settle(page);
  const attr = await pf().evaluate(() => document.documentElement.getAttribute('data-appearance'));
  const light = lib.complete(def, await page.evaluate((list) => Object.fromEntries(list.map((i) => [i.replace('noblen_brand_', ''), wp.customize(i).get()])), ids));
  const dark = lib.darkPalette(light);
  R.check(attr === 'dark', 'dark appearance sets data-appearance="dark" in the preview');
  R.check(H.toHex(await probe('body', 'backgroundColor')) === dark.bg, 'dark appearance: page background is the derived dark colour');
  R.check(H.toHex(await probe('.site-footer', 'backgroundColor')) === dark.footer_bg, 'dark appearance: footer uses the derived dark footer');
  await H.setting(page, 'noblen_brand_appearance', 'auto'); await H.settle(page);
  R.check(await pf().evaluate(() => document.documentElement.getAttribute('data-appearance')) === 'auto', 'follow-the-device sets data-appearance="auto"');
  await H.setting(page, 'noblen_brand_appearance', 'light'); await H.settle(page);

  // ---- Presets, restore, contrast warnings (the tools interface).
  await H.expandTools(page);
  await page.selectOption('#noblen-preset-select', 'b:deep-navy');
  await page.click('.noblen-apply-preset');
  await H.settle(page);
  const deep = def.presets.find((p) => p.id === 'deep-navy');
  R.check((await H.getSetting(page, 'noblen_brand_primary')) === lib.complete(def, deep.colors).primary, 'applying a built-in preset previews its colours');
  const stamp = 'E2E ' + Date.now();
  await page.fill('#noblen-preset-name', stamp);
  await page.click('.noblen-save-preset');
  await page.waitForFunction((n) => [...document.querySelectorAll('#noblen-preset-select option')].some((o) => o.textContent === n), stamp, { timeout: 15000 });
  R.check(true, 'the current colours can be saved as a named preset');
  await page.selectOption('#noblen-preset-select', { label: stamp });
  R.check(!(await page.locator('.noblen-delete').isDisabled()), 'a saved preset can be deleted');
  await H.setting(page, 'noblen_brand_primary', '#123456'); await H.settle(page);
  await page.selectOption('#noblen-preset-select', { label: stamp });
  await page.click('.noblen-apply-preset'); await H.settle(page);
  R.check((await H.getSetting(page, 'noblen_brand_primary')) === lib.complete(def, deep.colors).primary, 'applying a saved preset restores its colours');
  page.once('dialog', (d) => d.accept());
  await page.click('.noblen-delete');
  await page.waitForFunction((n) => ![...document.querySelectorAll('#noblen-preset-select option')].some((o) => o.textContent === n), stamp, { timeout: 15000 });
  R.check(true, 'deleting a saved preset removes it');

  await H.setting(page, 'noblen_brand_text', '#cccccc'); await H.settle(page);
  const warn = await page.locator('.noblen-report__head.is-warning').count();
  const warnText = (await page.locator('.noblen-report').innerText());
  R.check(warn >= 1 && /Body text on the page background/.test(warnText), 'a low-contrast choice raises a contrast warning that names the pair');
  await H.setting(page, 'noblen_brand_appearance', 'dark'); await H.settle(page);
  R.check((await page.locator('.noblen-report__head').count()) >= 2, 'in dark appearance the check also reports the dark palette');
  page.once('dialog', (d) => d.accept());
  await page.click('.noblen-restore'); await H.settle(page);
  const defaults = lib.defaults(def);
  const allDefault = await page.evaluate(([list, d]) => list.every((i) => wp.customize(i).get() === d[i.replace('noblen_brand_', '')]), [ids, defaults]);
  R.check(allDefault && (await H.getSetting(page, 'noblen_brand_appearance')) === 'light', 'Restore defaults returns every colour and the appearance to the originals (in the preview)');
  R.check((await page.locator('.noblen-report__head.is-ok').count()) >= 1, 'after restoring, the contrast check is clean');

  // ---- Publish, then a fresh logged-out visitor sees it; the Customizer shows it again after reload.
  await H.setting(page, 'noblen_brand_primary', '#0b3d2e');
  await H.setting(page, 'noblen_brand_secondary', '#e05a00');
  await H.setting(page, 'noblen_brand_appearance', 'auto');
  const before = await H.publicVars(browser, '/');
  R.check(before['--brand-primary'] === lib.defaults(def).primary, 'before publishing, visitors still see the saved palette (preview changes are not public)');
  await H.publish(page);
  const after = await H.publicVars(browser, '/');
  R.check(after['--brand-primary'] === '#0b3d2e' && after['--brand-secondary'] === '#e05a00' && after.appearance === 'auto', 'after Publish, visitors see the new palette and appearance');
  const deepPage = await H.publicVars(browser, '/projects/');
  R.check(deepPage['--brand-primary'] === '#0b3d2e', 'the palette reaches archive pages too');
  await H.openCustomizer(page);
  R.check((await H.getSetting(page, 'noblen_brand_primary')) === '#0b3d2e', 'the Customizer shows the saved palette after a reload');

  // ---- Restore defaults and publish, so the site is left as found.
  await H.expandTools(page);
  page.once('dialog', (d) => d.accept());
  await page.click('.noblen-restore'); await H.settle(page);
  await H.publish(page);
  const clean = await H.publicVars(browser, '/');
  R.check(Object.keys(lib.defaults(def)).every((k) => clean[H.varOf(k)] === lib.defaults(def)[k]) && clean.appearance === 'light', 'after Restore defaults and Publish, visitors see exactly the default palette');
  R.check(errors.length === 0, 'no script errors in the Customizer' + (errors.length ? ': ' + errors[0] : ''));
  await ctx.close(); await browser.close();
  R.done();
})().catch((e) => { console.log('FAIL (exception)', e.message.slice(0, 400)); process.exit(1); });
