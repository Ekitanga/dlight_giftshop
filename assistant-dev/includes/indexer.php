<?php
/**
 * Reads the catalog and writes a compact knowledge file for the assistant.
 * It rebuilds itself when products change, so the assistant keeps up with the store.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Index folder.
 *
 * @return array{0:string,1:string} Path and URL.
 */
function dla_index_location() {
	$uploads = wp_get_upload_dir();
	// Use the site's own scheme (https): the uploads address can be saved as http.
	return array( $uploads['basedir'] . '/dl-assistant', set_url_scheme( $uploads['baseurl'], (string) wp_parse_url( home_url(), PHP_URL_SCHEME ) ) . '/dl-assistant' );
}

/**
 * Public URL of the knowledge file.
 *
 * @return string
 */
function dla_index_url() {
	list( $dir, $url ) = dla_index_location();
	$v                 = (int) get_option( 'dla_index_version' );
	// A new name for every rebuild: the host's cache ignores "?v=" on static files.
	return file_exists( $dir . '/index-' . $v . '.json' ) ? $url . '/index-' . $v . '.json' : $url . '/index.json?v=' . $v;
}

/**
 * Fragrance notes and families the assistant recognises.
 *
 * @return string[]
 */
function dla_note_words() {
	return array( 'oud', 'vanilla', 'rose', 'jasmine', 'amber', 'musk', 'sandalwood', 'patchouli', 'bergamot', 'citrus', 'lemon', 'orange', 'mandarin', 'grapefruit', 'apple', 'pear', 'peach', 'raspberry', 'blackberry', 'cherry', 'plum', 'lychee', 'pineapple', 'coconut', 'passionfruit', 'coffee', 'caramel', 'chocolate', 'honey', 'almond', 'pistachio', 'tonka', 'cardamom', 'pepper', 'saffron', 'cinnamon', 'ginger', 'nutmeg', 'clove', 'leather', 'tobacco', 'vetiver', 'cedar', 'cedarwood', 'incense', 'myrrh', 'benzoin', 'labdanum', 'lavender', 'mint', 'sage', 'geranium', 'iris', 'violet', 'peony', 'lily', 'tuberose', 'ylang', 'neroli', 'orchid', 'ambroxan', 'floral', 'woody', 'fresh', 'aquatic', 'spicy', 'sweet', 'gourmand', 'oriental', 'fruity', 'powdery', 'smoky', 'green', 'marine', 'aromatic', 'creamy', 'chypre', 'fougere' );
}

/**
 * Watch features the assistant recognises: label to text pattern.
 *
 * @return array<string,string>
 */
function dla_feature_patterns() {
	return array(
		'automatic'      => 'automatic|self[- ]winding',
		'mechanical'     => 'mechanical',
		'quartz'         => 'quartz',
		'solar'          => 'solar',
		'chronograph'    => 'chronograph',
		'digital'        => 'digital',
		'analog'         => 'analog|analogue',
		'skeleton'       => 'skeleton',
		'leather'        => 'leather',
		'steel'          => 'stainless steel|steel bracelet|steel strap',
		'mesh'           => 'mesh',
		'silicone'       => 'silicone|rubber|resin',
		'ceramic'        => 'ceramic',
		'rosegold'       => 'rose[- ]gold',
		'gold'           => 'gold',
		'silver'         => 'silver',
		'black'          => 'black',
		'blue'           => 'blue',
		'green'          => 'green',
		'white'          => 'white',
		'brown'          => 'brown',
		'pink'           => 'pink',
		'waterresistant' => 'water[- ]resist|waterproof|\d+\s?(m|atm|bar)\b',
		'luminous'       => 'luminous|lume',
		'daydate'        => 'day[ /&-]+date|day and date',
	);
}

/**
 * Perfume houses found at the start of product names, with common short forms.
 *
 * @return array<string,string[]>
 */
function dla_brand_aliases() {
	return array(
		'Tom Ford'                 => array( 'tom ford', 'tomford' ),
		'Dior'                     => array( 'dior', 'christian dior' ),
		'Creed'                    => array( 'creed' ),
		'Giorgio Armani'           => array( 'giorgio armani', 'armani', 'emporio armani' ),
		'Gucci'                    => array( 'gucci' ),
		'Versace'                  => array( 'versace' ),
		'Hugo Boss'                => array( 'hugo boss', 'boss' ),
		'Lancome'                  => array( 'lancome' ),
		'Maison Francis Kurkdjian' => array( 'maison francis kurkdjian', 'francis kurkdjian', 'kurkdjian', 'mfk', 'baccarat' ),
		'Parfums de Marly'         => array( 'parfums de marly', 'de marly', 'pdm' ),
		'Afnan'                    => array( 'afnan' ),
		'Lattafa'                  => array( 'lattafa', 'latafa' ),
		'Armaf'                    => array( 'armaf' ),
		'Montblanc'                => array( 'montblanc', 'mont blanc' ),
		'Givenchy'                 => array( 'givenchy' ),
		'Chanel'                   => array( 'chanel' ),
		'Yves Saint Laurent'       => array( 'yves saint laurent', 'saint laurent', 'ysl' ),
		'Guerlain'                 => array( 'guerlain' ),
		'Dolce & Gabbana'          => array( 'dolce and gabbana', 'dolce gabbana', 'd and g', 'dandg', 'dg' ),
		'Burberry'                 => array( 'burberry' ),
		'Carolina Herrera'         => array( 'carolina herrera', 'herrera' ),
		'Paco Rabanne'             => array( 'paco rabanne', 'rabanne' ),
		'Jean Paul Gaultier'       => array( 'jean paul gaultier', 'gaultier', 'jpg' ),
		'Calvin Klein'             => array( 'calvin klein', 'ck' ),
		'Bvlgari'                  => array( 'bvlgari', 'bulgari' ),
		'Prada'                    => array( 'prada' ),
		'Viktor & Rolf'            => array( 'viktor and rolf', 'viktor rolf' ),
		'Amouage'                  => array( 'amouage' ),
		'Kilian'                   => array( 'kilian' ),
		'Xerjoff'                  => array( 'xerjoff' ),
		'Nishane'                  => array( 'nishane' ),
		'Maison Crivelli'          => array( 'maison crivelli', 'crivelli' ),
		'Al Haramain'              => array( 'al haramain', 'haramain' ),
		'Rasasi'                   => array( 'rasasi' ),
		'Maison Alhambra'          => array( 'maison alhambra', 'alhambra' ),
		'Mancera'                  => array( 'mancera' ),
		'Montale'                  => array( 'montale' ),
		'Valentino'                => array( 'valentino' ),
		'Marc Jacobs'              => array( 'marc jacobs' ),
		'Narciso Rodriguez'        => array( 'narciso rodriguez', 'narciso' ),
		'Issey Miyake'             => array( 'issey miyake', 'miyake' ),
		'Azzaro'                   => array( 'azzaro' ),
		'Davidoff'                 => array( 'davidoff' ),
	);
}

/**
 * Turn stored HTML into clean plain text.
 *
 * @param string $html Stored content.
 * @return string
 */
function dla_plain( $html ) {
	$text = preg_replace( '#<(br|/p|/li|/h[1-6]|/div)[^>]*>#i', ". ", (string) $html );
	$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
	$text = preg_replace( '/\s*\.(\s*\.)+/', '.', $text );
	return trim( preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Lowercase and remove accents, for matching.
 *
 * @param string $text Text.
 * @return string
 */
function dla_norm( $text ) {
	return strtolower( remove_accents( $text ) );
}

/**
 * Sold counts over the past year.
 *
 * @return array<int,int>
 */
function dla_sales() {
	global $wpdb;
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$rows = $wpdb->get_results(
		"SELECT l.product_id id, COUNT(DISTINCT l.order_id) c
		FROM {$wpdb->prefix}wc_order_product_lookup l
		JOIN {$wpdb->prefix}wc_order_stats s ON s.order_id = l.order_id
		WHERE s.status IN ('wc-completed','wc-processing') AND s.date_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
		GROUP BY l.product_id"
	);
	$out = array();
	foreach ( (array) $rows as $row ) {
		$out[ (int) $row->id ] = (int) $row->c;
	}
	return $out;
}

/**
 * Build the knowledge file.
 *
 * @return int Number of products written.
 */
function dla_build_index() {
	if ( ! function_exists( 'wc_get_products' ) ) {
		return 0;
	}
	$notes    = dla_note_words();
	$features = dla_feature_patterns();
	$aliases  = dla_brand_aliases();
	$sales    = dla_sales();
	$clicks   = dla_click_counts();

	$perfume_root = get_term_by( 'slug', 'perfumes', 'product_cat' );
	$watch_root   = get_term_by( 'slug', 'watches', 'product_cat' );
	$stop         = array_flip( explode( ' ', 'the and for with from that this your you are was were has have had not but all can will its his her him she they them their our out who how why when what which where more most very also into over under about such than then these those each other some any been being both only own same too just perfume fragrance scent watch product dlight giftshop features feature key description wear wearing perfect designed ideal style stylish made make makes gives give delivers offers offer ensures great best premium quality high new look looks long lasting' ) );

	$products = wc_get_products(
		array(
			'status'       => 'publish',
			'limit'        => -1,
			'stock_status' => 'instock',
			'visibility'   => 'catalog',
			'type'         => array( 'simple', 'variable' ),
		)
	);

	$rows = array();
	foreach ( $products as $product ) {
		$id    = $product->get_id();
		$title = dla_plain( $product->get_name() );
		$parts = preg_split( '/\s+[–—-]\s+/u', $title, 2 );
		$name  = trim( $parts[0] );
		$note  = isset( $parts[1] ) ? trim( $parts[1] ) : '';

		$desc     = dla_plain( $product->get_description() );
		$short    = dla_plain( $product->get_short_description() );
		$full     = trim( $short . ' ' . $desc );
		$haystack = dla_norm( $full );
		$title_n  = dla_norm( $title );

		// Categories decide what kind of product this is, and who it is for.
		$kind   = 'o';
		$gender = '';
		$type   = '';
		$brand  = '';
		$labels = array();
		foreach ( (array) get_the_terms( $id, 'product_cat' ) as $term ) {
			if ( ! $term ) {
				continue;
			}
			$label    = html_entity_decode( $term->name, ENT_QUOTES, 'UTF-8' );
			$labels[] = dla_norm( $label );
			$parents  = array_map( 'intval', get_ancestors( $term->term_id, 'product_cat' ) );
			$is_perf  = $perfume_root && ( (int) $term->term_id === (int) $perfume_root->term_id || in_array( (int) $perfume_root->term_id, $parents, true ) );
			$is_watch = $watch_root && ( (int) $term->term_id === (int) $watch_root->term_id || in_array( (int) $watch_root->term_id, $parents, true ) );
			if ( $is_perf ) {
				$kind = 'p';
			}
			if ( $is_watch ) {
				$kind = 'w';
				$chain = array_merge( array( (int) $term->term_id ), $parents );
				foreach ( $chain as $ancestor ) {
					$node = get_term( $ancestor, 'product_cat' );
					if ( $node && ! is_wp_error( $node ) && (int) $node->parent === (int) $watch_root->term_id && ! preg_match( '/\b(men|women|ladies)\b/i', $node->name ) ) {
						$brand = html_entity_decode( $node->name, ENT_QUOTES, 'UTF-8' );
					}
				}
			}
			if ( preg_match( '/\b(women|ladies)\b/i', $label ) ) {
				$gender = $gender && 'f' !== $gender ? 'u' : 'f';
			} elseif ( preg_match( '/\bmen\b/i', $label ) ) {
				$gender = $gender && 'm' !== $gender ? 'u' : 'm';
			}
			if ( preg_match( '/^(tester|original|arabic)/i', $label, $m ) ) {
				$type = strtolower( $m[1][0] );
			}
		}
		// The product's own name is more specific than its categories.
		if ( preg_match( '/\bunisex\b/', $title_n ) ) {
			$gender = 'u';
		} elseif ( preg_match( '/\b(women|womens|ladies|for her|woman)\b/', str_replace( "'", '', $title_n ) ) ) {
			$gender = 'f';
		} elseif ( preg_match( '/\b(men|mens|gents|for him|cologne)\b/', str_replace( "'", '', $title_n ) ) ) {
			$gender = 'm';
		}
		if ( 'w' === $kind ) {
			foreach ( array( 'Casio', 'Seiko', 'Naviforce', 'Curren', 'Fossil', 'Poedagar', 'Hannah Martin', 'Michael Kors', 'Diesel', 'Megir', 'Skmei', 'MiniFocus' ) as $watch_brand ) {
				if ( 0 === strpos( $title_n, strtolower( $watch_brand ) ) ) {
					$brand = $watch_brand;
					break;
				}
			}
		}
		if ( ! $brand ) {
			foreach ( $aliases as $canon => $names ) {
				foreach ( $names as $alias ) {
					if ( preg_match( '/\b' . preg_quote( $alias, '/' ) . '\b/', str_replace( array( '&amp;', '&' ), 'and', $title_n ) ) ) {
						$brand = $canon;
						break 2;
					}
				}
			}
		}

		// Whole-word matches only: "oud" must never match "proud" or "cloud".
		$sentences = preg_split( '/(?<=[.!?])\s+/u', $full );
		$found     = array();
		$snips     = array();
		if ( 'p' === $kind ) {
			foreach ( $notes as $word ) {
				$in_title = preg_match( '/\b' . $word . '\b/', $title_n );
				if ( $in_title || preg_match( '/\b' . $word . '\b/', $haystack ) ) {
					$found[] = $word;
					if ( count( $snips ) < 6 && ! $in_title ) {
						foreach ( (array) $sentences as $sentence ) {
							if ( strlen( $sentence ) > 12 && strlen( $sentence ) < 170 && preg_match( '/\b' . $word . '\b/', dla_norm( $sentence ) ) ) {
								$snips[ $word ] = $sentence;
								break;
							}
						}
					}
				}
			}
		}
		$feat = array();
		if ( 'w' === $kind ) {
			$both = $title_n . ' ' . $haystack;
			foreach ( $features as $label => $pattern ) {
				if ( preg_match( '~\b(' . $pattern . ')~', $both ) ) {
					$feat[] = $label;
				}
			}
			if ( preg_match( '/\brose[- ]gold/', $both ) ) {
				$feat = array_values( array_diff( $feat, array() ) );
			}
		}

		$size = preg_match( '/\b(\d{2,3})\s?ml\b/', $title_n . ' ' . $haystack, $m ) ? (int) $m[1] : 0;
		$conc = '';
		if ( preg_match( '/\b(extrait|parfum|edp|eau de parfum|edt|eau de toilette)\b/', $title_n . ' ' . substr( $haystack, 0, 400 ), $m ) ) {
			$conc = array( 'extrait' => 'Extrait', 'parfum' => 'Parfum', 'edp' => 'EDP', 'eau de parfum' => 'EDP', 'edt' => 'EDT', 'eau de toilette' => 'EDT' )[ $m[1] ];
		}

		// Details for follow-up questions: notes and the best describing sentences, or a watch's specs.
		$dtext = dla_detail_text( $product->get_short_description() . "\n" . $product->get_description() );
		$np    = 'p' === $kind ? dla_note_pyramid( $dtext ) : array();
		$mn    = 'p' === $kind ? dla_main_notes( $found, $haystack, $title_n ) : array();
		$hl    = 'o' !== $kind ? dla_highlight( $dtext, $kind, $mn ) : '';
		$sp    = 'w' === $kind ? dla_watch_specs( $dtext ) : array();

		// A short summary, and the most distinctive other words for general search.
		$summary = '';
		foreach ( (array) $sentences as $sentence ) {
			if ( strlen( $sentence ) > 40 && ! preg_match( '/^(key features|features|specifications)/i', $sentence ) ) {
				$summary = wp_html_excerpt( $sentence, 200, '...' );
				break;
			}
		}
		$counts = array();
		preg_match_all( '/\b[a-z][a-z-]{2,}\b/', $haystack, $words );
		foreach ( $words[0] as $word ) {
			if ( ! isset( $stop[ $word ] ) ) {
				$counts[ $word ] = ( $counts[ $word ] ?? 0 ) + 1;
			}
		}
		arsort( $counts );
		$tokens = array_slice( array_keys( $counts ), 0, 45 );

		$image = $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'woocommerce_thumbnail' ) : '';
		$path  = wp_parse_url( $product->get_permalink(), PHP_URL_PATH );

		$rows[] = array_filter(
			array(
				'i'  => $id,
				'n'  => $name,
				'd'  => $note,
				'u'  => $path,
				'p'  => (float) $product->get_price(),
				'r'  => (float) $product->get_regular_price(),
				'm'  => $image ? wp_parse_url( $image, PHP_URL_PATH ) : '',
				'k'  => $kind,
				'g'  => $gender,
				't'  => $type,
				'b'  => $brand,
				'o'  => $found,
				'f'  => $feat,
				'x'  => $snips,
				'np' => $np,
				'mn' => $mn,
				'hl' => $hl,
				'sp' => $sp,
				's'  => $summary,
				'w'  => implode( ' ', $tokens ),
				'c'  => implode( ' ', array_unique( $labels ) ),
				'z'  => $size,
				'y'  => $conc,
				'q'  => (int) ( $sales[ $id ] ?? 0 ) + (int) floor( ( $clicks[ $id ] ?? 0 ) / 3 ),
				'a'  => (int) strtotime( (string) $product->get_date_created() ),
			),
			static fn( $v ) => '' !== $v && array() !== $v && 0 !== $v && 0.0 !== $v
		);
	}

	$answers = array();
	foreach ( array_merge( dla_custom_answers(), dla_default_answers() ) as $answer ) {
		$answers[] = $answer;
	}

	$data = array(
		'v'        => time(),
		'notes'    => $notes,
		'features' => array_keys( $features ),
		'brands'   => $aliases,
		'answers'  => $answers,
		'synonyms' => (array) get_option( 'dla_synonyms', array() ),
		'products' => $rows,
	);

	list( $dir ) = dla_index_location();
	wp_mkdir_p( $dir );
	$tmp = $dir . '/index.tmp';
	// phpcs:ignore WordPress.WP.AlternativeFunctions
	if ( false === file_put_contents( $tmp, wp_json_encode( $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ) {
		return 0;
	}
	copy( $tmp, $dir . '/index-' . $data['v'] . '.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	rename( $tmp, $dir . '/index.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	update_option( 'dla_index_version', $data['v'], false );
	// Keep recent copies for pages that are still cached; remove the rest.
	foreach ( (array) glob( $dir . '/index-*.json' ) as $old ) {
		if ( basename( $old ) !== 'index-' . $data['v'] . '.json' && filemtime( $old ) < time() - 2 * DAY_IN_SECONDS ) {
			wp_delete_file( $old );
		}
	}
	update_option( 'dla_index_count', count( $rows ), false );
	return count( $rows );
}

/**
 * Rebuild shortly after any product change. Several quick edits cause just one rebuild.
 */
function dla_queue_rebuild() {
	if ( ! wp_next_scheduled( 'dla_rebuild' ) ) {
		wp_schedule_single_event( time() + 120, 'dla_rebuild' );
	}
}
foreach ( array( 'woocommerce_update_product', 'woocommerce_new_product', 'woocommerce_product_set_stock_status', 'woocommerce_delete_product', 'woocommerce_trash_product' ) as $dla_hook ) {
	add_action( $dla_hook, 'dla_queue_rebuild' );
}
