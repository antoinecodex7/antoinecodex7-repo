<?php
/**
 * Styles et compositions de blocs : pour habiller le contenu existant des pages
 * dans le langage visuel du thème, directement depuis l'éditeur.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Styles de blocs.
 */
function acx_register_block_styles() {
	if ( ! function_exists( 'register_block_style' ) ) {
		return;
	}
	register_block_style( 'core/group', array( 'name' => 'acx-panel', 'label' => __( 'Panneau ANTOINE CODEX', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/group', array( 'name' => 'acx-light', 'label' => __( 'Section claire', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/heading', array( 'name' => 'acx-eyebrow', 'label' => __( 'Étiquette monospace', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/paragraph', array( 'name' => 'acx-eyebrow', 'label' => __( 'Étiquette monospace', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/paragraph', array( 'name' => 'acx-lead', 'label' => __( 'Chapeau', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/button', array( 'name' => 'acx-ghost', 'label' => __( 'Contour', 'antoine-codex-immersive' ) ) );
	register_block_style( 'core/list', array( 'name' => 'acx-steps', 'label' => __( 'Étapes numérotées', 'antoine-codex-immersive' ) ) );
}
add_action( 'init', 'acx_register_block_styles' );

/**
 * Compositions (patterns). Textes génériques à remplacer : aucune donnée inventée.
 */
function acx_register_block_patterns() {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}
	register_block_pattern_category( 'antoine-codex', array( 'label' => 'ANTOINE CODEX' ) );

	register_block_pattern(
		'antoine-codex/cta',
		array(
			'title'      => __( 'Appel à l’action — diagnostic', 'antoine-codex-immersive' ),
			'categories' => array( 'antoine-codex' ),
			'content'    => '<!-- wp:group {"className":"is-style-acx-panel","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-acx-panel"><!-- wp:paragraph {"className":"is-style-acx-eyebrow"} -->
<p class="is-style-acx-eyebrow">' . esc_html__( 'Prochaine étape', 'antoine-codex-immersive' ) . '</p>
<!-- /wp:paragraph -->
<!-- wp:heading -->
<h2 class="wp-block-heading">' . esc_html__( 'Commençons par comprendre.', 'antoine-codex-immersive' ) . '</h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"className":"is-style-acx-lead"} -->
<p class="is-style-acx-lead">' . esc_html__( 'Le diagnostic est le point de départ de chaque accompagnement.', 'antoine-codex-immersive' ) . '</p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="' . esc_url( acx_page_url( 'diagnostic' ) ) . '">' . esc_html__( 'Lancer le diagnostic', 'antoine-codex-immersive' ) . '</a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-acx-ghost"} -->
<div class="wp-block-button is-style-acx-ghost"><a class="wp-block-button__link wp-element-button" href="' . esc_url( acx_page_url( 'contact' ) ) . '">' . esc_html__( 'Nous contacter', 'antoine-codex-immersive' ) . '</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group -->',
		)
	);

	register_block_pattern(
		'antoine-codex/steps',
		array(
			'title'      => __( 'Méthode en trois étapes', 'antoine-codex-immersive' ),
			'categories' => array( 'antoine-codex' ),
			'content'    => '<!-- wp:paragraph {"className":"is-style-acx-eyebrow"} -->
<p class="is-style-acx-eyebrow">' . esc_html__( 'Méthode', 'antoine-codex-immersive' ) . '</p>
<!-- /wp:paragraph -->
<!-- wp:list {"ordered":true,"className":"is-style-acx-steps"} -->
<ol class="wp-block-list is-style-acx-steps"><!-- wp:list-item -->
<li><strong>' . esc_html__( 'Comprendre', 'antoine-codex-immersive' ) . '</strong> — …</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>' . esc_html__( 'Décider', 'antoine-codex-immersive' ) . '</strong> — …</li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><strong>' . esc_html__( 'Transformer', 'antoine-codex-immersive' ) . '</strong> — …</li>
<!-- /wp:list-item --></ol>
<!-- /wp:list -->',
		)
	);

	register_block_pattern(
		'antoine-codex/light-section',
		array(
			'title'      => __( 'Section claire avec titre', 'antoine-codex-immersive' ),
			'categories' => array( 'antoine-codex' ),
			'content'    => '<!-- wp:group {"className":"is-style-acx-light","layout":{"type":"constrained"}} -->
<div class="wp-block-group is-style-acx-light"><!-- wp:heading {"className":"is-style-acx-eyebrow"} -->
<h2 class="wp-block-heading is-style-acx-eyebrow">' . esc_html__( 'Étiquette', 'antoine-codex-immersive' ) . '</h2>
<!-- /wp:heading -->
<!-- wp:heading {"level":3} -->
<h3 class="wp-block-heading">' . esc_html__( 'Titre de la section', 'antoine-codex-immersive' ) . '</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p>…</p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->',
		)
	);
}
add_action( 'init', 'acx_register_block_patterns' );
