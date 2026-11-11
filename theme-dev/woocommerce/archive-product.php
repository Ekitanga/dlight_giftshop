<?php
/**
 * Shop page and product category pages.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();

global $wp_query;

$term     = is_product_taxonomy() ? get_queried_object() : null;
$title    = $term ? ( 'product_cat' === $term->taxonomy ? dl_term_display_name( $term ) : html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) ) : ( dl_landing_title() ?: 'All Products' );
$base     = $term ? get_term_link( $term ) : wc_get_page_permalink( 'shop' );
$base     = is_wp_error( $base ) ? '' : $base;
$children = $term
	? get_terms( array( 'taxonomy' => $term->taxonomy, 'parent' => $term->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ) )
	: array_merge( dl_children( 'perfumes' ), dl_watch_brands() );
$children = is_wp_error( $children ) ? array() : $children;

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$for     = isset( $_GET['dl_for'] ) ? sanitize_key( $_GET['dl_for'] ) : '';
$min     = isset( $_GET['min_price'] ) ? absint( $_GET['min_price'] ) : '';
$max     = isset( $_GET['max_price'] ) ? absint( $_GET['max_price'] ) : '';
$price   = $min . '-' . $max;
$orderby = isset( $_GET['orderby'] ) ? sanitize_key( $_GET['orderby'] ) : '';
$kind    = isset( $_GET['dl_kind'] ) ? sanitize_key( $_GET['dl_kind'] ) : '';
$cats    = isset( $_GET['dl_cats'] ) ? sanitize_text_field( wp_unslash( $_GET['dl_cats'] ) ) : '';
// phpcs:enable

$search = get_search_query();
$total  = (int) $wp_query->found_posts;
?>

<div class="dl-wrap dl-crumbs"><?php woocommerce_breadcrumb(); ?></div>

<div class="dl-wrap dl-shop">
	<aside class="dl-side" aria-label="Filters">
		<?php if ( $children ) : ?>
			<div class="dl-side__box">
				<h2>Categories</h2>
				<ul class="dl-side__cats">
					<?php foreach ( $children as $child ) : ?>
						<li><a href="<?php echo esc_url( get_term_link( $child ) ); ?>"><?php echo esc_html( html_entity_decode( $child->name, ENT_QUOTES, 'UTF-8' ) ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<form class="dl-side__box dl-filters" id="dl-filters" action="<?php echo esc_url( $base ); ?>" method="get">
			<h2>Filter</h2>
			<?php if ( is_search() ) : ?>
				<input type="hidden" name="s" value="<?php echo esc_attr( $search ); ?>">
				<input type="hidden" name="post_type" value="product">
			<?php endif; ?>
			<?php if ( $cats ) : ?>
				<input type="hidden" name="dl_cats" value="<?php echo esc_attr( $cats ); ?>">
			<?php endif; ?>
			<?php if ( $kind ) : ?>
				<input type="hidden" name="dl_kind" value="<?php echo esc_attr( $kind ); ?>">
			<?php endif; ?>
			<input type="hidden" name="min_price" value="<?php echo esc_attr( $min ); ?>" data-min>
			<input type="hidden" name="max_price" value="<?php echo esc_attr( $max ); ?>" data-max>
			<?php if ( ! $cats ) : ?>
				<label class="dl-filter"><span>Gender</span>
					<select name="dl_for">
						<option value="">All</option>
						<option value="her" <?php selected( $for, 'her' ); ?>>Women</option>
						<option value="him" <?php selected( $for, 'him' ); ?>>Men</option>
					</select>
				</label>
			<?php endif; ?>
			<label class="dl-filter"><span>Price</span>
				<select data-price>
					<option value="-">Any price</option>
					<?php
					foreach ( array( '0-5000' => 'Under KSh 5,000', '5000-10000' => 'KSh 5,000 - 10,000', '10000-20000' => 'KSh 10,000 - 20,000', '20000-' => 'Over KSh 20,000' ) as $value => $label ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $price, $value, false ), esc_html( $label ) );
					}
					?>
				</select>
			</label>
			<label class="dl-filter dl-filter--sort"><span>Sort by</span>
				<select name="orderby">
					<?php
					foreach ( array( '' => 'Newest arrivals, best price first', 'popularity' => 'Best selling', 'date' => 'Newest', 'price' => 'Price: low to high', 'price-desc' => 'Price: high to low' ) as $value => $label ) {
						printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $orderby, $value, false ), esc_html( $label ) );
					}
					?>
				</select>
			</label>
			<noscript><button class="dl-btn dl-btn--dark" type="submit">Apply</button></noscript>
			<?php if ( dl_is_filtered() ) : ?>
				<a class="dl-more dl-filters__clear" href="<?php echo esc_url( $base ); ?>">Clear filters</a>
			<?php endif; ?>
		</form>
	</aside>

	<div class="dl-shop__main">
		<div class="dl-shop__head">
			<h1><?php echo is_search() ? 'Search results for "' . esc_html( $search ) . '"' : esc_html( $title ); ?></h1>
			<p><?php echo esc_html( number_format_i18n( $total ) ); ?> <?php echo 1 === $total ? 'product' : 'products'; ?></p>
		</div>
		<?php woocommerce_output_all_notices(); ?>
		<?php if ( woocommerce_product_loop() && have_posts() ) : ?>
			<div class="dl-grid dl-grid--shop">
				<?php
				while ( have_posts() ) {
					the_post();
					wc_get_template_part( 'content', 'product' );
				}
				?>
			</div>
			<?php woocommerce_pagination(); ?>
		<?php else : ?>
			<div class="dl-empty">
				<h2>No products found</h2>
				<p>Try removing a filter, or ask us on WhatsApp and we'll help you find it.</p>
				<a class="dl-btn dl-btn--wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I am looking for: ' . $search ) ); ?>" target="_blank" rel="noopener">Ask on WhatsApp</a>
			</div>
		<?php endif; ?>
		<?php if ( $term && ! is_paged() && term_description( $term ) ) : ?>
			<div class="dl-prose dl-shop__desc"><?php echo wp_kses_post( term_description( $term ) ); ?></div>
		<?php endif; ?>
	</div>
</div>

<?php
get_footer();
