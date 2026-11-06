<?php
/**
 * Store knowledge: settings and the answers about delivery, payment and returns.
 * Wording follows the store's own terms of sale and help pages.
 *
 * @package dlight-assistant
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin settings.
 *
 * @return array
 */
function dla_options() {
	return wp_parse_args(
		(array) get_option( 'dla_options', array() ),
		array(
			'enabled'  => 1,
			'greeting' => "Hi, I'm Dlight AI. I can help you find the right perfume or watch, compare products and answer questions about delivery and payment.\n\nWhat are you shopping for today?",
			'whatsapp' => '254740611468',
			'notify'   => 'dlightgiftshop21@gmail.com',
		)
	);
}

/**
 * Built-in answers. Each entry: id, match patterns, answer, optional internal link.
 *
 * @return array
 */
function dla_default_answers() {
	return array(
		array(
			'id'   => 'order_status',
			'm'    => array( 'where is my order', 'track', 'tracking', 'order status', 'my order', 'parcel', 'not arrived', 'not received' ),
			'a'    => 'You can check your order on our tracking page with your order number and billing email. If you need more help, our team can look into it for you.',
			'link' => array( 'Track your order', '/order-tracking/' ),
		),
		array(
			'id'   => 'delivery_cost',
			'm'    => array( 'delivery cost', 'delivery fee', 'delivery charge', 'shipping cost', 'shipping fee', 'how much is delivery', 'how much delivery', 'cost of delivery', 'charge for delivery', 'cost to deliver', 'free delivery', 'free shipping' ),
			'a'    => 'Delivery charges vary by location. You will see the options for your location, and what each costs, at checkout before you pay.',
			'link' => array( 'Delivery information', '/delivery-information/' ),
		),
		array(
			'id'   => 'delivery_time',
			'm'    => array( 'how long', 'when will', 'delivery time', 'how fast', 'same day', 'next day', 'how soon' ),
			'need' => array( 'deliver', 'delivery', 'shipping', 'arrive', 'receive', 'get my', 'order' ),
			'a'    => 'We share an estimated delivery time for your order and make reasonable efforts to meet it. Traffic, weather, courier delays or public holidays can occasionally affect it.',
			'link' => array( 'Delivery information', '/delivery-information/' ),
		),
		array(
			'id'   => 'delivery_where',
			'm'    => array( 'do you deliver', 'can you deliver', 'deliver to', 'delivery to', 'delivery outside', 'outside nairobi', 'countrywide', 'upcountry', 'ship to', 'do you ship', 'delivery', 'shipping', 'deliver' ),
			'a'    => 'Yes, we deliver within Nairobi and to other parts of Kenya. The options and charges for your location are shown at checkout. You can also collect from our shop.',
			'link' => array( 'Delivery information', '/delivery-information/' ),
		),
		array(
			'id'   => 'pickup',
			'm'    => array( 'pick up', 'pickup', 'collect', 'collection' ),
			'a'    => 'Yes. Choose Shop Pickup at checkout and collect from Rasumal House, 4th floor, shop 14, Tom Mboya Street, Nairobi.',
			'link' => array( 'How to find us', '/contact-us/' ),
		),
		array(
			'id'   => 'cod',
			'm'    => array( 'cash on delivery', 'pay on delivery', 'cod', 'pay when', 'pay after', 'pay cash', 'cash' ),
			'a'    => 'Cash on delivery is available on eligible orders. If you choose it, please be ready to receive your order when it arrives.',
			'link' => array( 'Payment options', '/payment-options/' ),
		),
		array(
			'id'   => 'payment',
			'm'    => array( 'mpesa', 'm pesa', 'lipa', 'how do i pay', 'how can i pay', 'how to pay', 'payment', 'pay with', 'pay by', 'pay online', 'card', 'visa', 'mastercard', 'paybill', 'till number' ),
			'a'    => 'You can pay securely with M-Pesa at checkout. Cash on delivery is also available on eligible orders, and you can pay in person at our shop.',
			'link' => array( 'Payment options', '/payment-options/' ),
		),
		array(
			'id'   => 'return_perfume',
			'm'    => array( 'return perfume', 'return a perfume', 'return my perfume', 'exchange perfume', 'exchange a perfume', 'return the perfume', 'opened perfume', 'dont like the smell', 'do not like the smell', 'dont like the scent', 'do not like the scent', 'return fragrance' ),
			'a'    => 'Opened perfumes cannot be returned or exchanged because of a change of mind, including when you do not like the scent. We may consider a completely unopened perfume with its seals and packaging intact, subject to inspection. Faulty or incorrect items are handled separately.',
			'link' => array( 'Returns & refunds', '/returns-refunds/' ),
		),
		array(
			'id'   => 'return_wrong',
			'm'    => array( 'wrong item', 'wrong product', 'wrong order', 'damaged', 'broken', 'leaking', 'defective', 'faulty', 'not working', 'stopped working' ),
			'a'    => 'Sorry about that. Please contact us as soon as you can and keep the product and all its packaging. We may ask for photos or videos. Once confirmed, we will arrange a suitable remedy, such as a replacement, exchange or refund.',
			'link' => array( 'Contact us', '/contact-us/' ),
		),
		array(
			'id'   => 'return_general',
			'm'    => array( 'return', 'returns', 'refund', 'refunds', 'exchange', 'money back', 'warranty', 'guarantee' ),
			'a'    => 'Please contact us before sending anything back, because a product may need to be inspected first. Change-of-mind returns depend on the product being unused and in its original condition. Faulty or incorrect products are handled separately.',
			'link' => array( 'Returns & refunds', '/returns-refunds/' ),
		),
		array(
			'id'   => 'cancel',
			'm'    => array( 'cancel', 'cancel my order', 'cancel order', 'cancel the order', 'change my order', 'change order', 'amend', 'edit my order' ),
			'a'    => 'Please contact us as soon as possible. If your order has not been dispatched, we may be able to cancel or change it. After dispatch, delivery and return costs may apply if you have changed your mind.',
			'link' => array( 'Contact us', '/contact-us/' ),
		),
		array(
			'id'   => 'location',
			'm'    => array( 'where are you', 'your location', 'where is your shop', 'where is the shop', 'your shop', 'your address', 'address', 'located', 'location', 'physical shop', 'visit you', 'visit your', 'directions', 'uko wapi', 'mko wapi' ),
			'a'    => 'You will find us at Rasumal House, 4th floor, shop 14, Tom Mboya Street (opposite Imenti House), Nairobi.',
			'link' => array( 'Contact & directions', '/contact-us/' ),
		),
		array(
			'id'   => 'hours',
			'm'    => array( 'opening hours', 'open today', 'open now', 'what time', 'when are you open', 'closing time', 'close today', 'are you open', 'working hours', 'business hours', 'open on', 'open sunday', 'open on sunday', 'open saturday', 'open on saturday', 'saa ngapi', 'mnafungua', 'mnafunga' ),
			'a'    => 'We are open Monday to Saturday, 8:30am to 7:00pm. We are closed on Sundays. You will find us at Rasumal House, 4th floor, shop 14, Tom Mboya Street, Nairobi.',
			'link' => array( 'Contact us', '/contact-us/' ),
		),
		array(
			'id'   => 'contact',
			'm'    => array( 'contact', 'phone number', 'call you', 'email', 'talk to', 'speak to', 'human', 'real person', 'customer care', 'agent', 'someone', 'attendant', 'whatsapp' ),
			'a'    => 'You can reach our team on +254 740 611 468 or dlightgiftshop21@gmail.com, or visit us at Rasumal House on Tom Mboya Street.',
			'link' => array( 'Contact us', '/contact-us/' ),
		),
		array(
			'id'   => 'tester',
			'm'    => array( 'what is a tester', 'what are tester', 'what are testers', 'what does tester', 'tester mean', 'is tester', 'are testers', 'tester vs', 'difference between tester', 'tester and original', 'original and tester', 'why tester', 'why is tester', 'tester cheaper', 'testers cheaper', 'what is tester', 'whats a tester', 'difference between a tester' ),
			'a'    => 'Tester perfumes are originally produced by fragrance houses for customers to sample and test in stores before buying the retail versions. They contain the same original fragrance as the retail bottles, with the same scent and quality. The main difference is usually the packaging: testers may come in plain boxes, have a tester label, or lack some of the presentation found in retail packaging.',
			'link' => array( 'See tester perfumes', '/product-category/beauty-personal-care/perfumes/tester-perfumes/' ),
		),
		array(
			'id'   => 'authentic',
			'm'    => array( 'genuine', 'authentic', 'fake', 'original or', 'real or', 'is it real', 'is it original', 'are they original', 'are your perfumes original', 'are they genuine', 'replica', 'counterfeit', 'copy', 'copies', 'imitation' ),
			'a'    => 'Yes, our perfumes are original. We also sell tester perfumes, which contain the same original fragrance as the retail bottles; the difference is usually the packaging. Ask me "what is a tester?" to learn more.',
			'link' => array( 'See original perfumes', '/product-category/beauty-personal-care/perfumes/original-perfumes/' ),
		),
		array(
			'id'   => 'allergy',
			'm'    => array( 'allergy', 'allergic', 'allergies', 'sensitive skin', 'pregnant', 'asthma', 'rash', 'irritation', 'safe for' ),
			'a'    => 'I cannot give medical advice. Perfumes contain ingredients that can affect people differently, so if you have an allergy or sensitivity, please review the product information and check with a medical professional before use.',
		),
		array(
			'id'   => 'privacy',
			'm'    => array( 'privacy', 'my data', 'personal data', 'are you a robot', 'dlight ai', 'are you human', 'are you real', 'are you ai', 'are you a bot', 'who are you', 'what are you' ),
			'a'    => 'I am Dlight AI, an automated shopping assistant for Dlight Giftshop. I answer from the store\'s own products and policies, and our team is here for anything I cannot help with. Chats may be saved to help us improve.',
			'link' => array( 'Privacy policy', '/privacy-policy/' ),
		),
		array(
			'id'   => 'account',
			'm'    => array( 'my account', 'password', 'login', 'log in', 'sign in', 'register', 'create account' ),
			'a'    => 'You can sign in, register or reset your password on the My Account page.',
			'link' => array( 'My account', '/my-account/' ),
		),
	);
}

/**
 * Answers added by the shop owner in the admin screen. They take priority over built-in ones.
 *
 * @return array
 */
function dla_custom_answers() {
	return (array) get_option( 'dla_custom_answers', array() );
}
