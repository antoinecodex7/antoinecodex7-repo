<?php
/**
 * Chargement des ressources.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Version d'un fichier (date de modification) pour le cache navigateur.
 *
 * @param string $rel Chemin relatif au thème.
 * @return string
 */
function acx_asset_ver( $rel ) {
	$path = ACX_DIR . '/' . $rel;
	return file_exists( $path ) ? ACX_VERSION . '.' . filemtime( $path ) : ACX_VERSION;
}

/**
 * Styles et scripts publics.
 */
function acx_enqueue_assets() {
	wp_enqueue_style( 'acx-fonts', ACX_URI . '/assets/css/fonts.css', array(), acx_asset_ver( 'assets/css/fonts.css' ) );
	wp_enqueue_style( 'acx-main', ACX_URI . '/assets/css/main.css', array( 'acx-fonts' ), acx_asset_ver( 'assets/css/main.css' ) );

	wp_enqueue_script(
		'acx-main',
		ACX_URI . '/assets/js/main.js',
		array(),
		acx_asset_ver( 'assets/js/main.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$config = array(
		'fieldUrl'  => ACX_URI . '/assets/js/field.js?ver=' . rawurlencode( acx_asset_ver( 'assets/js/field.js' ) ),
		'webgl'     => (bool) acx_opt( 'fx_webgl' ),
		'density'   => (string) acx_opt( 'fx_density' ),
		'pointerFx' => (bool) acx_opt( 'fx_pointer' ),
		'i18n'      => array(
			'menuOpen'  => __( 'Ouvrir le menu', 'antoine-codex-immersive' ),
			'menuClose' => __( 'Fermer le menu', 'antoine-codex-immersive' ),
		),
	);
	wp_add_inline_script( 'acx-main', 'window.ACX=' . wp_json_encode( $config ) . ';', 'before' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'acx_enqueue_assets' );

/**
 * Classe « js » immédiate (évite le flash des animations) + filet de sécurité :
 * si main.js ne s'exécute pas sous 3 s, tout le contenu est affiché sans animation.
 */
function acx_js_detection() {
	wp_print_inline_script_tag( "(function(d){d.className=d.className.replace('no-js','js');setTimeout(function(){if(!window.ACXReady){d.className+=' acx-failsafe';}},3000);})(document.documentElement);" );
}
add_action( 'wp_head', 'acx_js_detection', 0 );

/**
 * Préchargement des polices principales et indication de couleur du navigateur.
 */
function acx_head_hints() {
	$fonts = array( 'syne-latin-wght-normal.woff2', 'manrope-latin-wght-normal.woff2' );
	foreach ( $fonts as $font ) {
		printf(
			'<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n",
			esc_url( ACX_URI . '/assets/fonts/' . $font )
		);
	}
	echo '<meta name="theme-color" content="#050508">' . "\n";
	echo '<meta name="color-scheme" content="dark">' . "\n";
}
add_action( 'wp_head', 'acx_head_hints', 1 );
