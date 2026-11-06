<?php
/**
 * Fallback listing for posts.
 *
 * @package dlight
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="dl-wrap dl-page">
	<h1 class="dl-page__title"><?php echo is_singular() ? esc_html( get_the_title() ) : esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
	<?php
	if ( is_singular() ) {
		while ( have_posts() ) {
			the_post();
			echo '<div class="dl-prose">';
			the_content();
			echo '</div>';
		}
	} else {
		while ( have_posts() ) {
			the_post();
			printf( '<h2 class="dl-h3"><a href="%s">%s</a></h2>', esc_url( get_permalink() ), esc_html( get_the_title() ) );
			echo '<div class="dl-prose">';
			the_excerpt();
			echo '</div>';
		}
		the_posts_pagination();
	}
	?>
</div>
<?php
get_footer();
