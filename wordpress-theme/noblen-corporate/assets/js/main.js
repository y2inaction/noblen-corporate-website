(function () {
	var btn = document.querySelector('.nav-toggle');
	var nav = document.getElementById('primary-nav');
	if (!btn || !nav) { return; }
	btn.addEventListener('click', function () {
		var open = btn.getAttribute('aria-expanded') === 'true';
		btn.setAttribute('aria-expanded', open ? 'false' : 'true');
		nav.classList.toggle('is-open', !open);
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && btn.getAttribute('aria-expanded') === 'true') {
			btn.setAttribute('aria-expanded', 'false');
			nav.classList.remove('is-open');
			btn.focus();
		}
	});
})();
