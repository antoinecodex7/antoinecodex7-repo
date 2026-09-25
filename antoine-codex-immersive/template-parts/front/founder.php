<?php
/**
 * Accueil — fondateur (texte du Personnaliseur ou extrait de la page Fondateur ; rien d'inventé).
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

$acx_founder_id = acx_page_id( 'founder' );
$acx_text       = acx_text( 'founder_text' );
if ( '' === trim( $acx_text ) && $acx_founder_id && has_excerpt( $acx_founder_id ) ) {
	$acx_text = get_the_excerpt( $acx_founder_id );
}
$acx_image = absint( acx_opt( 'founder_image' ) );
if ( ! $acx_image && $acx_founder_id ) {
	$acx_image = (int) get_post_thumbnail_id( $acx_founder_id );
}
?>
<section class="section founder" id="fondateur" aria-labelledby="founder-title">
	<div class="container founder__grid">
		<figure class="founder__media" data-reveal>
			<?php if ( $acx_image ) : ?>
				<?php echo wp_get_attachment_image( $acx_image, 'acx-portrait', false, array( 'class' => 'founder__img', 'loading' => 'lazy', 'sizes' => '(min-width: 900px) 40vw, 100vw' ) ); ?>
			<?php else : ?>
				<div class="founder__placeholder" aria-hidden="true">
					<span class="founder__monogram">AC</span>
				</div>
			<?php endif; ?>
			<span class="founder__frame" aria-hidden="true"></span>
		</figure>
		<div class="founder__body">
			<?php
			acx_section_head(
				array(
					'index' => '05',
					'label' => acx_text( 'founder_label' ),
					'title' => acx_text( 'founder_title' ),
					'lead'  => $acx_text,
					'id'    => 'founder-title',
				)
			);
			?>
			<div class="btn-row" data-reveal>
				<?php acx_button( acx_text( 'founder_cta' ), acx_page_url( 'founder' ), 'ghost' ); ?>
			</div>
		</div>
	</div>
</section>
