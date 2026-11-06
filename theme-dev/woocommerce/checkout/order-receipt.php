<?php
/**
 * Payment page after checkout (for example, M-Pesa). The payment card shows the order number and
 * amount, so WooCommerce's separate list of order facts is left out.
 * Based on WooCommerce's order-receipt.php.
 *
 * @package dlight
 * @version 3.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_receipt_' . $order->get_payment_method(), $order->get_id() ); ?>

<div class="clear"></div>
