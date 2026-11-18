<?php
/**
 * TikTok ads: pixel, shopping events, server-side sales (Events API) and the catalogue feed.
 *
 * Nothing runs until the owner pastes the TikTok Pixel ID on WooCommerce > TikTok (option dl_tiktok_pixel).
 * Browser events (same product IDs as the Google and Meta catalogues):
 *   ViewContent      - product page
 *   AddToCart        - any add to cart (remembered in the cart session, sent on the next page or cart refresh)
 *   InitiateCheckout - checkout page
 *   Purchase  - a real sale: cash on delivery when placed; M-Pesa only once the payment arrives
 *                      (the same rule as Meta, so TikTok does not learn from unpaid orders)
 *   Contact          - any WhatsApp link tap
 * Sales are also sent from the server (Events API) when an access token is saved (option dl_tiktok_token),
 * with the same event_id, so TikTok counts each sale once and still sees sales the browser missed
 * (ad blockers, M-Pesa payments confirmed after the customer left). The TikTok click ID (ttclid) and the
 * TikTok cookie (_ttp) are saved with the order so the server event can be matched to the ad click.
 * Catalogue feed for TikTok: /tiktok-feed.csv (TikTok template) and /tiktok-feed.xml, the Google feed without designer testers.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * Saved Pixel ID ('' when not set up).
 *
 * @return string
 */
function dl_tt_pixel() {
	$id = strtoupper( trim( (string) get_option( 'dl_tiktok_pixel' ) ) );
	return preg_match( '/^[A-Z0-9]{10,30}$/', $id ) ? $id : '';
}

/**
 * TikTok "contents" entry for a product.
 *
 * @param WC_Product $p     Product.
 * @param int        $qty   Quantity.
 * @param float|null $price Price (default: shop price).
 * @return array
 */
function dl_tt_content( $p, $qty = 1, $price = null ) {
	$c = array(
		'content_id'   => (string) $p->get_id(),
		'content_type' => 'product',
		'content_name' => html_entity_decode( wp_strip_all_tags( $p->get_name() ), ENT_QUOTES, 'UTF-8' ),
		'quantity'     => (int) $qty,
		'price'        => (float) ( null === $price ? wc_get_price_to_display( $p ) : $price ),
	);
	if ( function_exists( 'dl_feed_brand' ) ) {
		$b = dl_feed_brand( $p );
		if ( $b ) {
			$c['brand'] = $b;
		}
	}
	return $c;
}

/**
 * A <script> that sends one TikTok event (safe before the pixel has loaded: ttq queues it).
 *
 * @param string $name  Event.
 * @param array  $props Properties.
 * @param string $eid   Event ID (for matching the server copy).
 * @return string
 */
function dl_tt_script( $name, $props, $eid = '' ) {
	if ( ! dl_tt_pixel() ) {
		return '';
	}
	$opt = $eid ? ',' . wp_json_encode( array( 'event_id' => $eid ) ) : '';
	return '<script>window.ttq&&ttq.track(' . wp_json_encode( $name ) . ',' . wp_json_encode( $props, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . $opt . ');</script>';
}

/**
 * Customer details for matching, SHA-256 hashed as TikTok requires.
 *
 * @param WC_Order $order Order.
 * @return array{email?:string,phone?:string,external_id?:string}
 */
function dl_tt_user( $order ) {
	$u     = array();
	$email = strtolower( trim( (string) $order->get_billing_email() ) );
	if ( is_email( $email ) ) {
		$u['email'] = hash( 'sha256', $email );
	}
	$ph = function_exists( 'dl_ke_phone' ) ? (string) dl_ke_phone( $order->get_billing_phone() ) : '';
	if ( $ph ) {
		$u['phone']       = hash( 'sha256', '+' . $ph );
		$u['external_id'] = hash( 'sha256', $ph );
	}
	return $u;
}

// ---- Pixel base code ----
add_action(
	'wp_head',
	static function () {
		$id = dl_tt_pixel();
		if ( ! $id ) {
			return;
		}
		// TikTok's standard loader, plus: keep the ad click ID (ttclid) for 30 days so the sale can be matched later.
		echo "<script>!function(w,d,t){w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=[\"page\",\"track\",\"identify\",\"instances\",\"debug\",\"on\",\"off\",\"once\",\"ready\",\"alias\",\"group\",\"enableCookie\",\"disableCookie\",\"holdConsent\",\"revokeConsent\",\"grantConsent\"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var r=\"https://analytics.tiktok.com/i18n/pixel/events.js\",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=r,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=d.createElement(\"script\");n.type=\"text/javascript\",n.async=!0,n.src=r+\"?sdkid=\"+e+\"&lib=\"+t;e=d.getElementsByTagName(\"script\")[0];e.parentNode.insertBefore(n,e)};ttq.load(" . wp_json_encode( $id ) . ");ttq.page();}(window,document,\"ttq\");" . // phpcs:ignore WordPress.Security.EscapeOutput
			'(function(){var m=location.search.match(/[?&]ttclid=([^&#]+)/);if(m){document.cookie="dl_ttclid="+m[1]+";path=/;max-age=2592000;SameSite=Lax;Secure";}})();</script>' . "\n";
	},
	6
);

// ---- AddToCart: queued in the cart session like the Google event ----
add_action(
	'woocommerce_add_to_cart',
	static function ( $key, $product_id, $qty, $variation_id ) {
		if ( ! dl_tt_pixel() || ! WC()->session ) {
			return;
		}
		$p = wc_get_product( $variation_id ? $variation_id : $product_id );
		if ( ! $p ) {
			return;
		}
		$q   = (array) WC()->session->get( 'dl_tt_queue', array() );
		$q[] = array( 'AddToCart', array( 'contents' => array( dl_tt_content( $p, $qty ) ), 'content_type' => 'product', 'value' => (float) wc_get_price_to_display( $p ) * (int) $qty, 'currency' => get_woocommerce_currency() ) );
		WC()->session->set( 'dl_tt_queue', array_slice( $q, -10 ) );
	},
	10,
	4
);

/**
 * Pending TikTok events as scripts (and clear the queue).
 *
 * @return string
 */
function dl_tt_flush() {
	if ( ! dl_tt_pixel() || ! function_exists( 'WC' ) || ! WC()->session ) {
		return '';
	}
	$q = (array) WC()->session->get( 'dl_tt_queue', array() );
	if ( ! $q ) {
		return '';
	}
	WC()->session->set( 'dl_tt_queue', array() );
	$out = '';
	foreach ( $q as $ev ) {
		$out .= dl_tt_script( $ev[0], $ev[1] );
	}
	return $out;
}

add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		if ( dl_tt_pixel() ) {
			$fragments['#dl-tt-ev'] = '<div id="dl-tt-ev" hidden>' . dl_tt_flush() . '</div>';
		}
		return $fragments;
	},
	21
);

// ---- Page events ----
add_action(
	'wp_footer',
	static function () {
		if ( ! dl_tt_pixel() ) {
			return;
		}
		$html = '';
		if ( function_exists( 'is_product' ) && is_product() ) {
			$p = wc_get_product( get_queried_object_id() );
			if ( $p ) {
				$html .= dl_tt_script( 'ViewContent', array( 'contents' => array( dl_tt_content( $p ) ), 'content_type' => 'product', 'value' => (float) wc_get_price_to_display( $p ), 'currency' => get_woocommerce_currency() ) );
			}
		}
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! is_wc_endpoint_url( 'order-pay' ) && WC()->cart && ! WC()->cart->is_empty() ) {
			$items = array();
			foreach ( WC()->cart->get_cart() as $line ) {
				$items[] = dl_tt_content( $line['data'], $line['quantity'] );
			}
			$html .= dl_tt_script( 'InitiateCheckout', array( 'contents' => $items, 'content_type' => 'product', 'value' => (float) WC()->cart->get_total( 'edit' ), 'currency' => get_woocommerce_currency() ) );
		}
		$html .= dl_tt_flush();
		echo '<div id="dl-tt-ev" hidden>' . $html . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput -- built with wp_json_encode.
		echo '<script>document.addEventListener("click",function(e){var a=e.target.closest&&e.target.closest("a[href*=\"wa.me\"],a[href*=\"whatsapp.com\"]");if(a&&window.ttq){ttq.track("Contact",{content_name:"WhatsApp"});}},true);</script>';
	},
	51
);

// ---- Save the TikTok click ID and cookie with the order (for the server-side sale) ----
add_action(
	'woocommerce_checkout_order_created',
	static function ( $order ) {
		if ( ! dl_tt_pixel() ) {
			return;
		}
		$ck = static fn( $k ) => isset( $_COOKIE[ $k ] ) ? substr( sanitize_text_field( wp_unslash( $_COOKIE[ $k ] ) ), 0, 200 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$order->update_meta_data( '_dl_tt', array_filter( array(
			'ttclid' => $ck( 'dl_ttclid' ),
			'ttp'    => $ck( '_ttp' ),
			'ip'     => WC_Geolocation::get_ip_address(),
			'ua'     => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ), 0, 400 ) : '',
			'url'    => wp_get_referer() ? wp_get_referer() : home_url( '/checkout/' ),
		) ) );
		$order->save();
	}
);

/**
 * True when the order is a real sale TikTok should hear about now.
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function dl_tt_is_sale( $order ) {
	if ( ! $order || $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) {
		return false;
	}
	return 'mpesa' === $order->get_payment_method() ? $order->is_paid() : true;
}

// ---- Purchase in the browser, on the order received page ----
add_action(
	'woocommerce_thankyou',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! dl_tt_pixel() || ! dl_tt_is_sale( $order ) || $order->get_meta( '_dl_tt_browser' ) ) {
			return;
		}
		$items = array();
		foreach ( $order->get_items() as $line ) {
			$p = $line->get_product();
			if ( $p ) {
				$items[] = dl_tt_content( $p, $line->get_quantity(), (float) $order->get_item_subtotal( $line, true ) );
			}
		}
		$u = dl_tt_user( $order );
		if ( $u ) {
			$id = array();
			if ( isset( $u['email'] ) ) {
				$id['email'] = $u['email'];
			}
			if ( isset( $u['phone'] ) ) {
				$id['phone_number'] = $u['phone'];
				$id['external_id']  = $u['external_id'];
			}
			echo '<script>window.ttq&&ttq.identify(' . wp_json_encode( $id ) . ');</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
		}
		echo dl_tt_script( 'Purchase', array( 'contents' => $items, 'content_type' => 'product', 'value' => (float) $order->get_total(), 'currency' => $order->get_currency(), 'order_id' => (string) $order->get_order_number() ), 'order_' . $order->get_id() ); // phpcs:ignore WordPress.Security.EscapeOutput
		$order->update_meta_data( '_dl_tt_browser', time() );
		$order->save();
		dl_tt_queue_server( $order );
	},
	6
);

// M-Pesa payment confirmed later: send the sale from the server.
add_action(
	'woocommerce_order_status_changed',
	static function ( $order_id, $from, $to, $order ) {
		if ( $order instanceof WC_Order && in_array( $to, array( 'processing', 'completed' ), true ) && dl_tt_is_sale( $order ) ) {
			dl_tt_queue_server( $order );
		}
	},
	21,
	4
);

/**
 * Schedule the server copy of the sale (once per order; sent by WP-Cron within a minute).
 *
 * @param WC_Order $order Order.
 */
function dl_tt_queue_server( $order ) {
	if ( ! dl_tt_pixel() || ! get_option( 'dl_tiktok_token' ) || ! $order->get_meta( '_dl_tt' ) || $order->get_meta( '_dl_tt_server' ) || 'checkout' !== $order->get_created_via() ) {
		return;
	}
	$order->update_meta_data( '_dl_tt_server', 'queued' );
	$order->save();
	wp_schedule_single_event( time() + 5, 'dl_tt_send', array( $order->get_id() ) );
}

/**
 * Send the sale to TikTok's Events API.
 *
 * @param int $order_id Order ID.
 * @return array|WP_Error Response.
 */
function dl_tt_send( $order_id ) {
	$order = wc_get_order( $order_id );
	$pixel = dl_tt_pixel();
	$token = (string) get_option( 'dl_tiktok_token' );
	if ( ! $order || ! $pixel || ! $token ) {
		return new WP_Error( 'dl_tt', 'not set up' );
	}
	$t     = (array) $order->get_meta( '_dl_tt' );
	$items = array();
	foreach ( $order->get_items() as $line ) {
		$p = $line->get_product();
		if ( $p ) {
			$items[] = dl_tt_content( $p, $line->get_quantity(), (float) $order->get_item_subtotal( $line, true ) );
		}
	}
	$user = dl_tt_user( $order );
	foreach ( array( 'ttclid' => 'ttclid', 'ttp' => 'ttp', 'ip' => 'ip', 'ua' => 'user_agent' ) as $k => $f ) {
		if ( ! empty( $t[ $k ] ) ) {
			$user[ $f ] = $t[ $k ];
		}
	}
	$paid = $order->get_date_paid() ? $order->get_date_paid() : $order->get_date_created();
	$body = array(
		'event_source'    => 'web',
		'event_source_id' => $pixel,
		'data'            => array(
			array(
				'event'      => 'Purchase',
				'event_time' => $paid ? $paid->getTimestamp() : time(),
				'event_id'   => 'order_' . $order->get_id(),
				'user'       => $user,
				'properties' => array( 'contents' => $items, 'content_type' => 'product', 'currency' => $order->get_currency(), 'value' => (float) $order->get_total(), 'order_id' => (string) $order->get_order_number() ),
				'page'       => array( 'url' => ! empty( $t['url'] ) ? $t['url'] : home_url( '/checkout/' ) ),
			),
		),
	);
	$r    = wp_remote_post( 'https://business-api.tiktok.com/open_api/v1.3/event/track/', array( 'timeout' => 15, 'headers' => array( 'Access-Token' => $token, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
	$res  = is_wp_error( $r ) ? $r->get_error_message() : (string) wp_remote_retrieve_body( $r );
	$ok   = ! is_wp_error( $r ) && 0 === (int) ( json_decode( $res, true )['code'] ?? -1 );
	$order->update_meta_data( '_dl_tt_server', $ok ? 'sent ' . gmdate( 'c' ) : 'error: ' . substr( $res, 0, 200 ) );
	$order->save();
	return $r;
}
add_action( 'dl_tt_send', 'dl_tt_send' );

// ---- Catalogue feed for TikTok: the Google feed without designer tester perfumes ----
//   /tiktok-feed.csv  TikTok's own column template (what the TikTok catalogue reads)
//   /tiktok-feed.xml  the same products in Google's XML format

/**
 * The Google feed XML without designer testers (cached for an hour, rebuilt with the Google feed).
 *
 * @return string
 */
function dl_tt_feed_xml() {
	$xml = get_transient( 'dl_tiktok_feed' );
	if ( false === $xml || false === get_transient( 'dl_google_feed' ) ) {
		$xml = preg_replace( '#<item>(?:(?!</item>).)*?<g:custom_label_4>designer-tester</g:custom_label_4>.*?</item>\s*#s', '', dl_feed_xml() );
		set_transient( 'dl_tiktok_feed', $xml, HOUR_IN_SECONDS );
	}
	return $xml;
}

/**
 * The TikTok feed as CSV in TikTok's catalogue template.
 *
 * @return string
 */
function dl_tt_feed_csv() {
	$csv = get_transient( 'dl_tiktok_csv' );
	if ( false !== $csv && false !== get_transient( 'dl_tiktok_feed' ) && false !== get_transient( 'dl_google_feed' ) ) { // A product change clears the Google feed, so the CSV is rebuilt too.
		return $csv;
	}
	$doc  = simplexml_load_string( dl_tt_feed_xml() );
	$cols = array( 'sku_id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'additional_image_link', 'brand', 'google_product_category', 'product_type', 'gender', 'age_group', 'custom_label_0', 'custom_label_1', 'custom_label_2', 'custom_label_3', 'custom_label_4' );
	$fh   = fopen( 'php://temp', 'w+' );
	fputcsv( $fh, $cols );
	foreach ( $doc ? $doc->channel->item : array() as $item ) {
		$g    = $item->children( 'http://base.google.com/ns/1.0' );
		$more = array();
		foreach ( $g->additional_image_link as $a ) {
			$more[] = (string) $a;
		}
		$row = array(
			(string) $g->id,
			mb_substr( (string) $g->title, 0, 150 ),
			mb_substr( (string) $g->description, 0, 9999 ),
			str_replace( '_', ' ', (string) $g->availability ),
			(string) $g->condition ? (string) $g->condition : 'new',
			(string) $g->price,
			(string) $g->sale_price,
			(string) $g->link,
			(string) $g->image_link,
			implode( ',', array_slice( $more, 0, 9 ) ),
			(string) $g->brand ? (string) $g->brand : 'Dlight Giftshop',
			(string) $g->google_product_category,
			(string) $g->product_type,
			(string) $g->gender,
			(string) $g->age_group,
			(string) $g->custom_label_0,
			(string) $g->custom_label_1,
			(string) $g->custom_label_2,
			(string) $g->custom_label_3,
			(string) $g->custom_label_4,
		);
		fputcsv( $fh, $row );
	}
	rewind( $fh );
	$csv = stream_get_contents( $fh );
	fclose( $fh );
	set_transient( 'dl_tiktok_csv', $csv, HOUR_IN_SECONDS );
	return $csv;
}

add_action(
	'parse_request',
	static function () {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// /tiktok-products: same CSV at an address the host's cache does not keep (it stores any *.csv for hours).
		if ( '/tiktok-products' === untrailingslashit( $path ) ) {
			$path = '/tiktok-feed.csv';
			header( 'X-Accel-Expires: 0' );
			header( 'Pragma: no-cache' );
		}
		if ( ! in_array( $path, array( '/tiktok-feed.xml', '/tiktok-feed.csv' ), true ) || ! function_exists( 'dl_feed_xml' ) ) {
			return;
		}
		$is_csv = '/tiktok-feed.csv' === $path;
		// Keep a short log of who fetches the feed (to see whether TikTok's downloader reaches the site).
		$h   = static fn( $k ) => isset( $_SERVER[ $k ] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER[ $k ] ) ), 0, 160 ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$log = (array) get_option( 'dl_tt_feed_log', array() );
		$log[] = array( gmdate( 'c' ), $path, $h( 'HTTP_CF_CONNECTING_IP' ) ? $h( 'HTTP_CF_CONNECTING_IP' ) : $h( 'REMOTE_ADDR' ), $h( 'HTTP_CF_IPCOUNTRY' ), $h( 'HTTP_USER_AGENT' ), $h( 'REQUEST_METHOD' ) );
		update_option( 'dl_tt_feed_log', array_slice( $log, -30 ), false );
		$out    = $is_csv ? dl_tt_feed_csv() : dl_tt_feed_xml();
		status_header( 200 );
		header( 'Content-Type: ' . ( $is_csv ? 'text/csv' : 'application/xml' ) . '; charset=UTF-8' );
		header( 'Cache-Control: private, no-store, no-cache, max-age=0' ); // Always from the site (fresh stock, and every fetch is logged).
		header( 'Content-Length: ' . strlen( $out ) );
		header( 'X-Robots-Tag: noindex' );
		if ( 'HEAD' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			exit;
		}
		echo $out; // phpcs:ignore WordPress.Security.EscapeOutput -- data feed, built from the escaped Google feed.
		exit;
	},
	1
);

// ---- Settings: WooCommerce > TikTok (the owner pastes the Pixel ID and access token here) ----
add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'woocommerce', 'TikTok', 'TikTok', 'manage_woocommerce', 'dl-tiktok', 'dl_tt_settings_page' );
	},
	60
);

/**
 * Settings page.
 */
function dl_tt_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$msg = '';
	if ( isset( $_POST['dl_tt_save'] ) && check_admin_referer( 'dl_tt_settings' ) ) {
		$px = strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_POST['dl_tt_pixel'] ?? '' ) ) ) );
		update_option( 'dl_tiktok_pixel', $px, true );
		$tok = trim( sanitize_text_field( wp_unslash( $_POST['dl_tt_token'] ?? '' ) ) );
		if ( '' !== $tok ) {
			update_option( 'dl_tiktok_token', $tok, false );
		}
		if ( ! empty( $_POST['dl_tt_token_clear'] ) ) {
			delete_option( 'dl_tiktok_token' );
		}
		do_action( 'clear_varnish' );
		$msg = $px && ! dl_tt_pixel() ? 'That Pixel ID does not look right. Copy it again from TikTok Events Manager.' : 'Saved.';
	}
	if ( isset( $_POST['dl_tt_test'] ) && check_admin_referer( 'dl_tt_settings' ) ) {
		$o = wc_get_orders( array( 'limit' => 1, 'status' => array( 'completed' ), 'created_via' => 'checkout', 'orderby' => 'date', 'order' => 'DESC' ) );
		$code = sanitize_text_field( wp_unslash( $_POST['dl_tt_test_code'] ?? '' ) );
		if ( ! $o || ! $code ) {
			$msg = 'Paste the test event code from TikTok first.';
		} else {
			add_filter( 'http_request_args', $f = static function ( $args, $url ) use ( $code ) {
				if ( false !== strpos( $url, 'business-api.tiktok.com' ) ) {
					$b              = json_decode( $args['body'], true );
					$b['test_event_code'] = $code;
					$b['data'][0]['event_id'] .= '_test' . time();
					$args['body']   = wp_json_encode( $b );
				}
				return $args;
			}, 10, 2 );
			$keep = $o[0]->get_meta( '_dl_tt_server' );
			$r    = dl_tt_send( $o[0]->get_id() );
			$o[0]->update_meta_data( '_dl_tt_server', $keep );
			$o[0]->save();
			remove_filter( 'http_request_args', $f, 10 );
			$msg = is_wp_error( $r ) ? 'Test failed: ' . $r->get_error_message() : 'TikTok replied: ' . wp_remote_retrieve_body( $r );
		}
	}
	$px  = (string) get_option( 'dl_tiktok_pixel' );
	$tok = (string) get_option( 'dl_tiktok_token' );
	?>
	<div class="wrap">
		<h1>TikTok</h1>
		<?php if ( $msg ) : ?><div class="notice notice-info"><p><?php echo esc_html( $msg ); ?></p></div><?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'dl_tt_settings' ); ?>
			<table class="form-table">
				<tr><th>Pixel ID</th><td><input name="dl_tt_pixel" value="<?php echo esc_attr( $px ); ?>" class="regular-text" placeholder="e.g. D1ABCDEFGHIJ2KLMNOPQ"><p class="description">TikTok Ads Manager &gt; Tools &gt; Events &gt; Web events &gt; your pixel. Status: <strong><?php echo dl_tt_pixel() ? 'on' : 'off'; ?></strong></p></td></tr>
				<tr><th>Events API access token</th><td><input name="dl_tt_token" value="" class="regular-text" autocomplete="off" placeholder="<?php echo $tok ? esc_attr( 'saved (ends …' . substr( $tok, -4 ) . ')' ) : 'paste token'; ?>"> <?php if ( $tok ) : ?><label><input type="checkbox" name="dl_tt_token_clear" value="1"> remove</label><?php endif; ?><p class="description">Pixel &gt; Settings &gt; Events API &gt; Generate access token. Lets the website report paid sales directly to TikTok.</p></td></tr>
				<tr><th>Catalogue feed</th><td><code><?php echo esc_html( home_url( '/tiktok-products' ) ); ?></code><p class="description">Catalog Manager &gt; Add products &gt; Data feed &gt; Scheduled, daily.</p></td></tr>
			</table>
			<p><button class="button button-primary" name="dl_tt_save" value="1">Save</button></p>
			<?php if ( dl_tt_pixel() && $tok ) : ?>
			<h2>Test the server connection</h2>
			<p>In TikTok Events Manager open your pixel &gt; Test events, copy the test event code, paste it here. The last completed order is sent as a test (it is not counted as a sale).</p>
			<p><input name="dl_tt_test_code" class="regular-text" placeholder="TEST12345"> <button class="button" name="dl_tt_test" value="1">Send test sale</button></p>
			<?php endif; ?>
		</form>
	</div>
	<?php
}
