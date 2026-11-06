<?php
/**
 * Plugin Name: Dlight AI
 * Description: A built-in shopping assistant that answers from the store's own products and policies. Uses no external AI service.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Dlight Giftshop
 * Text Domain: dlight-assistant
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

define( 'DLA_VERSION', '1.1.0' );
define( 'DLA_DIR', plugin_dir_path( __FILE__ ) );
define( 'DLA_URL', plugin_dir_url( __FILE__ ) );

require DLA_DIR . 'includes/knowledge.php';
require DLA_DIR . 'includes/details.php';
require DLA_DIR . 'includes/indexer.php';
require DLA_DIR . 'includes/leads.php';
require DLA_DIR . 'includes/logging.php';
require DLA_DIR . 'includes/admin.php';

register_activation_hook(
	__FILE__,
	static function () {
		dla_install_log_table();
		dla_build_index();
		if ( ! wp_next_scheduled( 'dla_daily' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'daily', 'dla_daily' );
		}
	}
);

register_deactivation_hook(
	__FILE__,
	static function () {
		wp_clear_scheduled_hook( 'dla_daily' );
		wp_clear_scheduled_hook( 'dla_rebuild' );
	}
);

add_action( 'dla_rebuild', 'dla_build_index' );
add_action(
	'dla_daily',
	static function () {
		dla_build_index();
		dla_purge_old_logs();
	}
);

/**
 * Add a tiny launcher to shop pages. Everything else loads only when a shopper opens the chat,
 * so the assistant adds almost nothing to page speed.
 */
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( function_exists( 'is_checkout' ) && is_checkout() ) {
			return;
		}
		$options = dla_options();
		if ( empty( $options['enabled'] ) || ! get_option( 'dla_index_version' ) ) {
			return;
		}
		wp_enqueue_script( 'dla-loader', dla_asset_url( 'loader.js' ), array(), null, array( 'strategy' => 'defer', 'in_footer' => true ) );

		$config = array(
			'index'    => dla_index_url(),
			'files'    => array(
				'engine.js'  => dla_asset_url( 'engine.js' ),
				'widget.js'  => dla_asset_url( 'widget.js' ),
				'widget.css' => dla_asset_url( 'widget.css' ),
			),
			'store'    => esc_url_raw( rest_url( 'wc/store/v1/' ) ),
			'log'      => esc_url_raw( rest_url( 'dlight-assistant/v1/log' ) ),
			'lead'     => esc_url_raw( rest_url( 'dlight-assistant/v1/lead' ) ),
			'home'     => home_url( '/' ),
			'product'  => ( function_exists( 'is_product' ) && is_product() ) ? get_the_ID() : 0,
			'greeting' => $options['greeting'],
			'handoff'  => empty( $options['whatsapp'] ) ? '' : 'https://wa.me/' . preg_replace( '/\D/', '', $options['whatsapp'] ),
		);
		wp_add_inline_script( 'dla-loader', 'window.DLA=' . wp_json_encode( $config ) . ';', 'before' );
	},
	120
);

// Launcher styles are inline, so nothing extra is downloaded until the chat is opened.
add_action(
	'wp_head',
	static function () {
		if ( ( function_exists( 'is_checkout' ) && is_checkout() ) || empty( dla_options()['enabled'] ) ) {
			return;
		}
		echo '<style id="dla-launcher">.dla-launch{position:fixed;right:18px;bottom:18px;z-index:44;display:flex;align-items:center;gap:8px;min-height:48px;padding:0 18px 0 14px;border:1px solid #c0a16b;border-radius:26px;background:#000;color:#f0d9a8;font:600 15px/1 Jost,system-ui,sans-serif;cursor:pointer;box-shadow:0 8px 24px rgba(0,0,0,.28)}.dla-launch:hover{background:#1c1712}.dla-launch svg{width:22px;height:22px;fill:none;stroke:currentColor;stroke-width:1.5;stroke-linecap:round;stroke-linejoin:round}@media(max-width:760px){.dla-launch{right:12px;bottom:calc(72px + env(safe-area-inset-bottom));min-height:46px;padding:0 14px 0 12px;font-size:14px}body:has(.dl-buybar.is-on) .dla-launch{bottom:calc(132px + env(safe-area-inset-bottom))}}</style>' . "\n";
	}
);

/**
 * Public URL of a chat file, under a name that changes whenever the file changes.
 *
 * The host's cache ignores "?v=" on static files, so an updated file needs a new name. A copy
 * named after its contents is kept in uploads/dl-assistant/build. If it cannot be made, the
 * plugin's own file is used.
 *
 * @param string $name File name inside assets/.
 * @return string
 */
function dla_asset_url( $name ) {
	$src  = DLA_DIR . 'assets/' . $name;
	$hash = md5_file( $src );
	if ( ! $hash ) {
		return DLA_URL . 'assets/' . $name;
	}
	$info       = pathinfo( $name );
	$file       = $info['filename'] . '-' . substr( $hash, 0, 10 ) . '.' . $info['extension'];
	list( $dir, $url ) = dla_index_location();
	$dir       .= '/build';
	if ( ! file_exists( $dir . '/' . $file ) ) {
		$tmp = $dir . '/' . $file . '.' . wp_generate_password( 6, false ) . '.tmp';
		// phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! wp_mkdir_p( $dir ) || ! copy( $src, $tmp ) || ! rename( $tmp, $dir . '/' . $file ) ) {
			if ( file_exists( $tmp ) ) {
				wp_delete_file( $tmp );
			}
			return DLA_URL . 'assets/' . $name;
		}
		foreach ( (array) glob( $dir . '/' . $info['filename'] . '-*.' . $info['extension'] ) as $old ) {
			if ( basename( $old ) !== $file && filemtime( $old ) < time() - 2 * DAY_IN_SECONDS ) {
				wp_delete_file( $old );
			}
		}
	}
	return $url . '/build/' . $file;
}
