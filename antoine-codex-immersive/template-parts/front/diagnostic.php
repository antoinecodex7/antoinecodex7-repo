<?php
/**
 * Accueil — les portes d'entrée du diagnostic.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section diagnostic" id="diagnostic" aria-labelledby="diagnostic-title">
	<div class="diagnostic__halo" aria-hidden="true"></div>
	<div class="container">
		<?php
		acx_section_head(
			array(
				'index' => '02',
				'label' => acx_text( 'diag_label' ),
				'title' => acx_text( 'diag_title' ),
				'lead'  => acx_text( 'diag_lead' ),
				'id'    => 'diagnostic-title',
			)
		);
		acx_diagnostic_paths( 'front' );
		?>
	</div>
</section>
