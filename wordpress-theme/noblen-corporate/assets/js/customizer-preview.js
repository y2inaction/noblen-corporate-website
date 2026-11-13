/* Customizer live preview: every colour, the appearance and the logo sizes update instantly without a reload. */
(function (api) {
	'use strict';
	var B = window.NoblenBrand, def = window.NoblenBrandPreview && window.NoblenBrandPreview.definition;
	if (!B || !def || !api) { return; }

	function current() {
		var o = {};
		def.tokens.forEach(function (t) { var s = api('noblen_brand_' + t.key); o[t.key] = s ? s.get() : t.default; });
		return B.complete(def, o);
	}
	function styleEl(id) {
		var el = document.getElementById(id);
		if (!el) { el = document.createElement('style'); el.id = id; document.head.appendChild(el); }
		return el;
	}
	function applyColours() {
		var p = current();
		styleEl('noblen-brand-css').textContent = B.buildCss(p);
		var meta = document.querySelector('meta[name="theme-color"]');
		if (meta) { meta.setAttribute('content', p.primary); }
	}
	function applyLogoSizes() {
		var d = api('noblen_logo_height'), m = api('noblen_logo_height_mobile');
		var dv = parseInt(d ? d.get() : 48, 10) || 48, mv = parseInt(m ? m.get() : 36, 10) || 36;
		styleEl('noblen-logo-css').textContent = ':root{--logo-h:' + Math.max(24, Math.min(120, dv)) + 'px;--logo-h-mobile:' + Math.max(20, Math.min(80, mv)) + 'px}';
	}
	def.tokens.forEach(function (t) { api('noblen_brand_' + t.key, function (v) { v.bind(applyColours); }); });
	api('noblen_brand_appearance', function (v) {
		v.bind(function (mode) {
			document.documentElement.setAttribute('data-appearance', ['light', 'dark', 'auto'].indexOf(mode) === -1 ? 'light' : mode);
			var meta = document.querySelector('meta[name="color-scheme"]');
			if (meta) { meta.setAttribute('content', mode === 'auto' ? 'light dark' : (mode === 'dark' ? 'dark' : 'light')); }
		});
	});
	['noblen_logo_height', 'noblen_logo_height_mobile'].forEach(function (id) { api(id, function (v) { v.bind(applyLogoSizes); }); });
})(window.wp && window.wp.customize);
