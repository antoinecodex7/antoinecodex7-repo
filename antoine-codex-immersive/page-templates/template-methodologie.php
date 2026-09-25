<?php
/**
 * Template Name: Immersif — Méthodologie
 * Template Post Type: page
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero( array( 'label' => __( 'Méthode', 'antoine-codex-immersive' ) ) );
	?>
	<section class="section method section--light" aria-labelledby="method-title" data-clip>
		<div class="container method__grid">
			<div class="method__aside">
				<?php
				acx_section_head(
					array(
						'index' => '01',
						'label' => acx_text( 'method_label' ),
						'title' => acx_text( 'method_title' ),
						'id'    => 'method-title',
					)
				);
				?>
			</div>
			<?php acx_method_steps(); ?>
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
