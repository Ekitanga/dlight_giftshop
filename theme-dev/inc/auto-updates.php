<?php
/**
 * Reviewed automatic updates.
 *
 * WordPress offers plugin updates twice a day. Instead of installing every one blindly, each update is
 * reviewed first and installed automatically only when all of these hold:
 *   - it comes from WordPress.org (downloads.wordpress.org) and the plugin is still listed there;
 *   - this server meets its PHP and WordPress requirements;
 *   - it has been out for DL_UPD_WAIT_DAYS days without being withdrawn (bad or hacked releases are
 *     usually pulled within a day or two) - unless Wordfence reports the installed version as
 *     vulnerable, in which case the fix goes in straight away;
 *   - it is not a major new version (x.0), and for WooCommerce and the Facebook plugin, which the shop's
 *     checkout and sales tracking are built on, only bug-fix releases (x.y.Z);
 *   - the plugin is not one that was customised for this shop (DL_UPD_HOLD).
 * Anything held back is emailed once, to be checked by hand. After updates run, the shop is checked
 * (home, product, shop and feed pages load; WooCommerce, M-Pesa, pay on delivery and the Facebook
 * sales tracking are in place) and a report is emailed. WordPress itself puts a plugin back if its
 * update crashes the site.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

const DL_UPD_WAIT_DAYS = 3;

/** Plugins never updated automatically, with the reason. */
const DL_UPD_HOLD = array(
	'woo-m-pesa-payment-gateway/Woocommerce_mpesa.php' => 'Customised for Dlight on 6 Nov 2026 (it no longer blocks the page cache). An update would undo that; check it by hand.',
);

/** Plugins the shop is built on: only bug-fix releases (x.y.Z) install automatically. */
const DL_UPD_CAREFUL = array(
	'woocommerce/woocommerce.php',
	'facebook-for-woocommerce/facebook-for-woocommerce.php',
);

/**
 * When this version was first offered (recorded on first sight).
 *
 * @param string $key Plugin file or "core".
 * @param string $ver Version.
 * @return int Timestamp.
 */
function dl_upd_first_seen( $key, $ver ) {
	$seen = (array) get_site_option( 'dl_upd_seen', array() );
	if ( empty( $seen[ $key ][ $ver ] ) ) {
		$seen[ $key ]         = array( $ver => time() ); // Forget older offers for this plugin.
		update_site_option( 'dl_upd_seen', $seen );
	}
	return (int) $seen[ $key ][ $ver ];
}

/**
 * Plugin slugs whose installed version Wordfence reports as vulnerable.
 *
 * @return string[]
 */
function dl_upd_vulnerable_slugs() {
	static $slugs = null;
	if ( null !== $slugs ) {
		return $slugs;
	}
	$slugs = array();
	global $wpdb;
	$table = class_exists( 'wfDB' ) && method_exists( 'wfDB', 'networkTable' ) ? wfDB::networkTable( 'wfIssues' ) : $wpdb->base_prefix . 'wfissues';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
		return $slugs;
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( "SELECT type, data FROM {$table} WHERE status = 'new' AND type IN ('wfPluginUpgrade','wfPluginVulnerable')" );
	foreach ( (array) $rows as $r ) {
		$d = maybe_unserialize( $r->data );
		if ( is_array( $d ) && ! empty( $d['slug'] ) && ( 'wfPluginVulnerable' === $r->type || ! empty( $d['vulnerable'] ) ) ) {
			$slugs[] = (string) $d['slug'];
		}
	}
	return $slugs;
}

/**
 * Is the plugin still listed on WordPress.org at this version? (Closed or pulled plugins fail.)
 *
 * @param string $slug Slug.
 * @param string $ver  Offered version.
 * @return bool
 */
function dl_upd_wporg_ok( $slug, $ver ) {
	$key  = 'dl_upd_api_' . md5( $slug );
	$info = get_site_transient( $key );
	if ( false === $info ) {
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		$res  = plugins_api( 'plugin_information', array( 'slug' => $slug, 'fields' => array( 'sections' => false, 'versions' => false ) ) );
		$info = is_wp_error( $res ) ? 'error' : (string) $res->version;
		set_site_transient( $key, $info, 6 * HOUR_IN_SECONDS );
	}
	return 'error' !== $info && version_compare( $info, $ver, '>=' );
}

/**
 * Review one plugin update.
 *
 * @param object $item Update offer (plugin, slug, new_version, package, requires, requires_php).
 * @return array{ok:bool,why:string,manual:bool}
 */
function dl_upd_review_plugin( $item ) {
	$file = (string) ( $item->plugin ?? '' );
	$new  = (string) ( $item->new_version ?? '' );
	$no   = static fn( $why, $manual = false ) => array( 'ok' => false, 'why' => $why, 'manual' => $manual );
	if ( isset( DL_UPD_HOLD[ $file ] ) ) {
		return $no( DL_UPD_HOLD[ $file ], true );
	}
	$host = wp_parse_url( (string) ( $item->package ?? '' ), PHP_URL_HOST );
	if ( 'downloads.wordpress.org' !== $host ) {
		return $no( 'Not a WordPress.org download (' . ( $host ? $host : 'no download address' ) . ').', true );
	}
	if ( ! empty( $item->requires_php ) && version_compare( PHP_VERSION, $item->requires_php, '<' ) ) {
		return $no( 'Needs PHP ' . $item->requires_php . '; this server has ' . PHP_VERSION . '.', true );
	}
	if ( ! empty( $item->requires ) && version_compare( get_bloginfo( 'version' ), $item->requires, '<' ) ) {
		return $no( 'Needs WordPress ' . $item->requires . '.', true );
	}
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$plugins = get_plugins();
	$cur     = (string) ( $plugins[ $file ]['Version'] ?? '' );
	$a       = array_map( 'intval', explode( '.', $cur ) + array( 0, 0, 0 ) );
	$b       = array_map( 'intval', explode( '.', $new ) + array( 0, 0, 0 ) );
	if ( $cur && $a[0] !== $b[0] && ! preg_match( '/^\d{8}$/', $cur ) ) {
		return $no( "Major new version ($cur to $new). Check it by hand before updating.", true );
	}
	if ( $cur && in_array( $file, DL_UPD_CAREFUL, true ) && $a[1] !== $b[1] ) {
		return $no( "Feature release ($cur to $new) of a plugin the shop is built on. Check it by hand before updating.", true );
	}
	$slug = (string) ( $item->slug ?? dirname( $file ) );
	if ( in_array( $slug, dl_upd_vulnerable_slugs(), true ) ) {
		return dl_upd_wporg_ok( $slug, $new ) ? array( 'ok' => true, 'why' => 'Security fix: Wordfence reports the installed version as vulnerable.', 'manual' => false ) : $no( 'Wordfence reports a vulnerability, but WordPress.org does not list this version. Check it by hand.', true );
	}
	$age = time() - dl_upd_first_seen( $file, $new );
	if ( $age < DL_UPD_WAIT_DAYS * DAY_IN_SECONDS ) {
		return $no( 'Waiting ' . human_time_diff( time(), time() + DL_UPD_WAIT_DAYS * DAY_IN_SECONDS - $age ) . ' more to be sure this release is not withdrawn.' );
	}
	if ( ! dl_upd_wporg_ok( $slug, $new ) ) {
		return $no( 'WordPress.org no longer lists this version (withdrawn, or the plugin was closed). Check it by hand.', true );
	}
	return array( 'ok' => true, 'why' => 'Passed review.', 'manual' => false );
}

// Every plugin update goes through the review (overrides the per-plugin "auto-update" switches).
add_filter(
	'auto_update_plugin',
	static function ( $update, $item ) {
		if ( ! is_object( $item ) || empty( $item->new_version ) || empty( $item->plugin ) ) {
			return $update; // Plugins screen asking about the setting, not a real update.
		}
		$r = dl_upd_review_plugin( $item );
		$GLOBALS['dl_upd_decisions'][ $item->plugin ] = array( 'name' => $item->slug ?? $item->plugin, 'ver' => $item->new_version ) + $r;
		return $r['ok'];
	},
	999,
	2
);

// Themes: the shop's own theme is not on WordPress.org; never auto-update others.
add_filter( 'auto_update_theme', '__return_false', 999 );

// WordPress itself: security releases (x.y.Z) install at once, as WordPress does by default; new
// versions (x.y) wait DL_UPD_WAIT_DAYS days.
add_filter(
	'allow_major_auto_core_updates',
	static function ( $allow ) {
		$uc  = get_site_transient( 'update_core' );
		$ver = '';
		foreach ( (array) ( $uc->updates ?? array() ) as $o ) {
			if ( in_array( $o->response ?? '', array( 'autoupdate', 'upgrade' ), true ) ) {
				$ver = (string) $o->current;
			}
		}
		return $allow && $ver && ( time() - dl_upd_first_seen( 'core', $ver ) ) >= DL_UPD_WAIT_DAYS * DAY_IN_SECONDS;
	},
	999
);

/**
 * Check the shop still works.
 *
 * @return array{ok:bool,lines:string[]}
 */
function dl_upd_health() {
	$lines = array();
	$ok    = true;
	$add   = static function ( $pass, $text ) use ( &$lines, &$ok ) {
		$lines[] = ( $pass ? '✔ ' : '✘ ' ) . $text;
		$ok      = $ok && $pass;
	};
	$pid  = (int) current( (array) get_posts( array( 'post_type' => 'product', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'orderby' => 'rand', 'meta_key' => '_stock_status', 'meta_value' => 'instock' ) ) );
	$urls = array(
		'Home page'    => home_url( '/' ),
		'Product page' => $pid ? get_permalink( $pid ) : '',
		'Shop page'    => function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : '',
		'Google feed'  => home_url( '/google-feed.xml' ),
	);
	foreach ( $urls as $label => $url ) {
		if ( ! $url ) {
			$add( false, $label . ': no address' );
			continue;
		}
		// Loaded inside the server (the host's edge rate-limits a site calling itself through the internet).
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$r    = wp_remote_get(
			add_query_arg( 'dl_hc', time(), 'http://127.0.0.1' . ( $path ? $path : '/' ) ),
			array( 'timeout' => 45, 'redirection' => 0, 'sslverify' => false, 'user-agent' => 'Mozilla/5.0 (DlightHealthCheck)', 'headers' => array( 'Host' => (string) wp_parse_url( home_url(), PHP_URL_HOST ), 'X-Forwarded-Proto' => 'https' ) )
		);
		$code = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
		$body = is_wp_error( $r ) ? '' : (string) wp_remote_retrieve_body( $r );
		$fine = 200 === $code && false === stripos( $body, 'critical error' ) && ( 'Google feed' === $label ? false !== strpos( $body, '<item>' ) : false !== stripos( $body, '</html>' ) );
		$add( $fine, $label . ' (' . ( $code ? $code : ( is_wp_error( $r ) ? $r->get_error_message() : 'no answer' ) ) . ')' );
	}
	$add( function_exists( 'WC' ), 'WooCommerce running' );
	if ( function_exists( 'WC' ) ) {
		$gw = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		$add( isset( $gw['mpesa'] ) && 'yes' === $gw['mpesa']->enabled, 'M-Pesa payment available' );
		$add( isset( $gw['cod'] ) && 'yes' === $gw['cod']->enabled, 'Pay on delivery available' );
		// Pages the theme overrides (checkout, cart, thank-you...) still match WooCommerce's own versions?
		$dir = get_stylesheet_directory() . '/woocommerce';
		$old = array();
		if ( is_dir( $dir ) ) {
			$ver = static function ( $f ) {
				$h = (string) file_get_contents( $f, false, null, 0, 4096 ); // phpcs:ignore WordPress.WP.AlternativeFunctions
				return preg_match( '/@version\s+([0-9.]+)/i', $h, $m ) ? $m[1] : '';
			};
			$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
			foreach ( $it as $f ) {
				$rel  = ltrim( str_replace( '\\', '/', substr( $f->getPathname(), strlen( $dir ) ) ), '/' );
				$core = WC()->plugin_path() . '/templates/' . $rel;
				if ( '.php' === substr( $rel, -4 ) && file_exists( $core ) ) {
					$a = $ver( $f->getPathname() );
					$b = $ver( $core );
					if ( $a && $b && version_compare( $a, $b, '<' ) ) {
						$old[] = $rel . " ($a, WooCommerce now $b)";
					}
				}
			}
		}
		$add( ! $old, 'Shop page templates match WooCommerce' . ( $old ? ': WooCommerce changed ' . implode( ', ', $old ) . '. Check those pages.' : '' ) );
	}
	$add( class_exists( 'WC_Facebookcommerce_EventsTracker' ) && method_exists( 'WC_Facebookcommerce_EventsTracker', 'inject_purchase_event' ), 'Facebook sales tracking in place (unpaid M-Pesa orders held back)' );
	return array( 'ok' => $ok, 'lines' => $lines );
}

/**
 * Email the update report.
 *
 * @param string $subject Subject.
 * @param array  $blocks  Heading => lines.
 */
function dl_upd_email( $subject, $blocks ) {
	$html = '';
	foreach ( $blocks as $head => $lines ) {
		if ( ! $lines ) {
			continue;
		}
		$html .= '<p><strong>' . esc_html( $head ) . '</strong></p><ul>';
		foreach ( $lines as $l ) {
			$html .= '<li>' . esc_html( $l ) . '</li>';
		}
		$html .= '</ul>';
	}
	$to = get_option( 'admin_email' );
	if ( function_exists( 'dl_send_branded_email' ) ) {
		dl_send_branded_email( $to, $subject, 'Website updates', $html );
	} else {
		wp_mail( $to, $subject, $html, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}
}

// After WordPress's update run: report what was installed and check the shop.
add_action(
	'automatic_updates_complete',
	static function ( $results ) {
		$done = array();
		$fail = array();
		foreach ( (array) $results as $type => $items ) {
			foreach ( (array) $items as $res ) {
				if ( 'translation' === $type ) {
					continue;
				}
				$name = wp_strip_all_tags( (string) ( $res->name ?? ( $res->item->slug ?? $type ) ) );
				$ver  = (string) ( $res->item->new_version ?? ( $res->item->current ?? '' ) );
				$why  = '';
				if ( 'plugin' === $type && ! empty( $res->item->plugin ) && isset( $GLOBALS['dl_upd_decisions'][ $res->item->plugin ] ) ) {
					$why = ' - ' . $GLOBALS['dl_upd_decisions'][ $res->item->plugin ]['why'];
				}
				if ( true === $res->result || ( ! is_wp_error( $res->result ) && $res->result ) ) {
					$done[] = $name . ' ' . $ver . $why;
				} else {
					$fail[] = $name . ' ' . $ver . ': ' . ( is_wp_error( $res->result ) ? $res->result->get_error_message() : 'did not install' );
				}
			}
		}
		if ( ! $done && ! $fail ) {
			return;
		}
		// Check the shop a few minutes later, not in the same moment: right after files are replaced the
		// server can still serve a mix of old and new code for a few seconds, which gave false 500 alarms.
		$p         = (array) get_site_option( 'dl_upd_pending', array() );
		$p['done'] = array_merge( (array) ( $p['done'] ?? array() ), $done );
		$p['fail'] = array_merge( (array) ( $p['fail'] ?? array() ), $fail );
		$p['try']  = 0;
		update_site_option( 'dl_upd_pending', $p );
		wp_clear_scheduled_hook( 'dl_upd_after_check' );
		wp_schedule_single_event( time() + 3 * MINUTE_IN_SECONDS, 'dl_upd_after_check' );
	}
);

// The delayed shop check: on a failure, look again 10 minutes later before raising the alarm.
add_action(
	'dl_upd_after_check',
	static function () {
		$p = (array) get_site_option( 'dl_upd_pending', array() );
		if ( empty( $p['done'] ) && empty( $p['fail'] ) ) {
			return;
		}
		$h = dl_upd_health();
		if ( ! $h['ok'] && (int) ( $p['try'] ?? 0 ) < 1 ) {
			$p['try']        = 1;
			$p['first_fail'] = $h['lines'];
			update_site_option( 'dl_upd_pending', $p );
			wp_schedule_single_event( time() + 10 * MINUTE_IN_SECONDS, 'dl_upd_after_check' );
			return;
		}
		delete_site_option( 'dl_upd_pending' );
		$done  = (array) ( $p['done'] ?? array() );
		$fail  = (array) ( $p['fail'] ?? array() );
		$log   = (array) get_site_option( 'dl_upd_log', array() );
		$log[] = array( 'time' => wp_date( 'Y-m-d H:i' ), 'done' => $done, 'fail' => $fail, 'ok' => $h['ok'] );
		update_site_option( 'dl_upd_log', array_slice( $log, -40 ) );
		$subj = $h['ok'] && ! $fail ? 'Website updated (' . count( $done ) . '): all checks passed' : 'ACTION NEEDED: website update problem';
		dl_upd_email(
			$subj,
			array(
				'Installed after review'                              => $done,
				'Failed (WordPress kept or restored the old version)' => $fail,
				'Shop checks after updating'                          => $h['lines'],
				'First check (10 minutes earlier) also failed'        => $h['ok'] ? array() : (array) ( $p['first_fail'] ?? array() ),
			)
		);
	}
);

// Daily: review waiting updates and email the ones that need a person to check (once per version).
add_action(
	'dl_upd_review_daily',
	static function () {
		wp_update_plugins();
		$up     = get_site_transient( 'update_plugins' );
		$told   = (array) get_site_option( 'dl_upd_told', array() );
		$manual = array();
		$wait   = array();
		foreach ( (array) ( $up->response ?? array() ) as $file => $item ) {
			$item->plugin = $file;
			$r            = dl_upd_review_plugin( $item );
			$label        = ( $item->slug ?? $file ) . ' ' . $item->new_version . ': ' . $r['why'];
			if ( $r['manual'] && ( $told[ $file ] ?? '' ) !== $item->new_version ) {
				$manual[]      = $label;
				$told[ $file ] = $item->new_version;
			} elseif ( ! $r['ok'] && ! $r['manual'] ) {
				$wait[] = $label;
			}
		}
		update_site_option( 'dl_upd_told', $told );
		if ( $manual ) {
			dl_upd_email( 'Website updates waiting for a manual check (' . count( $manual ) . ')', array( 'Not installed automatically - please have these checked' => $manual, 'Also waiting (will install by themselves after review)' => $wait ) );
		}
	}
);
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'dl_upd_review_daily' ) ) {
			$t = new DateTime( 'tomorrow 07:30', wp_timezone() );
			wp_schedule_event( $t->getTimestamp(), 'daily', 'dl_upd_review_daily' );
		}
	}
);
