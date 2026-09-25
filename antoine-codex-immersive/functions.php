<?php
/**
 * ANTOINE CODEX Immersive — amorçage du thème.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

define( 'ACX_VERSION', '1.0.0' );
define( 'ACX_DIR', get_template_directory() );
define( 'ACX_URI', get_template_directory_uri() );

$acx_includes = array(
	'inc/helpers.php',        // Options, pages clés, icônes.
	'inc/setup.php',          // Supports du thème, menus, gabarits automatiques.
	'inc/enqueue.php',        // Chargement des ressources.
	'inc/customizer.php',     // Réglages éditables (Apparence → Personnaliser).
	'inc/template-tags.php',  // Composants d'affichage.
	'inc/multilingual.php',   // Polylang / WPML / TranslatePress.
	'inc/seo.php',            // Métadonnées de secours (désactivées si une extension SEO est active).
	'inc/blocks.php',         // Styles et compositions de blocs.
	'inc/forms.php',          // Formulaire de contact de secours (optionnel).
	'inc/core-bridge.php',    // ANTOINE CODEX CORE : passerelle serveur + panneau.
	'inc/admin.php',          // Pages d'administration : réglages CORE et diagnostic de migration.
);

foreach ( $acx_includes as $acx_file ) {
	require_once ACX_DIR . '/' . $acx_file;
}
unset( $acx_includes, $acx_file );
