<?php
/**
 * Cart page. Slug-based so it ignores the old page builder markup.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="dl-wrap dl-commerce dl-commerce--cart">
	<h1 class="dl-page__title">Shopping Cart</h1>
	<?php echo do_shortcode( '[woocommerce_cart]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
