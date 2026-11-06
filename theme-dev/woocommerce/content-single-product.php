<?php
/**
 * Product page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( post_password_required() ) {
	echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput
	return;
}

list( $name, $note ) = dl_split_title( $product->get_name() );

$eyebrow  = dl_product_eyebrow( $product );
$images   = array_values( array_filter( array_merge( array( $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );
$ask      = dl_whatsapp_product_text( $product );
$pairs    = dl_pair_ids( $product );
$related  = wc_get_related_products( $product->get_id(), 5 );
$poster   = dl_is_perfume( $product->get_id() );
$discount = dl_discount( $product );
$regular  = (float) $product->get_regular_price();
$current  = (float) $product->get_price();

do_action( 'woocommerce_before_single_product' );
?>

<div class="dl-wrap dl-crumbs"><?php woocommerce_breadcrumb(); ?></div>

<div id="product-<?php the_ID(); ?>" <?php wc_product_class( 'dl-pdp dl-wrap', $product ); ?>>
	<div class="dl-gallery<?php echo $poster ? ' dl-gallery--poster' : ''; ?>">
		<div class="dl-gallery__main" id="dl-gallery-main" tabindex="0" aria-label="Product images">
			<?php
			foreach ( $images as $i => $image_id ) {
				$dl_full = wp_get_attachment_url( $image_id ); // The original upload, sharpest for zooming.
				echo '<figure class="dl-gallery__item" id="dl-img-' . (int) $i . '"' . ( $dl_full ? ' data-full="' . esc_url( $dl_full ) . '"' : '' ) . '>';
				echo wp_get_attachment_image( // phpcs:ignore WordPress.Security.EscapeOutput
					$image_id,
					'woocommerce_single',
					false,
					array(
						'alt'           => 0 === $i ? $name : $name . ' view ' . ( $i + 1 ),
						'loading'       => 0 === $i ? 'eager' : 'lazy',
						'fetchpriority' => 0 === $i ? 'high' : 'auto',
						'sizes'         => '(min-width: 900px) 46vw, 100vw',
					)
				);
				echo '</figure>';
			}
			if ( ! $images ) {
				echo '<figure class="dl-gallery__item">' . wc_placeholder_img( 'woocommerce_single' ) . '</figure>'; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			?>
			<?php if ( $discount ) : ?>
				<span class="dl-badge dl-badge--lg">-<?php echo (int) $discount; ?>%</span>
			<?php endif; ?>
		</div>
		<?php if ( count( $images ) > 1 ) : ?>
			<div class="dl-gallery__thumbs">
				<?php foreach ( $images as $i => $image_id ) : ?>
					<button type="button" class="js-thumb<?php echo 0 === $i ? ' is-on' : ''; ?>" data-to="dl-img-<?php echo (int) $i; ?>" aria-label="<?php echo esc_attr( 'Show image ' . ( $i + 1 ) ); ?>">
						<?php echo wp_get_attachment_image( $image_id, 'woocommerce_gallery_thumbnail', false, array( 'alt' => '', 'loading' => 'lazy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</button>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>

	<div class="dl-buy">
		<?php if ( $eyebrow ) : ?>
			<p class="dl-buy__brand"><?php echo esc_html( $eyebrow ); ?></p>
		<?php endif; ?>
		<h1 class="dl-buy__title"><?php echo esc_html( $name ); ?></h1>
		<?php if ( $note ) : ?>
			<p class="dl-buy__note"><?php echo esc_html( $note ); ?></p>
		<?php endif; ?>

		<div class="dl-buy__pricebox">
			<div class="dl-buy__price dl-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
			<?php if ( $discount && $regular > $current ) : ?>
				<span class="dl-save">You save <?php echo wp_kses_post( wc_price( $regular - $current ) ); ?></span>
			<?php endif; ?>
			<?php if ( $product->is_in_stock() ) : ?>
				<span class="dl-instock">In stock</span>
			<?php else : ?>
				<span class="dl-outstock">Out of stock</span>
			<?php endif; ?>
		</div>

		<div class="dl-buy__form" id="dl-buy-form">
			<?php woocommerce_template_single_add_to_cart(); ?>
		</div>

		<a class="dl-btn dl-btn--wa dl-buy__wa" href="<?php echo dl_whatsapp_href( $ask ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in dl_whatsapp_href(). ?>" target="_blank" rel="noopener">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.200-4A8 8 0 1 1 8 18.800z"/><path d="M9 8.500c0 3.500 3 6.500 6.500 6.500l1-1.700-2.200-1-1 .9c-1-.4-1.700-1.200-2.100-2.100l.9-1-1-2.200z"/></svg>
			Order on WhatsApp
		</a>

		<?php if ( has_term( 'tester-perfumes', 'product_cat', $product->get_id() ) || 0 < count( array_intersect( wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) ), get_term_children( (int) get_term_by( 'slug', 'tester-perfumes', 'product_cat' )->term_id, 'product_cat' ) ) ) ) : ?>
			<details class="dl-tester">
				<summary>This is a tester perfume. What does that mean?</summary>
				<p><?php echo esc_html( 'Tester perfumes are originally produced by fragrance houses for customers to sample and test in stores before buying the retail versions. They contain the same original fragrance as the retail bottles, with the same scent and quality. The main difference is usually the packaging: testers may come in plain boxes, have a tester label, or lack some of the presentation found in retail packaging.' ); ?></p>
			</details>
		<?php endif; ?>
		<ul class="dl-assure">
			<li>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7.500" cy="17.500" r="1.800"/><circle cx="17.500" cy="17.500" r="1.800"/></svg>
				<div><strong>Delivery</strong>Across Kenya. Charges vary by location and are shown at checkout. <a href="<?php echo esc_url( home_url( '/delivery-information/' ) ); ?>">Delivery information</a></div>
			</li>
			<li>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18v11H3zM3 11h18"/><path d="M7 15h3"/></svg>
				<div><strong>Payment</strong>Pay securely with M-Pesa. <a href="<?php echo esc_url( home_url( '/payment-options/' ) ); ?>">Payment options</a></div>
			</li>
			<li>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.500 5.500 4h13L20 9.500M5 9.500V20h14V9.500M4 9.500h16"/><path d="M10 20v-5h4v5"/></svg>
				<div><strong>Shop pickup</strong>Collect at Rasumal House, Tom Mboya Street.</div>
			</li>
			<li>
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 8h16v12H4zM4 8l3-4h10l3 4M9 13l-2 2 2 2M7 15h7a3 3 0 0 0 0-6"/></svg>
				<div><strong>Returns</strong>See our <a href="<?php echo esc_url( home_url( '/returns-refunds/' ) ); ?>">returns and refunds policy</a>.</div>
			</li>
		</ul>

		<?php
		// Stays for WooCommerce structured data and any plugin that hooks in here.
		do_action( 'woocommerce_single_product_summary' );
		?>
	</div>
</div>

<?php if ( $product->get_description() || $product->get_short_description() ) : ?>
<section class="dl-wrap dl-pdp-desc" aria-labelledby="desc-title">
	<h2 id="desc-title">Product Description</h2>
	<div class="dl-prose">
		<?php
		if ( $product->get_description() ) {
			the_content();
		} else {
			echo wp_kses_post( apply_filters( 'woocommerce_short_description', $product->get_short_description() ) );
		}
		?>
	</div>
</section>
<?php endif; ?>

<?php if ( $related ) : ?>
<section class="dl-section" aria-labelledby="related-title">
	<div class="dl-wrap">
		<div class="dl-head"><h2 id="related-title">Related Products</h2></div>
		<div class="dl-grid dl-grid--5"><?php dl_cards( $related ); ?></div>
	</div>
</section>
<?php endif; ?>

<?php if ( $pairs ) : ?>
<section class="dl-section" aria-labelledby="pair-title">
	<div class="dl-wrap">
		<div class="dl-head"><h2 id="pair-title">Complete the Gift</h2></div>
		<div class="dl-grid dl-grid--5"><?php dl_cards( array_slice( $pairs, 0, 5 ) ); ?></div>
	</div>
</section>
<?php endif; ?>

<?php if ( $product->is_purchasable() && $product->is_in_stock() ) : ?>
<div class="dl-buybar" id="dl-buybar" aria-hidden="true">
	<div class="dl-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
	<a class="dl-btn dl-btn--dark js-add" rel="nofollow" tabindex="-1" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-id="<?php echo esc_attr( $product->get_id() ); ?>">Add to Cart</a>
</div>
<?php endif; ?>

<?php
do_action( 'woocommerce_after_single_product' );
?>

<dialog class="dl-zoom" id="dl-zoom" aria-label="Photo, zoomed">
	<button type="button" class="dl-zoom__close js-zoom-close" aria-label="Close">&times;</button>
	<div class="dl-zoom__stage js-zoom-stage"><img alt="" decoding="async"></div>
	<p class="dl-zoom__hint">Tap the photo to zoom in or out</p>
</dialog>
