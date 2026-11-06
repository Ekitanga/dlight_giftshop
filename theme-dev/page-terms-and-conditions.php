<?php
/**
 * Terms and conditions. The wording stays exactly as stored in the page;
 * the theme adds only the layout.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

dl_help_open( 'terms-and-conditions', 'Terms & Conditions', 'Terms of sale, returns, exchanges and refunds.' );

while ( have_posts() ) {
	the_post();
	$dl_terms = apply_filters( 'the_content', get_the_content() );
	// Hide the repeated title lines before the first numbered clause. The terms themselves are unchanged.
	$dl_first = strpos( $dl_terms, 'Welcome to DLight' );
	if ( false !== $dl_first ) {
		$dl_start = strrpos( substr( $dl_terms, 0, $dl_first ), '<p' );
		$dl_terms = false === $dl_start ? $dl_terms : substr( $dl_terms, $dl_start );
	}
	echo wp_kses_post( $dl_terms );
}

dl_help_close();
