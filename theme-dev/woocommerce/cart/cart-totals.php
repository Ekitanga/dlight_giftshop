<?php
/**
 * Cart summary: subtotal, any discount, how delivery works, and the total before delivery.
 * Delivery is chosen at checkout, so the cart does not ask for it.
 * Based on WooCommerce's cart-totals.php.
 *
 * @package dlight
 * @version 2.3.6
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cart_totals dl-cart__summary">
	<?php do_action( 'woocommerce_before_cart_totals' ); ?>
	<h2>Order summary</h2>
	<table cellspacing="0" class="shop_table">
		<tr class="cart-subtotal">
			<th>Subtotal</th>
			<td><?php wc_cart_totals_subtotal_html(); ?></td>
		</tr>
		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>">
				<th><?php wc_cart_totals_coupon_label( $coupon ); ?></th>
				<td><?php wc_cart_totals_coupon_html( $coupon ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>
		<tr class="shipping dl-cart__delivery">
			<th>Delivery</th>
			<td>Chosen at checkout</td>
		</tr>
		<?php do_action( 'woocommerce_cart_totals_before_order_total' ); ?>
		<tr class="order-total">
			<th>Total</th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>
		<?php do_action( 'woocommerce_cart_totals_after_order_total' ); ?>
	</table>
	<p class="dl-cart__hint">Delivery within Nairobi KSh 350 &middot; Speedaf to other towns KSh 350 &middot; free pickup at our shop in town.</p>
	<div class="wc-proceed-to-checkout">
		<?php do_action( 'woocommerce_proceed_to_checkout' ); ?>
	</div>
	<p class="dl-cart__trust">Pay by M-Pesa, or pay when your order arrives.</p>
	<?php do_action( 'woocommerce_after_cart_totals' ); ?>
</div>
