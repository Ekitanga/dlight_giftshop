<?php
/**
 * Dlight theme bootstrap.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

define( 'DL_DIR', get_template_directory() );
define( 'DL_URI', get_template_directory_uri() );

define( 'DL_PHONE', '+254740611468' );
define( 'DL_PHONE_DISPLAY', '+254 740 611 468' );
define( 'DL_WHATSAPP', '254740611468' );
define( 'DL_EMAIL', 'dlightgiftshop21@gmail.com' );
define( 'DL_ADDRESS', 'Rasumal House, 4th Floor, Shop 14, Tom Mboya Street, Nairobi' );
define( 'DL_MAP', 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( 'Rasumal House, Tom Mboya Street, Nairobi' ) );

require DL_DIR . '/inc/data.php';
require DL_DIR . '/inc/setup.php';
require DL_DIR . '/inc/shop.php';
require DL_DIR . '/inc/seo.php';
require DL_DIR . '/inc/pages.php';
require DL_DIR . '/inc/images.php';
require DL_DIR . '/inc/perf.php';
