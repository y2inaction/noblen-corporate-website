/* Brand colour library: the JavaScript twin of inc/brand-color.php and inc/brand.php.
   Used for the Customizer live preview and contrast checks. tests-e2e/brand-parity.js proves both produce identical results. */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) { module.exports = factory(); } else { root.NoblenBrand = factory(); }
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function hex(value) {
		if (typeof value !== 'string') { return null; }
		var v = value.trim().toLowerCase();
		var m = /^#([0-9a-f]{3})$/.exec(v);
		if (m) { return '#' + m[1][0] + m[1][0] + m[1][1] + m[1][1] + m[1][2] + m[1][2]; }
		return /^#[0-9a-f]{6}$/.test(v) ? v : null;
	}
	function rgb(h) { return [parseInt(h.slice(1, 3), 16), parseInt(h.slice(3, 5), 16), parseInt(h.slice(5, 7), 16)]; }
	function fromRgb(c) {
		var out = '#';
		for (var i = 0; i < 3; i++) {
			var n = Math.max(0, Math.min(255, Math.round(c[i])));
			out += (n < 16 ? '0' : '') + n.toString(16);
		}
		return out;
	}
	function mix(a, b, t) {
		var x = rgb(a), y = rgb(b);
		return fromRgb([x[0] * (1 - t) + y[0] * t, x[1] * (1 - t) + y[1] * t, x[2] * (1 - t) + y[2] * t]);
	}
	function luminance(h) {
		var l = rgb(h).map(function (c) { c = c / 255; return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); });
		return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2];
	}
	function contrast(a, b) {
		var la = luminance(a), lb = luminance(b);
		return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
	}
	function ensureContrast(fg, bg, min) {
		if (contrast(fg, bg) >= min) { return fg; }
		var target = luminance(bg) > 0.5 ? '#000000' : '#ffffff';
		for (var i = 1; i <= 40; i++) {
			var c = mix(fg, target, i * 0.025);
			if (contrast(c, bg) >= min) { return c; }
		}
		return target;
	}
	function onColor(bg) { return contrast('#ffffff', bg) >= contrast('#101828', bg) ? '#ffffff' : '#101828'; }

	function defaults(def) { var o = {}; def.tokens.forEach(function (t) { o[t.key] = t.default; }); return o; }
	function complete(def, colors) {
		var o = defaults(def);
		Object.keys(o).forEach(function (k) { var h = hex(colors && colors[k]); if (h !== null) { o[k] = h; } });
		return o;
	}
	function ensureBadgeContrast(accent, surface) {
		for (var i = 0; i <= 40; i++) {
			var a = mix(accent, '#ffffff', i * 0.025);
			if (contrast(a, mix(surface, a, 0.14)) >= 4.5) { return a; }
		}
		return '#ffffff';
	}
	function darkPalette(p) {
		var base = luminance(p.primary) <= 0.12 ? p.primary : mix(p.primary, '#000000', 0.75);
		var bg = mix(base, '#000000', 0.6);
		var surface = mix(bg, '#ffffff', 0.05);
		var text = ensureContrast(ensureContrast('#e6ecf8', bg, 7), surface, 7);
		var footer = mix(bg, '#000000', 0.35);
		var link = ensureContrast(ensureContrast(p.secondary, bg, 4.5), surface, 4.5);
		var hover = ensureContrast(mix(link, '#ffffff', 0.35), bg, 4.5);
		return {
			secondary: ensureContrast(p.secondary, bg, 3),
			accent: ensureBadgeContrast(p.accent, surface),
			heading: '#ffffff',
			text: text,
			bg: bg,
			surface: surface,
			header_bg: surface,
			header_text: text,
			footer_bg: footer,
			footer_text: ensureContrast('#b9c8ee', footer, 4.5),
			link: link,
			link_hover: hover,
			link_active: hover
		};
	}
	function varName(key) {
		var dash = key.replace(/_/g, '-');
		return (key === 'primary' || key === 'secondary' || key === 'accent') ? '--brand-' + dash : '--color-' + dash;
	}
	function declarations(p) {
		var out = '';
		Object.keys(p).forEach(function (k) { out += varName(k) + ':' + p[k] + ';'; });
		return out;
	}
	function buildCss(p) {
		var dark = declarations(darkPalette(p)) + 'color-scheme:dark;';
		var light = declarations(p) + '--color-on-primary:' + onColor(p.primary) + ';color-scheme:light;';
		return ':root{' + light + '}\n' +
			':root[data-appearance="dark"]{' + dark + '}\n' +
			'@media (prefers-color-scheme:dark){:root[data-appearance="auto"]{' + dark + '}}';
	}
	function contrastReport(p) {
		var text = 4.5, ui = 3.0;
		var badge = mix(p.surface, p.accent, 0.14);
		var pairs = [
			['text-bg', 'Body text on the page background', p.text, p.bg, text],
			['text-surface', 'Body text on cards', p.text, p.surface, text],
			['heading-bg', 'Headings on the page background', p.heading, p.bg, text],
			['heading-surface', 'Card titles on cards', p.heading, p.surface, text],
			['link-bg', 'Links on the page background', p.link, p.bg, text],
			['link-surface', 'Links on cards', p.link, p.surface, text],
			['hover-bg', 'Link hover on the page background', p.link_hover, p.bg, text],
			['active-bg', 'Link active on the page background', p.link_active, p.bg, text],
			['header', 'Header text on the header', p.header_text, p.header_bg, text],
			['header-link', 'Links on the header', p.link, p.header_bg, text],
			['footer', 'Footer text on the footer', p.footer_text, p.footer_bg, text],
			['button', 'Button text on buttons', p.button_text, p.button_bg, text],
			['button-hover', 'Button text on hovered buttons', p.button_text, p.button_hover, text],
			['button-active', 'Button text on pressed buttons', p.button_text, p.button_active, text],
			['badge', 'Badge text (accent) on its tinted background', p.accent, badge, text],
			['hero', 'Hero text on the primary colour', onColor(p.primary), p.primary, text],
			['focus', 'Focus ring (secondary) on the page background', p.secondary, p.bg, ui]
		];
		return pairs.map(function (r) {
			var ratio = Math.round(contrast(r[2], r[3]) * 100) / 100;
			return { id: r[0], label: r[1], fg: r[2], bg: r[3], ratio: ratio, min: r[4], pass: ratio >= r[4] };
		});
	}

	return { hex: hex, rgb: rgb, fromRgb: fromRgb, mix: mix, luminance: luminance, contrast: contrast, ensureContrast: ensureContrast, ensureBadgeContrast: ensureBadgeContrast,
		onColor: onColor, defaults: defaults, complete: complete, darkPalette: darkPalette, varName: varName, buildCss: buildCss, contrastReport: contrastReport };
});
