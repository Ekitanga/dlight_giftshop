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

/*
 * Tell Meta whenever a product's stock status changes.
 * The Facebook plugin only listens for stock *quantity* changes and for edits made in the product
 * editor. Our products are marked in stock / out of stock without counting quantities, so a status
 * change made any other way (quick tools, imports, supplier stock sync) never reached the Meta
 * catalogue, and ads kept showing sold-out watches as available. Queue those products for the
 * plugin's own sync; it sends everything queued in one background job when the request ends.
 */
function dl_fb_queue_stock_sync( $product_id ) {
	static $queued = array();
	$product_id = (int) $product_id;
	if ( ! $product_id || isset( $queued[ $product_id ] ) || ! function_exists( 'facebook_for_woocommerce' ) ) {
		return;
	}
	$queued[ $product_id ] = true;
	try {
		facebook_for_woocommerce()->get_products_sync_handler()->create_or_update_products( array( $product_id ) );
	} catch ( \Throwable $e ) {
		// A Meta connection problem must never stop a stock change or an order.
		error_log( 'Dlight: could not queue Meta stock sync for product ' . $product_id . ': ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
}
add_action( 'woocommerce_product_set_stock_status', 'dl_fb_queue_stock_sync', 20 );
add_action( 'woocommerce_variation_set_stock_status', 'dl_fb_queue_stock_sync', 20 );

/*
 * Run the Facebook plugin's waiting product-sync jobs on our regular cron.
 * The plugin normally starts its background worker with a loopback request to this site, which
 * the host rate-limits (HTTP 429), so jobs could sit in the queue for hours. Its cron health check
 * fires on our 5-minute server cron; here we process the queue directly with the plugin's own
 * public process_job() before that check runs. The plugin's own lock prevents double work.
 */
function dl_fb_run_sync_jobs() {
	if ( ! function_exists( 'facebook_for_woocommerce' ) || get_transient( 'wc_facebook_background_product_sync_process_lock' ) || get_transient( 'dl_fb_jobs_running' ) ) {
		return;
	}
	set_transient( 'dl_fb_jobs_running', 1, 4 * MINUTE_IN_SECONDS );
	try {
		$handler = facebook_for_woocommerce()->get_products_sync_background_handler();
		foreach ( (array) $handler->get_jobs( array( 'status' => array( 'queued', 'processing' ) ) ) as $job ) {
			$handler->process_job( $job );
		}
	} catch ( \Throwable $e ) {
		error_log( 'Dlight: Meta product sync job failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
	}
	delete_transient( 'dl_fb_jobs_running' );
}
add_action( 'wc_facebook_background_product_sync_cron', 'dl_fb_run_sync_jobs', 5 );

/*
 * Send visitors from a removed (duplicate) product to the copy we kept.
 * The map lives in the 'dl_product_redirects' option: old URL path => product ID kept.
 * It only acts on pages that would otherwise be "not found", so it never overrides a live page.
 */
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() ) {
			return;
		}
		$map = get_option( 'dl_product_redirects', array() );
		if ( ! $map ) {
			return;
		}
		$path = trailingslashit( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( isset( $map[ $path ] ) && 'publish' === get_post_status( (int) $map[ $path ] ) ) {
			wp_safe_redirect( get_permalink( (int) $map[ $path ] ), 301 );
			exit;
		}
	}
);

/*
 * Default order for every category, brand page and product search:
 * in-stock first, then the newest arrivals first, and within each day's arrivals the cheapest first.
 * A customer can still choose another order from the "Sort by" menu.
 */
function dl_is_default_sort( $orderby ) {
	return in_array( (string) $orderby, array( '', 'menu_order', 'relevance', 'dl_fresh' ), true );
}
add_filter(
	'woocommerce_get_catalog_ordering_args',
	static function ( $args, $orderby ) {
		if ( dl_is_default_sort( $orderby ) ) {
			$args['orderby']  = 'date';
			$args['order']    = 'DESC';
			$args['meta_key'] = '';
			add_filter( 'posts_clauses', 'dl_fresh_clauses', 20, 2 );
		}
		return $args;
	},
	20,
	2
);
add_filter( 'woocommerce_default_catalog_orderby', static fn() => 'dl_fresh' );

/**
 * SQL for the default order. Runs once, on the main product query only.
 *
 * @param array    $clauses Query clauses.
 * @param WP_Query $query   Query.
 * @return array
 */
function dl_fresh_clauses( $clauses, $query ) {
	global $wpdb;
	if ( ! $query->is_main_query() ) {
		return $clauses;
	}
	remove_filter( 'posts_clauses', 'dl_fresh_clauses', 20 );
	if ( false === strpos( $clauses['join'], 'dl_fresh_l' ) ) {
		$clauses['join'] .= " LEFT JOIN {$wpdb->wc_product_meta_lookup} dl_fresh_l ON {$wpdb->posts}.ID = dl_fresh_l.product_id ";
	}
	$clauses['orderby'] = "(dl_fresh_l.stock_status = 'instock') DESC, DATE({$wpdb->posts}.post_date) DESC, dl_fresh_l.min_price ASC, {$wpdb->posts}.ID DESC";
	return $clauses;
}

/*
 * Supplier: a private product field for staff (replaces the public supplier tags such as "ET").
 * Not visible to customers or search engines; shown as a column, filter and Quick Edit box in Products.
 */
add_action(
	'init',
	static function () {
		register_taxonomy(
			'dl_supplier',
			'product',
			array(
				'labels'             => array(
					'name'          => 'Suppliers',
					'singular_name' => 'Supplier',
					'menu_name'     => 'Suppliers',
					'all_items'     => 'All suppliers',
					'edit_item'     => 'Edit supplier',
					'add_new_item'  => 'Add new supplier',
					'search_items'  => 'Search suppliers',
				),
				'public'             => false,
				'publicly_queryable' => false,
				'show_ui'            => true,
				'show_in_menu'       => true,
				'show_in_nav_menus'  => false,
				'show_tagcloud'      => false,
				'show_in_rest'       => false,
				'show_admin_column'  => true,
				'show_in_quick_edit' => true,
				'hierarchical'       => true, // tick boxes instead of free typing, so no spelling variants
				'rewrite'            => false,
				'query_var'          => false,
				'capabilities'       => array(
					'manage_terms' => 'manage_product_terms',
					'edit_terms'   => 'edit_product_terms',
					'delete_terms' => 'delete_product_terms',
					'assign_terms' => 'edit_products',
				),
			)
		);
	}
);

// Filter the Products list by supplier.
add_action(
	'restrict_manage_posts',
	static function ( $post_type ) {
		if ( 'product' !== $post_type ) {
			return;
		}
		wp_dropdown_categories(
			array(
				'taxonomy'        => 'dl_supplier',
				'name'            => 'dl_supplier_filter',
				'show_option_all' => 'All suppliers',
				'value_field'     => 'term_id',
				'selected'        => isset( $_GET['dl_supplier_filter'] ) ? absint( $_GET['dl_supplier_filter'] ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'hide_empty'      => false,
				'hierarchical'    => true,
			)
		);
	}
);
add_action(
	'pre_get_posts',
	static function ( $query ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) || empty( $_GET['dl_supplier_filter'] ) ) {
			return;
		}
		$tax   = (array) $query->get( 'tax_query' );
		$tax[] = array(
			'taxonomy' => 'dl_supplier',
			'terms'    => absint( $_GET['dl_supplier_filter'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		);
		$query->set( 'tax_query', $tax );
	}
);

/*
 * Stock change trail for the admin: remember who last changed a product's stock status and when,
 * so the owner can trace updates made by the shop manager. Shown in a "Last updated" column.
 */
add_action(
	'woocommerce_product_set_stock_status',
	static function ( $product_id, $status ) {
		$user = wp_get_current_user();
		update_post_meta( (int) $product_id, '_dl_stock_changed', time() );
		update_post_meta( (int) $product_id, '_dl_stock_changed_by', $user && $user->exists() ? $user->display_name : 'Automatic (order or supplier sync)' );
		update_post_meta( (int) $product_id, '_dl_stock_changed_to', (string) $status );
	},
	10,
	2
);

// Products list: "Last updated" column (date, and who made the last stock change or edit).
add_filter(
	'manage_edit-product_columns',
	static function ( $cols ) {
		$cols['dl_updated'] = 'Last updated';
		return $cols;
	},
	30
);
add_filter(
	'manage_edit-product_sortable_columns',
	static function ( $cols ) {
		$cols['dl_updated'] = 'modified';
		return $cols;
	}
);
add_action(
	'manage_product_posts_custom_column',
	static function ( $col, $post_id ) {
		if ( 'dl_updated' !== $col ) {
			return;
		}
		$when = get_post_modified_time( 'U', true, $post_id ); // GMT timestamp; wp_date() shows it in Nairobi time
		echo esc_html( wp_date( 'j M Y, H:i', $when ) );
		$by     = get_post_meta( $post_id, '_dl_stock_changed_by', true );
		$at     = (int) get_post_meta( $post_id, '_dl_stock_changed', true );
		$to     = get_post_meta( $post_id, '_dl_stock_changed_to', true );
		if ( $by && $at ) {
			printf( '<small>Stock set to %s by <strong>%s</strong>, %s</small>', esc_html( 'outofstock' === $to ? 'out of stock' : ( 'instock' === $to ? 'in stock' : $to ) ), esc_html( $by ), esc_html( wp_date( 'j M Y, H:i', $at ) ) );
		} else {
			$editor = (int) get_post_meta( $post_id, '_edit_last', true );
			$u      = $editor ? get_userdata( $editor ) : false;
			if ( $u ) {
				printf( '<small>Last edited by <strong>%s</strong></small>', esc_html( $u->display_name ) );
			}
		}
	},
	10,
	2
);

// Products list: give every column a sensible width so the extra columns don't get squeezed to one letter.
add_action(
	'admin_head-edit.php',
	static function () {
		if ( 'product' !== ( $GLOBALS['typenow'] ?? '' ) ) {
			return;
		}
		echo '<style>
		body.post-type-product table.wp-list-table { table-layout: fixed; width: 100%; }
		body.post-type-product table.wp-list-table th.column-cb, body.post-type-product table.wp-list-table td.column-cb { width: 2.2em !important; }
		body.post-type-product table.wp-list-table th.column-thumb, body.post-type-product table.wp-list-table td.column-thumb { width: 52px !important; }
		body.post-type-product table.wp-list-table th.column-name, body.post-type-product table.wp-list-table td.column-name { width: 15% !important; }
		body.post-type-product table.wp-list-table th.column-sku, body.post-type-product table.wp-list-table td.column-sku { width: 6% !important; }
		body.post-type-product table.wp-list-table th.column-is_in_stock, body.post-type-product table.wp-list-table td.column-is_in_stock { width: 7% !important; }
		body.post-type-product table.wp-list-table th.column-price, body.post-type-product table.wp-list-table td.column-price { width: 8% !important; }
		body.post-type-product table.wp-list-table th.column-product_cat, body.post-type-product table.wp-list-table td.column-product_cat { width: 11% !important; }
		body.post-type-product table.wp-list-table th.column-product_tag, body.post-type-product table.wp-list-table td.column-product_tag { width: 5% !important; }
		body.post-type-product table.wp-list-table th.column-featured, body.post-type-product table.wp-list-table td.column-featured { width: 32px !important; }
		body.post-type-product table.wp-list-table th.column-date, body.post-type-product table.wp-list-table td.column-date { width: 9% !important; }
		body.post-type-product table.wp-list-table th.column-taxonomy-product_brand, body.post-type-product table.wp-list-table td.column-taxonomy-product_brand { width: 7% !important; }
		body.post-type-product table.wp-list-table th.column-taxonomy-dl_supplier, body.post-type-product table.wp-list-table td.column-taxonomy-dl_supplier { width: 8% !important; }
		body.post-type-product table.wp-list-table th.column-facebook_sync, body.post-type-product table.wp-list-table td.column-facebook_sync { width: 7% !important; }
		body.post-type-product table.wp-list-table th.column-dl_updated, body.post-type-product table.wp-list-table td.column-dl_updated { width: 11% !important; }
		body.post-type-product table.wp-list-table th, body.post-type-product table.wp-list-table td { overflow-wrap: break-word; word-break: normal; }
		body.post-type-product table.wp-list-table th.column-taxonomy-dl_supplier, body.post-type-product table.wp-list-table th.column-facebook_sync, body.post-type-product table.wp-list-table th.column-dl_updated { white-space: normal; }
		body.post-type-product table.wp-list-table td.column-dl_updated small { display: block; margin-top: 3px; line-height: 1.35; color: #646970; }
		</style>';
	}
);

// Out of stock filter: most recently updated first (unless another sort is chosen).
add_action(
	'pre_get_posts',
	static function ( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'product' !== $query->get( 'post_type' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['stock_status'] ) && 'outofstock' === $_GET['stock_status'] && empty( $_GET['orderby'] ) ) {
			$query->set( 'orderby', 'modified' );
			$query->set( 'order', 'DESC' );
		}
		// phpcs:enable
	}
);

/*
 * Unpaid orders: never auto-cancel. WooCommerce cancels checkout orders that stay
 * unpaid past "Hold stock (minutes)"; the owner wants them kept as Pending payment
 * (the stock hold still expires, so the item is not blocked for other buyers).
 */
add_filter( 'woocommerce_cancel_unpaid_order', '__return_false', 99 );

/*
 * Supplier reference on the product page.
 * Visitors (incl. the owner when not logged in, e.g. a link opened from WhatsApp) see a
 * neutral code such as "Ref: ELI-34571". Logged-in staff also see the supplier's full name.
 * Codes are set in dl_supplier_code().
 */
function dl_supplier_code( $name ) {
	// Owner's codes. A supplier not listed here gets the first 3 letters of its name.
	$codes = array(
		'Elite Tech'    => 'ET',
		'Brianna'       => 'BRI',
		'Prompt Trader' => 'PROMP',
		'Pearl RNG'     => 'PEA',
		'Navispot'      => 'NAV',
		'Essential'     => 'ESS',
		'Instyle'       => 'INST',
		'Mustafa'       => 'MUS',
		'Planet'        => 'PLAN',
	);
	return $codes[ $name ] ?? strtoupper( substr( preg_replace( '/[^A-Za-z]/', '', $name ), 0, 3 ) );
}

/**
 * A product's supplier reference, e.g. "ELI-34480", plus the supplier names.
 *
 * @param WC_Product $product Product.
 * @return array{0:string,1:string[]} Reference ('' when the product has no supplier) and names.
 */
function dl_supplier_ref_code( $product ) {
	if ( ! taxonomy_exists( 'dl_supplier' ) ) {
		return array( '', array() );
	}
	$terms = get_the_terms( $product->get_id(), 'dl_supplier' );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return array( '', array() );
	}
	$codes = array();
	$names = array();
	foreach ( $terms as $term ) {
		$names[] = $term->name;
		$codes[] = dl_supplier_code( $term->name );
	}
	return array( implode( '/', $codes ) . '-' . $product->get_id(), $names );
}

/**
 * Print the supplier reference line for a product (nothing if it has no supplier).
 *
 * @param WC_Product $product Product.
 */
function dl_supplier_ref( $product ) {
	list( $ref, $names ) = dl_supplier_ref_code( $product );
	if ( '' === $ref ) {
		return;
	}
	echo '<p class="dl-ref">Ref: ' . esc_html( $ref );
	if ( current_user_can( 'edit_products' ) ) {
		echo ' <span class="dl-ref__staff">&middot; Supplier: <strong>' . esc_html( implode( ', ', $names ) ) . '</strong> (only you can see this)</span>';
	}
	echo '</p>';
}

// Staff see the full supplier name, so never let a staff view of a product page be cached.
add_action(
	'template_redirect',
	static function () {
		if ( is_product() && current_user_can( 'edit_products' ) ) {
			nocache_headers();
		}
	}
);

/**
 * Homepage rotation: the grid before this script holds a larger pool of products (cached page, so
 * it stays fast); each visitor's browser shuffles it and CSS shows only the first 10. Grids marked
 * data-mix keep perfumes and watches alternating. Runs inline, before the lazy images load.
 */
function dl_rotate_script() {
	echo '<script>(function(g){if(!g)return;var c=[].slice.call(g.children),sh=function(a){for(var i=a.length-1;i>0;i--){var j=Math.random()*(i+1)|0,t=a[i];a[i]=a[j];a[j]=t;}return a;};'
		. 'if(g.hasAttribute("data-mix")){var p=sh(c.filter(function(x){return x.classList.contains("dl-card--poster");})),w=sh(c.filter(function(x){return !x.classList.contains("dl-card--poster");})),o=[];while(p.length||w.length){if(p.length)o.push(p.shift());if(w.length)o.push(w.shift());}c=o;}else{c=sh(c);}'
		. 'c.forEach(function(x){g.appendChild(x);});})(document.currentScript.previousElementSibling);</script>'; // phpcs:ignore WordPress.Security.EscapeOutput
}
