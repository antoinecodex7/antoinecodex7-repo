<?php
/**
 * Template Name: Pleine largeur (constructeur de pages)
 * Template Post Type: page, post
 *
 * En-tête et pied du thème, contenu sans marges : pour Elementor ou tout constructeur.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'fullwidth-content' ); ?>>
		<?php the_content(); ?>
	</article>
	<?php
endwhile;

get_footer();
