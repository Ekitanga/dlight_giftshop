/* Checkout: show only the fields the chosen delivery needs, suggest towns for the chosen county,
   and make the Nairobi area list searchable. The page is already correct on load; this keeps it
   correct as the customer changes county or delivery. */
(function ($) {
	'use strict';
	if (!$ || !document.querySelector('form.checkout')) { return; }
	var NAIROBI = 'KE30';
	var TOWNS = window.DL_TOWNS || {}, COUNTIES = window.DL_COUNTIES || {};
	var OFFICES = window.DL_OFFICES || [], NEAR = window.DL_NEAR || {}, NONE = 'none';

	function chosen() { return $('input[name="shipping_method[0]"]:checked').val() || $('input[name="shipping_method[0]"]').first().val() || ''; }
	function isPickup(m) { return /^(local_pickup|pickup_location)/.test(m); }

	// WooCommerce re-sorts address fields after load; keep them in the order a shopper expects.
	var ORDER = ['#billing_first_name_field', '#billing_last_name_field', '#billing_phone_field', '#billing_email_field', '#billing_country_field', '#billing_state_field', '#billing_dl_area_field', '#billing_dl_office_field', '#billing_city_field', '#billing_address_1_field'];
	function reorder() {
		var prev = null;
		ORDER.forEach(function (sel) {
			var row = $(sel);
			if (!row.length) { return; }
			if (prev && prev.parent()[0] === row.parent()[0]) { row.insertAfter(prev); }
			prev = row;
		});
	}
	// The label's text without its "optional" or required marker.
	function setLabel(sel, text) {
		var label = $(sel + ' > label');
		var node = label.contents().filter(function () { return this.nodeType === 3; }).first()[0];
		if (node) { node.textContent = text + ' '; }
	}
	// Fields needed for the chosen delivery show a required star instead of "(optional)".
	function mark(sel, needed) {
		var label = $(sel + ' > label');
		label.find('.optional').toggle(!needed);
		if (needed && !label.find('.required').length) { label.append(' <abbr class="required" title="required">*</abbr>'); }
		if (!needed) { label.find('.required').remove(); }
	}
	function show(sel, on) { $(sel).removeClass('dl-off').toggle(!!on); }

	// Only the Speedaf offices in the chosen county (or the nearest ones where the county has none),
	// then "No Speedaf office is near me", highlighted so nobody misses it. Counties with no office
	// near them get that choice selected straight away.
	function buildOffices(county) {
		var sel = document.getElementById('billing_dl_office');
		if (!sel) { return; }
		var cur = sel.value;
		var mine = OFFICES.filter(function (o) { return o[2] === county; });
		var near = NEAR[county] || [];
		var list = mine.length ? mine : OFFICES.filter(function (o) { return near.indexOf(o[0]) > -1; });
		var $sel = $(sel);
		if ($sel.hasClass('select2-hidden-accessible')) { $sel.selectWoo('destroy'); }
		sel.innerHTML = '';
		var ph = document.createElement('option'); ph.value = ''; ph.textContent = 'Choose a Speedaf office'; sel.appendChild(ph);
		list.forEach(function (o) {
			var e = document.createElement('option');
			e.value = o[0];
			e.textContent = o[1] + (mine.length ? '' : ' (' + (COUNTIES[o[2]] || '') + ')');
			sel.appendChild(e);
		});
		var no = document.createElement('option'); no.value = NONE; no.textContent = 'No Speedaf office is near me'; no.className = 'dl-opt-none'; sel.appendChild(no);
		if (cur && sel.querySelector('option[value="' + cur + '"]')) { sel.value = cur; }
		else { sel.value = list.length ? '' : NONE; }
		if ($.fn.selectWoo) {
			var mark = function (item) {
				if (item.id !== NONE) { return item.text; }
				return $('<span class="dl-opt-none"></span>').text(item.text);
			};
			$sel.selectWoo({ width: '100%', placeholder: 'Choose a Speedaf office', minimumResultsForSearch: Infinity, templateResult: mark });
		}
		sel.setAttribute('data-county', county);
		if (!list.length && cur !== NONE) { $sel.trigger('change'); } // Shows the town box and updates the payment wording.
	}
	// A short line under the office list: the office address, or what happens without an office.
	function officeNote(county, office) {
		var row = document.getElementById('billing_dl_office_field');
		if (!row) { return; }
		var note = document.getElementById('dl-office-note');
		if (!note) { note = document.createElement('p'); note.id = 'dl-office-note'; note.className = 'dl-office-note'; row.appendChild(note); }
		var name = COUNTIES[county] || 'your county';
		var found = OFFICES.filter(function (o) { return o[0] === office; })[0];
		var has = OFFICES.some(function (o) { return o[2] === county; });
		var nearby = (NEAR[county] || []).length > 0;
		// Built from text parts; the bold parts are fixed wording, never customer input.
		var parts = [];
		if (found) { parts = [['', 'Collect from: ' + found[3]]]; }
		else if (office === NONE) {
			parts = has || nearby
				? [['b', 'No problem.'], ['', ' Place your order and we will call you to arrange delivery.']]
				: [['b', 'Speedaf has no office in or near ' + name + '.'], ['', ' Place your order and we will call you to arrange delivery. Tell us your town below.']];
		} else if (has) {
			parts = [['', 'None of these near you? Choose '], ['b', 'No Speedaf office is near me'], ['', ' and we will call you to arrange delivery.']];
		} else {
			parts = [['', 'Speedaf has no office in ' + name + '. These are the nearest. Too far? Choose '], ['b', 'No Speedaf office is near me'], ['', ' and we will call you to arrange delivery.']];
		}
		note.textContent = '';
		parts.forEach(function (p) {
			var el = p[0] ? document.createElement('strong') : document.createTextNode(p[1]);
			if (p[0]) { el.textContent = p[1]; }
			note.appendChild(el);
		});
		note.classList.toggle('is-choice', !found && office !== NONE);
		note.hidden = !parts.length;
	}

	var lastCounty = null;
	function refresh() {
		try {
			reorder();
			var m = chosen(), pickup = isPickup(m), county = $('#billing_state').val() || NAIROBI, nairobi = county === NAIROBI;
			var name = COUNTIES[county] || 'your county';
			var office = $('#billing_dl_office').val() || '', none = office === NONE;
			show('#billing_dl_area_field', !pickup && nairobi);
			show('#billing_dl_office_field', !pickup && !nairobi);
			show('#billing_city_field', !pickup && !nairobi && none);
			show('#billing_address_1_field', !pickup && (nairobi || none));
			mark('#billing_dl_area_field', !pickup && nairobi);
			mark('#billing_dl_office_field', !pickup && !nairobi);
			mark('#billing_city_field', !pickup && !nairobi && none);
			mark('#billing_address_1_field', false);
			setLabel('#billing_city_field', 'Your town in ' + name);
			setLabel('#billing_address_1_field', nairobi ? 'Building, street or landmark' : 'Area or landmark');
			$('.dl-pickup-note').prop('hidden', !pickup);
			$('.dl-option').each(function () { $(this).toggleClass('is-on', $(this).find('input').is(':checked')); });

			if (county !== lastCounty) {
				// Town suggestions for this county; a saved "Nairobi" town is cleared outside Nairobi.
				var list = document.getElementById('dl-towns');
				if (list) {
					list.innerHTML = '';
					(TOWNS[county] || []).forEach(function (t) { var o = document.createElement('option'); o.value = t; list.appendChild(o); });
				}
				var town = $('#billing_city');
				if (!nairobi && /^\s*nairobi\s*$/i.test(town.val() || '')) { town.val(''); }
				var ex = (TOWNS[county] || []).slice(0, 2).join(', ');
				town.attr('placeholder', ex ? 'e.g. ' + ex : 'Start typing your town');
				$('#billing_address_1').attr('placeholder', nairobi ? 'e.g. Kilimani, Argwings Kodhek Rd, Silver Court' : 'e.g. near the market, opposite the police station');
				if (!nairobi && $('#billing_dl_office').attr('data-county') !== county) { buildOffices(county); }
				lastCounty = county;
			}
			if (!nairobi) { officeNote(county, $('#billing_dl_office').val() || ''); }

			var area = $('#billing_dl_area');
			if (area.length && $.fn.selectWoo && !area.hasClass('select2-hidden-accessible')) {
				area.selectWoo({ width: '100%', placeholder: 'Choose your area', language: { noResults: function () { return 'Not listed? Choose "Other area" and write it below.'; } } });
			}
		} catch (e) { /* never block checkout */ }
	}

	$(document.body).on('updated_checkout country_to_state_changed', function () { setTimeout(refresh, 0); });
	$(document).on('change', 'input[name="shipping_method[0]"]', function () { setTimeout(refresh, 0); });
	// A new county means new delivery options: refresh the fields now and ask WooCommerce to recalculate.
	$(document).on('change', '#billing_state', function () {
		setTimeout(refresh, 0);
		$(document.body).trigger('update_checkout');
	});
	// Switching between M-Pesa (2% off) and pay on delivery changes the total.
	$(document).on('change', 'input[name="payment_method"]', function () { $(document.body).trigger('update_checkout'); });
	// The office choice changes what the pay-on-delivery option says (Speedaf fee first, or a call from us).
	$(document).on('change', '#billing_dl_office', function () {
		setTimeout(refresh, 0);
		$(document.body).trigger('update_checkout');
	});
	$(refresh);
})(window.jQuery);
