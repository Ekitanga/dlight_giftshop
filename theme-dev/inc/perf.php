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
