<?php
/**
 * Home page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();

$dl_shop = wc_get_page_permalink( 'shop' );
$dl_img  = DL_URI . '/assets/img/';

$dl_slides = array(
	array(
		'img'   => 'slide-perfume',
		'kick'  => 'Best-selling perfumes',
		'title' => 'Perfumes & Watches from Top Brands',
		'text'  => 'Tom Ford, Creed, Dior, Givenchy and more, all in one place.',
		'cta'   => array( 'Shop Perfumes', dl_cat_url( 'perfumes' ) ),
		'alt'   => 'Tom Ford Oud Wood, Givenchy Equivoque, Creed Aventus, Tom Ford Black Orchid and Dior J\'adore',
	),
	array(
		'img'   => 'slide-watch',
		'kick'  => 'Men\'s watches',
		'title' => 'Watches Made to Be Noticed',
		'text'  => 'Curren, Casio, Fossil, Poedagar and more.',
		'cta'   => array( 'Shop Men\'s Watches', dl_shop_url( 'him', 'watch' ) ),
		'alt'   => 'Fossil, Poedagar, Curren and Casio men\'s watches',
	),
	array(
		'img'   => 'slide-her',
		'kick'  => 'Gifts for her',
		'title' => 'Elegant Gifts She Will Love',
		'text'  => 'Beautiful watches and fragrances, ready to give.',
		'cta'   => array( 'Shop Women\'s Watches', dl_shop_url( 'her', 'watch' ) ),
		'alt'   => 'Curren, Fossil, Michael Kors and Hannah Martin women\'s watches with Dior J\'adore',
	),
);

$dl_cats = array(
	array( "Women's Perfumes", dl_shop_url( 'her', 'perfume' ), 'women-perfumes', 'warm' ),
	array( "Men's Perfumes", dl_shop_url( 'him', 'perfume' ), 'men-perfumes', 'dark' ),
	array( 'Tester Perfumes', dl_cat_url( 'tester-perfumes' ), 'tester-perfumes', 'gold' ),
	array( 'Original Perfumes', dl_cat_url( 'original-perfumes' ), 'original-perfumes', 'dark' ),
	array( 'Arabic Perfumes', dl_cat_url( 'arabic-perfumes' ), 'arabic-perfumes', 'wine' ),
	array( "Men's Watches", dl_shop_url( 'him', 'watch' ), 'men-watches', 'steel' ),
	array( "Women's Watches", dl_shop_url( 'her', 'watch' ), 'women-watches', 'rose' ),
	array( 'All Watches', dl_cat_url( 'watches' ), 'brands', 'dark' ),
);
?>

<section class="dl-slider" id="dl-slider" aria-roledescription="carousel" aria-label="Featured collections">
	<div class="dl-slides" id="dl-slides" tabindex="0">
		<?php foreach ( $dl_slides as $dl_i => $dl_s ) : ?>
			<article class="dl-slide" aria-roledescription="slide" aria-label="<?php echo esc_attr( ( $dl_i + 1 ) . ' of ' . count( $dl_slides ) ); ?>">
				<picture>
					<source media="(max-width: 760px)" srcset="<?php echo esc_url( $dl_img . $dl_s['img'] . '-phone.webp' ); ?>" width="820" height="900">
					<img src="<?php echo esc_url( $dl_img . $dl_s['img'] . '-wide.webp' ); ?>" width="1600" height="560" alt="<?php echo esc_attr( $dl_s['alt'] ); ?>" <?php echo 0 === $dl_i ? 'fetchpriority="high"' : 'loading="lazy"'; ?>>
				</picture>
				<div class="dl-wrap dl-slide__wrap">
					<div class="dl-slide__text">
						<p class="dl-slide__kick"><?php echo esc_html( $dl_s['kick'] ); ?></p>
						<<?php echo 0 === $dl_i ? 'h1' : 'h2'; ?> class="dl-slide__title"><?php echo esc_html( $dl_s['title'] ); ?></<?php echo 0 === $dl_i ? 'h1' : 'h2'; ?>>
						<p class="dl-slide__sub"><?php echo esc_html( $dl_s['text'] ); ?></p>
						<a class="dl-btn dl-btn--gold" href="<?php echo esc_url( $dl_s['cta'][1] ); ?>"><?php echo esc_html( $dl_s['cta'][0] ); ?></a>
					</div>
				</div>
			</article>
		<?php endforeach; ?>
	</div>
	<button type="button" class="dl-arrow dl-arrow--prev js-slide-prev" aria-label="Previous banner"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m15 5-7 7 7 7"/></svg></button>
	<button type="button" class="dl-arrow dl-arrow--next js-slide-next" aria-label="Next banner"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg></button>
	<div class="dl-dots">
		<?php foreach ( $dl_slides as $dl_i => $dl_s ) : ?>
			<button type="button" class="js-slide-dot<?php echo 0 === $dl_i ? ' is-on' : ''; ?>" data-i="<?php echo (int) $dl_i; ?>" aria-label="<?php echo esc_attr( 'Show banner ' . ( $dl_i + 1 ) ); ?>"></button>
		<?php endforeach; ?>
	</div>
</section>

<section class="dl-trust" aria-label="Why shop with us">
	<ul class="dl-wrap dl-trust__in">
		<li>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7.500" cy="17.500" r="1.800"/><circle cx="17.500" cy="17.500" r="1.800"/></svg>
			<div><strong>Delivery Across Kenya</strong><span>To your door, wherever you are</span></div>
		</li>
		<li>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18v12H3zM3 10h18"/><path d="M7 14h3"/></svg>
			<div><strong>Secure Payment</strong><span>Pay easily with M-Pesa</span></div>
		</li>
		<li>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9.500 5.500 4h13L20 9.500M5 9.500V20h14V9.500M4 9.500h16"/><path d="M10 20v-5h4v5"/></svg>
			<div><strong>Visit Our Shop</strong><span>Rasumal House, Tom Mboya St</span></div>
		</li>
		<li>
			<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1.200-4A8 8 0 1 1 8 18.800z"/></svg>
			<div><strong>Help on WhatsApp</strong><span>Real people, ready to help</span></div>
		</li>
	</ul>
</section>

<section class="dl-section" aria-labelledby="cat-title">
	<div class="dl-wrap">
		<div class="dl-head"><h2 id="cat-title">Shop by Category</h2></div>
		<div class="dl-cats">
			<?php foreach ( $dl_cats as $dl_c ) : ?>
				<a class="dl-cat dl-cat--<?php echo esc_attr( $dl_c[3] ); ?>" href="<?php echo esc_url( $dl_c[1] ); ?>">
					<span class="dl-cat__text">
						<strong><?php echo esc_html( $dl_c[0] ); ?></strong>
						<span class="dl-cat__go">Shop now <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
					</span>
					<img src="<?php echo esc_url( $dl_img . 'cats/' . $dl_c[2] . '.webp' ); ?>" alt="" loading="lazy">
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="dl-section" aria-labelledby="bp-title">
	<div class="dl-wrap">
		<div class="dl-head">
			<h2 id="bp-title">Best Selling Perfumes</h2>
			<a class="dl-more" href="<?php echo esc_url( dl_cat_url( 'perfumes' ) ); ?>">View all</a>
		</div>
		<div class="dl-grid dl-grid--5"><?php dl_cards( dl_top_seller_ids( 10, 'perfumes' ) ); ?></div>
	</div>
</section>

<section class="dl-section" aria-label="Featured collections">
	<div class="dl-wrap dl-offers">
		<a class="dl-offer" href="<?php echo esc_url( dl_cat_url( 'arabic-perfumes' ) ); ?>">
			<img src="<?php echo esc_url( $dl_img . 'offer-arabic.webp' ); ?>" width="960" height="400" loading="lazy" alt="Afnan 9PM Elixir perfume and box">
			<span class="dl-offer__text">
				<span class="dl-slide__kick">Arabic perfumes</span>
				<strong>Rich Scents from Arabia</strong>
				<span class="dl-offer__go">Shop now</span>
			</span>
		</a>
		<a class="dl-offer" href="<?php echo esc_url( dl_cat_url( 'casio' ) ); ?>">
			<img src="<?php echo esc_url( $dl_img . 'offer-casio.webp' ); ?>" width="960" height="400" loading="lazy" alt="Casio women's analog, gold digital and men's classic watches">
			<span class="dl-offer__text">
				<span class="dl-slide__kick">Casio</span>
				<strong>Classics for Every Day</strong>
				<span class="dl-offer__go">Shop now</span>
			</span>
		</a>
	</div>
</section>

<section class="dl-section" aria-labelledby="bw-title">
	<div class="dl-wrap">
		<div class="dl-head">
			<h2 id="bw-title">Best Selling Watches</h2>
			<a class="dl-more" href="<?php echo esc_url( dl_cat_url( 'watches' ) ); ?>">View all</a>
		</div>
		<div class="dl-grid dl-grid--5"><?php dl_cards( dl_top_seller_ids( 10, 'watches' ) ); ?></div>
	</div>
</section>

<section class="dl-section" aria-labelledby="brand-title">
	<div class="dl-wrap">
		<div class="dl-head"><h2 id="brand-title">Shop by Brand</h2></div>
		<div class="dl-brands">
			<?php foreach ( array( 'Casio' => 'casio', 'Seiko' => 'seiko', 'Fossil' => 'fs-dz-watches', 'Michael Kors' => 'michael-kors', 'Naviforce' => 'naviforce', 'Curren' => 'curren' ) as $dl_name => $dl_slug ) : ?>
				<a class="dl-brand" href="<?php echo esc_url( dl_cat_url( $dl_slug ) ); ?>"><?php echo esc_html( $dl_name ); ?></a>
			<?php endforeach; ?>
			<?php foreach ( array( 'Tom Ford', 'Dior', 'Creed', 'Armani', 'Gucci', 'Versace' ) as $dl_name ) : ?>
				<a class="dl-brand" href="<?php echo esc_url( add_query_arg( array( 's' => $dl_name, 'post_type' => 'product' ), home_url( '/' ) ) ); ?>"><?php echo esc_html( $dl_name ); ?></a>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<section class="dl-finder" id="gift-finder" aria-labelledby="finder-title">
	<div class="dl-wrap">
		<div class="dl-finder__top">
			<div>
				<h2 id="finder-title">Find the Perfect Gift</h2>
				<p>Choose who it's for and your budget. We'll show you what's in stock.</p>
			</div>
			<form class="dl-finder__form" id="dl-finder" action="<?php echo esc_url( $dl_shop ); ?>" method="get">
				<label><span>Gift for</span>
					<select name="who"><option value="her">Her</option><option value="him">Him</option></select>
				</label>
				<label><span>Type</span>
					<select name="kind"><option value="perfume">Perfume</option><option value="watch">Watch</option><option value="either">Either</option></select>
				</label>
				<label><span>Budget</span>
					<select name="budget">
						<option value="0-5000">Under KSh 5,000</option>
						<option value="5000-10000" selected>KSh 5,000 - 10,000</option>
						<option value="10000-20000">KSh 10,000 - 20,000</option>
						<option value="20000-">Over KSh 20,000</option>
					</select>
				</label>
			</form>
		</div>
		<div class="dl-grid dl-grid--5 dl-finder__results" id="dl-finder-results" aria-live="polite"></div>
		<a class="dl-btn dl-btn--gold dl-finder__all" id="dl-finder-all" href="<?php echo esc_url( $dl_shop ); ?>" hidden>See all</a>
		<noscript><a class="dl-btn dl-btn--gold" href="<?php echo esc_url( $dl_shop ); ?>">Browse the shop</a></noscript>
	</div>
</section>

<section class="dl-section" aria-labelledby="new-title">
	<div class="dl-wrap">
		<div class="dl-head">
			<h2 id="new-title">New Arrivals</h2>
			<a class="dl-more" href="<?php echo esc_url( add_query_arg( 'orderby', 'date', $dl_shop ) ); ?>">View all</a>
		</div>
		<div class="dl-grid dl-grid--5"><?php dl_cards( dl_new_ids( 10 ) ); ?></div>
	</div>
</section>

<section class="dl-store" aria-labelledby="store-title">
	<div class="dl-wrap dl-store__in">
		<div>
			<h2 id="store-title">Visit Our Shop in Nairobi</h2>
			<p>Tom Mboya Street, Rasumal House (opposite Imenti House), 4th floor, shop 14.</p>
		</div>
		<div class="dl-store__actions">
			<a class="dl-btn dl-btn--gold" href="<?php echo esc_url( DL_MAP ); ?>" target="_blank" rel="noopener">Get Directions</a>
			<a class="dl-btn dl-btn--wa" href="<?php echo esc_url( dl_whatsapp_url( 'Hello Dlight Giftshop, I would like to place an order.' ) ); ?>" target="_blank" rel="noopener">Order on WhatsApp</a>
		</div>
	</div>
</section>

<?php
get_footer();
