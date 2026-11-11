<?php
/**
 * Page speed: delays heavy third-party scripts, removes unused code, preloads the main image.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * Pages where tracking must load straight away, so conversions stay accurate.
 *
 * @return bool
 */
function dl_track_now() {
	return is_cart() || is_checkout() || is_account_page();
}

// Unused on the storefront.
add_action(
	'init',
	static function () {
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
		remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
		remove_action( 'wp_head', 'wp_print_auto_sizes_contain_css_fix', 1 );
		remove_action( 'wp_enqueue_scripts', 'wp_enqueue_img_auto_sizes_contain_css_fix', 1 );
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$jquery = wp_scripts()->registered['jquery'] ?? null;
		if ( $jquery && ! dl_track_now() ) {
			$jquery->deps = array_values( array_diff( $jquery->deps, array( 'jquery-migrate' ) ) );
		}
	},
	200
);

// Runs queued work at the first scroll, tap or key press, or 2.5 seconds after load.
add_action(
	'wp_head',
	static function () {
		if ( dl_track_now() ) {
			return;
		}
		?>
<script>!function(w,d){var q=[],done=!1;function run(){if(done)return;done=!0;q.forEach(function(f){try{f()}catch(e){}});q=[]}w.__dlLater=function(f){done?f():q.push(f)};['scroll','pointerdown','touchstart','keydown'].forEach(function(e){w.addEventListener(e,run,{once:!0,passive:!0})});function t(){setTimeout(run,2500)}'complete'===d.readyState?t():w.addEventListener('load',t)}(window,document);</script>
		<?php
	},
	0
);

// Preload the first banner, choosing the right one for the screen.
add_action(
	'wp_head',
	static function () {
		if ( ! is_front_page() ) {
			return;
		}
		$img = DL_URI . '/assets/img/slide-perfume-';
		printf( '<link rel="preload" as="image" href="%s" media="(max-width: 760px)" fetchpriority="high">' . "\n", esc_url( $img . 'phone.webp' ) );
		printf( '<link rel="preload" as="image" href="%s" media="(min-width: 761px)" fetchpriority="high">' . "\n", esc_url( $img . 'wide.webp' ) );
	},
	2
);

/**
 * Delay the Google and Meta script downloads. Both tools queue their events until the script
 * arrives, so events are sent late, not lost. If a plugin update changes these snippets, the
 * patterns simply do not match and tracking loads at its normal time.
 *
 * @param string $html Page.
 * @return string
 */
function dl_delay_tracking( $html ) {
	$html = preg_replace(
		'#<script async src="(https://www\.googletagmanager\.com/gtag/js\?id=[^"]+)"></script>#',
		'<script>__dlLater(function(){var s=document.createElement("script");s.async=true;s.src="$1";document.head.appendChild(s)})</script>',
		$html,
		1
	);
	$html = str_replace(
		't.src=v;s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}',
		't.src=v;s=b.getElementsByTagName(e)[0];__dlLater(function(){s.parentNode.insertBefore(t,s)})}',
		$html
	);
	return $html;
}

add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || is_feed() || is_robots() || dl_track_now() ) {
			return;
		}
		ob_start( 'dl_delay_tracking' );
	},
	1
);

/*
 * Plugins from the old design that may still be switched on. Their files are not
 * needed by this theme, so they are not loaded. Their admin settings are untouched.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		$old = '#/plugins/(click-to-chat-for-whatsapp|nasa-core|revslider|instagram-feed|yith-woocommerce-compare|header-footer-elementor|elementor)/#';
		foreach ( array( wp_scripts(), wp_styles() ) as $deps ) {
			foreach ( (array) $deps->queue as $handle ) {
				$src = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';
				if ( $src && preg_match( $old, $src ) ) {
					$deps instanceof WP_Scripts ? wp_dequeue_script( $handle ) : wp_dequeue_style( $handle );
				}
			}
		}
	},
	9999
);

/*
 * Page caching: the Facebook plugin sets its _fbp browser-ID cookie from the server on every
 * page view. Any Set-Cookie header stops EasyWP/Cloudflare from caching the page, so product and
 * category pages were rebuilt for every visitor (2-3 s). Facebook's own pixel script sets the same
 * _fbp cookie in the browser, so only the server copy is dropped. Cart, login and ad-click (_fbc)
 * cookies are untouched.
 */
add_action(
	'template_redirect',
	static function () {
		if ( is_admin() || headers_sent() || ! function_exists( 'headers_list' ) ) {
			return;
		}
		$cookies = array();
		$dropped = false;
		foreach ( headers_list() as $h ) {
			if ( 0 !== stripos( $h, 'Set-Cookie:' ) ) {
				continue;
			}
			if ( preg_match( '/^Set-Cookie:\s*_fbp=/i', $h ) ) {
				$dropped = true;
				continue;
			}
			$cookies[] = $h;
		}
		if ( ! $dropped ) {
			return;
		}
		header_remove( 'Set-Cookie' );
		foreach ( $cookies as $h ) {
			header( $h, false );
		}
	},
	999
);

/*
 * Private timing note (only with ?dl_timing=<preview key hash>): where page build time goes.
 * Prints an HTML comment at the end of the page; nothing is shown to normal visitors.
 */
if ( isset( $_GET['dl_timing'] ) && hash_equals( 'd5c3de228d', substr( md5( (string) $_GET['dl_timing'] ), 0, 10 ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended,WordPress.Security.ValidatedSanitizedInput
	$GLOBALS['dl_marks'] = array( 'theme_loaded' => microtime( true ) );
	foreach ( array( 'init', 'wp_loaded', 'wp', 'template_redirect', 'wp_head', 'wp_footer' ) as $dl_h ) {
		add_action( $dl_h, static function () use ( $dl_h ) { $GLOBALS['dl_marks'][ $dl_h ] = microtime( true ); }, -9999 );
	}
	add_action(
		'shutdown',
		static function () {
			$start = $_SERVER['REQUEST_TIME_FLOAT'] ?? $GLOBALS['timestart']; // phpcs:ignore
			$out   = array();
			foreach ( $GLOBALS['dl_marks'] as $k => $t ) {
				$out[] = $k . '=' . round( ( $t - $start ) * 1000 ) . 'ms';
			}
			$out[] = 'end=' . round( ( microtime( true ) - $start ) * 1000 ) . 'ms';
			$out[] = 'queries=' . get_num_queries();
			$out[] = 'plugins=' . implode( ',', array_map( static fn( $p ) => dirname( $p ), (array) get_option( 'active_plugins' ) ) );
			echo "\n<!-- dl-timing " . esc_html( implode( ' ', $out ) ) . ' -->';
		},
		9999
	);
}

/*
 * Cloudflare page cache: when a product changes (stock, price, title, photos), clear its page,
 * its categories, the shop and the homepage at once, so shoppers never see stale stock.
 * Credentials live in the private option dl_cf_purge = array( 'zone' => ..., 'token' => ... )
 * (token limited to Zone > Cache Purge for dlightgiftshop.com), never in this public code.
 */
function dl_cf_queue_product( $product_id ) {
	$product_id = (int) $product_id;
	$parent     = wp_get_post_parent_id( $product_id );
	if ( $parent ) {
		$product_id = $parent;
	}
	if ( 'product' !== get_post_type( $product_id ) ) {
		return;
	}
	$urls   = &$GLOBALS['dl_cf_urls'];
	$urls   = is_array( $urls ) ? $urls : array();
	$urls[] = get_permalink( $product_id );
	foreach ( (array) wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) ) as $tid ) {
		foreach ( array_merge( array( $tid ), get_ancestors( $tid, 'product_cat' ) ) as $t ) {
			$link = get_term_link( (int) $t, 'product_cat' );
			if ( ! is_wp_error( $link ) ) {
				$urls[] = $link;
			}
		}
	}
	$urls[] = home_url( '/google-feed.xml' );
	$urls[] = wc_get_page_permalink( 'shop' ); // The home page is not listed: its prefix would clear the whole site.
}
foreach ( array( 'woocommerce_update_product', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'woocommerce_product_set_stock' ) as $dl_hook ) {
	add_action( $dl_hook, static function ( $arg ) { dl_cf_queue_product( is_object( $arg ) ? $arg->get_id() : $arg ); } );
}
unset( $dl_hook );
add_action( 'transition_post_status', static function ( $new, $old, $post ) { if ( 'product' === $post->post_type && $new !== $old ) { dl_cf_queue_product( $post->ID ); } }, 10, 3 );
add_action(
	'shutdown',
	static function () {
		$urls = array_values( array_unique( array_filter( (array) ( $GLOBALS['dl_cf_urls'] ?? array() ) ) ) );
		$cfg  = get_option( 'dl_cf_purge' );
		if ( ! $urls || empty( $cfg['zone'] ) || empty( $cfg['token'] ) ) {
			return;
		}
		// Purge by prefix (scheme-less): purging by exact URL does not match this site's cache keys.
		$urls = array_values( array_unique( array_map( static fn( $u ) => preg_replace( '#^https?://#', '', $u ), $urls ) ) );
		foreach ( array_chunk( $urls, 30 ) as $chunk ) { // Cloudflare: 30 prefixes per call.
			wp_remote_post(
				'https://api.cloudflare.com/client/v4/zones/' . rawurlencode( $cfg['zone'] ) . '/purge_cache',
				array(
					'timeout'  => 5,
					'blocking' => false,
					'headers'  => array( 'Authorization' => 'Bearer ' . $cfg['token'], 'Content-Type' => 'application/json' ),
					'body'     => wp_json_encode( array( 'prefixes' => $chunk ) ),
				)
			);
		}
	},
	20
);
