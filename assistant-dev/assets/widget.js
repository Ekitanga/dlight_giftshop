/*
 * Dlight AI chat window. All links stay on this website, except "Continue on WhatsApp",
 * which opens the store's own WhatsApp number with a summary the shopper can read before sending.
 */
(function () {
	'use strict';

	var C = window.DLA;
	var engine = window.DLAEngine.create(window.DLAData);
	var KEY = 'dla_chat_v2';
	var state = {};
	var history = [];
	var session = '';
	var els = {};
	var opener = null;
	var live = {};

	function esc(s) {
		var d = document.createElement('div');
		d.textContent = s == null ? '' : s;
		return d.innerHTML;
	}
	function money(n) { return 'KSh ' + Math.round(n).toLocaleString('en-KE'); }
	function safePath(path) {
		var p = String(path || '');
		return /^\/(?!\/)/.test(p) ? p : '/';
	}
	function abs(path) { return location.origin + safePath(path); }

	function newSession() {
		var a = new Uint8Array(8);
		(window.crypto || window.msCrypto).getRandomValues(a);
		return Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
	}
	function save() {
		try { sessionStorage.setItem(KEY, JSON.stringify({ state: state, history: history.slice(-40), session: session })); } catch (e) { /* Private browsing. */ }
	}
	function restore() {
		try {
			var d = JSON.parse(sessionStorage.getItem(KEY) || 'null');
			if (d) { state = d.state || {}; history = d.history || []; session = d.session || ''; }
		} catch (e) { /* Ignore. */ }
		if (!/^[a-f0-9]{16}$/.test(session)) { session = newSession(); }
	}

	function post(url, data) {
		return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data), keepalive: true, credentials: 'same-origin' });
	}
	function log(question, result) {
		if (!C.log) { return; }
		try {
			post(C.log, { s: session, q: question || '', b: result && result.text ? result.text : '', a: result ? (result.answered ? 1 : 0) : 1, r: result ? result.total || 0 : 0, p: result && result.clicked ? result.clicked : 0 }).catch(function () {});
		} catch (e) { /* Logging must never affect the chat. */ }
	}

	/* ---------- WhatsApp handoff with a summary ---------- */
	// Button taps and short replies ("Show cheapest first", "For her") are not questions worth sending.
	var NOT_A_QUESTION = /^(show (cheapest|most expensive|similar|more|best|me more)|cheapest first|is it in stock|what are its features|help me choose|best[- ]selling|for (her|him)|under ksh|ksh|any budget|perfumes?|watch(es)?|yes|no|ok(ay)?|thanks?( you)?|hi|hello|start over)\b/i;

	// Tidy what the shopper typed: no shouting, a capital letter, a question mark on questions.
	function tidy(q) {
		q = String(q).replace(/\s+/g, ' ').trim().slice(0, 120);
		if (/[A-Z]{3}/.test(q) && q === q.toUpperCase()) { q = q.toLowerCase(); }
		q = q.charAt(0).toUpperCase() + q.slice(1);
		if (/^(which|what|who|where|when|how|do|does|is|are|can|could|will|would|should|have|has)\b/i.test(q) && !/[?.!]$/.test(q)) { q += '?'; }
		return q;
	}

	function summary() {
		var lines = ['Hello Dlight Giftshop,', 'I was chatting with Dlight AI on your website and would like help with an order.'];
		var ids = [];
		if (state.focus) { ids.push(state.focus); }
		(state.last || []).slice(0, 2).forEach(function (id) { if (ids.indexOf(id) < 0) { ids.push(id); } });
		var items = ids.map(engine.product).filter(Boolean).slice(0, 3);
		if (items.length) {
			lines.push('', '*Products I am interested in*');
			items.forEach(function (p, n) {
				var price = live[p.i] && live[p.i].price != null ? live[p.i].price : p.p;
				if (n) { lines.push(''); }
				lines.push((n + 1) + '. ' + p.n, '    Price: ' + money(price), '    ' + abs(p.u));
			});
		}
		var looking = state.q ? engine.describe(state.q) : '';
		if (looking) { lines.push('', '*Looking for*', '• ' + looking.charAt(0).toUpperCase() + looking.slice(1)); }
		var seen = {};
		if (looking) { seen[looking.toLowerCase()] = 1; }
		var asked = history.filter(function (h) { return h.me; }).map(function (h) { return h.me; })
			.filter(function (q) { return !NOT_A_QUESTION.test(q.trim()) && !/\d{3}\s?\d{3}\s?\d{3}/.test(q); })
			.map(tidy)
			.filter(function (q) { var k = q.toLowerCase(); if (seen[k]) { return false; } seen[k] = 1; return true; })
			.slice(-3);
		if (asked.length) { lines.push('', '*I asked about*'); asked.forEach(function (q) { lines.push('• ' + q); }); }
		lines.push('', 'Thank you.');
		return lines.join('\n').slice(0, 1500);
	}
	// The shop's own number for display, from the WhatsApp link (e.g. +254 740 611 468).
	function shopLine() {
		var d = (String(C.handoff || '').match(/(\d{12})/) || [])[1];
		return d ? '+' + d.slice(0, 3) + ' ' + d.slice(3, 6) + ' ' + d.slice(6, 9) + ' ' + d.slice(9) : '';
	}
	function waLink() { return C.handoff ? C.handoff + '?text=' + encodeURIComponent(summary()) : ''; }

	/* ---------- Layout ---------- */
	function build() {
		var root = document.createElement('div');
		root.className = 'dla';
		root.hidden = true;
		root.setAttribute('role', 'dialog');
		root.setAttribute('aria-modal', 'true');
		root.setAttribute('aria-label', 'Dlight AI shopping assistant');
		root.innerHTML =
			'<div class="dla__head">' +
				'<span class="dla__mark" aria-hidden="true">DG</span>' +
				'<div class="dla__title"><strong>Dlight AI</strong><small>Shopping assistant &middot; replies instantly</small></div>' +
				'<button type="button" class="dla__reset" aria-label="Start a new chat" title="New chat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12a8 8 0 1 0 2.500-5.800M4 4v4.500h4.500"/></svg></button>' +
				'<button type="button" class="dla__close" aria-label="Close chat"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>' +
			'</div>' +
			'<div class="dla__context" hidden></div>' +
			'<div class="dla__log" role="log" aria-live="polite" tabindex="0"></div>' +
			'<form class="dla__form">' +
				'<label class="dla__sr" for="dla-input">Message Dlight AI</label>' +
				'<input id="dla-input" type="text" maxlength="180" autocomplete="off" placeholder="Type your message">' +
				'<button type="submit" aria-label="Send"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>' +
			'</form>' +
			'<p class="dla__note">Dlight AI is automated and answers from our product listings and policies.' + (C.handoff ? ' <a class="dla__wa-mini" href="#" data-wa>Chat with our team</a>' : '') + '</p>';
		document.body.appendChild(root);
		els.root = root;
		els.log = root.querySelector('.dla__log');
		els.ctx = root.querySelector('.dla__context');
		els.input = root.querySelector('input');

		root.querySelector('.dla__close').addEventListener('click', close);
		root.querySelector('.dla__reset').addEventListener('click', reset);
		root.querySelector('form').addEventListener('submit', function (e) {
			e.preventDefault();
			var v = els.input.value.trim();
			if (v) { els.input.value = ''; ask(v); }
		});
		root.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') { close(); return; }
			if (e.key !== 'Tab') { return; }
			var f = Array.prototype.filter.call(root.querySelectorAll('a,button,input,[tabindex="0"]'), function (x) { return x.offsetParent !== null; });
			if (!f.length) { return; }
			if (e.shiftKey && document.activeElement === f[0]) { e.preventDefault(); f[f.length - 1].focus(); }
			else if (!e.shiftKey && document.activeElement === f[f.length - 1]) { e.preventDefault(); f[0].focus(); }
		});
		root.addEventListener('click', function (e) {
			var wa = e.target.closest('[data-wa]');
			if (wa) {
				e.preventDefault();
				log('[Opened WhatsApp]', { answered: true, total: 0 });
				window.open(waLink(), '_blank', 'noopener');
				return;
			}
			var chip = e.target.closest('.dla__chip');
			if (chip) { ask(chip.textContent); return; }
			var pill = e.target.closest('.dla__pill');
			if (pill) { dropPill(pill.getAttribute('data-key'), pill.getAttribute('data-value')); return; }
			var more = e.target.closest('.dla__expand');
			if (more) {
				var rail = more.nextElementSibling;
				rail.hidden = !rail.hidden;
				more.setAttribute('aria-expanded', String(!rail.hidden));
				more.querySelector('span').textContent = rail.hidden ? 'Show' : 'Hide';
				return;
			}
			var leadBtn = e.target.closest('[data-lead]');
			if (leadBtn) { showForm(leadBtn.closest('.dla__turn'), { products: JSON.parse(leadBtn.getAttribute('data-lead') || '[]'), why: 'button' }); leadBtn.parentNode.remove(); return; }
			var link = e.target.closest('[data-pid]');
			if (link) { log('', { answered: true, total: 0, clicked: Number(link.getAttribute('data-pid')) }); }
		});
	}

	/* ---------- Context bar: what Dlight AI is searching for now ---------- */
	function renderContext() {
		var pills = engine.context(state);
		els.ctx.hidden = !pills.length;
		els.ctx.innerHTML = pills.length ? '<span class="dla__ctxlabel">Showing:</span>' + pills.map(function (p) {
			return '<button type="button" class="dla__pill" data-key="' + esc(p[0]) + '" data-value="' + esc(p[1]) + '" aria-label="Remove ' + esc(p[1]) + '">' + esc(p[1]) + ' <span aria-hidden="true">&times;</span></button>';
		}).join('') : '';
	}
	function dropPill(key, value) {
		var r = engine.drop(key, value, state);
		if (!r) { return; }
		state = r.state;
		var label = 'Remove "' + value + '"';
		var plain = plainOf(r);
		history.push({ me: label }, { bot: plain });
		save();
		appendTurn(label, plain, true);
	}

	/* ---------- Messages ---------- */
	function card(c) {
		var p = engine.product(c.id);
		if (!p) { return ''; }
		var l = live[c.id] || {};
		var price = l.price != null ? l.price : p.p;
		var was = l.regular != null ? l.regular : (p.r || 0);
		var path = safePath(p.u);
		return '<article class="dla__card" data-card="' + p.i + '">' +
			'<a class="dla__img" data-pid="' + p.i + '" href="' + esc(path) + '">' +
			(p.m ? '<img loading="lazy" width="180" height="180" src="' + esc(safePath(p.m)) + '" alt="' + esc(p.n) + '">' : '') + '</a>' +
			'<a class="dla__name" data-pid="' + p.i + '" href="' + esc(path) + '">' + esc(p.n) + '</a>' +
			(c.why ? '<p class="dla__why">' + esc(c.why) + '</p>' : '') +
			'<p class="dla__price">' + (was > price ? '<del>' + money(was) + '</del> ' : '') + '<b>' + money(price) + '</b></p>' +
			'<a class="dla__add dl-add js-add" data-pid="' + p.i + '" data-id="' + p.i + '" rel="nofollow" href="?add-to-cart=' + encodeURIComponent(p.i) + '">Add to Cart</a>' +
			'</article>';
	}
	function compareTable(cmp) {
		var a = engine.product(cmp.a), b = engine.product(cmp.b);
		if (!a || !b) { return ''; }
		return '<div class="dla__tablewrap"><table class="dla__table"><thead><tr><th scope="col"><span class="dla__sr">Detail</span></th><th scope="col">' + esc(a.n) + '</th><th scope="col">' + esc(b.n) + '</th></tr></thead><tbody>' +
			cmp.rows.map(function (r) { return '<tr><th scope="row">' + esc(r[0]) + '</th><td>' + esc(r[1]) + '</td><td>' + esc(r[2]) + '</td></tr>'; }).join('') +
			'</tbody></table></div>';
	}

	function plainOf(r) {
		return { text: r.text, cards: r.cards, chips: r.chips, link: r.link, compare: r.compare, handoff: r.handoff, form: r.form, offerHelp: r.offerHelp, total: r.total };
	}

	// Each question and its answer form one block. Older blocks fold their product lists and lose their buttons.
	function settle() {
		var turns = els.log.querySelectorAll('.dla__turn');
		Array.prototype.forEach.call(turns, function (t, i) {
			if (i === turns.length - 1) { return; }
			t.classList.add('is-old');
			Array.prototype.forEach.call(t.querySelectorAll('.dla__chips,.dla__help'), function (x) { x.remove(); });
			var rail = t.querySelector('.dla__rail');
			if (rail && !rail.hidden && !rail.previousElementSibling.classList.contains('dla__expand')) {
				var n = rail.querySelectorAll('.dla__card').length;
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'dla__expand';
				btn.setAttribute('aria-expanded', 'false');
				btn.innerHTML = '<span>Show</span> ' + n + ' product' + (n === 1 ? '' : 's');
				rail.parentNode.insertBefore(btn, rail);
				rail.hidden = true;
			}
		});
	}

	function botBlock(turn, r) {
		// Paragraphs are separated by a blank line; lines starting with "• " become a list.
		var html = esc(r.text).split(/\n\n/).map(function (para) {
			var lines = para.split('\n'), head = [], items = [];
			lines.forEach(function (l) { if (/^• /.test(l)) { items.push('<li>' + l.slice(2).replace(/^([^:]{1,24}):/, '<b>$1:</b>') + '</li>'); } else { head.push(l); } });
			var p = head.length ? '<p>' + head.join('<br>') + '</p>' : '';
			return items.length ? '<div class="dla__bubble">' + p + '<ul>' + items.join('') + '</ul></div>' : p;
		}).join('');
		if (r.compare) { html += compareTable(r.compare); }
		if (r.link && r.link[1]) { html += '<a class="dla__link" href="' + esc(safePath(r.link[1])) + '">' + esc(r.link[0]) + ' &rarr;</a>'; }
		var d = document.createElement('div');
		d.className = 'dla__msg dla__msg--bot';
		d.innerHTML = html;
		turn.appendChild(d);
		if (r.cards && r.cards.length) {
			var rail = document.createElement('div');
			rail.className = 'dla__rail';
			rail.setAttribute('tabindex', '0');
			rail.setAttribute('aria-label', 'Products');
			rail.innerHTML = r.cards.map(card).join('');
			turn.appendChild(rail);
			refresh(r.cards.map(function (c) { return c.id; }), rail);
		}
		if (r.form) { showForm(turn, r.form); }
		if (r.handoff && C.handoff && !r.form) {
			var h = document.createElement('div');
			h.className = 'dla__help';
			h.innerHTML = '<a class="dla__wa" href="#" data-wa>Continue on WhatsApp</a>';
			turn.appendChild(h);
		}
		if (r.offerHelp) {
			var o = document.createElement('div');
			o.className = 'dla__help';
			o.innerHTML = '<p>Would you like help ordering? Our team can call you back.</p><button type="button" class="dla__ghost" data-lead="' + esc(JSON.stringify((r.cards || []).slice(0, 3).map(function (c) { return c.id; }))) + '">Leave my number</button>' + (C.handoff ? '<a class="dla__ghost" href="#" data-wa>Continue on WhatsApp</a>' : '');
			turn.appendChild(o);
		}
		if (r.chips && r.chips.length) {
			var chips = document.createElement('div');
			chips.className = 'dla__chips';
			chips.innerHTML = r.chips.map(function (c) { return '<button type="button" class="dla__chip">' + esc(c) + '</button>'; }).join('');
			turn.appendChild(chips);
		}
	}

	function appendTurn(question, r, animate) {
		var turn = document.createElement('section');
		turn.className = 'dla__turn';
		if (question) {
			var me = document.createElement('div');
			me.className = 'dla__msg dla__msg--me';
			me.innerHTML = '<p>' + esc(question) + '</p>';
			turn.appendChild(me);
		}
		els.log.appendChild(turn);
		settle();
		renderContext();
		function show() {
			botBlock(turn, r);
			// Start reading at the top of the new block, not at the bottom of a long answer.
			els.log.scrollTop = turn.offsetTop - els.log.offsetTop - 8;
		}
		if (animate) {
			var typing = document.createElement('div');
			typing.className = 'dla__typing';
			typing.innerHTML = '<span></span><span></span><span></span>';
			turn.appendChild(typing);
			els.log.scrollTop = turn.offsetTop - els.log.offsetTop - 8;
			setTimeout(function () { typing.remove(); show(); }, 320);
		} else {
			show();
		}
	}

	/* ---------- Contact form ---------- */
	function showForm(turn, form) {
		if (!turn || turn.querySelector('.dla__lead')) { return; }
		var f = document.createElement('form');
		f.className = 'dla__lead';
		var done = form.why === 'typed';
		f.innerHTML =
			(done ? '' : '<p class="dla__leadtitle">Leave your number and our team will contact you</p>') +
			'<label><span>Phone number' + (done ? '' : ' <b aria-hidden="true">*</b>') + '</span><input name="phone" type="tel" inputmode="tel" autocomplete="tel" maxlength="20" placeholder="07XX XXX XXX" ' + (done ? 'value="' + esc(form.phone) + '" readonly' : 'required') + '></label>' +
			'<label><span>Your name <small>(optional)</small></span><input name="name" autocomplete="name" maxlength="80"></label>' +
			'<label><span>Your location <small>(optional)</small></span><input name="location" autocomplete="address-level2" maxlength="120" placeholder="e.g. Westlands, Nairobi"></label>' +
			'<input name="website" tabindex="-1" autocomplete="off" class="dla__hp" aria-hidden="true">' +
			'<p class="dla__consent">We only use these details to contact you about this enquiry. <a href="/privacy-policy/">Privacy policy</a></p>' +
			'<div class="dla__leadbtns"><button type="submit" class="dla__send">' + (done ? 'Add details' : 'Request a call back') + '</button>' +
			(C.handoff ? '<a class="dla__ghost" href="#" data-wa>WhatsApp us instead</a>' : '') + '</div>' +
			'<p class="dla__err" role="alert" hidden></p>';
		turn.appendChild(f);
		var products = (form.products || []).join(',');
		if (done) { submitLead({ phone: form.phone, products: products, note: form.note || '', source: 'typed' }, f, true); }
		f.addEventListener('submit', function (e) {
			e.preventDefault();
			var data = { phone: f.phone.value, name: f.name.value, location: f.location.value, website: f.website.value, products: products, note: form.note || '', source: done ? 'typed' : 'form' };
			submitLead(data, f, false);
		});
	}
	function submitLead(data, f, quiet) {
		var err = f.querySelector('.dla__err');
		var btn = f.querySelector('.dla__send');
		data.s = session;
		btn.disabled = true;
		post(C.lead, data).then(function (res) {
			return res.json().then(function (j) { return { ok: res.ok, j: j, status: res.status }; });
		}).then(function (r) {
			btn.disabled = false;
			if (!r.ok) {
				err.hidden = false;
				err.textContent = r.j && r.j.error === 'phone' ? 'Please enter a valid phone number, for example 0712 345 678.' : (r.status === 429 ? 'Too many requests. Please try again later or WhatsApp us.' : 'Sorry, that did not go through. Please try again.');
				return;
			}
			err.hidden = true;
			if (quiet) { return; }
			var thanks = document.createElement('div');
			thanks.className = 'dla__msg dla__msg--bot';
			thanks.innerHTML = '<p>Thank you' + (data.name ? ', ' + esc(data.name.split(' ')[0]) : '') + '! We have saved your number, ' + esc(r.j.phone) + ', and our team will call or WhatsApp you shortly.' + (shopLine() ? ' You can also reach us on ' + esc(shopLine()) + '.' : '') + ' Meanwhile, feel free to keep browsing.</p>';
			f.replaceWith(thanks);
			history.push({ bot: { text: thanks.textContent } });
			state.leadOffered = 9;
			save();
		}).catch(function () {
			btn.disabled = false;
			err.hidden = false;
			err.textContent = 'You seem to be offline. Please try again.';
		});
	}

	/* ---------- Live prices ---------- */
	function refresh(ids, rail) {
		if (!C.store || !ids.length) { return; }
		fetch(C.store + 'products?per_page=' + ids.length + '&include=' + ids.join(',')).then(function (r) {
			return r.ok ? r.json() : null;
		}).then(function (items) {
			if (!Array.isArray(items)) { return; }
			var got = {};
			items.forEach(function (it) {
				var unit = Math.pow(10, it.prices.currency_minor_unit || 0);
				got[it.id] = 1;
				live[it.id] = { price: it.prices.price / unit, regular: it.prices.regular_price / unit };
				var el = rail.querySelector('[data-card="' + it.id + '"] .dla__price');
				if (el) {
					var l = live[it.id];
					el.innerHTML = (l.regular > l.price ? '<del>' + money(l.regular) + '</del> ' : '') + '<b>' + money(l.price) + '</b>';
				}
			});
			if (items.length) {
				ids.forEach(function (id) {
					var gone = !got[id] && rail.querySelector('[data-card="' + id + '"]');
					if (gone) { gone.remove(); }
				});
			}
		}).catch(function () { /* Keep the listed price if the check fails. */ });
	}

	/* ---------- Conversation ---------- */
	function ask(text) {
		var r = engine.reply(text, state);
		state = r.state;
		var plain = plainOf(r);
		history.push({ me: text }, { bot: plain });
		save();
		log(text, r);
		appendTurn(text, plain, true);
	}

	function greet() {
		var chips = engine.starters();
		if (C.product && engine.product(C.product)) {
			state = engine.focus(C.product, state);
			var p = engine.product(C.product);
			chips = ['Tell me about this ' + (p.k === 'w' ? 'watch' : 'perfume'), 'Show similar', 'I want to order this'].concat(chips.slice(1, 2));
		}
		var msg = { text: C.greeting || "Hi, I'm Dlight AI. What are you shopping for today?", chips: chips };
		history.push({ bot: msg });
		appendTurn('', msg, false);
		save();
	}

	function replay() {
		var pending = null;
		history.forEach(function (h) {
			if (h.me) { pending = h.me; return; }
			if (h.bot) {
				var b = h.bot;
				if (b.form && b.form.why === 'typed') { b = Object.assign({}, b, { form: null }); }
				appendTurn(pending, b, false);
				pending = null;
			}
		});
		els.log.scrollTop = els.log.scrollHeight;
	}

	function reset() {
		state = {};
		history = [];
		session = newSession();
		els.log.innerHTML = '';
		greet();
		els.input.focus();
	}

	function open(btn) {
		opener = btn;
		els.root.hidden = false;
		document.documentElement.classList.add('dla-open');
		if (btn) { btn.setAttribute('aria-expanded', 'true'); btn.hidden = true; }
		if (C.product && engine.product(C.product)) { state = engine.focus(C.product, state); }
		if (window.matchMedia('(min-width: 761px)').matches) { els.input.focus(); } else { els.root.querySelector('.dla__close').focus(); }
	}
	function close() {
		els.root.hidden = true;
		document.documentElement.classList.remove('dla-open');
		if (opener) { opener.hidden = false; opener.setAttribute('aria-expanded', 'false'); opener.focus(); }
	}

	restore();
	build();
	if (history.length) { replay(); } else { greet(); }
	window.DLAWidget = { open: open, close: close };
})();
