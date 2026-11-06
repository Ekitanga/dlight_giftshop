<?php
/**
 * Theme setup and asset loading.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'after_setup_theme',
	static function () {
		add_theme_support( 'title-tag' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );
		add_theme_support(
			'woocommerce',
			array(
				'thumbnail_image_width' => 450,
				'single_image_width'    => 800,
			)
		);
	}
);

add_action(
	'wp_enqueue_scripts',
	static function () {
		$css = '/assets/css/main.css';
		$js  = '/assets/js/main.js';

		wp_enqueue_style( 'dlight', dl_asset_url( $css ), array(), null );
		wp_enqueue_script(
			'dlight',
			dl_asset_url( $js ),
			array(),
			null,
			array( 'strategy' => 'defer', 'in_footer' => true )
		);

		wp_add_inline_script(
			'dlight',
			'window.DL=' . wp_json_encode(
				array(
					'ajax'     => WC_AJAX::get_endpoint( '%%endpoint%%' ),
					'store'    => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
					'shop'     => wc_get_page_permalink( 'shop' ),
					'checkout' => wc_get_checkout_url(),
					'finder'   => dl_finder_map(),
					'symbol'   => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
				)
			) . ';',
			'before'
		);

		// Styles below are replaced by main.css or are unused by this theme.
		foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'wc-blocks-style', 'brands-styles' ) as $handle ) {
			wp_dequeue_style( $handle );
		}

		// main.js handles add to cart and the cart count, on every page but the cart and checkout.
		if ( ! is_cart() && ! is_checkout() ) {
			wp_dequeue_script( 'wc-cart-fragments' );
			wp_dequeue_script( 'wc-add-to-cart' );
		}
		// Load WooCommerce's general script, the M-Pesa files and contact forms only where they work.
		$commerce = is_cart() || is_checkout() || is_account_page();
		if ( ! $commerce ) {
			wp_dequeue_script( 'woocommerce' );
			wp_dequeue_script( 'jquery-blockui' );
			wp_dequeue_script( 'js-cookie' );
		}
		$post      = get_post();
		$has_forms = is_singular() && $post && has_shortcode( $post->post_content, 'contact-form-7' );
		foreach ( array( wp_scripts(), wp_styles() ) as $i => $assets ) {
			foreach ( (array) $assets->queue as $handle ) {
				$src = isset( $assets->registered[ $handle ] ) ? (string) $assets->registered[ $handle ]->src : '';
				$off = ( false !== strpos( $src, 'woo-m-pesa' ) && ! is_checkout() )
					|| ( false !== strpos( $src, 'contact-form-7' ) && ! $has_forms );
				if ( $off ) {
					0 === $i ? wp_dequeue_script( $handle ) : wp_dequeue_style( $handle );
				}
			}
		}

		wp_dequeue_script( 'wc-single-product' );
		wp_dequeue_script( 'zoom' );
		wp_dequeue_script( 'flexslider' );
		wp_dequeue_script( 'photoswipe-ui-default' );
		wp_dequeue_style( 'photoswipe-default-skin' );
	},
	100
);

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

// Preload the two fonts the first screen needs.
add_action(
	'wp_head',
	static function () {
		foreach ( array( 'jost', 'playfair' ) as $font ) {
			printf(
				'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
				esc_url( DL_URI . '/assets/fonts/' . $font . '.woff2' )
			);
		}
		echo '<meta name="theme-color" content="#000000">' . "\n";
		printf( '<link rel="icon" href="%s" type="image/png">' . "\n", esc_url( DL_URI . '/assets/img/logo-dg.png' ) );
	},
	1
);

// Remove front-end weight that does nothing for shoppers.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

// Render a page's main content inside the standard content width.
add_filter(
	'body_class',
	static function ( $classes ) {
		$classes[] = 'dl';
		return $classes;
	}
);

/**
 * Public URL of a theme CSS or JS file, under a name that changes whenever the file changes.
 *
 * The host's cache ignores "?ver=" on static files, so a changed file needs a new name to reach
 * shoppers. A copy named after the file's contents is kept in uploads/dl-build and made on the
 * first visit after an update. If the copy cannot be made, the normal theme file is used.
 *
 * @param string $rel Path inside the theme, starting with a slash.
 * @return string
 */
function dl_asset_url( $rel ) {
	$src  = DL_DIR . $rel;
	$hash = md5_file( $src );
	if ( ! $hash ) {
		return DL_URI . $rel;
	}
	$info = pathinfo( $rel );
	$name = $info['filename'] . '-' . substr( $hash, 0, 10 ) . '.' . $info['extension'];
	$up   = wp_get_upload_dir();
	$dir  = $up['basedir'] . '/dl-build';
	if ( ! file_exists( $dir . '/' . $name ) ) {
		$body = file_get_contents( $src ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( 'css' === $info['extension'] ) {
			// Point relative links (fonts) back at the theme folder.
			$base = DL_URI . dirname( $rel ) . '/';
			$body = preg_replace( '#url\(([\"\']?)\.\./#', 'url($1' . $base . '../', $body );
		}
		$tmp = $dir . '/' . $name . '.' . wp_generate_password( 6, false ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! wp_mkdir_p( $dir ) || false === $body || false === file_put_contents( $tmp, $body ) || ! rename( $tmp, $dir . '/' . $name ) ) {
			if ( isset( $tmp ) && file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return DL_URI . $rel;
		}
		// Remove copies of this file older than two days; pages still cached may use recent ones.
		foreach ( (array) glob( $dir . '/' . $info['filename'] . '-*.' . $info['extension'] ) as $old ) {
			if ( basename( $old ) !== $name && filemtime( $old ) < time() - 2 * DAY_IN_SECONDS ) {
				wp_delete_file( $old );
			}
		}
	}
	return set_url_scheme( $up['baseurl'], (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ) . '/dl-build/' . $name;
}
