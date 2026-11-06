/*
 * Tests the assistant against the real knowledge file.
 * Usage: node tests/run.js path/to/index.json
 * A failure means a shopper would get a wrong or unhelpful answer.
 */
'use strict';
const fs = require('fs');
const path = require('path');
const Engine = require(path.join(__dirname, '..', 'assets', 'engine.js'));

const data = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const engine = Engine.create(data);
const P = (id) => engine.product(id);
const names = (r) => (r.cards || []).map((c) => P(c.id).n);
const prod = (r) => (r.cards || []).map((c) => P(c.id));
const text = (r) => r.text || '';
const has = (w) => (r) => new RegExp(w, 'i').test(text(r));
const hasCard = (w) => (r) => names(r).some((n) => new RegExp(w, 'i').test(n));
const answered = (r) => r.answered !== false;
const cards = (r) => (r.cards || []).length > 0;
const all = (...f) => (r) => f.every((x) => x(r));
const allCards = (f) => (r) => cards(r) && prod(r).every(f);
const word = (w) => new RegExp('\\b' + w + '\\b', 'i');
const mentions = (w) => (p) => word(w).test(p.n + ' ' + (p.d || '')) || (p.o || []).includes(w);
const kind = (k) => (p) => p.k === k;
const forG = (g) => (p) => !p.g || p.g === g || p.g === 'u';
const under = (n) => (p) => p.p <= n;
const answerId = (w) => has(w);

const fails = [];
const T = [
	// The exact example the owner gave.
	['which perfumes are oud', all(cards, allCards(mentions('oud')), allCards(kind('p')))],
	['Which perfumes have oud?', all(cards, allCards(mentions('oud')))],
	['do you have oud perfumes', all(cards, allCards(mentions('oud')))],
	['oud', all(cards, allCards(mentions('oud')))],
	['perfume with oudh', all(cards, allCards(mentions('oud')))],
	['oud perfumes for men', all(cards, allCards(mentions('oud')), allCards(forG('m')))],
	['oud perfume under 8000', all(answered, cards, allCards(mentions('oud')))],

	// Notes and families.
	['vanilla perfume', all(cards, allCards(mentions('vanilla')))],
	['something with vanila and rose', all(cards)],
	['floral perfume for her', all(cards, allCards(kind('p')), allCards(forG('f')))],
	['woody fragrance for him', all(cards, allCards(kind('p')), allCards(forG('m')))],
	['fresh cologne', all(cards, allCards(kind('p')))],
	['sweet perfume for my girlfriend', all(cards, allCards(forG('f')))],
	['smells like coffee', all(cards, allCards(mentions('coffee')))],
	['a perfume that smells like rose', all(cards, allCards(mentions('rose')))],

	// Named products.
	['tell me about dior sauvage', all(cards, hasCard('sauvage'))],
	['how much is creed aventus', all(has('KSh'), hasCard('aventus'))],
	['price of baccarat rouge', all(has('KSh'), hasCard('baccarat'))],
	['is tom ford oud wood in stock', all(has('in stock'), hasCard('oud wood'))],
	['does sauvage have oud', all(has('does not mention'), hasCard('sauvage'))],
	['does tom ford oud wood have oud', all(has('^yes'), hasCard('oud wood'))],
	['savage dior', all(cards, hasCard('sauvage'))],
	['aventis', all(cards, hasCard('aventus'))],
	['how much is jadore', all(has('KSh'))],
	['does black orchid last long', all(has('varies'), hasCard('black orchid'))],

	// Models and watches.
	['casio watches', all(cards, allCards(kind('w')), allCards((p) => p.b === 'Casio'))],
	['seiko 5', all(cards, hasCard('seiko'))],
	['SNXS77K1', all(cards, hasCard('snxs77k1'))],
	['g-shock', all(cards, hasCard('shock'))],
	['automatic watch', all(cards, allCards(kind('w')), allCards((p) => (p.f || []).includes('automatic')))],
	['gold watch for women', all(cards, allCards(kind('w')), allCards(forG('f')), allCards((p) => (p.f || []).includes('gold')))],
	['leather strap watch for men', all(cards, allCards(kind('w')), allCards(forG('m')), allCards((p) => (p.f || []).includes('leather')))],
	['cheap watches', all(cards, allCards(kind('w')), (r) => prod(r)[0].p <= prod(r)[prod(r).length - 1].p)],
	['most expensive watch', all(cards, allCards(kind('w')), (r) => prod(r)[0].p >= prod(r)[prod(r).length - 1].p)],
	['chronograph under 10k', all(cards, allCards(under(10000)), allCards(kind('w')))],
	['watch between 5000 and 10000', all(cards, allCards((p) => p.p >= 5000 && p.p <= 10000), allCards(kind('w')))],
	['best selling watches', all(cards, allCards(kind('w')))],
	['best selling perfumes', all(cards, allCards(kind('p')))],

	// Budget wording.
	['perfume under 5000', all(cards, allCards(under(5000)), allCards(kind('p')))],
	['perfume below ksh 4,000', all(cards, allCards(under(4000)))],
	['watches under 3k', all(cards, allCards(under(3000)))],
	['perfumes around 6000', all(cards, allCards((p) => p.p >= 4800 && p.p <= 7200))],
	['perfume 100ml', all(cards, allCards(kind('p')))],

	// Brands, including short forms.
	['ysl perfumes', answered],
	['tom ford perfumes', all(cards, allCards((p) => /tom ford/i.test(p.n)))],
	['michael kors watches', all(cards, allCards(kind('w')))],
	['fossil', all(cards, hasCard('fossil'))],
	['arabic perfumes', all(cards, allCards((p) => p.t === 'a'))],
	['tester perfumes for men', all(cards, allCards((p) => p.t === 't'), allCards(forG('m')))],

	// Swahili and everyday phrasing.
	['manukato ya wanawake', all(cards, allCards(kind('p')), allCards(forG('f')))],
	['saa za wanaume', all(cards, allCards(kind('w')), allCards(forG('m')))],
	['habari', all(answered, has('hello'))],
	['asante', all(answered, has('welcome'))],
	['uko wapi', has('rasumal')],

	// Policies: must never state a delivery price.
	['how much is delivery', all(has('vary|varies'), (r) => !/KSh\s?\d/.test(text(r)))],
	['do you deliver to mombasa', has('deliver')],
	['can i pay with mpesa', has('m-pesa')],
	['can I pay on delivery', has('cash on delivery')],
	['what is your return policy', has('contact us')],
	['can i return a perfume i opened', has('cannot be returned')],
	['i got the wrong item', has('keep the product')],
	['where are you located', has('rasumal')],
	['what time do you open', has('8:30am')],
	['are you open on sunday', has('closed on sundays')],
	['are you open now', has('8:30am')],
	['is creed aventus original', all(has('^yes'), hasCard('aventus'))],
	['where is my order', has('tracking')],
	['talk to a human', has('\\+254')],
	['what is a tester perfume', has('same original fragrance')],
	['whats the difference between tester and original perfumes', has('packaging')],
	['are your perfumes original', has('^yes, our perfumes are original')],
	['am allergic to perfume', has('medical')],
	['are you a robot', has('automated')],

	// Things it must handle honestly.
	['lamborghini', (r) => !cards(r) || r.answered === false],
	['asdfghjkl', (r) => r.answered === false],
	['what is the capital of france', (r) => r.answered === false || !cards(r)],
	['oud perfume under 100', all((r) => !prod(r).some((p) => !mentions('oud')(p)))],
];

// Conversations: each step keeps the memory of the last.
const C = [
	{ name: 'refine by budget', steps: [['oud perfumes', cards], ['cheaper', (r) => prod(r)[0].p <= prod(r)[prod(r).length - 1].p], ['under 8000', all(allCards(under(8000)), allCards(mentions('oud')))]] },
	{ name: 'refine by gender', steps: [['perfume', cards], ['for men', all(allCards(kind('p')), allCards(forG('m')))]] },
	{ name: 'show more pages', steps: [['casio watches', cards], ['show more', all(cards, has('more'))]] },
	{ name: 'gift flow', steps: [['i need a gift', has('man or a woman')], ['for her', has('perfume or a watch')], ['a perfume', has('budget')], ['KSh 5,000 to 10,000', all(cards, allCards(kind('p')), allCards(forG('f')), allCards((p) => p.p <= 10000))]] },
	{ name: 'gift all at once', steps: [['gift for my wife under 10000', has('perfume or a watch')], ['a watch', all(cards, allCards(kind('w')), allCards(under(10000)))]] },
	{ name: 'follow up about first result', steps: [['dior sauvage', cards], ['how much is it', has('KSh')], ['show similar', cards]] },
	{ name: 'ordinal', steps: [['best selling perfumes', cards], ['tell me about the second one', cards]] },
	{ name: 'compare', steps: [['compare dior sauvage and creed aventus', (r) => !!r.compare]] },
	{ name: 'compare vs', steps: [['sauvage vs aventus', (r) => !!r.compare]] },
	{ name: 'nothing leaks between topics', steps: [['oud perfumes for men', cards], ['casio watches', all(allCards(kind('w')), allCards((p) => p.b === 'Casio'))]] },
];

// Fresh questions written after the first fixes, to measure more than the cases it was tuned on.
const H = [
	['hi there', has('hello')],
	['good morning', has('hello')],
	['i want a perfume for my husband', all(cards, allCards(kind('p')), allCards(forG('m')))],
	['need a nice watch for my mother', all(cards, allCards(kind('w')), allCards(forG('f')))],
	['ladies watches under 6000', all(cards, allCards(kind('w')), allCards(forG('f')), allCards(under(6000)))],
	['mens cologne below 7000', all(cards, allCards(kind('p')), allCards(forG('m')), allCards(under(7000)))],
	['show me rose gold watches', all(cards, allCards((p) => (p.f || []).includes('rosegold')))],
	['do you stock lattafa', all(cards, hasCard('lattafa'))],
	['any armani perfumes?', all(cards, hasCard('armani'))],
	['price of gucci guilty', all(has('KSh'), hasCard('gucci guilty'))],
	['what does hypnotic poison smell like', all(cards, hasCard('hypnotic'), has('mentions|notes'))],
	['is hugo boss bottled night for men', all(cards, hasCard('bottled night'))],
	['perfumes with sandalwood', all(cards, allCards(mentions('sandalwood')))],
	['leather perfumes', all(cards, allCards(kind('p')), allCards(mentions('leather')))],
	['what perfume has jasmine and vanilla', all(cards, allCards(kind('p')))],
	['digital watch', all(cards, allCards((p) => (p.f || []).includes('digital')))],
	['solar watch', all(cards, allCards((p) => (p.f || []).includes('solar')))],
	['water resistant watches', all(cards, allCards((p) => (p.f || []).includes('waterresistant')))],
	['seiko automatic', all(cards, allCards((p) => p.b === 'Seiko'))],
	['curren for men', all(cards, allCards((p) => p.b === 'Curren'))],
	['naviforce nf9202', all(cards, hasCard('nf9202'))],
	['how do i pay', has('m-pesa')],
	['delivery fee to kisumu', has('vary|varies')],
	['how long does delivery take', has('estimated')],
	['can i collect from the shop', has('pickup')],
	['refund', has('contact us|inspect')],
	['my watch stopped working', has('keep the product')],
	['do you have a physical shop', has('rasumal')],
	['cancel my order', has('dispatched')],
	['compare casio a158wa and casio a159wa', (r) => !!r.compare],
	['gift for my dad', has('perfume or a watch')],
	['anniversary present for wife', has('perfume or a watch')],
	['what is the cheapest perfume', all(cards, allCards(kind('p')))],
	['newest watches', all(cards, allCards(kind('w')))],
	['tell me a joke', (r) => r.answered === false || !cards(r)],
	['who won the football', (r) => r.answered === false || !cards(r)],
];

let pass = 0, fail = 0;
let hp = 0;
for (const [q, check] of H) {
	// Fresh questions count separately below.
	const r = engine.reply(q, {});
	let ok = false;
	try { ok = !!check(r); } catch (e) { ok = false; }
	if (ok) { hp++; } else { fails.push(`  [fresh] ${JSON.stringify(q)} -> ${text(r).slice(0, 96)} [${names(r).slice(0, 3).join(' | ').slice(0, 86)}]`); }
}
console.log(`fresh questions: passed ${hp} of ${H.length}`);
for (const [q, check] of T) {
	const r = engine.reply(q, {});
	let ok = false;
	try { ok = !!check(r); } catch (e) { ok = false; }
	ok ? pass++ : (fail++, fails.push(`  ${JSON.stringify(q)} -> ${text(r).slice(0, 96)} [${names(r).slice(0, 3).join(' | ').slice(0, 86)}]`));
}
for (const c of C) {
	let st = {};
	c.steps.forEach(([q, check], i) => {
		const r = engine.reply(q, st);
		st = r.state;
		let ok = false;
		try { ok = !!check(r); } catch (e) { ok = false; }
		ok ? pass++ : (fail++, fails.push(`  [${c.name} #${i + 1}] ${JSON.stringify(q)} -> ${text(r).slice(0, 96)} [${names(r).slice(0, 3).join(' | ').slice(0, 86)}]`));
	});
}
// Answers must never contain an outside link.
let leak = 0;
for (const [q] of T) {
	const r = engine.reply(q, {});
	if (/https?:\/\//.test(JSON.stringify([r.text, r.link, r.chips]))) { leak++; }
	if (r.link && !/^\//.test(r.link[1])) { leak++; }
}
console.log(`products ${engine.count}, index ${Math.round(fs.statSync(process.argv[2]).size / 1024)} KB`);
console.log(`passed ${pass} of ${pass + fail}; outside links in answers: ${leak}`);
if (fails.length) { console.log('FAILED:\n' + fails.join('\n')); }
process.exitCode = fail ? 1 : 0;
