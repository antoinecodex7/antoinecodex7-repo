<?php
/**
 * Configuration du thème.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Supports, menus, tailles d'images, traductions.
 */
function acx_setup() {
	add_theme_support( 'title-tag' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'html5',
		array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' )
	);
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 80,
			'width'       => 320,
			'flex-height' => true,
			'flex-width'  => true,
		)
	);

	add_editor_style( array( 'assets/css/fonts.css', 'assets/css/editor.css' ) );

	register_nav_menus(
		array(
			'primary' => __( 'Navigation principale', 'antoine-codex-immersive' ),
			'footer'  => __( 'Pied de page', 'antoine-codex-immersive' ),
			'legal'   => __( 'Liens légaux', 'antoine-codex-immersive' ),
		)
	);

	add_image_size( 'acx-portrait', 900, 1125, true );
	add_image_size( 'acx-wide', 1800, 1000, true );

	add_post_type_support( 'page', 'excerpt' );
}
add_action( 'after_setup_theme', 'acx_setup' );

/**
 * Largeur de contenu.
 */
function acx_content_width() {
	$GLOBALS['content_width'] = apply_filters( 'acx_content_width', 1200 );
}
add_action( 'after_setup_theme', 'acx_content_width', 0 );

/**
 * Zone de widgets du pied de page (facultative).
 */
function acx_widgets_init() {
	register_sidebar(
		array(
			'name'          => __( 'Pied de page', 'antoine-codex-immersive' ),
			'id'            => 'footer-1',
			'description'   => __( 'Widgets affichés au-dessus des mentions du pied de page.', 'antoine-codex-immersive' ),
			'before_widget' => '<section id="%1$s" class="widget %2$s">',
			'after_widget'  => '</section>',
			'before_title'  => '<h2 class="widget-title mono">',
			'after_title'   => '</h2>',
		)
	);
}
add_action( 'widgets_init', 'acx_widgets_init' );

/**
 * Classes du body.
 *
 * @param array $classes Classes.
 * @return array
 */
function acx_body_classes( $classes ) {
	$classes[] = 'acx';
	if ( ! acx_opt( 'fx_webgl' ) ) {
		$classes[] = 'acx-no-webgl';
	}
	if ( is_page() ) {
		foreach ( array( 'diagnostic', 'services', 'method', 'founder', 'contact' ) as $role ) {
			if ( acx_page_id( $role ) === get_queried_object_id() ) {
				$classes[] = 'acx-role-' . $role;
			}
		}
	}
	return $classes;
}
add_filter( 'body_class', 'acx_body_classes' );

/**
 * Applique automatiquement le gabarit immersif adapté aux pages clés
 * (uniquement si aucun gabarit n'a été choisi explicitement pour la page).
 *
 * @param string $template Gabarit résolu.
 * @return string
 */
function acx_role_template( $template ) {
	if ( ! is_page() || is_front_page() ) {
		return $template;
	}

	$page_id  = get_queried_object_id();
	$assigned = get_page_template_slug( $page_id );

	// Gabarit choisi dans l'éditeur ET existant dans ce thème : on le respecte.
	if ( $assigned && locate_template( $assigned ) ) {
		return $template;
	}

	$files = array(
		'diagnostic' => 'page-templates/template-diagnostic.php',
		'services'   => 'page-templates/template-services.php',
		'method'     => 'page-templates/template-methodologie.php',
		'founder'    => 'page-templates/template-fondateur.php',
		'contact'    => 'page-templates/template-contact.php',
	);

	foreach ( $files as $role => $file ) {
		if ( acx_page_id( $role ) === $page_id ) {
			$located = locate_template( $file );
			return $located ? $located : $template;
		}
	}

	return $template;
}
add_filter( 'template_include', 'acx_role_template', 20 );

/**
 * Menu de repli quand aucun menu n'est assigné : pages clés détectées.
 *
 * @param array $args Arguments wp_nav_menu.
 */
function acx_fallback_menu( $args = array() ) {
	$items = array(
		'services'   => __( 'Expertises', 'antoine-codex-immersive' ),
		'method'     => __( 'Méthode', 'antoine-codex-immersive' ),
		'diagnostic' => __( 'Diagnostic', 'antoine-codex-immersive' ),
		'founder'    => __( 'Fondateur', 'antoine-codex-immersive' ),
		'contact'    => __( 'Contact', 'antoine-codex-immersive' ),
	);
	$class = ! empty( $args['menu_class'] ) ? $args['menu_class'] : 'menu';
	echo '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $role => $label ) {
		$url     = acx_page_url( $role );
		$current = acx_page_id( $role ) && is_page( acx_page_id( $role ) );
		printf(
			'<li class="menu-item%1$s"><a href="%2$s"%3$s>%4$s</a></li>',
			$current ? ' current-menu-item' : '',
			esc_url( $url ),
			$current ? ' aria-current="page"' : '',
			esc_html( $label )
		);
	}
	echo '</ul>';
}

/**
 * Extrait : longueur et suite.
 *
 * @return int
 */
function acx_excerpt_length() {
	return 28;
}
add_filter( 'excerpt_length', 'acx_excerpt_length' );

/**
 * Suite d'extrait.
 *
 * @return string
 */
function acx_excerpt_more() {
	return '…';
}
add_filter( 'excerpt_more', 'acx_excerpt_more' );

/**
 * Lien « Aller au contenu » : le main reçoit le focus.
 */
function acx_skip_link() {
	echo '<a class="skip-link" href="#contenu">' . esc_html__( 'Aller au contenu', 'antoine-codex-immersive' ) . '</a>';
}
add_action( 'wp_body_open', 'acx_skip_link', 1 );
