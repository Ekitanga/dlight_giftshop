<?php
/**
 * One-off store settings for the Kenyan checkout and the new emails. Each function returns before/after.
 * Safe to run twice: it checks what is already set.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nairobi County: shop pickup (free) and delivery within Nairobi (KSh 350).
 * Rest of Kenya: courier to your town (KSh 400, the existing option moved here) and shop pickup.
 */
function dl_setup_shipping() {
	global $wpdb;
	$out  = array( 'before' => array() );
	$zone = new WC_Shipping_Zone( 2 );
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		$out['before'][] = $z['zone_name'] . ': ' . implode( ', ', wp_list_pluck( $z['shipping_methods'], 'title' ) ) . ' | ' . count( $z['zone_locations'] ) . ' locations';
	}

	// Nairobi County only.
	$zone->set_zone_name( 'Nairobi County' );
	$zone->set_zone_order( 0 );
	$zone->clear_locations();
	$zone->add_location( 'KE:KE30', 'state' );
	$zone->save();
	$s3          = (array) get_option( 'woocommerce_flat_rate_3_settings', array() );
	$s3['title'] = 'Delivery within Nairobi';
	$s3['cost']  = '350';
	update_option( 'woocommerce_flat_rate_3_settings', $s3 );
	$s2          = (array) get_option( 'woocommerce_local_pickup_2_settings', array() );
	$s2['title'] = 'Shop Pickup';
	$s2['cost']  = '';
	update_option( 'woocommerce_local_pickup_2_settings', $s2 );

	// Delivery first, so it is the default choice; pickup second.
	$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', array( 'method_order' => 1 ), array( 'instance_id' => 3 ) );
	$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', array( 'method_order' => 2 ), array( 'instance_id' => 2 ) );

	// Rest of Kenya.
	$rest_id = 0;
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		if ( 'Rest of Kenya' === $z['zone_name'] ) {
			$rest_id = (int) $z['id'];
		}
	}
	$rest = new WC_Shipping_Zone( $rest_id ? $rest_id : null );
	$rest->set_zone_name( 'Rest of Kenya' );
	$rest->set_zone_order( 1 );
	$rest->clear_locations();
	$rest->add_location( 'KE', 'country' );
	$rest->save();
	$rest_id = $rest->get_id();

	// Move the existing courier option (flat_rate:5) into it, so its ID and cash-on-delivery setting stay.
	$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', array( 'zone_id' => $rest_id, 'method_order' => 1 ), array( 'instance_id' => 5 ) );
	$s5          = (array) get_option( 'woocommerce_flat_rate_5_settings', array() );
	$s5['title'] = 'Courier to your town (Speedaf)';
	$s5['cost']  = '400';
	update_option( 'woocommerce_flat_rate_5_settings', $s5 );

	// Pickup for shoppers outside Nairobi too.
	$pickup_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT instance_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE zone_id=%d AND method_id='local_pickup'", $rest_id ) );
	if ( ! $pickup_id ) {
		$pickup_id = (int) $rest->add_shipping_method( 'local_pickup' );
		$wpdb->update( $wpdb->prefix . 'woocommerce_shipping_zone_methods', array( 'method_order' => 2 ), array( 'instance_id' => $pickup_id ) );
	}
	update_option( 'woocommerce_local_pickup_' . $pickup_id . '_settings', array( 'title' => 'Shop Pickup', 'tax_status' => 'none', 'cost' => '' ) );

	// Cash on delivery stays available for every delivery option.
	$cod = (array) get_option( 'woocommerce_cod_settings', array() );
	$cod['enable_for_methods'] = array_values( array_unique( array_merge( (array) ( $cod['enable_for_methods'] ?? array() ), array( 'flat_rate:3', 'flat_rate:5', 'local_pickup:2', 'local_pickup:' . $pickup_id ) ) ) );
	$cod['title']              = 'Pay on delivery';
	$cod['description']        = 'Pay by M-Pesa or cash when your order arrives, or when you collect it.';
	$cod['instructions']       = 'Pay by M-Pesa or cash when your order arrives, or when you collect it.';
	update_option( 'woocommerce_cod_settings', $cod );

	// Sell and deliver within Kenya only.
	update_option( 'woocommerce_allowed_countries', 'specific' );
	update_option( 'woocommerce_specific_allowed_countries', array( 'KE' ) );
	update_option( 'woocommerce_ship_to_countries', '' );
	update_option( 'woocommerce_shipping_cost_requires_address', 'no' );

	WC_Cache_Helper::get_transient_version( 'shipping', true );
	WC_Cache_Helper::invalidate_cache_group( 'shipping_zones' );
	foreach ( WC_Shipping_Zones::get_zones() as $z ) {
		$out['after'][] = $z['zone_name'] . ': ' . implode( ', ', array_map( static fn( $m ) => $m->title . ' (' . $m->id . ':' . $m->instance_id . ', ' . ( $m->cost ?? '0' ) . ')', $z['shipping_methods'] ) ) . ' | ' . implode( ' ', wp_list_pluck( $z['zone_locations'], 'code' ) );
	}
	$out['cod_methods'] = $cod['enable_for_methods'];
	return $out;
}

/**
 * Email settings: branded look, the official address, every email switched on and sent to the right place.
 */
function dl_setup_emails() {
	$team = 'dlightgiftshop21@gmail.com';
	$out  = array();
	$opts = array(
		'woocommerce_email_from_name'             => 'Dlight Giftshop',
		'woocommerce_email_from_address'          => $team,
		'woocommerce_email_header_image'          => home_url( '/wp-content/themes/dlight-v4/assets/img/logo-dg.png' ),
		'woocommerce_email_header_alignment'      => 'center',
		'woocommerce_email_base_color'            => '#111111',
		'woocommerce_email_background_color'      => '#f4f1ea',
		'woocommerce_email_body_background_color' => '#ffffff',
		'woocommerce_email_text_color'            => '#1c1a17',
		'woocommerce_email_footer_text'           => 'Dlight Giftshop &middot; Rasumal House, 4th Floor, Shop 14, Tom Mboya Street, Nairobi<br>Call or WhatsApp <a href="https://wa.me/254740611468">+254 740 611 468</a> &middot; Mon to Sat, 8:30am to 7pm<br><a href="https://dlightgiftshop.com/">dlightgiftshop.com</a> &middot; <a href="mailto:' . $team . '">' . $team . '</a>',
		'admin_email'                             => $team,
		'new_admin_email'                         => $team,
	);
	foreach ( $opts as $k => $v ) {
		$out['before'][ $k ] = get_option( $k );
		update_option( $k, $v );
	}

	$emails = array(
		'new_order'                 => array( 'recipient' => $team, 'subject' => 'New order #{order_number} on {site_title}', 'heading' => 'New order #{order_number}', 'additional_content' => 'Call or WhatsApp the customer to confirm, then mark the order as Processing.' ),
		'cancelled_order'           => array( 'recipient' => $team, 'subject' => 'Order #{order_number} was cancelled', 'heading' => 'Order cancelled', 'additional_content' => '' ),
		'failed_order'              => array( 'recipient' => $team, 'subject' => 'Payment failed for order #{order_number}', 'heading' => 'Payment failed', 'additional_content' => 'The customer may need help paying. Consider calling or sending a WhatsApp.' ),
		'customer_processing_order' => array( 'subject' => 'We have received your order #{order_number}', 'heading' => 'Thank you for your order', 'additional_content' => 'Thank you for shopping with Dlight Giftshop.' ),
		'customer_on_hold_order'    => array( 'subject' => 'Your order #{order_number} is received', 'heading' => 'We have your order', 'additional_content' => 'Thank you for shopping with Dlight Giftshop.' ),
		'customer_completed_order'  => array( 'subject' => 'Your Dlight Giftshop order #{order_number} is complete', 'heading' => 'Your order is complete', 'additional_content' => 'Thank you for shopping with us. We would love to see you again.' ),
		'customer_cancelled_order'  => array( 'subject' => 'Your order #{order_number} has been cancelled', 'heading' => 'Order cancelled', 'additional_content' => '' ),
		'customer_failed_order'     => array( 'subject' => 'Your payment for order #{order_number} did not go through', 'heading' => 'Payment not completed', 'additional_content' => '' ),
		'customer_refunded_order'   => array( 'additional_content' => 'If you have any questions about this refund, call or WhatsApp us on +254 740 611 468.' ),
		'customer_note'             => array( 'additional_content' => 'Reply to this email or WhatsApp us on +254 740 611 468 if you have any questions.' ),
		'customer_new_account'      => array( 'additional_content' => 'You can now track your orders from your account.' ),
	);
	foreach ( $emails as $id => $settings ) {
		$key                  = 'woocommerce_' . $id . '_settings';
		$cur                  = (array) get_option( $key, array() );
		$out['before'][ $id ] = ( $cur['enabled'] ?? 'default' ) . '/' . ( $cur['email_type'] ?? 'default' ) . '/' . ( $cur['recipient'] ?? '' );
		$new                  = array_merge( $cur, $settings, array( 'enabled' => 'yes', 'email_type' => 'html' ) );
		update_option( $key, $new );
	}
	// Point the admin gateway notice at the shop's address too.
	$k = 'woocommerce_admin_payment_gateway_enabled_settings';
	update_option( $k, array_merge( (array) get_option( $k, array() ), array( 'recipient' => $team ) ) );

	foreach ( WC()->mailer()->get_emails() as $e ) {
		$e->init_settings();
	}
	return $out;
}

/**
 * Curren watches that were filed under Naviforce (or its "Watches for Men/Women"): moved to
 * Curren and Curren's sub-category of the same name.
 */
function dl_fix_curren() {
	$navi   = get_term_by( 'slug', 'naviforce', 'product_cat' );
	$curren = get_term_by( 'slug', 'curren', 'product_cat' );
	if ( ! $navi || ! $curren ) {
		return array( 'error' => 'categories not found' );
	}
	$navi_terms = array( (int) $navi->term_id => (int) $curren->term_id );
	$curren_sub = array();
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $curren->term_id, 'hide_empty' => false ) ) as $t ) {
		$curren_sub[ strtolower( $t->name ) ] = (int) $t->term_id;
	}
	foreach ( get_terms( array( 'taxonomy' => 'product_cat', 'parent' => $navi->term_id, 'hide_empty' => false ) ) as $t ) {
		$navi_terms[ (int) $t->term_id ] = $curren_sub[ strtolower( $t->name ) ] ?? (int) $curren->term_id;
	}
	$moved = array();
	$ids   = get_posts( array( 'post_type' => 'product', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'tax_query' => array( array( 'taxonomy' => 'product_cat', 'terms' => array_keys( $navi_terms ) ) ) ) );
	foreach ( $ids as $id ) {
		if ( 0 !== stripos( get_the_title( $id ), 'curren' ) ) {
			continue;
		}
		$have = wp_get_object_terms( $id, 'product_cat', array( 'fields' => 'ids' ) );
		$add  = array( (int) $curren->term_id );
		foreach ( $have as $tid ) {
			if ( isset( $navi_terms[ (int) $tid ] ) ) {
				$add[] = $navi_terms[ (int) $tid ];
			}
		}
		wp_remove_object_terms( $id, array_keys( $navi_terms ), 'product_cat' );
		wp_set_object_terms( $id, array_values( array_unique( $add ) ), 'product_cat', true );
		$moved[] = $id . ' ' . html_entity_decode( get_the_title( $id ) );
	}
	if ( $moved ) {
		wc_delete_product_transients();
		delete_transient( 'wc_term_counts' );
		$all = array_unique( array_merge( array_keys( $navi_terms ), array_values( $navi_terms ) ) );
		_wc_term_recount( get_terms( array( 'taxonomy' => 'product_cat', 'include' => $all, 'hide_empty' => false ) ), get_taxonomy( 'product_cat' ), true, false );
	}
	return array( 'moved' => $moved );
}
