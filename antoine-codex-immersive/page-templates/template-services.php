<?php
/**
 * Template Name: Immersif — Services
 * Template Post Type: page
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero( array( 'label' => __( 'Expertises', 'antoine-codex-immersive' ) ) );
	?>
	<section class="section section--tight services" aria-label="<?php esc_attr_e( 'Expertises', 'antoine-codex-immersive' ); ?>">
		<div class="container">
			<?php acx_services_grid( 'h2' ); ?>
		</div>
	</section>

	<?php if ( '' !== trim( get_the_content() ) ) : ?>
		<article id="post-<?php the_ID(); ?>" <?php post_class( 'section section--content' ); ?>>
			<div class="container">
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</div>
		</article>
	<?php endif; ?>
	<?php
endwhile;

get_footer();
