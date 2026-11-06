<?php
/**
 * Help Centre.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();

$topics = array(
	array( 'faqs', 'FAQs', 'Quick answers to the questions we hear most.', 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.500 9.500a2.500 2.500 0 1 1 3.500 2.300c-.7.300-1 .8-1 1.500M12 17h.01' ),
	array( 'delivery-information', 'Delivery Information', 'Where we deliver, charges and what to expect.', 'M3 7h11v9H3zM14 10h4l3 3v3h-7zM7.500 19a1.800 1.800 0 1 0 0-3.600 1.800 1.800 0 0 0 0 3.600zM17.500 19a1.800 1.800 0 1 0 0-3.600 1.800 1.800 0 0 0 0 3.600z' ),
	array( 'payment-options', 'Payment Options', 'The ways you can pay for your order.', 'M3 6h18v12H3zM3 10h18M7 14h3' ),
	array( 'returns-refunds', 'Returns & Refunds', 'What can be returned, and how refunds work.', 'M4 8h16v12H4zM4 8l3-4h10l3 4M9 13l-2 2 2 2M7 15h7a3 3 0 0 0 0-6' ),
	array( 'order-tracking', 'Track Your Order', 'Check the status of an order you have placed.', 'M12 21s7-6.500 7-11.500a7 7 0 1 0-14 0C5 14.500 12 21 12 21zM12 12a2.500 2.500 0 1 0 0-5 2.500 2.500 0 0 0 0 5z' ),
	array( 'terms-and-conditions', 'Terms & Conditions', 'The full terms of sale for every purchase.', 'M7 3h7l4 4v14H7zM14 3v4h4M10 12h6M10 16h6' ),
	array( 'privacy-policy', 'Privacy Policy', 'How we collect, use and protect your details.', 'M12 3l7 3v6c0 4.500-3 7.500-7 9-4-1.500-7-4.500-7-9V6zM9 12l2 2 4-4' ),
	array( 'contact-us', 'Contact Us', 'Reach us by WhatsApp, phone, email or in person.', 'M4 20l1.200-4A8 8 0 1 1 8 18.800z' ),
);
?>
<div class="dl-helphero dl-helphero--centre">
	<div class="dl-wrap">
		<?php woocommerce_breadcrumb(); ?>
		<h1>How can we help?</h1>
		<p>Find answers about orders, delivery, payment and returns.</p>
	</div>
</div>

<div class="dl-wrap dl-helpgrid">
	<?php foreach ( $topics as $topic ) : ?>
		<a class="dl-helpcard" href="<?php echo esc_url( home_url( '/' . $topic[0] . '/' ) ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="<?php echo esc_attr( $topic[3] ); ?>"/></svg>
			<h2><?php echo esc_html( $topic[1] ); ?></h2>
			<p><?php echo esc_html( $topic[2] ); ?></p>
		</a>
	<?php endforeach; ?>
</div>

<section class="dl-helpcta">
	<div class="dl-wrap dl-helpcta__in">
		<div>
			<h2>Talk to our team</h2>
			<p>Rasumal House, 4th floor, shop 14, Tom Mboya Street, Nairobi.</p>
		</div>
		<div class="dl-actions">
			<a class="dl-btn dl-btn--wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I need some help.' ) ); ?>" target="_blank" rel="noopener">Chat on WhatsApp</a>
			<a class="dl-btn dl-btn--outline" href="tel:<?php echo esc_attr( DL_PHONE ); ?>">Call <?php echo esc_html( DL_PHONE_DISPLAY ); ?></a>
		</div>
	</div>
</section>
<?php
get_footer();
