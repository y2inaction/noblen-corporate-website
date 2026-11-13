// Logo management and logo-based palette suggestions in a real browser: uploads (raster and SVG, REST and admin routes), hostile SVG,
// replacement and removal, aspect ratios, sizes on desktop and mobile, alternative text, the fallback, dark-mode logo, suggestions,
// "nothing changes until Publish", and axe contrast checks on real presets. Dev-only accounts on a disposable local site.
// It deletes what it uploads and restores the default palette at the end.
const zlib = require('zlib');
const H = require('./brand-lib-e2e.js');
const AxeBuilder = require('@axe-core/playwright').default;
const { lib, def } = H;
const R = H.reporter();

// ---- Fixtures built in memory.
function crc32(buf) { let x = 0xffffffff; for (const b of buf) { x ^= b; for (let k = 0; k < 8; k++) x = x & 1 ? 0xedb88320 ^ (x >>> 1) : x >>> 1; } return (x ^ 0xffffffff) >>> 0; }
function chunk(type, data) { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc32(td)); return Buffer.concat([len, td, c]); }
function png(w, h, paint) {
  const ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 6;
  const rows = [];
  for (let y = 0; y < h; y++) { const row = Buffer.alloc(1 + w * 4); for (let x = 0; x < w; x++) { const [r, g, b, a] = paint(x, y); row.set([r, g, b, a], 1 + x * 4); } rows.push(row); }
  return Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(Buffer.concat(rows))), chunk('IEND', Buffer.alloc(0))]);
}
const NAVY = [10, 27, 61, 255], BLUE = [31, 107, 255, 255], GREEN = [46, 139, 58, 255], CLEAR = [0, 0, 0, 0];
const threeColour = (w, h) => png(w, h, (x, y) => (x < 4 || y < 4 || x >= w - 4 || y >= h - 4) ? CLEAR : (x < w * 0.5 ? NAVY : (x < w * 0.8 ? BLUE : GREEN)));
const stamp = Date.now();
const fixtures = {
  wide: { name: `e2e-logo-wide-${stamp}.png`, mime: 'image/png', data: threeColour(400, 100), w: 400, h: 100 },
  square: { name: `e2e-logo-square-${stamp}.png`, mime: 'image/png', data: threeColour(120, 120), w: 120, h: 120 },
  tall: { name: `e2e-logo-tall-${stamp}.png`, mime: 'image/png', data: threeColour(60, 200), w: 60, h: 200 },
  huge: { name: `e2e-logo-huge-${stamp}.png`, mime: 'image/png', data: threeColour(1200, 100), w: 1200, h: 100 },
  svg: { name: `e2e-logo-${stamp}.svg`, mime: 'image/svg+xml', data: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 300 80"><rect width="300" height="80" fill="#0a1b3d"/><circle cx="40" cy="40" r="28" fill="#1f6bff"/><rect x="90" y="30" width="180" height="20" fill="#2e8b3a"/></svg>'), w: 300, h: 80 },
  evil: { name: `e2e-evil-${stamp}.svg`, mime: 'image/svg+xml', data: Buffer.from('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 40" onload="alert(1)"><script>alert(document.cookie)</script><foreignObject width="10" height="10"><body xmlns="http://www.w3.org/1999/xhtml" onload="alert(2)"/></foreignObject><a href="javascript:alert(3)"><rect width="100" height="40" fill="#0a1b3d" onclick="alert(4)"/></a><image href="http://evil.example/p.png"/></svg>') },
  xxe: { name: `e2e-xxe-${stamp}.svg`, mime: 'image/svg+xml', data: Buffer.from('<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><text>&x;</text></svg>') },
  jpeg: { name: `e2e-logo-${stamp}.jpg`, mime: 'image/jpeg', data: Buffer.from('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=', 'base64') },
};
const uploaded = [];

async function restNonce(page) { return page.evaluate(async (b) => (await (await fetch(`${b}/wp-admin/admin-ajax.php?action=rest-nonce`, { credentials: 'include' })).text()).trim(), H.base); }
async function restUpload(page, nonce, f) {
  return page.evaluate(async ([b, n, name, mime, b64]) => {
    const bytes = Uint8Array.from(atob(b64), (c) => c.charCodeAt(0));
    const r = await fetch(`${b}/wp-json/wp/v2/media`, { method: 'POST', credentials: 'include', headers: { 'X-WP-Nonce': n, 'Content-Disposition': `attachment; filename="${name}"`, 'Content-Type': mime }, body: bytes });
    let j = null; try { j = await r.json(); } catch (e) { j = null; }
    return { status: r.status, id: j && j.id, url: j && j.source_url, message: j && j.message, mime: j && j.mime_type };
  }, [H.base, nonce, f.name, f.mime, f.data.toString('base64')]);
}
async function restDelete(page, nonce, id) { await page.evaluate(([b, n, i]) => fetch(`${b}/wp-json/wp/v2/media/${i}?force=true`, { method: 'DELETE', credentials: 'include', headers: { 'X-WP-Nonce': n } }), [H.base, nonce, id]); }
async function reloadPreview(page) { await page.waitForTimeout(2500); await page.waitForFunction(() => { try { return !!wp.customize.previewer.targetWindow().document.querySelector('.site-header'); } catch (e) { return false; } }, null, { timeout: 30000 }); }

(async () => {
  const browser = await H.chromium.launch();
  const { ctx, page } = await H.login(browser, 'admin', 'e2e-admin-dev-only');
  await H.openCustomizer(page);
  await H.resetAll(page);
  await H.openCustomizer(page);
  const nonce = await restNonce(page);
  const pf = () => H.previewFrame(page);
  const logoBox = () => pf().evaluate(() => { const i = document.querySelector('.brand__logo--light'); if (!i) { return null; } const r = i.getBoundingClientRect(); const c = getComputedStyle(i); return { w: r.width, h: r.height, nw: i.naturalWidth, nh: i.naturalHeight, fit: c.objectFit, alt: i.getAttribute('alt'), display: c.display, src: i.getAttribute('src') }; });

  // ---- Uploads.
  for (const key of ['wide', 'square', 'tall', 'huge', 'svg', 'jpeg']) {
    const r = await restUpload(page, nonce, fixtures[key]);
    R.check(r.status === 201 && r.id, `upload ${key} (${fixtures[key].name}) is accepted`, JSON.stringify(r));
    fixtures[key].id = r.id; fixtures[key].url = r.url; if (r.id) { uploaded.push(r.id); }
  }
  const evil = await restUpload(page, nonce, fixtures.evil);
  R.check(evil.status === 201 && evil.id, 'a hostile SVG uploaded through the REST API is accepted only after being cleaned', JSON.stringify(evil));
  if (evil.id) { uploaded.push(evil.id); }
  const evilText = evil.url ? await page.evaluate(async (u) => (await fetch(u)).text(), evil.url) : '';
  R.check(evilText.length > 0 && !/<script|onload|onclick|foreignObject|javascript:|evil\.example|<image/i.test(evilText) && /<rect/.test(evilText), 'the stored hostile SVG contains no script, event handler, foreignObject, javascript: link or external image', evilText.slice(0, 160));
  const xxe = await restUpload(page, nonce, fixtures.xxe);
  R.check(xxe.status >= 400 && !xxe.id && /not uploaded/i.test(xxe.message || ''), 'an SVG with an entity declaration is refused with a plain message', JSON.stringify(xxe));
  // The admin media route (used by the Customizer's uploader) is filtered too.
  const adminRoute = await page.evaluate(async ([b, name, b64]) => {
    const s = (window._wpPluploadSettings && _wpPluploadSettings.defaults && _wpPluploadSettings.defaults.multipart_params) || {};
    const fd = new FormData(); fd.append('name', name); fd.append('action', 'upload-attachment'); fd.append('_wpnonce', s._wpnonce || '');
    fd.append('async-upload', new Blob([Uint8Array.from(atob(b64), (c) => c.charCodeAt(0))], { type: 'image/svg+xml' }), name);
    const r = await fetch(`${b}/wp-admin/async-upload.php`, { method: 'POST', credentials: 'include', body: fd });
    let j = null; try { j = await r.json(); } catch (e) { j = null; } return { status: r.status, j };
  }, [H.base, `e2e-admin-evil-${stamp}.svg`, fixtures.evil.data.toString('base64')]);
  if (adminRoute.j && adminRoute.j.data && adminRoute.j.data.id) { uploaded.push(adminRoute.j.data.id); }
  const adminUrl = adminRoute.j && adminRoute.j.data && adminRoute.j.data.url;
  const adminText = adminUrl ? await page.evaluate(async (u) => (await fetch(u)).text(), adminUrl) : '';
  R.check(!!adminUrl && !/<script|onload|onclick|foreignObject|javascript:/i.test(adminText), 'the admin media uploader also stores only the cleaned SVG', JSON.stringify(adminRoute).slice(0, 200));

  // ---- Editors cannot upload SVG.
  const ed = await H.login(browser, 'fx_editor', 'e2e-editor-dev-only');
  await ed.page.goto(`${H.base}/wp-admin/index.php`);
  const edNonce = await restNonce(ed.page);
  const edUp = await restUpload(ed.page, edNonce, fixtures.svg);
  R.check(edUp.status >= 400 && !edUp.id, 'a user without permission to edit theme options cannot upload an SVG', JSON.stringify(edUp));
  const edPng = await restUpload(ed.page, edNonce, fixtures.tall);
  R.check(edPng.status === 201, 'the same user can still upload ordinary images');
  if (edPng.id) { uploaded.push(edPng.id); }
  await ed.ctx.close();

  // ---- Fallback before any logo is set.
  R.check((await pf().evaluate(() => !!document.querySelector('.brand__text') && !document.querySelector('.brand__logo'))), 'with no logo configured, the header shows the text wordmark');
  const nameLink = await pf().evaluate(() => document.querySelector('a.brand').textContent.trim());
  R.check(nameLink.length > 0, 'the wordmark fallback has a readable name');

  // ---- Each logo: shown, aspect ratio preserved, sized by the height setting.
  const paletteBefore = JSON.stringify(await page.evaluate((l) => l.map((i) => wp.customize(i).get()), def.tokens.map((t) => 'noblen_brand_' + t.key)));
  for (const key of ['wide', 'square', 'tall', 'svg']) {
    await H.setting(page, 'custom_logo', fixtures[key].id); await reloadPreview(page);
    const b = await logoBox();
    R.check(!!b && b.nw > 0 && b.nh > 0, `${key}: the logo image loads`, JSON.stringify(b));
    if (!b) { continue; }
    R.check(Math.abs(b.h - 48) <= 1, `${key}: rendered height follows the desktop height setting (${b.h}px)`);
    const nat = b.nw / b.nh, shown = b.w / b.h;
    R.check(Math.abs(nat - shown) / nat < 0.02, `${key}: aspect ratio preserved (natural ${nat.toFixed(2)}, shown ${shown.toFixed(2)})`);
    R.check(b.fit === 'contain', `${key}: the image is contained, never stretched`);
  }
  R.check(JSON.stringify(await page.evaluate((l) => l.map((i) => wp.customize(i).get()), def.tokens.map((t) => 'noblen_brand_' + t.key))) === paletteBefore, 'choosing and replacing logos never changes the site palette');
  await H.setting(page, 'custom_logo', fixtures.huge.id); await reloadPreview(page);
  const hb = await logoBox();
  R.check(!!hb && hb.w <= 320.5 && hb.fit === 'contain', `a very wide logo is capped at 320px wide and contained (${hb && hb.w}px)`);

  // ---- Alternative text: custom, then Media Library text, then company name.
  await H.setting(page, 'custom_logo', fixtures.wide.id); await H.setting(page, 'noblen_logo_alt', 'Noblen Technologies Limited logo'); await reloadPreview(page);
  R.check((await logoBox()).alt === 'Noblen Technologies Limited logo', 'the administrator\'s alternative text is used');
  await H.setting(page, 'noblen_logo_alt', ''); 
  await page.evaluate(async ([b, n, i]) => { await fetch(`${b}/wp-json/wp/v2/media/${i}`, { method: 'POST', credentials: 'include', headers: { 'X-WP-Nonce': n, 'Content-Type': 'application/json' }, body: JSON.stringify({ alt_text: 'Library alt text' }) }); }, [H.base, nonce, fixtures.wide.id]);
  await reloadPreview(page); await page.evaluate(() => wp.customize.previewer.refresh()); await reloadPreview(page);
  R.check((await logoBox()).alt === 'Library alt text', 'with no custom text, the Media Library alternative text is used');
  await page.evaluate(async ([b, n, i]) => { await fetch(`${b}/wp-json/wp/v2/media/${i}`, { method: 'POST', credentials: 'include', headers: { 'X-WP-Nonce': n, 'Content-Type': 'application/json' }, body: JSON.stringify({ alt_text: '' }) }); }, [H.base, nonce, fixtures.wide.id]);
  await page.evaluate(() => wp.customize.previewer.refresh()); await reloadPreview(page);
  const fallbackAlt = (await logoBox()).alt;
  R.check(!!fallbackAlt && fallbackAlt.trim().length > 0, `with no text anywhere, the company name is used (${fallbackAlt})`);

  // ---- Sizes (live) and bounds.
  await H.setting(page, 'noblen_logo_height', 80); await H.settle(page);
  R.check(Math.abs((await logoBox()).h - 80) <= 1, 'the desktop height setting previews live (80px)');
  await H.setting(page, 'noblen_logo_height', 9999); await H.settle(page);
  R.check((await logoBox()).h <= 120.5, 'an out-of-range height is clamped (at most 120px)');
  await H.setting(page, 'noblen_logo_height', 48);

  // ---- Dark-mode logo.
  await H.setting(page, 'noblen_logo_dark', fixtures.square.id); await H.setting(page, 'noblen_brand_appearance', 'dark'); await H.settle(page); await page.evaluate(() => wp.customize.previewer.refresh()); await reloadPreview(page);
  const darkState = await pf().evaluate(() => { const l = document.querySelector('.brand__logo--light'), d = document.querySelector('.brand__logo--dark'); return { light: l && getComputedStyle(l).display, dark: d && getComputedStyle(d).display, darkAlt: d && d.getAttribute('alt') }; });
  R.check(darkState.light === 'none' && darkState.dark !== 'none' && darkState.darkAlt === '', 'in dark appearance the dark logo replaces the main logo, and is hidden from assistive technology');
  await H.setting(page, 'noblen_brand_appearance', 'light'); await H.settle(page);
  const lightState = await pf().evaluate(() => { const l = document.querySelector('.brand__logo--light'), d = document.querySelector('.brand__logo--dark'); return { light: l && getComputedStyle(l).display, dark: d && getComputedStyle(d).display }; });
  R.check(lightState.light !== 'none' && lightState.dark === 'none', 'in light appearance the main logo is shown');
  await H.setting(page, 'noblen_logo_dark', 0);

  // ---- Publish the logo; a fresh visitor on a phone sees the mobile size and no overflow.
  await H.setting(page, 'custom_logo', fixtures.wide.id); await H.publish(page);
  const mctx = await browser.newContext({ viewport: { width: 375, height: 800 } });
  const mp = await mctx.newPage(); await mp.goto(H.base + '/');
  const mob = await mp.evaluate(() => { const i = document.querySelector('.brand__logo--light'); const r = i.getBoundingClientRect(); return { h: r.height, w: r.width, over: document.documentElement.scrollWidth > document.documentElement.clientWidth, toggle: getComputedStyle(document.querySelector('.nav-toggle')).display }; });
  R.check(Math.abs(mob.h - 36) <= 1 && !mob.over && mob.toggle !== 'none', `published logo on a 375px phone: mobile height ${mob.h}px, no horizontal overflow, menu button present`);
  await mp.click('.nav-toggle');
  const menuBg = await mp.evaluate(() => getComputedStyle(document.getElementById('primary-nav')).backgroundColor);
  R.check(H.toHex(menuBg) === lib.defaults(def).header_bg, 'the mobile menu panel uses the header colour');
  await mctx.close();
  const pubDesktop = await browser.newContext({ viewport: { width: 1280, height: 800 } });
  const dp = await pubDesktop.newPage(); await dp.goto(H.base + '/');
  R.check(Math.abs((await dp.evaluate(() => document.querySelector('.brand__logo--light').getBoundingClientRect().height)) - 48) <= 1, 'published logo on desktop uses the desktop height');
  await pubDesktop.close();

  // ---- Palette suggestions from the logo (wide three-colour PNG is set).
  await H.openCustomizer(page); await H.expandTools(page);
  await page.click('.noblen-suggest');
  await page.waitForSelector('.noblen-suggestion:not([hidden]) .noblen-grid', { timeout: 30000 });
  R.check((await page.locator('.noblen-swatches li').count()) >= 3, 'analysis shows the colours found in the logo');
  R.check((await page.locator('.noblen-grid input[type=color]').count()) === def.tokens.length, 'the suggestion offers every colour for adjustment');
  const sugPrimary = await page.inputValue('#noblen-sug-primary'), sugSecondary = await page.inputValue('#noblen-sug-secondary'), sugAccent = await page.inputValue('#noblen-sug-accent');
  const hue = (h) => { const x = lib.rgb(h).map((c) => c / 255), mx = Math.max(...x), mn = Math.min(...x), d = mx - mn; if (!d) { return 0; } let hh = mx === x[0] ? ((x[1] - x[2]) / d) % 6 : (mx === x[1] ? (x[2] - x[0]) / d + 2 : (x[0] - x[1]) / d + 4); return ((hh * 60) + 360) % 360; };
  R.check(lib.luminance(sugPrimary) < 0.1 && hue(sugPrimary) > 200 && hue(sugPrimary) < 240, `primary is suggested as the logo's navy (${sugPrimary})`);
  R.check(hue(sugSecondary) > 200 && hue(sugSecondary) < 240, `secondary is suggested as the logo's blue (${sugSecondary})`);
  R.check(hue(sugAccent) > 100 && hue(sugAccent) < 160, `accent is suggested as the logo's green (${sugAccent})`);
  R.check(/passes every contrast check/.test(await page.locator('.noblen-sum').innerText()), 'the suggestion reports that it passes every contrast check');
  const defaultsNow = JSON.stringify(await page.evaluate((l) => l.map((i) => wp.customize(i).get()), def.tokens.map((t) => 'noblen_brand_' + t.key)));
  R.check(defaultsNow === paletteBefore, 'analysing the logo does not change the site palette');
  await page.fill('#noblen-sug-accent', '#7a2a8a'); await page.press('#noblen-sug-accent', 'Tab'); await H.settle(page);
  R.check((await page.evaluate(() => getComputedStyle(document.querySelector('.noblen-mini')).getPropertyValue('--brand-accent').trim())) === '#7a2a8a', 'adjusting a suggested colour updates the preview card');
  await page.fill('#noblen-sug-text', '#dddddd'); await page.press('#noblen-sug-text', 'Tab'); await H.settle(page);
  R.check(/warning/.test(await page.locator('.noblen-sum').innerText()), 'a poor manual adjustment raises a warning in the suggestion');
  await page.fill('#noblen-sug-text', '#1a2438'); await page.press('#noblen-sug-text', 'Tab'); await H.settle(page);
  await page.click('.noblen-discard');
  R.check(JSON.stringify(await page.evaluate((l) => l.map((i) => wp.customize(i).get()), def.tokens.map((t) => 'noblen_brand_' + t.key))) === paletteBefore, 'discarding a suggestion changes nothing');
  await page.click('.noblen-suggest'); await page.waitForSelector('.noblen-suggestion:not([hidden]) .noblen-grid', { timeout: 30000 });
  await page.click('.noblen-apply-suggestion'); await H.settle(page);
  R.check((await H.getSetting(page, 'noblen_brand_primary')) === sugPrimary, 'previewing a suggestion applies it to the preview');
  const stillPublic = await H.publicVars(browser, '/');
  R.check(stillPublic['--brand-primary'] === lib.defaults(def).primary, 'the suggestion is not public until Publish');
  await H.publish(page);
  const nowPublic = await H.publicVars(browser, '/');
  R.check(nowPublic['--brand-primary'] === sugPrimary, 'after Publish, visitors see the suggested palette');

  // ---- Real rendered contrast (axe) for presets in light and dark appearance.
  for (const [preset, mode] of [['slate', 'light'], ['noblen-teal', 'light'], ['deep-navy', 'dark'], ['noblen', 'dark']]) {
    await H.openCustomizer(page); await H.expandTools(page);
    await page.selectOption('#noblen-preset-select', 'b:' + preset); await page.click('.noblen-apply-preset');
    await H.setting(page, 'noblen_brand_appearance', mode); await H.publish(page);
    const actx = await browser.newContext(); const ap = await actx.newPage(); await ap.goto(H.base + '/');
    const axe = await new AxeBuilder({ page: ap }).withTags(['wcag2a', 'wcag2aa']).analyze();
    const bad = axe.violations.filter((v) => v.id === 'color-contrast' || v.id === 'link-in-text-block');
    R.check(bad.length === 0, `axe: no contrast violations on the home page with the "${preset}" preset in ${mode} appearance`, JSON.stringify(bad.map((v) => v.nodes.slice(0, 2).map((n) => n.html.slice(0, 80)))));
    await actx.close();
  }

  // ---- Remove the logo and restore everything.
  await H.openCustomizer(page); await H.expandTools(page);
  await H.setting(page, 'custom_logo', 0); await H.setting(page, 'noblen_logo_alt', ''); await H.setting(page, 'noblen_logo_height', 48);
  page.once('dialog', (d) => d.accept()); await page.click('.noblen-restore'); await H.settle(page);
  await H.publish(page);
  const rp = await browser.newPage(); await rp.goto(H.base + '/');
  R.check(await rp.evaluate(() => !!document.querySelector('.brand__text') && !document.querySelector('.brand__logo')), 'after removing the logo, visitors see the text wordmark again');
  await rp.close();
  const clean = await H.publicVars(browser, '/');
  R.check(Object.keys(lib.defaults(def)).every((k) => clean[H.varOf(k)] === lib.defaults(def)[k]), 'the default palette is restored');
  for (const id of uploaded) { await restDelete(page, nonce, id); }
  R.check(true, `cleaned up ${uploaded.length} uploaded files`);
  await ctx.close(); await browser.close();
  R.done();
})().catch((e) => { console.log('FAIL (exception)', e.message.slice(0, 500)); process.exit(1); });
