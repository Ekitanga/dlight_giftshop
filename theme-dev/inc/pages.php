<?php
/**
 * Help and policy pages: list, automatic creation, shared navigation.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * Help pages in menu order. Each has a template named page-{slug}.php.
 *
 * @return array<string,string> Slug to title.
 */
function dl_help_pages() {
	return array(
		'help-centre'          => 'Help Centre',
		'faqs'                 => 'FAQs',
		'delivery-information' => 'Delivery Information',
		'payment-options'      => 'Payment Options',
		'returns-refunds'      => 'Returns & Refunds',
		'order-tracking'       => 'Track Your Order',
		'terms-and-conditions' => 'Terms & Conditions',
		'privacy-policy'       => 'Privacy Policy',
		'contact-us'           => 'Contact Us',
	);
}

/**
 * Create any missing help page and publish any left as a draft. Page content is not changed.
 */
function dl_create_help_pages() {
	foreach ( dl_help_pages() as $slug => $title ) {
		$existing = get_page_by_path( $slug );
		if ( $existing && 'publish' !== $existing->post_status ) {
			wp_update_post( array( 'ID' => $existing->ID, 'post_status' => 'publish' ) );
		}
		if ( ! $existing ) {
			wp_insert_post(
				array(
					'post_type'   => 'page',
					'post_status' => 'publish',
					'post_title'  => $title,
					'post_name'   => $slug,
				)
			);
		}
	}
}
add_action( 'after_switch_theme', 'dl_create_help_pages' );

/**
 * Print the help section's side navigation.
 *
 * @param string $current Current page slug.
 */
function dl_help_nav( $current ) {
	echo '<nav class="dl-helpnav" aria-label="Help pages"><p class="dl-helpnav__h">Help &amp; Policies</p><ul>';
	foreach ( dl_help_pages() as $slug => $title ) {
		printf(
			'<li><a href="%s"%s>%s</a></li>',
			esc_url( home_url( '/' . $slug . '/' ) ),
			$slug === $current ? ' aria-current="page"' : '',
			esc_html( $title )
		);
	}
	echo '</ul></nav>';
}

/**
 * Open a help page layout.
 *
 * @param string $slug  Page slug.
 * @param string $title Page heading.
 * @param string $lede  Short intro.
 */
function dl_help_open( $slug, $title, $lede = '' ) {
	get_header();
	echo '<div class="dl-helphero"><div class="dl-wrap">';
	woocommerce_breadcrumb();
	echo '<h1>' . esc_html( $title ) . '</h1>';
	if ( $lede ) {
		echo '<p>' . esc_html( $lede ) . '</p>';
	}
	echo '</div></div><div class="dl-wrap dl-help">';
	dl_help_nav( $slug );
	echo '<div class="dl-help__body dl-prose">';
}

/**
 * Close a help page layout, with a contact prompt.
 */
function dl_help_close() {
	echo '</div></div>';
	?>
	<section class="dl-helpcta">
		<div class="dl-wrap dl-helpcta__in">
			<div>
				<h2>Still need help?</h2>
				<p>Our team is one message away.</p>
			</div>
			<div class="dl-actions">
				<a class="dl-btn dl-btn--wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I need some help.' ) ); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
				<a class="dl-btn dl-btn--outline" href="tel:<?php echo esc_attr( DL_PHONE ); ?>">Call <?php echo esc_html( DL_PHONE_DISPLAY ); ?></a>
			</div>
		</div>
	</section>
	<?php
	get_footer();
}

/**
 * Print FAQ items as question and answer pairs, with matching Google data.
 *
 * @param array<int,array{0:string,1:string}> $items Question, answer HTML.
 */
function dl_faq_list( $items ) {
	echo '<div class="dl-faq">';
	foreach ( $items as $item ) {
		printf( '<details><summary>%s</summary><div>%s</div></details>', esc_html( $item[0] ), wp_kses_post( $item[1] ) );
	}
	echo '</div>';
}
