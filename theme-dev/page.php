<?php
/**
 * Default page.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) {
	the_post();
	?>
	<article class="dl-wrap dl-page">
		<h1 class="dl-page__title"><?php the_title(); ?></h1>
		<div class="dl-prose"><?php the_content(); ?></div>
	</article>
	<?php
}

get_footer();
