<?php
/**
 * Accueil — ouverture plein écran avec champ de particules.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

$acx_second_url = acx_opt( 'hero_cta_second_url' );
$acx_second_url = $acx_second_url ? $acx_second_url : acx_page_url( 'services' );
?>
<section class="hero" data-hero aria-labelledby="hero-title">
	<div class="hero__visual" aria-hidden="true">
		<div class="field-poster"></div>
		<canvas class="field-canvas" data-acx-field="full"></canvas>
		<div class="hero__veil"></div>
	</div>

	<div class="hero__inner container">
		<p class="hero__eyebrow mono" data-reveal>
			<span class="hero__dot" aria-hidden="true"></span>
			<?php echo esc_html( acx_text( 'hero_eyebrow' ) ); ?>
		</p>

		<h1 class="hero__title" id="hero-title">
			<?php acx_hero_title_lines( acx_text( 'hero_title' ) ); ?>
		</h1>

		<div class="hero__bottom">
			<p class="hero__lead" data-reveal><?php echo esc_html( acx_text( 'hero_lead' ) ); ?></p>
			<div class="btn-row hero__actions" data-reveal>
				<?php acx_button( acx_text( 'hero_cta_primary' ), acx_page_url( 'diagnostic' ), 'primary' ); ?>
				<?php acx_button( acx_text( 'hero_cta_second' ), $acx_second_url, 'ghost' ); ?>
			</div>
		</div>
	</div>

	<div class="hero__meta container mono" aria-hidden="true">
		<span>43.7102° N — 7.2620° E</span>
		<span class="hero__scroll"><span class="hero__scroll-line"></span><?php esc_html_e( 'Défiler pour révéler la structure', 'antoine-codex-immersive' ); ?></span>
		<span data-hero-readout>STRUCT · 000%</span>
	</div>
</section>

<div class="marquee" aria-hidden="true" data-marquee>
	<div class="marquee__track">
		<?php for ( $acx_i = 0; $acx_i < 2; $acx_i++ ) : ?>
			<span class="marquee__group">
				<?php foreach ( acx_services() as $acx_service ) : ?>
					<span class="marquee__item"><?php echo esc_html( $acx_service['title'] ); ?></span><span class="marquee__sep">✦</span>
				<?php endforeach; ?>
			</span>
		<?php endfor; ?>
	</div>
</div>
