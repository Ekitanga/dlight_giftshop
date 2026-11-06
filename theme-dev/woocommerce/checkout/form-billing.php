<?php
/**
 * Checkout steps 1 and 2: your details, then delivery.
 * Based on WooCommerce's form-billing.php; the fields are WooCommerce's own, in two short groups.
 *
 * @package dlight
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

$dl_fields   = $checkout->get_checkout_fields( 'billing' );
$dl_details  = array( 'billing_first_name', 'billing_last_name', 'billing_phone', 'billing_email' );
$dl_delivery = array( 'billing_country', 'billing_state' );
$dl_where    = array( 'billing_dl_area', 'billing_city', 'billing_address_1' );
$dl_print    = static function ( $keys ) use ( $dl_fields, $checkout ) {
	foreach ( $keys as $key ) {
		if ( isset( $dl_fields[ $key ] ) ) {
			woocommerce_form_field( $key, $dl_fields[ $key ], $checkout->get_value( $key ) );
		}
	}
};
?>
<div class="woocommerce-billing-fields">
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>

	<section class="dl-checkstep" aria-labelledby="dl-step-1">
		<h2 class="dl-step" id="dl-step-1"><span>1</span>Your details</h2>
		<div class="woocommerce-billing-fields__field-wrapper">
			<?php $dl_print( $dl_details ); ?>
		</div>
	</section>

	<section class="dl-checkstep" aria-labelledby="dl-step-2">
		<h2 class="dl-step" id="dl-step-2"><span>2</span>Delivery</h2>
		<div class="woocommerce-billing-fields__field-wrapper">
			<?php $dl_print( $dl_delivery ); ?>
		</div>
		<?php echo dl_delivery_options_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped when built. ?>
		<p class="dl-pickup-note" hidden>We will WhatsApp or call you when your order is ready to collect.</p>
		<div class="woocommerce-billing-fields__field-wrapper dl-where">
			<?php $dl_print( $dl_where ); ?>
			<?php
			// Any other billing fields added by plugins.
			$dl_print( array_diff( array_keys( $dl_fields ), $dl_details, $dl_delivery, $dl_where ) );
			?>
		</div>
	</section>

	<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
</div>

<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() && $checkout->get_checkout_fields( 'account' ) ) : ?>
	<div class="woocommerce-account-fields">
		<?php if ( ! $checkout->is_registration_required() ) : ?>
			<p class="form-row form-row-wide create-account">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( ( true === $checkout->get_value( 'createaccount' ) || ( true === apply_filters( 'woocommerce_create_account_default_checked', false ) ) ), true ); ?> type="checkbox" name="createaccount" value="1" /> <span>Create an account to track your orders</span>
				</label>
			</p>
		<?php endif; ?>
		<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>
		<div class="create-account">
			<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
				<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
			<?php endforeach; ?>
		</div>
		<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
	</div>
<?php endif; ?>
