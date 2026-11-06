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
	<h1 class="dl-page__title"><?php echo is_order_received_page() ? 'Thank You for Your Order' : 'Checkout'; ?></h1>
	<?php echo do_shortcode( '[woocommerce_checkout]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
