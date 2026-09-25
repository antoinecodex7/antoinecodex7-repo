<?php
/**
 * Personnaliseur : textes, pages clés, coordonnées, effets.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Nettoyage d'une case à cocher.
 *
 * @param mixed $value Valeur.
 * @return bool
 */
function acx_sanitize_checkbox( $value ) {
	return (bool) $value;
}

/**
 * Nettoyage d'un choix parmi une liste.
 *
 * @param string               $value   Valeur.
 * @param WP_Customize_Setting $setting Réglage.
 * @return string
 */
function acx_sanitize_choice( $value, $setting ) {
	$control = $setting->manager->get_control( $setting->id );
	$choices = $control && ! empty( $control->choices ) ? $control->choices : array();
	return array_key_exists( $value, $choices ) ? $value : $setting->default;
}

/**
 * Nettoyage d'un lien (URL absolue, relative ou ancre).
 *
 * @param string $value Valeur.
 * @return string
 */
function acx_sanitize_link( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}
	if ( 0 === strpos( $value, '#' ) ) {
		return '#' . sanitize_title( substr( $value, 1 ) );
	}
	return esc_url_raw( $value );
}

/**
 * Ajoute un réglage + contrôle en une seule ligne.
 *
 * @param WP_Customize_Manager $wp       Gestionnaire.
 * @param string               $section  Section.
 * @param string               $key      Clé sans préfixe.
 * @param string               $label    Libellé.
 * @param string               $type     Type de contrôle.
 * @param array                $extra    Paramètres supplémentaires.
 */
function acx_customizer_field( $wp, $section, $key, $label, $type = 'text', $extra = array() ) {
	$defaults = acx_defaults();
	$default  = array_key_exists( $key, $defaults ) ? $defaults[ $key ] : '';

	$sanitize = 'sanitize_text_field';
	if ( 'textarea' === $type ) {
		$sanitize = 'sanitize_textarea_field';
	} elseif ( 'checkbox' === $type ) {
		$sanitize = 'acx_sanitize_checkbox';
	} elseif ( 'select' === $type || 'radio' === $type ) {
		$sanitize = 'acx_sanitize_choice';
	} elseif ( 'url' === $type ) {
		$sanitize = 'acx_sanitize_link';
	} elseif ( 'email' === $type ) {
		$sanitize = 'sanitize_email';
	} elseif ( 'dropdown-pages' === $type ) {
		$sanitize = 'absint';
	}
	if ( isset( $extra['sanitize'] ) ) {
		$sanitize = $extra['sanitize'];
		unset( $extra['sanitize'] );
	}

	$wp->add_setting(
		'acx_' . $key,
		array(
			'default'           => $default,
			'sanitize_callback' => $sanitize,
			'transport'         => 'refresh',
		)
	);

	$wp->add_control(
		'acx_' . $key,
		array_merge(
			array(
				'label'   => $label,
				'section' => $section,
				'type'    => $type,
			),
			$extra
		)
	);
}

/**
 * Enregistrement du Personnaliseur.
 *
 * @param WP_Customize_Manager $wp Gestionnaire.
 */
function acx_customize_register( $wp ) {
	$wp->add_panel(
		'acx_panel',
		array(
			'title'       => __( 'ANTOINE CODEX — Thème', 'antoine-codex-immersive' ),
			'description' => __( 'Textes de la page d’accueil, pages clés, coordonnées et effets visuels. Laissez un champ vide pour revenir au texte par défaut.', 'antoine-codex-immersive' ),
			'priority'    => 30,
		)
	);

	// --- Accueil : ouverture.
	$wp->add_section( 'acx_hero', array( 'title' => __( 'Accueil — Ouverture', 'antoine-codex-immersive' ), 'panel' => 'acx_panel' ) );
	acx_customizer_field( $wp, 'acx_hero', 'hero_eyebrow', __( 'Surtitre', 'antoine-codex-immersive' ) );
	acx_customizer_field( $wp, 'acx_hero', 'hero_title', __( 'Titre (une phrase par ligne séparée par un point)', 'antoine-codex-immersive' ) );
	acx_customizer_field( $wp, 'acx_hero', 'hero_lead', __( 'Positionnement', 'antoine-codex-immersive' ), 'textarea' );
	acx_customizer_field( $wp, 'acx_hero', 'hero_cta_primary', __( 'Bouton principal (vers le diagnostic)', 'antoine-codex-immersive' ) );
	acx_customizer_field( $wp, 'acx_hero', 'hero_cta_second', __( 'Bouton secondaire', 'antoine-codex-immersive' ) );
	acx_customizer_field( $wp, 'acx_hero', 'hero_cta_second_url', __( 'Lien du bouton secondaire (vide = page Services)', 'antoine-codex-immersive' ), 'url' );
	acx_customizer_field(
		$wp,
		'acx_hero',
		'front_content_mode',
		__( 'Contenu de la page d’accueil statique', 'antoine-codex-immersive' ),
		'select',
		array(
			'choices'     => array(
				'designed'         => __( 'Sections immersives du thème', 'antoine-codex-immersive' ),
				'designed_content' => __( 'Sections immersives + contenu de la page', 'antoine-codex-immersive' ),
				'content'          => __( 'Ouverture immersive + contenu de la page uniquement', 'antoine-codex-immersive' ),
			),
			'description' => __( 'Le contenu existant de votre page d’accueil n’est jamais supprimé : choisissez s’il est affiché.', 'antoine-codex-immersive' ),
		)
	);

	// --- Accueil : sections.
	$wp->add_section( 'acx_sections', array( 'title' => __( 'Accueil — Sections', 'antoine-codex-immersive' ), 'panel' => 'acx_panel' ) );
	$fields = array(
		'manifesto_label' => array( __( 'Approche — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'manifesto_text'  => array( __( 'Approche — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'services_label'  => array( __( 'Expertises — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'services_title'  => array( __( 'Expertises — titre', 'antoine-codex-immersive' ), 'text' ),
		'services_lead'   => array( __( 'Expertises — introduction', 'antoine-codex-immersive' ), 'textarea' ),
		'diag_label'      => array( __( 'Diagnostic — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'diag_title'      => array( __( 'Diagnostic — titre', 'antoine-codex-immersive' ), 'text' ),
		'diag_lead'       => array( __( 'Diagnostic — introduction', 'antoine-codex-immersive' ), 'textarea' ),
		'method_label'    => array( __( 'Méthode — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'method_title'    => array( __( 'Méthode — titre', 'antoine-codex-immersive' ), 'text' ),
		'method_1_title'  => array( __( 'Étape 1 — titre', 'antoine-codex-immersive' ), 'text' ),
		'method_1_text'   => array( __( 'Étape 1 — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'method_2_title'  => array( __( 'Étape 2 — titre', 'antoine-codex-immersive' ), 'text' ),
		'method_2_text'   => array( __( 'Étape 2 — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'method_3_title'  => array( __( 'Étape 3 — titre', 'antoine-codex-immersive' ), 'text' ),
		'method_3_text'   => array( __( 'Étape 3 — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'territory_label' => array( __( 'Territoire — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'territory_title' => array( __( 'Territoire — titre', 'antoine-codex-immersive' ), 'text' ),
		'territory_text'  => array( __( 'Territoire — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'hotel_title'     => array( __( 'Hôtels indépendants — titre', 'antoine-codex-immersive' ), 'text' ),
		'hotel_text'      => array( __( 'Hôtels indépendants — texte', 'antoine-codex-immersive' ), 'textarea' ),
		'founder_label'   => array( __( 'Fondateur — étiquette', 'antoine-codex-immersive' ), 'text' ),
		'founder_title'   => array( __( 'Fondateur — titre', 'antoine-codex-immersive' ), 'textarea' ),
		'founder_text'    => array( __( 'Fondateur — texte (vide = extrait de la page Fondateur)', 'antoine-codex-immersive' ), 'textarea' ),
		'founder_cta'     => array( __( 'Fondateur — bouton', 'antoine-codex-immersive' ), 'text' ),
		'cta_title'       => array( __( 'Appel final — titre', 'antoine-codex-immersive' ), 'text' ),
		'cta_text'        => array( __( 'Appel final — texte', 'antoine-codex-immersive' ), 'textarea' ),
	);
	foreach ( $fields as $key => $def ) {
		acx_customizer_field( $wp, 'acx_sections', $key, $def[0], $def[1] );
	}

	$wp->add_setting(
		'acx_founder_image',
		array(
			'default'           => 0,
			'sanitize_callback' => 'absint',
		)
	);
	$wp->add_control(
		new WP_Customize_Media_Control(
			$wp,
			'acx_founder_image',
			array(
				'label'       => __( 'Fondateur — portrait (vide = image mise en avant de la page Fondateur)', 'antoine-codex-immersive' ),
				'section'     => 'acx_sections',
				'mime_type'   => 'image',
			)
		)
	);

	// --- Expertises.
	$wp->add_section(
		'acx_services',
		array(
			'title'       => __( 'Expertises (6 cartes)', 'antoine-codex-immersive' ),
			'panel'       => 'acx_panel',
			'description' => __( 'Laissez vide pour conserver le texte par défaut. Points clés : un par ligne.', 'antoine-codex-immersive' ),
		)
	);
	foreach ( acx_service_defaults() as $slug => $d ) {
		$name = wp_strip_all_tags( $d['title'] );
		foreach ( array( 'title' => __( 'titre', 'antoine-codex-immersive' ), 'text' => __( 'texte', 'antoine-codex-immersive' ), 'points' => __( 'points clés', 'antoine-codex-immersive' ), 'url' => __( 'lien', 'antoine-codex-immersive' ) ) as $field => $flabel ) {
			$wp->add_setting(
				'acx_service_' . $slug . '_' . $field,
				array(
					'default'           => '',
					'sanitize_callback' => 'url' === $field ? 'acx_sanitize_link' : ( 'title' === $field ? 'sanitize_text_field' : 'sanitize_textarea_field' ),
				)
			);
			$wp->add_control(
				'acx_service_' . $slug . '_' . $field,
				array(
					/* translators: 1: service name, 2: field name. */
					'label'       => sprintf( __( '%1$s — %2$s', 'antoine-codex-immersive' ), $name, $flabel ),
					'section'     => 'acx_services',
					'type'        => in_array( $field, array( 'text', 'points' ), true ) ? 'textarea' : 'text',
					'input_attrs' => 'url' === $field ? array() : array( 'placeholder' => 'points' === $field ? str_replace( "\n", ' / ', $d[ $field ] ) : $d[ $field ] ),
				)
			);
		}
	}

	// --- Pages clés.
	$wp->add_section(
		'acx_pages',
		array(
			'title'       => __( 'Pages clés', 'antoine-codex-immersive' ),
			'panel'       => 'acx_panel',
			'description' => __( 'Par défaut, le thème détecte vos pages existantes par leur adresse ou leur titre. Choisissez-les ici pour être certain des liens. La page choisie reçoit automatiquement la mise en page immersive correspondante (sauf si un gabarit est déjà sélectionné dans l’éditeur).', 'antoine-codex-immersive' ),
		)
	);
	$pages = array(
		'page_diagnostic' => __( 'Diagnostic d’entreprise & Stratégie de croissance', 'antoine-codex-immersive' ),
		'page_services'   => __( 'Services / Expertises', 'antoine-codex-immersive' ),
		'page_method'     => __( 'Méthodologie', 'antoine-codex-immersive' ),
		'page_founder'    => __( 'Fondateur', 'antoine-codex-immersive' ),
		'page_contact'    => __( 'Contact', 'antoine-codex-immersive' ),
	);
	foreach ( $pages as $key => $label ) {
		acx_customizer_field( $wp, 'acx_pages', $key, $label, 'dropdown-pages', array( 'allow_addition' => false ) );
	}
	acx_customizer_field(
		$wp,
		'acx_pages',
		'quick_diag_url',
		__( 'Lien du Quick Diagnostic', 'antoine-codex-immersive' ),
		'url',
		array( 'description' => __( 'URL complète ou ancre (ex. #quick-diagnostic). Vide = détection automatique, sinon section Diagnostic.', 'antoine-codex-immersive' ) )
	);

	// --- Coordonnées.
	$wp->add_section(
		'acx_contact',
		array(
			'title'       => __( 'Coordonnées & réseaux', 'antoine-codex-immersive' ),
			'panel'       => 'acx_panel',
			'description' => __( 'Les champs vides ne sont pas affichés.', 'antoine-codex-immersive' ),
		)
	);
	acx_customizer_field( $wp, 'acx_contact', 'contact_email', __( 'E-mail', 'antoine-codex-immersive' ), 'email' );
	acx_customizer_field( $wp, 'acx_contact', 'contact_phone', __( 'Téléphone', 'antoine-codex-immersive' ) );
	acx_customizer_field( $wp, 'acx_contact', 'contact_address', __( 'Adresse / zone', 'antoine-codex-immersive' ), 'textarea' );
	acx_customizer_field( $wp, 'acx_contact', 'contact_hours', __( 'Disponibilités', 'antoine-codex-immersive' ) );
	foreach ( array( 'linkedin' => 'LinkedIn', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube' ) as $net => $netlabel ) {
		acx_customizer_field( $wp, 'acx_contact', 'social_' . $net, $netlabel, 'url' );
	}
	acx_customizer_field(
		$wp,
		'acx_contact',
		'contact_form_enabled',
		__( 'Afficher le formulaire de contact du thème sur la page Contact', 'antoine-codex-immersive' ),
		'checkbox',
		array( 'description' => __( 'Solution de secours. Laissez décoché si votre page Contact contient déjà un formulaire (Contact Form 7, WPForms, etc.). Également disponible via le code court [acx_contact_form].', 'antoine-codex-immersive' ) )
	);

	// --- Effets.
	$wp->add_section(
		'acx_fx',
		array(
			'title'       => __( 'Effets visuels', 'antoine-codex-immersive' ),
			'panel'       => 'acx_panel',
			'description' => __( 'Les effets sont automatiquement désactivés pour les visiteurs qui ont demandé la réduction des animations, et allégés sur les appareils modestes.', 'antoine-codex-immersive' ),
		)
	);
	acx_customizer_field( $wp, 'acx_fx', 'fx_webgl', __( 'Champ de particules 3D (WebGL)', 'antoine-codex-immersive' ), 'checkbox' );
	acx_customizer_field(
		$wp,
		'acx_fx',
		'fx_density',
		__( 'Densité des particules', 'antoine-codex-immersive' ),
		'select',
		array(
			'choices' => array(
				'auto' => __( 'Automatique (selon l’appareil)', 'antoine-codex-immersive' ),
				'low'  => __( 'Légère', 'antoine-codex-immersive' ),
				'high' => __( 'Élevée', 'antoine-codex-immersive' ),
			),
		)
	);
	acx_customizer_field( $wp, 'acx_fx', 'fx_pointer', __( 'Effets au survol (lumière, inclinaison des cartes)', 'antoine-codex-immersive' ), 'checkbox' );
}
add_action( 'customize_register', 'acx_customize_register' );

/**
 * Enregistre les textes personnalisés auprès de Polylang / WPML pour traduction EN.
 */
function acx_register_translatable_strings() {
	foreach ( acx_translatable_keys() as $key ) {
		$value = get_theme_mod( 'acx_' . $key, '' );
		if ( '' === $value || ! is_string( $value ) ) {
			continue;
		}
		if ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( 'acx_' . $key, $value, 'ANTOINE CODEX Immersive', strlen( $value ) > 80 );
		}
		do_action( 'wpml_register_single_string', 'antoine-codex-immersive', 'acx_' . $key, $value );
	}
}
add_action( 'admin_init', 'acx_register_translatable_strings' );
