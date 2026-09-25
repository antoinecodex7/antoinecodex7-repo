<?php
/**
 * Sélecteur de langue FR/EN : Polylang, WPML ou TranslatePress (si actifs).
 * Sans extension multilingue, aucun sélecteur n'est affiché.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Langue courante du site public (code à 2 lettres) si une extension multilingue est active.
 *
 * @return string Code (« fr », « en »…) ou chaîne vide.
 */
function acx_current_lang() {
	if ( function_exists( 'pll_current_language' ) ) {
		$lang = pll_current_language( 'slug' );
		return $lang ? (string) $lang : '';
	}
	$wpml = apply_filters( 'wpml_current_language', null );
	if ( is_string( $wpml ) && '' !== $wpml ) {
		return $wpml;
	}
	return '';
}

/**
 * Traductions du thème.
 *
 * Les textes sources du thème sont en français. La traduction anglaise n'est chargée que :
 *  - sur les pages publiques en anglais d'une extension multilingue (Polylang / WPML) ;
 *  - dans l'administration, si la langue de l'utilisateur est l'anglais.
 * Ainsi, un site français dont la langue WordPress serait réglée sur « English » reste en français.
 */
function acx_load_translations() {
	static $loaded = null;

	if ( is_admin() && ! wp_doing_ajax() ) {
		$locale = get_user_locale();
		$want   = 0 === strpos( $locale, 'en' ) ? 'en' : '';
	} else {
		$want = 0 === strpos( acx_current_lang(), 'en' ) ? 'en' : '';
	}
	$want = (string) apply_filters( 'acx_translation_lang', $want );

	if ( $loaded === $want ) {
		return;
	}
	$loaded = $want;

	unload_textdomain( 'antoine-codex-immersive', true );
	if ( 'en' === $want ) {
		$GLOBALS['acx_loading_translation'] = true;
		// Enregistré sous la langue courante (ex. en_GB avec Polylang) : c'est elle que WordPress interroge.
		load_textdomain( 'antoine-codex-immersive', ACX_DIR . '/languages/en_US.mo', determine_locale() );
		$GLOBALS['acx_loading_translation'] = false;
	}
}
add_action( 'after_setup_theme', 'acx_load_translations', 1 );
add_action( 'pll_language_defined', 'acx_load_translations' );
add_action( 'wpml_language_has_switched', 'acx_load_translations' );
add_action( 'wp', 'acx_load_translations', 1 );

/**
 * Empêche WordPress de charger automatiquement une traduction de ce thème selon la
 * langue du site : c'est acx_load_translations() qui décide.
 *
 * @param bool   $override Valeur.
 * @param string $domain   Domaine.
 * @param string $mofile   Fichier.
 * @return bool
 */
function acx_block_auto_translation( $override, $domain, $mofile ) {
	if ( 'antoine-codex-immersive' === $domain && empty( $GLOBALS['acx_loading_translation'] ) ) {
		return true; // Chargement automatique (langue du site) ignoré : le français reste la langue source.
	}
	return $override;
}
add_filter( 'override_load_textdomain', 'acx_block_auto_translation', 10, 3 );

/**
 * Langues disponibles, normalisées.
 *
 * @return array Liste de [ code, name, url, current ].
 */
function acx_languages() {
	$langs = array();

	if ( function_exists( 'pll_the_languages' ) ) {
		$raw = pll_the_languages(
			array(
				'raw'           => 1,
				'hide_if_empty' => 0,
			)
		);
		if ( is_array( $raw ) ) {
			foreach ( $raw as $l ) {
				$langs[] = array(
					'code'    => isset( $l['slug'] ) ? $l['slug'] : '',
					'name'    => isset( $l['name'] ) ? $l['name'] : '',
					'url'     => isset( $l['url'] ) ? $l['url'] : '',
					'current' => ! empty( $l['current_lang'] ),
					'missing' => ! empty( $l['no_translation'] ),
				);
			}
		}
	} elseif ( has_filter( 'wpml_active_languages' ) ) {
		$raw = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) );
		if ( is_array( $raw ) ) {
			foreach ( $raw as $l ) {
				$langs[] = array(
					'code'    => isset( $l['code'] ) ? $l['code'] : '',
					'name'    => isset( $l['native_name'] ) ? $l['native_name'] : '',
					'url'     => isset( $l['url'] ) ? $l['url'] : '',
					'current' => ! empty( $l['active'] ),
					'missing' => isset( $l['missing'] ) && $l['missing'],
				);
			}
		}
	} elseif ( function_exists( 'trp_custom_language_switcher' ) ) {
		$raw = trp_custom_language_switcher();
		if ( is_array( $raw ) ) {
			$current = get_locale();
			foreach ( $raw as $l ) {
				$langs[] = array(
					'code'    => isset( $l['short_language_name'] ) ? $l['short_language_name'] : '',
					'name'    => isset( $l['language_name'] ) ? $l['language_name'] : '',
					'url'     => isset( $l['current_page_url'] ) ? $l['current_page_url'] : '',
					'current' => isset( $l['language_code'] ) && $l['language_code'] === $current,
					'missing' => false,
				);
			}
		}
	}

	return apply_filters( 'acx_languages', $langs );
}

/**
 * Affiche le sélecteur de langue.
 *
 * @param string $class Classe supplémentaire.
 */
function acx_language_switcher( $class = '' ) {
	$langs = acx_languages();
	if ( count( $langs ) < 2 ) {
		return;
	}
	echo '<nav class="lang-switch mono ' . esc_attr( $class ) . '" aria-label="' . esc_attr__( 'Choix de la langue', 'antoine-codex-immersive' ) . '"><ul>';
	foreach ( $langs as $l ) {
		$code = strtoupper( substr( (string) $l['code'], 0, 2 ) );
		if ( $l['current'] ) {
			printf( '<li><span class="is-current" aria-current="true" title="%1$s">%2$s</span></li>', esc_attr( $l['name'] ), esc_html( $code ) );
		} else {
			printf(
				'<li><a href="%1$s" hreflang="%2$s" lang="%2$s" title="%3$s">%4$s</a></li>',
				esc_url( $l['url'] ),
				esc_attr( $l['code'] ),
				esc_attr( $l['name'] ),
				esc_html( $code )
			);
		}
	}
	echo '</ul></nav>';
}
