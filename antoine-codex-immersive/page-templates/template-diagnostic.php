<?php
/**
 * Template Name: Immersif — Diagnostic d’entreprise
 * Template Post Type: page
 *
 * Page « Diagnostic d’entreprise & Stratégie de croissance ». Le contenu existant de la
 * page (formulaires, Quick Diagnostic, codes courts) est affiché intégralement.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero(
		array(
			'label' => __( 'Diagnostic', 'antoine-codex-immersive' ),
			'class' => 'page-hero--diagnostic',
		)
	);
	?>
	<section class="section section--tight diagnostic diagnostic--page" aria-labelledby="diag-paths-title">
		<div class="container">
			<h2 class="screen-reader-text" id="diag-paths-title"><?php esc_html_e( 'Choisir une démarche', 'antoine-codex-immersive' ); ?></h2>
			<?php acx_diagnostic_paths( 'page' ); ?>
		</div>
	</section>

	<article id="diagnostic-contenu" <?php post_class( 'section section--content' ); ?>>
		<div class="container">
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
