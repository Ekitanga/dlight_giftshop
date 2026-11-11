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

/**
 * What happens next for a new order: delivery, then payment. Used in emails and on the thank-you page.
 *
 * @param WC_Order $order Order.
 * @return string[]
 */
function dl_order_next_steps( $order ) {
	$how   = dl_order_delivery( $order );
	$cod   = 'cod' === $order->get_payment_method();
	$phone = $order->get_billing_phone();
	$lines = array();
	if ( 'pickup' === $how ) {
		$lines[] = 'We are getting your order ready. We will call or WhatsApp you on ' . $phone . ' when it is ready to collect.';
		$lines[] = 'Collect it at ' . DL_ADDRESS . ', Monday to Saturday, 8:30am to 7pm.';
	} elseif ( 'nairobi' === $how ) {
		$lines[] = 'We will call you on ' . $phone . ' to agree a delivery time to ' . dl_order_place( $order ) . '.';
	} elseif ( 'courier' === $how ) {
		$lines[] = 'We will send your order by Speedaf to ' . dl_order_place( $order ) . ', and share the parcel details with you on ' . $phone . '.';
		if ( $cod ) {
			// Pay on delivery outside Nairobi: the delivery fee goes to Speedaf first (commitment), the product is paid on arrival.
			$fee     = (float) $order->get_shipping_total();
			$lines[] = 'First, pay the ' . ( $fee > 0 ? dl_order_ksh( $fee ) . ' ' : '' ) . 'delivery fee to SPEEDAF (Paybill ' . DL_SPEEDAF_PAYBILL . ') as a commitment fee, then send us the Speedaf M-Pesa message on WhatsApp. We send your parcel once we receive it. See how to pay below.';
			$lines[] = 'When your parcel arrives, pay ' . dl_order_ksh( (float) $order->get_total() - $fee ) . ' for your order, by M-Pesa or cash. Do not pay for your order to the Speedaf paybill.';
			return $lines;
		}
		$lines[] = 'Your delivery fee covers Speedaf, so there is nothing more to pay on collection.';
	}
	$lines[] = $cod ? ( 'pickup' === $how ? 'Pay when you collect, by M-Pesa or cash.' : 'Pay when your order arrives, by M-Pesa or cash.' ) : ( $order->get_date_paid() ? 'Your payment has been received. Thank you.' : 'Your order will be confirmed once payment is received.' );
	return $lines;
}

// Speedaf's own M-Pesa paybill (not ours): pay-on-delivery customers outside Nairobi pay the delivery fee here first.
const DL_SPEEDAF_PAYBILL = '4040305';

/**
 * Does this order need the Speedaf delivery fee paid first (Speedaf delivery, pay on delivery)?
 *
 * @param WC_Order $order Order.
 * @return bool
 */
function dl_order_needs_speedaf_fee( $order ) {
	return 'courier' === dl_order_delivery( $order ) && 'cod' === $order->get_payment_method();
}

/**
 * "How to pay the Speedaf delivery fee" box (thank-you page and emails), or plain text.
 *
 * @param WC_Order $order Order.
 * @param bool     $plain Plain text.
 * @return string
 */
function dl_speedaf_box( $order, $plain = false ) {
	if ( ! dl_order_needs_speedaf_fee( $order ) ) {
		return '';
	}
	$fee  = dl_order_ksh( (float) $order->get_shipping_total() );
	$name = trim( $order->get_formatted_billing_full_name() );
	$rest = dl_order_ksh( (float) $order->get_total() - (float) $order->get_shipping_total() );
	if ( $plain ) {
		return "HOW TO PAY THE SPEEDAF DELIVERY FEE\n"
			. "M-Pesa > Lipa na M-Pesa > Pay Bill\n"
			. 'Business number: ' . DL_SPEEDAF_PAYBILL . "\n"
			. 'Account number: your full name' . ( $name ? ' (' . $name . ')' : '' ) . "\n"
			. 'Amount: ' . $fee . "\n"
			. "The M-Pesa confirmation shows SPEEDAF LOGISTICS KENYA LTD.\n"
			. 'THEN SEND US THAT M-PESA MESSAGE ON WHATSAPP: ' . dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I have paid the Speedaf delivery fee. Here is the M-Pesa message:' ) . "\nWe cannot see Speedaf payments, so we send your parcel once we receive your message.\n"
			. 'IMPORTANT: Paybill ' . DL_SPEEDAF_PAYBILL . ' belongs to SPEEDAF, not Dlight Giftshop. Pay ONLY the ' . $fee . ' delivery fee there. Do NOT pay for your order to this paybill. You pay ' . $rest . " for your order when the parcel arrives.\n\n";
	}
	return '<div class="dl-speedaf">'
		. '<h3>How to pay the Speedaf delivery fee</h3>'
		. '<p class="dl-speedaf__warn"><strong>This is SPEEDAF\'s paybill, not Dlight Giftshop\'s.</strong> Pay <strong>only the ' . esc_html( $fee ) . ' delivery fee</strong> here. <strong>Do not pay for your order to this paybill.</strong> You pay ' . esc_html( $rest ) . ' for your order when the parcel arrives.</p>'
		. '<table class="dl-speedaf__steps" cellspacing="0" cellpadding="0" role="presentation">'
		. '<tr><th>M-Pesa</th><td>Lipa na M-Pesa &rsaquo; Pay Bill</td></tr>'
		. '<tr><th>Business number</th><td><strong>' . esc_html( DL_SPEEDAF_PAYBILL ) . '</strong> (Speedaf)</td></tr>'
		. '<tr><th>Account number</th><td><strong>Your full name</strong>' . ( $name ? ' (' . esc_html( $name ) . ')' : '' ) . '</td></tr>'
		. '<tr><th>Amount</th><td><strong>' . esc_html( $fee ) . '</strong></td></tr>'
		. '</table>'
		. '<p class="dl-speedaf__note">The M-Pesa confirmation will show <strong>SPEEDAF LOGISTICS KENYA LTD</strong>.</p>'
		. '<p class="dl-speedaf__note"><strong>Then send us that M-Pesa message on WhatsApp.</strong> We cannot see Speedaf payments, so we send your parcel once we receive your message.</p>'
		. '<p class="dl-speedaf__cta"><a class="dl-btn dl-btn--wa" href="' . esc_attr( dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I have paid the Speedaf delivery fee. Here is the M-Pesa message:' ) ) . '">Send us the M-Pesa message on WhatsApp</a></p>'
		. '</div>';
}

/**
 * Plain "KSh 2,450" for emails and the thank-you page.
 *
 * @param float $amount Amount.
 * @return string
 */
function dl_order_ksh( $amount ) {
	return 'KSh ' . number_format( max( 0, (float) $amount ), 0 );
}

// Google Business Profile review link (owner's).
const DL_GOOGLE_REVIEW_URL = 'https://g.page/r/CfgiV75gTqpdEAI/review';

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
		$lines  = array();
		$review = false; // Completed orders also invite a Google review.
		switch ( $email->id ) {
			case 'customer_processing_order':
			case 'customer_on_hold_order':
				$lines = dl_order_next_steps( $order );
				break;
			case 'customer_completed_order':
				$lines[] = 'pickup' === $how ? 'Thank you for collecting your order.' : ( 'courier' === $how ? 'Your order is on its way with Speedaf.' : 'Your order has been delivered.' );
				$lines[] = 'We hope you love it. If anything is not right, tell us within 24 hours and we will help.';
				$review  = true;
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
		$wa    = dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I have a question about my order' );
		$fresh = in_array( $email->id, array( 'customer_processing_order', 'customer_on_hold_order' ), true );
		if ( $plain_text ) {
			echo "WHAT HAPPENS NEXT\n" . implode( "\n", $lines ) . "\n\n" . ( $fresh ? dl_speedaf_box( $order, true ) : '' ) . 'Questions? WhatsApp us: ' . $wa . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			if ( $review ) {
				echo 'Happy with your order? Please leave us a quick Google review: ' . DL_GOOGLE_REVIEW_URL . "\n\n"; // phpcs:ignore WordPress.Security.EscapeOutput
			}
			return;
		}
		echo '<div class="dl-next"><h2>What happens next</h2>';
		foreach ( $lines as $line ) {
			echo '<p>' . esc_html( $line ) . '</p>';
		}
		if ( $fresh ) {
			echo dl_speedaf_box( $order ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped inside.
		}
		echo '<p><a class="dl-btn" href="' . esc_attr( $wa ) . '">Questions? Chat with us on WhatsApp</a></p>';
		if ( $review ) {
			echo '<p>Happy with your order? A quick Google review helps other shoppers find us.</p>';
			echo '<p><a class="dl-btn" href="' . esc_url( DL_GOOGLE_REVIEW_URL ) . '">&#9733; Leave a Google review</a></p>';
		}
		echo '</div>';
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
			'Payment'  => $order->get_payment_method_title() . ( 'cod' === $order->get_payment_method() ? ( 'Courier (Speedaf)' === $how ? ' (customer pays the ' . dl_order_ksh( (float) $order->get_shipping_total() ) . ' delivery fee to Speedaf first, then ' . dl_order_ksh( (float) $order->get_total() - (float) $order->get_shipping_total() ) . ' on delivery)' : ' (collect payment on delivery)' ) : ( $order->get_date_paid() ? ' (paid)' : ' (not paid yet)' ) ),
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
			.dl-speedaf { margin: 12px 0; padding: 14px 16px; background: #ffffff; border: 2px solid #c0392b; border-radius: 6px; }
			.dl-speedaf h3 { margin: 0 0 8px; font-size: 16px; color: #111111; }
			.dl-speedaf__warn { margin: 0 0 10px; padding: 8px 10px; background: #fdecea; color: #8a1f14; border-radius: 4px; }
			.dl-speedaf__steps { width: 100%; margin: 0 0 8px; }
			.dl-speedaf__steps th { width: 130px; padding: 3px 10px 3px 0; text-align: left; color: #6f675c; font-weight: normal; vertical-align: top; }
			.dl-speedaf__steps td { padding: 3px 0; vertical-align: top; }
			.dl-speedaf__note { margin: 0 0 6px; font-size: 14px; }
			.dl-speedaf__cta { margin: 10px 0 0; }
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

/*
 * Orders waiting for payment. WooCommerce sends nothing while an order is "pending", so an M-Pesa
 * order whose payment never completes was invisible. Now the team is told straight away, and the
 * customer gets a reminder if the order is still unpaid after 20 minutes.
 */

/**
 * Send a branded email built from WooCommerce's own header, footer and styles.
 *
 * @param string $to      Recipient.
 * @param string $subject Subject.
 * @param string $heading Heading in the black band.
 * @param string $body    HTML body.
 * @return bool
 */
function dl_send_branded_email( $to, $subject, $heading, $body ) {
	$mailer = WC()->mailer();
	$html   = $mailer->wrap_message( $heading, $body );
	$email  = new WC_Email();
	return $mailer->send( $to, $subject, $email->style_inline( $html ), "Content-Type: text/html\r\n" );
}

/**
 * The order's items and total, as a simple table.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function dl_email_items( $order ) {
	$rows = '';
	foreach ( $order->get_items() as $item ) {
		$rows .= '<tr><td style="padding:6px 0;border-bottom:1px solid #eee;">' . esc_html( $item->get_name() ) . ' &times; ' . (int) $item->get_quantity() . '</td><td style="padding:6px 0;border-bottom:1px solid #eee;text-align:right;white-space:nowrap;">' . wp_kses_post( $order->get_formatted_line_subtotal( $item ) ) . '</td></tr>';
	}
	return '<table class="dl-facts" cellspacing="0" cellpadding="0" role="presentation" style="width:100%;">' . $rows . '<tr><td style="padding:8px 0;font-weight:bold;">Total</td><td style="padding:8px 0;font-weight:bold;text-align:right;">' . wp_kses_post( $order->get_formatted_order_total() ) . '</td></tr></table>';
}

/**
 * The link that opens the M-Pesa payment page for this order.
 *
 * @param WC_Order $order Order.
 * @return string
 */
function dl_order_pay_link( $order ) {
	return add_query_arg( array( 'transactionType' => 'checkout', 'order' => $order->get_id() ), $order->get_checkout_payment_url( true ) );
}

// The team: told at once about an order waiting for M-Pesa payment.
add_action(
	'woocommerce_checkout_order_processed',
	static function ( $order_id, $posted, $order ) {
		if ( ! $order instanceof WC_Order || 'mpesa' !== $order->get_payment_method() || $order->get_meta( '_dl_pending_alert' ) ) {
			return;
		}
		$order->update_meta_data( '_dl_pending_alert', time() );
		$order->save_meta_data();
		$phone = $order->get_billing_phone();
		$wa    = 'https://wa.me/' . preg_replace( '/\D+/', '', (string) $phone );
		$body  = '<p>A customer has placed an order and is paying by M-Pesa. It is <strong>not paid yet</strong>. If the payment does not come through, call or WhatsApp them to help.</p>'
			. '<div class="dl-next dl-next--team"><table class="dl-facts" cellspacing="0" cellpadding="0" role="presentation">'
			. '<tr><th>Customer</th><td>' . esc_html( $order->get_formatted_billing_full_name() ) . '</td></tr>'
			. '<tr><th>Phone</th><td><a href="tel:' . esc_attr( $phone ) . '">' . esc_html( $phone ) . '</a> &nbsp;&middot;&nbsp; <a href="' . esc_attr( $wa ) . '">WhatsApp</a></td></tr>'
			. '<tr><th>Where</th><td>' . esc_html( 'pickup' === dl_order_delivery( $order ) ? 'Collects at the shop' : dl_order_place( $order ) ) . '</td></tr>'
			. '</table></div>'
			. dl_email_items( $order )
			. '<p><a class="dl-btn" style="background:#111;" href="' . esc_url( $order->get_edit_order_url() ) . '">Open the order</a></p>'
			. '<p>You will get the usual "New order" email as soon as the payment is confirmed.</p>';
		$to = get_option( 'woocommerce_new_order_settings' )['recipient'] ?? get_option( 'admin_email' );
		dl_send_branded_email( $to ? $to : get_option( 'admin_email' ), 'Awaiting M-Pesa payment: order #' . $order->get_order_number() . ' (' . wp_strip_all_tags( $order->get_formatted_billing_full_name() ) . ')', 'Awaiting payment', $body );
		// Remind the customer if it is still unpaid in 20 minutes.
		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action( time() + 20 * MINUTE_IN_SECONDS, 'dl_pending_reminder', array( 'order_id' => $order->get_id() ), 'dlight' );
		}
	},
	20,
	3
);

// The customer: a friendly reminder with a pay link, the till number and WhatsApp.
add_action(
	'dl_pending_reminder',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->has_status( 'pending' ) || ! $order->get_billing_email() || $order->get_meta( '_dl_pending_reminded' ) ) {
			return;
		}
		$order->update_meta_data( '_dl_pending_reminded', time() );
		$order->save_meta_data();
		$wa   = dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I need help paying for my order' );
		$body = '<p>Hi ' . esc_html( $order->get_billing_first_name() ) . ',</p>'
			. '<p>Your order <strong>#' . esc_html( $order->get_order_number() ) . '</strong> is saved, but we have not received the M-Pesa payment yet. It only takes a minute to finish:</p>'
			. '<p><a class="dl-btn" style="background:#43b02a;" href="' . esc_url( dl_order_pay_link( $order ) ) . '">Pay ' . wp_strip_all_tags( $order->get_formatted_order_total() ) . ' with M-Pesa</a></p>'
			. '<div class="dl-next"><h2>No M-Pesa prompt?</h2><p>Pay to our Till Number <strong>8109502</strong> (DLIGHT GIFTSHOP) via Lipa na M-Pesa, Buy Goods and Services, then send us the M-Pesa message on WhatsApp and we will confirm your order.</p><p><a class="dl-btn" href="' . esc_attr( $wa ) . '">Chat with us on WhatsApp</a></p></div>'
			. dl_email_items( $order );
		dl_send_branded_email( $order->get_billing_email(), 'Your order #' . $order->get_order_number() . ' is waiting for payment', 'Complete your order', $body );
	}
);
