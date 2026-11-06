<?php
/**
 * Order summary: each item with its photo, then subtotal, delivery and total.
 * Delivery is chosen in step 2, so this shows the chosen option and its price, without its own buttons.
 * Based on WooCommerce's review-order.php.
 *
 * @package dlight
 * @version 11.2.2
 */

defined( 'ABSPATH' ) || exit;

$dl_rates  = array();
$dl_pack   = WC()->shipping() ? WC()->shipping()->get_packages() : array();
$dl_rates  = $dl_pack[0]['rates'] ?? array();
$dl_chosen = dl_chosen_method();
$dl_rate   = $dl_rates[ $dl_chosen ] ?? ( $dl_rates ? reset( $dl_rates ) : null );
?>
<table class="shop_table woocommerce-checkout-review-order-table">
	<tbody>
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			$visible  = apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key );
			if ( ! ( $_product instanceof WC_Product && $_product->exists() && $cart_item['quantity'] > 0 && $visible ) ) {
				continue;
			}
			$dl_name = apply_filters( 'woocommerce_cart_item_name', WC()->cart->get_item_product_name( $cart_item, $_product ), $cart_item, $cart_item_key );
			?>
			<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<td class="product-name">
					<span class="dl-sum__item">
						<span class="dl-sum__img"><?php echo $_product->get_image( 'thumbnail' ); // phpcs:ignore WordPress.Security.EscapeOutput ?><b><?php echo esc_html( $cart_item['quantity'] ); ?></b></span>
						<span class="dl-sum__name"><?php echo wp_kses_post( $dl_name ); ?><?php echo wc_get_formatted_cart_item_data( $cart_item, false ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
					</span>
				</td>
				<td class="product-total"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></td>
			</tr>
			<?php
		}
		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</tbody>
	<tfoot>
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
		<?php if ( WC()->cart->needs_shipping() ) : ?>
			<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
			<tr class="woocommerce-shipping-totals shipping">
				<th>Delivery</th>
				<td>
					<?php
					if ( $dl_rate ) {
						echo (float) $dl_rate->get_cost() > 0 ? wp_kses_post( wc_price( (float) $dl_rate->get_cost() ) ) : 'Free';
					} else {
						echo 'Choose in step 2';
					}
					?>
				</td>
			</tr>
			<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
		<?php endif; ?>
		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee">
				<th><?php echo esc_html( $fee->name ); ?></th>
				<td><?php wc_cart_totals_fee_html( $fee ); ?></td>
			</tr>
		<?php endforeach; ?>
		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
		<tr class="order-total">
			<th>Total</th>
			<td><?php wc_cart_totals_order_total_html(); ?></td>
		</tr>
		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</tfoot>
</table>
