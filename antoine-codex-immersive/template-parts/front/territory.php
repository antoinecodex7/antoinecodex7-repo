<?php
/**
 * Accueil — territoire : Nice, Côte d'Azur, hôtels indépendants.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

$acx_segments = array(
	__( 'Hôtels indépendants', 'antoine-codex-immersive' ),
	__( 'PME & commerces', 'antoine-codex-immersive' ),
	__( 'Professions libérales', 'antoine-codex-immersive' ),
	__( 'Entrepreneurs & créateurs', 'antoine-codex-immersive' ),
);
?>
<section class="section territory" id="territoire" aria-labelledby="territory-title">
	<div class="container territory__grid">
		<div class="territory__text">
			<?php
			acx_section_head(
				array(
					'index' => '04',
					'label' => acx_text( 'territory_label' ),
					'title' => acx_text( 'territory_title' ),
					'lead'  => acx_text( 'territory_text' ),
					'id'    => 'territory-title',
				)
			);
			?>
			<ul class="territory__segments mono" data-reveal>
				<?php foreach ( $acx_segments as $acx_segment ) : ?>
					<li><?php echo esc_html( $acx_segment ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>

		<div class="territory__visual" data-reveal>
			<div class="radar" aria-hidden="true">
				<span class="radar__ring"></span><span class="radar__ring"></span><span class="radar__ring"></span><span class="radar__ring"></span>
				<span class="radar__sweep"></span>
				<span class="radar__cross"></span>
				<span class="radar__core"></span>
				<span class="radar__label mono">NICE<br>43.7102° N<br>7.2620° E</span>
			</div>
			<article class="hotel-card">
				<p class="mono hotel-card__kicker"><?php esc_html_e( 'Focus', 'antoine-codex-immersive' ); ?></p>
				<h3 class="hotel-card__title"><?php echo esc_html( acx_text( 'hotel_title' ) ); ?></h3>
				<p><?php echo esc_html( acx_text( 'hotel_text' ) ); ?></p>
			</article>
		</div>
	</div>
</section>
