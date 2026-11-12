<?php
/**
 * Daily Elite Tech stock sync (Casio, Fossil, Seiko, Michael Kors, Naviforce, Curren).
 *
 * Each of our Elite Tech watches is linked to its exact Elite Tech product page (option dl_et_map,
 * product ID => supplier URL, built and photo-checked by hand). Every morning the shop reads Elite
 * Tech's own in-stock lists and sets each linked watch In stock / Out of stock to match. Only the
 * stock status changes. Products with the meta _dl_no_sync = yes are skipped (own stock).
 * Safety: if Elite Tech's lists come back much shorter than usual, nothing is changed.
 * A summary is emailed to the shop when something changes or needs a look.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

const DL_ET_CATS = array(
	'Casio'        => array( 38, 229 ),
	'Fossil'       => array( 21 ),
	'Seiko'        => array( 241, 460, 467, 463 ),
	'Michael Kors' => array( 227 ),
	'Naviforce'    => array( 16 ),
	'Curren'       => array( 357 ),
);

/**
 * Elite Tech's in-stock products, as normalised URL => array( name, brand ). Null if any request failed.
 *
 * @return array|null
 */
function dl_et_fetch_instock() {
	$out = array();
	foreach ( DL_ET_CATS as $brand => $cats ) {
		foreach ( $cats as $cat ) {
			for ( $page = 1; $page <= 10; $page++ ) {
				$r = wp_remote_get(
					add_query_arg( array( 'category' => $cat, 'per_page' => 100, 'page' => $page ), 'https://elitetechh.com/wp-json/wc/store/v1/products' ),
					array( 'timeout' => 40, 'user-agent' => 'Mozilla/5.0 (compatible; DlightStockCheck/1.0)' )
				);
				if ( is_wp_error( $r ) || 200 !== wp_remote_retrieve_response_code( $r ) ) {
					return null;
				}
				$items = json_decode( wp_remote_retrieve_body( $r ), true );
				if ( ! is_array( $items ) ) {
					return null;
				}
				foreach ( $items as $it ) {
					if ( ! empty( $it['permalink'] ) && ! empty( $it['is_in_stock'] ) ) {
						$out[ trailingslashit( strtolower( $it['permalink'] ) ) ] = array( 'name' => html_entity_decode( (string) $it['name'], ENT_QUOTES, 'UTF-8' ), 'brand' => $brand );
					}
				}
				if ( count( $items ) < 100 ) {
					break;
				}
			}
		}
	}
	return $out;
}

/**
 * Run the sync.
 *
 * @param bool $apply False for a dry run (report only).
 * @return array Report.
 */
function dl_et_sync( $apply = true ) {
	$map  = (array) get_option( 'dl_et_map', array() );
	$prev = (int) get_option( 'dl_et_last_count', 0 );
	$list = dl_et_fetch_instock();
	$rep  = array( 'time' => wp_date( 'Y-m-d H:i' ), 'changed' => array(), 'new' => array(), 'missing' => array(), 'note' => '' );
	if ( null === $list ) {
		$rep['note'] = 'Elite Tech could not be reached; nothing changed.';
	} elseif ( count( $list ) < 200 || ( $prev && count( $list ) < 0.6 * $prev ) ) {
		$rep['note'] = 'Elite Tech returned only ' . count( $list ) . ' in-stock products (usually ' . $prev . '); nothing changed, to be safe.';
	} else {
		update_option( 'dl_et_last_count', count( $list ), false );
		$mapped = array();
		// Elite Tech sometimes lists the same watch on more than one page (option dl_et_map_extra:
		// product ID => extra pages, photo-checked). Our watch is in stock while ANY of its pages is.
		$extra = (array) get_option( 'dl_et_map_extra', array() );
		// Pages never counted as "not on our website" (e.g. a watch we sell from another supplier): option dl_et_ignore.
		foreach ( array_keys( (array) get_option( 'dl_et_ignore', array() ) ) as $url ) {
			$mapped[ trailingslashit( strtolower( $url ) ) ] = 1;
		}
		foreach ( $map as $pid => $url ) {
			$urls = array_map( static fn( $u ) => trailingslashit( strtolower( $u ) ), array_merge( array( $url ), (array) ( $extra[ $pid ] ?? array() ) ) );
			foreach ( $urls as $u ) {
				$mapped[ $u ] = 1;
			}
			$p = wc_get_product( (int) $pid );
			if ( ! $p || 'publish' !== $p->get_status() || 'yes' === get_post_meta( (int) $pid, '_dl_no_sync', true ) ) {
				continue;
			}
			$want = array_intersect_key( $list, array_flip( $urls ) ) ? 'instock' : 'outofstock';
			if ( $p->get_stock_status() === $want ) {
				continue;
			}
			if ( $apply ) {
				if ( $p->get_manage_stock() ) {
					$p->set_stock_quantity( 'outofstock' === $want ? 0 : max( 1, (int) $p->get_stock_quantity() ) );
				}
				$p->set_stock_status( $want );
				$p->save();
			}
			$rep['changed'][] = array( 'id' => (int) $pid, 'name' => $p->get_name(), 'to' => $want );
		}
		// In stock at Elite Tech but not on our website: counted by brand every day; first-time ones listed by name.
		$known = (array) get_option( 'dl_et_seen', array() );
		foreach ( $list as $url => $it ) {
			if ( ! empty( $mapped[ $url ] ) || preg_match( '/megir/i', $url ) ) {
				continue;
			}
			$rep['missing'][ $it['brand'] ] = ( $rep['missing'][ $it['brand'] ] ?? 0 ) + 1;
			if ( empty( $known[ $url ] ) ) {
				$rep['new'][]  = array( 'url' => $url, 'name' => $it['name'], 'brand' => $it['brand'] );
				$known[ $url ] = time();
			}
		}
		arsort( $rep['missing'] );
		if ( $apply ) {
			update_option( 'dl_et_seen', $known, false );
		}
	}
	if ( $apply ) {
		$log   = (array) get_option( 'dl_et_sync_log', array() );
		$log[] = array( 'time' => $rep['time'], 'changed' => count( $rep['changed'] ), 'new' => count( $rep['new'] ), 'note' => $rep['note'] );
		update_option( 'dl_et_sync_log', array_slice( $log, -60 ), false );
		// Email when stock changed, something is new, something went wrong, or the "not on our website" count moved.
		$missing = array_sum( $rep['missing'] );
		if ( $rep['changed'] || $rep['new'] || $rep['note'] || (int) get_option( 'dl_et_missing_last', -1 ) !== $missing ) {
			dl_et_sync_email( $rep );
		}
		if ( ! $rep['note'] ) {
			update_option( 'dl_et_missing_last', $missing, false );
		}
	}
	return $rep;
}

/**
 * Email the morning summary to the shop.
 *
 * @param array $rep Report.
 */
function dl_et_sync_email( $rep ) {
	$lines   = array();
	$missing = array_sum( (array) ( $rep['missing'] ?? array() ) );
	if ( ! $rep['note'] ) {
		$lines[] = '<p style="font-size:16px"><strong>Not on our website yet: ' . $missing . ' watch' . ( 1 === $missing ? '' : 'es' ) . ' in stock at Elite Tech.</strong></p>';
		if ( $missing ) {
			$lines[] = '<table cellspacing="0" cellpadding="0" role="presentation" style="margin:0 0 12px">';
			foreach ( $rep['missing'] as $brand => $n ) {
				$lines[] = '<tr><td style="padding:2px 18px 2px 0">' . esc_html( $brand ) . '</td><td style="padding:2px 0;text-align:right"><strong>' . (int) $n . '</strong></td></tr>';
			}
			$lines[] = '</table><p>To add them, ask for "the Elite Tech items not on our website" to be uploaded (photos, descriptions and margin-band prices are done by hand to avoid duplicates and wrong prices).</p>';
		}
	}
	if ( $rep['note'] ) {
		$lines[] = '<p><strong>' . esc_html( $rep['note'] ) . '</strong></p>';
	}
	if ( $rep['changed'] ) {
		$lines[] = '<p><strong>Stock updated to match Elite Tech:</strong></p><ul>';
		foreach ( $rep['changed'] as $c ) {
			$lines[] = '<li>' . esc_html( $c['name'] ) . ' &rarr; <strong>' . ( 'instock' === $c['to'] ? 'In stock' : 'Out of stock' ) . '</strong></li>';
		}
		$lines[] = '</ul>';
	}
	if ( $rep['new'] ) {
		$lines[] = '<p><strong>New since the last check (' . count( $rep['new'] ) . '): new items, or a watch they re-listed under a new address:</strong></p><ul>';
		foreach ( array_slice( $rep['new'], 0, 60 ) as $n ) {
			$lines[] = '<li>' . esc_html( $n['brand'] ?? '' ) . ': <a href="' . esc_url( $n['url'] ) . '">' . esc_html( $n['name'] ) . '</a></li>';
		}
		$lines[] = '</ul>';
	}
	$body = implode( "\n", $lines );
	$to   = get_option( 'admin_email' );
	$subj = 'Elite Tech stock check ' . $rep['time'] . ': ' . count( $rep['changed'] ) . ' updated, ' . count( $rep['new'] ) . ' new' . ( $rep['note'] ? '' : ', ' . $missing . ' not on our website' );
	if ( function_exists( 'dl_send_branded_email' ) ) {
		dl_send_branded_email( $to, $subj, 'Elite Tech stock check', $body );
	} else {
		wp_mail( $to, $subj, $body, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}

// Every day at 05:00 Nairobi time, an hour before Google Merchant Center fetches the feed (06:00),
// so Google never advertises a watch that went out of stock overnight. Afterwards the feed caches
// (WordPress, EasyWP Varnish, Cloudflare) are cleared so the 06:00 fetch gets fresh stock.
add_action(
	'dl_et_sync_daily',
	static function () {
		$rep = dl_et_sync( true );
		if ( $rep['changed'] ) {
			delete_transient( 'dl_google_feed' );
			if ( function_exists( 'dl_feed_xml' ) ) {
				dl_feed_xml(); // Rebuild now, not on Google's request.
			}
			do_action( 'clear_varnish' );
		}
	}
);
add_action(
	'init',
	static function () {
		$at   = '05:00';
		$next = wp_next_scheduled( 'dl_et_sync_daily' );
		if ( $next && wp_date( 'H:i', $next ) !== $at ) {
			wp_clear_scheduled_hook( 'dl_et_sync_daily' );
			$next = false;
		}
		if ( ! $next ) {
			$today = new DateTime( 'today ' . $at, wp_timezone() );
			$tmrw  = new DateTime( 'tomorrow ' . $at, wp_timezone() );
			wp_schedule_event( ( $today->getTimestamp() > time() ? $today : $tmrw )->getTimestamp(), 'daily', 'dl_et_sync_daily' );
		}
	}
);
