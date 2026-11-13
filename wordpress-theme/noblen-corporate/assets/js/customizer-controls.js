/* Customizer brand tools: presets, restore defaults, logo palette suggestions and the contrast check.
   Interface built with DOM APIs (text is never inserted as HTML). Nothing is saved except through the Customizer's Publish
   button, apart from the saved-preset list, which is stored when the administrator chooses "Save current colours as preset". */
(function (api, $) {
	'use strict';
	var cfg = window.NoblenBrandConfig, B = window.NoblenBrand;
	if (!api || !cfg || !B) { return; }
	var def = cfg.definition, keys = def.tokens.map(function (t) { return t.key; });
	var saved = cfg.saved || {};

	function el(tag, attrs, children) {
		var n = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { n.textContent = attrs[k]; } else if (k === 'class') { n.className = attrs[k]; } else { n.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { if (c) { n.appendChild(c); } });
		return n;
	}
	function button(text, cls, onClick) {
		var b = el('button', { type: 'button', class: 'button ' + (cls || ''), text: text });
		b.addEventListener('click', onClick);
		return b;
	}
	function getPalette() {
		var o = {};
		keys.forEach(function (k) { var s = api('noblen_brand_' + k); o[k] = s ? s.get() : undefined; });
		return B.complete(def, o);
	}
	function setPalette(colors) {
		var full = B.complete(def, colors);
		keys.forEach(function (k) { var s = api('noblen_brand_' + k); if (s) { s.set(full[k]); } });
	}
	function mode() { var s = api('noblen_brand_appearance'); return s ? s.get() : 'light'; }

	function build(root) {
		root.textContent = '';
		var status = el('p', { class: 'noblen-status', role: 'status', 'aria-live': 'polite' });
		function say(msg, bad) { status.textContent = msg; status.className = 'noblen-status' + (bad ? ' is-error' : ''); }

		// ---- Presets.
		var select = el('select', { id: 'noblen-preset-select', 'aria-label': 'Palette preset' });
		function fillSelect(selectId) {
			select.textContent = '';
			var g1 = el('optgroup', { label: 'Built-in' });
			def.presets.forEach(function (p) { g1.appendChild(el('option', { value: 'b:' + p.id, text: p.label })); });
			select.appendChild(g1);
			var ids = Object.keys(saved);
			if (ids.length) {
				var g2 = el('optgroup', { label: 'Saved by you' });
				ids.forEach(function (id) { g2.appendChild(el('option', { value: 's:' + id, text: saved[id].name })); });
				select.appendChild(g2);
			}
			if (selectId) { select.value = selectId; }
			delBtn.disabled = select.value.indexOf('s:') !== 0;
		}
		function chosenColours() {
			var v = select.value;
			if (v.indexOf('b:') === 0) { var p = def.presets.filter(function (x) { return x.id === v.slice(2); })[0]; return p ? p.colors : null; }
			return saved[v.slice(2)] ? saved[v.slice(2)].colors : null;
		}
		var delBtn = button('Delete saved preset', 'noblen-delete', function () {
			var id = select.value.slice(2);
			if (select.value.indexOf('s:') !== 0 || !window.confirm('Delete the saved preset "' + (saved[id] ? saved[id].name : id) + '"?')) { return; }
			$.post(cfg.ajaxUrl, { action: 'noblen_preset_delete', nonce: cfg.nonce, id: id }).done(function (r) {
				saved = (r.data && r.data.saved) || {}; fillSelect('b:noblen'); say('Preset deleted.');
			}).fail(function (x) { say((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'Could not delete the preset.', true); });
		});
		var previewBtn = button('Preview this preset', 'button-primary noblen-apply-preset', function () {
			var c = chosenColours();
			if (!c) { return; }
			setPalette(c);
			say('Preset previewed on the site. Press Publish to save it.');
		});
		var nameInput = el('input', { type: 'text', id: 'noblen-preset-name', maxlength: '40', placeholder: 'Preset name', 'aria-label': 'Name for the new preset' });
		var saveBtn = button('Save current colours as preset', 'noblen-save-preset', function () {
			var c = getPalette();
			$.post(cfg.ajaxUrl, { action: 'noblen_preset_save', nonce: cfg.nonce, name: nameInput.value, colors: c }).done(function (r) {
				saved = (r.data && r.data.saved) || saved; fillSelect('s:' + r.data.id); nameInput.value = ''; say('Preset saved.');
			}).fail(function (x) { say((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'Could not save the preset.', true); });
		});
		select.addEventListener('change', function () { delBtn.disabled = select.value.indexOf('s:') !== 0; });
		var restoreBtn = button('Restore Noblen defaults', 'noblen-restore', function () {
			if (!window.confirm('Restore the original Noblen colours and light appearance? Nothing is saved until you press Publish.')) { return; }
			setPalette({});
			var a = api('noblen_brand_appearance'); if (a) { a.set('light'); }
			say('Default colours restored in the preview. Press Publish to save them.');
		});
		root.appendChild(el('h4', { text: 'Palette presets' }));
		root.appendChild(el('div', { class: 'noblen-row' }, [select]));
		root.appendChild(el('div', { class: 'noblen-row' }, [previewBtn, delBtn]));
		root.appendChild(el('div', { class: 'noblen-row' }, [nameInput, saveBtn]));
		root.appendChild(el('div', { class: 'noblen-row' }, [restoreBtn]));
		fillSelect('b:noblen');

		// ---- Logo palette suggestions.
		root.appendChild(el('h4', { text: 'Colours from your logo' }));
		root.appendChild(el('p', { class: 'description', text: 'Analyse the logo you chose in Site Identity and review a suggested palette. Nothing changes until you choose to preview it and then press Publish.' }));
		var suggestBtn = button('Suggest colours from my logo', 'noblen-suggest', function () { analyse(); });
		root.appendChild(el('div', { class: 'noblen-row' }, [suggestBtn]));
		var panel = el('div', { class: 'noblen-suggestion', hidden: 'hidden' });
		root.appendChild(panel);
		root.appendChild(status);

		function analyse() {
			var logo = api('custom_logo') ? parseInt(api('custom_logo').get(), 10) : 0;
			if (!logo) { say('Choose a logo in Site Identity first, or choose the colours manually.', true); return; }
			say('Analysing the logo…');
			$.post(cfg.ajaxUrl, { action: 'noblen_logo_palette', nonce: cfg.nonce, attachment: logo }).done(function (r) {
				showSuggestion(r.data); say('Review the suggested colours below.');
			}).fail(function (x) { panel.hidden = true; say((x.responseJSON && x.responseJSON.data && x.responseJSON.data.message) || 'The logo could not be analysed. Choose the colours manually.', true); });
		}
		function showSuggestion(data) {
			var work = B.complete(def, data.palette);
			panel.textContent = ''; panel.hidden = false;
			var sw = el('ul', { class: 'noblen-swatches', 'aria-label': 'Colours found in the logo' });
			(data.dominant || []).forEach(function (d) {
				var li = el('li', {}, [el('span', { class: 'noblen-swatch', style: 'background:' + d.hex, 'aria-hidden': 'true' }), el('span', { text: d.hex + ' (' + Math.round(d.share * 100) + '%)' })]);
				sw.appendChild(li);
			});
			panel.appendChild(el('h5', { text: 'Found in the logo' }));
			panel.appendChild(sw);
			(data.notes || []).forEach(function (n) { panel.appendChild(el('p', { class: 'noblen-note', text: n })); });
			panel.appendChild(el('h5', { text: 'Suggested palette (adjust any colour)' }));
			var grid = el('div', { class: 'noblen-grid' });
			var inputs = {};
			def.tokens.forEach(function (t) {
				var colour = el('input', { type: 'color', id: 'noblen-sug-' + t.key, value: work[t.key], 'aria-label': t.label });
				var hex = el('input', { type: 'text', value: work[t.key], maxlength: '7', 'aria-label': t.label + ' (hex)', class: 'noblen-hex' });
				inputs[t.key] = { colour: colour, hex: hex };
				function commit(v) { var h = B.hex(v); if (h) { work[t.key] = h; colour.value = h; hex.value = h; refresh(); } }
				colour.addEventListener('input', function () { commit(colour.value); });
				hex.addEventListener('change', function () { var h = B.hex(hex.value); if (h) { commit(h); } else { hex.value = work[t.key]; } });
				grid.appendChild(el('div', { class: 'noblen-cell' }, [el('label', { for: 'noblen-sug-' + t.key, text: t.label }), colour, hex]));
			});
			panel.appendChild(grid);
			var mini = el('div', { class: 'noblen-mini', 'aria-hidden': 'true' }, [
				el('div', { class: 'noblen-mini__header', text: 'Header' }),
				el('div', { class: 'noblen-mini__hero', text: 'Hero' }),
				el('div', { class: 'noblen-mini__body' }, [el('strong', { class: 'noblen-mini__h', text: 'Heading' }), el('span', { text: ' body text ' }), el('a', { href: '#', class: 'noblen-mini__link', text: 'link' }), el('span', { class: 'noblen-mini__btn', text: 'Button' }), el('span', { class: 'noblen-mini__card', text: 'Card' })]),
				el('div', { class: 'noblen-mini__footer', text: 'Footer' })
			]);
			mini.querySelector('a').addEventListener('click', function (e) { e.preventDefault(); });
			panel.appendChild(el('h5', { text: 'Preview' }));
			panel.appendChild(mini);
			var summary = el('p', { class: 'noblen-sum', role: 'status', 'aria-live': 'polite' });
			panel.appendChild(summary);
			function refresh() {
				keys.forEach(function (k) { mini.style.setProperty(B.varName(k), work[k]); });
				mini.style.setProperty('--color-on-primary', B.onColor(work.primary));
				var fails = B.contrastReport(work).filter(function (r) { return !r.pass; });
				summary.textContent = fails.length ? fails.length + ' contrast warning' + (fails.length > 1 ? 's' : '') + ' in this suggestion: ' + fails.map(function (f) { return f.label; }).join('; ') + '.' : 'This suggestion passes every contrast check.';
				summary.className = 'noblen-sum' + (fails.length ? ' is-warning' : '');
			}
			refresh();
			panel.appendChild(el('div', { class: 'noblen-row' }, [
				button('Preview these colours on the site', 'button-primary noblen-apply-suggestion', function () { setPalette(work); panel.hidden = true; say('Suggested colours previewed on the site. Press Publish to save them, or use Restore to go back.'); }),
				button('Discard suggestion', 'noblen-discard', function () { panel.hidden = true; say('Suggestion discarded. The site colours are unchanged.'); })
			]));
		}

		// ---- Contrast check.
		root.appendChild(el('h4', { text: 'Contrast check' }));
		var report = el('div', { class: 'noblen-report' });
		root.appendChild(report);
		function drawReport() {
			var p = getPalette(), m = mode();
			var sets = [['Light appearance', p]];
			if (m !== 'light') { sets.push(['Dark appearance', Object.assign({}, p, B.darkPalette(p))]); }
			report.textContent = '';
			sets.forEach(function (s) {
				var rows = B.contrastReport(s[1]), fails = rows.filter(function (r) { return !r.pass; });
				var head = el('p', { class: 'noblen-report__head' + (fails.length ? ' is-warning' : ' is-ok'), text: s[0] + ': ' + (fails.length ? fails.length + ' warning' + (fails.length > 1 ? 's' : '') : 'all ' + rows.length + ' checks pass') });
				report.appendChild(head);
				if (fails.length) {
					var ul = el('ul', { class: 'noblen-report__list' });
					fails.forEach(function (f) { ul.appendChild(el('li', { text: f.label + ': ' + f.ratio + ':1 (needs ' + f.min + ':1)' })); });
					report.appendChild(ul);
				}
			});
		}
		keys.forEach(function (k) { var s = api('noblen_brand_' + k); if (s) { s.bind(drawReport); } });
		var ap = api('noblen_brand_appearance'); if (ap) { ap.bind(drawReport); }
		drawReport();
	}

	api.bind('ready', function () {
		api.control('noblen_brand_tools', function (control) {
			control.deferred.embedded.done(function () {
				var root = control.container.find('[data-noblen-tools]')[0];
				if (root) { build(root); }
			});
		});
		// A JPEG logo cannot be transparent: say so with a Customizer notification next to the logo control.
		api('custom_logo', function (setting) {
			function check(id) {
				setting.notifications.remove('noblen_jpeg');
				if (!id || !window.wp || !wp.media || !wp.media.attachment) { return; }
				var att = wp.media.attachment(id);
				att.fetch().done(function () {
					if (att.get('mime') === 'image/jpeg') {
						setting.notifications.add(new api.Notification('noblen_jpeg', { type: 'warning', message: 'This logo is a JPEG, which cannot have a transparent background. An SVG, or a PNG or WebP with a transparent background, looks better on coloured headers.' }));
					}
				});
			}
			setting.bind(check);
			check(setting.get());
		});
	});
})(window.wp && window.wp.customize, window.jQuery);
