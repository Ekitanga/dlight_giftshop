<?php
/**
 * Site header: promo bar, logo, search, account, cart, category navigation.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

$dl_groups = dl_children( 'perfumes' );
$dl_brands = dl_watch_brands();
$dl_shop   = wc_get_page_permalink( 'shop' );
$dl_quick  = array(
	'Casio'     => 'casio',
	'Seiko'     => 'seiko',
	'Naviforce' => 'naviforce',
	'Curren'    => 'curren',
	'Fossil'    => 'fs-dz-watches',
);
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="dl-skip" href="#main">Skip to content</a>

<div class="dl-promo">
	<div class="dl-promo__in">
		<?php
		$dl_promo = array( 'Original perfumes & watches', 'Up to 40% off', 'Countrywide delivery', 'Gift wrapping available', 'Order in minutes, online or on WhatsApp' );
		?>
		<div class="dl-promo__msg">
			<div class="dl-promo__track">
				<?php foreach ( array( false, true, true, true ) as $dl_copy ) : ?>
					<p class="dl-promo__set"<?php echo $dl_copy ? ' aria-hidden="true"' : ''; ?>>
						<?php foreach ( $dl_promo as $dl_item ) : ?>
							<span><?php echo esc_html( $dl_item ); ?></span>
						<?php endforeach; ?>
					</p>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>

<header class="dl-header" id="dl-header">
	<div class="dl-wrap dl-header__in">
		<button type="button" class="dl-menu-btn js-menu-open" aria-label="Open menu">
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
		</button>

		<a class="dl-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Dlight Giftshop home">
			<img src="<?php echo esc_url( DL_URI . '/assets/img/logo-dg.webp' ); ?>" width="52" height="52" alt="">
			<span class="dl-logo__text"><strong>Dlight Giftshop</strong><small>Your one stop shop</small></span>
		</a>

		<form class="dl-search" id="dl-search" role="search" action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get">
			<label class="dl-sr" for="dl-search-input">Search products</label>
			<input id="dl-search-input" name="s" type="search" autocomplete="off" placeholder="Search perfumes, watches and brands" required value="<?php echo esc_attr( get_search_query() ); ?>">
			<input type="hidden" name="post_type" value="product">
			<button type="submit" aria-label="Search">
				<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="m16 16 4.500 4.500"/></svg>
			</button>
			<div class="dl-search__panel" id="dl-search-results" aria-live="polite" hidden></div>
		</form>

		<div class="dl-tools">
			<a class="dl-tool dl-tool--wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I would like to place an order.' ) ); ?>" target="_blank" rel="noopener">
				<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.200-4A8 8 0 1 1 8 18.800z"/><path d="M9 8.500c0 3.500 3 6.500 6.500 6.500l1-1.700-2.200-1-1 .9c-1-.4-1.700-1.200-2.100-2.100l.9-1-1-2.200z"/></svg>
				<span><small>Order on</small>WhatsApp</span>
			</a>
			<a class="dl-tool dl-tool--account" href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>">
				<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8.500" r="3.700"/><path d="M4.800 20c1-3.700 3.800-5.500 7.200-5.500s6.200 1.800 7.200 5.500"/></svg>
				<span><small>Sign in</small>Account</span>
			</a>
			<a class="dl-tool dl-tool--cart js-bag" href="<?php echo esc_url( wc_get_cart_url() ); ?>" aria-label="Shopping cart">
				<span class="dl-tool__icon">
					<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 4h2.500l2.200 11h10.100l2-8H6.300"/><circle cx="9.500" cy="19.500" r="1.300"/><circle cx="16.500" cy="19.500" r="1.300"/></svg>
					<span class="dl-cart-count" data-count="0">0</span>
				</span>
				<span><small>Your</small>Cart</span>
			</a>
		</div>
	</div>

	<nav class="dl-nav" aria-label="Categories">
		<div class="dl-wrap dl-nav__in">
			<div class="dl-nav__item">
				<a class="dl-nav__link dl-nav__link--main" href="<?php echo esc_url( dl_cat_url( 'perfumes' ) ); ?>">Perfumes<svg viewBox="0 0 12 12" aria-hidden="true"><path d="m2 4 4 4 4-4"/></svg></a>
				<div class="dl-mega">
					<div class="dl-wrap dl-mega__in">
						<div>
							<p class="dl-mega__h">Shop by type</p>
							<?php foreach ( $dl_groups as $dl_group ) : ?>
								<a href="<?php echo esc_url( get_term_link( $dl_group ) ); ?>"><?php echo esc_html( html_entity_decode( $dl_group->name, ENT_QUOTES, 'UTF-8' ) ); ?></a>
							<?php endforeach; ?>
						</div>
						<div>
							<p class="dl-mega__h">Shop by gender</p>
							<a href="<?php echo esc_url( dl_shop_url( 'her', 'perfume' ) ); ?>">Women's perfumes</a>
							<a href="<?php echo esc_url( dl_shop_url( 'him', 'perfume' ) ); ?>">Men's perfumes</a>
						</div>
						<div>
							<p class="dl-mega__h">Top brands</p>
							<?php foreach ( array( 'Tom Ford', 'Dior', 'Creed', 'Armani', 'Gucci', 'Versace', 'Lancome', 'Afnan' ) as $dl_name ) : ?>
								<a href="<?php echo esc_url( add_query_arg( array( 's' => $dl_name, 'post_type' => 'product' ), home_url( '/' ) ) ); ?>"><?php echo esc_html( $dl_name ); ?></a>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
			</div>
			<div class="dl-nav__item">
				<a class="dl-nav__link dl-nav__link--main" href="<?php echo esc_url( dl_cat_url( 'watches' ) ); ?>">Watches<svg viewBox="0 0 12 12" aria-hidden="true"><path d="m2 4 4 4 4-4"/></svg></a>
				<div class="dl-mega">
					<div class="dl-wrap dl-mega__in">
						<div>
							<p class="dl-mega__h">Shop by gender</p>
							<a href="<?php echo esc_url( dl_shop_url( 'him', 'watch' ) ); ?>">Men's watches</a>
							<a href="<?php echo esc_url( dl_shop_url( 'her', 'watch' ) ); ?>">Women's watches</a>
						</div>
						<div class="dl-mega__wide">
							<p class="dl-mega__h">Shop by brand</p>
							<div class="dl-mega__cols">
								<?php foreach ( $dl_brands as $dl_brand ) : ?>
									<a href="<?php echo esc_url( get_term_link( $dl_brand ) ); ?>"><?php echo esc_html( $dl_brand->name ); ?></a>
								<?php endforeach; ?>
							</div>
						</div>
					</div>
				</div>
			</div>
			<a class="dl-nav__link" href="<?php echo esc_url( dl_shop_url( 'her', 'perfume' ) ); ?>">Women's Perfumes</a>
			<a class="dl-nav__link" href="<?php echo esc_url( dl_shop_url( 'him', 'perfume' ) ); ?>">Men's Perfumes</a>
			<a class="dl-nav__link" href="<?php echo esc_url( dl_cat_url( 'arabic-perfumes' ) ); ?>">Arabic Perfumes</a>
			<?php foreach ( $dl_quick as $dl_label => $dl_slug ) : ?>
				<a class="dl-nav__link" href="<?php echo esc_url( dl_cat_url( $dl_slug ) ); ?>"><?php echo esc_html( $dl_label ); ?></a>
			<?php endforeach; ?>
			<a class="dl-nav__link dl-nav__link--gift" href="<?php echo esc_url( home_url( '/#gift-finder' ) ); ?>">Gift Finder</a>
		</div>
	</nav>
</header>

<main id="main">
