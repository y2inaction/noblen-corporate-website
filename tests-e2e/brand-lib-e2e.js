// Shared helpers for the brand and logo browser tests.
const { chromium } = require('playwright');
const lib = require('../wordpress-theme/noblen-corporate/assets/js/brand-lib.js');
const def = require('../wordpress-theme/noblen-corporate/assets/brand-defaults.json');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';

function reporter() {
  const r = { passes: 0, failures: 0 };
  r.check = (ok, name, extra) => { if (ok) { r.passes++; console.log('ok  ', name); } else { r.failures++; console.log('FAIL', name, extra === undefined ? '' : extra); } };
  r.done = () => { console.log(`\n${r.passes} passed, ${r.failures} failed`); process.exit(r.failures ? 1 : 0); };
  return r;
}
// "rgb(10, 27, 61)", "rgba(...)" and "color(srgb 0.04 0.1 0.24 / 0.96)" to "#0a1b3d".
function toHex(css) {
  if (!css) { return null; }
  let m = /rgba?\(\s*(\d+)[ ,]+(\d+)[ ,]+(\d+)/.exec(css);
  if (m) { return lib.fromRgb([+m[1], +m[2], +m[3]]); }
  m = /color\(srgb\s+([\d.]+)\s+([\d.]+)\s+([\d.]+)/.exec(css);
  if (m) { return lib.fromRgb([m[1] * 255, m[2] * 255, m[3] * 255]); }
  return null;
}
async function login(browser, user, pass, viewport) {
  const ctx = await browser.newContext({ viewport: viewport || { width: 1400, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(`${base}/wp-login.php`);
  await page.fill('#user_login', user); await page.fill('#user_pass', pass);
  await page.click('#wp-submit'); await page.waitForURL(/wp-admin/);
  return { ctx, page };
}
async function openCustomizer(page) {
  await page.goto(`${base}/wp-admin/customize.php`);
  await page.waitForFunction(() => window.wp && wp.customize && wp.customize.previewer && wp.customize.previewer.targetWindow && wp.customize.previewer.targetWindow() && wp.customize.state && wp.customize.settings, null, { timeout: 60000 });
  await page.waitForFunction(() => { try { return !!wp.customize('noblen_brand_primary') && !!wp.customize.previewer.targetWindow().document.querySelector('.site-header'); } catch (e) { return false; } }, null, { timeout: 60000 });
}
const previewFrame = (page) => page.frames().find((f) => f !== page.mainFrame() && /customize_changeset_uuid|customize_messenger_channel/.test(f.url()));
const setting = (page, id, value) => page.evaluate(([i, v]) => { wp.customize(i).set(v); }, [id, value]);
const getSetting = (page, id) => page.evaluate((i) => wp.customize(i).get(), id);
const publish = (page) => page.evaluate(() => new Promise((res, rej) => { wp.customize.previewer.save().done(res).fail(rej); }));
const settle = (page) => page.waitForTimeout(350);
async function expandTools(page) {
  await page.evaluate(() => { wp.customize.panel('noblen_brand').expand(); });
  await page.evaluate(() => { wp.customize.section('noblen_brand_tools_section').expand(); });
  await page.waitForSelector('.noblen-tools h4', { timeout: 15000 });
}
// Put the site in a known state first (a previous aborted run may have left settings behind): default palette, light, no logo.
async function resetAll(page) {
  await page.evaluate(([defs]) => {
    Object.keys(defs).forEach((k) => wp.customize('noblen_brand_' + k).set(defs[k]));
    wp.customize('noblen_brand_appearance').set('light');
    ['custom_logo', 'noblen_logo_dark'].forEach((i) => wp.customize(i).set(0));
    wp.customize('noblen_logo_alt').set(''); wp.customize('noblen_logo_height').set(48); wp.customize('noblen_logo_height_mobile').set(36);
  }, [lib.defaults(def)]);
  await new Promise((res) => setTimeout(res, 300));
  await page.evaluate(() => new Promise((res, rej) => { wp.customize.previewer.save().done(res).fail(rej); }));
}
// Computed values on a PUBLIC page in a fresh, logged-out context.
async function publicVars(browser, path, extraCtx) {
  const ctx = await browser.newContext(extraCtx || {});
  const page = await ctx.newPage();
  await page.goto(base + (path || '/'));
  const v = await page.evaluate(() => {
    const cs = getComputedStyle(document.documentElement);
    const o = {}; ['--brand-primary', '--brand-secondary', '--brand-accent', '--color-heading', '--color-text', '--color-bg', '--color-surface', '--color-header-bg', '--color-header-text', '--color-footer-bg', '--color-footer-text', '--color-link', '--color-link-hover', '--color-link-active', '--color-button-bg', '--color-button-text', '--color-button-hover', '--color-button-active'].forEach((k) => { o[k] = cs.getPropertyValue(k).trim(); });
    o.appearance = document.documentElement.getAttribute('data-appearance');
    return o;
  });
  await ctx.close();
  return v;
}
const varOf = (key) => (['primary', 'secondary', 'accent'].includes(key) ? '--brand-' : '--color-') + key.replace(/_/g, '-');
module.exports = { chromium, lib, def, base, reporter, toHex, login, openCustomizer, previewFrame, setting, getSetting, publish, settle, expandTools, publicVars, varOf, resetAll };
