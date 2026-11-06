/* Checkout: show only the fields the chosen delivery needs, and make the Nairobi area list searchable. */
(function ($) {
	'use strict';
	if (!$ || !document.querySelector('form.checkout')) { return; }
	var NAIROBI = 'KE30';

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
	// Fields needed for the chosen delivery show a required star instead of "(optional)".
	function mark(sel, needed) {
		var label = $(sel + ' > label');
		label.find('.optional').toggle(!needed);
		if (needed && !label.find('.required').length) { label.append(' <abbr class="required" title="required">*</abbr>'); }
		if (!needed) { label.find('.required').remove(); }
	}

	function refresh() {
		reorder();
		var m = chosen(), pickup = isPickup(m), nairobi = $('#billing_state').val() === NAIROBI;
		mark('#billing_dl_area_field', !pickup && nairobi);
		mark('#billing_city_field', !pickup && !nairobi);
		mark('#billing_address_1_field', !pickup && nairobi);
		$('.dl-row-area').toggle(!pickup && nairobi);
		$('.dl-row-town').toggle(!pickup && !nairobi);
		$('.dl-row-address').toggle(!pickup);
		$('.dl-row-address label').contents().first()[0] && ($('.dl-row-address label').contents().first()[0].textContent = nairobi ? 'Building, street or landmark ' : 'Nearest Speedaf office or landmark (optional) ');
		$('.dl-pickup-note').prop('hidden', !pickup);
		$('.dl-option').each(function () { $(this).toggleClass('is-on', $(this).find('input').is(':checked')); });
		var area = $('#billing_dl_area');
		if (area.length && $.fn.selectWoo && !area.hasClass('select2-hidden-accessible')) {
			area.selectWoo({ width: '100%', placeholder: 'Choose your area', language: { noResults: function () { return 'Not listed? Choose "Other area" and write it below.'; } } });
		}
	}

	$(document.body).on('updated_checkout country_to_state_changed', function () { setTimeout(refresh, 0); });
	$(document).on('change', '#billing_state, input[name="shipping_method[0]"]', function () { setTimeout(refresh, 0); });
	$(refresh);
})(window.jQuery);
