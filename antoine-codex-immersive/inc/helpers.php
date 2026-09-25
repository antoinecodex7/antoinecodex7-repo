<?php
/**
 * Fonctions utilitaires : options, pages clés, icônes.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Valeurs par défaut des réglages du Personnaliseur.
 *
 * Les textes passent par __() : si la valeur n'a pas été modifiée, la version
 * anglaise (languages/en_*.mo) s'affiche automatiquement sur les pages EN.
 *
 * @return array
 */
function acx_defaults() {
	static $cache = array();
	$key = is_textdomain_loaded( 'antoine-codex-immersive' ) ? 'en' : 'fr';
	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$defaults = array(
		// Accueil — ouverture.
		'hero_eyebrow'     => __( 'Conseil stratégique & technologique — Nice · Côte d’Azur', 'antoine-codex-immersive' ),
		'hero_title'       => __( 'Comprendre. Décider. Transformer.', 'antoine-codex-immersive' ),
		'hero_lead'        => __( 'Diagnostic d’entreprise, stratégie de croissance, automatisations IA et présence digitale pour les dirigeants de la Côte d’Azur — des PME aux hôtels indépendants.', 'antoine-codex-immersive' ),
		'hero_cta_primary' => __( 'Lancer le diagnostic', 'antoine-codex-immersive' ),
		'hero_cta_second'  => __( 'Découvrir les expertises', 'antoine-codex-immersive' ),
		'hero_cta_second_url' => '',

		// Manifeste.
		'manifesto_label' => __( 'Approche', 'antoine-codex-immersive' ),
		'manifesto_text'  => __( 'Chaque entreprise possède une structure invisible : ses flux, ses décisions, ses signaux faibles. Nous la rendons lisible, puis nous l’aidons à évoluer — avec méthode, technologie et exigence.', 'antoine-codex-immersive' ),

		// Expertises.
		'services_label' => __( 'Expertises', 'antoine-codex-immersive' ),
		'services_title' => __( 'Six leviers, une seule lecture de votre entreprise.', 'antoine-codex-immersive' ),
		'services_lead'  => __( 'Chaque accompagnement part du même principe : comprendre avant d’agir. Les expertises se combinent selon vos priorités.', 'antoine-codex-immersive' ),

		// Diagnostic.
		'diag_label' => __( 'Diagnostic', 'antoine-codex-immersive' ),
		'diag_title' => __( 'Commencer par une lecture juste de votre entreprise.', 'antoine-codex-immersive' ),
		'diag_lead'  => __( 'Trois portes d’entrée, trois niveaux de profondeur. Choisissez celle qui correspond à votre besoin du moment.', 'antoine-codex-immersive' ),

		// Méthode.
		'method_label' => __( 'Méthode', 'antoine-codex-immersive' ),
		'method_title' => __( 'Une méthode en trois temps.', 'antoine-codex-immersive' ),
		'method_1_title' => __( 'Comprendre', 'antoine-codex-immersive' ),
		'method_1_text'  => __( 'Observer l’entreprise telle qu’elle fonctionne réellement : chiffres, processus, clients, équipe, présence en ligne. Écouter avant de conclure.', 'antoine-codex-immersive' ),
		'method_2_title' => __( 'Décider', 'antoine-codex-immersive' ),
		'method_2_text'  => __( 'Hiérarchiser les priorités. Chaque recommandation est reliée à un enjeu concret, avec ses moyens, son calendrier et ses risques.', 'antoine-codex-immersive' ),
		'method_3_title' => __( 'Transformer', 'antoine-codex-immersive' ),
		'method_3_text'  => __( 'Mettre en œuvre, étape par étape : stratégie, automatisations, marketing, site web, image. Puis mesurer, apprendre et ajuster.', 'antoine-codex-immersive' ),

		// Territoire.
		'territory_label' => __( 'Territoire', 'antoine-codex-immersive' ),
		'territory_title' => __( 'Ancré à Nice. Au service de la Côte d’Azur.', 'antoine-codex-immersive' ),
		'territory_text'  => __( 'Une connaissance directe du tissu économique local : saisonnalité, clientèle internationale, concurrence des plateformes, exigence de qualité. Les accompagnements se font sur place ou à distance.', 'antoine-codex-immersive' ),
		'hotel_title'     => __( 'Hôtels indépendants', 'antoine-codex-immersive' ),
		'hotel_text'      => __( 'Visibilité, réservation directe, automatisation des échanges avec les clients, images qui donnent envie de venir : une approche globale pour les établissements qui veulent rester indépendants.', 'antoine-codex-immersive' ),

		// Fondateur.
		'founder_label' => __( 'Fondateur', 'antoine-codex-immersive' ),
		'founder_title' => __( 'Une approche personnelle, à la croisée de la stratégie, de la technologie et de l’image.', 'antoine-codex-immersive' ),
		'founder_text'  => '',
		'founder_cta'   => __( 'Découvrir le fondateur', 'antoine-codex-immersive' ),
		'founder_image' => 0,

		// Appel final.
		'cta_title' => __( 'Commençons par comprendre.', 'antoine-codex-immersive' ),
		'cta_text'  => __( 'Le diagnostic est le point de départ de chaque accompagnement.', 'antoine-codex-immersive' ),

		// Contenu de la page d'accueil statique.
		'front_content_mode' => 'designed',

		// Pages clés (0 = détection automatique).
		'page_diagnostic'  => 0,
		'page_services'    => 0,
		'page_method'      => 0,
		'page_founder'     => 0,
		'page_contact'     => 0,
		'quick_diag_url'   => '',

		// Coordonnées (vides = masquées ; aucune donnée inventée).
		'contact_email'   => '',
		'contact_phone'   => '',
		'contact_address' => '',
		'contact_hours'   => '',
		'social_linkedin'  => '',
		'social_instagram' => '',
		'social_facebook'  => '',
		'social_youtube'   => '',

		// Effets.
		'fx_webgl'   => true,
		'fx_density' => 'auto',
		'fx_pointer' => true,

		// Formulaire de secours.
		'contact_form_enabled' => false,
	);

	$cache[ $key ] = $defaults;
	return $defaults;
}

/**
 * Lit une option du thème.
 *
 * @param string $key Clé sans préfixe.
 * @return mixed
 */
function acx_opt( $key ) {
	$defaults = acx_defaults();
	$default  = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
	$value    = get_theme_mod( 'acx_' . $key, $default );

	// Champ texte vidé dans le Personnaliseur : retour au texte par défaut.
	if ( '' === $value && is_string( $default ) && '' !== $default ) {
		return $default;
	}
	return $value;
}

/**
 * Lit une option texte, traduisible via Polylang / WPML si elle a été personnalisée.
 *
 * @param string $key Clé sans préfixe.
 * @return string
 */
function acx_text( $key ) {
	$value = (string) acx_opt( $key );
	$raw   = get_theme_mod( 'acx_' . $key, null );

	// Valeur personnalisée : traduction par l'extension multilingue.
	if ( null !== $raw && '' !== $raw ) {
		if ( function_exists( 'pll__' ) ) {
			$value = pll__( $value );
		} else {
			$value = apply_filters( 'wpml_translate_single_string', $value, 'antoine-codex-immersive', 'acx_' . $key );
		}
	}

	return (string) apply_filters( 'acx_text', $value, $key );
}

/**
 * Clés texte traduisibles (enregistrées auprès de Polylang / WPML).
 *
 * @return array
 */
function acx_translatable_keys() {
	$keys = array(
		'hero_eyebrow', 'hero_title', 'hero_lead', 'hero_cta_primary', 'hero_cta_second',
		'manifesto_label', 'manifesto_text',
		'services_label', 'services_title', 'services_lead',
		'diag_label', 'diag_title', 'diag_lead',
		'method_label', 'method_title', 'method_1_title', 'method_1_text', 'method_2_title', 'method_2_text', 'method_3_title', 'method_3_text',
		'territory_label', 'territory_title', 'territory_text', 'hotel_title', 'hotel_text',
		'founder_label', 'founder_title', 'founder_text', 'founder_cta',
		'cta_title', 'cta_text', 'contact_address', 'contact_hours',
	);
	foreach ( array_keys( acx_services() ) as $slug ) {
		$keys[] = 'service_' . $slug . '_title';
		$keys[] = 'service_' . $slug . '_text';
		$keys[] = 'service_' . $slug . '_points';
	}
	return $keys;
}

/**
 * Expertises : valeurs par défaut (modifiables dans le Personnaliseur).
 *
 * @return array
 */
function acx_service_defaults() {
	return array(
		'diagnostic' => array(
			'title'  => __( 'Diagnostic d’entreprise & stratégie de croissance', 'antoine-codex-immersive' ),
			'text'   => __( 'Une lecture complète de votre activité : modèle économique, organisation, offre, parcours client et présence digitale. Vous repartez avec des priorités claires et une feuille de route réaliste.', 'antoine-codex-immersive' ),
			'points' => __( "Analyse de l’existant\nPriorités hiérarchisées\nFeuille de route de croissance", 'antoine-codex-immersive' ),
		),
		'ia'         => array(
			'title'  => __( 'Automatisations IA', 'antoine-codex-immersive' ),
			'text'   => __( 'Repérer les tâches répétitives qui freinent votre équipe, puis concevoir des automatisations et des assistants IA utiles, sécurisés et reliés à vos outils.', 'antoine-codex-immersive' ),
			'points' => __( "Cartographie des processus\nAssistants et agents IA\nIntégration à vos outils", 'antoine-codex-immersive' ),
		),
		'marketing'  => array(
			'title'  => __( 'Marketing & SEO', 'antoine-codex-immersive' ),
			'text'   => __( 'Clarifier votre message, être trouvé par les bonnes personnes et construire une acquisition durable, locale comme internationale.', 'antoine-codex-immersive' ),
			'points' => __( "Positionnement et message\nRéférencement naturel et local\nContenus et campagnes", 'antoine-codex-immersive' ),
		),
		'web'        => array(
			'title'  => __( 'Sites web', 'antoine-codex-immersive' ),
			'text'   => __( 'Des sites rapides, élégants et pensés pour convertir : site vitrine, réservation directe, génération de demandes qualifiées.', 'antoine-codex-immersive' ),
			'points' => __( "Conception et développement\nPerformance et accessibilité\nParcours de conversion", 'antoine-codex-immersive' ),
		),
		'image'      => array(
			'title'  => __( 'Photographie, vidéo & drone corporate', 'antoine-codex-immersive' ),
			'text'   => __( 'Des images qui traduisent la qualité réelle de votre entreprise : équipes, lieux, savoir-faire et paysages de la Riviera, vus du sol comme du ciel.', 'antoine-codex-immersive' ),
			'points' => __( "Photographie corporate\nVidéo de marque\nPrises de vue par drone", 'antoine-codex-immersive' ),
		),
		'mentorat'   => array(
			'title'  => __( 'Mentorat d’entrepreneurs', 'antoine-codex-immersive' ),
			'text'   => __( 'Un accompagnement individuel pour prendre du recul, structurer vos décisions et avancer avec méthode, du lancement au développement.', 'antoine-codex-immersive' ),
			'points' => __( "Séances individuelles\nStructuration des décisions\nSuivi dans la durée", 'antoine-codex-immersive' ),
		),
	);
}

/**
 * Expertises résolues (réglages + valeurs par défaut).
 *
 * @return array
 */
function acx_services() {
	$out = array();
	$i   = 0;
	foreach ( acx_service_defaults() as $slug => $d ) {
		++$i;
		$title  = get_theme_mod( 'acx_service_' . $slug . '_title', '' );
		$text   = get_theme_mod( 'acx_service_' . $slug . '_text', '' );
		$points = get_theme_mod( 'acx_service_' . $slug . '_points', '' );
		$url    = get_theme_mod( 'acx_service_' . $slug . '_url', '' );

		$translate = function ( $value, $key ) {
			if ( function_exists( 'pll__' ) ) {
				return pll__( $value );
			}
			return apply_filters( 'wpml_translate_single_string', $value, 'antoine-codex-immersive', 'acx_' . $key );
		};

		$title  = '' !== $title ? $translate( $title, 'service_' . $slug . '_title' ) : $d['title'];
		$text   = '' !== $text ? $translate( $text, 'service_' . $slug . '_text' ) : $d['text'];
		$points = '' !== $points ? $translate( $points, 'service_' . $slug . '_points' ) : $d['points'];

		if ( '' === $url ) {
			$url = 'diagnostic' === $slug ? acx_page_url( 'diagnostic' ) : acx_page_url( 'services', '#service-' . $slug );
		}

		$out[ $slug ] = array(
			'index'  => str_pad( (string) $i, 2, '0', STR_PAD_LEFT ),
			'title'  => $title,
			'text'   => $text,
			'points' => array_filter( array_map( 'trim', explode( "\n", (string) $points ) ) ),
			'url'    => $url,
		);
	}
	return apply_filters( 'acx_services', $out );
}

/**
 * Candidats pour la détection automatique des pages clés.
 *
 * @return array
 */
function acx_page_roles() {
	return array(
		'diagnostic' => array(
			'slugs' => array( 'diagnostic-dentreprise-strategie-de-croissance', 'diagnostic-entreprise-strategie-de-croissance', 'diagnostic-dentreprise-et-strategie-de-croissance', 'diagnostic-entreprise-strategie-croissance', 'diagnostic-dentreprise', 'diagnostic-entreprise', 'diagnostic', 'business-diagnostic' ),
			'title' => 'Diagnostic d',
		),
		'services'   => array(
			'slugs' => array( 'services', 'nos-services', 'expertises', 'nos-expertises', 'prestations', 'offres' ),
			'title' => 'Services',
		),
		'method'     => array(
			'slugs' => array( 'methodologie', 'methode', 'notre-methode', 'notre-methodologie', 'approche', 'methodology' ),
			'title' => 'Méthodologie',
		),
		'founder'    => array(
			'slugs' => array( 'fondateur', 'le-fondateur', 'a-propos', 'qui-suis-je', 'qui-sommes-nous', 'about', 'antoine' ),
			'title' => 'Fondateur',
		),
		'contact'    => array(
			'slugs' => array( 'contact', 'contactez-nous', 'nous-contacter', 'rendez-vous' ),
			'title' => 'Contact',
		),
		'quick'      => array(
			'slugs' => array( 'quick-diagnostic', 'diagnostic-rapide', 'diagnostic-express', 'quick-diag' ),
			'title' => 'Quick Diagnostic',
		),
	);
}

/**
 * Retourne l'ID de la page associée à un rôle (réglage manuel ou détection).
 *
 * @param string $role Rôle : diagnostic, services, method, founder, contact, quick.
 * @return int
 */
function acx_page_id( $role ) {
	static $cache = array();
	if ( isset( $cache[ $role ] ) ) {
		return $cache[ $role ];
	}

	$map = array(
		'diagnostic' => 'page_diagnostic',
		'services'   => 'page_services',
		'method'     => 'page_method',
		'founder'    => 'page_founder',
		'contact'    => 'page_contact',
	);

	$id = isset( $map[ $role ] ) ? absint( acx_opt( $map[ $role ] ) ) : 0;

	if ( ! $id || 'publish' !== get_post_status( $id ) ) {
		$id = acx_detect_page( $role );
	}

	// Page traduite dans la langue courante (Polylang / WPML).
	if ( $id && function_exists( 'pll_get_post' ) ) {
		$translated = pll_get_post( $id );
		if ( $translated ) {
			$id = (int) $translated;
		}
	} elseif ( $id ) {
		$id = (int) apply_filters( 'wpml_object_id', $id, 'page', true );
	}

	$cache[ $role ] = (int) $id;
	return $cache[ $role ];
}

/**
 * Détection automatique d'une page par slug puis par titre. Résultat mis en cache.
 *
 * @param string $role Rôle.
 * @return int
 */
function acx_detect_page( $role ) {
	$roles = acx_page_roles();
	if ( ! isset( $roles[ $role ] ) ) {
		return 0;
	}

	$cached = get_transient( 'acx_page_role_' . $role );
	if ( false !== $cached ) {
		return (int) $cached;
	}

	$found = 0;
	foreach ( $roles[ $role ]['slugs'] as $slug ) {
		$page = get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page && 'publish' === $page->post_status ) {
			$found = (int) $page->ID;
			break;
		}
	}

	if ( ! $found && ! empty( $roles[ $role ]['title'] ) ) {
		$q = new WP_Query(
			array(
				'post_type'              => 'page',
				'post_status'            => 'publish',
				'posts_per_page'         => 1,
				's'                      => $roles[ $role ]['title'],
				'search_columns'         => array( 'post_title' ),
				'orderby'                => 'menu_order date',
				'order'                  => 'ASC',
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'suppress_filters'       => false,
			)
		);
		if ( $q->posts ) {
			$found = (int) $q->posts[0];
		}
	}

	set_transient( 'acx_page_role_' . $role, $found, DAY_IN_SECONDS );
	return $found;
}

/**
 * Invalide le cache de détection quand une page change.
 */
function acx_flush_page_roles() {
	foreach ( array_keys( acx_page_roles() ) as $role ) {
		delete_transient( 'acx_page_role_' . $role );
	}
}
add_action( 'save_post_page', 'acx_flush_page_roles' );
add_action( 'deleted_post', 'acx_flush_page_roles' );
add_action( 'after_switch_theme', 'acx_flush_page_roles' );
add_action( 'customize_save_after', 'acx_flush_page_roles' );

/**
 * URL d'une page clé, avec repli sur une ancre de la page d'accueil.
 *
 * @param string $role     Rôle.
 * @param string $fragment Fragment optionnel (ex. « #ia »).
 * @return string
 */
function acx_page_url( $role, $fragment = '' ) {
	if ( 'quick' === $role ) {
		$custom = trim( (string) acx_opt( 'quick_diag_url' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
	}

	$id = acx_page_id( $role );
	if ( $id ) {
		return get_permalink( $id ) . $fragment;
	}

	// Quick Diagnostic sans page dédiée : ancre sur la page Diagnostic.
	if ( 'quick' === $role && acx_page_id( 'diagnostic' ) ) {
		return get_permalink( acx_page_id( 'diagnostic' ) ) . '#quick-diagnostic';
	}

	$fallback = array(
		'diagnostic' => '#diagnostic',
		'services'   => '#expertises',
		'method'     => '#methode',
		'founder'    => '#fondateur',
		'contact'    => '#contact',
		'quick'      => '#diagnostic',
	);
	$anchor = isset( $fallback[ $role ] ) ? $fallback[ $role ] : '';
	if ( $fragment ) {
		$anchor = $fragment;
	}
	return home_url( '/' ) . $anchor;
}

/**
 * Icônes SVG inline (décoratives).
 *
 * @param string $name Nom.
 * @return string
 */
function acx_icon( $name ) {
	$icons = array(
		'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6" />',
		'arrow-ne'=> '<path d="M7 17 17 7M8 7h9v9" />',
		'close'   => '<path d="M6 6l12 12M18 6 6 18" />',
		'menu'    => '<path d="M4 8h16M4 16h16" />',
		'send'    => '<path d="M4 12 20 4l-6 16-3-7-7-1Z" />',
		'reset'   => '<path d="M4 4v6h6" /><path d="M5.5 15a7 7 0 1 0 1.6-7.3L4 10" />',
		'spark'   => '<path d="M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6" />',
		'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2" /><path d="m3 7 9 6 9-6" />',
		'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2" />',
		'pin'     => '<path d="M12 21s-7-6.2-7-12a7 7 0 0 1 14 0c0 5.8-7 12-7 12Z" /><circle cx="12" cy="9" r="2.5" />',
		'clock'   => '<circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" />',
	);
	if ( ! isset( $icons[ $name ] ) ) {
		return '';
	}
	return '<svg class="acx-icon acx-icon--' . esc_attr( $name ) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $icons[ $name ] . '</svg>';
}

/**
 * Autorisation SVG pour wp_kses.
 *
 * @return array
 */
function acx_kses_svg() {
	return array(
		'svg'    => array( 'class' => true, 'viewbox' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'focusable' => true ),
		'path'   => array( 'd' => true ),
		'rect'   => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ),
		'circle' => array( 'cx' => true, 'cy' => true, 'r' => true ),
	);
}

/**
 * Affiche une icône.
 *
 * @param string $name Nom.
 */
function acx_the_icon( $name ) {
	echo wp_kses( acx_icon( $name ), acx_kses_svg() );
}
