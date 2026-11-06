<?php
/**
 * Cart: one card per item (photo, name, price, quantity buttons, remove), then the summary.
 * Based on WooCommerce's cart.php: same form, field names, nonce and hooks, so updates,
 * removals and coupons work as usual.
 *
 * @package dlight
 * @version 11.2.0
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_cart' ); ?>

<div class="dl-cart">
	<form class="woocommerce-cart-form dl-cart__items" action="<?php echo esc_url( wc_get_cart_url() ); ?>" method="post">
		<?php do_action( 'woocommerce_before_cart_table' ); ?>
		<?php do_action( 'woocommerce_before_cart_contents' ); ?>

		<ul class="dl-cart__list shop_table cart woocommerce-cart-form__contents">
			<?php
			foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
				$_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
				$product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );
				$name       = $_product ? apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key ) : '';
				if ( ! ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_cart_item_visible', true, $cart_item, $cart_item_key ) ) ) {
					continue;
				}
				$link = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
				?>
				<li class="dl-cart__item woocommerce-cart-form__cart-item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
					<a class="dl-cart__img" href="<?php echo esc_url( $link ? $link : '#' ); ?>" tabindex="-1" aria-hidden="true">
						<?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
					<div class="dl-cart__info product-name">
						<?php if ( $link ) : ?>
							<a class="dl-cart__name" href="<?php echo esc_url( $link ); ?>"><?php echo wp_kses_post( $name ); ?></a>
						<?php else : ?>
							<span class="dl-cart__name"><?php echo wp_kses_post( $name ); ?></span>
						<?php endif; ?>
						<?php
						do_action( 'woocommerce_after_cart_item_name', $cart_item, $cart_item_key );
						echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput
						if ( $_product->backorders_require_notification() && $_product->is_on_backorder( $cart_item['quantity'] ) ) {
							echo wp_kses_post( apply_filters( 'woocommerce_cart_item_backorder_notification', '<p class="backorder_notification">' . esc_html__( 'Available on backorder', 'woocommerce' ) . '</p>', $product_id ) );
						}
						?>
						<span class="dl-cart__each product-price"><?php echo apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
						<div class="dl-cart__row">
							<div class="dl-qty product-quantity">
								<?php if ( ! $_product->is_sold_individually() ) : ?>
									<button type="button" class="dl-qty__btn js-qty" data-step="-1" aria-label="One less">&minus;</button>
								<?php endif; ?>
								<?php
								$min = $_product->is_sold_individually() ? 1 : 0;
								$max = $_product->is_sold_individually() ? 1 : $_product->get_max_purchase_quantity();
								echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput
									'woocommerce_cart_item_quantity',
									woocommerce_quantity_input(
										array(
											'input_name'   => "cart[{$cart_item_key}][qty]",
											'input_value'  => $cart_item['quantity'],
											'max_value'    => $max,
											'min_value'    => $min,
											'product_name' => $name,
										),
										$_product,
										false
									),
									$cart_item_key,
									$cart_item
								);
								?>
								<?php if ( ! $_product->is_sold_individually() ) : ?>
									<button type="button" class="dl-qty__btn js-qty" data-step="1" aria-label="One more">+</button>
								<?php endif; ?>
							</div>
							<?php
							echo apply_filters( // phpcs:ignore WordPress.Security.EscapeOutput
								'woocommerce_cart_item_remove_link',
								sprintf(
									'<a role="button" href="%s" class="remove dl-cart__remove" aria-label="%s" data-product_id="%s" data-product_sku="%s">Remove</a>',
									esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
									/* translators: %s: product name */
									esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $name ) ) ),
									esc_attr( $product_id ),
									esc_attr( $_product->get_sku() )
								),
								$cart_item_key
							);
							?>
						</div>
					</div>
					<span class="dl-cart__line product-subtotal"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput ?></span>
				</li>
				<?php
			}
			?>
		</ul>

		<?php do_action( 'woocommerce_cart_contents' ); ?>

		<div class="dl-cart__actions actions">
			<?php if ( wc_coupons_enabled() ) : ?>
				<details class="dl-coupon">
					<summary>Have a coupon code?</summary>
					<div class="coupon">
						<label for="coupon_code" class="screen-reader-text">Coupon:</label>
						<input type="text" name="coupon_code" class="input-text" id="coupon_code" value="" placeholder="Coupon code" />
						<button type="submit" class="button" name="apply_coupon" value="Apply coupon">Apply</button>
						<?php do_action( 'woocommerce_cart_coupon' ); ?>
					</div>
				</details>
			<?php endif; ?>
			<button type="submit" class="button dl-cart__update" name="update_cart" value="Update cart">Update cart</button>
			<?php do_action( 'woocommerce_cart_actions' ); ?>
			<?php wp_nonce_field( 'woocommerce-cart', 'woocommerce-cart-nonce' ); ?>
		</div>

		<?php do_action( 'woocommerce_after_cart_contents' ); ?>
		<?php do_action( 'woocommerce_after_cart_table' ); ?>
	</form>

	<?php do_action( 'woocommerce_before_cart_collaterals' ); ?>
	<div class="cart-collaterals dl-cart__side">
		<?php do_action( 'woocommerce_cart_collaterals' ); ?>
	</div>
</div>

<?php do_action( 'woocommerce_after_cart' ); ?>
