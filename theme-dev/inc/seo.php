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
			$seo            = (string) get_term_meta( $term->term_id, 'dl_seo_title', true );
			$parts['title'] = $seo ? $seo : dl_term_display_name( $term ) . ' in Kenya';
		}
		unset( $parts['tagline'] );
		return $parts;
	}
);
add_filter( 'document_title_separator', static fn() => '|' );

/**
 * Category name with its brand in front when the name alone is generic,
 * e.g. "Casio Watches for Men" instead of "Watches for Men".
 *
 * @param WP_Term $term Category.
 * @return string
 */
function dl_term_display_name( $term ) {
	$name = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
	if ( $term->parent && preg_match( '/^(watches|watch|perfumes?)\b.*\b(men|women|ladies)\b|^(men|women)\b.*perfumes?$/i', $name ) ) {
		$parent = get_term( $term->parent, $term->taxonomy );
		if ( $parent && ! is_wp_error( $parent ) ) {
			$pname = html_entity_decode( $parent->name, ENT_QUOTES, 'UTF-8' );
			if ( 0 === strcasecmp( $pname, 'Watches' ) ) {
				return ucwords( $name );
			}
			$pname = ucwords( $pname );
			$name  = preg_match( '/perfume/i', $name ) ? $pname . ' for ' . ( preg_match( '/^women/i', $name ) ? 'Women' : 'Men' ) : $pname . ' ' . $name;
		}
	}
	return $name;
}

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
				// Official profiles: tells Google which Facebook page is the real one.
				'sameAs'    => array( 'https://www.facebook.com/DlightGiftshopKe', 'https://www.instagram.com/dlightgiftshopofficial/' ),
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
		if ( ( ! $brand || preg_match( '/tester|original|arabic/i', $brand ) ) && function_exists( 'dl_feed_brand' ) ) {
			$brand = dl_feed_brand( $product ); // Perfumes: the brand from the title (Tom Ford, Dior ...).
		}
		$model = function_exists( 'dl_product_model' ) ? dl_product_model( $product ) : '';
		if ( $model ) {
			$markup['mpn'] = $model;
		}
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
 * Pages kept out of the search index (and so out of the sitemap): leftover theme demo pages,
 * compare and order tracking, and the cart, checkout and account pages. Never the home page.
 *
 * @return int[]
 */
function dl_noindex_page_ids() {
	// Several leftover pages can share one address (e.g. four "Compare" pages), so match every page by address.
	$ids = get_posts(
		array(
			'post_type'     => 'page',
			'post_status'   => 'any',
			'numberposts'   => -1,
			'fields'        => 'ids',
			'post_name__in' => array( 'compare', 'yith-compare', 'yith-compare-2', 'about-us-type-2', 'contact-us-type-2', 'elm-about-us', 'elm-contact-us', 'elm-digital-v1', 'order-tracking' ),
		)
	);
	if ( function_exists( 'wc_get_page_id' ) ) {
		foreach ( array( 'cart', 'checkout', 'myaccount' ) as $wc ) {
			$ids[] = (int) wc_get_page_id( $wc );
		}
	}
	return array_values( array_diff( array_filter( $ids, static fn( $id ) => $id > 0 ), array( (int) get_option( 'page_on_front' ) ) ) );
}

// The sitemap lists only pages Google should index (a sitemap page marked noindex is reported as an error).
add_filter(
	'wp_sitemaps_posts_query_args',
	static function ( $args, $post_type ) {
		if ( 'page' === $post_type ) {
			$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), dl_noindex_page_ids() );
		}
		return $args;
	},
	10,
	2
);

// Common guesses and old addresses for the cart go to the real cart page.
add_action(
	'template_redirect',
	static function () {
		if ( ! is_404() || ! function_exists( 'wc_get_cart_url' ) ) {
			return;
		}
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( in_array( $path, array( 'cart', 'basket', 'shopping-bag' ), true ) ) {
			wp_safe_redirect( wc_get_cart_url(), 301 );
			exit;
		}
		// Old pages of perfumes we still sell (from Google's "Not found" report) -> today's page for the same perfume.
		$moved = array(
			'product/libre-le-parfum-yves-saint-laurent'                => 31760,
			'product/chanel-chance-eau-tendre-eau-de-toilette-spray'    => 32338,
			'product/christian-dior-miss-dior-eau-de-parfum-spray'      => 31438,
			'product/givenchy-gentleman-intense-eau-de-toilette-spray'  => 34394,
			'product/acqua-di-gio-profondo-giorgio-armani-eau-de-parfum-spray' => 32330,
		);
		if ( isset( $moved[ $path ] ) && 'publish' === get_post_status( $moved[ $path ] ) ) {
			wp_safe_redirect( get_permalink( $moved[ $path ] ), 301 );
			exit;
		}
		// Products, tags and departments of the old shop (household, clothing, electronics...) are gone for good:
		// answer 410 so Google drops them sooner. The visitor still sees the normal "page not found" page.
		if ( preg_match( '#^(product/[^/]+|product-tag/.+|product-category/(fashion|home-office|outdoor-items|kids-items|electronics-appliances|computersaccessories|toners-cartridges|new-stock|valentine-deals)(/.*)?)$#', $path ) ) {
			status_header( 410 );
		}
	},
	5
);

// The blog list page gets a canonical address like every other page.
add_action(
	'wp_head',
	static function () {
		if ( is_home() && ! is_front_page() && ! is_paged() && get_option( 'page_for_posts' ) ) {
			echo '<link rel="canonical" href="' . esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ) . '" />' . "\n";
		} elseif ( is_category() && ! is_paged() ) {
			$link = get_term_link( get_queried_object() );
			if ( ! is_wp_error( $link ) ) {
				echo '<link rel="canonical" href="' . esc_url( $link ) . '" />' . "\n";
			}
		}
	},
	2
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

// Buying guides: Article + FAQ structured data, so Google can show them as articles and FAQ answers.
add_action(
	'wp_head',
	static function () {
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post  = get_queried_object();
		$img   = get_the_post_thumbnail_url( $post, 'large' );
		$ids   = array_filter( array_map( 'intval', explode( ',', (string) get_post_meta( $post->ID, 'dl_featured_products', true ) ) ) );
		if ( ! $img && $ids ) {
			$p   = wc_get_product( reset( $ids ) );
			$img = $p ? wp_get_attachment_url( $p->get_image_id() ) : '';
		}
		$org   = array( '@type' => 'Organization', 'name' => 'Dlight Giftshop', 'url' => home_url( '/' ), 'logo' => array( '@type' => 'ImageObject', 'url' => dl_asset_url( '/assets/img/logo-dg.png' ) ) );
		$graph = array(
			array(
				'@type'            => 'Article',
				'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
				'description'      => wp_strip_all_tags( get_the_excerpt( $post ) ),
				'datePublished'    => get_the_date( 'c', $post ),
				'dateModified'     => get_the_modified_date( 'c', $post ),
				'author'           => $org,
				'publisher'        => $org,
				'mainEntityOfPage' => get_permalink( $post ),
				'image'            => $img ? array( $img ) : array(),
			),
		);
		// FAQ: every <h3> ending with "?" followed by its paragraph(s).
		if ( preg_match_all( '#<h3[^>]*>(.*?\?)</h3>\s*((?:<p[^>]*>.*?</p>\s*)+)#s', (string) $post->post_content, $m, PREG_SET_ORDER ) ) {
			$faq = array();
			foreach ( $m as $q ) {
				$faq[] = array( '@type' => 'Question', 'name' => wp_strip_all_tags( $q[1] ), 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => trim( wp_strip_all_tags( $q[2] ) ) ) );
			}
			$graph[] = array( '@type' => 'FAQPage', 'mainEntity' => $faq );
		}
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	},
	6
);
