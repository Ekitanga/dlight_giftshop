<?php
/**
 * Product details for follow-up questions: a perfume's notes and a watch's key specifications,
 * plus one or two sentences that describe the product best. Read from the product's own description.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Description HTML as plain text that keeps its structure: one line per paragraph, list item or table row.
 *
 * @param string $html Description HTML.
 * @return string
 */
function dla_detail_text( $html ) {
	$html = preg_replace( '~<\s*(script|style)[^>]*>.*?</\s*\1\s*>~is', ' ', (string) $html );
	$html = preg_replace( '~</t[dh]\s*>~i', ' | ', $html );
	$html = preg_replace( '~<\s*br\s*/?>|</\s*(p|div|li|tr|h[1-6]|ul|ol|table)\s*>~i', "\n", $html );
	$text = html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' );
	$text = str_replace( "\xc2\xa0", ' ', $text );
	$lines = array();
	foreach ( preg_split( '/\R/u', $text ) as $line ) {
		$line = trim( preg_replace( '/[ \t]+/u', ' ', $line ), " |\t" );
		if ( '' !== $line ) {
			$lines[] = $line;
		}
	}
	return implode( "\n", $lines );
}

/**
 * Split "bergamot, pink pepper and lavender" into clean items.
 *
 * @param string $list Text after "Top notes:".
 * @return string[]
 */
function dla_detail_items( $list ) {
	$list  = preg_replace( '/\b(?:heart|middle|base|top|dry[\s-]?down)\s+notes?\b.*$/iu', '', $list );
	$parts = preg_split( '/\s*(?:,|;|\/|\band\b|&|\+|\|)\s*/iu', (string) $list );
	$out   = array();
	$known = dla_detail_vocab();
	foreach ( $parts as $p ) {
		$p = preg_replace( '/\s*[—–].*$/u', '', (string) $p );
		$p = trim( preg_replace( '/^(?:of|with|notes?|accords?|is|are|include|includes|the|a|an)\s+/i', '', trim( $p, " .:-–—()\"'" ) ) );
		$p = function_exists( 'mb_strtolower' ) ? mb_strtolower( $p ) : strtolower( $p );
		if ( strlen( $p ) < 3 || strlen( $p ) > 32 || str_word_count( $p ) > 3 || preg_match( '/\d{3,}|http|ksh|price/i', $p ) ) {
			continue;
		}
		// Keep it only if it names a real note, so phrases like "most distinctive chapter" are dropped.
		foreach ( preg_split( '/[\s-]+/', $p ) as $w ) {
			if ( isset( $known[ $w ] ) ) {
				$out[] = $p;
				break;
			}
		}
	}
	return array_slice( array_values( array_unique( $out ) ), 0, 6 );
}

/**
 * Words that name perfume ingredients, used to keep only real notes in a list.
 *
 * @return array<string,int>
 */
function dla_detail_vocab() {
	static $vocab = null;
	if ( null === $vocab ) {
		$extra = array( 'rosewood', 'tonka', 'bean', 'pepper', 'blossom', 'leaf', 'leaves', 'wood', 'woods', 'absolute', 'resin', 'heliotrope', 'praline', 'caramel', 'coffee', 'tea', 'tobacco', 'leather', 'suede', 'ambroxan', 'ambrette', 'galbanum', 'neroli', 'petitgrain', 'mandarin', 'grapefruit', 'lime', 'yuzu', 'pear', 'apple', 'pineapple', 'blackcurrant', 'cassis', 'plum', 'peach', 'raspberry', 'cherry', 'strawberry', 'coconut', 'almond', 'pistachio', 'honey', 'labdanum', 'benzoin', 'myrrh', 'frankincense', 'incense', 'olibanum', 'cypress', 'juniper', 'sage', 'basil', 'mint', 'violet', 'iris', 'orris', 'lily', 'magnolia', 'peony', 'freesia', 'gardenia', 'osmanthus', 'ylang', 'geranium', 'carnation', 'cinnamon', 'nutmeg', 'clove', 'ginger', 'cardamom', 'saffron', 'cumin', 'vetiver', 'patchouli', 'oakmoss', 'moss', 'birch', 'guaiac', 'cashmeran', 'agarwood', 'musk', 'musks', 'rosemary', 'lavender', 'bergamot', 'lemon', 'orange', 'vanilla', 'amber', 'oud', 'sandalwood', 'cedar', 'cedarwood', 'jasmine', 'rose', 'tuberose' );
		$vocab = array_fill_keys( array_merge( function_exists( 'dla_note_words' ) ? (array) dla_note_words() : array(), $extra ), 1 );
	}
	return $vocab;
}

/**
 * Top, heart and base notes when the description lists them.
 *
 * @param string $text Text from dla_detail_text().
 * @return array{t?:string[],h?:string[],b?:string[]}
 */
function dla_note_pyramid( $text ) {
	$levels = array(
		't' => '(?:top|head|opening)',
		'h' => '(?:heart|middle|mid)',
		'b' => '(?:base|bottom|dry[\s-]?down)',
	);
	$out = array();
	foreach ( $levels as $key => $word ) {
		if ( preg_match( '/\b' . $word . '\s+notes?\b\s*(?:are|include|of|is)?\s*[:\-–—]?\s*([^\n.]{3,180})/iu', $text, $m ) ) {
			$items = dla_detail_items( $m[1] );
			if ( $items ) {
				$out[ $key ] = $items;
			}
		}
	}
	// One level alone is usually a sentence that happens to say "base notes", not a real list.
	return count( $out ) >= 2 ? $out : array();
}

/**
 * The perfume's main notes, most prominent first: notes in the name, then by how often they are mentioned.
 *
 * @param string[] $found    Note words found in the description or name.
 * @param string   $haystack Normalised description.
 * @param string   $title_n  Normalised product name.
 * @return string[]
 */
function dla_main_notes( $found, $haystack, $title_n ) {
	$score = array();
	foreach ( (array) $found as $i => $word ) {
		if ( in_array( $word, array( 'fresh', 'green', 'sweet', 'warm', 'spicy', 'floral', 'woody', 'fruity', 'creamy', 'smoky', 'powdery', 'citrus', 'aquatic', 'oriental', 'gourmand', 'fougere', 'chypre', 'aromatic', 'earthy', 'balsamic', 'animalic', 'sensual', 'clean' ), true ) ) {
			continue;
		}
		$count          = preg_match_all( '/\b' . preg_quote( $word, '/' ) . '\b/', $haystack );
		$score[ $word ] = ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/', $title_n ) ? 100 : 0 ) + $count * 3 - $i * 0.01;
	}
	arsort( $score );
	return array_slice( array_keys( $score ), 0, 6 );
}

/**
 * One or two sentences that best describe the product, for "what does it smell like" or "tell me about it".
 *
 * @param string   $text  Text from dla_detail_text().
 * @param string   $kind  'p' perfume or 'w' watch.
 * @param string[] $words Note words (perfume) to favour.
 * @return string
 */
function dla_highlight( $text, $kind, $words ) {
	$cues = 'p' === $kind
		? '/\b(opens?|opening|heart|base|dry[\s-]?down|notes?|accord|scent|smells?|fragrance|trail|warm|fresh|sweet|sensual|elegant|long[\s-]?lasting)\b/i'
		: '/\b(dial|strap|bracelet|band|movement|quartz|automatic|water|resistan|case|chronograph|date|luminous|stainless|leather|display|elegant|durable)\b/i';
	$best = array();
	foreach ( preg_split( '/(?<=[.!?])\s+|\n/u', $text ) as $s ) {
		// No emoji, arrows or decorative symbols in an answer.
		$s = trim( preg_replace( '/\s{2,}/u', ' ', preg_replace( '/[^\p{L}\p{N}\p{P}\p{Zs}]+/u', ' ', $s ) ) );
		// Note lists are shown separately, so a sentence that is only a list is not a description.
		if ( preg_match( '/^(top|head|heart|middle|base|key|main)\s+notes?\b|^(notes|development|specifications?)\s*:/i', $s ) ) {
			continue;
		}
		// "Durable Construction: Crafted with..." reads better without its label.
		$s   = preg_replace( '/^[A-Z][\w&\' -]{2,32}:\s+(?=[A-Z])/u', '', $s );
		$len = strlen( $s );
		if ( $len < 50 || $len > 260 || preg_match( '/\b(kenya|nairobi|price|buy|order|deliver|shop|ksh|warranty card|click|whatsapp)\b/i', $s ) || substr_count( $s, '|' ) ) {
			continue;
		}
		$score = preg_match_all( $cues, $s ) * 2;
		foreach ( (array) $words as $w ) {
			if ( preg_match( '/\b' . preg_quote( $w, '/' ) . '\b/i', $s ) ) {
				$score += 3;
			}
		}
		if ( $score >= 4 ) {
			$best[] = array( $score - $len / 200, $s );
		}
	}
	usort( $best, static fn( $a, $b ) => $b[0] <=> $a[0] );
	$out = isset( $best[0] ) ? $best[0][1] : '';
	// A second sentence only if it adds something (descriptions sometimes repeat a sentence).
	if ( isset( $best[1] ) && $best[1][1] !== $out && strlen( $out ) + strlen( $best[1][1] ) < 300 ) {
		$out .= ' ' . $best[1][1];
	}
	return $out;
}

/**
 * A watch's key specifications from "Label | Value" table rows or "Label: Value" lines.
 *
 * @param string $text Text from dla_detail_text().
 * @return string[] Lines such as "Movement: Quartz".
 */
function dla_watch_specs( $text ) {
	$wanted = array(
		'movement'         => 'Movement',
		'water resistance' => 'Water resistance',
		'water resistant'  => 'Water resistance',
		'case diameter'    => 'Case size',
		'case size'        => 'Case size',
		'diameter'         => 'Case size',
		'case material'    => 'Case',
		'strap'            => 'Strap',
		'band'             => 'Strap',
		'bracelet'         => 'Strap',
		'dial color'       => 'Dial',
		'dial colour'      => 'Dial',
		'dial'             => 'Dial',
		'glass'            => 'Glass',
		'crystal'          => 'Glass',
		'date function'    => 'Date',
		'display'          => 'Display',
		'clasp'            => 'Clasp',
		'battery'          => 'Battery',
		'warranty'         => 'Warranty',
	);
	$out   = array();
	$lines = explode( "\n", $text );
	foreach ( $lines as $i => $line ) {
		if ( preg_match( '/^\s*([A-Za-z][A-Za-z \/]{2,28}?)\s*(?:\||:|–|-)\s*(.{1,48})$/u', $line, $m ) ) {
			// "Movement: Quartz" or a table row "Movement | Quartz".
			$label = strtolower( trim( $m[1] ) );
			$value = trim( $m[2], " |.\t" );
		} elseif ( isset( $lines[ $i + 1 ] ) && preg_match( '/^[A-Za-z][A-Za-z \/]{2,28}$/u', trim( $line ) ) && strlen( $lines[ $i + 1 ] ) <= 48 ) {
			// A table whose label and value cells end up on separate lines: "Movement", then "Quartz".
			$label = strtolower( trim( $line ) );
			$value = trim( $lines[ $i + 1 ], " |.\t" );
		} else {
			continue;
		}
		foreach ( $wanted as $needle => $nice ) {
			if ( ( $label === $needle || 0 === strpos( $label, $needle ) ) && ! isset( $out[ $nice ] ) && '' !== $value && strlen( $value ) <= 48 ) {
				$out[ $nice ] = $nice . ': ' . $value;
				break;
			}
		}
		if ( count( $out ) >= 8 ) {
			break;
		}
	}
	return array_values( $out );
}
