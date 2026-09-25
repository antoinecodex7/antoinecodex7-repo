<?php
/**
 * Accueil — manifeste (texte révélé mot à mot au défilement).
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section manifesto" aria-labelledby="manifesto-label">
	<div class="container manifesto__grid">
		<p class="mono manifesto__label" id="manifesto-label"><span class="section-head__index">00</span> <?php echo esc_html( acx_text( 'manifesto_label' ) ); ?></p>
		<p class="manifesto__text" data-scrub-words><?php echo esc_html( acx_text( 'manifesto_text' ) ); ?></p>
	</div>
</section>
