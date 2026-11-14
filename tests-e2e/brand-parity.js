// The PHP and JavaScript brand colour code must agree exactly: same palettes, same dark derivation, same CSS, same contrast report.
// Needs only PHP and Node (no WordPress). Usage: node brand-parity.js
const { spawnSync } = require('child_process');
const path = require('path');
const lib = require('../wordpress-theme/noblen-corporate/assets/js/brand-lib.js');
const def = require('../wordpress-theme/noblen-corporate/assets/brand-defaults.json');
let passes = 0, failures = 0;
const check = (ok, name, extra) => { if (ok) { passes++; console.log('ok  ', name); } else { failures++; console.log('FAIL', name, extra || ''); } };

// Deterministic pseudo-random palettes, plus presets and awkward extremes.
let seed = 123456789;
const rnd = () => { seed = (seed * 1664525 + 1013904223) % 4294967296; return seed / 4294967296; };
const randHex = () => '#' + [0, 0, 0].map(() => Math.floor(rnd() * 256).toString(16).padStart(2, '0')).join('');
const samples = [{}, ...def.presets.map((p) => p.colors),
  { primary: '#ffffff', secondary: '#ffffff', accent: '#ffffff', bg: '#000000', surface: '#000000', text: '#ffffff' },
  { primary: '#000000', secondary: '#000000', accent: '#000000' },
  { primary: '#808080', secondary: '#7f7f7f', accent: '#808081', button_bg: '#767676' },
  { primary: '#FFF', secondary: '#abc' }, // short, upper-case forms
  { primary: 'not-a-colour', secondary: 42, accent: null }];
for (let i = 0; i < 150; i++) {
  const s = {}; def.tokens.forEach((t) => { if (rnd() < 0.7) { s[t.key] = randHex(); } });
  samples.push(s);
}
const r = spawnSync('php', [path.join(__dirname, '../tests-cli/brand-export.php')], { input: JSON.stringify(samples), encoding: 'utf8', maxBuffer: 64 * 1024 * 1024 });
if (r.status !== 0) { console.log('FAIL php export', r.stderr.slice(0, 300)); process.exit(1); }
const php = JSON.parse(r.stdout);
check(php.length === samples.length, `PHP returned ${php.length} results for ${samples.length} palettes`);

let bad = { palette: 0, dark: 0, css: 0, report: 0, reportD: 0 }, first = {};
samples.forEach((s, i) => {
  const p = lib.complete(def, s), dark = lib.darkPalette(p);
  const js = { palette: p, dark, css: lib.buildCss(p), report: lib.contrastReport(p), reportD: lib.contrastReport(Object.assign({}, p, dark)) };
  for (const k of Object.keys(bad)) {
    if (JSON.stringify(js[k]) !== JSON.stringify(php[i][k])) { bad[k]++; if (!first[k]) first[k] = { i, js: JSON.stringify(js[k]).slice(0, 200), php: JSON.stringify(php[i][k]).slice(0, 200) }; }
  }
});
for (const k of Object.keys(bad)) check(bad[k] === 0, `${k}: identical for all ${samples.length} palettes`, JSON.stringify(first[k]));

// Properties the whole system relies on.
check(lib.hex('#ABC') === '#aabbcc' && lib.hex('nope') === null && lib.hex('#12345') === null, 'hex() normalises and rejects');
check(Math.abs(lib.contrast('#000000', '#ffffff') - 21) < 1e-9, 'black on white is 21:1');
check(lib.contrast(lib.ensureContrast('#777777', '#ffffff', 4.5), '#ffffff') >= 4.5, 'ensureContrast reaches the requested ratio');
const defaults = lib.complete(def, {});
check(lib.contrastReport(defaults).every((x) => x.pass), 'the default palette passes every contrast check');
def.presets.forEach((pr) => {
  const p = lib.complete(def, pr.colors);
  check(lib.contrastReport(p).every((x) => x.pass), `preset "${pr.id}" passes every contrast check (light)`);
  check(lib.contrastReport(Object.assign({}, p, lib.darkPalette(p))).every((x) => x.pass), `preset "${pr.id}" passes every contrast check (dark)`);
});

// Static guards: brand colours live in tokens, not in component CSS.
const fs = require('fs');
const cssDir = path.join(__dirname, '../wordpress-theme/noblen-corporate/assets/css');
const mainCss = fs.readFileSync(path.join(cssDir, 'main.css'), 'utf8');
const tokensCss = fs.readFileSync(path.join(cssDir, 'tokens.css'), 'utf8');
const literals = (mainCss.match(/#[0-9a-fA-F]{3,8}\b/g) || []).map((x) => x.toLowerCase()).filter((x) => !['#000', '#fff', '#000000', '#ffffff'].includes(x));
check(literals.length === 0, 'main.css has no colour literals other than neutral black and white', literals.join(' '));
const brandLiterals = def.tokens.map((t) => t.default).filter((h) => mainCss.toLowerCase().includes(h));
check(brandLiterals.length === 0, 'main.css contains none of the default brand colours', brandLiterals.join(' '));
check(!/rgba?\(/i.test(mainCss), 'main.css has no rgb() colour literals');
const varName = (k) => (['primary', 'secondary', 'accent'].includes(k) ? '--brand-' : '--color-') + k.replace(/_/g, '-');
def.tokens.forEach((t) => check(tokensCss.includes(`${varName(t.key)}:${t.default};`), `tokens.css default for ${t.key} equals the shared definition`));
const used = new Set([...mainCss.matchAll(/var\((--[a-z0-9-]+)/g)].map((m) => m[1]));
const defined = new Set([...tokensCss.matchAll(/(--[a-z0-9-]+):/g)].map((m) => m[1]));
const undefinedVars = [...used].filter((v) => !defined.has(v) && !['--logo-h'].includes(v));
check(undefinedVars.length === 0, 'every custom property used in main.css is defined in tokens.css', undefinedVars.join(' '));
// The same guards for the populated-site component styles (tiles, bands, hero art, sample cards).
const compCss = fs.readFileSync(path.join(cssDir, 'components.css'), 'utf8');
const compLiterals = (compCss.match(/#[0-9a-fA-F]{3,8}\b/g) || []).map((x) => x.toLowerCase()).filter((x) => !['#000', '#fff', '#000000', '#ffffff'].includes(x));
check(compLiterals.length === 0, 'components.css has no colour literals other than neutral black and white', compLiterals.join(' '));
check(def.tokens.map((t) => t.default).filter((h) => compCss.toLowerCase().includes(h)).length === 0, 'components.css contains none of the default brand colours');
check(!/rgba?\(/i.test(compCss), 'components.css has no rgb() colour literals');
const compUsed = new Set([...compCss.matchAll(/var\((--[a-z0-9-]+)/g)].map((m) => m[1]));
const compUndefined = [...compUsed].filter((v) => !defined.has(v) && !used.has(v) && !['--logo-h', '--icon'].includes(v));
check(compUndefined.length === 0, 'every custom property used in components.css is defined in tokens.css', compUndefined.join(' '));
console.log(`\n${passes} passed, ${failures} failed`);
process.exit(failures ? 1 : 0);
