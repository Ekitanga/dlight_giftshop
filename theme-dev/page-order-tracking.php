<?php
/**
 * Order tracking page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

dl_help_open( 'order-tracking', 'Track Your Order', 'Enter your order number and billing email to see its status.' );
echo do_shortcode( '[woocommerce_order_tracking]' ); // phpcs:ignore WordPress.Security.EscapeOutput
dl_help_close();
