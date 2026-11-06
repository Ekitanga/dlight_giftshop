<?php
/**
 * Account page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="dl-wrap dl-commerce dl-commerce--account">
	<h1 class="dl-page__title">My Account</h1>
	<?php echo do_shortcode( '[woocommerce_my_account]' ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
</div>
<?php
get_footer();
