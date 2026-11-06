<?php
/**
 * Delivery goes to the details in step 2, so this only holds the optional note, folded away.
 * Based on WooCommerce's form-shipping.php.
 *
 * @package dlight
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="woocommerce-shipping-fields"></div>
<div class="woocommerce-additional-fields">
	<?php do_action( 'woocommerce_before_order_notes', $checkout ); ?>
	<?php $dl_order_fields = $checkout->get_checkout_fields( 'order' ); ?>
	<?php if ( ! empty( $dl_order_fields ) ) : ?>
		<details class="dl-note-toggle"<?php echo $checkout->get_value( 'order_comments' ) ? ' open' : ''; ?>>
			<summary>Add a note to your order (optional)</summary>
			<div class="woocommerce-additional-fields__field-wrapper">
				<?php foreach ( $dl_order_fields as $key => $field ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endforeach; ?>
			</div>
		</details>
	<?php endif; ?>
	<?php do_action( 'woocommerce_after_order_notes', $checkout ); ?>
</div>
