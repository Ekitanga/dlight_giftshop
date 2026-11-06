<?php
/**
 * Checkout made for Kenyan shoppers: three short steps (your details, delivery, payment),
 * delivery chosen as cards, Nairobi areas in a searchable list, and a quiet bot check.
 * WooCommerce's own checkout still processes the order, so payment plugins work unchanged.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

const DL_NAIROBI = 'KE30'; // WooCommerce's code for Nairobi County.

/**
 * Areas and estates in Nairobi County, for the delivery list.
 *
 * @return string[]
 */
function dl_nairobi_areas() {
	$areas = array(
		'CBD (Town)', 'Upper Hill', 'Ngara', 'Pangani', 'Parklands', 'Highridge', 'Westlands', 'Spring Valley', 'Loresho', 'Kitisuru',
		'Muthaiga', 'Runda', 'Gigiri', 'Rosslyn', 'Nyari', 'Kileleshwa', 'Kilimani', 'Lavington', 'Hurlingham', 'Yaya Centre',
		'Valley Arcade', 'Riverside', 'Kangemi', 'Mountain View', 'Uthiru', 'Kawangware', 'Dagoretti', 'Riruta', 'Satellite', 'Waithaka',
		'Karen', 'Lang\'ata', 'Hardy', 'Bomas', 'Otiende', 'Ngong Road', 'Adams Arcade', 'Jamhuri', 'Woodley', 'Kibera', 'Madaraka',
		'Nairobi West', 'South B', 'South C', 'Mugoya', 'Industrial Area', 'Imara Daima', 'Embakasi', 'Pipeline', 'Nyayo Estate', 'Fedha',
		'Tassia', 'Utawala', 'Mihang\'o', 'Ruai', 'Kamulu', 'Donholm', 'Savannah', 'Umoja', 'Kayole', 'Komarock', 'Saika', 'Njiru',
		'Mwiki', 'Kasarani', 'Clay City', 'Hunters', 'Sunton', 'Seasons', 'Mirema', 'Roysambu', 'Zimmerman', 'Garden Estate', 'Thome',
		'Marurui', 'Githurai 44', 'Kahawa West', 'Kamiti', 'Ruaraka', 'Baba Dogo', 'Lucky Summer', 'Mathare', 'Huruma', 'Kariobangi',
		'Dandora', 'Eastleigh', 'Buruburu', 'Jericho', 'Makadara', 'Hamza', 'Harambee', 'Mbotela', 'Kaloleni', 'Makongeni', 'Shauri Moyo',
		'Ziwani', 'Kariokor', 'Starehe', 'JKIA', 'Lower Kabete', 'Mlango Kubwa', 'Kitengela Road (Nairobi side)',
	);
	sort( $areas, SORT_NATURAL | SORT_FLAG_CASE );
	return $areas;
}

/**
 * True when the shopper picked shop pickup.
 *
 * @param string $method Chosen shipping rate ID.
 * @return bool
 */
function dl_is_pickup( $method ) {
	return 0 === strpos( (string) $method, 'local_pickup' ) || 0 === strpos( (string) $method, 'pickup_location' );
}

/**
 * The chosen delivery method, from the form while checking out, else from the session.
 *
 * @return string
 */
function dl_chosen_method() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only; WooCommerce verifies the checkout nonce.
	if ( isset( $_POST['shipping_method'][0] ) ) {
		return sanitize_text_field( wp_unslash( $_POST['shipping_method'][0] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	$chosen = WC()->session ? (array) WC()->session->get( 'chosen_shipping_methods', array() ) : array();
	return (string) ( $chosen[0] ?? '' );
}

// Only Kenyan addresses, so the county list and delivery options always make sense.
add_filter( 'woocommerce_countries_allowed_countries', static fn( $c ) => isset( $c['KE'] ) ? array( 'KE' => $c['KE'] ) : $c );
add_filter( 'woocommerce_countries_shipping_countries', static fn( $c ) => isset( $c['KE'] ) ? array( 'KE' => $c['KE'] ) : $c );
add_filter( 'default_checkout_billing_country', static fn() => 'KE' );
add_filter( 'default_checkout_billing_state', static fn( $s ) => $s ? $s : DL_NAIROBI );

// One address only: delivery goes to the details given at checkout.
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );

// Kenyan addresses: no postcode; town and street are checked by the delivery choice, below.
add_filter(
	'woocommerce_get_country_locale',
	static function ( $locale ) {
		$locale['KE']['state']     = array( 'label' => 'County', 'required' => true );
		$locale['KE']['city']      = array( 'label' => 'Town', 'required' => false );
		$locale['KE']['address_1'] = array( 'label' => 'Building, street or landmark', 'required' => false, 'placeholder' => 'e.g. Kilimani, Argwings Kodhek Rd, Silver Court, Hse 4' );
		$locale['KE']['address_2'] = array( 'required' => false, 'hidden' => true );
		$locale['KE']['postcode']  = array( 'required' => false, 'hidden' => true );
		return $locale;
	},
	20
);

add_filter(
	'woocommerce_checkout_fields',
	static function ( $fields ) {
		$b = $fields['billing'];
		unset( $b['billing_company'], $b['billing_address_2'], $b['billing_postcode'] );

		$b['billing_first_name'] = array_merge(
			$b['billing_first_name'],
			array( 'label' => 'Full name', 'placeholder' => 'e.g. Jane Wanjiku', 'class' => array( 'form-row-wide' ), 'priority' => 10, 'autocomplete' => 'name' )
		);
		$b['billing_last_name']  = array_merge( $b['billing_last_name'], array( 'required' => false, 'class' => array( 'dl-hidden' ), 'priority' => 11 ) );
		$b['billing_phone']      = array_merge(
			$b['billing_phone'],
			array( 'label' => 'Phone number (M-Pesa)', 'placeholder' => 'e.g. 0712 345 678', 'required' => true, 'class' => array( 'form-row-wide' ), 'priority' => 20, 'custom_attributes' => array( 'inputmode' => 'tel' ) )
		);
		$b['billing_email']      = array_merge(
			$b['billing_email'],
			array( 'label' => 'Email, for your receipt', 'placeholder' => 'you@example.com', 'required' => false, 'class' => array( 'form-row-wide' ), 'priority' => 30 )
		);
		$b['billing_country']    = array_merge( $b['billing_country'], array( 'priority' => 40, 'class' => array( 'dl-hidden' ) ) );
		$b['billing_state']      = array_merge( $b['billing_state'], array( 'label' => 'County', 'class' => array( 'form-row-wide', 'dl-row-county' ), 'priority' => 50 ) );
		$b['billing_dl_area']    = array(
			'type'     => 'select',
			'label'    => 'Area in Nairobi',
			'required' => false,
			'class'    => array( 'form-row-wide', 'dl-row-area' ),
			'priority' => 60,
			'options'  => array( '' => 'Choose your area' ) + array_combine( dl_nairobi_areas(), dl_nairobi_areas() ) + array( 'Other' => 'Other area (write it below)' ),
		);
		$b['billing_city']       = array_merge( $b['billing_city'], array( 'label' => 'Town', 'placeholder' => 'Start typing your town', 'required' => false, 'class' => array( 'form-row-wide', 'dl-row-town' ), 'priority' => 70, 'custom_attributes' => array( 'list' => 'dl-towns', 'autocomplete' => 'off' ) ) );
		$b['billing_address_1']  = array_merge( $b['billing_address_1'], array( 'label' => 'Building, street or landmark', 'required' => false, 'class' => array( 'form-row-wide', 'dl-row-address' ), 'priority' => 80 ) );
		$fields['billing']       = $b;

		if ( isset( $fields['order']['order_comments'] ) ) {
			$fields['order']['order_comments']['label']       = 'Note for us (optional)';
			$fields['order']['order_comments']['placeholder'] = 'e.g. gift wrapping, best time to call, gate colour';
		}
		return $fields;
	},
	30
);

/**
 * Delivery choices as cards. The inputs are WooCommerce's own shipping inputs, so totals update as usual.
 *
 * @return string
 */
function dl_delivery_options_html() {
	$packages = WC()->shipping() ? WC()->shipping()->get_packages() : array();
	$rates    = isset( $packages[0]['rates'] ) ? $packages[0]['rates'] : array();
	$chosen   = dl_chosen_method();
	if ( ! $rates ) {
		return '<div class="dl-delivery-options"><p class="dl-delivery__none">Choose your county to see delivery options.</p></div>';
	}
	if ( ! isset( $rates[ $chosen ] ) ) {
		$chosen = (string) key( $rates );
	}
	$html = '<div class="dl-delivery-options" role="radiogroup" aria-label="Delivery">';
	foreach ( $rates as $id => $rate ) {
		$cost  = (float) $rate->get_cost();
		$price = $cost > 0 ? wc_price( $cost ) : '<b class="dl-free">Free</b>';
		if ( dl_is_pickup( $id ) ) {
			$title = 'Pick up at our shop';
			$note  = esc_html( DL_ADDRESS ) . '. Mon to Sat, 8:30am to 7pm.';
		} elseif ( false !== stripos( $rate->get_label(), 'nairobi' ) && false === stripos( $rate->get_label(), 'outside' ) ) {
			$title = 'Delivery within Nairobi';
			$note  = 'Brought to your door. We call you to agree a delivery time.';
		} else {
			$title = 'Outside Nairobi via Speedaf';
			$note  = 'Sent by Speedaf to the Speedaf office nearest you.<span class="dl-option__alert"><b>Please note:</b> Speedaf\'s own parcel fee is separate. You pay it to Speedaf before delivery, and we will send you the details by SMS or WhatsApp.</span>';
		}
		$input_id = 'shipping_method_0_' . sanitize_title( $id );
		$html    .= sprintf(
			'<label class="dl-option%5$s" for="%1$s"><input type="radio" name="shipping_method[0]" data-index="0" id="%1$s" value="%2$s" class="shipping_method"%3$s><span class="dl-option__body"><span class="dl-option__head"><strong>%6$s</strong><span class="dl-option__price">%4$s</span></span><span class="dl-option__note">%7$s</span></span></label>',
			esc_attr( $input_id ),
			esc_attr( $id ),
			checked( $id, $chosen, false ),
			wp_kses_post( $price ),
			$id === $chosen ? ' is-on' : '',
			esc_html( $title ),
			wp_kses_post( $note )
		);
	}
	return $html . '</div>';
}

// The delivery cards refresh with the order totals whenever the county or choice changes.
add_filter(
	'woocommerce_update_order_review_fragments',
	static function ( $fragments ) {
		$fragments['.dl-delivery-options'] = dl_delivery_options_html();
		return $fragments;
	}
);

/**
 * Normalise a Kenyan mobile number to 2547XXXXXXXX / 2541XXXXXXXX, or '' if it is not one.
 *
 * @param string $raw Number as typed.
 * @return string
 */
function dl_ke_phone( $raw ) {
	$d = preg_replace( '/\D+/', '', (string) $raw );
	if ( preg_match( '/^(?:254|0)?([17]\d{8})$/', $d, $m ) ) {
		return '254' . $m[1];
	}
	return '';
}

// Full name into first and last name, a tidy phone number, and the Nairobi area as the town.
add_filter(
	'woocommerce_checkout_posted_data',
	static function ( $data ) {
		$full = trim( preg_replace( '/\s+/', ' ', (string) ( $data['billing_first_name'] ?? '' ) ) );
		if ( $full && empty( $data['billing_last_name'] ) ) {
			$parts                      = explode( ' ', $full, 2 );
			$data['billing_first_name'] = $parts[0];
			$data['billing_last_name']  = $parts[1] ?? '';
		}
		$phone = dl_ke_phone( $data['billing_phone'] ?? '' );
		if ( $phone ) {
			$data['billing_phone'] = '+' . $phone;
		}
		$data['billing_country'] = 'KE';
		$area                    = (string) ( $data['billing_dl_area'] ?? '' );
		if ( DL_NAIROBI === ( $data['billing_state'] ?? '' ) && $area && 'Other' !== $area ) {
			$data['billing_city'] = $area . ', Nairobi';
		} elseif ( DL_NAIROBI === ( $data['billing_state'] ?? '' ) && empty( $data['billing_city'] ) ) {
			$data['billing_city'] = 'Nairobi';
		}
		return $data;
	}
);

// Checks that depend on the delivery choice, plus the bot trap.
add_action(
	'woocommerce_after_checkout_validation',
	static function ( $data, $errors ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce has verified the checkout nonce.
		$trap = isset( $_POST['dl_website'] ) ? (string) wp_unslash( $_POST['dl_website'] ) : '';
		$age  = isset( $_POST['dl_t'] ) ? time() - (int) $_POST['dl_t'] : 999;
		// phpcs:enable
		if ( '' !== $trap || $age < 3 ) {
			$errors->add( 'dl_bot', 'Sorry, we could not place this order. Please try again, or order on WhatsApp.' );
			return;
		}
		if ( empty( $data['billing_first_name'] ) ) {
			$errors->add( 'dl_name', 'Please enter your <strong>full name</strong>.' );
		}
		if ( ! dl_ke_phone( $data['billing_phone'] ?? '' ) ) {
			$errors->add( 'dl_phone', 'Please enter a Kenyan <strong>phone number</strong>, for example 0712 345 678.' );
		}
		$method = dl_chosen_method();
		if ( dl_is_pickup( $method ) ) {
			return;
		}
		if ( DL_NAIROBI === ( $data['billing_state'] ?? '' ) ) {
			// The area is needed; the building or landmark is optional (we confirm it on the phone).
			if ( empty( $data['billing_dl_area'] ) ) {
				$errors->add( 'dl_area', 'Please choose your <strong>area in Nairobi</strong>.' );
			}
		} elseif ( empty( $data['billing_city'] ) ) {
			$errors->add( 'dl_town', 'Please enter your <strong>town</strong>, so we send your parcel to the right Speedaf office.' );
		}
	},
	10,
	2
);

// The bot trap: a field people never see, and the time the form was opened.
add_action(
	'woocommerce_review_order_before_submit',
	static function () {
		echo '<div class="dl-hidden" aria-hidden="true"><label>Leave this empty <input type="text" name="dl_website" value="" tabindex="-1" autocomplete="off"></label><input type="hidden" name="dl_t" value="' . esc_attr( (string) time() ) . '"></div>';
	}
);

// Keep the Nairobi area with the order, and show it to the team.
add_action(
	'woocommerce_checkout_create_order',
	static function ( $order, $data ) {
		if ( ! empty( $data['billing_dl_area'] ) ) {
			$order->update_meta_data( '_dl_area', sanitize_text_field( $data['billing_dl_area'] ) );
		}
	},
	10,
	2
);
add_action(
	'woocommerce_admin_order_data_after_billing_address',
	static function ( $order ) {
		$area = $order->get_meta( '_dl_area' );
		if ( $area ) {
			echo '<p><strong>Nairobi area:</strong> ' . esc_html( $area ) . '</p>';
		}
	}
);

// M-Pesa first: most shoppers pay that way.
add_filter(
	'woocommerce_available_payment_gateways',
	static function ( $gateways ) {
		if ( isset( $gateways['mpesa'] ) && is_checkout() ) {
			$gateways = array( 'mpesa' => $gateways['mpesa'] ) + $gateways;
		}
		return $gateways;
	}
);

// Clear headings for the order summary and payment.
add_filter( 'woocommerce_order_button_text', static fn() => 'Place order' );
add_action( 'woocommerce_review_order_before_payment', static fn() => print '<h2 class="dl-step"><span>3</span>Payment</h2>' );

// The checkout script, only on the checkout page, after WooCommerce's own.
add_action(
	'wp_enqueue_scripts',
	static function () {
		if ( is_checkout() && ! is_order_received_page() ) {
			wp_enqueue_script( 'dlight-checkout', dl_asset_url( '/assets/js/checkout.js' ), array( 'jquery', 'wc-checkout' ), null, true );
			$states = WC()->countries->get_states( 'KE' );
			wp_add_inline_script( 'dlight-checkout', 'window.DL_TOWNS=' . wp_json_encode( dl_county_towns() ) . ';window.DL_COUNTIES=' . wp_json_encode( array_map( static fn( $n ) => str_replace( ' County', '', html_entity_decode( $n ) ), $states ) ) . ';', 'before' );
			wp_enqueue_script( 'selectWoo' );
			wp_enqueue_style( 'select2' );
		}
	},
	30
);

// "Your order" reads better as "Order summary".
add_filter(
	'gettext_woocommerce',
	static function ( $text, $original ) {
		return 'Your order' === $original && function_exists( 'is_checkout' ) && is_checkout() ? 'Order summary' : $text;
	},
	10,
	2
);

// The cart shows items and the total before delivery; delivery is chosen at checkout.
add_filter( 'woocommerce_cart_ready_to_calc_shipping', static fn( $ready ) => function_exists( 'is_cart' ) && is_cart() ? false : $ready );

/*
 * M-Pesa payment page. The M-Pesa plugin's buttons and payment logic stay exactly as they are;
 * this adds a clear card with the amount and three short steps, and hides the plugin's long text.
 */
add_action(
	'woocommerce_receipt_mpesa',
	static function ( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}
		$phone = dl_ke_phone( $order->get_billing_phone() );
		$show  = $phone ? '0' . substr( $phone, 3, 3 ) . ' ' . substr( $phone, 6, 3 ) . ' ' . substr( $phone, 9 ) : $order->get_billing_phone();
		?>
		<div class="dl-mpay">
			<p class="dl-mpay__brand"><span>M-PESA</span> Lipa na M-Pesa</p>
			<h2 class="dl-mpay__amount">Pay <?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></h2>
			<p class="dl-mpay__meta">Order #<?php echo esc_html( $order->get_order_number() ); ?> &middot; payment request to <strong><?php echo esc_html( $show ); ?></strong></p>
			<ol class="dl-mpay__steps">
				<li>Tap <strong>Send M-Pesa request</strong>.</li>
				<li>Enter your M-Pesa PIN on your phone.</li>
				<li>When you get the M-Pesa message, tap <strong>I have paid</strong>.</li>
			</ol>
			<?php
			// The plugin shows its buttons only on the page it redirects to after checkout. Coming from
			// a "pay for this order" link, offer the way into that page.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( ! isset( $_GET['transactionType'] ) || 'checkout' !== $_GET['transactionType'] ) {
				echo '<p><a class="dl-mpay__start" href="' . esc_url( add_query_arg( array( 'transactionType' => 'checkout', 'order' => $order->get_id() ), $order->get_checkout_payment_url( true ) ) ) . '">Pay now</a></p>';
			}
			?>
			<div class="dl-mpesa">
		<?php
	},
	1
);
add_action(
	'woocommerce_receipt_mpesa',
	static function () {
		?>
			</div>
			<div class="dl-mpay__till">
				<p><strong>No prompt on your phone?</strong> Check your M-Pesa balance and tap Send M-Pesa request again. Or pay directly:</p>
				<ol>
					<li>Go to <strong>M-Pesa &rarr; Lipa na M-Pesa &rarr; Buy Goods and Services</strong>.</li>
					<li>Till number <strong class="dl-mpay__tillno">8109502</strong> (DLIGHT GIFTSHOP).</li>
					<li>Send us the M-Pesa message on WhatsApp and we will confirm your order.</li>
				</ol>
				<p><a class="dl-mpay__wa" href="<?php echo esc_attr( dl_whatsapp_url( 'Hello Dlight Giftshop, I have paid by till number 8109502 for my order. Here is my M-Pesa message:' ) ); ?>">Send the M-Pesa message on WhatsApp</a></p>
			</div>
		</div>
		<script>
		(function () {
			var pay = document.getElementById('pay_btn'), done = document.getElementById('complete_btn');
			if (pay) { pay.textContent = 'Send M-Pesa request'; pay.type = 'button'; }
			if (done) { done.textContent = 'I have paid, complete my order'; done.type = 'button'; }
		})();
		</script>
		<?php
	},
	99
);

// Thank-you page: the theme shows the order and next steps itself, so WooCommerce's long details
// table and the repeated cash-on-delivery note are left out. Tracking hooks still run.
add_action(
	'woocommerce_before_thankyou',
	static function () {
		remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
		$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->payment_gateways() : array();
		if ( isset( $gateways['cod'] ) ) {
			remove_action( 'woocommerce_thankyou_cod', array( $gateways['cod'], 'thankyou_page' ) );
		}
	}
);

// "Delivery" reads better than "Shipping" on the thank-you page and in emails.
add_filter(
	'woocommerce_get_order_item_totals',
	static function ( $rows ) {
		if ( isset( $rows['shipping'] ) ) {
			$rows['shipping']['label'] = 'Delivery:';
		}
		return $rows;
	}
);

/**
 * Main towns in each county (WooCommerce county code), suggested as a customer types their town.
 * They can still type any town.
 *
 * @return array<string,string[]>
 */
function dl_county_towns() {
	return array(
		'KE01' => array( 'Kabarnet', 'Eldama Ravine', 'Marigat', 'Mogotio', 'Kabartonjo' ),
		'KE02' => array( 'Bomet', 'Sotik', 'Longisa', 'Mulot', 'Silibwet' ),
		'KE03' => array( 'Bungoma', 'Webuye', 'Kimilili', 'Chwele', 'Sirisia', 'Malakisi' ),
		'KE04' => array( 'Busia', 'Malaba', 'Nambale', 'Port Victoria', 'Funyula', 'Butula' ),
		'KE05' => array( 'Iten', 'Kapsowar', 'Chepkorio', 'Tambach', 'Kamwosor' ),
		'KE06' => array( 'Embu', 'Runyenjes', 'Siakago', 'Manyatta', 'Kiritiri' ),
		'KE07' => array( 'Garissa', 'Dadaab', 'Masalani', 'Bura', 'Hulugho' ),
		'KE08' => array( 'Homa Bay', 'Mbita', 'Oyugis', 'Kendu Bay', 'Ndhiwa', 'Rangwe' ),
		'KE09' => array( 'Isiolo', 'Garbatulla', 'Merti', 'Kinna' ),
		'KE10' => array( 'Kitengela', 'Ongata Rongai', 'Kajiado', 'Ngong', 'Kiserian', 'Namanga', 'Isinya', 'Loitokitok', 'Kimana' ),
		'KE11' => array( 'Kakamega', 'Mumias', 'Malava', 'Butere', 'Lugari', 'Khayega', 'Shinyalu' ),
		'KE12' => array( 'Kericho', 'Litein', 'Londiani', 'Kipkelion', 'Brooke' ),
		'KE13' => array( 'Thika', 'Ruiru', 'Kiambu', 'Juja', 'Kikuyu', 'Limuru', 'Ruaka', 'Githurai 45', 'Kahawa Wendani', 'Kahawa Sukari', 'Banana', 'Gatundu', 'Karuri', 'Kiambaa', 'Lari' ),
		'KE14' => array( 'Kilifi', 'Malindi', 'Watamu', 'Mtwapa', 'Mariakani', 'Kaloleni', 'Mazeras' ),
		'KE15' => array( 'Kerugoya', 'Kutus', 'Sagana', 'Kagio', 'Wang\'uru', 'Kianyaga' ),
		'KE16' => array( 'Kisii', 'Ogembo', 'Suneka', 'Keroka', 'Masimba', 'Nyamache' ),
		'KE17' => array( 'Kisumu', 'Ahero', 'Maseno', 'Kombewa', 'Muhoroni', 'Awasi', 'Katito' ),
		'KE18' => array( 'Kitui', 'Mwingi', 'Mutomo', 'Kabati', 'Kwa Vonza', 'Ikutha' ),
		'KE19' => array( 'Ukunda', 'Diani', 'Kwale', 'Msambweni', 'Kinango', 'Lunga Lunga', 'Shimoni' ),
		'KE20' => array( 'Nanyuki', 'Nyahururu', 'Rumuruti', 'Doldol', 'Kinamba', 'Lamuria' ),
		'KE21' => array( 'Lamu', 'Mpeketoni', 'Witu', 'Mokowe', 'Faza' ),
		'KE22' => array( 'Machakos', 'Athi River', 'Syokimau', 'Mlolongo', 'Kangundo', 'Tala', 'Matuu', 'Masii', 'Kathiani' ),
		'KE23' => array( 'Wote', 'Emali', 'Mtito Andei', 'Kibwezi', 'Makindu', 'Sultan Hamud', 'Mbumbuni' ),
		'KE24' => array( 'Mandera', 'El Wak', 'Rhamu', 'Takaba', 'Lafey' ),
		'KE25' => array( 'Marsabit', 'Moyale', 'Laisamis', 'Sololo', 'North Horr' ),
		'KE26' => array( 'Meru', 'Maua', 'Timau', 'Nkubu', 'Chuka Road', 'Mitunguu', 'Laare', 'Kianjai' ),
		'KE27' => array( 'Migori', 'Rongo', 'Awendo', 'Kehancha', 'Isebania', 'Uriri', 'Muhuru Bay' ),
		'KE28' => array( 'Mombasa CBD', 'Nyali', 'Bamburi', 'Likoni', 'Changamwe', 'Mtwapa', 'Kisauni', 'Mikindani', 'Shanzu' ),
		'KE29' => array( 'Murang\'a', 'Kenol', 'Kangema', 'Maragua', 'Kandara', 'Kiriaini', 'Makuyu' ),
		'KE30' => array(),
		'KE31' => array( 'Nakuru', 'Naivasha', 'Gilgil', 'Molo', 'Njoro', 'Subukia', 'Mai Mahiu', 'Salgaa', 'Bahati', 'Rongai' ),
		'KE32' => array( 'Kapsabet', 'Nandi Hills', 'Mosoriot', 'Kabiyet', 'Kobujoi' ),
		'KE33' => array( 'Narok', 'Kilgoris', 'Ololulunga', 'Nairagie Ngare', 'Suswa', 'Emurua Dikirr' ),
		'KE34' => array( 'Nyamira', 'Keroka', 'Nyansiongo', 'Ekerenyo', 'Magwagwa' ),
		'KE35' => array( 'Ol Kalou', 'Njabini', 'Engineer', 'Ndaragwa', 'Ol Joro Orok', 'Mairo Inya' ),
		'KE36' => array( 'Nyeri', 'Karatina', 'Othaya', 'Mukurweini', 'Naro Moru', 'Chaka', 'Endarasha' ),
		'KE37' => array( 'Maralal', 'Baragoi', 'Wamba', 'Archers Post' ),
		'KE38' => array( 'Siaya', 'Bondo', 'Ugunja', 'Yala', 'Usenge', 'Ukwala', 'Sega' ),
		'KE39' => array( 'Voi', 'Wundanyi', 'Taveta', 'Mwatate', 'Maungu' ),
		'KE40' => array( 'Hola', 'Garsen', 'Bura', 'Madogo', 'Kipini' ),
		'KE41' => array( 'Chuka', 'Kathwana', 'Marimanti', 'Chogoria', 'Gatunga' ),
		'KE42' => array( 'Kitale', 'Endebess', 'Kiminini', 'Saboti', 'Kwanza' ),
		'KE43' => array( 'Lodwar', 'Kakuma', 'Lokichogio', 'Lokitaung', 'Kalokol' ),
		'KE44' => array( 'Eldoret', 'Burnt Forest', 'Turbo', 'Moi\'s Bridge', 'Ziwa', 'Kesses' ),
		'KE45' => array( 'Mbale', 'Luanda', 'Chavakali', 'Majengo', 'Kaimosi', 'Hamisi' ),
		'KE46' => array( 'Wajir', 'Habaswein', 'Bute', 'Griftu', 'Tarbaj' ),
		'KE47' => array( 'Kapenguria', 'Makutano', 'Chepareria', 'Sigor', 'Alale' ),
	);
}

// Delivery options follow the county the customer has chosen (or saved), from the first moment the
// page loads: a saved delivery address elsewhere must not show the wrong options.
add_action(
	'woocommerce_before_checkout_form',
	static function () {
		$customer = WC()->customer;
		if ( ! $customer || ! WC()->cart ) {
			return;
		}
		$state = $customer->get_billing_state() ? $customer->get_billing_state() : DL_NAIROBI;
		if ( $customer->get_shipping_state() !== $state || 'KE' !== $customer->get_shipping_country() ) {
			$customer->set_shipping_location( 'KE', $state, '', $customer->get_billing_city() );
			$customer->save();
			WC()->cart->calculate_shipping();
			WC()->cart->calculate_totals();
		}
	},
	1
);

// Fields that do not apply to the county or delivery choice start hidden, before any script runs.
add_filter(
	'woocommerce_form_field_args',
	static function ( $args, $key ) {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() || ! in_array( $key, array( 'billing_dl_area', 'billing_city', 'billing_address_1' ), true ) ) {
			return $args;
		}
		$customer = WC()->customer;
		$state    = $customer && $customer->get_billing_state() ? $customer->get_billing_state() : DL_NAIROBI;
		$pickup   = dl_is_pickup( dl_chosen_method() );
		$nairobi  = DL_NAIROBI === $state;
		$off      = $pickup || ( 'billing_dl_area' === $key && ! $nairobi ) || ( 'billing_city' === $key && $nairobi );
		if ( $off ) {
			$args['class'][] = 'dl-off';
		}
		if ( 'billing_city' === $key && ! $nairobi ) {
			$states        = WC()->countries->get_states( 'KE' );
			$args['label'] = 'Town in ' . str_replace( ' County', '', html_entity_decode( $states[ $state ] ?? '' ) );
		}
		return $args;
	},
	10,
	2
);

// Delivery, not pickup, is the default choice; a customer who picked pickup keeps it.
add_filter(
	'woocommerce_shipping_chosen_method',
	static function ( $default, $rates ) {
		if ( dl_is_pickup( $default ) ) {
			foreach ( array_keys( (array) $rates ) as $id ) {
				if ( ! dl_is_pickup( $id ) ) {
					return $id;
				}
			}
		}
		return $default;
	},
	10,
	2
);

/*
 * M-Pesa prepay discount: the M-Pesa option has always offered 2% off for paying upfront.
 * It is now applied automatically when M-Pesa is chosen, and shown in the totals.
 * Set DL_MPESA_DISCOUNT to 0 to switch it off.
 */
const DL_MPESA_DISCOUNT = 2;

/**
 * The payment method being used at checkout (M-Pesa is first, so it is the default).
 *
 * @return string
 */
function dl_checkout_payment_method() {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- read only; WooCommerce verifies the nonce.
	if ( isset( $_POST['payment_method'] ) ) {
		return sanitize_key( wp_unslash( $_POST['payment_method'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}
	$chosen = WC()->session ? (string) WC()->session->get( 'chosen_payment_method' ) : '';
	if ( $chosen ) {
		return $chosen;
	}
	$gateways = WC()->payment_gateways() ? WC()->payment_gateways()->get_available_payment_gateways() : array();
	return isset( $gateways['mpesa'] ) ? 'mpesa' : (string) key( $gateways );
}

add_action(
	'woocommerce_cart_calculate_fees',
	static function ( $cart ) {
		if ( DL_MPESA_DISCOUNT <= 0 || ( is_admin() && ! wp_doing_ajax() ) ) {
			return;
		}
		$on_checkout = ( function_exists( 'is_checkout' ) && is_checkout() ) || ( wp_doing_ajax() && isset( $_GET['wc-ajax'] ) && in_array( $_GET['wc-ajax'], array( 'update_order_review', 'checkout' ), true ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! $on_checkout || 'mpesa' !== dl_checkout_payment_method() ) {
			return;
		}
		$items = (float) $cart->get_subtotal() - (float) $cart->get_discount_total();
		$off   = floor( $items * DL_MPESA_DISCOUNT / 100 );
		if ( $off > 0 ) {
			$cart->add_fee( 'M-Pesa discount (' . DL_MPESA_DISCOUNT . '%)', -$off, false );
		}
	}
);

// The M-Pesa option says what the customer saves, in shillings.
add_filter(
	'woocommerce_gateway_description',
	static function ( $description, $id ) {
		if ( 'mpesa' === $id && DL_MPESA_DISCOUNT > 0 && WC()->cart ) {
			$items = (float) WC()->cart->get_subtotal() - (float) WC()->cart->get_discount_total();
			$off   = floor( $items * DL_MPESA_DISCOUNT / 100 );
			return 'Pay now with M-Pesa and save ' . DL_MPESA_DISCOUNT . '%' . ( $off > 0 ? ' (' . wp_strip_all_tags( wc_price( $off ) ) . ')' : '' ) . '. We send a payment request to your phone.';
		}
		return $description;
	},
	20,
	2
);
