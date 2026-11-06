<?php
/**
 * Product card in loops.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

global $product;

if ( $product && $product->is_visible() ) {
	static $dl_loop_index = 0;
	++$dl_loop_index;
	dl_card( $product, array( 'eager' => $dl_loop_index <= 4, 'tag' => 'h2' ) );
}
