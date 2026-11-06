<?php
/**
 * Thank-you page: a warm confirmation, what happens next, the order at a glance, and where to go now.
 * WooCommerce's thank-you hooks still run, so Meta, Google and payment plugins see the purchase.
 * Based on WooCommerce's thankyou.php.
 *
 * @package dlight
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-order dl-thanks">
	<?php
	if ( ! $order ) {
		echo '<div class="dl-thanks__hero"><span class="dl-thanks__tick" aria-hidden="true"></span><h1>Thank you</h1><p>Your order has been received.</p></div>';
		echo '</div>';
		return;
	}

	do_action( 'woocommerce_before_thankyou', $order->get_id() );

	if ( $order->has_status( 'failed' ) ) :
		?>
		<div class="dl-thanks__hero dl-thanks__hero--failed">
			<h1>Payment not completed</h1>
			<p>Your order #<?php echo esc_html( $order->get_order_number() ); ?> is saved, but the payment did not go through.</p>
			<p class="dl-thanks__actions">
				<a class="dl-btn dl-btn--gold" href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>">Try paying again</a>
				<a class="dl-btn dl-btn--wa" href="<?php echo esc_attr( dl_order_whatsapp( $order, 'Hello Dlight Giftshop, my payment did not go through' ) ); ?>">Get help on WhatsApp</a>
			</p>
		</div>
		<?php
	else :
		$dl_first = $order->get_billing_first_name();
		$dl_email = $order->get_billing_email();
		?>
		<div class="dl-thanks__hero">
			<span class="dl-thanks__tick" aria-hidden="true"></span>
			<p class="dl-thanks__eyebrow">Order #<?php echo esc_html( $order->get_order_number() ); ?> confirmed</p>
			<h1>Thank you<?php echo $dl_first ? ', ' . esc_html( $dl_first ) : ''; ?>!</h1>
			<p><?php echo $dl_email ? 'We have sent your order details to <strong>' . esc_html( $dl_email ) . '</strong>.' : 'We have your order and will be in touch shortly.'; ?></p>
		</div>

		<div class="dl-thanks__grid">
			<section class="dl-thanks__card dl-thanks__next">
				<h2>What happens next</h2>
				<ol>
					<?php foreach ( dl_order_next_steps( $order ) as $dl_line ) : ?>
						<li><?php echo esc_html( $dl_line ); ?></li>
					<?php endforeach; ?>
				</ol>
				<p class="dl-thanks__actions">
					<a class="dl-btn dl-btn--wa" href="<?php echo esc_attr( dl_order_whatsapp( $order, 'Hello Dlight Giftshop, I have a question about my order' ) ); ?>">Chat with us on WhatsApp</a>
				</p>
			</section>

			<section class="dl-thanks__card dl-thanks__order">
				<h2>Your order</h2>
				<ul class="dl-thanks__items">
					<?php foreach ( $order->get_items() as $dl_item ) : ?>
						<?php $dl_product = $dl_item->get_product(); ?>
						<li>
							<span class="dl-sum__img"><?php echo $dl_product ? $dl_product->get_image( 'thumbnail' ) : ''; // phpcs:ignore WordPress.Security.EscapeOutput ?><b><?php echo esc_html( $dl_item->get_quantity() ); ?></b></span>
							<span class="dl-thanks__name"><?php echo esc_html( $dl_item->get_name() ); ?></span>
							<span class="dl-thanks__price"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $dl_item ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
				<table class="dl-thanks__totals">
					<?php foreach ( $order->get_order_item_totals() as $dl_key => $dl_total ) : ?>
						<tr class="dl-thanks__row--<?php echo esc_attr( $dl_key ); ?>"><th><?php echo esc_html( rtrim( wp_strip_all_tags( $dl_total['label'] ), ':' ) ); ?></th><td><?php echo wp_kses_post( $dl_total['value'] ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</section>
		</div>

		<p class="dl-thanks__more">
			<a class="dl-btn dl-btn--gold" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Continue shopping</a>
			<a class="dl-thanks__link" href="<?php echo esc_url( home_url( '/order-tracking/' ) ); ?>">Track your order</a>
		</p>
		<?php
	endif;

	// Payment and tracking plugins (including Meta's purchase event) listen to these.
	do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
	do_action( 'woocommerce_thankyou', $order->get_id() );
	?>
</div>
