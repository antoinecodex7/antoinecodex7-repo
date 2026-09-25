<?php
/**
 * Accueil — expertises.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="section services" id="expertises" aria-labelledby="services-title">
	<div class="container">
		<?php
		acx_section_head(
			array(
				'index' => '01',
				'label' => acx_text( 'services_label' ),
				'title' => acx_text( 'services_title' ),
				'lead'  => acx_text( 'services_lead' ),
				'id'    => 'services-title',
				'class' => 'section-head--split',
			)
		);
		acx_services_grid();
		?>
	</div>
</section>
