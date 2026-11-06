<?php
/**
 * Order emails in the Dlight style: black and gold, with a short "what happens next" for the
 * customer and the customer's contact and delivery details at the top of the team's emails.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

/**
 * How the order is delivered: 'pickup', 'nairobi' or 'courier'.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function dl_order_delivery( $order ) {
	foreach ( $order->get_shipping_methods() as $item ) {
		$id    = (string) $item->get_method_id();
		$label = strtolower( (string) $item->get_name() );
		if ( 0 === strpos( $id, 'local_pickup' ) || 0 === strpos( $id, 'pickup_location' ) || false !== strpos( $label, 'pickup' ) ) {
			return 'pickup';
		}
		if ( false !== strpos( $label, 'nairobi' ) && false === strpos( $label, 'outside' ) ) {
			return 'nairobi';
		}
		return 'courier';
	}
	return '';
}

/**
 * Where the order is going, in one line.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function dl_order_place( $order ) {
	$area   = (string) $order->get_meta( '_dl_area' );
	$parts  = array_filter( array( $order->get_billing_address_1(), $area && 'Other' !== $area ? $area : $order->get_billing_city() ) );
	$states = WC()->countries->get_states( 'KE' );
	$county = $states[ $order->get_billing_state() ] ?? $order->get_billing_state();
	if ( $county && false === stripos( implode( ' ', $parts ), (string) $county ) ) {
		$parts[] = $county;
	}
	return implode( ', ', $parts );
}

/**
 * A WhatsApp link about this order.
 *
 * @param WC_Order $order Order.
 * @param string   $text  Opening line.
 * @return string
 */
function dl_order_whatsapp( $order, $text ) {
	return dl_whatsapp_url( $text . ' (order #' . $order->get_order_number() . ')' );
}

// The customer's "what happens next", above the order details.
add_action(
	'woocommerce_email_before_order_table',
	static function ( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $sent_to_admin || ! $order instanceof WC_Order || ! $email ) {
			return;
		}
		$how   = dl_order_delivery( $order );
		$cod   = 'cod' === $order->get_payment_method();
		$phone = $order->get_billing_phone();
		$lines = array();
		switch ( $email->id ) {
			case 'customer_processing_order':
			case 'customer_on_hold_order':
				if ( 'pickup' === $how ) {
					$lines[] = 'We are getting your order ready. We will call or WhatsApp you on ' . $phone . ' when it is ready to collect.';
					$lines[] = 'Collect it at ' . DL_ADDRESS . ', Monday to Saturday, 8:30am to 7pm.';
				} elseif ( 'nairobi' === $how ) {
					$lines[] = 'We will call you on ' . $phone . ' to agree a delivery time to ' . dl_order_place( $order ) . '.';
				} elseif ( 'courier' === $how ) {
					$lines[] = 'We will send your order by Speedaf to ' . dl_order_place( $order ) . ', and share the parcel details with you on ' . $phone . '.';
					$lines[] = 'Speedaf\'s parcel fee is paid to Speedaf before delivery.';
				}
				$lines[] = $cod ? ( 'pickup' === $how ? 'Pay when you collect, by M-Pesa or cash.' : 'Pay when your order arrives, by M-Pesa or cash.' ) : ( $order->get_date_paid() ? 'Your payment has been received. Thank you.' : 'Your order will be confirmed once payment is received.' );
				break;
			case 'customer_completed_order':
				$lines[] = 'pickup' === $how ? 'Thank you for collecting your order.' : ( 'courier' === $how ? 'Your order is on its way with Speedaf.' : 'Your order has been delivered.' );
				$lines[] = 'We hope you love it. If anything is not right, tell us within 24 hours and we will help.';
				break;
			case 'customer_cancelled_order':
				$lines[] = 'Your order #' . $order->get_order_number() . ' has been cancelled. If you did not ask for this, or would still like the items, reply to this email or WhatsApp us and we will sort it out.';
				break;
			case 'customer_failed_order':
				$lines[] = 'Your payment for order #' . $order->get_order_number() . ' did not go through, so the order is not confirmed yet. You can try again, or WhatsApp us and we will help you complete it.';
				break;
		}
		if ( ! $lines ) {
			return;
		}
		$wa = dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I have a question about my order' );
		if ( $plain_text ) {
			echo "WHAT HAPPENS NEXT\n" . implode( "\n", $lines ) . "\nQuestions? WhatsApp us: " . $wa . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}
		echo '<div class="dl-next"><h2>What happens next</h2>';
		foreach ( $lines as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}
		echo '<p><a class="dl-btn" href="' . esc_attr( $wa ) . '">Questions? Chat with us on WhatsApp</a></p></div>';
	},
	5,
	4
);

// The team's emails: who to call and where it is going, before anything else.
add_action(
	'woocommerce_email_before_order_table',
	static function ( $order, $sent_to_admin, $plain_text ) {
		if ( ! $sent_to_admin || ! $order instanceof WC_Order ) {
			return;
		}
		$how   = array( 'pickup' => 'Shop pickup', 'nairobi' => 'Delivery within Nairobi', 'courier' => 'Courier (Speedaf)' )[ dl_order_delivery( $order ) ] ?? $order->get_shipping_method();
		$phone = $order->get_billing_phone();
		$wa    = 'https://wa.me/' . preg_replace( '/\D+/', '', (string) $phone );
		$rows  = array(
			'Customer' => trim( $order->get_formatted_billing_full_name() ),
			'Phone'    => $phone,
			'Delivery' => $how,
			'Where'    => 'Shop pickup' === $how ? 'Collects at the shop' : dl_order_place( $order ),
			// Cash on delivery orders count as "paid" in WooCommerce once processing, so check the method first.
			'Payment'  => $order->get_payment_method_title() . ( 'cod' === $order->get_payment_method() ? ' (collect payment on delivery)' : ( $order->get_date_paid() ? ' (paid)' : ' (not paid yet)' ) ),
			'Note'     => $order->get_customer_note(),
		);
		$rows  = array_filter( $rows );
		if ( $plain_text ) {
			foreach ( $rows as $k => $v ) {
				echo esc_html( $k . ': ' . $v ) . "\n";
			}
			echo "\n";
			return;
		}
		echo '<div class="dl-next dl-next--team"><table class="dl-facts" cellspacing="0" cellpadding="0" role="presentation">';
		foreach ( $rows as $k => $v ) {
			$cell = esc_html( $v );
			if ( 'Phone' === $k ) {
				$cell = '<a href="tel:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a> &nbsp;·&nbsp; <a href="' . esc_attr( $wa ) . '">WhatsApp</a>';
			}
			echo '<tr><th>' . esc_html( $k ) . '</th><td>' . $cell . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput -- cell escaped above.
		}
		echo '</table></div>';
	},
	5,
	3
);

// Brand styles on top of WooCommerce's own email styles.
add_filter(
	'woocommerce_email_styles',
	static function ( $css ) {
		return $css . '
			#wrapper { padding-top: 24px; }
			#template_header_image img { width: 84px; height: auto; }
			#template_container { border: 0 !important; border-radius: 12px !important; box-shadow: 0 2px 10px rgba(0,0,0,.06) !important; overflow: hidden; }
			#template_header { background-color: #111111 !important; border-radius: 12px 12px 0 0 !important; }
			#template_header h1 { color: #d8bd86 !important; font-family: Georgia, "Times New Roman", serif !important; font-size: 26px !important; text-align: center !important; text-shadow: none !important; }
			#body_content_inner { font-size: 15px !important; line-height: 1.6 !important; color: #1c1a17 !important; }
			h2 { color: #111111 !important; font-family: Georgia, "Times New Roman", serif !important; }
			a { color: #7d6233; }
			.dl-next { margin: 0 0 24px; padding: 16px 18px; background: #fbf8f1; border-left: 3px solid #c0a16b; border-radius: 6px; }
			.dl-next h2 { margin: 0 0 8px; font-size: 18px; }
			.dl-next p { margin: 0 0 8px; }
			.dl-btn { display: inline-block; margin-top: 4px; padding: 10px 16px; background: #128c4a; color: #ffffff !important; border-radius: 6px; font-weight: bold; text-decoration: none; }
			.dl-facts { width: 100%; }
			.dl-facts th { width: 90px; padding: 4px 10px 4px 0; text-align: left; color: #6f675c; font-weight: normal; vertical-align: top; }
			.dl-facts td { padding: 4px 0; font-weight: bold; vertical-align: top; }
			#template_footer #credit { color: #6f675c !important; font-size: 12.5px !important; line-height: 1.6 !important; }
			#template_footer #credit a { color: #7d6233 !important; }
		';
	}
);

// No WooCommerce mobile-app advert at the bottom of the team's emails.
add_action(
	'woocommerce_email',
	static function ( $mailer ) {
		$new_order = $mailer->emails['WC_Email_New_Order'] ?? null;
		if ( $new_order ) {
			remove_action( 'woocommerce_email_footer', array( $new_order, 'mobile_messaging' ), 9 );
		}
	}
);
