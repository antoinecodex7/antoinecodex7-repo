<?php
/**
 * Pied de page.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?>
</main>

<footer class="site-footer" id="contact-footer">
	<div class="site-footer__top container">
		<div class="site-footer__cta" data-reveal>
			<p class="mono site-footer__kicker"><?php esc_html_e( 'Prochaine étape', 'antoine-codex-immersive' ); ?></p>
			<p class="site-footer__title"><?php echo esc_html( acx_text( 'cta_title' ) ); ?></p>
			<div class="btn-row">
				<?php acx_button( acx_text( 'hero_cta_primary' ), acx_page_url( 'diagnostic' ), 'primary' ); ?>
				<?php acx_button( __( 'Nous contacter', 'antoine-codex-immersive' ), acx_page_url( 'contact' ), 'ghost' ); ?>
			</div>
		</div>

		<div class="site-footer__cols">
			<div class="site-footer__col">
				<h2 class="mono site-footer__heading"><?php esc_html_e( 'Navigation', 'antoine-codex-immersive' ); ?></h2>
				<?php
				wp_nav_menu(
					array(
						'theme_location' => has_nav_menu( 'footer' ) ? 'footer' : 'primary',
						'container'      => false,
						'menu_class'     => 'footer-menu',
						'depth'          => 1,
						'fallback_cb'    => 'acx_fallback_menu',
					)
				);
				?>
			</div>
			<div class="site-footer__col">
				<h2 class="mono site-footer__heading"><?php esc_html_e( 'Expertises', 'antoine-codex-immersive' ); ?></h2>
				<ul class="footer-menu">
					<?php foreach ( acx_services() as $service ) : ?>
						<li><a href="<?php echo esc_url( $service['url'] ); ?>"><?php echo esc_html( $service['title'] ); ?></a></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<div class="site-footer__col">
				<h2 class="mono site-footer__heading"><?php esc_html_e( 'Contact', 'antoine-codex-immersive' ); ?></h2>
				<?php
				if ( ! acx_contact_details( 'contact-details--footer' ) ) {
					echo '<p><a href="' . esc_url( acx_page_url( 'contact' ) ) . '">' . esc_html__( 'Page contact', 'antoine-codex-immersive' ) . '</a></p>';
				}
				acx_social_links();
				?>
				<p class="mono site-footer__place">Nice · Côte d’Azur<br>43.7102° N — 7.2620° E</p>
			</div>
		</div>
	</div>

	<?php if ( is_active_sidebar( 'footer-1' ) ) : ?>
		<div class="site-footer__widgets container"><?php dynamic_sidebar( 'footer-1' ); ?></div>
	<?php endif; ?>

	<div class="site-footer__word" aria-hidden="true"><span>ANTOINE</span><span>CODEX</span></div>

	<div class="site-footer__bottom container mono">
		<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
		<?php
		if ( has_nav_menu( 'legal' ) ) {
			wp_nav_menu(
				array(
					'theme_location' => 'legal',
					'container'      => 'nav',
					'container_aria_label' => __( 'Liens légaux', 'antoine-codex-immersive' ),
					'menu_class'     => 'legal-menu',
					'depth'          => 1,
				)
			);
		} elseif ( get_privacy_policy_url() ) {
			echo '<nav aria-label="' . esc_attr__( 'Liens légaux', 'antoine-codex-immersive' ) . '"><ul class="legal-menu"><li>' . get_the_privacy_policy_link() . '</li></ul></nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fonction du cœur.
		}
		?>
		<?php acx_language_switcher( 'lang-switch--footer' ); ?>
		<a class="back-top" href="#contenu"><?php esc_html_e( 'Haut de page', 'antoine-codex-immersive' ); ?> ↑</a>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
