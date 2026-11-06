<?php
/**
 * Fast product images. Makes small WebP copies of each product photo on the store's own
 * server, a few at a time in the background, and serves them to shoppers. Any image without
 * a copy keeps using the original, so nothing breaks while the work is in progress.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

define( 'DL_IMG_DIR', 'dl-img' );
define( 'DL_IMG_WIDTHS', array( 300, 450, 800 ) );
define( 'DL_IMG_QUALITY', 76 );
define( 'DL_IMG_BATCH', 8 );

/**
 * Attachments that already have WebP copies.
 *
 * @return array<int,int> Attachment ID to 1.
 */
function dl_img_done() {
	static $done = null;
	if ( null === $done ) {
		$done = (array) get_option( 'dl_img_done', array() );
	}
	return $done;
}

/**
 * Public URL of a WebP copy.
 *
 * @param int $id    Attachment ID.
 * @param int $width Width.
 * @return string
 */
function dl_img_url( $id, $width ) {
	$uploads = wp_get_upload_dir();
	$done    = dl_img_done();
	// The site's own scheme (https): the uploads address can be saved as http.
	$base = set_url_scheme( $uploads['baseurl'], (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) );
	return $base . '/' . DL_IMG_DIR . '/' . dl_img_file( $id, $width, (int) ( $done[ $id ] ?? 1 ) );
}

/**
 * File name of a WebP copy. An edited image gets a new name (with the time of the edit), because
 * the host's cache ignores "?v=" and would keep serving the old picture under the old name.
 *
 * @param int $id      Attachment ID.
 * @param int $width   Width.
 * @param int $version 1 for the first copy, or the time of the last edit.
 * @return string
 */
function dl_img_file( $id, $width, $version = 1 ) {
	return (int) $id . '-' . (int) $width . ( $version > 1 ? '-' . (int) $version : '' ) . '.webp';
}

/**
 * True while an email is being written. Email apps have patchy WebP support.
 *
 * @return bool
 */
function dl_img_in_email() {
	return did_action( 'woocommerce_email_header' ) > did_action( 'woocommerce_email_footer' );
}

/**
 * Point image URLs at the WebP copy when one exists.
 *
 * @param array|false  $image Image data.
 * @param int          $id    Attachment ID.
 * @param string|int[] $size  Requested size.
 * @return array|false
 */
function dl_img_src( $image, $id, $size ) {
	if ( ! $image || is_admin() || is_feed() || dl_img_in_email() ) {
		return $image;
	}
	$done = dl_img_done();
	if ( empty( $done[ $id ] ) ) {
		return $image;
	}
	$map = array(
		'woocommerce_gallery_thumbnail' => 300,
		'thumbnail'                     => 300,
		'medium'                        => 450,
		'woocommerce_thumbnail'         => 450,
		'woocommerce_single'            => 800,
		'large'                         => 800,
		'full'                          => 800,
	);
	$key = is_string( $size ) ? $size : '';
	if ( isset( $map[ $key ] ) ) {
		$image[0] = dl_img_url( $id, $map[ $key ] );
	}
	return $image;
}
add_filter( 'wp_get_attachment_image_src', 'dl_img_src', 20, 3 );

// Offer the browser all three sizes, so phones download the small one.
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr, $attachment ) {
		$done = dl_img_done();
		if ( ! empty( $done[ $attachment->ID ] ) && ! is_admin() && ! dl_img_in_email() ) {
			$set = array();
			foreach ( DL_IMG_WIDTHS as $width ) {
				$set[] = dl_img_url( $attachment->ID, $width ) . ' ' . $width . 'w';
			}
			$attr['srcset'] = implode( ', ', $set );
		}
		return $attr;
	},
	20,
	2
);

/**
 * Product photos still waiting for WebP copies.
 *
 * @param int $limit Maximum to return.
 * @return int[]
 */
function dl_img_pending( $limit ) {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		"SELECT pm.meta_key, pm.meta_value FROM {$wpdb->postmeta} pm
		JOIN {$wpdb->posts} p ON p.ID = pm.post_id AND p.post_type = 'product' AND p.post_status = 'publish'
		WHERE pm.meta_key IN ('_thumbnail_id','_product_image_gallery') AND pm.meta_value <> ''"
	);
	$ids = array();
	foreach ( $rows as $row ) {
		foreach ( explode( ',', $row->meta_value ) as $id ) {
			$ids[ (int) $id ] = 1;
		}
	}
	unset( $ids[0] );
	// A failed image is retried after a day, so a passing server problem does not skip it for good.
	$failed = array_filter(
		(array) get_option( 'dl_img_failed', array() ),
		static fn( $when ) => $when > time() - DAY_IN_SECONDS
	);
	$done   = (array) get_option( 'dl_img_done', array() );
	return array_slice( array_keys( array_diff_key( $ids, $done, $failed ) ), 0, $limit );
}

/**
 * Make WebP copies of one image.
 *
 * @param int $id Attachment ID.
 * @return bool
 */
function dl_img_make( $id, $version = 1 ) {
	$file = get_attached_file( $id );
	if ( ! $file || ! file_exists( $file ) ) {
		return false;
	}
	$uploads = wp_get_upload_dir();
	$dir     = $uploads['basedir'] . '/' . DL_IMG_DIR;
	wp_mkdir_p( $dir );

	foreach ( DL_IMG_WIDTHS as $width ) {
		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->set_quality( DL_IMG_QUALITY );
		$size = $editor->get_size();
		if ( $size['width'] > $width ) {
			$resized = $editor->resize( $width, null, false );
			if ( is_wp_error( $resized ) ) {
				return false;
			}
		}
		$saved = $editor->save( $dir . '/' . dl_img_file( $id, $width, $version ), 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			return false;
		}
	}
	return true;
}

/**
 * Process a small batch. Safe to run repeatedly, and stops if the server cannot make WebP.
 *
 * @param int $limit Images to process.
 * @return int Images still waiting.
 */
function dl_img_batch( $limit = DL_IMG_BATCH ) {
	if ( ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return 0;
	}
	$done   = (array) get_option( 'dl_img_done', array() );
	$failed = (array) get_option( 'dl_img_failed', array() );
	foreach ( dl_img_pending( $limit ) as $id ) {
		if ( dl_img_make( $id ) ) {
			$done[ $id ] = 1;
			unset( $failed[ $id ] );
		} else {
			$failed[ $id ] = time();
		}
		update_option( 'dl_img_done', $done, false );
		update_option( 'dl_img_failed', $failed, false );
	}
	return count( dl_img_pending( 1 ) );
}

/**
 * Convert an image as soon as it is uploaded or edited. If this fails or times out, the
 * background job below picks it up, so the person uploading never sees an error.
 *
 * @param array $data Attachment metadata.
 * @param int   $id   Attachment ID.
 * @return array
 */
function dl_img_on_save( $data, $id ) {
	static $seen = array();
	if ( isset( $seen[ $id ] ) || ! wp_attachment_is_image( $id ) || ! is_array( $data ) || empty( $data['file'] ) ) {
		return $data;
	}
	$seen[ $id ] = 1;
	if ( 'image/gif' === get_post_mime_type( $id ) || ! wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		return $data;
	}
	$done    = (array) get_option( 'dl_img_done', array() );
	$version = time();
	unset( $done[ $id ] );
	if ( dl_img_make( $id, $version ) ) {
		$done[ $id ] = $version;
		// Remove the copies of the previous version.
		$dir = wp_get_upload_dir()['basedir'] . '/' . DL_IMG_DIR . '/';
		foreach ( (array) glob( $dir . (int) $id . '-*.webp' ) as $old ) {
			if ( false === strpos( basename( $old ), '-' . $version . '.webp' ) ) {
				wp_delete_file( $old );
			}
		}
	}
	update_option( 'dl_img_done', $done, false );
	return $data;
}
add_filter( 'wp_update_attachment_metadata', 'dl_img_on_save', 20, 2 );

// Deleting an image also removes its copies.
add_action(
	'delete_attachment',
	static function ( $id ) {
		$uploads = wp_get_upload_dir();
		foreach ( (array) glob( $uploads['basedir'] . '/' . DL_IMG_DIR . '/' . (int) $id . '-*.webp' ) as $file ) {
			wp_delete_file( $file );
		}
		$done = (array) get_option( 'dl_img_done', array() );
		unset( $done[ $id ] );
		update_option( 'dl_img_done', $done, false );
	}
);

add_filter(
	'cron_schedules',
	static function ( $schedules ) {
		$schedules['dl_five_minutes'] = array(
			'interval' => 300,
			'display'  => 'Every five minutes',
		);
		return $schedules;
	}
);
add_action( 'dl_img_cron', 'dl_img_batch' );

// Runs from theme activation, and picks up images added to new products later.
add_action(
	'init',
	static function () {
		if ( ! wp_next_scheduled( 'dl_img_cron' ) ) {
			wp_schedule_event( time() + 120, 'dl_five_minutes', 'dl_img_cron' );
		}
	}
);
add_action(
	'switch_theme',
	static function () {
		wp_clear_scheduled_hook( 'dl_img_cron' );
	}
);
