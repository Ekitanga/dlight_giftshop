<?php
/**
 * Technical SEO: titles, descriptions, canonicals, social tags, structured data.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'document_title_parts',
	static function ( $parts ) {
		if ( is_front_page() ) {
			return array( 'title' => 'Dlight Giftshop: Perfume & Watches in Nairobi, Kenya' );
		}
		if ( function_exists( 'is_product' ) && is_product() ) {
			list( $name ) = dl_split_title( get_the_title() );
			$parts['title'] = $name . ' Price in Kenya';
		}
		if ( dl_landing_title() ) {
			$parts['title'] = dl_landing_title() . ' in Kenya';
		} elseif ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$term           = get_queried_object();
			$parts['title'] = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' ) . ' in Kenya';
		}
		unset( $parts['tagline'] );
		return $parts;
	}
);
add_filter( 'document_title_separator', static fn() => '|' );

/**
 * Meta description for the current page.
 *
 * @return string
 */
function dl_meta_description() {
	if ( is_front_page() ) {
		return 'Perfumes and watches at Dlight Giftshop, Tom Mboya Street, Nairobi. Pay securely with M-Pesa, with delivery across Kenya.';
	}
	if ( function_exists( 'is_product' ) && is_product() ) {
		$product = wc_get_product( get_the_ID() );
		if ( $product ) {
			list( $name, $note ) = dl_split_title( $product->get_name() );
			$price               = wp_strip_all_tags( wc_price( $product->get_price() ) );
			$price               = html_entity_decode( $price, ENT_QUOTES, 'UTF-8' );
			$text                = sprintf( '%s%s. %s at Dlight Giftshop Nairobi. Pay securely with M-Pesa, with delivery across Kenya.', $name, $note ? ' - ' . $note : '', $price );
			return wp_html_excerpt( preg_replace( '/\s+/u', ' ', $text ), 158 );
		}
	}
	if ( is_tax() ) {
		$term = get_queried_object();
		$desc = wp_strip_all_tags( term_description( $term ) );
		if ( $desc ) {
			return wp_html_excerpt( $desc, 158 );
		}
		$name = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
		return sprintf( 'Shop %1$d %2$s at Dlight Giftshop, Nairobi. Pay securely with M-Pesa, with delivery across Kenya.', (int) $term->count, $name );
	}
	if ( dl_landing_title() ) {
		return sprintf( 'Shop %s at Dlight Giftshop, Nairobi. Pay securely with M-Pesa, with delivery across Kenya.', dl_landing_title() );
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		return 'Browse perfume and watches at Dlight Giftshop, Nairobi: Tom Ford, Dior, Creed, Casio, Seiko, Fossil and more. Pay securely with M-Pesa.';
	}
	if ( is_singular() ) {
		return wp_html_excerpt( wp_strip_all_tags( get_the_excerpt() ), 158 );
	}
	return '';
}

/**
 * Canonical URL for archives, with filters removed. WordPress handles singular pages.
 *
 * @return string
 */
function dl_canonical() {
	if ( is_front_page() ) {
		return home_url( '/' );
	}
	if ( is_tax() ) {
		$link = get_term_link( get_queried_object() );
		$page = max( 1, (int) get_query_var( 'paged' ) );
		return is_wp_error( $link ) ? '' : ( $page > 1 ? trailingslashit( $link ) . 'page/' . $page . '/' : $link );
	}
	if ( function_exists( 'is_shop' ) && is_shop() ) {
		$link = wc_get_page_permalink( 'shop' );
		$page = max( 1, (int) get_query_var( 'paged' ) );
		if ( dl_landing_title() ) {
			$link = dl_shop_url( sanitize_key( $_GET['dl_for'] ), sanitize_key( $_GET['dl_kind'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $page > 1 ? add_query_arg( 'paged', $page, $link ) : $link;
		}
		return $page > 1 ? trailingslashit( $link ) . 'page/' . $page . '/' : $link;
	}
	return '';
}

add_action(
	'wp_head',
	static function () {
		$desc = dl_meta_description();
		if ( $desc ) {
			echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
		}

		$canonical = dl_canonical();
		// WordPress prints the canonical link on single pages (the home page is one), so only add it elsewhere.
		if ( $canonical && ! is_singular() ) {
			echo '<link rel="canonical" href="' . esc_url( $canonical ) . '">' . "\n";
		}

		$title = wp_get_document_title();
		$url   = $canonical ?: ( is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ) );
		$image = dl_logo_url();
		$type  = 'website';

		if ( function_exists( 'is_product' ) && is_product() ) {
			$type = 'product';
			$src  = get_the_post_thumbnail_url( get_the_ID(), 'large' );
			$image = $src ?: $image;
		} elseif ( is_tax( 'product_cat' ) ) {
			$thumb = get_term_meta( get_queried_object_id(), 'thumbnail_id', true );
			$src   = $thumb ? wp_get_attachment_image_url( $thumb, 'large' ) : '';
			$image = $src ?: $image;
		}

		$og = array(
			'og:site_name'   => 'Dlight Giftshop',
			'og:locale'      => 'en_KE',
			'og:type'        => $type,
			'og:title'       => $title,
			'og:description' => $desc,
			'og:url'         => $url,
			'og:image'       => $image,
		);
		foreach ( $og as $property => $content ) {
			if ( $content ) {
				echo '<meta property="' . esc_attr( $property ) . '" content="' . esc_attr( $content ) . '">' . "\n";
			}
		}
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";

		if ( function_exists( 'is_product' ) && is_product() ) {
			$product = wc_get_product( get_the_ID() );
			if ( $product ) {
				echo '<meta property="product:price:amount" content="' . esc_attr( $product->get_price() ) . '">' . "\n";
				echo '<meta property="product:price:currency" content="' . esc_attr( get_woocommerce_currency() ) . '">' . "\n";
			}
		}

		$graph = array(
			array(
				'@type'     => 'Store',
				'@id'       => home_url( '/#store' ),
				'name'      => 'Dlight Giftshop',
				'url'       => home_url( '/' ),
				'logo'      => dl_logo_url(),
				'image'     => dl_logo_url(),
				'telephone' => DL_PHONE,
				'email'     => DL_EMAIL,
				'priceRange' => 'KES',
				'openingHoursSpecification' => array(
					array(
						'@type'     => 'OpeningHoursSpecification',
						'dayOfWeek' => array( 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' ),
						'opens'     => '08:30',
						'closes'    => '19:00',
					),
				),
				'address'   => array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => 'Rasumal House, 4th Floor, Shop 14, Tom Mboya Street',
					'addressLocality' => 'Nairobi',
					'addressCountry'  => 'KE',
				),
			),
		);
		if ( is_front_page() ) {
			$graph[] = array(
				'@type'           => 'WebSite',
				'@id'             => home_url( '/#website' ),
				'url'             => home_url( '/' ),
				'name'            => 'Dlight Giftshop',
				'publisher'       => array( '@id' => home_url( '/#store' ) ),
				'potentialAction' => array(
					'@type'       => 'SearchAction',
					'target'      => home_url( '/?s={search_term_string}&post_type=product' ),
					'query-input' => 'required name=search_term_string',
				),
			);
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	},
	5
);

// Add the brand to Google product data when the product belongs to a brand category.
add_filter(
	'woocommerce_structured_data_product',
	static function ( $markup, $product ) {
		$brand = dl_product_eyebrow( $product );
		if ( $brand && ! preg_match( '/tester|original|arabic/i', $brand ) ) {
			$markup['brand'] = array(
				'@type' => 'Brand',
				'name'  => $brand,
			);
		}
		return $markup;
	},
	10,
	2
);

// Keep thin or duplicate views out of the search index.
add_filter(
	'wp_robots',
	static function ( $robots ) {
		$noindex = is_search() || ( function_exists( 'dl_is_filtered' ) && ( is_shop_archive_view() && dl_is_filtered() && ! dl_landing_title() ) );
		if ( is_page( array( 'compare', 'yith-compare', 'yith-compare-2', 'about-us-type-2', 'contact-us-type-2', 'elm-about-us', 'elm-contact-us', 'elm-digital-v1', 'order-tracking' ) ) ) {
			$noindex = true;
		}
		if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) {
			$noindex = true;
		}
		// The home page is stored under a leftover slug on that list. It must always be indexable.
		if ( $noindex && ! is_front_page() ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}
);

/**
 * True on the shop page and product taxonomy archives.
 *
 * @return bool
 */
function is_shop_archive_view() {
	return function_exists( 'is_shop' ) && ( is_shop() || is_product_taxonomy() );
}

// Every product image gets meaningful alt text, even when none was entered.
add_filter(
	'wp_get_attachment_image_attributes',
	static function ( $attr, $attachment ) {
		if ( empty( $attr['alt'] ) ) {
			$parent = $attachment->post_parent ? get_the_title( $attachment->post_parent ) : '';
			$attr['alt'] = $parent ?: get_the_title( $attachment );
		}
		return $attr;
	},
	10,
	2
);
