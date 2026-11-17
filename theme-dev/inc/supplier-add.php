<?php
/**
 * New Elite Tech watches are added to the shop automatically (owner, 2026-11-16).
 *
 * The 05:00 stock check finds watches that are new at Elite Tech and queues them here. Each one is
 * checked against the shop's rules, created, audited, and only then left published; the owner gets
 * one email with links and each watch's audit result.
 *  - only in-stock Elite Tech watches of the brands we sell from them; never Megir;
 *  - never a second copy: skipped when the model number (or the Elite Tech page) is already in the
 *    shop in any status. A Naviforce/Curren model we already sell (maybe another colour) is held;
 *  - prices (owner's rules): margin bands on Elite Tech's prices for Casio, Seiko, Fossil and
 *    Michael Kors (Fossil's bands); Naviforce and Curren are priced like the most similar watches
 *    we already sell in that brand (same men/ladies, same type, similar Elite Tech price). No similar
 *    watch, no price: it is held for the owner, never guessed;
 *  - our own title (brand first, exact model number, at most 85 characters) and the supplier's text
 *    with their name, links, prices and warranty promises removed;
 *  - supplier Elite Tech (private), linked to the daily stock check;
 *  - audit after creating: price, title, photo, text, categories, stock, stock-check link, supplier,
 *    no duplicate, and the live product page opening. Any failure puts it back to hidden (draft) and
 *    lists it for the owner with the reason.
 * Work is done a few watches at a time from WP-Cron so no request runs long.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

const DL_ET_ADD_PER_RUN = 3;
const DL_ET_ADD_PER_DAY = 30;

/**
 * Margin added to Elite Tech's prices, by their selling price: below 8,000 / below 25,000 / below 45,000 / 45,000 and up.
 */
const DL_ET_BANDS = array(
	'Casio'        => array( 2000, 3000, 4000, 6000 ),
	'Seiko'        => array( 2000, 4000, 5000, 8000 ),
	'Fossil'       => array( 2000, 3000, 3000, 5000 ),
	'Michael Kors' => array( 2000, 3000, 3000, 5000 ), // Owner 2026-11-16: same as Fossil.
);

/**
 * Brands priced like the similar watches we already sell (owner 2026-11-16), not by a margin.
 */
const DL_ET_PEER_BRANDS = array( 'Naviforce', 'Curren' );

/**
 * Our categories for a brand, found by name: all watches, the brand, its men's and women's
 * categories, and G-Shock for Casio. Null when the brand has no category.
 *
 * @param string $brand Brand.
 * @return array|null
 */
function dl_et_cats( $brand ) {
	static $cache = array();
	if ( array_key_exists( $brand, $cache ) ) {
		return $cache[ $brand ];
	}
	$all   = get_term_by( 'slug', 'watches', 'product_cat' );
	$all   = $all ? (int) $all->term_id : 222;
	$terms = get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'name' => $brand, 'parent' => $all ) );
	if ( is_wp_error( $terms ) || ! $terms ) {
		$cache[ $brand ] = null;
		return null;
	}
	$c = array( 'all' => $all, 'brand' => (int) $terms[0]->term_id, 'men' => 0, 'women' => 0, 'gshock' => 0 );
	foreach ( (array) get_terms( array( 'taxonomy' => 'product_cat', 'hide_empty' => false, 'parent' => $c['brand'] ) ) as $t ) {
		if ( preg_match( '/\b(women|ladies)\b/i', $t->name ) ) {
			$c['women'] = (int) $t->term_id;
		} elseif ( preg_match( '/\bmen\b/i', $t->name ) ) {
			$c['men'] = (int) $t->term_id;
		} elseif ( preg_match( '/g-?shock/i', $t->name ) ) {
			$c['gshock'] = (int) $t->term_id;
		}
	}
	if ( 'Casio' === $brand && ! $c['gshock'] ) {
		$g           = get_term_by( 'name', 'G-Shock', 'product_cat' );
		$c['gshock'] = $g ? (int) $g->term_id : 0;
	}
	// The categories used for every upload so far win over the name lookup (Fossil has two "Watches for Women").
	$known = array(
		'Casio'     => array( 'brand' => 411, 'men' => 442, 'women' => 443, 'gshock' => 460 ),
		'Seiko'     => array( 'brand' => 445, 'men' => 464, 'women' => 463 ),
		'Fossil'    => array( 'brand' => 195, 'men' => 449, 'women' => 450 ),
		'Naviforce' => array( 'brand' => 446, 'men' => 447, 'women' => 448 ),
		'Curren'    => array( 'brand' => 457, 'men' => 458, 'women' => 459 ),
	);
	foreach ( $known[ $brand ] ?? array() as $k => $tid ) {
		if ( get_term( $tid, 'product_cat' ) instanceof WP_Term ) {
			$c[ $k ] = $tid;
		}
	}
	$cache[ $brand ] = $c;
	return $c;
}

/**
 * The model number in a supplier product name, or '' when none can be read.
 *
 * @param string $brand Brand.
 * @param string $name  Product name.
 * @return string
 */
function dl_et_model( $brand, $name ) {
	$n = strtoupper( $name );
	if ( 'Naviforce' === $brand ) {
		return preg_match( '/\bNF\s?-?(\d{4}[A-Z]?)\b/', $n, $m ) ? 'NF' . $m[1] : '';
	}
	if ( 'Curren' === $brand ) {
		return preg_match( '/\b([89]\d{3})\b/', $n, $m ) ? $m[1] : '';
	}
	if ( 'Fossil' === $brand ) {
		return preg_match( '/\b([A-Z]{2}\d{4})\b/', $n, $m ) ? $m[1] : '';
	}
	if ( 'Michael Kors' === $brand ) {
		return preg_match( '/\bMK\s?-?(\d{4})\b/', $n, $m ) ? 'MK' . $m[1] : '';
	}
	// Casio and Seiko: the longest code made of letters and digits (dashes allowed), e.g. GA-2100-1A1DR, SNKE53K1.
	$best = '';
	if ( preg_match_all( '/\b[A-Z0-9]+(?:-[A-Z0-9]+)*\b/', $n, $all ) ) {
		foreach ( $all[0] as $tok ) {
			if ( strlen( $tok ) >= 5 && preg_match( '/\d/', $tok ) && preg_match( '/[A-Z]/', $tok ) && 'G-SHOCK' !== $tok && strlen( $tok ) > strlen( $best ) ) {
				$best = $tok;
			}
		}
	}
	return $best;
}

/**
 * The type of watch, for comparing like with like.
 *
 * @param string $text Name.
 * @return string
 */
function dl_et_kind( $text ) {
	if ( preg_match( '/chrono/i', $text ) ) {
		return 'chronograph';
	}
	if ( preg_match( '/dual|digital|ana-?digi|multi-?function/i', $text ) ) {
		return 'dual display';
	}
	return 'three-hand';
}

/**
 * Elite Tech's in-stock list with prices (saved by the morning stock check; read again if missing).
 *
 * @return array
 */
function dl_et_list() {
	$list = get_transient( 'dl_et_list' );
	if ( ! is_array( $list ) || ! $list || ! isset( reset( $list )['price'] ) ) {
		$list = function_exists( 'dl_et_fetch_instock' ) ? dl_et_fetch_instock() : null;
		if ( is_array( $list ) && count( $list ) >= 200 ) {
			set_transient( 'dl_et_list', $list, 2 * DAY_IN_SECONDS );
		}
	}
	return is_array( $list ) ? $list : array();
}

/**
 * Price a Naviforce or Curren watch like the most similar ones we already sell: same brand, same
 * men's/ladies', same type where possible, and an Elite Tech price within KSh 300.
 *
 * @param string $brand Brand.
 * @param int    $now   Elite Tech's selling price.
 * @param bool   $women Ladies watch.
 * @param string $kind  Type (dl_et_kind).
 * @return array|string array( regular, sale, 'like' => names ) or the reason to hold it.
 */
function dl_et_peer_price( $brand, $now, $women, $kind ) {
	$c    = dl_et_cats( $brand );
	$list = dl_et_list();
	if ( ! $c || ! $list ) {
		return 'our ' . $brand . ' prices could not be compared';
	}
	$peers = array();
	foreach ( (array) get_option( 'dl_et_map', array() ) as $pid => $url ) {
		$u = trailingslashit( strtolower( (string) $url ) );
		if ( empty( $list[ $u ]['price'] ) || ! has_term( $c['brand'], 'product_cat', (int) $pid ) ) {
			continue;
		}
		$p = wc_get_product( (int) $pid );
		if ( ! $p || 'publish' !== $p->get_status() || ! (float) $p->get_price() ) {
			continue;
		}
		$is_w    = $c['women'] && has_term( $c['women'], 'product_cat', (int) $pid ) && ! ( $c['men'] && has_term( $c['men'], 'product_cat', (int) $pid ) );
		$peers[] = array(
			'et'    => (int) $list[ $u ]['price'],
			'sale'  => (int) $p->get_price(),
			'reg'   => (int) $p->get_regular_price(),
			'women' => $is_w,
			'kind'  => dl_et_kind( $p->get_name() ),
			'name'  => $p->get_name(),
		);
	}
	foreach ( array( true, false ) as $same_kind ) {
		$m = array_filter( $peers, static fn( $x ) => $x['women'] === $women && abs( $x['et'] - $now ) <= 300 && ( ! $same_kind || $x['kind'] === $kind ) );
		if ( ! $m ) {
			continue;
		}
		// The price we charge most often for these; a tie goes to the one whose Elite Tech price is closest.
		$by = array();
		foreach ( $m as $x ) {
			$by[ $x['sale'] ][] = $x;
		}
		uasort(
			$by,
			static function ( $a, $b ) use ( $now ) {
				if ( count( $a ) !== count( $b ) ) {
					return count( $b ) - count( $a );
				}
				$da = min( array_map( static fn( $x ) => abs( $x['et'] - $now ), $a ) );
				$db = min( array_map( static fn( $x ) => abs( $x['et'] - $now ), $b ) );
				return $da - $db;
			}
		);
		$sale  = (int) array_key_first( $by );
		$regs  = array_count_values( array_map( static fn( $x ) => $x['reg'], $by[ $sale ] ) );
		arsort( $regs );
		$reg   = (int) array_key_first( $regs );
		$like  = array_slice( array_map( static fn( $x ) => $x['name'] . ' (Elite Tech KSh ' . number_format( $x['et'] ) . ')', $by[ $sale ] ), 0, 3 );
		return $reg > $sale ? array( $reg, $sale, 'like' => $like ) : array( $sale, '', 'like' => $like );
	}
	return 'no similar ' . ( $women ? 'ladies ' : 'men\'s ' ) . $brand . ' watch on our site at about KSh ' . number_format( $now ) . ' (Elite Tech) to copy the price from';
}

/**
 * Our prices from the supplier's, by the owner's rules.
 *
 * @param string $brand Brand.
 * @param int    $reg   Supplier's regular (crossed-out) price.
 * @param int    $now   Supplier's selling price.
 * @param bool   $women Ladies watch.
 * @param string $name  Product name.
 * @return array|string array( regular, sale ) with sale '' when none, or the reason to hold it.
 */
function dl_et_price( $brand, $reg, $now, $women, $name ) {
	if ( $now < 1000 || $reg < $now ) {
		return 'the Elite Tech price looks wrong (KSh ' . number_format( $now ) . ')';
	}
	if ( isset( DL_ET_BANDS[ $brand ] ) ) {
		$b   = DL_ET_BANDS[ $brand ];
		$add = $now < 8000 ? $b[0] : ( $now < 25000 ? $b[1] : ( $now < 45000 ? $b[2] : $b[3] ) );
		$r   = $reg + $add;
		$s   = $now + $add;
		return array( $r, $s < $r ? $s : '', 'rule' => '+' . number_format( $add ) . ' on Elite Tech' );
	}
	if ( in_array( $brand, DL_ET_PEER_BRANDS, true ) ) {
		return dl_et_peer_price( $brand, $now, $women, dl_et_kind( $name ) );
	}
	return 'there is no price rule for ' . $brand . ' yet';
}

/**
 * Our title: brand first, the exact model number, no supplier or "price in Kenya" wording, at most 85 characters.
 *
 * @param string $brand  Brand.
 * @param string $name   Supplier name.
 * @param string $model  Model number.
 * @param bool   $gshock G-Shock watch.
 * @return string '' when a good title cannot be made.
 */
function dl_et_title( $brand, $name, $model, $gshock ) {
	$t = html_entity_decode( $name, ENT_QUOTES, 'UTF-8' );
	$t = preg_replace( '/[\x{2010}-\x{2015}\x{2212}]/u', '-', $t );
	$t = str_replace( array( "\xc2\xa0", "\u{2019}" ), array( ' ', "'" ), $t );
	$t = preg_replace( '/\s*\|?\s*(?:Best\s+)?Price\s+in\s+Kenya\b\s*\|?/i', ' ', $t );
	$t = preg_replace( '/\s*\|?\s*(?:Elite\s*Tech+h?(?:\s*Base)?(?:\s*Kenya)?|Casio\s+Kenya)\b\s*\|?/i', ' ', $t );
	$t = preg_replace( '/\s+in\s+Kenya\b/i', '', $t );
	$t = preg_replace( '/\b(' . preg_quote( $brand, '/' ) . ')\s+\1\b/i', '$1', $t );
	// The model number written exactly, e.g. "MTP V005L 7B5UDF" -> "MTP-V005L-7B5UDF", "nf 9275" -> "NF9275".
	if ( in_array( $brand, array( 'Naviforce', 'Michael Kors' ), true ) ) {
		$loose = preg_quote( substr( $model, 0, 2 ), '/' ) . '[\s-]?' . preg_quote( substr( $model, 2 ), '/' );
	} else {
		$loose = str_replace( '\-', '[\s-]', preg_quote( $model, '/' ) );
	}
	$t = preg_replace( '/\b' . $loose . '\b/i', $model, $t );
	$t = trim( preg_replace( '/\s{2,}/', ' ', $t ), " -|\t" );
	$t = preg_replace( '/^' . preg_quote( $brand, '/' ) . '\b/i', $brand, $t );
	if ( 0 !== stripos( $t, $brand ) ) {
		$t = $brand . ' ' . $t;
	}
	if ( $gshock && ! preg_match( '/g-?shock/i', $t ) ) {
		$t = preg_replace( '/^Casio /', 'Casio G-Shock ', $t );
	}
	$t = str_ireplace( array( 'G-SHOCK', 'G-STEEL', 'BABY-G' ), array( 'G-Shock', 'G-Steel', 'Baby-G' ), $t );
	if ( strlen( $t ) > 85 ) {
		$t = substr( $t, 0, strrpos( substr( $t, 0, 86 ), ' ' ) );
		// Never end on a joining word: "... Bezel &" -> "... Bezel".
		$t = preg_replace( '/(?:\s+(?:&|and|with|for|in|on|-|\|))+\s*$/i', '', rtrim( $t, ' ,|-' ) );
	}
	return false === strpos( $t, $model ) ? '' : $t;
}

/**
 * The supplier's description made ours: no links, images, scripts, their name, prices or warranty promises.
 *
 * @param string $h     HTML.
 * @param int    $price Our price, used wherever the text states one.
 * @return string
 */
function dl_et_text( $h, $price ) {
	$h = str_contains( $h, '&lt;' ) ? html_entity_decode( $h, ENT_QUOTES, 'UTF-8' ) : $h;
	$h = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $h );
	$h = preg_replace( '#<(script|style|iframe)\b.*?</\1>#is', '', $h );
	$h = preg_replace( '#<img\b[^>]*>#i', '', $h );
	$h = preg_replace( '#<a\b[^>]*>(.*?)</a>#is', '$1', $h );
	$h = preg_replace( '#https?://\S+#i', '', $h );
	$h = preg_replace( '/\s(?:data-[\w-]+|class|style|id|dir)="[^"]*"/', '', $h );
	$h = preg_replace( '#<span>(.*?)</span>#s', '$1', $h );
	$h = preg_replace( '#<b>(.*?)</b>#s', '<strong>$1</strong>', $h );
	$shop = 'Dlight Giftshop';
	$h    = preg_replace( '/Elite\s*Tech+h?(?:\s*Base)?(?:\s*Kenya)?/i', $shop, $h );
	$h    = preg_replace( '/elitetechh\.com/i', 'dlightgiftshop.com', $h );
	// The supplier's warranty is their promise, not ours.
	$h = preg_replace( '#<tr>(?:(?!</tr>).)*?warrant(?:(?!</tr>).)*</tr>\s*#is', '', $h );
	$h = preg_replace( '#<(li|p)>(?:(?!</\1>).)*?warrant(?:(?!</\1>).)*</\1>\s*#is', '', $h );
	$p = 'KSh ' . number_format( $price );
	$h = preg_replace( '/(?:KShs|KSh|KES)\.?\s?[\d,]+(?:\.\d+)?(?:\s?\/=)?/i', $p, $h );
	$h = preg_replace( '/' . preg_quote( $p, '/' ) . '(?:\s|&nbsp;)*(?:[-\x{2013}\x{2014}]|&#821[12];|&[nm]dash;)(?:\s|&nbsp;)*' . preg_quote( $p, '/' ) . '/u', $p, $h );
	$h = preg_replace( '/\*\s*\(approximate[^)]*\)/i', '', $h );
	$h = preg_replace( '/(?:Approximate )?Price Range in Kenya|Approximate Price in Kenya/i', 'Price in Kenya', $h );
	$h = preg_replace( '#<(p|li|strong|h\d|td|th)>\s*</\1>#', '', $h );
	$h = preg_replace( '#<p>(?:\s|&nbsp;)*</p>#', '', $h );
	$h = preg_replace( "/\n{3,}/", "\n\n", $h );
	return trim( wp_kses_post( $h ) );
}

/**
 * Our product in the shop (any status) that already has this model, or 0.
 *
 * @param string $brand   Brand.
 * @param string $model   Model number.
 * @param int    $exclude Product to leave out (the one just created).
 * @return int
 */
function dl_et_existing( $brand, $model, $exclude = 0 ) {
	global $wpdb;
	$like = array( '%' . $wpdb->esc_like( $model ) . '%', '%' . $wpdb->esc_like( str_replace( '-', ' ', $model ) ) . '%' );
	if ( in_array( $brand, array( 'Naviforce', 'Michael Kors' ), true ) ) {
		$like[1] = '%' . substr( $model, 0, 2 ) . ' ' . $wpdb->esc_like( substr( $model, 2 ) ) . '%';
	}
	$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status <> 'auto-draft' AND ID <> %d AND (post_title LIKE %s OR post_title LIKE %s OR post_name LIKE %s)";
	$arg = array( (int) $exclude, $like[0], $like[1], '%' . $wpdb->esc_like( strtolower( $model ) ) . '%' );
	if ( 'Curren' === $brand ) { // A bare 4-digit number: only among Curren listings.
		$sql  .= ' AND post_title LIKE %s';
		$arg[] = '%Curren%';
	}
	return (int) $wpdb->get_var( $wpdb->prepare( $sql . ' LIMIT 1', $arg ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

/**
 * Read one Elite Tech product and decide what we would publish.
 *
 * @param string $url   Elite Tech product page.
 * @param string $brand Brand (from the Elite Tech category it was found in).
 * @return array Either 'plan' (everything needed to create it) or 'hold' (the reason not to).
 */
function dl_et_plan( $url, $brand ) {
	$base = array( 'url' => $url, 'brand' => $brand );
	$c    = dl_et_cats( $brand );
	if ( ! $c ) {
		return $base + array( 'hold' => 'we have no ' . $brand . ' category' );
	}
	$slug = basename( untrailingslashit( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
	$r    = wp_remote_get( add_query_arg( 'slug', rawurlencode( $slug ), 'https://elitetechh.com/wp-json/wc/store/v1/products' ), array( 'timeout' => 30, 'user-agent' => 'Mozilla/5.0 (compatible; DlightStockCheck/1.0)' ) );
	$list = is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ? null : json_decode( wp_remote_retrieve_body( $r ), true );
	if ( ! is_array( $list ) ) {
		return $base + array( 'retry' => 1, 'hold' => 'Elite Tech could not be reached' );
	}
	$s = $list[0] ?? null;
	if ( ! $s ) {
		return $base + array( 'hold' => 'Elite Tech no longer lists it' );
	}
	$name           = html_entity_decode( (string) $s['name'], ENT_QUOTES, 'UTF-8' );
	$base['name']   = $name;
	$base['sup_id'] = (int) $s['id'];
	if ( empty( $s['is_in_stock'] ) ) {
		return $base + array( 'hold' => 'out of stock at Elite Tech' );
	}
	if ( 'simple' !== ( $s['type'] ?? '' ) ) {
		return $base + array( 'hold' => 'it has several options (colours or sizes) on Elite Tech' );
	}
	if ( preg_match( '/megir/i', $name . ' ' . $url ) ) {
		return $base + array( 'hold' => 'Megir, which we do not sell from Elite Tech' );
	}
	$model = dl_et_model( $brand, $name );
	if ( '' === $model ) {
		return $base + array( 'hold' => 'no model number in the name' );
	}
	$base['model'] = $model;
	$dupe          = dl_et_existing( $brand, $model );
	if ( ! $dupe ) {
		global $wpdb;
		$dupe = (int) $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE (meta_key = '_dl_supplier_id' AND meta_value = %s) OR (meta_key = '_dl_supplier_url' AND meta_value = %s) LIMIT 1", (string) $s['id'], $url ) );
	}
	if ( $dupe ) {
		$what = in_array( $brand, DL_ET_PEER_BRANDS, true ) ? 'we already sell ' . $model . ' (maybe this is another colour)' : $model . ' is already in the shop';
		return $base + array( 'hold' => $what, 'ours' => $dupe );
	}
	$div   = 10 ** (int) ( $s['prices']['currency_minor_unit'] ?? 2 );
	$reg   = (int) round( (int) $s['prices']['regular_price'] / $div );
	$now   = (int) round( (int) ( $s['prices']['sale_price'] ?: $s['prices']['regular_price'] ) / $div );
	$slugs = wp_list_pluck( (array) $s['categories'], 'slug' );
	$women = in_array( 'womens-watches', $slugs, true ) || preg_match( '/\b(women|womens|ladies|lady)\b/i', $name );
	$men   = in_array( 'mens-watches', $slugs, true ) || preg_match( '/\bmen\'?s?\b/i', $name ) || ! $women;
	if ( preg_match( '/unisex/i', $name ) ) {
		$women = true;
		$men   = true;
	}
	$base['sup_price'] = $now;
	$price             = dl_et_price( $brand, $reg, $now, $women && ! $men, $name );
	if ( ! is_array( $price ) ) {
		return $base + array( 'hold' => $price );
	}
	$gshock = 'Casio' === $brand && ( in_array( 'g-shock-watches', $slugs, true ) || preg_match( '/g-?shock/i', $name . ' ' . $url ) );
	$cats   = array( $c['all'], $c['brand'] );
	if ( $men && $c['men'] ) {
		$cats[] = $c['men'];
	}
	if ( $women && $c['women'] ) {
		$cats[] = $c['women'];
	}
	if ( $gshock && $c['gshock'] ) {
		$cats[] = $c['gshock'];
	}
	$title = dl_et_title( $brand, $name, $model, $gshock );
	if ( '' === $title ) {
		return $base + array( 'hold' => 'a clean title could not be made from "' . $name . '"' );
	}
	$images = array_slice( array_values( array_filter( wp_list_pluck( (array) $s['images'], 'src' ) ) ), 0, 6 );
	if ( ! $images ) {
		return $base + array( 'hold' => 'Elite Tech has no photo of it' );
	}
	$sell  = '' === $price[1] ? $price[0] : $price[1];
	$long  = dl_et_text( (string) $s['description'], $sell );
	$short = dl_et_text( (string) $s['short_description'], $sell );
	if ( '' === wp_strip_all_tags( $short ) ) {
		$short = '<p>The <strong>' . esc_html( $title ) . '</strong>, in stock at Dlight Giftshop in Nairobi. Pay by M-Pesa or cash on delivery, with delivery across Kenya.</p>';
	}
	$file = sanitize_file_name( str_replace( ' ', '-', $brand . '-' . $model ) );
	return $base + array(
		'how'  => $price['rule'] ?? 'priced like: ' . implode( '; ', (array) ( $price['like'] ?? array() ) ),
		'plan' => array(
			'title'   => $title,
			'regular' => $price[0],
			'sale'    => $price[1],
			'cats'    => $cats,
			'content' => '' === wp_strip_all_tags( $long ) ? $short : $long,
			'excerpt' => $short,
			'images'  => array_map(
				static fn( $src, $k ) => array( 'src' => $src, 'name' => $file . ( $k ? '-' . ( $k + 1 ) : '' ) . '.' . ( strtolower( pathinfo( (string) wp_parse_url( $src, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ?: 'jpg' ) ),
				$images,
				array_keys( $images )
			),
		),
	);
}

/**
 * Create and publish one planned product and link it to the stock check.
 *
 * @param array $row Result of dl_et_plan() with a 'plan'.
 * @return array The row with 'id', 'status' and any photo errors.
 */
function dl_et_create( $row ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$plan = $row['plan'];
	$p    = new WC_Product_Simple();
	$p->set_name( $plan['title'] );
	$p->set_status( 'draft' );
	$p->set_regular_price( (string) $plan['regular'] );
	$p->set_sale_price( '' === $plan['sale'] ? '' : (string) $plan['sale'] );
	$p->set_description( $plan['content'] );
	$p->set_short_description( $plan['excerpt'] );
	$p->set_category_ids( array_map( 'intval', $plan['cats'] ) );
	$p->set_manage_stock( false );
	$p->set_stock_status( 'instock' );
	$p->set_catalog_visibility( 'visible' );
	$id = $p->save();
	if ( ! $id ) {
		return $row + array( 'hold' => 'the product could not be created' );
	}
	if ( taxonomy_exists( 'dl_supplier' ) ) {
		wp_set_object_terms( $id, 'Elite Tech', 'dl_supplier' );
	}
	update_post_meta( $id, '_dl_supplier_id', (string) $row['sup_id'] );
	update_post_meta( $id, '_dl_supplier_url', esc_url_raw( $row['url'] ) );
	update_post_meta( $id, '_dl_auto_added', time() );
	$imgs = array();
	$errs = array();
	foreach ( $plan['images'] as $img ) {
		$tmp = download_url( $img['src'], 60 );
		if ( is_wp_error( $tmp ) ) {
			$errs[] = $tmp->get_error_message();
			continue;
		}
		$aid = media_handle_sideload( array( 'name' => $img['name'], 'tmp_name' => $tmp ), $id, $plan['title'] );
		if ( is_wp_error( $aid ) ) {
			wp_delete_file( $tmp );
			$errs[] = $aid->get_error_message();
			continue;
		}
		update_post_meta( $aid, '_wp_attachment_image_alt', $plan['title'] );
		$imgs[] = (int) $aid;
	}
	$p = wc_get_product( $id );
	if ( $imgs ) {
		$p->set_image_id( $imgs[0] );
		$p->set_gallery_image_ids( array_slice( $imgs, 1 ) );
	}
	if ( $imgs && wp_attachment_is_image( $imgs[0] ) ) {
		$p->set_status( 'publish' );
	}
	$p->save();
	// The daily stock check keeps it in or out of stock from now on.
	$map        = (array) get_option( 'dl_et_map', array() );
	$map[ $id ] = $row['url'];
	update_option( 'dl_et_map', $map, false );
	$row += array( 'id' => (int) $id, 'photos' => count( $imgs ), 'errors' => $errs );
	// Audit; anything wrong goes back to hidden and to the owner.
	$row['audit'] = dl_et_audit( $id, $row );
	if ( $row['audit']['failed'] ) {
		$p = wc_get_product( $id );
		$p->set_status( 'draft' );
		$p->save();
		$row['hold'] = 'failed the check, so it is hidden: ' . implode( '; ', $row['audit']['failed'] );
		$row['ours'] = (int) $id;
	}
	$row['status'] = get_post_status( $id );
	return $row;
}

/**
 * Check a newly added watch from the saved product, as a shopper and as the shop would see it.
 *
 * @param int   $id  Product.
 * @param array $row Plan row.
 * @return array( 'passed' => count, 'failed' => reasons )
 */
function dl_et_audit( $id, $row ) {
	clean_post_cache( $id );
	$plan = $row['plan'];
	$p    = wc_get_product( $id );
	$ok   = 0;
	$fail = array();
	$chk  = static function ( $cond, $why ) use ( &$ok, &$fail ) {
		if ( $cond ) {
			++$ok;
		} else {
			$fail[] = $why;
		}
	};
	if ( ! $p ) {
		return array( 'passed' => 0, 'failed' => array( 'the product could not be read back' ) );
	}
	$sell = '' === $plan['sale'] ? (int) $plan['regular'] : (int) $plan['sale'];
	$chk( (int) $p->get_regular_price() === (int) $plan['regular'] && (string) $p->get_sale_price() === (string) $plan['sale'] && (int) $p->get_price() === $sell, 'the saved price is not the rule price' );
	$chk( $sell >= ( (int) ( $row['sup_price'] ?? 0 ) + 400 ), 'our price is not above Elite Tech\'s' );
	$name = html_entity_decode( $p->get_name(), ENT_QUOTES, 'UTF-8' ); // WordPress saves "&" as "&amp;".
	$chk( false !== strpos( $name, (string) $row['model'] ) && strlen( $name ) <= 85 && 0 === stripos( $name, (string) $row['brand'] ), 'the title is missing the brand or model, or is too long' );
	$chk( ! dl_et_existing( $row['brand'], (string) $row['model'], $id ), 'another product with this model appeared' );
	$img = (int) $p->get_image_id();
	$chk( $img && wp_attachment_is_image( $img ) && is_file( (string) get_attached_file( $img ) ), 'the main photo is missing' );
	$text = $p->get_name() . ' ' . $p->get_description() . ' ' . $p->get_short_description();
	$chk( ! preg_match( '/elite\s*tech|elitetechh|<a\s|https?:\/\//i', $text ), 'the supplier\'s name or a link is still in the text' );
	preg_match_all( '/KSh\s?([\d,]+)/i', $text, $m );
	$chk( ! array_diff( array_map( static fn( $v ) => (int) str_replace( ',', '', $v ), $m[1] ), array( $sell ) ), 'the text states a different price' );
	$c    = dl_et_cats( $row['brand'] );
	$cats = $p->get_category_ids();
	$chk( $c && in_array( $c['brand'], $cats, true ) && ( ! $c['men'] || ! $c['women'] || array_intersect( array( $c['men'], $c['women'] ), $cats ) ), 'it is not in the right categories' );
	$chk( 'instock' === $p->get_stock_status(), 'it is not in stock' );
	$chk( ( (array) get_option( 'dl_et_map', array() ) )[ $id ] ?? false, 'it is not linked to the daily stock check' );
	$sup = taxonomy_exists( 'dl_supplier' ) ? wp_get_object_terms( $id, 'dl_supplier', array( 'fields' => 'names' ) ) : array( 'Elite Tech' );
	$chk( is_array( $sup ) && in_array( 'Elite Tech', $sup, true ), 'the supplier is not set to Elite Tech' );
	// The product page opens for shoppers with the right title and price (loaded inside the server).
	$page = false;
	if ( 'publish' === $p->get_status() ) {
		$r    = wp_remote_get(
			add_query_arg( 'dl_hc', time(), 'http://127.0.0.1' . (string) wp_parse_url( get_permalink( $id ), PHP_URL_PATH ) ),
			array( 'timeout' => 45, 'redirection' => 0, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (DlightHealthCheck)', 'headers' => array( 'Host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'X-Forwarded-Proto' => 'https' ) )
		);
		$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		$page = ! is_wp_error( $r ) && 200 === (int) wp_remote_retrieve_response_code( $r ) && false !== strpos( $body, (string) $row['model'] ) && false !== strpos( $body, number_format( $sell ) ) && false === stripos( $body, 'critical error' );
	}
	$chk( $page, 'the product page did not open correctly (or it is not published because the photo failed)' );
	return array( 'passed' => $ok, 'failed' => $fail );
}

/**
 * Queue Elite Tech pages to be added.
 *
 * @param array $items List of array( 'url' => ..., 'brand' => ... ).
 */
function dl_et_add_queue( $items ) {
	$q = (array) get_option( 'dl_et_add_queue', array() );
	foreach ( $items as $it ) {
		$q[ trailingslashit( strtolower( $it['url'] ) ) ] = $it['brand'];
	}
	update_option( 'dl_et_add_queue', $q, false );
	if ( $q && ! wp_next_scheduled( 'dl_et_add_run' ) ) {
		wp_schedule_single_event( time() + 60, 'dl_et_add_run' );
	}
}

/**
 * Work through the queue a few at a time; email the owner once it is done.
 */
function dl_et_add_run() {
	if ( get_transient( 'dl_et_add_lock' ) ) {
		return;
	}
	set_transient( 'dl_et_add_lock', 1, 10 * MINUTE_IN_SECONDS );
	@set_time_limit( 300 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	$q     = (array) get_option( 'dl_et_add_queue', array() );
	$rep   = (array) get_option( 'dl_et_add_report', array( 'added' => array(), 'held' => array() ) );
	$today = wp_date( 'Y-m-d' );
	$count = (array) get_option( 'dl_et_add_count', array() );
	$done  = (int) ( $count[ $today ] ?? 0 );
	$n     = 0;
	$row   = array();
	foreach ( $q as $url => $brand ) {
		if ( $n >= DL_ET_ADD_PER_RUN || $done >= DL_ET_ADD_PER_DAY ) {
			break;
		}
		++$n;
		$row = dl_et_plan( $url, $brand );
		if ( ! empty( $row['retry'] ) ) {
			break; // Elite Tech is down: keep the queue for the next run.
		}
		unset( $q[ $url ] );
		if ( isset( $row['plan'] ) ) {
			$row = dl_et_create( $row );
			++$done;
		}
		unset( $row['plan'] );
		$rep[ empty( $row['hold'] ) ? 'added' : 'held' ][] = $row;
	}
	update_option( 'dl_et_add_queue', $q, false );
	update_option( 'dl_et_add_report', $rep, false );
	update_option( 'dl_et_add_count', array( $today => $done ), false );
	delete_transient( 'dl_et_add_lock' );
	$capped = $done >= DL_ET_ADD_PER_DAY;
	if ( $q && ! $capped ) {
		wp_schedule_single_event( time() + ( empty( $row['retry'] ) ? 60 : 30 * MINUTE_IN_SECONDS ), 'dl_et_add_run' );
		return;
	}
	if ( $rep['added'] || $rep['held'] ) {
		dl_et_add_email( $rep, $capped ? count( $q ) : 0 );
		delete_option( 'dl_et_add_report' );
		if ( $rep['added'] ) {
			delete_transient( 'dl_google_feed' );
			do_action( 'clear_varnish' );
		}
	}
}
add_action( 'dl_et_add_run', 'dl_et_add_run' );

/**
 * One email: everything added (with links and its audit) and what was held back and why.
 *
 * @param array $rep  Report.
 * @param int   $left Watches left for tomorrow because of the daily limit.
 */
function dl_et_add_email( $rep, $left = 0 ) {
	$l    = array();
	$cell = 'padding:8px 10px 8px 0;border-bottom:1px solid #eee;vertical-align:top';
	if ( $rep['added'] ) {
		$l[] = '<p style="font-size:16px"><strong>Added to the website (' . count( $rep['added'] ) . '), each one checked:</strong> tap a watch to see it. If one looks wrong, open "Edit" and change it or move it to the bin.</p>';
		$l[] = '<table cellspacing="0" cellpadding="0" role="presentation" style="width:100%;border-collapse:collapse">';
		foreach ( $rep['added'] as $a ) {
			$p = wc_get_product( $a['id'] );
			if ( ! $p ) {
				continue;
			}
			$thumb = get_the_post_thumbnail_url( $a['id'], 'thumbnail' );
			$price = '<strong>KSh ' . number_format( (float) $p->get_price() ) . '</strong>' . ( $p->is_on_sale() ? ' <span style="color:#888;text-decoration:line-through">KSh ' . number_format( (float) $p->get_regular_price() ) . '</span>' : '' );
			$l[]   = '<tr><td style="' . $cell . ';width:72px">' . ( $thumb ? '<img src="' . esc_url( $thumb ) . '" width="64" height="64" alt="" style="display:block;border-radius:6px">' : '' ) . '</td>'
				. '<td style="' . $cell . '"><a href="' . esc_url( get_permalink( $a['id'] ) ) . '"><strong>' . esc_html( $p->get_name() ) . '</strong></a><br>'
				. $price . ' &middot; Elite Tech KSh ' . number_format( (int) $a['sup_price'] ) . ' (' . esc_html( $a['how'] ?? '' ) . ')<br>'
				. '<span style="color:#1e7a34">&#10003; Check passed (' . (int) ( $a['audit']['passed'] ?? 0 ) . ' of ' . (int) ( $a['audit']['passed'] ?? 0 ) . ': price, title, photo, text, categories, stock, stock check, supplier, no duplicate, page opens)</span> &middot; ' . (int) $a['photos'] . ' photo' . ( 1 === (int) $a['photos'] ? '' : 's' )
				. '<br><a href="' . esc_url( admin_url( 'post.php?post=' . $a['id'] . '&action=edit' ) ) . '">Edit</a> &middot; <a href="' . esc_url( $a['url'] ) . '">Elite Tech page</a></td></tr>';
		}
		$l[] = '</table>';
	}
	if ( $rep['held'] ) {
		$l[] = '<p style="font-size:16px;margin-top:20px"><strong>Not added, please decide (' . count( $rep['held'] ) . '):</strong></p><ul>';
		foreach ( $rep['held'] as $h ) {
			$ours = ! empty( $h['ours'] ) ? ' (<a href="' . esc_url( admin_url( 'post.php?post=' . (int) $h['ours'] . '&action=edit' ) ) . '">ours</a>)' : '';
			$l[]  = '<li style="margin-bottom:6px"><a href="' . esc_url( $h['url'] ) . '">' . esc_html( $h['name'] ?? $h['url'] ) . '</a>' . ( isset( $h['sup_price'] ) ? ', Elite Tech KSh ' . number_format( (int) $h['sup_price'] ) : '' ) . ': ' . esc_html( $h['hold'] ) . $ours . '</li>';
		}
		$l[] = '</ul><p>To add any of these, reply with the ones you want and the price.</p>';
	}
	if ( $left ) {
		$l[] = '<p>' . (int) $left . ' more will be added tomorrow (at most ' . DL_ET_ADD_PER_DAY . ' a day).</p>';
	}
	$subj = 'New Elite Tech watches: ' . count( $rep['added'] ) . ' added to the website' . ( $rep['held'] ? ', ' . count( $rep['held'] ) . ' need you' : '' );
	if ( function_exists( 'dl_send_branded_email' ) ) {
		dl_send_branded_email( get_option( 'admin_email' ), $subj, 'New watches added', implode( "\n", $l ) );
	}
}
