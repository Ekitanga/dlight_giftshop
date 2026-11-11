<?php
/**
 * Google Analytics 4 shopping events (and, later, Google Ads conversions).
 *
 * The GA4 tag itself (G-W8L91JSETT) is loaded by the "GA Google Analytics" plugin; this file adds
 * the e-commerce events Google needs to learn which visits turn into sales:
 *   view_item       - product page
 *   add_to_cart     - any add to cart (quick "Add" buttons, drawer, product form)
 *   begin_checkout  - checkout page
 *   purchase        - order received page, once per order, with the order value (M-Pesa and cash on delivery)
 *   whatsapp_click  - any "Order on WhatsApp" / WhatsApp link click
 * Item IDs are the WooCommerce product IDs, the same IDs as the Google Merchant feed and Meta catalogue.
 *
 * Google Ads: when an Ads account exists, set the option dl_gads_id (e.g. AW-123456789) and
 * dl_gads_purchase_label; the purchase is then also sent to Google Ads as a conversion.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * GA4 item array for a product.
 *
 * @param WC_Product $p   Product.
 * @param int        $qty Quantity.
 * @return array
 */
function dl_ga_item( $p, $qty = 1 ) {
	$parent = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
	$cats   = wp_get_post_terms( $parent->get_id(), 'product_cat', array( 'fields' => 'names' ) );
	$item   = array(
		'item_id'   => (string) $p->get_id(),
		'item_name' => html_entity_decode( wp_strip_all_tags( $p->get_name() ), ENT_QUOTES, 'UTF-8' ),
		'price'     => (float) wc_get_price_to_display( $p ),
		'quantity'  => (int) $qty,
	);
	if ( function_exists( 'dl_feed_brand' ) ) {
		$item['item_brand'] = dl_feed_brand( $p );
	}
	foreach ( array_slice( (array) $cats, 0, 3 ) as $i => $c ) {
		$item[ 0 === $i ? 'item_category' : 'item_category' . ( $i + 1 ) ] = html_entity_decode( $c, ENT_QUOTES, 'UTF-8' );
	}
	return $item;
}

/**
 * GA4 properties every event goes to: the owner's property and the older plugin property.
 *
 * @return string[]
 */
function dl_ga_targets() {
	return array_values( array_filter( array( (string) get_option( 'dl_ga4_id', 'G-W4MBEE67W0' ), 'G-W8L91JSETT' ) ) );
}

/**
 * A <script> that sends one GA4 event (safe even before the GA tag has loaded).
 *
 * @param string $name   Event name.
 * @param array  $params Parameters.
 * @return string
 */
function dl_ga_script( $name, $params ) {
	if ( empty( $params['send_to'] ) ) {
		$params['send_to'] = dl_ga_targets();
	}
	return '<script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag("event",' .
		wp_json_encode( $name ) . ',' . wp_json_encode( $params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ');</script>';
}

// ---- add_to_cart: remembered in the cart session, then sent on the next page or cart refresh ----
add_action(
	'woocommerce_add_to_cart',
	static function ( $key, $product_id, $qty, $variation_id ) {
		if ( ! WC()->session ) {
			return;
		}
		$p = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $p ) {
			return;
		}
		$q   = (array) WC()->session->get( 'dl_ga_queue', array() );
		$q[] = array( 'add_to_cart', array( 'currency' => get_woocommerce_currency(), 'value' => (float) wc_get_price_to_display( $p ) * (int) $qty, 'items' => array( dl_ga_item( $p, $qty ) ) ) );
		WC()->session->set( 'dl_ga_queue', array_slice( $q, -10 ) );
	},
	10,
	4
);

/**
 * Pending events as scripts (and clear the queue).
 *
 * @return string
 */
function dl_ga_flush() {
	if ( ! function_exists( 'WC' ) || ! WC()->session ) {
		return '';
	}
	$q = (array) WC()->session->get( 'dl_ga_queue', array() );
	if ( ! $q ) {
		return '';
	}
	WC()->session->set( 'dl_ga_queue', array() );
	$out = '';
	foreach ( $q as $ev ) {
		$out .= dl_ga_script( $ev[0], $ev[1] );
	}
	return $out;
}

// Quick "Add" buttons refresh the cart by AJAX: deliver the event inside the refreshed fragment.
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		$fragments['#dl-ga-ev'] = '<div id="dl-ga-ev">' . dl_ga_flush() . '</div>';
		return $fragments;
	},
	20
);

// ---- Page events, printed in the footer ----
add_action(
	'wp_footer',
	static function () {
		$html = '';
		if ( function_exists( 'is_product' ) && is_product() ) {
			$p = wc_get_product( get_queried_object_id() );
			if ( $p ) {
				$html .= dl_ga_script( 'view_item', array( 'currency' => get_woocommerce_currency(), 'value' => (float) wc_get_price_to_display( $p ), 'items' => array( dl_ga_item( $p ) ) ) );
			}
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$items = array();
			foreach ( WC()->cart->get_cart() as $line ) {
				$items[] = dl_ga_item( $line['data'], $line['quantity'] );
			}
			$html .= dl_ga_script( 'begin_checkout', array( 'currency' => get_woocommerce_currency(), 'value' => (float) WC()->cart->get_total( 'edit' ), 'items' => $items ) );
		}
		$html .= dl_ga_flush();
		echo '<div id="dl-ga-ev" hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with wp_json_encode.
		// WhatsApp clicks (product "Order on WhatsApp", floating button, contact links).
		$item = ( function_exists( 'is_product' ) && is_product() ) ? html_entity_decode( get_the_title( get_queried_object_id() ), ENT_QUOTES, 'UTF-8' ) : '';
		echo '<script>document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href*=\"wa.me\"],a[href*=\"whatsapp.com\"]");if(!a)return;window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag("event","whatsapp_click",{send_to:' . wp_json_encode( dl_ga_targets() ) . ',link_location:a.className||"link",page_type:' . wp_json_encode( $item ? 'product' : 'other' ) . ',item_name:' . wp_json_encode( $item, JSON_UNESCAPED_UNICODE ) . '});},true);</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
	},
	50
);

// ---- purchase: once per order, on the "order received" page ----
add_action(
	'woocommerce_thankyou',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->has_status( array( 'failed', 'cancelled' ) ) || $order->get_meta( '_dl_ga_tracked' ) ) {
			return;
		}
		$items = array();
		foreach ( $order->get_items() as $line ) {
			$p = $line->get_product();
			if ( $p ) {
				$it             = dl_ga_item( $p, $line->get_quantity() );
				$it['price']    = (float) $order->get_item_subtotal( $line, true );
				$items[]        = $it;
			}
		}
		$params = array(
			'transaction_id' => (string) $order->get_order_number(),
			'currency'       => $order->get_currency(),
			'value'          => (float) $order->get_total(),
			'shipping'       => (float) $order->get_shipping_total(),
			'payment_type'   => $order->get_payment_method_title(),
			'items'          => $items,
		);
		echo dl_ga_script( 'purchase', $params ); // phpcs:ignore WordPress.Security.EscapeOutput
		$aw    = (string) get_option( 'dl_gads_id' );
		$label = (string) get_option( 'dl_gads_purchase_label' );
		if ( $aw && $label ) {
			echo dl_ga_script( 'conversion', array( 'send_to' => $aw . '/' . $label, 'value' => (float) $order->get_total(), 'currency' => $order->get_currency(), 'transaction_id' => (string) $order->get_order_number() ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		}
		$order->update_meta_data( '_dl_ga_tracked', time() );
		$order->save();
	},
	5
);

// The owner's GA4 property (dlightgiftshop21@gmail.com). The old plugin tag (G-W8L91JSETT) keeps working too;
// every event above is sent to both. Change with the option dl_ga4_id.
add_action(
	'wp_head',
	static function () {
		$ga = (string) get_option( 'dl_ga4_id', 'G-W4MBEE67W0' );
		if ( preg_match( '/^G-[A-Z0-9]+$/', $ga ) ) {
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $ga ) . '"></script><script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag("js",new Date());gtag("config",' . wp_json_encode( $ga ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	4
);

// Google tag container created by Google Ads (account 436-269-0817). Change with the option dl_google_tag.
add_action(
	'wp_head',
	static function () {
		$gt = (string) get_option( 'dl_google_tag', 'GTM-M7HR93GM' );
		if ( preg_match( '/^GTM-[A-Z0-9]+$/', $gt ) ) {
			echo '<script async src="https://www.googletagmanager.com/gtm.js?id=' . esc_attr( $gt ) . '"></script><script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag("js",new Date());</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	3
);

// Google Ads tag (only once an Ads account ID is saved in the option dl_gads_id).
add_action(
	'wp_head',
	static function () {
		$aw = (string) get_option( 'dl_gads_id' );
		if ( $aw && preg_match( '/^AW-\d+$/', $aw ) ) {
			echo '<script async src="https://www.googletagmanager.com/gtag/js?id=' . esc_attr( $aw ) . '"></script><script>window.dataLayer=window.dataLayer||[];window.gtag=window.gtag||function(){dataLayer.push(arguments);};gtag("js",new Date());gtag("config",' . wp_json_encode( $aw ) . ');</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput
		}
	},
	5
);
