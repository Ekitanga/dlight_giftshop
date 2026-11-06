<?php
/**
 * Checkout page. The live page has no stored content, so the theme supplies the checkout.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="dl-wrap dl-commerce dl-commerce--checkout">
	<?php if ( ! is_order_received_page() ) : ?>
		<h1 class="dl-page__title"><?php echo is_wc_endpoint_url( 'order-pay' ) ? 'Complete your payment' : 'Checkout'; ?></h1>
	<?php endif; ?>
	<?php if ( ! is_order_received_page() && ! is_wc_endpoint_url( 'order-pay' ) ) : ?>
		<p class="dl-checkout-trust">Secure checkout. Pay by M-Pesa, or pay when your order arrives.</p>
	<?php endif; ?>
	<?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
