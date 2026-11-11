<?php
/**
 * Catalog data helpers. Everything shown as a fact comes from real store data.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * Product IDs ranked by real orders over the last 12 months.
 *
 * @param int $limit How many IDs to return.
 * @return int[]
 */
function dl_top_seller_ids( $limit = 10, $only = '' ) {
	global $wpdb;
	$key = 'dl_top_' . $only;
	$ids = get_transient( $key );

	if ( false === $ids ) {
		$join = '';
		if ( $only ) {
			$term = get_term_by( 'slug', $only, 'product_cat' );
			if ( $term ) {
				$kids   = get_term_children( $term->term_id, 'product_cat' );
				$kids[] = $term->term_id;
				$kids   = implode( ',', array_map( 'intval', $kids ) );
				$join   = "JOIN {$wpdb->term_relationships} tr ON tr.object_id = l.product_id
					JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.term_id IN ({$kids})";
			}
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$ids = $wpdb->get_col(
			"SELECT l.product_id
			FROM {$wpdb->prefix}wc_order_product_lookup l
			JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id
			JOIN {$wpdb->posts} p ON p.ID = l.product_id AND p.post_status = 'publish'
			JOIN {$wpdb->postmeta} st ON st.post_id = l.product_id AND st.meta_key = '_stock_status' AND st.meta_value = 'instock'
			{$join}
			WHERE s.status IN ('wc-completed','wc-processing')
				AND s.date_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
			GROUP BY l.product_id
			ORDER BY COUNT(DISTINCT l.order_id) DESC, SUM(l.product_qty) DESC
			LIMIT 24"
		);
		$ids = array_map( 'intval', (array) $ids );
		set_transient( $key, $ids, 6 * HOUR_IN_SECONDS );
	}

	return array_slice( $ids, 0, $limit );
}

/**
 * Split "Tom Ford Oud Wood EDP – Cardamom, Oud" into name and descriptor.
 *
 * @param string $title Product title.
 * @return array{0:string,1:string}
 */
function dl_split_title( $title ) {
	$title = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
	$parts = preg_split( '/\s+[–—-]\s+/u', $title, 2 );
	return array( trim( $parts[0] ), isset( $parts[1] ) ? trim( $parts[1] ) : '' );
}

/**
 * Direct children of a category that actually have products.
 *
 * @param string $slug Parent category slug.
 * @return WP_Term[]
 */
function dl_children( $slug ) {
	$parent = get_term_by( 'slug', $slug, 'product_cat' );
	if ( ! $parent ) {
		return array();
	}
	$terms = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'parent'     => $parent->term_id,
			'hide_empty' => true,
			'orderby'    => 'count',
			'order'      => 'DESC',
		)
	);
	return is_wp_error( $terms ) ? array() : $terms;
}

/**
 * Watch brands: children of Watches, minus the gender-only groupings.
 *
 * @return WP_Term[]
 */
function dl_watch_brands() {
	return array_values(
		array_filter(
			dl_children( 'watches' ),
			static function ( $t ) {
				return ! preg_match( '/\b(men|women|ladies)\b/i', $t->name );
			}
		)
	);
}

/**
 * Category IDs grouped by recipient and product kind, read from category names.
 *
 * @return array<string,array<string,int[]>>
 */
function dl_finder_map() {
	$map = get_transient( 'dl_finder_map' );
	if ( false !== $map ) {
		return $map;
	}

	$map   = array(
		'him' => array( 'perfume' => array(), 'watch' => array() ),
		'her' => array( 'perfume' => array(), 'watch' => array() ),
	);
	$roots = array(
		'perfume' => get_term_by( 'slug', 'perfumes', 'product_cat' ),
		'watch'   => get_term_by( 'slug', 'watches', 'product_cat' ),
	);
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => true ) );

	foreach ( (array) $terms as $term ) {
		$who = '';
		if ( preg_match( '/\b(women|ladies|woman|lady)\b/i', $term->name ) ) {
			$who = 'her';
		} elseif ( preg_match( '/\b(men|gents)\b/i', $term->name ) ) {
			$who = 'him';
		}
		if ( ! $who ) {
			continue;
		}
		$ancestors = get_ancestors( $term->term_id, 'product_cat' );
		foreach ( $roots as $kind => $root ) {
			if ( $root && in_array( $root->term_id, $ancestors, true ) ) {
				$map[ $who ][ $kind ][] = (int) $term->term_id;
			}
		}
	}

	set_transient( 'dl_finder_map', $map, DAY_IN_SECONDS );
	return $map;
}

/**
 * WhatsApp link with a prefilled message.
 *
 * @param string $text Message.
 * @return string
 */
function dl_whatsapp_url( $text = '' ) {
	$url = 'https://wa.me/' . DL_WHATSAPP;
	return $text ? $url . '?text=' . rawurlencode( $text ) : $url;
}

/**
 * WhatsApp link ready for an href, keeping line breaks.
 * esc_url() removes encoded line breaks (%0A), which joins a structured message into one line,
 * so this link is built from fixed parts plus rawurlencode() and only attribute-escaped.
 *
 * @param string $text Message.
 * @return string Escaped for use in an href attribute.
 */
function dl_whatsapp_href( $text = '' ) {
	return esc_attr( dl_whatsapp_url( $text ) );
}

/**
 * A tidy WhatsApp message about a product: name in bold, price and link on their own lines.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function dl_whatsapp_product_text( $product ) {
	$name  = html_entity_decode( wp_strip_all_tags( $product->get_name() ), ENT_QUOTES, 'UTF-8' );
	$price = html_entity_decode( wp_strip_all_tags( wc_price( wc_get_price_to_display( $product ) ) ), ENT_QUOTES, 'UTF-8' );
	// The supplier reference has letters and a dash (e.g. "Item ref: ELI-34480"), so it never reads like a price.
	$ref = function_exists( 'dl_supplier_ref_code' ) ? dl_supplier_ref_code( $product )[0] : '';
	return "Hello Dlight Giftshop,\nI would like to order this item:\n\n*" . $name . "*\nPrice: " . $price . ( '' !== $ref ? "\nItem ref: " . $ref : '' ) . "\n" . $product->get_permalink() . "\n\nIs it available?";
}

/**
 * Products a shopper can actually see in a category. WooCommerce keeps this count
 * without hidden out-of-stock items, so it matches the category page.
 *
 * @param WP_Term $term Category.
 * @return int
 */
function dl_term_count( $term ) {
	$count = get_term_meta( $term->term_id, 'product_count_product_cat', true );
	return '' === $count ? (int) $term->count : (int) $count;
}

/**
 * Whether a product is a perfume. Perfume photos are full-bleed posters, not cut-outs.
 *
 * @param int $product_id Product ID.
 * @return bool
 */
function dl_is_perfume( $product_id ) {
	$root = get_term_by( 'slug', 'perfumes', 'product_cat' );
	if ( ! $root ) {
		return false;
	}
	foreach ( (array) get_the_terms( $product_id, 'product_cat' ) as $term ) {
		if ( $term && ( (int) $term->term_id === (int) $root->term_id || in_array( (int) $root->term_id, array_map( 'intval', get_ancestors( $term->term_id, 'product_cat' ) ), true ) ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Newest in-stock products from both halves of the shop, alternating.
 *
 * @param int $limit How many to return.
 * @return int[]
 */
function dl_new_ids( $limit = 8 ) {
	$lists = array();
	foreach ( array( 'perfumes', 'watches' ) as $slug ) {
		$lists[] = wc_get_products(
			array(
				'status'       => 'publish',
				'limit'        => $limit,
				'orderby'      => 'date',
				'order'        => 'DESC',
				'return'       => 'ids',
				'stock_status' => 'instock',
				'visibility'   => 'catalog',
				'category'     => array( $slug ),
			)
		);
	}
	$out = array();
	for ( $i = 0; $i < $limit; $i++ ) {
		foreach ( $lists as $list ) {
			if ( isset( $list[ $i ] ) && ! in_array( $list[ $i ], $out, true ) ) {
				$out[] = $list[ $i ];
			}
		}
	}
	return array_slice( $out, 0, $limit );
}

/**
 * A readable shop link such as /shop/?dl_for=her&dl_kind=perfume.
 *
 * @param string $for  her or him.
 * @param string $kind perfume or watch.
 * @return string
 */
function dl_shop_url( $for, $kind ) {
	return add_query_arg( array( 'dl_for' => $for, 'dl_kind' => $kind ), wc_get_page_permalink( 'shop' ) );
}

/**
 * Page title when the shop shows exactly one gender and type, such as "Women's Perfumes".
 *
 * @return string Empty for any other view.
 */
function dl_landing_title() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	if ( ! function_exists( 'is_shop' ) || ! is_shop() || is_search() || empty( $_GET['dl_for'] ) || empty( $_GET['dl_kind'] ) || ! empty( $_GET['min_price'] ) || ! empty( $_GET['max_price'] ) || ! empty( $_GET['orderby'] ) ) {
		return '';
	}
	$for  = array( 'her' => "Women's", 'him' => "Men's" )[ sanitize_key( $_GET['dl_for'] ) ] ?? '';
	$kind = array( 'perfume' => 'Perfumes', 'watch' => 'Watches' )[ sanitize_key( $_GET['dl_kind'] ) ] ?? '';
	// phpcs:enable
	return $for && $kind ? $for . ' ' . $kind : '';
}

/**
 * Link to a product category by slug.
 *
 * @param string $slug Category slug.
 * @return string
 */
function dl_cat_url( $slug ) {
	$link = get_term_link( $slug, 'product_cat' );
	return is_wp_error( $link ) ? wc_get_page_permalink( 'shop' ) : $link;
}

/**
 * The real logo file.
 *
 * @return string
 */
function dl_logo_url() {
	return DL_URI . '/assets/img/logo-dg.png';
}

/**
 * In-stock products within a price band, newest first, alternating perfumes and watches.
 * Used by the home page "Just in / Under KSh 5,000 / Luxury picks" tabs. Cached for 30 minutes.
 *
 * @param int      $limit How many.
 * @param int      $min   Lowest price (inclusive).
 * @param int|null $max   Highest price (inclusive), or null for no ceiling.
 * @return int[]
 */
function dl_band_ids( $limit, $min, $max = null ) {
	global $wpdb;
	$key = 'dl_band_' . $limit . '_' . $min . '_' . ( null === $max ? 'x' : $max );
	$ids = get_transient( $key );
	if ( false !== $ids ) {
		return $ids;
	}
	$lists = array();
	foreach ( array( 'perfumes', 'watches' ) as $slug ) {
		$term = get_term_by( 'slug', $slug, 'product_cat' );
		if ( ! $term ) {
			continue;
		}
		$cats    = array_map( 'intval', array_merge( array( $term->term_id ), get_term_children( $term->term_id, 'product_cat' ) ) );
		$ceiling = null === $max ? '' : $wpdb->prepare( ' AND l.min_price <= %d', $max );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$lists[] = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID FROM {$wpdb->posts} p
				JOIN {$wpdb->wc_product_meta_lookup} l ON l.product_id = p.ID
				JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
				JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'product_cat'
				WHERE p.post_type = 'product' AND p.post_status = 'publish' AND l.stock_status = 'instock'
					AND l.min_price >= %d {$ceiling} AND tt.term_id IN (" . implode( ',', $cats ) . ')
				ORDER BY p.post_date DESC, l.min_price ASC LIMIT %d',
				$min,
				$limit * 2
			)
		);
	}
	$out = array();
	for ( $i = 0; count( $out ) < $limit && $i < $limit * 2; $i++ ) {
		foreach ( $lists as $list ) {
			if ( isset( $list[ $i ] ) && ! in_array( (int) $list[ $i ], $out, true ) && count( $out ) < $limit ) {
				$product = wc_get_product( $list[ $i ] );
				if ( $product && $product->is_visible() ) {
					$out[] = (int) $list[ $i ];
				}
			}
		}
	}
	set_transient( $key, $out, 30 * MINUTE_IN_SECONDS );
	return $out;
}
