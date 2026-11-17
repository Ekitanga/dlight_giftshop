<?php
/**
 * Where each order came from: a clear "Came from" column on the Orders list and a
 * "Sales by source" report (WooCommerce > Sales by source).
 *
 * WooCommerce already records each visitor's source with the order (order attribution:
 * _wc_order_attribution_* meta). This file only reads it and turns the codes into plain words:
 * "fb / paid / 120253…" becomes "Facebook ad", campaign and ad names come from the option
 * dl_ad_labels (ID => name), which the owner fills in on the report page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/** Channel groups: label and badge colour. */
const DL_SRC_GROUPS = array(
	'google_ads' => array( 'Google Ads', '#1a73e8' ),
	'google'     => array( 'Google (free)', '#5f9cf0' ),
	'meta_ads'   => array( 'Meta ads', '#7b3fe4' ),
	'meta'       => array( 'Facebook / Instagram (free)', '#b18cf0' ),
	'tiktok_ads' => array( 'TikTok ads', '#111111' ),
	'tiktok'     => array( 'TikTok (free)', '#69c9d0' ),
	'whatsapp'   => array( 'WhatsApp', '#1fa855' ),
	'direct'     => array( 'Direct / came back', '#6b7280' ),
	'other'      => array( 'Other websites', '#b7791f' ),
	'staff'      => array( 'Added by staff', '#374151' ),
	'unknown'    => array( 'Unknown', '#9ca3af' ),
);

/**
 * Plain-words source of an order.
 *
 * @param WC_Order $o Order.
 * @return array{group:string,label:string,campaign:string,adset:string,ad:string,landing:string}
 */
function dl_order_source( $o ) {
	$m    = static fn( $k ) => (string) $o->get_meta( '_wc_order_attribution_' . $k );
	$type = strtolower( $m( 'source_type' ) );
	$src  = strtolower( urldecode( $m( 'utm_source' ) ) );
	$med  = strtolower( $m( 'utm_medium' ) );
	$hay  = strtolower( $m( 'referrer' ) . ' ' . $m( 'session_entry' ) );
	$r    = array( 'group' => 'unknown', 'label' => 'Unknown', 'campaign' => '', 'adset' => '', 'ad' => '', 'landing' => $m( 'session_entry' ) );
	$paid = in_array( $med, array( 'paid', 'cpc', 'ppc', 'paidsocial', 'paid_social' ), true );

	if ( 'admin' === $type || ( '' === $type && 'admin' === $o->get_created_via() ) ) {
		return array_merge( $r, array( 'group' => 'staff', 'label' => 'Added by staff' ) );
	}
	if ( preg_match( '/[?&](gclid|gbraid|wbraid|gad_source)=/', $hay ) || ( false !== strpos( $src, 'google' ) && $paid ) ) {
		$r = array_merge( $r, array( 'group' => 'google_ads', 'label' => 'Google Ads' ) );
		$camp          = $m( 'utm_campaign' );
		$r['campaign'] = in_array( strtolower( $camp ), array( '', '(none)', '(not set)', 'google_cpc' ), true ) ? '' : $camp; // Auto-tagged clicks carry no campaign name.
		// Auto-tagged clicks carry no campaign name, but Google adds the campaign ID to the landing address.
		if ( '' === $r['campaign'] && preg_match( '/[?&]gad_campaignid=(\d+)/', $hay, $gm ) ) {
			$r['campaign'] = $gm[1];
		}
		return $r;
	}
	// TikTok ads add their click ID (ttclid) to the landing address; ad links may also be tagged utm_source=tiktok.
	if ( preg_match( '/[?&]ttclid=/', $hay ) || ( false !== strpos( $src, 'tiktok' ) && $paid ) ) {
		return array_merge( $r, array( 'group' => 'tiktok_ads', 'label' => 'TikTok ad', 'campaign' => $m( 'utm_campaign' ) && false === strpos( $m( 'utm_campaign' ), '__' ) ? urldecode( $m( 'utm_campaign' ) ) : $m( 'utm_id' ), 'adset' => $m( 'utm_term' ), 'ad' => $m( 'utm_content' ) ) );
	}
	if ( false !== strpos( $src, 'tiktok' ) || false !== strpos( $hay, 'tiktok.com' ) ) {
		return array_merge( $r, array( 'group' => 'tiktok', 'label' => 'TikTok (not an ad)' ) );
	}
	$meta_ad = array(
		'fb'        => 'Facebook ad',
		'facebook'  => 'Facebook ad',
		'ig'        => 'Instagram ad',
		'instagram' => 'Instagram ad',
		'an'        => 'Meta ad in other apps',
		'msg'       => 'Messenger ad',
		'messenger' => 'Messenger ad',
		'threads'   => 'Threads ad',
	);
	if ( isset( $meta_ad[ $src ] ) && $paid ) {
		return array_merge( $r, array( 'group' => 'meta_ads', 'label' => $meta_ad[ $src ], 'campaign' => $m( 'utm_campaign' ) ? $m( 'utm_campaign' ) : $m( 'utm_id' ), 'adset' => $m( 'utm_term' ), 'ad' => $m( 'utm_content' ) ) );
	}
	// Older Meta ad links carried only the IDs (no source): the campaign ID plus Facebook's click ID or referrer.
	if ( '' === $src && $m( 'utm_id' ) && preg_match( '/fbclid=|facebook\.com|instagram\.com/', $hay ) ) {
		return array_merge( $r, array( 'group' => 'meta_ads', 'label' => false !== strpos( $hay, 'instagram' ) ? 'Instagram ad' : 'Facebook ad', 'campaign' => $m( 'utm_id' ), 'adset' => $m( 'utm_term' ), 'ad' => $m( 'utm_content' ) ) );
	}
	if ( false !== strpos( $src, 'catalog' ) || ( false !== strpos( $src, 'meta' ) && $paid ) ) {
		return array_merge( $r, array( 'group' => 'meta_ads', 'label' => 'Meta catalogue ad', 'campaign' => urldecode( $m( 'utm_campaign' ) ) ) );
	}
	if ( in_array( $src, array( 'ig', 'instagram' ), true ) || false !== strpos( $src, 'instagram' ) ) {
		return array_merge( $r, array( 'group' => 'meta', 'label' => 'link_in_bio' === $m( 'utm_content' ) ? 'Instagram bio link' : 'Instagram (not an ad)' ) );
	}
	if ( in_array( $src, array( 'fb', 'facebook' ), true ) || false !== strpos( $src, 'facebook' ) ) {
		return array_merge( $r, array( 'group' => 'meta', 'label' => 'Facebook (not an ad)' ) );
	}
	if ( false !== strpos( $src, 'whatsapp' ) || false !== strpos( $hay, 'whatsapp' ) || false !== strpos( $hay, 'wa.me' ) ) {
		return array_merge( $r, array( 'group' => 'whatsapp', 'label' => 'WhatsApp link' ) );
	}
	if ( 'organic' === $type || ( 'referral' === $type && false !== strpos( $src, 'google' ) ) ) {
		$se = false !== strpos( $src, 'google' ) ? ( false !== strpos( $hay, 'srsltid=' ) ? 'Google Shopping (free)' : 'Google search (free)' ) : ucfirst( $src ) . ' search (free)';
		return array_merge( $r, array( 'group' => false !== strpos( $src, 'google' ) ? 'google' : 'other', 'label' => $se ) );
	}
	if ( 'referral' === $type ) {
		if ( false !== strpos( $src, 'dlightgiftshop.co.ke' ) ) {
			return array_merge( $r, array( 'group' => 'other', 'label' => 'Old .co.ke website' ) );
		}
		return array_merge( $r, array( 'group' => 'other', 'label' => 'Website: ' . $src ) );
	}
	if ( 'typein' === $type ) {
		// Facebook's in-app browser often hides the ad details; its click ID still shows where they came from.
		if ( false !== strpos( $hay, 'fbclid=' ) ) {
			return array_merge( $r, array( 'group' => 'meta', 'label' => 'Facebook / Instagram (no ad details)' ) );
		}
		return array_merge( $r, array( 'group' => 'direct', 'label' => 'Direct / came back later' ) );
	}
	if ( 'utm' === $type && $src ) {
		return array_merge( $r, array( 'group' => 'other', 'label' => 'Tagged link: ' . $src ) );
	}
	return $r;
}

/**
 * Name for a campaign / ad set / ad ID (owner-entered), or a short form of the ID.
 *
 * @param string $id ID.
 * @return string
 */
function dl_ad_label( $id ) {
	if ( '' === $id ) {
		return '';
	}
	$labels = (array) get_option( 'dl_ad_labels', array() );
	if ( ! empty( $labels[ $id ] ) ) {
		return $labels[ $id ];
	}
	return ctype_digit( $id ) ? 'ID …' . substr( $id, -6 ) : urldecode( $id );
}

/**
 * Badge HTML.
 *
 * @param array $s Source.
 * @return string
 */
function dl_src_badge( $s ) {
	$c = DL_SRC_GROUPS[ $s['group'] ][1] ?? '#9ca3af';
	return '<span class="dl-src" style="--c:' . esc_attr( $c ) . '">' . esc_html( $s['label'] ) . '</span>';
}

/** Orders that count as sales (not unpaid, failed or cancelled). */
function dl_src_is_sale( $o ) {
	return ! $o->has_status( array( 'pending', 'failed', 'cancelled', 'refunded', 'checkout-draft' ) );
}

// ---- Orders list: "Came from" column (classic and HPOS order screens) ----
$dl_src_cols = static function ( $cols ) {
	unset( $cols['origin'] ); // WooCommerce's own short "Origin" column; ours replaces it.
	$out = array();
	foreach ( $cols as $k => $v ) {
		$out[ $k ] = $v;
		if ( 'order_status' === $k ) {
			$out['dl_source'] = 'Came from';
		}
	}
	if ( ! isset( $out['dl_source'] ) ) {
		$out['dl_source'] = 'Came from';
	}
	return $out;
};
add_filter( 'manage_edit-shop_order_columns', $dl_src_cols, 30 );
add_filter( 'manage_woocommerce_page_wc-orders_columns', $dl_src_cols, 30 );

$dl_src_cell = static function ( $col, $order ) {
	if ( 'dl_source' !== $col ) {
		return;
	}
	$o = $order instanceof WC_Order ? $order : wc_get_order( $order );
	if ( ! $o ) {
		return;
	}
	$s = dl_order_source( $o );
	echo dl_src_badge( $s ); // phpcs:ignore WordPress.Security.EscapeOutput
	$sub = array_filter( array( dl_ad_label( $s['campaign'] ), dl_ad_label( $s['ad'] ) ) );
	if ( $sub ) {
		echo '<div class="dl-src-sub">' . esc_html( implode( ' › ', $sub ) ) . '</div>';
	}
};
add_action( 'manage_shop_order_posts_custom_column', $dl_src_cell, 10, 2 );
add_action( 'manage_woocommerce_page_wc-orders_custom_column', $dl_src_cell, 10, 2 );

// ---- Report page ----
add_action(
	'admin_menu',
	static function () {
		add_submenu_page( 'woocommerce', 'Sales by source', 'Sales by source', 'manage_woocommerce', 'dl-sources', 'dl_sources_page' );
	},
	60
);

add_action(
	'admin_post_dl_ad_labels',
	static function () {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Not allowed.' );
		}
		check_admin_referer( 'dl_ad_labels' );
		$labels = (array) get_option( 'dl_ad_labels', array() );
		foreach ( (array) ( $_POST['dl_label'] ?? array() ) as $id => $name ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$id   = sanitize_text_field( wp_unslash( $id ) );
			$name = sanitize_text_field( wp_unslash( $name ) );
			if ( '' === $name ) {
				unset( $labels[ $id ] );
			} else {
				$labels[ $id ] = mb_substr( $name, 0, 80 );
			}
		}
		update_option( 'dl_ad_labels', $labels, false );
		wp_safe_redirect( add_query_arg( 'saved', 1, wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=dl-sources' ) ) );
		exit;
	}
);

/**
 * Date range from the request: [from timestamp, to timestamp, label, key].
 *
 * @return array
 */
function dl_sources_range() {
	$tz  = wp_timezone();
	$key = sanitize_key( $_GET['range'] ?? '30' ); // phpcs:ignore WordPress.Security.NonceVerification
	$now = new DateTime( 'now', $tz );
	switch ( $key ) {
		case 'today':
			return array( ( new DateTime( 'today', $tz ) )->getTimestamp(), $now->getTimestamp(), 'Today', $key );
		case 'yesterday':
			return array( ( new DateTime( 'yesterday', $tz ) )->getTimestamp(), ( new DateTime( 'today', $tz ) )->getTimestamp() - 1, 'Yesterday', $key );
		case '7':
			return array( ( new DateTime( '-6 days today', $tz ) )->getTimestamp(), $now->getTimestamp(), 'Last 7 days', $key );
		case 'month':
			return array( ( new DateTime( 'first day of this month 00:00', $tz ) )->getTimestamp(), $now->getTimestamp(), 'This month', $key );
		case 'lastmonth':
			return array( ( new DateTime( 'first day of last month 00:00', $tz ) )->getTimestamp(), ( new DateTime( 'first day of this month 00:00', $tz ) )->getTimestamp() - 1, 'Last month', $key );
		case '90':
			return array( ( new DateTime( '-89 days today', $tz ) )->getTimestamp(), $now->getTimestamp(), 'Last 90 days', $key );
		case 'custom':
			$f = sanitize_text_field( wp_unslash( $_GET['from'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			$t = sanitize_text_field( wp_unslash( $_GET['to'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $f ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $t ) ) {
				return array( ( new DateTime( $f . ' 00:00', $tz ) )->getTimestamp(), ( new DateTime( $t . ' 23:59:59', $tz ) )->getTimestamp(), $f . ' to ' . $t, $key );
			}
			// Fall through to the default.
		default:
			return array( ( new DateTime( '-29 days today', $tz ) )->getTimestamp(), $now->getTimestamp(), 'Last 30 days', '30' );
	}
}

/**
 * Add an order to a totals row.
 *
 * @param array    $row Row (by reference).
 * @param WC_Order $o   Order.
 */
function dl_src_add( &$row, $o ) {
	$row  = (array) $row;
	$row += array( 'orders' => 0, 'sales' => 0.0, 'delivered' => 0, 'lost' => 0 );
	if ( dl_src_is_sale( $o ) ) {
		++$row['orders'];
		$row['sales'] += (float) $o->get_total();
		if ( $o->has_status( 'completed' ) ) {
			++$row['delivered'];
		}
	} else {
		++$row['lost'];
	}
}

/**
 * Totals cells.
 *
 * @param array $row Row.
 * @return string
 */
function dl_src_cells( $row ) {
	$avg = $row['orders'] ? $row['sales'] / $row['orders'] : 0;
	return '<td class="n">' . (int) $row['orders'] . '</td><td class="n"><strong>' . esc_html( number_format( $row['sales'] ) ) . '</strong></td><td class="n">' . esc_html( number_format( $avg ) ) . '</td><td class="n">' . (int) $row['delivered'] . '</td><td class="n muted">' . (int) $row['lost'] . '</td>';
}

/** The report page. */
function dl_sources_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	list( $from, $to, $range_label, $key ) = dl_sources_range();
	$orders = wc_get_orders(
		array(
			'limit'        => -1,
			'type'         => 'shop_order',
			'status'       => array_keys( wc_get_order_statuses() ),
			'date_created' => $from . '...' . $to,
			'orderby'      => 'date',
			'order'        => 'DESC',
		)
	);
	$by_label = array();
	$by_group = array();
	$camps    = array();
	$ids      = array();
	$all      = array();
	$rows     = array();
	foreach ( $orders as $o ) {
		$s = dl_order_source( $o );
		dl_src_add( $all, $o );
		dl_src_add( $by_group[ $s['group'] ], $o );
		$by_label[ $s['group'] . '|' . $s['label'] ] = $by_label[ $s['group'] . '|' . $s['label'] ] ?? array();
		dl_src_add( $by_label[ $s['group'] . '|' . $s['label'] ], $o );
		if ( in_array( $s['group'], array( 'meta_ads', 'google_ads', 'tiktok_ads' ), true ) ) {
			$ck = $s['group'] . '|' . $s['campaign'];
			$camps[ $ck ]['row'] = $camps[ $ck ]['row'] ?? array();
			dl_src_add( $camps[ $ck ]['row'], $o );
			$ak = $s['adset'] . '|' . $s['ad'];
			$camps[ $ck ]['ads'][ $ak ] = $camps[ $ck ]['ads'][ $ak ] ?? array();
			dl_src_add( $camps[ $ck ]['ads'][ $ak ], $o );
			foreach ( array( $s['campaign'], $s['adset'], $s['ad'] ) as $id ) {
				if ( '' !== $id ) {
					$ids[ $id ] = 1;
				}
			}
		}
		$rows[] = array( $o, $s );
	}
	$by_sales = static fn( $a, $b ) => $b['sales'] <=> $a['sales'];
	uasort( $by_group, $by_sales );
	uasort( $by_label, $by_sales );
	uasort( $camps, static fn( $a, $b ) => $b['row']['sales'] <=> $a['row']['sales'] );
	$base   = admin_url( 'admin.php?page=dl-sources' );
	$ranges = array( 'today' => 'Today', 'yesterday' => 'Yesterday', '7' => 'Last 7 days', '30' => 'Last 30 days', 'month' => 'This month', 'lastmonth' => 'Last month', '90' => 'Last 90 days' );
	$head   = '<tr><th></th><th class="n">Orders</th><th class="n">Sales (KSh)</th><th class="n">Avg order</th><th class="n">Delivered</th><th class="n" title="Unpaid M-Pesa, failed, cancelled or refunded">Not completed</th></tr>';
	?>
	<div class="wrap dl-srcpage">
		<h1>Sales by source</h1>
		<p class="dl-src-intro">Where your website orders came from. <strong>Orders</strong> and <strong>Sales</strong> count real orders only; unpaid M-Pesa, failed and cancelled orders are under <em>Not completed</em>. Orders taken on WhatsApp or in the shop are not here unless staff enter them on the website.</p>
		<div class="dl-src-ranges">
			<?php foreach ( $ranges as $k => $l ) : ?>
				<a class="button<?php echo $k === $key ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'range', $k, $base ) ); ?>"><?php echo esc_html( $l ); ?></a>
			<?php endforeach; ?>
			<form method="get" class="dl-src-custom">
				<input type="hidden" name="page" value="dl-sources"><input type="hidden" name="range" value="custom">
				<input type="date" name="from" required value="<?php echo esc_attr( wp_date( 'Y-m-d', $from ) ); ?>"> to
				<input type="date" name="to" required value="<?php echo esc_attr( wp_date( 'Y-m-d', $to ) ); ?>">
				<button class="button">Show</button>
			</form>
		</div>
		<?php if ( ! empty( $_GET['saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification ?>
			<div class="notice notice-success"><p>Names saved.</p></div>
		<?php endif; ?>

		<h2><?php echo esc_html( $range_label ); ?>: <?php echo (int) ( $all['orders'] ?? 0 ); ?> orders, KSh <?php echo esc_html( number_format( $all['sales'] ?? 0 ) ); ?></h2>

		<div class="dl-src-cards">
			<?php foreach ( $by_group as $g => $row ) : ?>
				<div class="dl-src-card" style="--c:<?php echo esc_attr( DL_SRC_GROUPS[ $g ][1] ); ?>">
					<div class="t"><?php echo esc_html( DL_SRC_GROUPS[ $g ][0] ); ?></div>
					<div class="v">KSh <?php echo esc_html( number_format( $row['sales'] ) ); ?></div>
					<div class="s"><?php echo (int) $row['orders']; ?> order<?php echo 1 === (int) $row['orders'] ? '' : 's'; ?><?php echo $row['lost'] ? ' · ' . (int) $row['lost'] . ' not completed' : ''; ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<h2>By source</h2>
		<table class="widefat striped dl-src-table"><thead><?php echo $head; // phpcs:ignore ?></thead><tbody>
			<?php foreach ( $by_label as $gl => $row ) : list( $g, $l ) = explode( '|', $gl, 2 ); ?>
				<tr><td><?php echo dl_src_badge( array( 'group' => $g, 'label' => $l ) ); // phpcs:ignore ?></td><?php echo dl_src_cells( $row ); // phpcs:ignore ?></tr>
			<?php endforeach; ?>
		</tbody></table>

		<h2>Ad campaigns and ads</h2>
		<?php if ( ! $camps ) : ?>
			<p>No orders from ads in this period.</p>
		<?php else : ?>
			<p class="description">Meta sends campaign, ad set and ad <strong>IDs</strong>. Give them names in the box at the bottom (copy the names from Meta Ads Manager) and they show here and on the Orders list.</p>
			<table class="widefat dl-src-table"><thead><?php echo $head; // phpcs:ignore ?></thead><tbody>
				<?php foreach ( $camps as $ck => $c ) : list( $g, $cid ) = explode( '|', $ck, 2 ); ?>
					<tr class="camp"><td><?php echo dl_src_badge( array( 'group' => $g, 'label' => 'google_ads' === $g ? 'Google Ads' : ( 'tiktok_ads' === $g ? 'TikTok' : 'Meta' ) ) ); // phpcs:ignore ?> <strong><?php echo esc_html( '' !== $cid ? dl_ad_label( $cid ) : ( 'google_ads' === $g ? 'All Google campaigns' : ( 'tiktok_ads' === $g ? 'All TikTok campaigns' : 'Campaign not recorded' ) ) ); ?></strong></td><?php echo dl_src_cells( $c['row'] ); // phpcs:ignore ?></tr>
					<?php
					uasort( $c['ads'], $by_sales );
					foreach ( $c['ads'] as $ak => $row ) :
						list( $adset, $ad ) = explode( '|', $ak, 2 );
						if ( '' === $adset && '' === $ad ) {
							continue;
						}
						?>
						<tr class="ad"><td><span class="lvl">Ad set</span> <?php echo esc_html( dl_ad_label( $adset ) ); ?> &nbsp;›&nbsp; <span class="lvl">Ad</span> <?php echo esc_html( dl_ad_label( $ad ) ); ?></td><?php echo dl_src_cells( $row ); // phpcs:ignore ?></tr>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</tbody></table>
		<?php endif; ?>

		<h2>Orders in this period</h2>
		<table class="widefat striped dl-src-table dl-src-orders"><thead><tr><th>Order</th><th>Date</th><th>Status</th><th class="n">Total (KSh)</th><th>Came from</th><th>Campaign › Ad</th><th>First page they saw</th></tr></thead><tbody>
			<?php
			foreach ( $rows as $pair ) :
				list( $o, $s ) = $pair;
				$land = (string) wp_parse_url( $s['landing'], PHP_URL_PATH );
				$land = $land ? ( preg_match( '#/product/([^/]+)#', $land, $pm ) ? ucwords( str_replace( '-', ' ', $pm[1] ) ) : $land ) : '';
				?>
				<tr>
					<td><a href="<?php echo esc_url( $o->get_edit_order_url() ); ?>">#<?php echo esc_html( $o->get_order_number() ); ?></a> <?php echo esc_html( $o->get_formatted_billing_full_name() ); ?></td>
					<td><?php echo esc_html( $o->get_date_created() ? $o->get_date_created()->date_i18n( 'j M, H:i' ) : '' ); ?></td>
					<td><?php echo esc_html( wc_get_order_status_name( $o->get_status() ) ); ?></td>
					<td class="n"><?php echo esc_html( number_format( (float) $o->get_total() ) ); ?></td>
					<td><?php echo dl_src_badge( $s ); // phpcs:ignore ?></td>
					<td><?php echo esc_html( implode( ' › ', array_filter( array( dl_ad_label( $s['campaign'] ), dl_ad_label( $s['ad'] ) ) ) ) ); ?></td>
					<td class="land"><?php echo esc_html( mb_substr( $land, 0, 60 ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody></table>

		<?php if ( $ids ) : $labels = (array) get_option( 'dl_ad_labels', array() ); ?>
			<h2>Name your campaigns and ads</h2>
			<p class="description">In Meta Ads Manager, turn on the <em>Campaign ID</em>, <em>Ad set ID</em> and <em>Ad ID</em> columns, find each ID below, and type its name. Leave a box empty to show the ID.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="dl-src-names">
				<input type="hidden" name="action" value="dl_ad_labels">
				<?php wp_nonce_field( 'dl_ad_labels' ); ?>
				<table class="widefat striped"><thead><tr><th>ID</th><th>Name</th></tr></thead><tbody>
					<?php foreach ( array_keys( $ids ) as $id ) : ?>
						<tr><td><code><?php echo esc_html( $id ); ?></code></td><td><input type="text" class="regular-text" name="dl_label[<?php echo esc_attr( $id ); ?>]" value="<?php echo esc_attr( $labels[ $id ] ?? '' ); ?>" placeholder="e.g. November watches – video ad"></td></tr>
					<?php endforeach; ?>
				</tbody></table>
				<p><button class="button button-primary">Save names</button></p>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

// ---- Styles for the Orders list and the report ----
add_action(
	'admin_head',
	static function () {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! preg_match( '/shop_order|wc-orders|dl-sources/', (string) $screen->id ) ) {
			return;
		}
		?>
		<style>
		.dl-src{display:inline-block;padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;line-height:1.6;color:#fff;background:var(--c);white-space:nowrap}
		.dl-src-sub{font-size:11px;color:#50575e;margin-top:3px}
		.column-dl_source{width:180px}
		.dl-srcpage .dl-src-intro{max-width:820px;font-size:14px}
		.dl-src-ranges{display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin:12px 0}
		.dl-src-custom{display:flex;gap:6px;align-items:center;margin-left:8px}
		.dl-src-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:12px;margin:12px 0 24px}
		.dl-src-card{background:#fff;border:1px solid #dcdcde;border-top:4px solid var(--c);border-radius:6px;padding:12px 14px}
		.dl-src-card .t{font-weight:600;color:#1d2327}
		.dl-src-card .v{font-size:22px;font-weight:700;margin:6px 0 2px}
		.dl-src-card .s{color:#50575e}
		.dl-src-table{max-width:1100px;margin-bottom:24px}
		.dl-src-table td,.dl-src-table th{vertical-align:middle}
		.dl-src-table .n{text-align:right;white-space:nowrap}
		.dl-src-table .muted{color:#8c8f94}
		.dl-src-table tr.camp td{background:#f6f7f7;border-top:2px solid #dcdcde}
		.dl-src-table tr.ad td:first-child{padding-left:28px}
		.dl-src-table .lvl{font-size:11px;color:#8c8f94;text-transform:uppercase}
		.dl-src-orders .land{color:#50575e}
		.dl-src-names{max-width:820px}
		</style>
		<?php
	}
);
