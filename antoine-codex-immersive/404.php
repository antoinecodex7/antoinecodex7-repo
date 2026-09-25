<?php
/**
 * Page introuvable.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

acx_page_hero(
	array(
		'title' => __( 'Cette page n’existe pas — ou plus.', 'antoine-codex-immersive' ),
		'lead'  => __( 'Le lien est peut-être ancien. Voici quelques points de départ.', 'antoine-codex-immersive' ),
		'label' => '404',
	)
);
?>
<section class="section section--content">
	<div class="container container--narrow">
		<div class="btn-row" data-reveal>
			<?php acx_button( __( 'Retour à l’accueil', 'antoine-codex-immersive' ), home_url( '/' ), 'primary' ); ?>
			<?php acx_button( __( 'Diagnostic d’entreprise', 'antoine-codex-immersive' ), acx_page_url( 'diagnostic' ), 'ghost' ); ?>
		</div>
		<div class="search-again" data-reveal><?php get_search_form(); ?></div>
	</div>
</section>
<?php
get_footer();
