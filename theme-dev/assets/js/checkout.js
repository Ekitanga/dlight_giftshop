/* Checkout: show only the fields the chosen delivery needs, suggest towns for the chosen county,
   and make the Nairobi area list searchable. The page is already correct on load; this keeps it
   correct as the customer changes county or delivery. */
(function ($) {
	'use strict';
	if (!$ || !document.querySelector('form.checkout')) { return; }
	var NAIROBI = 'KE30';
	var TOWNS = window.DL_TOWNS || {}, COUNTIES = window.DL_COUNTIES || {};

	function chosen() { return $('input[name="shipping_method[0]"]:checked').val() || $('input[name="shipping_method[0]"]').first().val() || ''; }
	function isPickup(m) { return /^(local_pickup|pickup_location)/.test(m); }

	// WooCommerce re-sorts address fields after load; keep them in the order a shopper expects.
	var ORDER = ['#billing_first_name_field', '#billing_last_name_field', '#billing_phone_field', '#billing_email_field', '#billing_country_field', '#billing_state_field', '#billing_dl_area_field', '#billing_city_field', '#billing_address_1_field'];
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

	var lastCounty = null;
	function refresh() {
		try {
			reorder();
			var m = chosen(), pickup = isPickup(m), county = $('#billing_state').val() || NAIROBI, nairobi = county === NAIROBI;
			var name = COUNTIES[county] || 'your county';
			show('#billing_dl_area_field', !pickup && nairobi);
			show('#billing_city_field', !pickup && !nairobi);
			show('#billing_address_1_field', !pickup);
			mark('#billing_dl_area_field', !pickup && nairobi);
			mark('#billing_city_field', !pickup && !nairobi);
			mark('#billing_address_1_field', !pickup && nairobi);
			setLabel('#billing_city_field', 'Town in ' + name);
			setLabel('#billing_address_1_field', nairobi ? 'Building, street or landmark' : 'Nearest Speedaf office or landmark');
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
				lastCounty = county;
			}

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
	$(refresh);
})(window.jQuery);
