<?php
/**
 * Page standard : ouverture immersive + contenu existant de la page.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero();
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'section section--content' ); ?>>
		<div class="container">
			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages(
					array(
						'before' => '<nav class="page-links mono" aria-label="' . esc_attr__( 'Pages', 'antoine-codex-immersive' ) . '">',
						'after'  => '</nav>',
					)
				);
				?>
			</div>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="container container--narrow">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
