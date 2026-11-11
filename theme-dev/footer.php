<?php
/**
 * Site footer, cart drawer, mobile menu and bottom navigation.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

$dl_shop   = wc_get_page_permalink( 'shop' );
?>
</main>

<footer class="dl-footer">
	<div class="dl-wrap dl-footer__grid">
		<div class="dl-footer__brand">
			<img src="<?php echo esc_url( DL_URI . '/assets/img/logo-dg.webp' ); ?>" width="72" height="72" alt="Dlight Giftshop" loading="lazy">
			<p>Dlight Giftshop. Your one stop shop for perfumes and watches in Nairobi.</p>
			<div class="dl-social">
				<a href="https://www.instagram.com/dlightgiftshopofficial/" target="_blank" rel="noopener">Instagram</a>
				<a href="https://www.facebook.com/DlightGiftshopKe" target="_blank" rel="noopener">Facebook</a>
			</div>
		</div>
		<div>
			<h2 class="dl-footer__h">Shop</h2>
			<a href="<?php echo esc_url( dl_cat_url( 'perfumes' ) ); ?>">All perfumes</a>
			<a href="<?php echo esc_url( dl_shop_url( 'her', 'perfume' ) ); ?>">Women's perfumes</a>
			<a href="<?php echo esc_url( dl_shop_url( 'him', 'perfume' ) ); ?>">Men's perfumes</a>
			<a href="<?php echo esc_url( dl_cat_url( 'watches' ) ); ?>">All watches</a>
			<a href="<?php echo esc_url( dl_shop_url( 'him', 'watch' ) ); ?>">Men's watches</a>
			<a href="<?php echo esc_url( dl_shop_url( 'her', 'watch' ) ); ?>">Women's watches</a>
		</div>
		<div>
			<h2 class="dl-footer__h">Customer care</h2>
			<a href="<?php echo esc_url( home_url( '/help-centre/' ) ); ?>">Help Centre</a>
			<a href="<?php echo esc_url( home_url( '/faqs/' ) ); ?>">FAQs</a>
			<a href="<?php echo esc_url( home_url( '/order-tracking/' ) ); ?>">Track your order</a>
			<a href="<?php echo esc_url( home_url( '/delivery-information/' ) ); ?>">Delivery information</a>
			<a href="<?php echo esc_url( home_url( '/payment-options/' ) ); ?>">Payment options</a>
			<a href="<?php echo esc_url( home_url( '/returns-refunds/' ) ); ?>">Returns &amp; refunds</a>
			<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">My account</a>
		</div>
		<div>
			<h2 class="dl-footer__h">Dlight Giftshop</h2>
			<a href="<?php echo esc_url( home_url( '/about-us/' ) ); ?>">About us</a>
			<a href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact us</a>
			<a href="<?php echo esc_url( home_url( '/terms-and-conditions/' ) ); ?>">Terms &amp; conditions</a>
			<a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy policy</a>
		</div>
		<div>
			<h2 class="dl-footer__h">Visit us</h2>
			<address><?php echo esc_html( DL_ADDRESS ); ?></address>
			<p class="dl-hours">Mon to Sat: 8:30am to 7:00pm<br>Sunday: closed</p>
			<a href="<?php echo esc_url( DL_MAP ); ?>" target="_blank" rel="noopener">Get directions</a>
			<a href="tel:<?php echo esc_attr( DL_PHONE ); ?>"><?php echo esc_html( DL_PHONE_DISPLAY ); ?></a>
			<a href="mailto:<?php echo esc_attr( DL_EMAIL ); ?>"><?php echo esc_html( DL_EMAIL ); ?></a>
		</div>
	</div>
	<div class="dl-wrap dl-footer__base">
		<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Dlight Giftshop. All rights reserved.</p>
		<p class="dl-pay"><span>Pay securely with</span><b>M-PESA</b></p>
	</div>
</footer>

<?php
// WhatsApp opens with the page the shopper is on, so the team knows what they were looking at.
if ( function_exists( 'is_product' ) && is_product() ) {
	$dl_wa_product = wc_get_product( get_queried_object_id() );
	$dl_wa_text    = $dl_wa_product ? dl_whatsapp_product_text( $dl_wa_product ) : 'Hello Dlight Giftshop';
} else {
	$dl_wa_title = html_entity_decode( wp_get_document_title(), ENT_QUOTES, 'UTF-8' );
	$dl_wa_url   = remove_query_arg( array( 'fbclid', 'gclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term' ), home_url( add_query_arg( array() ) ) );
	$dl_wa_text  = "Hello Dlight Giftshop,\nI have a question about this page:\n\n*" . $dl_wa_title . "*\n" . $dl_wa_url;
}
?>
<a class="dl-wa-float js-wa" href="<?php echo dl_whatsapp_href( $dl_wa_text ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in dl_whatsapp_href(). ?>" data-wa-text="<?php echo esc_attr( $dl_wa_text ); ?>" target="_blank" rel="noopener" aria-label="Chat with us on WhatsApp">
	<svg viewBox="0 0 32 32" aria-hidden="true"><path fill="#fff" d="M16 3.200C9 3.200 3.300 8.900 3.300 15.900c0 2.200.6 4.400 1.700 6.300L3.200 28.800l6.800-1.800c1.800 1 3.900 1.500 6 1.500 7 0 12.700-5.700 12.700-12.700S23 3.200 16 3.200zm0 23.200c-1.900 0-3.800-.5-5.400-1.500l-.4-.2-4 1.100 1.100-3.900-.3-.4a10.400 10.400 0 1 1 9 4.900zm5.700-7.800c-.3-.2-1.900-.9-2.200-1-.3-.1-.5-.2-.7.2-.2.300-.8 1-1 1.200-.2.200-.4.200-.7.100-.3-.2-1.300-.5-2.500-1.600-.9-.8-1.600-1.800-1.700-2.100-.2-.3 0-.5.100-.6l.5-.6c.2-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-1-2.400c-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.400-.3.300-1 1-1 2.500s1.100 2.900 1.200 3.100c.2.200 2.100 3.300 5.200 4.600.7.3 1.300.5 1.700.6.700.2 1.400.2 1.900.1.600-.1 1.900-.8 2.100-1.500.3-.7.3-1.400.2-1.500-.1-.2-.3-.3-.6-.4z"/></svg>
	<span class="dl-wa-float__label">WhatsApp</span>
</a>

<nav class="dl-dock" aria-label="Quick links">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m4 11 8-7 8 7v9h-5.500v-6h-5v6H4z"/></svg>Home
	</a>
	<button type="button" class="js-menu-open">
		<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h7v7H4zM13 5h7v7h-7zM4 14h7v6H4zM13 14h7v6h-7z"/></svg>Categories
	</button>
	<button type="button" class="js-search-focus">
		<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.500"/><path d="m16 16 4.500 4.500"/></svg>Search
	</button>
	<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
		<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.500" r="3.700"/><path d="M4.800 20c1-3.700 3.800-5.500 7.200-5.500s6.200 1.800 7.200 5.500"/></svg>Account
	</a>
	<a class="js-bag" href="<?php echo esc_url( wc_get_cart_url() ); ?>">
		<span class="dl-dock__bag">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.500l2.200 11h10.100l2-8H6.300"/><circle cx="9.500" cy="19.500" r="1.300"/><circle cx="16.500" cy="19.500" r="1.300"/></svg>
			<span class="dl-cart-count" data-count="0">0</span>
		</span>Cart
	</a>
</nav>

<aside class="dl-drawer" id="dl-drawer" aria-label="Your cart" hidden>
	<div class="dl-drawer__scrim js-drawer-close"></div>
	<div class="dl-drawer__panel" role="dialog" aria-modal="true" aria-labelledby="dl-drawer-title" tabindex="-1">
		<div class="dl-drawer__head">
			<h2 id="dl-drawer-title">Your cart</h2>
			<button type="button" class="dl-x js-drawer-close" aria-label="Close cart"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
		</div>
		<div id="dl-drawer-body" class="dl-drawer__body"></div>
	</div>
</aside>

<dialog class="dl-menu" id="dl-menu" aria-label="Menu">
	<div class="dl-menu__head">
		<img src="<?php echo esc_url( DL_URI . '/assets/img/logo-dg.webp' ); ?>" width="40" height="40" alt="" loading="lazy">
		<strong>Dlight Giftshop</strong>
		<button type="button" class="dl-x js-menu-close" aria-label="Close menu"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
	</div>
	<div class="dl-menu__body">
		<p class="dl-menu__label">Shop</p>
		<details class="dl-menu__group">
			<summary>Perfumes</summary>
			<div class="dl-menu__sub">
				<a href="<?php echo esc_url( dl_shop_url( 'her', 'perfume' ) ); ?>">Women's Perfumes</a>
				<a href="<?php echo esc_url( dl_shop_url( 'him', 'perfume' ) ); ?>">Men's Perfumes</a>
				<?php foreach ( dl_children( 'perfumes' ) as $dl_group ) : ?>
					<a href="<?php echo esc_url( get_term_link( $dl_group ) ); ?>"><?php echo esc_html( ucwords( html_entity_decode( $dl_group->name, ENT_QUOTES, 'UTF-8' ) ) ); ?></a>
				<?php endforeach; ?>
				<a class="dl-menu__all" href="<?php echo esc_url( dl_cat_url( 'perfumes' ) ); ?>">View all perfumes</a>
			</div>
		</details>
		<details class="dl-menu__group">
			<summary>Watches</summary>
			<div class="dl-menu__sub">
				<a href="<?php echo esc_url( dl_shop_url( 'him', 'watch' ) ); ?>">Men's Watches</a>
				<a href="<?php echo esc_url( dl_shop_url( 'her', 'watch' ) ); ?>">Women's Watches</a>
				<a class="dl-menu__all" href="<?php echo esc_url( dl_cat_url( 'watches' ) ); ?>">View all watches</a>
			</div>
		</details>
		<details class="dl-menu__group">
			<summary>Watch Brands</summary>
			<div class="dl-menu__chips">
				<?php foreach ( dl_watch_brands() as $dl_brand ) : ?>
					<a href="<?php echo esc_url( get_term_link( $dl_brand ) ); ?>"><?php echo esc_html( $dl_brand->name ); ?></a>
				<?php endforeach; ?>
			</div>
		</details>
		<a class="dl-menu__row" href="<?php echo esc_url( home_url( '/#gift-finder' ) ); ?>">Gift Finder</a>

		<p class="dl-menu__label">Help</p>
		<a class="dl-menu__row" href="<?php echo esc_url( home_url( '/order-tracking/' ) ); ?>">Track Your Order</a>
		<a class="dl-menu__row" href="<?php echo esc_url( home_url( '/help-centre/' ) ); ?>">Help Centre</a>
		<a class="dl-menu__row" href="<?php echo esc_url( home_url( '/faqs/' ) ); ?>">FAQs</a>
		<a class="dl-menu__row" href="<?php echo esc_url( home_url( '/contact-us/' ) ); ?>">Contact Us</a>

		<a class="dl-btn dl-btn--wa dl-menu__wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I would like to place an order.' ) ); ?>" target="_blank" rel="noopener">Order on WhatsApp</a>
	</div>
</dialog>

<?php wp_footer(); ?>
</body>
</html>
