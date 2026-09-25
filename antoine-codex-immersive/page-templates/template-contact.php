<?php
/**
 * Template Name: Immersif — Contact
 * Template Post Type: page
 *
 * Le formulaire existant de la page (Contact Form 7, WPForms, etc.) est conservé tel quel.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	acx_page_hero( array( 'label' => __( 'Contact', 'antoine-codex-immersive' ) ) );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'section section--content contact-page' ); ?>>
		<div class="container contact-page__grid">
			<aside class="contact-page__aside" data-reveal>
				<?php
				ob_start();
				$acx_has_details = acx_contact_details( 'contact-details--large' );
				$acx_details     = ob_get_clean();
				if ( $acx_has_details ) {
					echo '<p class="mono contact-page__kicker">' . esc_html__( 'Coordonnées', 'antoine-codex-immersive' ) . '</p>';
					echo $acx_details; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé dans acx_contact_details().
				}
				?>
				<?php acx_social_links(); ?>
				<div class="contact-page__paths">
					<a class="contact-path" href="<?php echo esc_url( acx_page_url( 'quick' ) ); ?>">
						<span class="mono"><?php esc_html_e( 'Quelques minutes', 'antoine-codex-immersive' ); ?></span>
						<strong><?php esc_html_e( 'Quick Diagnostic', 'antoine-codex-immersive' ); ?></strong>
						<?php acx_the_icon( 'arrow' ); ?>
					</a>
					<a class="contact-path" href="<?php echo esc_url( acx_page_url( 'diagnostic' ) ); ?>">
						<span class="mono"><?php esc_html_e( 'Analyse approfondie', 'antoine-codex-immersive' ); ?></span>
						<strong><?php esc_html_e( 'Diagnostic d’entreprise', 'antoine-codex-immersive' ); ?></strong>
						<?php acx_the_icon( 'arrow' ); ?>
					</a>
					<?php if ( acx_core_is_public() ) : ?>
						<button type="button" class="contact-path" data-acx-core-open>
							<span class="mono"><?php esc_html_e( 'Conversation IA', 'antoine-codex-immersive' ); ?></span>
							<strong>ANTOINE CODEX CORE</strong>
							<?php acx_the_icon( 'spark' ); ?>
						</button>
					<?php endif; ?>
				</div>
			</aside>
			<div class="contact-page__main">
				<div class="entry-content">
					<?php the_content(); ?>
				</div>
				<?php
				if ( acx_opt( 'contact_form_enabled' ) && ! has_shortcode( get_the_content(), 'acx_contact_form' ) ) {
					echo do_shortcode( '[acx_contact_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendu échappé dans le code court.
				}
				?>
			</div>
		</div>
	</article>
	<?php
endwhile;

get_footer();
