/* Dlight Giftshop storefront behaviour. No dependencies. */
(function () {
	'use strict';

	var DL = window.DL || {};
	var $ = function (s, c) { return (c || document).querySelector(s); };
	var $$ = function (s, c) { return Array.prototype.slice.call((c || document).querySelectorAll(s)); };

	function esc(text) {
		var el = document.createElement('div');
		el.textContent = text == null ? '' : text;
		return el.innerHTML;
	}
	function decode(html) {
		var el = document.createElement('textarea');
		el.innerHTML = html;
		return el.value;
	}
	function money(minor, unit) {
		var value = Number(minor) / Math.pow(10, unit);
		return (DL.symbol || '') + ' ' + value.toLocaleString('en-KE', { maximumFractionDigits: 0 });
	}
	function shortName(p) {
		return decode(p.name).split(/\s+[–—-]\s+/)[0];
	}

	/* ---------- Product card from the store API ---------- */
	function card(p) {
		var name = shortName(p);
		var img = p.images && p.images[0];
		var pr = p.prices;
		var regular = Number(pr.regular_price), price = Number(pr.price);
		var off = regular > price ? Math.round((regular - price) / regular * 100) : 0;
		var src = img ? (img.thumbnail || img.src) : '';
		var poster = (p.categories || []).some(function (c) { return /perfume/i.test(c.name); });
		return '<article class="dl-card' + (poster ? ' dl-card--poster' : '') + '">' +
			'<a class="dl-card__media" href="' + esc(p.permalink) + '" aria-label="' + esc(name) + '">' +
			(off ? '<span class="dl-badge">-' + off + '%</span>' : '') +
			(src ? '<img class="dl-card__img" loading="lazy" width="450" height="450" src="' + esc(src) + '" alt="' + esc(name) + '">' : '') + '</a>' +
			'<div class="dl-card__body">' +
			'<h3 class="dl-card__name"><a href="' + esc(p.permalink) + '">' + esc(name) + '</a></h3>' +
			'<div class="dl-price">' + (off ? '<del>' + money(regular, pr.currency_minor_unit) + '</del>' : '') +
			'<ins>' + money(price, pr.currency_minor_unit) + '</ins></div>' +
			(p.is_purchasable && p.is_in_stock && p.type === 'simple' ?
				'<a class="dl-add js-add" rel="nofollow" href="?add-to-cart=' + p.id + '" data-id="' + p.id + '" aria-label="Add ' + esc(name) + ' to cart">Add to Cart</a>' : '') +
			'</div></article>';
	}

	/* ---------- Home page banners ---------- */
	(function slider() {
		var root = $('#dl-slider');
		if (!root) { return; }
		var slides = $$('.dl-slide', root);
		var dots = $$('.js-slide-dot', root);
		var calm = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		var now = 0, timer, startX = null;

		root.classList.add('js-slider');
		function go(i) {
			now = (i + slides.length) % slides.length;
			slides.forEach(function (s, n) {
				s.classList.toggle('is-on', n === now);
				s.setAttribute('aria-hidden', n === now ? 'false' : 'true');
			});
			dots.forEach(function (d, n) { d.classList.toggle('is-on', n === now); });
		}
		function stop() { clearInterval(timer); timer = null; }
		function play() {
			if (calm || timer) { return; }
			timer = setInterval(function () {
				if (!document.hidden) { go(now + 1); }
			}, 6000);
		}
		go(0);
		root.addEventListener('click', function (e) {
			var dot = e.target.closest('.js-slide-dot');
			var next = e.target.closest('.js-slide-next'), prev = e.target.closest('.js-slide-prev');
			if (!dot && !next && !prev) { return; }
			go(dot ? Number(dot.dataset.i) : now + (next ? 1 : -1));
			// A manual choice restarts the timer, so the banner does not change straight away.
			stop();
			play();
		});
		root.addEventListener('mouseenter', stop);
		root.addEventListener('mouseleave', play);
		root.addEventListener('focusin', stop);
		root.addEventListener('touchstart', function (e) { stop(); startX = e.touches[0].clientX; }, { passive: true });
		root.addEventListener('touchend', function (e) {
			if (startX === null) { return; }
			var dx = e.changedTouches[0].clientX - startX;
			startX = null;
			if (Math.abs(dx) > 40) { go(now + (dx < 0 ? 1 : -1)); }
		}, { passive: true });
		play();
	})();

	/* ---------- Gift finder ---------- */
	(function finder() {
		var form = $('#dl-finder');
		if (!form) { return; }
		var results = $('#dl-finder-results');
		var all = $('#dl-finder-all');
		var ticket = 0;

		function cats(who, kind) {
			var map = (DL.finder || {})[who] || {};
			return (kind === 'either' ? (map.perfume || []).concat(map.watch || []) : (map[kind] || [])).join(',');
		}
		function run() {
			var b = form.budget.value.split('-');
			var ids = cats(form.who.value, form.kind.value);
			var q = 'products?per_page=5&orderby=popularity&order=desc&category=' + encodeURIComponent(ids);
			var seeAll = DL.shop + (DL.shop.indexOf('?') > -1 ? '&' : '?') + 'dl_for=' + form.who.value +
				(form.kind.value === 'either' ? '' : '&dl_kind=' + form.kind.value) + '&orderby=popularity';
			if (b[0]) { q += '&min_price=' + (Number(b[0]) * 100); seeAll += '&min_price=' + b[0]; }
			if (b[1]) { q += '&max_price=' + (Number(b[1]) * 100); seeAll += '&max_price=' + b[1]; }

			var mine = ++ticket;
			results.classList.add('is-loading');
			if (!results.children.length) {
				results.innerHTML = '<div class="dl-skel"></div><div class="dl-skel"></div><div class="dl-skel"></div><div class="dl-skel"></div><div class="dl-skel"></div>';
				results.classList.remove('is-loading');
			}
			fetch(DL.store + q).then(function (r) {
				var total = Number(r.headers.get('X-WP-Total') || 0);
				return r.json().then(function (items) { return { items: items, total: total }; });
			}).then(function (data) {
				if (mine !== ticket) { return; }
				results.classList.remove('is-loading');
				if (!data.items.length) {
					results.innerHTML = '<p class="dl-finder__none">No products in stock for that budget. Try another range.</p>';
					all.hidden = true;
					return;
				}
				results.innerHTML = data.items.map(card).join('');
				all.hidden = data.total <= data.items.length;
				all.href = seeAll;
				all.textContent = 'See all ' + data.total + ' products';
			}).catch(function () {
				if (mine !== ticket) { return; }
				results.classList.remove('is-loading');
				results.innerHTML = '<p class="dl-finder__none"><a href="' + esc(DL.shop) + '">Browse the shop instead</a></p>';
			});
		}
		form.addEventListener('change', run);
		form.addEventListener('submit', function (e) { e.preventDefault(); run(); });

		if ('IntersectionObserver' in window) {
			var seen = new IntersectionObserver(function (entries) {
				if (entries[0].isIntersecting) { seen.disconnect(); run(); }
			}, { rootMargin: '900px' });
			seen.observe(form);
		} else {
			run();
		}
	})();

	/* ---------- Cart ---------- */
	var drawer = $('#dl-drawer');
	var lastFocus = null;

	function openDrawer() {
		if (!drawer) { return; }
		lastFocus = document.activeElement;
		drawer.hidden = false;
		document.documentElement.style.overflow = 'hidden';
		$('.dl-drawer__panel', drawer).focus();
	}
	function closeDrawer() {
		if (!drawer || drawer.hidden) { return; }
		drawer.hidden = true;
		document.documentElement.style.overflow = '';
		if (lastFocus && lastFocus.focus) { lastFocus.focus(); }
	}
	function apply(fragments) {
		if (!fragments) { return; }
		Object.keys(fragments).forEach(function (selector) {
			$$(selector).forEach(function (el) {
				// createContextualFragment runs scripts, which plugins such as Meta tracking rely on.
				el.replaceWith(document.createRange().createContextualFragment(fragments[selector]));
			});
		});
	}
	function ajax(endpoint, data) {
		var body = new URLSearchParams();
		Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
		return fetch(DL.ajax.replace('%%endpoint%%', endpoint), {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) { return r.json(); });
	}
	function addToCart(id, qty, trigger) {
		var label = trigger.textContent;
		trigger.classList.add('is-busy');
		return ajax('add_to_cart', { product_id: id, quantity: qty || 1 }).then(function (res) {
			trigger.classList.remove('is-busy');
			if (!res || res.error) {
				if (res && res.product_url) { window.location = res.product_url; }
				return;
			}
			apply(res.fragments);
			if (trigger.classList.contains('dl-add')) {
				trigger.classList.add('is-done');
				trigger.textContent = 'Added';
				setTimeout(function () { trigger.classList.remove('is-done'); trigger.textContent = label; }, 1500);
			}
			openDrawer();
		}).catch(function () {
			trigger.classList.remove('is-busy');
			window.location = '?add-to-cart=' + id;
		});
	}
	function refreshCart(open) {
		ajax('get_refreshed_fragments').then(function (res) {
			apply(res.fragments);
			if (open) { openDrawer(); }
		}).catch(function () {
			if (open) { window.location = $('.js-bag').href; }
		});
	}

	var menu = $('#dl-menu');

	document.addEventListener('click', function (e) {
		var add = e.target.closest('.js-add');
		if (add) { e.preventDefault(); addToCart(add.dataset.id, 1, add); return; }

		var remove = e.target.closest('.js-remove');
		if (remove) {
			remove.disabled = true;
			ajax('remove_from_cart', { cart_item_key: remove.dataset.key }).then(function (res) { apply(res.fragments); });
			return;
		}
		if (e.target.closest('.js-drawer-close')) { closeDrawer(); return; }

		var bag = e.target.closest('.js-bag');
		if (bag && !document.body.classList.contains('woocommerce-cart')) {
			e.preventDefault();
			refreshCart(true);
			return;
		}
		if (e.target.closest('.js-search-focus')) {
			var box = $('#dl-search-input');
			if (box) { window.scrollTo({ top: 0, behavior: 'smooth' }); box.focus(); }
			return;
		}
		// On the cart drawer, WhatsApp carries the items in the cart.
		var wa = e.target.closest('.js-wa');
		if (wa && drawer && !drawer.hidden) {
			var lines = $$('.dl-line__name', drawer).map(function (n) { return '- ' + n.textContent.trim(); });
			if (lines.length) { wa.href = wa.href.split('?')[0] + '?text=' + encodeURIComponent('Hello Dlight Giftshop, I would like to order:\n' + lines.join('\n')); }
		}
		if (e.target.closest('.js-menu-open') && menu) { menu.showModal(); return; }
		if (e.target.closest('.js-menu-close') && menu) { menu.close(); return; }
		if (e.target === menu) { menu.close(); return; }

		var thumb = e.target.closest('.js-thumb');
		if (thumb) {
			var main = $('#dl-gallery-main'), to = document.getElementById(thumb.dataset.to);
			if (main && to) { main.scrollTo({ left: to.offsetLeft - main.offsetLeft, behavior: 'smooth' }); }
			$$('.js-thumb').forEach(function (b) { b.classList.toggle('is-on', b === thumb); });
		}
	});

	// Add to Cart opens the drawer. Buy Now is left as a normal form post that goes to checkout.
	var cartForm = $('.dl-buy__form form.cart');
	if (cartForm) {
		cartForm.addEventListener('submit', function (e) {
			var btn = e.submitter;
			if (!btn || btn.classList.contains('dl-buynow') || !btn.classList.contains('single_add_to_cart_button') || !btn.value) { return; }
			e.preventDefault();
			var qty = $('input.qty', cartForm);
			addToCart(btn.value, qty ? qty.value : 1, btn);
		});
	}

	// Only ask the server for the cart when this visitor has something in it.
	if (document.cookie.indexOf('woocommerce_items_in_cart=1') > -1) { refreshCart(false); }

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') { closeDrawer(); closeSearch(); }
	});

	/* ---------- Search suggestions ---------- */
	var input = $('#dl-search-input');
	var panel = $('#dl-search-results');
	function closeSearch() { if (panel) { panel.hidden = true; } }
	(function suggest() {
		if (!input || !panel) { return; }
		var form = $('#dl-search');
		var timer, ticket = 0;
		input.addEventListener('input', function () {
			clearTimeout(timer);
			var term = input.value.trim();
			if (term.length < 2) { closeSearch(); return; }
			timer = setTimeout(function () {
				var mine = ++ticket;
				fetch(DL.store + 'products?per_page=6&search=' + encodeURIComponent(term)).then(function (r) {
					var total = Number(r.headers.get('X-WP-Total') || 0);
					return r.json().then(function (items) { return { items: items, total: total }; });
				}).then(function (data) {
					if (mine !== ticket) { return; }
					panel.innerHTML = data.items.length ? data.items.map(function (p) {
						var img = p.images && p.images[0];
						return '<a class="dl-suggest" href="' + esc(p.permalink) + '">' +
							(img ? '<img loading="lazy" width="56" height="56" src="' + esc(img.thumbnail || img.src) + '" alt="">' : '<span></span>') +
							'<span>' + esc(shortName(p)) + '</span><b>' + money(p.prices.price, p.prices.currency_minor_unit) + '</b></a>';
					}).join('') + (data.total > data.items.length ? '<button type="submit" class="dl-search__all">See all ' + data.total + ' results</button>' : '') :
						'<p class="dl-search__none">No products found. Try another name, or ask us on WhatsApp.</p>';
					panel.hidden = false;
				}).catch(closeSearch);
			}, 200);
		});
		document.addEventListener('click', function (e) {
			if (!form.contains(e.target)) { closeSearch(); }
		});
	})();

	/* ---------- Shop filters submit themselves ---------- */
	(function filters() {
		var form = $('#dl-filters');
		if (!form) { return; }
		var price = $('[data-price]', form), min = $('[data-min]', form), max = $('[data-max]', form);
		form.addEventListener('change', function () {
			var b = price.value.split('-');
			min.value = b[0] || ''; max.value = b[1] || '';
			// Leave empty values out of the URL.
			$$('input,select', form).forEach(function (el) { el.disabled = !el.value || el === price; });
			form.submit();
		});
	})();

	/* ---------- Mobile buy bar appears once the main button has scrolled away ---------- */
	(function buybar() {
		var bar = $('#dl-buybar'), target = $('#dl-buy-form');
		if (!bar || !target || !('IntersectionObserver' in window)) { return; }
		new IntersectionObserver(function (entries) {
			bar.classList.toggle('is-on', !entries[0].isIntersecting && entries[0].boundingClientRect.top < 0);
		}).observe(target);
	})();
})();
