<?php
/**
 * FAQs. Answers follow the shop's published terms of sale.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

$returns = esc_url( home_url( '/returns-refunds/' ) );
$terms   = esc_url( home_url( '/terms-and-conditions/' ) );
$track   = esc_url( home_url( '/order-tracking/' ) );

$groups = array(
	'Orders'            => array(
		array( 'How do I place an order?', '<p>Add the items you want to your cart and complete checkout on the website. You can also order on WhatsApp, by phone, or in person at our shop.</p>' ),
		array( 'When is my order confirmed?', '<p>An order is confirmed once you have approved the product and we have accepted the order. Please check the product name, size, model and price, along with your name, phone number and delivery location.</p>' ),
		array( 'Can I change or cancel my order?', '<p>Contact us as soon as possible. If the order has not been dispatched, we may be able to cancel or amend it. If it has already been dispatched, delivery and return costs may apply when the reason is a change of mind.</p>' ),
		array( 'What if an item becomes unavailable?', '<p>All orders are subject to availability. If we cannot supply a product you have confirmed and paid for, we will offer a suitable alternative, store credit or a refund. We will not substitute a materially different product without your approval.</p>' ),
		array( 'How do I track my order?', '<p>Use our <a href="' . $track . '">order tracking page</a> with your order number and billing email, or message us on WhatsApp.</p>' ),
	),
	'Delivery'          => array(
		array( 'Do you deliver outside Nairobi?', '<p>Yes. We deliver within Nairobi and to other parts of Kenya.</p>' ),
		array( 'How much is delivery?', '<p>Delivery charges vary by location. You will see the available options and their cost at checkout before you pay.</p>' ),
		array( 'How long will delivery take?', '<p>We will share the estimated delivery time for your order. Delivery can occasionally be affected by things like traffic, weather, courier delays or public holidays.</p>' ),
		array( 'What are your opening hours?', '<p>Monday to Saturday, 8:30am to 7:00pm. We are closed on Sundays.</p>' ),
		array( 'Can I collect my order from the shop?', '<p>Yes. Choose Shop Pickup at checkout and collect from Rasumal House, 4th floor, shop 14, Tom Mboya Street, Nairobi.</p>' ),
		array( 'What if I am not available when my order arrives?', '<p>Please make sure you or someone you authorise can receive the order. Additional delivery charges may apply if another delivery attempt is needed.</p>' ),
	),
	'Payment'           => array(
		array( 'What payment methods do you accept?', '<p>You can pay by M-Pesa at checkout. Cash on delivery is also available on eligible orders.</p>' ),
		array( 'Are your prices in Kenya Shillings?', '<p>Yes, all prices are in Kenya Shillings unless we state otherwise. Once your order is confirmed at an agreed price, a later general price change will not ordinarily affect it.</p>' ),
		array( 'Do I ever need to pay in advance?', '<p>Some orders may require advance payment or a deposit, and those orders may not be dispatched until payment is received.</p>' ),
	),
	'Returns & refunds' => array(
		array( 'Can I return a perfume I have opened?', '<p>Perfumes cannot be returned or exchanged because of a change of mind once opened, tested or sprayed. This includes when you simply do not like the scent. See our <a href="' . $returns . '">returns policy</a> for details.</p>' ),
		array( 'Can I return an unopened perfume?', '<p>We may consider it if the perfume is completely unopened with its cellophane and seals intact and it can be resold as new. Acceptance follows inspection, and transport costs may be yours.</p>' ),
		array( 'I received the wrong or a damaged item. What should I do?', '<p>Tell us as soon as possible and keep the product and all packaging. We may ask for photos or videos. Once confirmed, we will arrange a suitable remedy, such as a replacement, exchange or refund.</p>' ),
		array( 'Can I return a watch?', '<p>A change-of-mind return may be declined if the watch has been worn, resized, scratched, or is missing its packaging or accessories. Your rights for a genuinely defective or incorrect product are not affected.</p>' ),
		array( 'How do I start a return?', '<p>Please contact us before sending anything back. Returning a product does not automatically mean a refund or exchange has been approved, as it may need to be inspected first.</p>' ),
	),
	'Products'          => array(
		array( 'Are your perfumes original?', '<p>Yes, our perfumes are original.</p>' ),
		array( 'What is a tester perfume?', '<p>Tester perfumes are originally produced by fragrance houses for customers to sample and test in stores before buying the retail versions. They contain the same original fragrance as the retail bottles, with the same scent and quality. The main difference is usually the packaging: testers may come in plain boxes, have a tester label, or lack some of the presentation found in retail packaging.</p>' ),
		array( 'Can I smell a perfume before buying?', '<p>Where testers or samples are available, you are welcome to ask us about them at the shop. Availability is not guaranteed.</p>' ),
		array( 'Why does a perfume last differently on me?', '<p>Scent and longevity vary with skin chemistry, the number of sprays, temperature, humidity and how the perfume is stored. Keep your perfume away from heat and direct sunlight.</p>' ),
		array( 'The packaging looks different from the photo. Is that normal?', '<p>Manufacturers sometimes change packaging, and lighting or screen settings can affect how a photo looks. If a specific design or batch matters to you, please check with us before ordering.</p>' ),
	),
);

dl_help_open( 'faqs', 'Frequently Asked Questions', 'Quick answers about ordering, delivery, payment and returns.' );

foreach ( $groups as $group => $items ) {
	echo '<h2>' . esc_html( $group ) . '</h2>';
	dl_faq_list( $items );
}
echo '<p>For the complete rules, read our <a href="' . $terms . '">Terms &amp; Conditions</a>.</p>'; // phpcs:ignore WordPress.Security.EscapeOutput

// FAQ data for search engines, built from the same answers shown above.
$schema = array();
foreach ( $groups as $items ) {
	foreach ( $items as $item ) {
		$schema[] = array(
			'@type'          => 'Question',
			'name'           => $item[0],
			'acceptedAnswer' => array(
				'@type' => 'Answer',
				'text'  => wp_strip_all_tags( $item[1] ),
			),
		);
	}
}
echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $schema ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>';

dl_help_close();
