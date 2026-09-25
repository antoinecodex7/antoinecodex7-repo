<?php
/**
 * Template Name: Immersif — Fondateur
 * Template Post Type: page
 *
 * Portrait (image mise en avant) + contenu existant de la page. Aucun texte biographique
 * n'est ajouté par le thème.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero(
		array(
			'label' => __( 'Fondateur', 'antoine-codex-immersive' ),
			'class' => 'page-hero--founder',
		)
	);
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'section section--content founder-page' ); ?>>
		<div class="container founder-page__grid<?php echo has_post_thumbnail() ? '' : ' founder-page__grid--single'; ?>">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="founder__media founder-page__media" data-reveal>
					<?php the_post_thumbnail( 'acx-portrait', array( 'class' => 'founder__img', 'sizes' => '(min-width: 900px) 38vw, 100vw' ) ); ?>
					<span class="founder__frame" aria-hidden="true"></span>
				</figure>
			<?php endif; ?>
			<div class="entry-content">
				<?php the_content(); ?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
