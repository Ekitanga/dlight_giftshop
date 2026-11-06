<?php
/**
 * Not found page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="dl-wrap dl-page dl-empty">
	<h1 class="dl-page__title">Page not found</h1>
	<p>This page may have moved. Search above, or start here.</p>
	<div class="dl-actions">
		<a class="dl-btn dl-btn--dark" href="<?php echo esc_url( dl_cat_url( 'perfumes' ) ); ?>">Shop Perfumes</a>
		<a class="dl-btn dl-btn--outline" href="<?php echo esc_url( dl_cat_url( 'watches' ) ); ?>">Shop Watches</a>
	</div>
</div>
<?php
get_footer();
