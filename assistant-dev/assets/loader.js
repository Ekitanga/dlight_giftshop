/* Tiny launcher. The chat itself loads only when a shopper opens it. */
(function () {
	'use strict';
	var C = window.DLA;
	if (!C) { return; }
	var btn = document.createElement('button');
	btn.type = 'button';
	btn.className = 'dla-launch';
	btn.setAttribute('aria-haspopup', 'dialog');
	btn.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v11H9l-5 4z"/><path d="M8 9.500h8M8 12.500h5"/></svg><span>Dlight AI</span>';
	var loading = false;

	function script(name) {
		return new Promise(function (resolve, reject) {
			var s = document.createElement('script');
			s.src = C.files[name];
			s.onload = resolve;
			s.onerror = reject;
			document.head.appendChild(s);
		});
	}

	function open() {
		if (window.DLAWidget) { window.DLAWidget.open(btn); return; }
		if (loading) { return; }
		loading = true;
		btn.querySelector('span').textContent = 'Opening...';
		var css = document.createElement('link');
		css.rel = 'stylesheet';
		css.href = C.files['widget.css'];
		document.head.appendChild(css);
		Promise.all([script('engine.js'), fetch(C.index).then(function (r) { return r.json(); })]).then(function (res) {
			window.DLAData = res[1];
			return script('widget.js');
		}).then(function () {
			btn.querySelector('span').textContent = 'Dlight AI';
			window.DLAWidget.open(btn);
		}).catch(function () {
			loading = false;
			btn.querySelector('span').textContent = 'Try again';
		});
	}

	btn.addEventListener('click', open);
	document.body.appendChild(btn);
})();
