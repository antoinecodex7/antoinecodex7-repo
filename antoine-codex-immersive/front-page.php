<?php
/**
 * Page d'accueil.
 *
 * Si une page statique est définie comme accueil, son contenu est conservé et peut être
 * affiché selon le réglage « Contenu de la page d'accueil statique ».
 * Si l'accueil affiche les derniers articles, index.php/home.php prend le relais
 * pour la liste — ici on affiche les sections immersives.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

$acx_mode        = acx_opt( 'front_content_mode' );
$acx_has_content = 'page' === get_option( 'show_on_front' ) && have_posts();

get_template_part( 'template-parts/front/hero' );

if ( 'content' !== $acx_mode ) {
	get_template_part( 'template-parts/front/manifesto' );
	get_template_part( 'template-parts/front/services' );
	get_template_part( 'template-parts/front/diagnostic' );
	get_template_part( 'template-parts/front/method' );
	get_template_part( 'template-parts/front/territory' );
	get_template_part( 'template-parts/front/founder' );
}

if ( $acx_has_content && in_array( $acx_mode, array( 'designed_content', 'content' ), true ) ) {
	while ( have_posts() ) {
		the_post();
		if ( '' === trim( get_the_content() ) ) {
			continue;
		}
		?>
		<section class="section section--content" aria-label="<?php echo esc_attr( get_the_title() ); ?>">
			<div class="container">
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
			</div>
		</section>
		<?php
	}
}

get_footer();
