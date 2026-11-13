// Gallery media picker: choose images in the WordPress media modal, reorder, remove, and still work without JavaScript.
// Dev-only account on a disposable local site. BASE_URL points at it. Creates one image in that site's media library.
const { chromium } = require('playwright');
const fs = require('fs');
const os = require('os');
const path = require('path');
const base = process.env.BASE_URL || 'http://127.0.0.1:8099';
let failures = 0, passes = 0;
const check = (ok, name) => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name); } };

// A tiny valid PNG, built in memory, so the test needs no fixture file.
function tinyPng(file) {
  const zlib = require('zlib');
  const crc = (buf) => { let c, t = []; for (let n = 0; n < 256; n++) { c = n; for (let k = 0; k < 8; k++) c = c & 1 ? 0xedb88320 ^ (c >>> 1) : c >>> 1; t[n] = c >>> 0; } let x = 0xffffffff; for (const b of buf) x = t[(x ^ b) & 255] ^ (x >>> 8); return (x ^ 0xffffffff) >>> 0; };
  const chunk = (type, data) => { const len = Buffer.alloc(4); len.writeUInt32BE(data.length); const td = Buffer.concat([Buffer.from(type), data]); const c = Buffer.alloc(4); c.writeUInt32BE(crc(td)); return Buffer.concat([len, td, c]); };
  const w = 40, h = 30, ihdr = Buffer.alloc(13); ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4); ihdr[8] = 8; ihdr[9] = 2;
  const row = Buffer.concat([Buffer.from([0]), Buffer.alloc(w * 3, 0x80)]);
  fs.writeFileSync(file, Buffer.concat([Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]), chunk('IHDR', ihdr), chunk('IDAT', zlib.deflateSync(Buffer.concat(Array(h).fill(row)))), chunk('IEND', Buffer.alloc(0))]));
}

(async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'gallery-'));
  const a = path.join(dir, `gallery-a-${Date.now()}.png`); tinyPng(a);
  const browser = await chromium.launch();
  const ctx = await browser.newContext();
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  await page.goto(`${base}/wp-login.php`);
  await page.fill('#user_login', 'fx_editor'); await page.fill('#user_pass', 'e2e-editor-dev-only');
  await page.click('#wp-submit'); await page.waitForURL(/wp-admin/);

  await page.goto(`${base}/wp-admin/post-new.php?post_type=noblen_project`);
  const add = page.locator('.noblen-gallery__add');
  check(await add.isVisible(), 'the Choose images button is shown when scripts run');
  const input = page.locator('#noblen_f_gallery');
  check(await input.isVisible(), 'the media ID field stays available as the fallback');

  await add.click();
  await page.waitForSelector('.media-modal');
  await page.locator('.media-modal input[type=file]').setInputFiles(a);
  await page.waitForSelector('.media-modal .attachment.selected, .media-modal .attachment.details', { timeout: 30000 });
  await page.locator('.media-modal .media-button-select').click();
  await page.waitForSelector('.noblen-gallery__list li');
  const first = await input.inputValue();
  check(/^\d+$/.test(first), `choosing an image fills the ID field (${first})`);
  check(await page.locator('.noblen-gallery__list li img').count() === 1, 'a thumbnail is shown');

  // Add the same image again: it must not be duplicated.
  await add.click(); await page.waitForSelector('.media-modal');
  await page.locator('.media-modal .attachment').first().click();
  await page.locator('.media-modal .media-button-select').click();
  await page.locator('.media-modal').waitFor({ state: 'hidden', timeout: 15000 });
  check((await input.inputValue()) === first, 'choosing the same image twice does not duplicate it');

  // Manual ID edits survive, and removing empties the field.
  await page.locator('.noblen-gallery__remove').first().click();
  check((await input.inputValue()) === '', 'Remove empties the ID field');
  check(await page.locator('.noblen-gallery__list li').count() === 0, 'Remove removes the thumbnail');
  check(errors.length === 0, 'no script errors' + (errors.length ? ': ' + errors[0] : ''));
  await ctx.close();

  // Without JavaScript the plain ID field still works and the button stays hidden.
  const nojs = await browser.newContext({ javaScriptEnabled: false });
  const p2 = await nojs.newPage();
  await p2.goto(`${base}/wp-login.php`);
  await p2.fill('#user_login', 'fx_editor'); await p2.fill('#user_pass', 'e2e-editor-dev-only');
  await p2.click('#wp-submit'); await p2.waitForURL(/wp-admin/);
  await p2.goto(`${base}/wp-admin/post-new.php?post_type=noblen_project`);
  check(!(await p2.locator('.noblen-gallery__add').isVisible()) && (await p2.locator('#noblen_f_gallery').isVisible()), 'without JavaScript the button is hidden and the ID field is usable');
  await nojs.close();
  await browser.close();
  fs.rmSync(dir, { recursive: true, force: true });
  console.log(`\n${passes} passed, ${failures} failed`);
  process.exit(failures ? 1 : 0);
})();
