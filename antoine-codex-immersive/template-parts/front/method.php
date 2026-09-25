<?php
/**
 * Accueil — méthode (section claire, transition de découpe).
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

$acx_method_page = acx_page_id( 'method' );
?>
<section class="section method section--light" id="methode" aria-labelledby="method-title" data-clip>
	<div class="container method__grid">
		<div class="method__aside">
			<?php
			acx_section_head(
				array(
					'index' => '03',
					'label' => acx_text( 'method_label' ),
					'title' => acx_text( 'method_title' ),
					'id'    => 'method-title',
				)
			);
			if ( $acx_method_page ) {
				acx_button( __( 'Lire la méthodologie', 'antoine-codex-immersive' ), get_permalink( $acx_method_page ), 'dark' );
			}
			?>
		</div>
		<?php acx_method_steps(); ?>
	</div>
</section>
