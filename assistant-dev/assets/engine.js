/*
 * Dlight Shopping Assistant engine. Runs in the shopper's browser with no outside AI service.
 * Every fact it states comes from the store's knowledge file. It never invents product details:
 * if the data does not say something, it says so.
 */
(function (root, factory) {
	if (typeof module === 'object' && module.exports) { module.exports = factory(); } else { root.DLAEngine = factory(); }
})(this, function () {
	'use strict';

	var STOP = words('a an the of to in on at for from with and or but is are was were be been am do does did have has had i me my we you your it its this that these those there here what which who whom how when where why can could would should will shall may might please pls plz kindly need want wants looking look find show see give get got tell let know like any some something anything one ones thing things item items product products good nice stuff also just really very so if about as by not no yes ok okay thanks hi hello hey sir madam dear buy buying sell selling shop store available have stock got recommend recommendation recommendations suggest suggestion suggestions option options choice choices help best top much many how price prices pricing cost costs stock similar alternative alternatives same instead compare comparison difference better contain contains containing include includes including smell smells smelling feature features note notes spec specs details detail info information describe description more less tell talk speak order ordering between under below over above around approx approximately roughly within budget maximum minimum max min than up upto least most ksh kes kshs sh shillings bob thousand price ones again else maybe still only other another them their his hers am im ive id isnt doesnt dont cant wont use using used wear wearing needs try trying think thinking say says said going go come coming make made take took put');
	var KIND = {
		p: words('perfume perfumes fragrance fragrances cologne colognes scent scents manukato parfum parfums edp edt spray'),
		w: words('watch watches timepiece timepieces wristwatch wristwatches saa')
	};
	var HER = words('her she women woman womens ladies lady ladys female females girlfriend gf wife mum mom mother mama sister daughter girl girls auntie aunt fiancee mke wanawake mwanamke dada bride');
	var HIM = words('him he men man mens gents gent male males boyfriend bf husband dad father baba brother son boy boys guy guys uncle fiance mume wanaume mwanaume kaka groom');
	var GIFT = words('gift gifts present presents gifting zawadi birthday anniversary valentine valentines christmas graduation wedding surprise');
	var SORT_LOW = words('cheap cheapest cheaper affordable lowest budget inexpensive rahisi');
	var SORT_HIGH = words('expensive luxury luxurious priciest premium highest');
	var SORT_POP = words('popular popularity bestseller bestsellers bestselling trending favourite favorite favourites favorites');
	var SORT_NEW = words('new newest latest recent arrivals');

	var NOTE_ALIAS = { oudh: 'oud', aoud: 'oud', oudy: 'oud', agarwood: 'oud', vanila: 'vanilla', roses: 'rose', flowery: 'floral', flower: 'floral', flowers: 'floral', woods: 'woody', wood: 'woody', citrusy: 'citrus', musky: 'musk', fruit: 'fruity', fruits: 'fruity', spice: 'spicy', spices: 'spicy', spiced: 'spicy', smoke: 'smoky', smokey: 'smoky', ocean: 'aquatic', sea: 'marine', leathery: 'leather', jasmin: 'jasmine', sugar: 'sweet', cedarwood: 'cedar', sandal: 'sandalwood', powder: 'powdery' };
	var FEAT_ALIAS = { analogue: 'analog', automatics: 'automatic', selfwinding: 'automatic', waterproof: 'waterresistant', stainless: 'steel', metal: 'steel', rubber: 'silicone', chrono: 'chronograph', leatherstrap: 'leather', golden: 'gold' };
	var PHRASES = [
		[/\beau de parfum\b/g, 'edp'], [/\beau de toilette\b/g, 'edt'], [/\brose[\s-]+gold\b/g, 'rosegold'], [/\bstainless steel\b/g, 'steel'],
		[/\bwater[\s-]*(resistant|resistance|proof)\b/g, 'waterresistant'], [/\bself[\s-]winding\b/g, 'automatic'], [/\blong[\s-]+(lasting|last|lasts)\b/g, 'longlasting'],
		[/\blast(s|ing)?\s+long(er)?\b/g, 'longlasting'], [/\bbest[\s-]+sell(er|ers|ing)\b/g, 'bestselling'], [/\bg[\s-]*shock\b/g, 'gshock'], [/\bday[\s/&-]+date\b/g, 'daydate'],
		[/\bfor (a |my )?(boy|girl) ?friend\b/g, function (m, a, g) { return g === 'boy' ? 'boyfriend' : 'girlfriend'; }], [/\bm[\s-]+pesa\b/g, 'mpesa'], [/\bd\s*&\s*g\b/g, 'dandg'], [/\bdo not\b/g, 'dont'], [/\bdon't\b/g, 'dont'], [/\bhigh[\s-]+end\b/g, 'luxury'],
		[/\bbei gani\b/g, 'how much'], [/\bngapi\b/g, 'how much'], [/\bbei\b/g, 'price']
	];
	var PRICE_WORDS = /\b(how much|price|prices|cost|costs|pricing)\b/;
	var STOCK_WORDS = /\b(in stock|available|availability|do you have|do you sell|do you stock|have you got|you got|iko|mnayo)\b/;
	var GREETING = /^(hi+|hello+|hey+|hola|good (morning|afternoon|evening)|habari|sasa|mambo|niaje|jambo|hujambo|howdy|greetings)\b[\s!.,?]*(there|team|dlight)?[\s!.,?]*$/;
	var THANKS = /^(thanks?|thank you|thx|asante|cool|great|perfect|nice|okay|ok|sawa|awesome|wow|good)\b[\s\w]{0,20}[!.]*$/;
	var BYE = /^(bye|goodbye|see you|kwaheri)\b/;
	var ORDINAL = { first: 0, '1st': 0, second: 1, '2nd': 1, third: 2, '3rd': 2, fourth: 3, '4th': 3, fifth: 4, '5th': 4, last: -1 };
	var PAGE = 6;
	var BUY = /\b(i('ll| will)? take (it|this|that|one)|i want (it|this|that|to buy|to order|this one|that one)|i('d| would) like to (buy|order|get)|i('m| am) (buying|ordering)|how (do|can) i (buy|order|get it|purchase)|buy (it|this|that|now)|order (it|this|that|now)|want to (buy|order|purchase)|ready to (buy|order)|place an order|reserve (it|this)|book (it|this)|nataka|ninataka|nitanunua)\b/;
	var UNSURE = /\b(not sure|unsure|confused|dont know|don'?t know which|cant decide|can'?t decide|help me decide|which (one )?should i|what do you recommend|undecided|sijui)\b/;
	var CALLME = /\b(call me|contact me|reach me|text me|whatsapp me|get back to me|my number is|my phone|nipigie|niite)\b/;
	var PHONE = /(?:\+?254|\b0)[\s-]?[17]\d{2}[\s-]?\d{3}[\s-]?\d{3}\b|\+\d[\d\s-]{7,14}\d/;
	var SMALLTALK = [
		[/\b(how are you|how r u|hows it going|how is it going|uko aje|uko poa|habari yako)\b/, "I'm doing well, thank you for asking! What can I help you find today?"],
		[/\b(just (looking|browsing)|only (looking|browsing)|window shopping)\b/, 'No problem, take your time. If you tell me who it is for or your budget, I can show you a few favourites.'],
		[/\b(you are (good|great|helpful|smart)|good job|well done|you('re| are) amazing)\b/, "Thank you! I'm happy to help. Is there anything else you'd like to see?"],
		[/\b(your name|who made you|what can you do|what do you do)\b/, "I'm Dlight AI, Dlight Giftshop's shopping assistant. I can find perfumes and watches by brand, notes, budget or who they're for, compare products, and answer questions about delivery, payment and returns."],
	];
	var OCCASION = [
		[/\b(office|work|everyday|daily|daytime|day time|school|campus|casual)\b/, ['fresh', 'citrus'], 'For daytime and office wear, many people prefer fresher, lighter scents. Here are perfumes whose descriptions mention fresh or citrus notes.'],
		[/\b(date|date night|night out|evening|dinner|romantic|seductive|sexy|club|party)\b/, ['vanilla', 'amber', 'oud'], 'For evenings and date nights, many people choose warmer scents. Here are perfumes whose descriptions mention vanilla, amber or oud.'],
		[/\b(wedding|bride|bridal)\b/, ['floral', 'rose', 'jasmine'], 'For weddings, many people go for floral scents. Here are perfumes whose descriptions mention floral, rose or jasmine notes.'],
		[/\b(gym|sport|sports|workout|hot weather|summer)\b/, ['fresh', 'citrus', 'aquatic'], 'For hot days and active wear, lighter fresh scents are a popular choice. Here are perfumes whose descriptions mention fresh, citrus or aquatic notes.'],
	];

	function words(s) { var o = {}; s.split(' ').forEach(function (w) { o[w] = 1; }); return o; }
	function has(o, k) { return Object.prototype.hasOwnProperty.call(o, k); }
	function title(s) { return s.replace(/(^|\s)\S/g, function (c) { return c.toUpperCase(); }); }
	function list(a) { return a.length < 2 ? a.join('') : a.slice(0, -1).join(', ') + ' and ' + a[a.length - 1]; }
	function money(n) { return 'KSh ' + Math.round(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ','); }
	function clone(o) { return JSON.parse(JSON.stringify(o || {})); }

	function norm(s) {
		return String(s || '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().replace(/[‘’]/g, "'").replace(/&amp;/g, ' and ').replace(/&/g, ' and ');
	}
	function clean(s) { return norm(s).replace(/[^a-z0-9\s'.,-]/g, ' ').replace(/\s+/g, ' ').trim(); }
	function tokens(s) { return clean(s).replace(/'s\b/g, '').replace(/'/g, '').replace(/[.,]/g, ' ').replace(/-/g, ' ').split(/\s+/).filter(Boolean); }

	// Edit distance, with an early exit once it cannot beat the limit.
	function dist(a, b, max) {
		if (Math.abs(a.length - b.length) > max) { return max + 1; }
		var prev = [], i, j, cur, best;
		for (j = 0; j <= b.length; j++) { prev[j] = j; }
		for (i = 1; i <= a.length; i++) {
			cur = [i]; best = i;
			for (j = 1; j <= b.length; j++) {
				cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + (a[i - 1] === b[j - 1] ? 0 : 1));
				if (i > 1 && j > 1 && a[i - 1] === b[j - 2] && a[i - 2] === b[j - 1]) { cur[j] = Math.min(cur[j], prev[j - 1]); }
				if (cur[j] < best) { best = cur[j]; }
			}
			if (best > max) { return max + 1; }
			prev = cur;
		}
		return prev[b.length];
	}
	function allow(len) { return len >= 8 ? 2 : (len >= 4 ? 1 : 0); }

	function create(data) {
		var products = data.products || [];
		var byId = {};
		var df = {};
		var noteSet = words((data.notes || []).join(' '));
		var featSet = words((data.features || []).join(' '));
		var brandAlias = [];
		var synonyms = data.synonyms || {};
		var answers = (data.answers || []).map(function (a) {
			return { id: a.id, m: a.m.map(clean), need: a.need ? a.need.map(clean) : null, a: a.a, link: a.link };
		});

		Object.keys(data.brands || {}).forEach(function (canon) {
			data.brands[canon].forEach(function (al) { brandAlias.push([al, canon]); });
		});
		var watchBrands = {};
		products.forEach(function (p) {
			byId[p.i] = p;
			p._name = ' ' + tokens(p.n).join(' ') + ' ';
			p._all = ' ' + tokens(p.n + ' ' + (p.d || '')).join(' ') + ' ';
			p._words = ' ' + (p.w || '') + ' ' + tokens(p.c || '').join(' ') + ' ';
			p._nt = tokens(p.n);
			var seen = {};
			p._nt.forEach(function (t) { if (!seen[t]) { seen[t] = 1; df[t] = (df[t] || 0) + 1; } });
			if (p.b && p.k === 'w') { watchBrands[p.b] = 1; }
		});
		Object.keys(watchBrands).forEach(function (b) { brandAlias.push([clean(b).replace(/,/g, ''), b]); });
		brandAlias.push(['michael kors', 'Michael Kors'], ['mk', 'Michael Kors'], ['fossil', 'Fossil']);
		brandAlias.sort(function (a, b) { return b[0].length - a[0].length; });

		// Words a typo can be corrected towards.
		var fuzzyPool = {};
		Object.keys(noteSet).forEach(function (w) { fuzzyPool[w] = 1; });
		Object.keys(featSet).forEach(function (w) { fuzzyPool[w] = 1; });
		Object.keys(KIND.p).concat(Object.keys(KIND.w)).forEach(function (w) { fuzzyPool[w] = 1; });
		brandAlias.forEach(function (b) { if (b[0].indexOf(' ') < 0 && b[0].length >= 4) { fuzzyPool[b[0]] = 1; } });
		Object.keys(df).forEach(function (w) { if (w.length >= 5 && /^[a-z]+$/.test(w) && !has(STOP, w)) { fuzzyPool[w] = 1; } });
		var fuzzyWords = Object.keys(fuzzyPool);
		var GENERIC_NAME = words('perfume perfumes watch watches men mens women womens ladies unisex edp edt parfum ml tester original new for with and the of de la le in on stainless steel leather strap analog digital quartz automatic chronograph gold silver black blue green white brown rose dial gents ladys classic sport sports fashion luxury dress case bracelet mesh silicone rubber band elegant minimal minimalist business casual');

		function correct(t) {
			if (t.length < 4 || /\d/.test(t) || has(STOP, t) || has(fuzzyPool, t) || has(df, t) || has(NOTE_ALIAS, t) || has(FEAT_ALIAS, t) || has(HER, t) || has(HIM, t) || has(GIFT, t) || has(SORT_LOW, t) || has(SORT_HIGH, t) || has(SORT_POP, t) || has(SORT_NEW, t)) { return t; }
			var max = allow(t.length), best = t, bestD = max + 1, i, w, d;
			for (i = 0; i < fuzzyWords.length; i++) {
				w = fuzzyWords[i];
				if (w[0] !== t[0] && max < 2) { continue; }
				d = dist(t, w, Math.min(max, bestD - 1 < 0 ? 0 : bestD - 1));
				if (d < bestD) { bestD = d; best = w; if (d === 1 && max === 1) { break; } }
			}
			return bestD <= max ? best : t;
		}

		function prepare(text) {
			var s = ' ' + clean(text) + ' ';
			Object.keys(synonyms).forEach(function (w) {
				var re = new RegExp('\\b' + clean(w).replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'g');
				s = s.replace(re, clean(synonyms[w]));
			});
			PHRASES.forEach(function (p) { s = s.replace(p[0], p[1]); });
			return s.replace(/\s+/g, ' ').trim();
		}

		function parsePrice(s, out) {
			var num = '(?:ksh\\.?|kes|kshs|sh\\.?|shillings?)?\\s*(\\d[\\d,]*(?:\\.\\d+)?)\\s*(k|bob|thousand)?\\b(?!\\s*ml)';
			var m = s.match(new RegExp('\\bbetween\\s+' + num + '\\s+(?:and|to)\\s+' + num)) || s.match(new RegExp('\\b' + num + '\\s*(?:-|to)\\s*' + num));
			function val(a, b) {
				var v = parseFloat(String(a).replace(/,/g, ''));
				if (b === 'k' || b === 'thousand') { v *= 1000; }
				return v;
			}
			if (m) {
				var lo = val(m[1], m[2]), hi = val(m[3], m[4]);
				if (hi >= 100 && lo < hi) { out.min = lo < 100 ? lo * 1000 : lo; out.max = hi; return true; }
			}
			m = s.match(new RegExp('\\b(?:under|below|less than|cheaper than|not more than|no more than|max|maximum|up to|upto|within|at most|budget(?: is| of)?|chini ya)\\s+' + num));
			if (m && val(m[1], m[2]) >= 100) { out.max = val(m[1], m[2]); return true; }
			m = s.match(new RegExp('\\b(?:over|above|more than|at least|min|minimum|from|zaidi ya|starting at)\\s+' + num));
			if (m && val(m[1], m[2]) >= 100) { out.min = val(m[1], m[2]); return true; }
			m = s.match(new RegExp('\\b(?:around|about|approx|approximately|roughly|near|close to)\\s+' + num));
			if (m && val(m[1], m[2]) >= 100) { out.min = val(m[1], m[2]) * 0.8; out.max = val(m[1], m[2]) * 1.2; return true; }
			m = s.match(/\b(?:ksh\.?|kes|kshs|sh\.?)\s*(\d[\d,]*)\s*(k)?\b/) || s.match(/\b(\d[\d,]*)\s*(k|bob|thousand|shillings?)\b/) || s.match(/(?:^|\s)(\d{4,6})(?:\s|$)/);
			if (m && !/ml\b/.test(s.slice(m.index, m.index + m[0].length + 3)) && val(m[1], m[2]) >= 500) { out.max = val(m[1], m[2]); return true; }
			return false;
		}

		function findBrands(s) {
			var found = [], rest = ' ' + s + ' ';
			brandAlias.forEach(function (b) {
				var re = new RegExp('\\b' + b[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b');
				if (re.test(rest)) {
					if (found.indexOf(b[1]) < 0) { found.push(b[1]); }
					rest = rest.replace(re, ' ');
				}
			});
			return found;
		}

		/* Named products: a phrase from the question that points to only a few product names. */
		function findNamed(toks) {
			var best = [], bestLen = 0;
			var usable = toks.map(function (t) { return { t: t, stop: has(STOP, t) }; });
			for (var n = Math.min(6, toks.length); n >= 1 && !best.length; n--) {
				for (var i = 0; i + n <= toks.length; i++) {
					var gram = toks.slice(i, i + n);
					if (usable[i].stop || usable[i + n - 1].stop) { continue; }
					var meaningful = gram.filter(function (t) {
						return !/^\d+$/.test(t) && !has(GENERIC_NAME, t) && !has(noteSet, t) && !has(STOP, t) && !has(HER, t) && !has(HIM, t) && !has(KIND.p, t) && !has(KIND.w, t) && !has(GIFT, t) && !isBrandToken(t);
					});
					if (!meaningful.length && !(n >= 2 && gram.some(function (t) { return has(noteSet, t); }) && gram.some(isDistinct))) { continue; }
					var phrase = ' ' + gram.join(' ') + ' ';
					var hits = products.filter(function (p) { return p._name.indexOf(phrase) >= 0; });
					if (hits.length && hits.length <= 12 && (n > bestLen || (n === bestLen && hits.length < best.length))) {
						best = hits; bestLen = n;
					}
				}
			}
			best.sort(function (a, b) { return (b.q || 0) - (a.q || 0); });
			return best;
		}
		function isDistinct(t) { return !has(GENERIC_NAME, t) && !has(STOP, t) && (df[t] || 0) <= 12; }
		function isBrandToken(t) {
			return brandAlias.some(function (b) { return b[0] === t || b[0].split(' ').indexOf(t) >= 0; });
		}

		function parse(text) {
			var s = prepare(text);
			var slots = {};
			parsePrice(s, slots);
			// Bare numbers and "100ml" stay out of the search words, but model numbers like "NF5073" stay in.
			var noPrice = s.replace(/\b(?:ksh\.?|kes|kshs|sh\.?)\s*\d[\d,]*\s*k?\b/g, ' ').replace(/\b\d[\d,]*\s*(k|bob|thousand|shillings?)\b/g, ' ').replace(/\b(?:under|below|over|above|around|about|between|from|up to|less than|more than)\s+\d[\d,]*\b/g, ' ');
			var brands = findBrands(noPrice);
			if (brands.length) { slots.brands = brands; }
			var raw = tokens(noPrice);
			var toks = raw.map(correct);
			var notes = [], feats = [], rest = [], who = {}, kind = '';
			toks.forEach(function (t, i) {
				var key = has(NOTE_ALIAS, t) ? NOTE_ALIAS[t] : t;
				var fkey = has(FEAT_ALIAS, t) ? FEAT_ALIAS[t] : t;
				if (has(HER, t)) { who.f = 1; }
				else if (has(HIM, t)) { who.m = 1; }
				else if (has(KIND.p, t)) { kind = kind && kind !== 'p' ? 'x' : 'p'; if (t === 'edp' || t === 'edt') { slots.conc = t.toUpperCase(); } }
				else if (has(KIND.w, t)) { kind = kind && kind !== 'w' ? 'x' : 'w'; }
				else if (t === 'unisex') { slots.unisex = 1; }
				else if (t === 'tester' || t === 'testers') { slots.type = 't'; }
				else if (t === 'arabic' || t === 'arabian' || t === 'arab') { slots.type = 'a'; }
				else if (t === 'original' || t === 'originals') { slots.type = 'o'; }
				else if (has(SORT_LOW, t)) { slots.sort = 'low'; }
				else if (has(SORT_HIGH, t)) { slots.sort = 'high'; }
				else if (has(SORT_POP, t) || t === 'bestselling') { slots.sort = 'pop'; }
				else if (has(SORT_NEW, t)) { slots.sort = 'new'; }
				else if (t === 'gshock') { rest.push('shock'); }
				else if (has(featSet, fkey) && !(has(noteSet, key) && (kind === 'p'))) { feats.push(fkey); if (has(noteSet, key)) { notes.push(key); } }
				else if (has(noteSet, key)) { notes.push(key); }
				else if (!has(STOP, t) && !/^\d+$/.test(t) && !has(GIFT, t) && t !== 'longlasting' && !isBrandToken(t)) { rest.push(t); }
			});
			if (who.f && !who.m) { slots.gender = 'f'; } else if (who.m && !who.f) { slots.gender = 'm'; }
			if (kind === 'p' || kind === 'w') { slots.kind = kind; }
			if (notes.length) { slots.notes = uniq(notes); }
			if (feats.length) { slots.feats = uniq(feats); }
			if (rest.length) { slots.rest = uniq(rest); }
			if (toks.some(function (t) { return has(GIFT, t); })) { slots.gift = 1; }
			if (s.indexOf('longlasting') >= 0) { slots.longlasting = 1; }
			return { s: s, toks: toks, raw: raw, slots: slots };
		}
		function uniq(a) { return a.filter(function (v, i) { return a.indexOf(v) === i; }); }

		function genderOk(p, g) { return !g || p.g === g || p.g === 'u' || !p.g; }

		/* Search products, and record why each one matched. */
		function search(q) {
			var out = [];
			products.forEach(function (p) {
				if (q.kind && p.k !== q.kind) { return; }
				if (q.gender && !genderOk(p, q.gender)) { return; }
				if (q.unisex && p.g !== 'u') { return; }
				if (q.type && p.t !== q.type) { return; }
				if (q.conc && p.y !== q.conc) { return; }
				if (q.max && p.p > q.max) { return; }
				if (q.min && p.p < q.min) { return; }
				if (q.exclude && q.exclude.indexOf(p.i) >= 0) { return; }
				if (q.brands && q.brands.indexOf(p.b) < 0 && !q.brands.some(function (b) { return p._all.indexOf(' ' + tokens(b).join(' ') + ' ') >= 0; })) { return; }
				var score = 0, why = '';
				var i, n;
				if (q.anyNotes) {
					var one = q.anyNotes.filter(function (n) { return p._all.indexOf(' ' + n + ' ') >= 0 || (p.o && p.o.indexOf(n) >= 0); });
					if (!one.length) { return; }
					score += one.length * 4;
				}
				if (q.notes) {
					var missing = false;
					for (i = 0; i < q.notes.length; i++) {
						n = q.notes[i];
						var inName = p._name.indexOf(' ' + n + ' ') >= 0;
						var inDesc = p._all.indexOf(' ' + n + ' ') >= 0;
						var listed = p.o && p.o.indexOf(n) >= 0;
						if (!inName && !inDesc && !listed) { missing = true; break; }
						score += inName ? 12 : (inDesc ? 9 : 5);
						if (!why) {
							if (inDesc && p.d) { why = p.d; }
							else if (p.x && p.x[n]) { why = p.x[n]; }
						}
					}
					if (missing) { return; }
				}
				if (q.feats) {
					for (i = 0; i < q.feats.length; i++) {
						if (!p.f || p.f.indexOf(q.feats[i]) < 0) { return; }
						score += 4;
					}
				}
				if (q.rest && q.rest.length) {
					var matched = 0;
					q.rest.forEach(function (t) {
						if (p._all.indexOf(' ' + t + ' ') >= 0) { matched += 2; }
						else if (p._words.indexOf(' ' + t + ' ') >= 0) { matched += 1; }
					});
					if (!matched) { return; }
					score += matched * 3;
				}
				score += Math.log(1 + (p.q || 0)) * 1.4;
				out.push({ p: p, score: score, why: why });
			});
			var sort = q.sort;
			out.sort(function (a, b) {
				if (sort === 'low') { return a.p.p - b.p.p; }
				if (sort === 'high') { return b.p.p - a.p.p; }
				if (sort === 'new') { return (b.p.a || 0) - (a.p.a || 0); }
				if (sort === 'pop') { return (b.p.q || 0) - (a.p.q || 0) || b.score - a.score; }
				return b.score - a.score || (b.p.q || 0) - (a.p.q || 0);
			});
			return out;
		}

		function context(st) {
			var q = st.q;
			if (!q) { return []; }
			var pills = [];
			if (q.kind) { pills.push(['kind', q.kind === 'p' ? 'Perfumes' : 'Watches']); }
			if (q.gender) { pills.push(['gender', q.gender === 'f' ? 'For her' : 'For him']); }
			(q.brands || []).forEach(function (b) { pills.push(['brands', b]); });
			(q.notes || []).forEach(function (n) { pills.push(['notes', title(n)]); });
			(q.feats || []).forEach(function (f) { pills.push(['feats', title(featLabel(f))]); });
			if (q.type) { pills.push(['type', { t: 'Tester', a: 'Arabic', o: 'Original' }[q.type]]); }
			if (q.max && q.min) { pills.push(['price', money(q.min) + ' - ' + money(q.max)]); }
			else if (q.max) { pills.push(['price', 'Under ' + money(q.max)]); }
			else if (q.min) { pills.push(['price', 'Over ' + money(q.min)]); }
			if (q.sort === 'low') { pills.push(['sort', 'Cheapest first']); }
			return pills;
		}
		function drop(key, value, state) {
			var st = clone(state);
			if (!st.q) { return null; }
			var q = clone(st.q);
			if (key === 'price') { delete q.max; delete q.min; }
			else if (Array.isArray(q[key])) { q[key] = q[key].filter(function (v) { return (key === 'notes' || key === 'feats' ? title(key === 'feats' ? featLabel(v) : v) : v) !== value; }); if (!q[key].length) { delete q[key]; } }
			else { delete q[key]; }
			delete q.anyNotes;
			var r = answerSearch(q, st);
			r.state = st;
			return r;
		}

		function describeQuery(q, plural) {
			var bits = [];
			var kind = q.kind === 'p' ? 'perfume' : (q.kind === 'w' ? 'watch' : 'product');
			if (plural) { kind = q.kind === 'w' ? 'watches' : kind + 's'; }
			var s = kind;
			if (q.rest && q.rest.length && !/long-lasting/.test(q.rest[0])) { s = q.rest.map(title).join(' ') + ' ' + s; }
			if (q.rest && /long-lasting/.test(q.rest[0])) { s = 'long-lasting ' + s; }
			if (q.feats) { s = q.feats.map(featLabel).join(', ') + ' ' + s; }
			if (q.notes) { s = list(q.notes.map(title)) + ' ' + s; }
			if (q.brands) { s = list(q.brands) + ' ' + s; }
			if (q.type) { s = { t: 'tester', a: 'Arabic', o: 'original' }[q.type] + ' ' + s; }
			if (q.unisex) { s = 'unisex ' + s; }
			bits.push(s);
			if (q.gender) { bits.push(q.gender === 'f' ? 'for her' : 'for him'); }
			if (q.min && q.max) { bits.push('between ' + money(q.min) + ' and ' + money(q.max)); }
			else if (q.max) { bits.push('under ' + money(q.max)); }
			else if (q.min) { bits.push('over ' + money(q.min)); }
			return bits.join(' ');
		}
		function featLabel(f) { return { rosegold: 'rose gold', waterresistant: 'water-resistant', steel: 'stainless steel', daydate: 'day-date', silicone: 'rubber or silicone' }[f] || f; }

		function shopLink(q) {
			if (q.kind && q.gender && !q.notes && !q.feats && !q.brands && !q.rest && !q.type && !q.max && !q.min) {
				return ['See all ' + describeQuery(q).replace(/^(perfume|watch)/, function (m) { return m + (m === 'watch' ? 'es' : 's'); }), '/shop/?dl_for=' + (q.gender === 'f' ? 'her' : 'him') + '&dl_kind=' + (q.kind === 'p' ? 'perfume' : 'watch')];
			}
			if (q.brands && q.brands.length === 1 && !q.notes && !q.feats) {
				return ['Search ' + q.brands[0], '/?s=' + encodeURIComponent(q.brands[0]) + '&post_type=product'];
			}
			if (q.notes && q.notes.length === 1) {
				return ['Search "' + q.notes[0] + '" in the shop', '/?s=' + encodeURIComponent(q.notes[0]) + '&post_type=product'];
			}
			return null;
		}

		function cardsFrom(results, st, offset) {
			offset = offset || 0;
			var page = results.slice(offset, offset + PAGE);
			st.last = page.map(function (r) { return r.p.i; });
			st.lastOffset = offset;
			return page.map(function (r) { return { id: r.p.i, why: r.why || '' }; });
		}

		function refineChips(q, total) {
			var c = [];
			if (total > PAGE) { c.push('Show more'); }
			if (!q.max && !q.sort) { c.push('Show cheapest first'); }
			if (q.kind === 'p' && !q.gender) { c.push('For her', 'For him'); }
			if (q.kind === 'w' && !q.gender) { c.push("Men's", "Women's"); }
			if (!q.max && total > 3) { c.push('Under KSh 5,000'); }
			return c.slice(0, 4);
		}

		function answerSearch(q, st) {
			st.q = q; st.offset = 0;
			var res = search(q);
			if (!res.length) { return relax(q, st); }
			var total = res.length;
			var intro;
			// When only one kind of product matched, say so: "Curren watches", not "Curren products".
			var shown = clone(q);
			if (!shown.kind && res.every(function (r) { return r.p.k === res[0].p.k; }) && res[0].p.k !== 'o') { shown.kind = res[0].p.k; }
			intro = 'I found ' + total + ' ' + describeQuery(shown, total !== 1) + ' in stock.';
			if (q.notes && q.notes.length) {
				intro += (total === 1 ? ' It mentions ' : ' These mention ') + list(q.notes.map(title)) + ' in the name or description.';
			}
			if (q.sort === 'low') { intro += ' Cheapest first.'; }
			if (q.sort === 'high') { intro += ' Highest price first.'; }
			if (q.sort === 'pop' || (!q.sort && !q.notes && !q.rest && !q.feats)) { intro += ' Our most ordered come first.'; }
			if (q.sort === 'new') { intro += ' Newest first.'; }
			return { text: intro, cards: cardsFrom(res, st), chips: refineChips(q, total), link: shopLink(q), answered: true, total: total };
		}

		// Nothing matched: say so plainly, then offer the closest real alternative.
		function relax(q, st) {
			var tryDrop = [];
			if (q.max || q.min) { tryDrop.push(['price', function (x) { delete x.max; delete x.min; }]); }
			if (q.gender) { tryDrop.push(['gender', function (x) { delete x.gender; }]); }
			if (q.feats && q.feats.length) { tryDrop.push(['feature', function (x) { delete x.feats; }]); }
			if (q.notes && q.notes.length > 1) { tryDrop.push(['note', function (x) { x.notes = x.notes.slice(0, 1); }]); }
			if (q.type) { tryDrop.push(['type', function (x) { delete x.type; }]); }
			if (q.rest && q.rest.length) { tryDrop.push(['word', function (x) { delete x.rest; }]); }
			for (var i = 0; i < tryDrop.length; i++) {
				var alt = clone(q);
				tryDrop[i][1](alt);
				if (tryDrop[i][0] === 'price') { alt.sort = q.max ? 'low' : 'high'; }
				var res = search(alt);
				if (res.length && (alt.notes || alt.feats || alt.brands || alt.kind || alt.rest)) {
					st.q = alt; st.offset = 0;
					var lead = 'I could not find any ' + describeQuery(q, true) + ' in stock right now.';
					if (tryDrop[i][0] === 'price') {
						lead += q.max ? ' The lowest price I have for ' + describeQuery(alt, true) + ' is ' + money(res[0].p.p) + '.' : ' The highest price I have for ' + describeQuery(alt, true) + ' is ' + money(res[0].p.p) + '.';
					} else {
						lead += ' Here is what I do have: ' + describeQuery(alt, true) + '.';
					}
					return { text: lead, cards: cardsFrom(res, st), chips: refineChips(alt, res.length), link: shopLink(alt), answered: true, total: res.length };
				}
			}
			st.q = null;
			return { text: 'I could not find any ' + describeQuery(q, true) + ' in stock right now. Try different words, or browse a category.', chips: ['Best-selling perfumes', 'Best-selling watches', 'Help me choose a gift'], answered: false, total: 0 };
		}

		function productFacts(p) {
			var facts = [];
			if (p.k === 'p') {
				if (p.y) { facts.push(p.y); }
				if (p.z) { facts.push(p.z + 'ml'); }
				if (p.t) { facts.push({ t: 'Tester', a: 'Arabic', o: 'Original' }[p.t]); }
			}
			if (p.g) { facts.push({ m: 'For men', f: 'For women', u: 'Unisex' }[p.g]); }
			return facts;
		}

		function notesOf(p, max) {
			return (p.o || []).filter(function (n) { return n !== 'fresh' && n !== 'green' && n !== 'sweet'; }).slice(0, max || 6);
		}

		function answerProduct(named, parsed, st) {
			var p = named[0], s = parsed.s, sl = parsed.slots;
			st.focus = p.i; st.last = named.slice(0, PAGE).map(function (x) { return x.i; });
			var card = [{ id: p.i, why: '' }];
			var many = named.length > 1 ? named.slice(0, PAGE).map(function (x) { return { id: x.i, why: '' }; }) : card;
			var chips = ['Is it in stock?', 'What notes does it have?', 'Show similar'];
			if (p.k === 'w') { chips = ['Is it in stock?', 'What are its features?', 'Show similar']; }

			if (/\b(similar|alternative|alternatives|like|same as|instead of)\b/.test(s) && !/\b(i like|would like|like to|smells? like)\b/.test(s)) { return answerSimilar(p, st); }
			// "Oud" inside the name "Oud Wood" is not a question about oud, unless it is asked: "does Oud Wood have oud?"
			var asked = (sl.notes || []).filter(function (n) {
				var inName = p._nt.some(function (t) { return t === n || NOTE_ALIAS[t] === n; });
				return !inName || new RegExp('\\b(have|has|contain|contains|include|includes|with|smell|smells)\\b.*\\b' + n + '\\b').test(s);
			});
			if (asked.length && p.k === 'p') {
				var word = asked[0];
				var inName = (p.n + ' ' + (p.d || '')).toLowerCase().indexOf(word) >= 0;
				var hit = inName || (p.o && p.o.indexOf(word) >= 0);
				if (hit) {
					var quote = p.x && p.x[word] ? ' The description says: "' + p.x[word] + '"' : '';
					return { text: 'Yes, ' + p.n + ' mentions ' + word + '.' + quote, cards: card, chips: chips, answered: true, total: 1 };
				}
				var others = notesOf(p);
				return { text: 'The description for ' + p.n + ' does not mention ' + word + '.' + (others.length ? ' It mentions ' + list(others) + '.' : ''), cards: card, chips: ['Show ' + word + ' perfumes', 'Show similar'], answered: true, total: 1 };
			}
			if (/\b(original|genuine|authentic|fake|real|legit)\b/.test(s)) {
				var t = p.t === 't'
					? 'Yes. ' + p.n + ' is listed as a tester: it contains the same original fragrance as the retail bottle, with the same scent and quality. The difference is usually the packaging, such as a plain box or a tester label.'
					: 'Yes, ' + p.n + ' is original.';
				return { text: t, cards: card, chips: chips, answered: true, total: 1 };
			}
			if (PRICE_WORDS.test(s)) {
				var off = p.r > p.p ? ' (was ' + money(p.r) + ')' : '';
				return { text: p.n + ' is ' + money(p.p) + off + '.', cards: many.slice(0, 3), chips: chips, answered: true, total: many.length };
			}
			// "Which notes does it have" is about notes, not stock, even though it says "have".
			if (DETAIL_WORDS.test(s) && !/\b(stock|available|availability)\b/.test(s)) {
				return answerDetail(p, card, chips, st);
			}
			if (/\b(stock|available|availability|have|sell|got|iko|mnayo)\b/.test(s)) {
				return { text: 'Yes, ' + p.n + ' is currently in stock at ' + money(p.p) + '.', cards: many.slice(0, 3), chips: chips, answered: true, total: many.length };
			}
			if (sl.longlasting || /\b(longevity|projection|strong|how long)\b/.test(s)) {
				return answerLongevity(p, card, chips);
			}
			if (/\b(unisex|for men|for women|for him|for her|male|female|gender|ladies|gents)\b/.test(s) && p.g) {
				return { text: p.n + ' is listed as ' + { m: 'for men', f: 'for women', u: 'unisex' }[p.g] + '.', cards: card, chips: chips, answered: true, total: 1 };
			}
			if (DETAIL_WORDS.test(s)) {
				return answerDetail(p, card, chips, st);
			}
			var text = named.length > 1 ? 'I found ' + named.length + ' products matching that. Here they are.' : summary(p);
			if (named.length === 1 && p.hl) { st.hlShown = p.i; }
			return { text: text, cards: many, chips: chips, answered: true, total: named.length };
		}

		function summary(p) {
			var facts = productFacts(p);
			var t = p.n + (p.d ? ': ' + p.d : '') + '.';
			if (facts.length) { t += ' ' + facts.join(', ') + '.'; }
			t += ' ' + money(p.p) + '.';
			// The sentence that describes it best, or the description's opening line.
			var about = p.hl || p.s;
			if (about) { t += '\n\n' + about; }
			if (p.k === 'p' || p.k === 'w') { t += '\n\nAsk me about its ' + (p.k === 'p' ? 'notes' : 'features') + ', or whether it is right for you.'; }
			return t;
		}

		// A short, structured summary from the product's own description:
		// a perfume's notes (top, heart and base when listed) or a watch's key specifications,
		// then the sentence that describes it best, then who it is for and the price.
		function nice(w) { return String(w).replace(/\b[a-z]/g, function (c) { return c.toUpperCase(); }); }
		function answerDetail(p, card, chips, st) {
			var parts = [], tail = [];
			if (p.k === 'w') {
				var specs = (p.sp || []).slice(0, 7);
				if (!specs.length) { specs = (p.f || []).map(featLabel).map(function (f) { return nice(f); }); }
				parts.push(specs.length ? 'Key features of ' + p.n + ':\n' + specs.map(function (s) { return '• ' + s; }).join('\n') : 'Here is what the listing says about ' + p.n + '.');
			} else {
				var np = p.np || {};
				if (np.t || np.h || np.b) {
					var rows = [];
					if (np.t) { rows.push('• Top: ' + list(np.t.map(nice))); }
					if (np.h) { rows.push('• Heart: ' + list(np.h.map(nice))); }
					if (np.b) { rows.push('• Base: ' + list(np.b.map(nice))); }
					parts.push('The notes of ' + p.n + ':\n' + rows.join('\n'));
				} else {
					var main = (p.mn && p.mn.length ? p.mn : notesOf(p, 6)).slice(0, 6);
					parts.push(main.length ? 'The main notes of ' + p.n + ' are ' + list(main) + '.' : 'The listing for ' + p.n + ' does not name specific notes.');
				}
			}
			// The describing sentence once per product: not again if the last answer already showed it.
			if (p.hl && !(st && st.hlShown === p.i)) { parts.push(p.hl); }
			if (st) { st.hlShown = p.i; }
			if (p.k === 'p' && p.g) { tail.push({ m: 'A fragrance for men', f: 'A fragrance for women', u: 'A unisex fragrance' }[p.g]); }
			if (p.t === 't') { tail.push('this is a tester (same original fragrance, simpler packaging)'); }
			tail.push((tail.length ? 'now ' : 'Now ') + money(p.p) + (p.r > p.p ? ' (was ' + money(p.r) + ')' : ''));
			parts.push(tail.join(', ') + '.');
			return { text: parts.join('\n\n'), cards: card, chips: p.k === 'w' ? ['Is it in stock?', 'Show similar', 'I want to buy it'] : ['Is it long lasting?', 'Show similar', 'I want to buy it'], answered: true, total: 1 };
		}

		function answerLongevity(p, card, chips) {
			var says = /long[\s-]?lasting|longevity/.test((p.w || '') + ' ' + (p.s || '').toLowerCase());
			return {
				text: (says ? 'The description of ' + p.n + ' describes it as long lasting. ' : 'The listing for ' + p.n + ' does not give a specific lasting time. ') + 'How long any perfume lasts varies with skin, climate and the number of sprays.',
				cards: card, chips: chips, answered: true, total: 1
			};
		}

		function answerSimilar(p, st) {
			var mine = notesOf(p, 12), feats = p.f || [];
			var scored = products.filter(function (x) { return x.i !== p.i && x.k === p.k && genderOk(x, p.g === 'u' ? '' : p.g); }).map(function (x) {
				var shared = (p.k === 'p' ? notesOf(x, 12).filter(function (n) { return mine.indexOf(n) >= 0; }) : (x.f || []).filter(function (f) { return feats.indexOf(f) >= 0; }));
				var close = 1 - Math.min(1, Math.abs(x.p - p.p) / Math.max(p.p, 1));
				return { p: x, shared: shared, score: shared.length * 3 + close * 2 + Math.log(1 + (x.q || 0)) * 0.5 };
			}).filter(function (r) { return r.shared.length >= (p.k === 'p' ? 2 : 3); });
			scored.sort(function (a, b) { return b.score - a.score; });
			if (!scored.length) { return { text: 'I could not find a close match for ' + p.n + ' based on its description. Would you like to browse more?', chips: [p.k === 'p' ? 'Best-selling perfumes' : 'Best-selling watches'], answered: true, total: 0 }; }
			st.q = null;
			var page = scored.slice(0, PAGE);
			st.last = page.map(function (r) { return r.p.i; });
			return {
				text: 'Here are products with descriptions closest to ' + p.n + '. I matched them on shared ' + (p.k === 'p' ? 'notes' : 'features') + ', and you can see what they share on each card.',
				cards: page.map(function (r) { return { id: r.p.i, why: 'Shares: ' + list(r.shared.slice(0, 4).map(p.k === 'p' ? String : featLabel)) }; }),
				answered: true, total: page.length
			};
		}

		function answerCompare(a, b, st) {
			st.last = [a.i, b.i];
			var rows = [['Price', money(a.p), money(b.p)], ['Listed for', { m: 'Men', f: 'Women', u: 'Unisex' }[a.g] || '-', { m: 'Men', f: 'Women', u: 'Unisex' }[b.g] || '-']];
			if (a.k === 'p' || b.k === 'p') {
				rows.push(['Type', a.y || '-', b.y || '-'], ['Size', a.z ? a.z + 'ml' : '-', b.z ? b.z + 'ml' : '-']);
				rows.push(['Mentions', list(notesOf(a, 5)) || '-', list(notesOf(b, 5)) || '-']);
				var shared = notesOf(a, 12).filter(function (n) { return notesOf(b, 12).indexOf(n) >= 0; });
				if (shared.length) { rows.push(['In common', list(shared.slice(0, 5)), list(shared.slice(0, 5))]); }
			} else {
				rows.push(['Features', list((a.f || []).slice(0, 6).map(featLabel)) || '-', list((b.f || []).slice(0, 6).map(featLabel)) || '-']);
			}
			var diff = Math.abs(a.p - b.p);
			var lead = 'Here is how they compare, using their listings.' + (diff ? ' ' + (a.p < b.p ? a.n : b.n) + ' costs ' + money(diff) + ' less.' : ' They cost the same.') + ' Which is better comes down to personal taste.';
			return { text: lead, compare: { a: a.i, b: b.i, rows: rows }, cards: [{ id: a.i, why: '' }, { id: b.i, why: '' }], answered: true, total: 2 };
		}

		function tryCompare(parsed, st) {
			var s = parsed.s;
			if (!/\b(compare|comparison|vs|versus|difference between|better|or)\b/.test(s)) { return null; }
			var cleaned = s.replace(/\b(compare|comparison|difference between|which is better|which one is better|what is better|whats better|better)\b/g, ' ');
			var parts = cleaned.split(/\b(?:vs\.?|versus|and|or|with|to)\b/).map(function (x) { return x.trim(); }).filter(Boolean);
			var found = [];
			parts.forEach(function (part) {
				var named = findNamed(tokens(part).map(correct));
				if (named.length && found.indexOf(named[0]) < 0) { found.push(named[0]); }
			});
			if (found.length >= 2) { return answerCompare(found[0], found[1], st); }
			if (/\b(compare|vs|versus|difference between)\b/.test(s) && !/\btester\b/.test(s)) {
				return { text: 'Tell me the two products you would like to compare, for example "Compare Dior Sauvage and Creed Aventus".', chips: ['Compare Dior Sauvage and Creed Aventus'], answered: true, total: 0 };
			}
			return null;
		}

		function matchAnswer(s) {
			var padded = ' ' + s.replace(/[.,]/g, ' ').replace(/\s+/g, ' ') + ' ';
			// The longest matching phrase wins, so "pay on delivery" beats "delivery".
			var best = null, bestLen = 0;
			for (var i = 0; i < answers.length; i++) {
				var a = answers[i];
				var len = 0;
				a.m.forEach(function (m) { if (m.length > len && padded.indexOf(' ' + m + ' ') >= 0) { len = m.length; } });
				if (len && a.need && !a.need.some(function (m) { return padded.indexOf(' ' + m) >= 0; })) { len = 0; }
				if (len > bestLen) { best = a; bestLen = len; }
			}
			return best;
		}

		function giftFlow(q, st) {
			st.gift = q;
			if (!q.gender) { return { text: 'Lovely! Is the gift for a man or a woman?', chips: ['For her', 'For him'], answered: true, total: 0 }; }
			if (!q.kind && !q.notes && !q.feats && !q.brands) { return { text: 'Would you like to give a perfume or a watch?', chips: ['A perfume', 'A watch', 'Show me both'], answered: true, total: 0 }; }
			if (!q.max && !q.min && !q.sort && !st.giftBudgetAsked) {
				st.giftBudgetAsked = 1;
				return { text: 'What is your budget?', chips: ['Under KSh 5,000', 'KSh 5,000 to 10,000', 'KSh 10,000 to 20,000', 'Any budget'], answered: true, total: 0 };
			}
			st.gift = null; st.giftBudgetAsked = 0;
			var showKind = q.kind;
			var r = answerSearch(q, st);
			if (r.total && !showKind) { r.text = 'Here are some gift ideas ' + (q.gender === 'f' ? 'for her' : 'for him') + '. Our most ordered come first.'; }
			return r;
		}

		// Open Monday to Saturday, 8:30am to 7:00pm Nairobi time (UTC+3).
		function openNow() {
			var n = new Date(Date.now() + 3 * 3600 * 1000);
			var day = n.getUTCDay(), mins = n.getUTCHours() * 60 + n.getUTCMinutes();
			if (day !== 0 && mins >= 510 && mins < 1140) { return 'We are open right now, until 7:00pm.'; }
			if (day === 6 && mins >= 1140) { return 'We are closed now and open again on Monday at 8:30am.'; }
			if (day === 0) { return 'We are closed today (Sunday) and open again tomorrow at 8:30am.'; }
			return mins < 510 ? 'We open today at 8:30am.' : 'We are closed now and open again tomorrow at 8:30am.';
		}

		function focusProduct(st) { return st.focus ? byId[st.focus] : null; }

		function ordinalRef(s, st) {
			if (!st.last || !st.last.length) { return null; }
			var m = s.match(/\b(first|1st|second|2nd|third|3rd|fourth|4th|fifth|5th|last)\b(?:\s+(one|option|perfume|watch))?/);
			if (m && (m[2] || /\b(tell|about|more|that|the|price|how much)\b/.test(s))) {
				var i = ORDINAL[m[1]];
				var id = i < 0 ? st.last[st.last.length - 1] : st.last[i];
				return id ? byId[id] : null;
			}
			return null;
		}

		var HAS_PRONOUN = /\b(it|this|that|this one|that one|them)\b/;
		// Questions about one product's details: notes, smell, features, specs, or "tell me more".
		var DETAIL_WORDS = /\b(note|notes|smell|smells|smelling|scent|ingredient|ingredients|contain|contains|feature|features|specs|specification|specifications|describe|description|details|tell me more|more about|what is it like|movement|water resistant|water resistance|strap|dial|case size)\b/;
		var MERGE_KEYS = ['gender', 'max', 'min', 'sort', 'kind', 'notes', 'feats', 'brands', 'type', 'unisex', 'conc'];

		function reply(text, state) {
			var st = clone(state);
			var raw = String(text || '').trim();
			if (!raw) { return { text: 'What can I help you find?', chips: starters(), state: st, answered: true, total: 0 }; }
			var parsed = parse(raw);
			var s = parsed.s;
			var sl = parsed.slots;
			var out = route(raw, s, sl, parsed, st);
			out.state = st;
			return out;
		}

		function starters() { return ['Which perfumes have oud?', 'Gift for her under KSh 10,000', 'Best-selling watches', 'Compare Dior Sauvage and Creed Aventus']; }

		function interest(st) {
			var ids = [];
			if (st.focus) { ids.push(st.focus); }
			(st.last || []).slice(0, 3).forEach(function (id) { if (ids.indexOf(id) < 0) { ids.push(id); } });
			return ids.slice(0, 4);
		}
		function leadOffer(st, text, why) {
			st.leadOffered = (st.leadOffered || 0) + 1;
			return { text: text, form: { products: interest(st), note: st.q ? describeQuery(st.q, true) : '', why: why }, answered: true, total: 0, handoff: true };
		}

		function route(raw, s, sl, parsed, st) {
			var bare = clean(raw).replace(/[.,!?]/g, '').trim();

			// A phone number typed into the chat means "please contact me".
			var phone = String(raw).match(PHONE);
			if (phone && (CALLME.test(s) || /^\s*[+\d][\d\s-]{8,}\s*$/.test(raw) || st.leadOffered)) {
				st.unanswered = 0;
				return { text: 'Thank you! We will call or WhatsApp you on your number, ' + phone[0].trim() + ', shortly. You can add your name and location below to help us prepare.', typedPhone: phone[0].trim(), form: { products: interest(st), note: st.q ? describeQuery(st.q, true) : '', why: 'typed', phone: phone[0].trim() }, answered: true, total: 0 };
			}
			if (CALLME.test(s)) {
				return leadOffer(st, 'Of course. Leave your number and our team will contact you.', 'callme');
			}
			if (BUY.test(s)) {
				var target = st.focus ? byId[st.focus] : null;
				var named0 = findNamed(parsed.toks);
				if (named0.length) { target = named0[0]; st.focus = target.i; }
				if (target) {
					st.last = [target.i];
					var r0 = leadOffer(st, 'Great choice! You can add ' + target.n + ' to your cart and check out now, or leave your number and our team will contact you to complete the order.', 'buy');
					r0.cards = [{ id: target.i, why: '' }];
					return r0;
				}
				return leadOffer(st, 'Happy to help you order. Tell me which product you would like, or leave your number and our team will contact you.', 'buy');
			}
			if (UNSURE.test(s) && !hasShopSlots(sl)) {
				if (!st.leadOffered) {
					var r1 = leadOffer(st, 'No problem, I can help you narrow it down. Who is it for, and what is your budget? If you would rather talk to someone, leave your number and our team will help you choose.', 'unsure');
					r1.chips = ['For her', 'For him', 'Under KSh 5,000', 'KSh 5,000 to 10,000'];
					return r1;
				}
				return { text: 'Who is it for, and what is your budget?', chips: ['For her', 'For him', 'Under KSh 5,000', 'KSh 5,000 to 10,000'], answered: true, total: 0 };
			}
			for (var t = 0; t < SMALLTALK.length; t++) {
				if (SMALLTALK[t][0].test(s) && !hasShopSlots(sl)) { return { text: SMALLTALK[t][1], chips: starters(), answered: true, total: 0 }; }
			}
			var occ = null;
			OCCASION.forEach(function (o) { if (!occ && o[0].test(s)) { occ = o; } });
			if (occ && !sl.notes && sl.kind !== 'w' && /\b(perfume|perfumes|fragrance|scent|cologne|wear|for|smell|something|recommend|suggest|good)\b/.test(s)) {
				var oq = { kind: 'p', anyNotes: occ[1], sort: sl.sort };
				if (sl.gender) { oq.gender = sl.gender; }
				if (sl.max) { oq.max = sl.max; }
				if (sl.min) { oq.min = sl.min; }
				var ores = search(oq);
				if (ores.length) {
					st.q = oq; st.offset = 0;
					return { text: occ[2], cards: cardsFrom(ores, st), chips: refineChips(oq, ores.length), answered: true, total: ores.length };
				}
			}
			var out0 = routeMain(raw, s, sl, parsed, st, bare);
			// Twice not understood in a row: offer a person.
			if (out0.answered === false) {
				st.unanswered = (st.unanswered || 0) + 1;
				if (st.unanswered >= 2) {
					st.unanswered = 0;
					return leadOffer(st, "Sorry, I'm still not sure what you need. Our team can help: continue on WhatsApp, or leave your number and we will contact you.", 'stuck');
				}
			} else {
				st.unanswered = 0;
			}
			// After a useful product answer, offer help to buy, once per chat.
			if (out0.cards && out0.cards.length && !st.leadOffered && (st.turns = (st.turns || 0) + 1) >= 4) {
				st.leadOffered = 1;
				out0.offerHelp = true;
			}
			return out0;
		}

		function routeMain(raw, s, sl, parsed, st, bare) {
			if (GREETING.test(bare)) { return { text: 'Hello! What are you shopping for today?', chips: starters(), answered: true, total: 0 }; }
			if (BYE.test(bare)) { return { text: 'Thank you for visiting Dlight Giftshop. Come back any time!', answered: true, total: 0 }; }
			if (THANKS.test(bare) && !hasShopSlots(sl)) { return { text: "You're welcome! Is there anything else I can help you find?", chips: ['Help me choose a gift', 'Best-selling perfumes', 'Best-selling watches'], answered: true, total: 0 }; }

			// Quick follow-ups on the last result.
			if (/^(show )?(me )?more( please)?$|^next$|^see more$|^more options$/.test(bare) && st.q) {
				var res = search(st.q);
				var offset = (st.offset || 0) + PAGE;
				if (offset >= res.length) { return { text: 'That is everything I have for that search. Would you like to change it?', chips: ['Show cheapest first', 'Help me choose a gift'], answered: true, total: res.length }; }
				st.offset = offset;
				return { text: 'Here are more.', cards: cardsFrom(res, st, offset), chips: refineChips(st.q, res.length - offset), link: shopLink(st.q), answered: true, total: res.length };
			}

			var ord = ordinalRef(s, st);
			if (ord) { st.focus = ord.i; return answerProduct([ord], parsed, st); }

			var cmp = tryCompare(parsed, st);
			if (cmp) { return cmp; }

			// A chosen product can be named while a store question is being asked, so check policy wording first.
			var ans = matchAnswer(s);
			var named = findNamed(parsed.toks);
			if (ans && !(named.length && ans.id === 'authentic')) {
				if (ans.id === 'hours') { return { text: openNow() + ' ' + ans.a, link: ans.link || null, chips: ['Get directions', 'Help me choose a gift'], answered: true, total: 0 }; }
				return { text: ans.a, link: ans.link || null, chips: ['Help me choose a gift', 'Best-selling perfumes'], handoff: ans.id === 'contact' || ans.id === 'order_status', answered: true, total: 0 };
			}

			// Gift questions are a short guided conversation.
			if (st.gift || sl.gift || /\b(help me choose|help me pick|what should i (buy|get|give)|dont know what)\b/.test(s)) {
				var gq = st.gift ? clone(st.gift) : {};
				MERGE_KEYS.concat(['rest']).forEach(function (k) { if (sl[k] !== undefined) { gq[k] = sl[k]; } });
				if (/\b(both|either|any)\b/.test(s) && !gq.kind) { gq.anyKind = 1; }
				if (/\bany budget\b/.test(s) || /\bno budget\b/.test(s)) { st.giftBudgetAsked = 1; gq.sort = gq.sort || 'pop'; }
				delete gq.rest;
				if (gq.anyKind) { delete gq.anyKind; if (!gq.gender) { return giftFlow(gq, st); } gq.sort = gq.sort || 'pop'; st.gift = null; st.giftBudgetAsked = 0; return answerSearchMixed(gq, st); }
				return giftFlow(gq, st);
			}

			// A budget in the question limits named matches too; if none fit, search instead
			// ("day and date watch under 10000" should not return the one watch named "Day & Date").
			if (named.length && (parsed.slots.max || parsed.slots.min)) {
				named = named.filter(function (p) { return (!parsed.slots.max || p.p <= parsed.slots.max) && (!parsed.slots.min || p.p >= parsed.slots.min); });
			}
			if (named.length && !/\b(which|what) (perfumes|watches|products)\b/.test(s)) { return answerProduct(named, parsed, st); }

			// "How much is it", "does it have oud", "second one": questions about what was just shown.
			var focus = focusProduct(st);
			// "Which notes?", "what are the features?", "tell me more": about the product just shown.
			var aboutFocus = HAS_PRONOUN.test(s) || /^(is|does|how much|what notes|what are its|show similar|what features)/.test(s) || (DETAIL_WORDS.test(s) && s.split(' ').length <= 8);
			if (focus && aboutFocus && !hasCatalogSlots(sl)) { return answerProduct([focus], parsed, st); }
			if (focus && aboutFocus && sl.notes && !sl.kind) { return answerProduct([focus], parsed, st); }

			if (/\b(show|find|browse|see)?\s*(best|top)\b/.test(s) && (sl.kind || /\b(sell|selling|seller)/.test(s))) { sl.sort = sl.sort || 'pop'; }

			// Refine the last search: "cheaper", "for men", "under 5k".
			var refineOnly = st.q && !sl.notes && !sl.feats && !sl.brands && !sl.rest && !sl.type && (sl.gender || sl.max || sl.min || sl.sort);
			if (refineOnly && !sl.kind) {
				var q2 = clone(st.q);
				MERGE_KEYS.forEach(function (k) { if (sl[k] !== undefined) { q2[k] = sl[k]; } });
				delete q2.exclude;
				return answerSearch(q2, st);
			}

			if (hasCatalogSlots(sl)) {
				var q = {};
				MERGE_KEYS.concat(['rest']).forEach(function (k) { if (sl[k] !== undefined) { q[k] = sl[k]; } });
				// A question naming only notes means a perfume.
				if (q.notes && !q.kind && !q.feats) { q.kind = 'p'; }
				if (q.feats && !q.kind && !q.notes) { q.kind = 'w'; }
				if (sl.longlasting && !q.rest) { q.rest = ['long-lasting', 'longevity']; if (!q.kind) { q.kind = 'p'; } }
				if (!q.sort && !q.notes && !q.feats && !q.rest && !q.brands) { q.sort = 'pop'; }
				return answerSearch(q, st);
			}
			if (sl.longlasting) { return answerSearch({ kind: 'p', rest: ['long-lasting', 'longevity'], sort: 'pop' }, st); }

			return {
				text: 'Sorry, I did not quite catch that. I can find products by brand, note, budget or who they are for, and answer questions about delivery, payment and returns. Try one of these:',
				chips: starters(), handoff: true, answered: false, total: 0
			};
		}

		function answerSearchMixed(q, st) {
			var r = answerSearch(q, st);
			if (r.total) { r.text = 'Here are some gift ideas ' + (q.gender === 'f' ? 'for her' : 'for him') + '. Our most ordered come first.'; }
			return r;
		}

		function hasCatalogSlots(sl) { return !!(sl.kind || sl.notes || sl.feats || sl.brands || sl.type || sl.unisex || sl.max || sl.min || sl.rest || sl.gender || (sl.sort && sl.kind)); }
		function hasShopSlots(sl) { return !!(sl.kind || sl.notes || sl.feats || sl.brands || sl.type || sl.max || sl.min); }

		return {
			reply: reply,
			context: context,
			drop: drop,
			describe: function (q) { return q ? describeQuery(q, true) : ''; },
			starters: starters,
			product: function (id) { return byId[id]; },
			// Used on a product page, so "this" means the product being viewed.
			focus: function (id, state) { var st = clone(state); if (byId[id]) { st.focus = Number(id); st.last = [Number(id)]; } return st; },
			count: products.length
		};
	}

	return { create: create };
});
