<?php
/**
 * WooCommerce behaviour: cards, filters, cart drawer, product page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

// Display only: "KSh 45,000" instead of "KSh45,000.00". Stored prices are untouched.
add_filter( 'woocommerce_price_trim_zeros', '__return_true' );
add_filter(
	'woocommerce_price_format',
	static function () {
		return '%1$s&nbsp;%2$s';
	}
);

add_filter( 'woocommerce_product_single_add_to_cart_text', static fn() => 'Add to Cart' );

// Buy Now: adds the item through the normal store form, then goes straight to checkout.
add_action(
	'woocommerce_after_add_to_cart_button',
	static function () {
		global $product;
		if ( $product && $product->is_type( 'simple' ) ) {
			printf(
				'<button type="submit" name="add-to-cart" value="%d" class="dl-buynow" formaction="%s">Buy Now</button>',
				(int) $product->get_id(),
				esc_url( add_query_arg( 'dl_buy', '1', $product->get_permalink() ) )
			);
		}
	}
);
add_filter(
	'woocommerce_add_to_cart_redirect',
	static function ( $url ) {
		return isset( $_GET['dl_buy'] ) ? wc_get_checkout_url() : $url; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}
);

/**
 * Discount percentage from the prices set on the product.
 *
 * @param WC_Product $product Product.
 * @return int
 */
function dl_discount( $product ) {
	$regular = (float) $product->get_regular_price();
	$current = (float) $product->get_price();
	return ( $regular > 0 && $current < $regular ) ? (int) round( ( $regular - $current ) / $regular * 100 ) : 0;
}

// Only the product name is an H1. Headings typed into descriptions are demoted.
add_filter(
	'the_content',
	static function ( $content ) {
		if ( is_singular() ) {
			$content = preg_replace( '#<(/?)h1\b#i', '<$1h2', $content );
		}
		return $content;
	}
);

// Most Kenyan shoppers have no postcode to give, and delivery here does not use one.
add_filter(
	'woocommerce_checkout_fields',
	static function ( $fields ) {
		unset( $fields['billing']['billing_postcode'], $fields['shipping']['shipping_postcode'], $fields['billing']['billing_company'], $fields['shipping']['shipping_company'] );
		return $fields;
	}
);
add_filter(
	'woocommerce_get_country_locale',
	static function ( $locale ) {
		$locale['KE']['postcode'] = array(
			'required' => false,
			'hidden'   => true,
		);
		return $locale;
	}
);

// Show 24 products a page in a four-column grid.
add_filter( 'loop_shop_per_page', static fn() => 24 );
add_filter( 'loop_shop_columns', static fn() => 4 );

// Remove every fabricated urgency signal from the old theme's companion plugin.
add_action(
	'wp',
	static function () {
		$fake = array( 'nasa_fake_sold', 'nasa_fake_in_cart', 'nasa_fake_view', 'nasa_fake_purchase' );
		foreach ( $fake as $callback ) {
			remove_action( 'woocommerce_single_product_summary', $callback, 22 );
			remove_action( 'woocommerce_single_product_summary', $callback );
		}

		// The product template prints these itself, in its own layout.
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title', 5 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price', 10 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt', 20 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
		remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
	}
);

/**
 * Print one product card.
 *
 * @param WC_Product $product Product.
 * @param array      $args    rank: position number; eager: load image immediately.
 */
function dl_card( $product, $args = array() ) {
	if ( ! $product instanceof WC_Product ) {
		return;
	}
	list( $name ) = dl_split_title( $product->get_name() );

	$url   = $product->get_permalink();
	$attrs = array(
		'alt'     => $name,
		'sizes'   => '(min-width: 1100px) 24vw, (min-width: 700px) 32vw, 46vw',
		'loading' => empty( $args['eager'] ) ? 'lazy' : 'eager',
		'class'   => 'dl-card__img',
	);
	?>
	<article class="dl-card<?php echo dl_is_perfume( $product->get_id() ) ? ' dl-card--poster' : ''; ?>">
		<a class="dl-card__media" href="<?php echo esc_url( $url ); ?>" aria-label="<?php echo esc_attr( $name ); ?>">
			<?php if ( dl_discount( $product ) ) : ?>
				<span class="dl-badge">-<?php echo (int) dl_discount( $product ); ?>%</span>
			<?php endif; ?>
			<?php echo $product->get_image_id() ? wp_get_attachment_image( $product->get_image_id(), 'woocommerce_thumbnail', false, $attrs ) : wc_placeholder_img( 'woocommerce_thumbnail', $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</a>
		<div class="dl-card__body">
			<?php $dl_tag = empty( $args['tag'] ) ? 'h3' : 'h2'; ?>
			<<?php echo $dl_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?> class="dl-card__name"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a></<?php echo $dl_tag; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
			<div class="dl-price"><?php echo wp_kses_post( $product->get_price_html() ); ?></div>
			<?php if ( $product->is_purchasable() && $product->is_in_stock() && $product->is_type( 'simple' ) ) : ?>
				<a class="dl-add js-add" rel="nofollow" href="<?php echo esc_url( $product->add_to_cart_url() ); ?>" data-id="<?php echo esc_attr( $product->get_id() ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Add %s to cart', $name ) ); ?>">Add to Cart</a>
			<?php endif; ?>
		</div>
	</article>
	<?php
}

/**
 * Print a row of cards from IDs.
 *
 * @param int[] $ids  Product IDs.
 * @param array $args ranked: show rank numbers.
 */
function dl_cards( $ids, $args = array() ) {
	$i = 0;
	foreach ( $ids as $id ) {
		$product = wc_get_product( $id );
		if ( $product && $product->is_visible() ) {
			++$i;
			dl_card( $product, empty( $args['ranked'] ) ? array() : array( 'rank' => $i ) );
		}
	}
}

/*
 * Shop filters. They use the store's own query, so they work without JavaScript
 * and stay on the existing category URLs.
 */
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		if ( ! ( $query->is_post_type_archive( 'product' ) || $query->is_tax( get_object_taxonomies( 'product' ) ) ) ) {
			return;
		}

		$ids = array();
		$map = dl_finder_map();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
		$for  = isset( $_GET['dl_for'] ) ? sanitize_key( $_GET['dl_for'] ) : '';
		$kind = isset( $_GET['dl_kind'] ) ? sanitize_key( $_GET['dl_kind'] ) : '';
		foreach ( array( 'her', 'him' ) as $who ) {
			if ( ! $for || $for === $who ) {
				foreach ( array( 'perfume', 'watch' ) as $type ) {
					if ( ( $for || $kind ) && ( ! $kind || $kind === $type ) ) {
						$ids = array_merge( $ids, $map[ $who ][ $type ] );
					}
				}
			}
		}
		if ( ! empty( $_GET['dl_cats'] ) ) {
			$ids = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['dl_cats'] ) ) ) );
		}
		// phpcs:enable
		if ( $ids ) {
			$tax   = (array) $query->get( 'tax_query' );
			$tax[] = array(
				'taxonomy' => 'product_cat',
				'field'    => 'term_id',
				'terms'    => $ids,
			);
			$query->set( 'tax_query', $tax );
		}
	},
	20
);

/**
 * Whether the current archive is showing a filtered or sorted view.
 *
 * @return bool
 */
function dl_is_filtered() {
	foreach ( array( 'dl_for', 'dl_kind', 'dl_cats', 'min_price', 'max_price', 'orderby' ) as $key ) {
		if ( ! empty( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
	}
	return false;
}

/*
 * Cart drawer. Its contents travel in WooCommerce's own cart fragments, so full-page
 * caching stays safe: the page never contains anyone's cart.
 */
add_filter(
	'woocommerce_add_to_cart_fragments',
	static function ( $fragments ) {
		$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;

		$fragments['.dl-cart-count'] = '<span class="dl-cart-count" data-count="' . (int) $count . '">' . (int) $count . '</span>';

		ob_start();
		dl_drawer_body();
		$fragments['#dl-drawer-body'] = ob_get_clean();

		return $fragments;
	}
);

/**
 * Print the cart drawer contents.
 */
function dl_drawer_body() {
	$cart = WC()->cart;
	echo '<div id="dl-drawer-body" class="dl-drawer__body">';

	if ( ! $cart || $cart->is_empty() ) {
		echo '<p class="dl-drawer__empty">Your cart is empty.</p>';
		echo '<a class="dl-btn dl-btn--dark" href="' . esc_url( wc_get_page_permalink( 'shop' ) ) . '">Continue Shopping</a>';
		echo '</div>';
		return;
	}

	echo '<ul class="dl-lines">';
	foreach ( $cart->get_cart() as $key => $item ) {
		$product = $item['data'];
		if ( ! $product || ! $product->exists() ) {
			continue;
		}
		list( $name ) = dl_split_title( $product->get_name() );
		printf(
			'<li class="dl-line"><a class="dl-line__img" href="%1$s">%2$s</a><div class="dl-line__info"><a class="dl-line__name" href="%1$s">%3$s</a><span class="dl-line__meta">Qty %4$d &middot; %5$s</span><button type="button" class="dl-line__remove js-remove" data-key="%6$s">Remove</button></div></li>',
			esc_url( $product->get_permalink() ),
			$product->get_image( 'woocommerce_gallery_thumbnail', array( 'alt' => $name ) ), // phpcs:ignore WordPress.Security.EscapeOutput
			esc_html( $name ),
			(int) $item['quantity'],
			wp_kses_post( $cart->get_product_subtotal( $product, $item['quantity'] ) ),
			esc_attr( $key )
		);
	}
	echo '</ul>';

	printf(
		'<div class="dl-drawer__total"><span>Subtotal</span><strong>%s</strong></div><p class="dl-drawer__hint">Delivery is calculated at checkout.</p>',
		wp_kses_post( $cart->get_cart_subtotal() )
	);
	printf(
		'<a class="dl-btn dl-btn--gold" href="%s">Checkout</a><a class="dl-btn dl-btn--outline" href="%s">View Cart</a>',
		esc_url( wc_get_checkout_url() ),
		esc_url( wc_get_cart_url() )
	);
	echo '</div>';
}

/**
 * The first category on a product that is a named brand, or the top-level category.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function dl_product_eyebrow( $product ) {
	$terms = get_the_terms( $product->get_id(), 'product_cat' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return '';
	}
	$watches = get_term_by( 'slug', 'watches', 'product_cat' );
	foreach ( $terms as $term ) {
		if ( $watches && (int) $term->parent === (int) $watches->term_id && ! preg_match( '/\b(men|women|ladies)\b/i', $term->name ) ) {
			return $term->name;
		}
	}
	foreach ( $terms as $term ) {
		if ( preg_match( '/tester|original|arabic/i', $term->name ) ) {
			return html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
		}
	}
	return '';
}

/**
 * Products from the other half of the shop, for building a gift set.
 *
 * @param WC_Product $product Product being viewed.
 * @return int[]
 */
function dl_pair_ids( $product ) {
	$is_watch = has_term( 'watches', 'product_cat', $product->get_id() );
	foreach ( get_the_terms( $product->get_id(), 'product_cat' ) ?: array() as $t ) {
		$anc = get_ancestors( $t->term_id, 'product_cat' );
		$w   = get_term_by( 'slug', 'watches', 'product_cat' );
		if ( $w && in_array( $w->term_id, $anc, true ) ) {
			$is_watch = true;
		}
	}
	return dl_top_seller_ids( 5, $is_watch ? 'perfumes' : 'watches' );
}
