<?php
/**
 * Conversation records. Each chat gets a random ID that is not linked to an IP address,
 * account or cookie. Phone numbers and emails typed into the chat are removed from these
 * records; when a shopper gives a number for us to call, it is saved as a lead instead.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'DLA_DB_VERSION', '2' );
define( 'DLA_LOG_DAYS', 90 );

/**
 * Log table name.
 *
 * @return string
 */
function dla_log_table() {
	global $wpdb;
	return $wpdb->prefix . 'dla_log';
}

/**
 * Leads table name.
 *
 * @return string
 */
function dla_lead_table() {
	global $wpdb;
	return $wpdb->prefix . 'dla_leads';
}

/**
 * Create or update both tables.
 */
function dla_install_log_table() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		'CREATE TABLE ' . dla_log_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			session char(16) NOT NULL DEFAULT '',
			question varchar(191) NOT NULL DEFAULT '',
			reply varchar(255) NOT NULL DEFAULT '',
			answered tinyint(1) NOT NULL DEFAULT 1,
			results smallint(5) unsigned NOT NULL DEFAULT 0,
			product bigint(20) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			KEY answered (answered, created),
			KEY session (session, created),
			KEY product (product)
		) $charset;"
	);
	dbDelta(
		'CREATE TABLE ' . dla_lead_table() . " (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created datetime NOT NULL,
			session char(16) NOT NULL DEFAULT '',
			phone varchar(20) NOT NULL DEFAULT '',
			name varchar(80) NOT NULL DEFAULT '',
			location varchar(120) NOT NULL DEFAULT '',
			note varchar(255) NOT NULL DEFAULT '',
			products varchar(191) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT '',
			status varchar(12) NOT NULL DEFAULT 'new',
			PRIMARY KEY  (id),
			KEY status (status, created),
			KEY session (session)
		) $charset;"
	);
	update_option( 'dla_db_version', DLA_DB_VERSION, false );
}

// Update the tables after a plugin update, without needing reactivation.
add_action(
	'plugins_loaded',
	static function () {
		if ( get_option( 'dla_db_version' ) !== DLA_DB_VERSION ) {
			dla_install_log_table();
		}
	}
);

/**
 * Remove contact details from free text.
 *
 * @param string $text Text.
 * @param int    $max  Maximum length.
 * @return string
 */
function dla_scrub( $text, $max = 180 ) {
	$text = preg_replace( '/[\w.+-]+@[\w-]+\.[\w.-]+/', '[email]', (string) $text );
	$text = preg_replace( '/\+?\d[\d\s-]{7,}\d/', '[number]', $text );
	return mb_substr( sanitize_text_field( $text ), 0, $max );
}

/**
 * Reject requests that are not from a page on this website, and limit how often one visitor
 * can write. The visitor's address is only used, hashed, to count requests; it is not stored.
 *
 * @param string $bucket Limit name.
 * @param int    $limit  Requests allowed per hour.
 * @return true|WP_REST_Response
 */
function dla_guard( $bucket, $limit ) {
	$origin = isset( $_SERVER['HTTP_ORIGIN'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) ) : '';
	$home   = wp_parse_url( home_url(), PHP_URL_HOST );
	if ( $origin && wp_parse_url( $origin, PHP_URL_HOST ) !== $home ) {
		return new WP_REST_Response( array( 'ok' => false ), 403 );
	}
	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$key    = 'dla_' . $bucket . '_' . substr( md5( wp_salt( 'nonce' ) . $remote ), 0, 20 );
	$count  = (int) get_transient( $key );
	if ( $count >= $limit ) {
		return new WP_REST_Response( array( 'ok' => false, 'error' => 'busy' ), 429 );
	}
	set_transient( $key, $count + 1, HOUR_IN_SECONDS );
	return true;
}

/**
 * A chat ID made by the shopper's browser: 16 random hex characters, or nothing.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function dla_session( $value ) {
	$value = strtolower( (string) $value );
	return preg_match( '/^[a-f0-9]{16}$/', $value ) ? $value : '';
}

add_action(
	'rest_api_init',
	static function () {
		register_rest_route(
			'dlight-assistant/v1',
			'/log',
			array(
				'methods'             => 'POST',
				'permission_callback' => '__return_true', // Public by design: anonymous shoppers. Guarded in the callback.
				'callback'            => 'dla_rest_log',
			)
		);
	}
);

/**
 * Save one turn of a conversation, or a product click.
 *
 * @param WP_REST_Request $request Request.
 * @return WP_REST_Response
 */
function dla_rest_log( $request ) {
	global $wpdb;
	$guard = dla_guard( 'log', 150 );
	if ( true !== $guard ) {
		return $guard;
	}
	$question = dla_scrub( (string) $request->get_param( 'q' ) );
	$product  = absint( $request->get_param( 'p' ) );
	if ( '' === $question && ! $product ) {
		return new WP_REST_Response( array( 'ok' => false ), 400 );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$wpdb->insert(
		dla_log_table(),
		array(
			'created'  => current_time( 'mysql', true ),
			'session'  => dla_session( $request->get_param( 's' ) ),
			'question' => $question,
			'reply'    => dla_scrub( (string) $request->get_param( 'b' ), 250 ),
			'answered' => $request->get_param( 'a' ) ? 1 : 0,
			'results'  => min( 999, absint( $request->get_param( 'r' ) ) ),
			'product'  => $product,
		),
		array( '%s', '%s', '%s', '%s', '%d', '%d', '%d' )
	);
	return new WP_REST_Response( array( 'ok' => true ), 200 );
}

/**
 * Products shoppers opened or added from the chat in the last 90 days. This nudges future results.
 *
 * @return array<int,int>
 */
function dla_click_counts() {
	global $wpdb;
	$table = dla_log_table();
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return array();
	}
	$rows = $wpdb->get_results( "SELECT product id, COUNT(*) c FROM {$table} WHERE product > 0 AND created > DATE_SUB(UTC_TIMESTAMP(), INTERVAL 90 DAY) GROUP BY product" );
	// phpcs:enable
	$out = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->id ] = (int) $row->c;
	}
	return $out;
}

/**
 * Delete old conversations and old leads.
 */
function dla_purge_old_logs() {
	global $wpdb;
	// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( 'DELETE FROM ' . dla_log_table() . ' WHERE created < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (int) DLA_LOG_DAYS . ' DAY)' );
	$wpdb->query( 'DELETE FROM ' . dla_lead_table() . ' WHERE created < DATE_SUB(UTC_TIMESTAMP(), INTERVAL ' . (int) DLA_LEAD_DAYS . ' DAY)' );
	// phpcs:enable
}
