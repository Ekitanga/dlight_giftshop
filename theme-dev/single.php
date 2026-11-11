<?php
/**
 * Buying guide / blog article.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	$dl_ids  = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( get_the_ID(), 'dl_featured_products', true ) ) ) );
	$dl_ids  = array_values( array_filter( $dl_ids, static fn( $id ) => ( $p = wc_get_product( $id ) ) && $p->is_visible() && $p->is_in_stock() ) );
	$dl_shop = (string) get_post_meta( get_the_ID(), 'dl_shop_link', true );
	$dl_shop_label = (string) get_post_meta( get_the_ID(), 'dl_shop_label', true );
	?>
	<div class="dl-wrap dl-crumbs"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> / <a href="<?php echo esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ); ?>">Buying Guides</a></div>
	<article class="dl-wrap dl-page dl-article">
		<h1 class="dl-page__title"><?php the_title(); ?></h1>
		<p class="dl-article__meta">By Dlight Giftshop &middot; <time datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">Updated <?php echo esc_html( get_the_modified_date( 'j F Y' ) ); ?></time></p>
		<div class="dl-prose"><?php the_content(); ?></div>
		<?php if ( $dl_shop ) : ?>
			<p><a class="dl-btn dl-btn--dark" href="<?php echo esc_url( $dl_shop ); ?>"><?php echo esc_html( $dl_shop_label ? $dl_shop_label : 'Shop now' ); ?></a></p>
		<?php endif; ?>
	</article>
	<?php if ( $dl_ids ) : ?>
		<section class="dl-section" aria-labelledby="guide-shop">
			<div class="dl-wrap">
				<div class="dl-head"><h2 id="guide-shop">Shop This Guide</h2></div>
				<div class="dl-grid dl-grid--5"><?php dl_cards( array_slice( $dl_ids, 0, 10 ) ); ?></div>
			</div>
		</section>
	<?php endif; ?>
	<section class="dl-wrap dl-article__help">
		<p>Not sure which one to choose? <a href="<?php echo dl_whatsapp_href( 'Hello Dlight Giftshop, I read your guide "' . html_entity_decode( get_the_title(), ENT_QUOTES, 'UTF-8' ) . '" and I would like help choosing.' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in dl_whatsapp_href(). ?>" target="_blank" rel="noopener">Chat with us on WhatsApp</a> and we will help you pick.</p>
	</section>
	<?php
}

get_footer();
