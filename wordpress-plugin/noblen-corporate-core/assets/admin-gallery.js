/* Media picker for the gallery field. The text input holding comma-separated media IDs stays the source of truth,
   so the field still works without this script. Uses the WordPress media modal; no third-party code. */
(function () {
	'use strict';
	function ids(input) {
		return input.value.split(',').map(function (v) { return parseInt(v, 10); }).filter(function (v) { return v > 0; });
	}
	function init(box) {
		var input = document.getElementById(box.getAttribute('data-input'));
		var list = box.querySelector('.noblen-gallery__list');
		var add = box.querySelector('.noblen-gallery__add');
		if (!input || !list || !add || !window.wp || !wp.media) { return; }
		add.hidden = false;
		var frame = null;

		function write(order) { input.value = order.join(','); }
		function items() { return Array.prototype.slice.call(list.children); }
		function sync() { write(items().map(function (li) { return parseInt(li.getAttribute('data-id'), 10); })); refresh(); }
		function refresh() {
			var all = items();
			all.forEach(function (li, i) {
				li.querySelector('.noblen-gallery__up').disabled = (i === 0);
				li.querySelector('.noblen-gallery__down').disabled = (i === all.length - 1);
			});
		}
		function button(cls, label, text) {
			var b = document.createElement('button');
			b.type = 'button'; b.className = 'button-link ' + cls; b.textContent = text;
			b.setAttribute('aria-label', label);
			return b;
		}
		function make(id, src, alt) {
			var li = document.createElement('li');
			li.setAttribute('data-id', String(id));
			var img = document.createElement('img');
			img.src = src; img.alt = alt || ''; img.width = 80; img.height = 80;
			li.appendChild(img);
			var bar = document.createElement('span');
			bar.className = 'noblen-gallery__tools';
			bar.appendChild(button('noblen-gallery__up', 'Move image ' + id + ' earlier', '↑'));
			bar.appendChild(button('noblen-gallery__down', 'Move image ' + id + ' later', '↓'));
			bar.appendChild(button('noblen-gallery__remove', 'Remove image ' + id, 'Remove'));
			li.appendChild(bar);
			return li;
		}
		list.addEventListener('click', function (e) {
			var t = e.target;
			var li = t.closest ? t.closest('li') : null;
			if (!li || !t.classList) { return; }
			if (t.classList.contains('noblen-gallery__remove')) { li.remove(); sync(); add.focus(); }
			else if (t.classList.contains('noblen-gallery__up') && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); sync(); t.focus(); }
			else if (t.classList.contains('noblen-gallery__down') && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); sync(); t.focus(); }
		});
		add.addEventListener('click', function () {
			if (!frame) {
				frame = wp.media({ title: 'Choose gallery images', button: { text: 'Use these images' }, multiple: 'add', library: { type: 'image' } });
				frame.on('select', function () {
					var have = ids(input);
					frame.state().get('selection').each(function (att) {
						var a = att.toJSON();
						if (a.type !== 'image' || have.indexOf(a.id) !== -1) { return; }
						var src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
						list.appendChild(make(a.id, src, a.alt));
						have.push(a.id);
					});
					sync();
				});
			}
			frame.open();
			frame.state().get('selection').reset(); // each visit starts with nothing selected, so a click always adds
		});
		input.addEventListener('change', function () { /* manual edits to the ID field win; thumbnails refresh on save */ });
		refresh();
	}
	document.addEventListener('DOMContentLoaded', function () {
		Array.prototype.forEach.call(document.querySelectorAll('.noblen-gallery'), init);
	});
})();
