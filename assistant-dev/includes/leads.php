<?php
/**
 * Leads: contact details a shopper chose to leave in the chat, so the team can follow up.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'DLA_LEAD_DAYS', 365 );

/**
 * Turn a typed phone number into a standard form, or return '' when it is not a phone number.
 * Kenyan numbers become +254 numbers; other international numbers are accepted with their code.
 *
 * @param string $raw Typed number.
 * @return string
 */
function dla_phone( $raw ) {
	$digits = preg_replace( '/\D/', '', (string) $raw );
	$plus   = 0 === strpos( trim( (string) $raw ), '+' );
	if ( preg_match( '/^0([17]\d{8})$/', $digits, $m ) ) {
		return '+254' . $m[1];
	}
	if ( preg_match( '/^254([17]\d{8})$/', $digits, $m ) ) {
		return '+254' . $m[1];
	}
	if ( preg_match( '/^([17]\d{8})$/', $digits, $m ) ) {
		return '+254' . $m[1];
	}
	if ( $plus && strlen( $digits ) >= 8 && strlen( $digits ) <= 15 ) {
		return '+' . $digits;
	}
	return '';
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'dlight-assistant/v1',
			'/lead',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public by design: shoppers are not logged in. Guarded in the callback.
				'callback'            => 'dla_rest_lead',
			)
		);
	}
);

/**
 * Save a lead.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function dla_rest_lead( $request ) {
	global $wpdb;
	$guard = dla_guard( 'lead', 6 );
	if ( true !== $guard ) {
		return $guard;
	}
	// A hidden field that people never see. Automated spam fills it in.
	if ( '' !== (string) $request->get_param( 'website' ) ) {
		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}
	$phone = dla_phone( (string) $request->get_param( 'phone' ) );
	if ( ! $phone ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'phone' ), 400 );
	}
	$session = dla_session( $request->get_param( 's' ) );
	$ids     = array_slice( array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'products' ) ) ) ), 0, 8 );
	$ids     = array_values( array_filter( $ids, static fn( $id ) => 'product' === get_post_type( $id ) ) );

	$row = array(
		'created'  => current_time( 'mysql', true ),
		'session'  => $session,
		'phone'    => $phone,
		'name'     => mb_substr( sanitize_text_field( (string) $request->get_param( 'name' ) ), 0, 80 ),
		'location' => mb_substr( sanitize_text_field( (string) $request->get_param( 'location' ) ), 0, 120 ),
		'note'     => mb_substr( sanitize_text_field( (string) $request->get_param( 'note' ) ), 0, 250 ),
		'products' => implode( ',', $ids ),
		'source'   => in_array( $request->get_param( 'source' ), array( 'form', 'typed' ), true ) ? $request->get_param( 'source' ) : 'form',
	);

	// The same number in the same chat updates the earlier lead, so the team is not notified twice.
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$existing = $session ? (int) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . dla_lead_table() . ' WHERE session = %s AND phone = %s', $session, $phone ) ) : 0;
	if ( $existing ) {
		$update = array_filter( array( 'name' => $row['name'], 'location' => $row['location'], 'note' => $row['note'], 'products' => $row['products'] ) );
		if ( $update ) {
			$wpdb->update( dla_lead_table(), $update, array( 'id' => $existing ) );
		}
		return new WP_REST_Response( array( 'ok' => true, 'phone' => $phone ), 200 );
	}
	$wpdb->insert( dla_lead_table(), $row, array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	// phpcs:enable
	dla_notify_lead( $row );
	return new WP_REST_Response( array( 'ok' => true, 'phone' => $phone ), 200 );
}

/**
 * Email the team about a new lead.
 *
 * @param array $row Lead.
 */
function dla_notify_lead( $row ) {
	$to = dla_options()['notify'];
	if ( ! $to || ! is_email( $to ) ) {
		return;
	}
	$items = array();
	foreach ( array_filter( explode( ',', $row['products'] ) ) as $id ) {
		$product = wc_get_product( (int) $id );
		if ( $product ) {
			$items[] = '- ' . $product->get_name() . ' (' . html_entity_decode( wp_strip_all_tags( wc_price( $product->get_price() ) ), ENT_QUOTES, 'UTF-8' ) . ') ' . $product->get_permalink();
		}
	}
	$body  = "A shopper left their details with Dlight AI.\n\n";
	$body .= 'Phone: ' . $row['phone'] . "\n";
	$body .= $row['name'] ? 'Name: ' . $row['name'] . "\n" : '';
	$body .= $row['location'] ? 'Location: ' . $row['location'] . "\n" : '';
	$body .= $row['note'] ? 'Looking for: ' . $row['note'] . "\n" : '';
	$body .= $items ? "\nInterested in:\n" . implode( "\n", $items ) . "\n" : '';
	$body .= "\nSee all leads: " . admin_url( 'admin.php?page=dla-leads' ) . "\n";
	wp_mail( $to, 'New lead from Dlight AI: ' . $row['phone'], $body );
}
