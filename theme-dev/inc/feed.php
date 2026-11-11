<?php
/**
 * Google Merchant Center product feed: https://dlightgiftshop.com/google-feed.xml
 * Every published, visible product with its price, sale price, stock, photos and brand.
 * Merchant Center fetches it on a schedule, so stock and price changes reach Google automatically.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

const DL_FEED_PATH = '/google-feed.xml';

/**
 * Brand of a product: the watch brand category, otherwise the brand at the start of the title.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function dl_feed_brand( $product ) {
	$parent  = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	$watches = get_term_by( 'slug', 'watches', 'product_cat' );
	$terms   = get_the_terms( $parent->get_id(), 'product_cat' );
	if ( $watches && $terms && ! is_wp_error( $terms ) ) {
		foreach ( $terms as $t ) {
			if ( (int) $t->parent === (int) $watches->term_id && ! preg_match( '/\b(men|women|ladies)\b/i', $t->name ) ) {
				return html_entity_decode( $t->name, ENT_QUOTES, 'UTF-8' );
			}
		}
	}
	$title = html_entity_decode( wp_strip_all_tags( $parent->get_name() ), ENT_QUOTES, 'UTF-8' );
	$known = array(
		'Parfums de Marly', 'Maison Francis Kurkdjian', 'MFK' => 'Maison Francis Kurkdjian', 'Yves Saint Laurent', 'YSL' => 'Yves Saint Laurent', 'Tom Ford',
		'Carolina Herrera', 'Jimmy Choo', 'Giorgio Armani', 'Emporio Armani', 'Dolce & Gabbana', 'Dolce Gabbana' => 'Dolce & Gabbana', 'D&G' => 'Dolce & Gabbana',
		'Paco Rabanne', 'Hugo Boss', 'Jean Paul Gaultier', 'Victoria\'s Secret', 'Calvin Klein', 'Narciso Rodriguez', 'Marc Jacobs', 'Issey Miyake',
		'Penhaligon\'s', 'Al Haramain', 'Fragrance World', 'Paris Corner', 'Maison Alhambra', 'Maison Asrar', 'Maison Crivelli', 'Maison Margiela', 'French Avenue',
		'Michael Kors', 'Anna Sui', 'Elie Saab', 'Viktor & Rolf', 'Roberto Cavalli', 'Tiziana Terenzi', 'Alfred Dunhill' => 'Dunhill', 'Ralph Lauren', 'Le Labo',
		'By Kilian' => 'Kilian', 'Ex Nihilo', 'Montblanc', 'Mont Blanc' => 'Montblanc', 'Lancôme', 'Lancome' => 'Lancôme', 'Hermès', 'Hermes' => 'Hermès',
		'Chanel', 'Dior', 'Gucci', 'Prada', 'Versace', 'Valentino', 'Creed', 'Givenchy', 'Burberry', 'Bvlgari', 'Azzaro', 'Guerlain', 'Kenzo', 'Lacoste',
		'Dunhill', 'Mancera', 'Montale', 'Davidoff', 'Kayali', 'Lattafa', 'Armaf', 'Afnan', 'Rasasi', 'Ajmal', 'Xerjoff', 'Nishane', 'Initio', 'Byredo',
		'Kilian', 'Mugler', 'Atkinsons', 'Amouage', 'Ard Al Zaafaran', 'Ahmed Al Maghribi', 'Niche Emarati', 'Lalique', 'Boss' => 'Hugo Boss', 'Hugo' => 'Hugo Boss', 'Rabanne' => 'Paco Rabanne', 'Armani' => 'Giorgio Armani',
	);
	$map = array();
	foreach ( $known as $k => $v ) {
		$map[ is_int( $k ) ? $v : $k ] = $v;
	}
	// 1) Title starts with a brand; 2) "... by Brand"; 3) the brand appears anywhere in the title (e.g. "Bleu de Chanel").
	foreach ( $map as $name => $brand ) {
		if ( preg_match( '/^' . preg_quote( $name, '/' ) . '\b/iu', $title ) ) {
			return $brand;
		}
	}
	foreach ( $map as $name => $brand ) {
		if ( preg_match( '/\bby\s+' . preg_quote( $name, '/' ) . '\b/iu', $title ) ) {
			return $brand;
		}
	}
	$best = null;
	foreach ( $map as $name => $brand ) {
		if ( preg_match( '/\b' . preg_quote( $name, '/' ) . '\b/iu', $title, $m, PREG_OFFSET_CAPTURE ) && ( null === $best || $m[0][1] < $best[0] ) ) {
			$best = array( $m[0][1], $brand );
		}
	}
	if ( $best ) {
		return $best[1];
	}
	$first = strtok( $title, ' ' );
	return $first ? $first : 'Dlight Giftshop';
}

/**
 * One <item> for the feed.
 *
 * @param WC_Product $p Product (simple or variation).
 * @return string
 */
function dl_feed_item( $p ) {
	$parent  = $p->get_parent_id() ? wc_get_product( $p->get_parent_id() ) : $p;
	$regular = (float) $p->get_regular_price();
	$sale    = (float) $p->get_sale_price();
	$price   = $regular > 0 ? $regular : (float) $p->get_price();
	if ( $price <= 0 ) {
		return '';
	}
	$img = dl_feed_img_url( (int) ( $p->get_image_id() ? $p->get_image_id() : $parent->get_image_id() ) );
	if ( ! $img ) {
		return '';
	}
	$x = static fn( $s ) => htmlspecialchars( (string) $s, ENT_XML1 | ENT_QUOTES, 'UTF-8' );
	// Plain-text description: the short description, else the long one.
	$desc = $parent->get_short_description() ? $parent->get_short_description() : $parent->get_description();
	$desc = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( str_replace( array( '</li>', '</p>', '<br>' ), array( '. ', ' ', ' ' ), $desc ) ), ENT_QUOTES, 'UTF-8' ) ) );
	$desc = $desc ? mb_substr( $desc, 0, 4900 ) : html_entity_decode( $parent->get_name(), ENT_QUOTES, 'UTF-8' );
	$cats = wc_get_product_terms( $parent->get_id(), 'product_cat', array( 'orderby' => 'parent' ) );
	$path = array();
	$type = '';
	foreach ( $cats as $c ) {
		$anc  = array_reverse( get_ancestors( $c->term_id, 'product_cat' ) );
		$trail = array_map( static fn( $id ) => html_entity_decode( get_term( $id )->name, ENT_QUOTES, 'UTF-8' ), $anc );
		$trail[] = html_entity_decode( $c->name, ENT_QUOTES, 'UTF-8' );
		if ( count( $trail ) > count( $path ) ) {
			$path = $trail;
		}
	}
	$type   = implode( ' > ', $path );
	$is_w   = false !== stripos( $type, 'watch' );
	$is_p   = false !== stripos( $type, 'perfum' );
	$gcat   = $is_w ? '201' : ( $is_p ? '479' : '' ); // Google: Watches / Perfume & Cologne.
	$o  = "<item>\n";
	$o .= '<g:id>' . $p->get_id() . "</g:id>\n";
	if ( $p->get_parent_id() ) {
		$o .= '<g:item_group_id>' . $p->get_parent_id() . "</g:item_group_id>\n";
	}
	$o .= '<g:title>' . $x( mb_substr( html_entity_decode( wp_strip_all_tags( $p->get_name() ), ENT_QUOTES, 'UTF-8' ), 0, 150 ) ) . "</g:title>\n";
	$o .= '<g:description>' . $x( $desc ) . "</g:description>\n";
	$o .= '<g:link>' . $x( $p->get_permalink() ) . "</g:link>\n";
	$o .= '<g:image_link>' . $x( $img ) . "</g:image_link>\n";
	foreach ( array_slice( $parent->get_gallery_image_ids(), 0, 10 ) as $gid ) {
		$u = dl_feed_img_url( (int) $gid );
		if ( $u ) {
			$o .= '<g:additional_image_link>' . $x( $u ) . "</g:additional_image_link>\n";
		}
	}
	$o .= '<g:availability>' . ( $p->is_in_stock() ? 'in_stock' : 'out_of_stock' ) . "</g:availability>\n";
	$o .= '<g:price>' . number_format( $price, 2, '.', '' ) . " KES</g:price>\n";
	if ( $sale > 0 && $sale < $price ) {
		$o .= '<g:sale_price>' . number_format( $sale, 2, '.', '' ) . " KES</g:sale_price>\n";
	}
	$o .= '<g:brand>' . $x( dl_feed_brand( $p ) ) . "</g:brand>\n";
	$mpn = dl_product_model( $p );
	if ( $mpn ) {
		$o .= '<g:mpn>' . $x( $mpn ) . "</g:mpn>\n";
	}
	$o .= "<g:condition>new</g:condition>\n";
	if ( $gcat ) {
		$o .= '<g:google_product_category>' . $gcat . "</g:google_product_category>\n";
	}
	if ( $type ) {
		$o .= '<g:product_type>' . $x( $type ) . "</g:product_type>\n";
	}
	$who = strtolower( $type . ' ' . $p->get_name() );
	if ( preg_match( '/\b(women|womens|women\'s|ladies|for her)\b/', $who ) && ! preg_match( '/\b(men|mens|men\'s|unisex)\b/', $who ) ) {
		$o .= "<g:gender>female</g:gender>\n";
	} elseif ( preg_match( '/\b(men|mens|men\'s)\b/', $who ) && ! preg_match( '/\b(women|ladies|unisex)\b/', $who ) ) {
		$o .= "<g:gender>male</g:gender>\n";
	} elseif ( false !== strpos( $who, 'unisex' ) ) {
		$o .= "<g:gender>unisex</g:gender>\n";
	}
	if ( $is_w || $is_p ) {
		$o .= "<g:age_group>adult</g:age_group>\n";
	}
	foreach ( dl_feed_labels( $p, $parent, $type, $is_w, $is_p, $sale > 0 && $sale < $price ? $sale : $price ) as $n => $label ) {
		$o .= '<g:custom_label_' . $n . '>' . $x( $label ) . '</g:custom_label_' . $n . ">\n";
	}
	return $o . "</item>\n";
}

/**
 * Google Ads product groups (custom labels), so campaigns can bid by group:
 *   0 price band (what the customer pays), 1 product group, 2 supplier code,
 *   3 bestseller / standard (top 24 sellers of the last 12 months), 4 designer-tester / standard
 *   (designer testers are kept apart: Google is strictest with luxury brands from resellers).
 *
 * @param WC_Product $p      Product.
 * @param WC_Product $parent Parent product.
 * @param string     $type   Category path.
 * @param bool       $is_w   Watch.
 * @param bool       $is_p   Perfume.
 * @param float      $pays   Price the customer pays.
 * @return string[]
 */
function dl_feed_labels( $p, $parent, $type, $is_w, $is_p, $pays ) {
	static $best = null;
	if ( null === $best ) {
		$best = function_exists( 'dl_top_seller_ids' ) ? array_flip( array_map( 'intval', (array) dl_top_seller_ids( 24 ) ) ) : array();
	}
	$band = $pays < 3000 ? 'under-3k' : ( $pays < 7000 ? '3k-7k' : ( $pays < 15000 ? '7k-15k' : ( $pays < 30000 ? '15k-30k' : '30k-plus' ) ) );
	$t    = strtolower( $type );
	if ( $is_p ) {
		$group = false !== strpos( $t, 'tester' ) ? 'tester-perfume' : ( false !== strpos( $t, 'arabic' ) ? 'arabic-perfume' : 'other-perfume' );
	} else {
		$group = $is_w ? 'watch' : 'other';
	}
	$sup = '';
	if ( function_exists( 'dl_supplier_ref_code' ) ) {
		$sup = (string) strtok( (string) dl_supplier_ref_code( $parent )[0], '-' );
	}
	return array(
		0 => $band,
		1 => $group,
		2 => $sup ? strtolower( $sup ) : 'none',
		3 => isset( $best[ $parent->get_id() ] ) ? 'bestseller' : 'standard',
		4 => 'tester-perfume' === $group ? 'designer-tester' : 'standard',
	);
}

/**
 * Build the whole feed (cached for an hour; cleared when a product changes).
 *
 * @return string
 */
function dl_feed_xml() {
	$xml = get_transient( 'dl_google_feed' );
	if ( false !== $xml ) {
		return $xml;
	}
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$ids   = wc_get_products( array( 'status' => 'publish', 'visibility' => 'catalog', 'limit' => -1, 'return' => 'ids' ) );
	$items = '';
	foreach ( $ids as $id ) {
		$p = wc_get_product( $id );
		if ( ! $p ) {
			continue;
		}
		if ( $p->is_type( 'variable' ) ) {
			foreach ( $p->get_children() as $cid ) {
				$v = wc_get_product( $cid );
				if ( $v && $v->is_purchasable() ) {
					$items .= dl_feed_item( $v );
				}
			}
		} elseif ( $p->is_type( array( 'simple', 'external' ) ) ) {
			$items .= dl_feed_item( $p );
		}
	}
	$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
	$xml .= '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"><channel>' . "\n";
	$xml .= "<title>Dlight Giftshop</title>\n<link>" . esc_url( home_url( '/' ) ) . "</link>\n<description>Perfumes and watches in Kenya</description>\n";
	$xml .= $items . "</channel></rss>\n";
	set_transient( 'dl_google_feed', $xml, HOUR_IN_SECONDS );
	return $xml;
}

// Serve the feed at /google-feed.xml.
add_action(
	'parse_request',
	static function () {
		$path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( DL_FEED_PATH !== $path || ! function_exists( 'wc_get_products' ) ) {
			return;
		}
		$xml = dl_feed_xml();
		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'Cache-Control: public, max-age=1800' );
		header( 'X-Robots-Tag: noindex' );
		echo $xml; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped above.
		exit;
	},
	1
);

// Any product change (stock, price, title, photos) refreshes the feed on the next fetch.
foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_product_set_stock_status', 'woocommerce_variation_set_stock_status', 'woocommerce_update_product_variation', 'trashed_post', 'deleted_post' ) as $dl_hook ) {
	add_action( $dl_hook, static fn() => delete_transient( 'dl_google_feed' ) );
}
unset( $dl_hook );

/**
 * Watch model number from the title (e.g. GA-2100-1A, FS5711, MK6694, SRPD65K1, NF9208, Curren 8471).
 *
 * @param WC_Product $product Product.
 * @return string '' when none is found or the product is not a watch.
 */
function dl_product_model( $product ) {
	$parent = $product->get_parent_id() ? wc_get_product( $product->get_parent_id() ) : $product;
	if ( ! has_term( 'watches', 'product_cat', $parent->get_id() ) && ! array_intersect( wp_get_post_terms( $parent->get_id(), 'product_cat', array( 'fields' => 'slugs' ) ), array( 'watches' ) ) ) {
		$anc = array();
		foreach ( wp_get_post_terms( $parent->get_id(), 'product_cat', array( 'fields' => 'ids' ) ) as $tid ) {
			$anc = array_merge( $anc, get_ancestors( $tid, 'product_cat' ) );
		}
		$w = get_term_by( 'slug', 'watches', 'product_cat' );
		if ( ! $w || ! in_array( (int) $w->term_id, array_map( 'intval', $anc ), true ) ) {
			return '';
		}
	}
	$t = strtoupper( html_entity_decode( wp_strip_all_tags( $parent->get_name() ), ENT_QUOTES, 'UTF-8' ) );
	$t = preg_replace( '/[\x{2010}-\x{2015}\x{2212}]/u', '-', $t );
	$patterns = array(
		'/\b(S[A-Z]{2,4}\d{2,3}[A-Z]\d)\b/', // Seiko full code: SNXS77K1, SRPD65K4
		'/\b([A-Z]{1,5}-?[A-Z]?\d{2,5}[A-Z]{0,4}(?:-\d?[A-Z0-9]{1,6})+)\b/', // Casio style: GA-2100-1A, MTP-V006D-1C
		'/\b((?:FS|ES|ME|BQ|CE|JR|BQ|MK|DZ|NF|SKX|SN[A-Z]{1,3}|SR[A-Z]{1,3}|SS[A-Z]|SY[A-Z]{1,2}|SU[A-Z]{1,2}|SP[A-Z]?)\d{3,4}[A-Z]{0,2}\d?)\b/', // Fossil, MK, Seiko, Naviforce
		'/\b(?:CURREN|POEDAGAR|NAVIFORCE|MEGIR|SKMEI|MINIFOCUS|HANNAH MARTIN)\s+(?:NF)?(\d{3,5}[A-Z]?)\b/', // brand + number
	);
	foreach ( $patterns as $p ) {
		if ( preg_match( $p, $t, $m ) ) {
			return $m[1];
		}
	}
	return '';
}

/*
 * Google Merchant images: a light JPEG copy (max 1200 px, quality 85) of every product photo,
 * in uploads/dl-feed/. Large PNGs (1.8-2 MB) were timing out ("Image not processed") or had an
 * encoding Google could not read. The website keeps using its own images; only the feed uses these.
 */
const DL_FEED_IMG_DIR = 'dl-feed';

/**
 * Google-ready image URL for an attachment (the original until its JPEG copy exists).
 *
 * @param int $aid Attachment ID.
 * @return string
 */
function dl_feed_img_url( $aid ) {
	$map = (array) get_option( 'dl_feed_imgs', array() );
	if ( ! empty( $map[ $aid ] ) && dl_feed_img_needed( $aid ) ) {
		$up = wp_get_upload_dir();
		return set_url_scheme( $up['baseurl'], 'https' ) . '/' . DL_FEED_IMG_DIR . '/' . $map[ $aid ];
	}
	return (string) wp_get_attachment_url( $aid );
}

/**
 * Only heavy photos (PNG, or over 400 KB) get a copy; the rest are already fine for Google.
 *
 * @param int $aid Attachment ID.
 * @return bool
 */
function dl_feed_img_needed( $aid ) {
	static $cache = array();
	if ( ! isset( $cache[ $aid ] ) ) {
		$file           = get_attached_file( $aid );
		$cache[ $aid ] = in_array( (int) $aid, array_map( 'intval', (array) get_option( 'dl_feed_force', array() ) ), true ) || $file && file_exists( $file ) && ( preg_match( '/\.png$/i', $file ) || filesize( $file ) > 400 * 1024 );
	}
	return $cache[ $aid ];
}

/**
 * Make the JPEG copy of one attachment.
 *
 * @param int $aid Attachment ID.
 * @return bool
 */
function dl_feed_img_make( $aid ) {
	$file = get_attached_file( $aid );
	if ( ! $file || ! file_exists( $file ) ) {
		return false;
	}
	$editor = wp_get_image_editor( $file );
	if ( is_wp_error( $editor ) ) {
		return false;
	}
	$size = $editor->get_size();
	if ( max( $size['width'], $size['height'] ) > 1200 ) {
		$editor->resize( 1200, 1200, false );
	}
	$editor->set_quality( 85 );
	$up  = wp_get_upload_dir();
	$dir = $up['basedir'] . '/' . DL_FEED_IMG_DIR;
	wp_mkdir_p( $dir );
	$name  = (int) $aid . '-' . substr( md5( $file . filemtime( $file ) ), 0, 8 ) . '.jpg'; // New name when the photo changes.
	$saved = $editor->save( $dir . '/' . $name, 'image/jpeg' );
	if ( is_wp_error( $saved ) ) {
		return false;
	}
	$map         = (array) get_option( 'dl_feed_imgs', array() );
	$map[ $aid ] = $name;
	update_option( 'dl_feed_imgs', $map, false );
	return true;
}

/**
 * Make JPEG copies for product photos that do not have one yet (main photo first).
 *
 * @param int $limit Photos per run.
 * @return int Photos still waiting.
 */
function dl_feed_img_batch( $limit = 25 ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_col( "SELECT pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product' AND p.post_status = 'publish' WHERE pm.meta_key = '_thumbnail_id' AND pm.meta_value <> ''" );
	$map  = (array) get_option( 'dl_feed_imgs', array() );
	$fail = (array) get_option( 'dl_feed_imgs_failed', array() );
	$rows = array_filter( $rows, static fn( $a ) => dl_feed_img_needed( (int) $a ) );
	$todo = array_values( array_diff( array_unique( array_map( 'intval', $rows ) ), array_map( 'intval', array_keys( $map ) ), array_map( 'intval', array_keys( $fail ) ) ) );
	foreach ( array_slice( $todo, 0, $limit ) as $aid ) {
		if ( ! dl_feed_img_make( $aid ) ) {
			$fail[ $aid ] = time();
			update_option( 'dl_feed_imgs_failed', $fail, false );
		}
	}
	if ( array_slice( $todo, 0, $limit ) ) {
		delete_transient( 'dl_google_feed' );
	}
	return max( 0, count( $todo ) - $limit );
}
add_action( 'dl_img_cron', static fn() => dl_feed_img_batch( 25 ), 20 );

// A replaced or edited photo gets a fresh JPEG copy.
add_filter(
	'wp_update_attachment_metadata',
	static function ( $data, $aid ) {
		$map = (array) get_option( 'dl_feed_imgs', array() );
		if ( isset( $map[ $aid ] ) ) {
			unset( $map[ $aid ] );
			update_option( 'dl_feed_imgs', $map, false );
		}
		return $data;
	},
	30,
	2
);
