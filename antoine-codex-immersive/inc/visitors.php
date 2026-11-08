<?php
/**
 * Compteur de visiteurs par pays — mesure d'audience anonyme, intégrée au thème.
 *
 * Principe (conforme RGPD, sans cookie) :
 *  - à chaque page vue, le navigateur envoie un signal à /wp-json/antoine-codex/v1/visit ;
 *    cela fonctionne même si les pages sont mises en cache ;
 *  - le pays est déduit de l'adresse IP (en-tête du CDN/hébergeur si présent, sinon base locale
 *    data/geoip, aucun service tiers) ;
 *  - l'adresse IP n'est JAMAIS enregistrée : seule une empreinte hachée avec un sel qui change
 *    chaque jour sert à compter une seule fois le même visiteur dans la journée, et ces empreintes
 *    sont effacées au bout de 2 jours ;
 *  - seuls des totaux par jour et par pays sont conservés.
 *
 * « Visiteurs » = visiteurs uniques par jour, additionnés (un visiteur revenant un autre jour
 * est compté à nouveau, faute de cookie). « Pages vues » = toutes les pages chargées.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

define( 'ACX_VISITS_DB_VERSION', '1' );

/* --------------------------------------------------------------------------
 * Tables
 * ------------------------------------------------------------------------ */

/**
 * Noms des tables.
 *
 * @return array
 */
function acx_visits_tables() {
	global $wpdb;
	return array(
		'stats' => $wpdb->prefix . 'acx_visit_stats',
		'seen'  => $wpdb->prefix . 'acx_visit_seen',
	);
}

/**
 * Crée ou met à jour les tables.
 */
function acx_visits_install() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t       = acx_visits_tables();
	$charset = $wpdb->get_charset_collate();
	dbDelta(
		"CREATE TABLE {$t['stats']} (
			day date NOT NULL,
			country char(2) NOT NULL,
			visitors int(10) unsigned NOT NULL DEFAULT 0,
			views int(10) unsigned NOT NULL DEFAULT 0,
			PRIMARY KEY  (day,country)
		) $charset;"
	);
	dbDelta(
		"CREATE TABLE {$t['seen']} (
			day date NOT NULL,
			hash char(32) NOT NULL,
			PRIMARY KEY  (day,hash)
		) $charset;"
	);
	update_option( 'acx_visits_db_version', ACX_VISITS_DB_VERSION, false );
}

/**
 * Installe les tables si nécessaire (activation du thème ou mise à jour).
 */
function acx_visits_maybe_install() {
	if ( get_option( 'acx_visits_db_version' ) !== ACX_VISITS_DB_VERSION ) {
		acx_visits_install();
	}
}
add_action( 'after_switch_theme', 'acx_visits_install' );
add_action( 'admin_init', 'acx_visits_maybe_install' );

/* --------------------------------------------------------------------------
 * Réglages (Personnaliseur → ANTOINE CODEX — Thème → Compteur de visiteurs)
 * ------------------------------------------------------------------------ */

/**
 * Lit un réglage du compteur.
 *
 * @param string $key Clé.
 * @return mixed
 */
function acx_visits_opt( $key ) {
	$defaults = array(
		'visits_enabled'      => true,  // Compter les visites.
		'visits_public'       => true,  // Afficher le compteur aux visiteurs.
		'visits_count_admins' => false, // Compter les utilisateurs connectés (éditeurs, administrateurs).
		'visits_top'          => 10,    // Nombre de pays affichés publiquement.
	);
	return get_theme_mod( 'acx_' . $key, $defaults[ $key ] );
}

/**
 * Contrôles du Personnaliseur.
 *
 * @param WP_Customize_Manager $wp Gestionnaire.
 */
function acx_visits_customize( $wp ) {
	$wp->add_section(
		'acx_visits',
		array(
			'title'       => __( 'Compteur de visiteurs', 'antoine-codex-immersive' ),
			'panel'       => 'acx_panel',
			'description' => __( 'Mesure anonyme sans cookie : seuls des totaux par jour et par pays sont conservés, jamais les adresses IP.', 'antoine-codex-immersive' ),
		)
	);
	$fields = array(
		'visits_enabled'      => array( __( 'Compter les visites', 'antoine-codex-immersive' ), true ),
		'visits_public'       => array( __( 'Afficher le compteur et la carte des pays en bas du site', 'antoine-codex-immersive' ), true ),
		'visits_count_admins' => array( __( 'Compter aussi les utilisateurs connectés (administrateurs, éditeurs)', 'antoine-codex-immersive' ), false ),
	);
	foreach ( $fields as $key => $f ) {
		$wp->add_setting(
			'acx_' . $key,
			array(
				'default'           => $f[1],
				'sanitize_callback' => 'acx_sanitize_checkbox',
			)
		);
		$wp->add_control(
			'acx_' . $key,
			array(
				'label'   => $f[0],
				'section' => 'acx_visits',
				'type'    => 'checkbox',
			)
		);
	}
	$wp->add_setting(
		'acx_visits_top',
		array(
			'default'           => 10,
			'sanitize_callback' => 'absint',
		)
	);
	$wp->add_control(
		'acx_visits_top',
		array(
			'label'       => __( 'Nombre de pays affichés', 'antoine-codex-immersive' ),
			'section'     => 'acx_visits',
			'type'        => 'number',
			'input_attrs' => array(
				'min' => 3,
				'max' => 30,
			),
		)
	);
}
add_action( 'customize_register', 'acx_visits_customize', 20 );

/* --------------------------------------------------------------------------
 * Géolocalisation IP → pays
 * ------------------------------------------------------------------------ */

/**
 * Recherche dichotomique dans une table binaire triée (début de bloc + code pays).
 *
 * @param string $file    Fichier.
 * @param int    $key     Clé (début de bloc).
 * @param int    $keysize Taille de la clé en octets (3 ou 4).
 * @return string Code pays ou chaîne vide.
 */
function acx_geo_search( $file, $key, $keysize ) {
	$rec  = $keysize + 2;
	$size = @filesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( ! $size || $size % $rec ) {
		return '';
	}
	$fh = @fopen( $file, 'rb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $fh ) {
		return '';
	}
	$lo  = 0;
	$hi  = (int) ( $size / $rec ) - 1;
	$hit = -1;
	while ( $lo <= $hi ) {
		$mid = ( $lo + $hi ) >> 1;
		fseek( $fh, $mid * $rec );
		$raw   = fread( $fh, $rec ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$start = unpack( 'N', str_pad( substr( $raw, 0, $keysize ), 4, "\0", STR_PAD_LEFT ) );
		if ( $start[1] <= $key ) {
			$hit = $mid;
			$lo  = $mid + 1;
		} else {
			$hi = $mid - 1;
		}
	}
	$cc = '';
	if ( $hit >= 0 ) {
		fseek( $fh, $hit * $rec + $keysize );
		$cc = (string) fread( $fh, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
	}
	fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
	return preg_match( '/^[A-Z]{2}$/', $cc ) ? $cc : '';
}

/**
 * Pays d'une adresse IP selon la base locale.
 *
 * @param string $ip Adresse IP.
 * @return string Code pays ISO 3166-1 alpha-2, ou chaîne vide si inconnu / privé.
 */
function acx_geo_country_from_ip( $ip ) {
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return '';
	}
	$dir = ACX_DIR . '/data/geoip/';
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return acx_geo_search( $dir . 'ipv4.bin', ip2long( $ip ) >> 8, 3 );
	}
	$bin = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	if ( false === $bin || 16 !== strlen( $bin ) ) {
		return '';
	}
	$key = unpack( 'N', substr( $bin, 0, 4 ) );
	return acx_geo_search( $dir . 'ipv6.bin', $key[1], 4 );
}

/**
 * Pays du visiteur courant : en-tête du CDN/hébergeur s'il existe, sinon base locale.
 *
 * @return string Code pays ou « -- » si inconnu.
 */
function acx_visitor_country() {
	$headers = array( 'HTTP_CF_IPCOUNTRY', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_COUNTRY_CODE', 'HTTP_X_APPENGINE_COUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_GEO_COUNTRY' );
	$cc      = '';
	foreach ( $headers as $h ) {
		if ( ! empty( $_SERVER[ $h ] ) ) {
			$v = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $h ] ) ) );
			if ( preg_match( '/^[A-Z]{2}$/', $v ) && ! in_array( $v, array( 'XX', 'T1', 'ZZ' ), true ) ) {
				$cc = $v;
				break;
			}
		}
	}
	if ( '' === $cc ) {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = (string) apply_filters( 'acx_client_ip', $ip );
		$cc = acx_geo_country_from_ip( $ip );
	}
	$cc = (string) apply_filters( 'acx_visitor_country', $cc );
	return preg_match( '/^[A-Z]{2}$/', $cc ) ? $cc : '--';
}

/* --------------------------------------------------------------------------
 * Enregistrement d'une visite
 * ------------------------------------------------------------------------ */

/**
 * Robots et aperçus à ne pas compter.
 *
 * @param string $ua User-Agent.
 * @return bool
 */
function acx_is_bot( $ua ) {
	if ( '' === trim( $ua ) ) {
		return true;
	}
	return (bool) preg_match( '/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|embedly|quora link|whatsapp|telegram|skype|discord|preview|headless|lighthouse|pagespeed|gtmetrix|pingdom|uptime|monitor|curl|wget|python|java\/|go-http|axios|node-fetch|phantom|scrapy|semrush|ahrefs|mj12|dotbot|petal|yandex|baidu|bytespider|gptbot|claude|perplexity|ccbot/i', $ua );
}

/**
 * Point d'entrée : enregistre une page vue (et un visiteur unique du jour).
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response
 */
function acx_rest_visit( WP_REST_Request $request ) {
	$response = function ( $counted ) {
		$r = rest_ensure_response(
			array(
				'counted' => $counted,
				'stats'   => acx_visits_opt( 'visits_public' ) ? acx_visits_public_stats() : null,
			)
		);
		$r->header( 'Cache-Control', 'no-store' );
		return $r;
	};

	if ( ! acx_visits_opt( 'visits_enabled' ) ) {
		return $response( false );
	}
	if ( ! acx_core_same_origin( $request ) ) {
		return $response( false );
	}
	$ua = (string) $request->get_header( 'user_agent' );
	if ( acx_is_bot( $ua ) || 'prefetch' === strtolower( (string) $request->get_header( 'purpose' ) ) || 'prefetch' === strtolower( (string) $request->get_header( 'sec_purpose' ) ) ) {
		return $response( false );
	}
	if ( ! acx_visits_opt( 'visits_count_admins' ) && current_user_can( 'edit_posts' ) ) {
		return $response( false );
	}
	// Limite anti-gonflage : 60 pages vues par minute et par visiteur.
	if ( ! acx_rate_limit( 'visit', 60, MINUTE_IN_SECONDS ) ) {
		return $response( false );
	}

	global $wpdb;
	acx_visits_maybe_install();
	$t       = acx_visits_tables();
	$day     = current_time( 'Y-m-d' );
	$country = acx_visitor_country();

	// Empreinte du jour : sel secret + date, jamais réversible ; l'IP n'est pas stockée.
	$ip   = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip   = (string) apply_filters( 'acx_client_ip', $ip );
	$hash = hash_hmac( 'md5', $ip . '|' . $ua, wp_salt( 'auth' ) . '|acx-visits|' . $day );

	// phpcs:disable WordPress.DB.DirectDatabaseQuery
	$new = (int) $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$t['seen']} (day, hash) VALUES (%s, %s)", $day, $hash ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query(
		$wpdb->prepare(
			"INSERT INTO {$t['stats']} (day, country, visitors, views) VALUES (%s, %s, %d, 1) ON DUPLICATE KEY UPDATE visitors = visitors + %d, views = views + 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$day,
			$country,
			$new > 0 ? 1 : 0,
			$new > 0 ? 1 : 0
		)
	);
	// phpcs:enable

	acx_visits_flush_cache();
	return $response( true );
}

/**
 * Routes REST.
 */
function acx_visits_routes() {
	register_rest_route(
		'antoine-codex/v1',
		'/visit',
		array(
			'methods'             => 'POST',
			'callback'            => 'acx_rest_visit',
			'permission_callback' => '__return_true', // Public : contrôle d'origine, filtre robots, limite de débit.
		)
	);
	register_rest_route(
		'antoine-codex/v1',
		'/visitors',
		array(
			'methods'             => 'GET',
			'callback'            => function () {
				if ( ! acx_visits_opt( 'visits_public' ) && ! current_user_can( 'manage_options' ) ) {
					return new WP_Error( 'acx_visits_private', __( 'Statistiques non publiques.', 'antoine-codex-immersive' ), array( 'status' => 403 ) );
				}
				return rest_ensure_response( acx_visits_public_stats() );
			},
			'permission_callback' => '__return_true',
		)
	);
}
add_action( 'rest_api_init', 'acx_visits_routes' );

/**
 * Nettoyage quotidien des empreintes (RGPD : conservées 2 jours au plus).
 */
function acx_visits_cleanup() {
	global $wpdb;
	$t = acx_visits_tables();
	if ( get_option( 'acx_visits_db_version' ) ) {
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$t['seen']} WHERE day < %s", gmdate( 'Y-m-d', strtotime( current_time( 'Y-m-d' ) . ' -1 day' ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}
}
add_action( 'acx_visits_daily', 'acx_visits_cleanup' );

/**
 * Planifie le nettoyage (et le relance au besoin, sans dépendre du cron si celui-ci est désactivé).
 */
function acx_visits_schedule() {
	if ( ! wp_next_scheduled( 'acx_visits_daily' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'acx_visits_daily' );
	}
	$last = get_option( 'acx_visits_last_cleanup' );
	if ( current_time( 'Y-m-d' ) !== $last ) {
		update_option( 'acx_visits_last_cleanup', current_time( 'Y-m-d' ), false );
		acx_visits_cleanup();
	}
}
add_action( 'admin_init', 'acx_visits_schedule' );

/**
 * Désactive la tâche planifiée si le thème est abandonné (les données restent).
 */
function acx_visits_unschedule() {
	wp_clear_scheduled_hook( 'acx_visits_daily' );
}
add_action( 'switch_theme', 'acx_visits_unschedule' );

/* --------------------------------------------------------------------------
 * Lecture des statistiques
 * ------------------------------------------------------------------------ */

/**
 * Vide le cache des statistiques.
 */
function acx_visits_flush_cache() {
	delete_transient( 'acx_visits_public' );
}

/**
 * Nom d'un pays dans la langue courante.
 *
 * @param string $cc Code.
 * @return string
 */
function acx_country_name( $cc ) {
	static $names = null;
	if ( null === $names ) {
		$names = include ACX_DIR . '/inc/countries-data.php';
	}
	if ( '--' === $cc || ! isset( $names[ $cc ] ) ) {
		return __( 'Pays inconnu', 'antoine-codex-immersive' );
	}
	return 0 === strpos( acx_current_lang(), 'en' ) || ( is_admin() && 0 === strpos( get_user_locale(), 'en' ) ) ? $names[ $cc ][1] : $names[ $cc ][0];
}

/**
 * URL du drapeau d'un pays (ou chaîne vide).
 *
 * @param string $cc Code.
 * @return string
 */
function acx_flag_url( $cc ) {
	if ( preg_match( '/^[A-Z]{2}$/', $cc ) && file_exists( ACX_DIR . '/assets/flags/' . $cc . '.svg' ) ) {
		return ACX_URI . '/assets/flags/' . $cc . '.svg';
	}
	return '';
}

/**
 * Statistiques agrégées.
 *
 * @param int $days 0 = depuis le début ; sinon les N derniers jours (aujourd'hui compris).
 * @return array total_visitors, total_views, today_visitors, countries[ [code, name, flag, visitors, views, share] ], days[ day => visitors ].
 */
function acx_visits_stats( $days = 0 ) {
	global $wpdb;
	$t     = acx_visits_tables();
	$today = current_time( 'Y-m-d' );
	$empty = array(
		'total_visitors' => 0,
		'total_views'    => 0,
		'today_visitors' => 0,
		'countries'      => array(),
		'days'           => array(),
		'since'          => '',
	);
	if ( ! get_option( 'acx_visits_db_version' ) ) {
		return $empty;
	}
	$where = '';
	if ( $days > 0 ) {
		$from  = gmdate( 'Y-m-d', strtotime( $today . ' -' . ( (int) $days - 1 ) . ' days' ) );
		$where = $wpdb->prepare( 'WHERE day >= %s', $from );
	}
	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows  = $wpdb->get_results( "SELECT country, SUM(visitors) AS v, SUM(views) AS p FROM {$t['stats']} $where GROUP BY country ORDER BY v DESC, p DESC", ARRAY_A );
	$daily = $wpdb->get_results( "SELECT day, SUM(visitors) AS v FROM {$t['stats']} $where GROUP BY day ORDER BY day ASC", ARRAY_A );
	$since = $wpdb->get_var( "SELECT MIN(day) FROM {$t['stats']}" );
	$tv    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT SUM(visitors) FROM {$t['stats']} WHERE day = %s", $today ) );
	// phpcs:enable

	$out          = $empty;
	$out['since'] = (string) $since;
	foreach ( (array) $rows as $r ) {
		$out['total_visitors'] += (int) $r['v'];
		$out['total_views']    += (int) $r['p'];
	}
	foreach ( (array) $rows as $r ) {
		$out['countries'][] = array(
			'code'     => $r['country'],
			'name'     => acx_country_name( $r['country'] ),
			'flag'     => acx_flag_url( $r['country'] ),
			'visitors' => (int) $r['v'],
			'views'    => (int) $r['p'],
			'share'    => $out['total_visitors'] ? round( 100 * (int) $r['v'] / $out['total_visitors'], 1 ) : 0,
		);
	}
	foreach ( (array) $daily as $d ) {
		$out['days'][ $d['day'] ] = (int) $d['v'];
	}
	$out['today_visitors'] = $tv;
	return $out;
}

/**
 * Statistiques publiques (mises en cache 60 s) : totaux + pays principaux, le reste regroupé.
 *
 * @return array
 */
function acx_visits_public_stats() {
	$key    = 'acx_visits_public';
	$cached = get_transient( $key );
	if ( is_array( $cached ) && isset( $cached['lang'] ) && $cached['lang'] === acx_current_lang() ) {
		return $cached['data'];
	}
	$s   = acx_visits_stats( 0 );
	$top = max( 3, min( 30, (int) acx_visits_opt( 'visits_top' ) ) );
	$all = $s['countries'];
	$out = array(
		'total_visitors' => $s['total_visitors'],
		'total_views'    => $s['total_views'],
		'today_visitors' => $s['today_visitors'],
		'country_count'  => count( array_filter( wp_list_pluck( $all, 'code' ), function ( $c ) {
			return '--' !== $c;
		} ) ),
		'countries'      => array_slice( $all, 0, $top ),
		'other'          => 0,
	);
	foreach ( array_slice( $all, $top ) as $c ) {
		$out['other'] += $c['visitors'];
	}
	set_transient(
		$key,
		array(
			'lang' => acx_current_lang(),
			'data' => $out,
		),
		MINUTE_IN_SECONDS
	);
	return $out;
}

/* --------------------------------------------------------------------------
 * Affichage
 * ------------------------------------------------------------------------ */

/**
 * Graphique horizontal des pays (une seule série : une couleur, étiquettes directes).
 *
 * @param array  $countries Lignes (code, name, flag, visitors, share).
 * @param int    $other     Visiteurs regroupés dans « Autres pays ».
 * @param string $context   public | admin.
 */
function acx_visits_chart( $countries, $other = 0, $context = 'public' ) {
	$max = 0;
	foreach ( $countries as $c ) {
		$max = max( $max, (int) $c['visitors'] );
	}
	$max = max( $max, (int) $other, 1 );
	?>
	<ol class="acx-geo-chart acx-geo-chart--<?php echo esc_attr( $context ); ?>" data-geo-chart>
		<?php foreach ( $countries as $c ) : ?>
			<?php
			$pct = round( 100 * (int) $c['visitors'] / $max, 2 );
			/* translators: 1: country, 2: visitors, 3: share. */
			$tip = sprintf( __( '%1$s : %2$s visiteurs (%3$s %%)', 'antoine-codex-immersive' ), $c['name'], number_format_i18n( $c['visitors'] ), number_format_i18n( $c['share'], 1 ) );
			?>
			<li class="acx-geo-chart__row" data-code="<?php echo esc_attr( $c['code'] ); ?>" tabindex="0" aria-label="<?php echo esc_attr( $tip ); ?>">
				<span class="acx-geo-chart__label">
					<?php if ( $c['flag'] ) : ?>
						<img class="acx-geo-chart__flag" src="<?php echo esc_url( $c['flag'] ); ?>" width="24" height="16" alt="" loading="lazy" decoding="async">
					<?php else : ?>
						<span class="acx-geo-chart__flag acx-geo-chart__flag--none" aria-hidden="true"></span>
					<?php endif; ?>
					<span class="acx-geo-chart__name"><?php echo esc_html( $c['name'] ); ?></span>
				</span>
				<span class="acx-geo-chart__track" aria-hidden="true"><span class="acx-geo-chart__bar" style="--w:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
				<span class="acx-geo-chart__value"><?php echo esc_html( number_format_i18n( $c['visitors'] ) ); ?></span>
				<span class="acx-geo-chart__tip" role="tooltip"><?php echo esc_html( $tip ); ?></span>
			</li>
		<?php endforeach; ?>
		<?php if ( $other > 0 ) : ?>
			<?php $pct = round( 100 * (int) $other / $max, 2 ); ?>
			<li class="acx-geo-chart__row acx-geo-chart__row--other">
				<span class="acx-geo-chart__label"><span class="acx-geo-chart__flag acx-geo-chart__flag--none" aria-hidden="true"></span><span class="acx-geo-chart__name"><?php esc_html_e( 'Autres pays', 'antoine-codex-immersive' ); ?></span></span>
				<span class="acx-geo-chart__track" aria-hidden="true"><span class="acx-geo-chart__bar" style="--w:<?php echo esc_attr( (string) $pct ); ?>%"></span></span>
				<span class="acx-geo-chart__value"><?php echo esc_html( number_format_i18n( $other ) ); ?></span>
			</li>
		<?php endif; ?>
	</ol>
	<?php
}

/**
 * Section publique « Visiteurs » au-dessus du pied de page.
 */
function acx_visits_public_section() {
	if ( ! acx_visits_opt( 'visits_public' ) ) {
		return;
	}
	$s = acx_visits_public_stats();
	?>
	<section class="acx-visitors" id="visiteurs" aria-labelledby="acx-visitors-title" data-acx-visitors>
		<div class="container acx-visitors__grid">
			<div class="acx-visitors__head" data-reveal>
				<p class="mono acx-visitors__kicker"><span class="acx-visitors__live" aria-hidden="true"></span><?php esc_html_e( 'Audience en direct', 'antoine-codex-immersive' ); ?></p>
				<h2 class="acx-visitors__title" id="acx-visitors-title"><?php esc_html_e( 'Visiteurs', 'antoine-codex-immersive' ); ?></h2>
				<dl class="acx-visitors__stats">
					<div class="acx-visitors__stat acx-visitors__stat--hero">
						<dt class="mono"><?php esc_html_e( 'Visiteurs', 'antoine-codex-immersive' ); ?></dt>
						<dd data-stat="total_visitors"><?php echo esc_html( number_format_i18n( $s['total_visitors'] ) ); ?></dd>
					</div>
					<div class="acx-visitors__stat">
						<dt class="mono"><?php esc_html_e( 'Aujourd’hui', 'antoine-codex-immersive' ); ?></dt>
						<dd data-stat="today_visitors"><?php echo esc_html( number_format_i18n( $s['today_visitors'] ) ); ?></dd>
					</div>
					<div class="acx-visitors__stat">
						<dt class="mono"><?php esc_html_e( 'Pays', 'antoine-codex-immersive' ); ?></dt>
						<dd data-stat="country_count"><?php echo esc_html( number_format_i18n( $s['country_count'] ) ); ?></dd>
					</div>
				</dl>
				<p class="acx-visitors__note"><?php esc_html_e( 'Mesure anonyme, sans cookie : seuls des totaux par pays sont conservés.', 'antoine-codex-immersive' ); ?></p>
			</div>
			<div class="acx-visitors__chart" data-reveal>
				<p class="mono acx-visitors__chart-title" id="acx-visitors-chart-title"><?php esc_html_e( 'Visiteurs par pays', 'antoine-codex-immersive' ); ?></p>
				<div data-geo-chart-wrap aria-labelledby="acx-visitors-chart-title">
					<?php if ( $s['countries'] ) : ?>
						<?php acx_visits_chart( $s['countries'], $s['other'], 'public' ); ?>
					<?php else : ?>
						<p class="acx-visitors__empty"><?php esc_html_e( 'Les premières visites apparaîtront ici.', 'antoine-codex-immersive' ); ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</section>
	<?php
}

/**
 * Script de comptage (très léger, différé).
 */
function acx_visits_enqueue() {
	if ( ! acx_visits_opt( 'visits_enabled' ) && ! acx_visits_opt( 'visits_public' ) ) {
		return;
	}
	if ( is_preview() || is_customize_preview() ) {
		return;
	}
	wp_enqueue_script(
		'acx-visitors',
		ACX_URI . '/assets/js/visitors.js',
		array(),
		acx_asset_ver( 'assets/js/visitors.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);
	$cfg = array(
		'url'    => esc_url_raw( rest_url( 'antoine-codex/v1/visit' ) ),
		'count'  => (bool) acx_visits_opt( 'visits_enabled' ) && ! is_404(),
		'locale' => str_replace( '_', '-', determine_locale() ),
		'other'  => __( 'Autres pays', 'antoine-codex-immersive' ),
		/* translators: 1: country, 2: visitors, 3: share. */
		'tip'    => __( '%1$s : %2$s visiteurs (%3$s %%)', 'antoine-codex-immersive' ),
	);
	if ( is_user_logged_in() ) {
		$cfg['nonce'] = wp_create_nonce( 'wp_rest' ); // Permet d'exclure les administrateurs du comptage.
	}
	wp_add_inline_script( 'acx-visitors', 'window.ACXVisits=' . wp_json_encode( $cfg ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'acx_visits_enqueue', 30 );

/**
 * Texte suggéré pour la politique de confidentialité.
 */
function acx_visits_privacy_text() {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	wp_add_privacy_policy_content(
		'ANTOINE CODEX Immersive',
		'<p>' . esc_html__( 'Mesure d’audience : à chaque page consultée, le site enregistre de façon anonyme le pays du visiteur, déterminé à partir de son adresse IP au moment de la visite. L’adresse IP n’est pas conservée. Une empreinte non réversible, renouvelée chaque jour, permet de ne compter qu’une fois un même visiteur dans la journée ; elle est supprimée sous 48 heures. Aucun cookie n’est déposé et aucune donnée n’est transmise à des tiers. Seuls des totaux par jour et par pays sont conservés et affichés publiquement.', 'antoine-codex-immersive' ) . '</p>'
	);
}
add_action( 'admin_init', 'acx_visits_privacy_text' );

/* --------------------------------------------------------------------------
 * Administration : widget du tableau de bord + page détaillée
 * ------------------------------------------------------------------------ */

/**
 * Widget du tableau de bord.
 */
function acx_visits_dashboard_widget() {
	if ( current_user_can( 'manage_options' ) ) {
		wp_add_dashboard_widget( 'acx_visits_widget', __( 'Visiteurs — ANTOINE CODEX', 'antoine-codex-immersive' ), 'acx_visits_dashboard_render' );
	}
}
add_action( 'wp_dashboard_setup', 'acx_visits_dashboard_widget' );

/**
 * Feuille de style admin du graphique.
 *
 * @param string $hook Écran.
 */
function acx_visits_admin_assets( $hook ) {
	if ( in_array( $hook, array( 'index.php', 'appearance_page_acx-visitors' ), true ) ) {
		wp_enqueue_style( 'acx-visitors-admin', ACX_URI . '/assets/css/visitors-admin.css', array(), acx_asset_ver( 'assets/css/visitors-admin.css' ) );
	}
}
add_action( 'admin_enqueue_scripts', 'acx_visits_admin_assets' );

/**
 * Contenu du widget.
 */
function acx_visits_dashboard_render() {
	$s = acx_visits_stats( 0 );
	echo '<div class="acx-visits-admin">';
	acx_visits_admin_kpis( $s );
	if ( $s['countries'] ) {
		echo '<h3 class="acx-visits-admin__h">' . esc_html__( 'Visiteurs par pays', 'antoine-codex-immersive' ) . '</h3>';
		$top   = array_slice( $s['countries'], 0, 8 );
		$other = 0;
		foreach ( array_slice( $s['countries'], 8 ) as $c ) {
			$other += $c['visitors'];
		}
		acx_visits_chart( $top, $other, 'admin' );
	} else {
		echo '<p>' . esc_html__( 'Aucune visite enregistrée pour le moment.', 'antoine-codex-immersive' ) . '</p>';
	}
	echo '<p><a class="button" href="' . esc_url( admin_url( 'themes.php?page=acx-visitors' ) ) . '">' . esc_html__( 'Statistiques détaillées', 'antoine-codex-immersive' ) . '</a></p>';
	echo '</div>';
}

/**
 * Indicateurs clés.
 *
 * @param array $s Statistiques.
 */
function acx_visits_admin_kpis( $s ) {
	$kpis = array(
		__( 'Visiteurs', 'antoine-codex-immersive' )      => $s['total_visitors'],
		__( 'Aujourd’hui', 'antoine-codex-immersive' )    => $s['today_visitors'],
		__( 'Pages vues', 'antoine-codex-immersive' )     => $s['total_views'],
		__( 'Pays', 'antoine-codex-immersive' )           => count( array_filter( wp_list_pluck( $s['countries'], 'code' ), function ( $c ) {
			return '--' !== $c;
		} ) ),
	);
	echo '<dl class="acx-visits-admin__kpis">';
	foreach ( $kpis as $label => $v ) {
		echo '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( number_format_i18n( $v ) ) . '</dd></div>';
	}
	echo '</dl>';
}

/**
 * Menu de la page détaillée.
 */
function acx_visits_admin_menu() {
	add_theme_page( __( 'Statistiques visiteurs', 'antoine-codex-immersive' ), __( 'Statistiques visiteurs', 'antoine-codex-immersive' ), 'manage_options', 'acx-visitors', 'acx_visits_admin_page' );
}
add_action( 'admin_menu', 'acx_visits_admin_menu' );

/**
 * Remise à zéro (formulaire avec nonce).
 */
function acx_visits_handle_reset() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'Action non autorisée.', 'antoine-codex-immersive' ) );
	}
	check_admin_referer( 'acx_visits_reset' );
	global $wpdb;
	$t = acx_visits_tables();
	$wpdb->query( "DELETE FROM {$t['stats']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DELETE FROM {$t['seen']}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	acx_visits_flush_cache();
	wp_safe_redirect( admin_url( 'themes.php?page=acx-visitors&reset=1' ) );
	exit;
}
add_action( 'admin_post_acx_visits_reset', 'acx_visits_handle_reset' );

/**
 * Page détaillée : période, pays, visiteurs par jour.
 */
function acx_visits_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$ranges = array(
		7   => __( '7 jours', 'antoine-codex-immersive' ),
		30  => __( '30 jours', 'antoine-codex-immersive' ),
		365 => __( '12 mois', 'antoine-codex-immersive' ),
		0   => __( 'Depuis le début', 'antoine-codex-immersive' ),
	);
	$range = isset( $_GET['range'] ) ? absint( $_GET['range'] ) : 30; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$range = array_key_exists( $range, $ranges ) ? $range : 30;
	$s     = acx_visits_stats( $range );
	?>
	<div class="wrap acx-visits-admin">
		<h1><?php esc_html_e( 'Statistiques visiteurs', 'antoine-codex-immersive' ); ?></h1>
		<?php if ( isset( $_GET['reset'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'Les statistiques ont été remises à zéro.', 'antoine-codex-immersive' ); ?></p></div>
		<?php endif; ?>
		<nav class="acx-visits-admin__ranges" aria-label="<?php esc_attr_e( 'Période', 'antoine-codex-immersive' ); ?>">
			<?php foreach ( $ranges as $k => $label ) : ?>
				<a class="button<?php echo $k === $range ? ' button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'range', $k ) ); ?>"<?php echo $k === $range ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php acx_visits_admin_kpis( $s ); ?>

		<?php if ( $range > 0 ) : ?>
			<h2><?php esc_html_e( 'Visiteurs par jour', 'antoine-codex-immersive' ); ?></h2>
			<?php
			$series = array();
			$today  = current_time( 'Y-m-d' );
			for ( $i = $range - 1; $i >= 0; $i-- ) {
				$d            = gmdate( 'Y-m-d', strtotime( $today . ' -' . $i . ' days' ) );
				$series[ $d ] = isset( $s['days'][ $d ] ) ? $s['days'][ $d ] : 0;
			}
			$max = max( 1, max( $series ) );
			?>
			<div class="acx-visits-days" role="img" aria-label="<?php esc_attr_e( 'Visiteurs par jour sur la période', 'antoine-codex-immersive' ); ?>">
				<div class="acx-visits-days__max"><?php echo esc_html( number_format_i18n( $max ) ); ?></div>
				<div class="acx-visits-days__bars" style="--n:<?php echo (int) count( $series ); ?>">
					<?php foreach ( $series as $d => $v ) : ?>
						<span class="acx-visits-days__col" style="--h:<?php echo esc_attr( (string) round( 100 * $v / $max, 2 ) ); ?>%"><span class="acx-visits-days__tip"><?php echo esc_html( date_i18n( 'j M', strtotime( $d ) ) . ' : ' . number_format_i18n( $v ) ); ?></span></span>
					<?php endforeach; ?>
				</div>
				<div class="acx-visits-days__axis"><span><?php echo esc_html( date_i18n( 'j M', strtotime( array_key_first( $series ) ) ) ); ?></span><span><?php echo esc_html( date_i18n( 'j M', strtotime( $today ) ) ); ?></span></div>
			</div>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Visiteurs par pays', 'antoine-codex-immersive' ); ?></h2>
		<?php if ( $s['countries'] ) : ?>
			<?php acx_visits_chart( array_slice( $s['countries'], 0, 15 ), 0, 'admin' ); ?>
			<table class="widefat striped acx-visits-admin__table">
				<thead><tr><th><?php esc_html_e( 'Pays', 'antoine-codex-immersive' ); ?></th><th class="num"><?php esc_html_e( 'Visiteurs', 'antoine-codex-immersive' ); ?></th><th class="num"><?php esc_html_e( 'Pages vues', 'antoine-codex-immersive' ); ?></th><th class="num">%</th></tr></thead>
				<tbody>
				<?php foreach ( $s['countries'] as $c ) : ?>
					<tr>
						<td><?php if ( $c['flag'] ) : ?><img src="<?php echo esc_url( $c['flag'] ); ?>" width="21" height="14" alt="" style="vertical-align:middle;margin-right:8px;border-radius:2px"><?php endif; ?><?php echo esc_html( $c['name'] ); ?> <code><?php echo esc_html( $c['code'] ); ?></code></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $c['visitors'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $c['views'] ) ); ?></td>
						<td class="num"><?php echo esc_html( number_format_i18n( $c['share'], 1 ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php else : ?>
			<p><?php esc_html_e( 'Aucune visite enregistrée sur cette période.', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>

		<h2><?php esc_html_e( 'Fonctionnement', 'antoine-codex-immersive' ); ?></h2>
		<p><?php esc_html_e( '« Visiteurs » : visiteurs uniques par jour, additionnés (sans cookie, une personne revenant un autre jour est comptée à nouveau). Les robots connus sont exclus. Les administrateurs connectés ne sont pas comptés, sauf réglage contraire.', 'antoine-codex-immersive' ); ?></p>
		<p>
			<?php esc_html_e( 'Pays : en-tête du CDN ou de l’hébergeur (Cloudflare, CloudFront…) s’il existe, sinon base IP locale incluse dans le thème (données NRO, licence CC BY 4.0). Aucun service externe n’est appelé.', 'antoine-codex-immersive' ); ?>
			<?php
			/* translators: %s: country name. */
			echo ' ' . esc_html( sprintf( __( 'Votre connexion actuelle est localisée : %s.', 'antoine-codex-immersive' ), acx_country_name( acx_visitor_country() ) ) );
			?>
		</p>
		<p><?php esc_html_e( 'Réglages (affichage public, nombre de pays, comptage des administrateurs) : Apparence → Personnaliser → ANTOINE CODEX — Thème → Compteur de visiteurs.', 'antoine-codex-immersive' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm(<?php echo esc_attr( wp_json_encode( __( 'Effacer définitivement toutes les statistiques ?', 'antoine-codex-immersive' ) ) ); ?>);">
			<input type="hidden" name="action" value="acx_visits_reset">
			<?php wp_nonce_field( 'acx_visits_reset' ); ?>
			<?php submit_button( __( 'Remettre les statistiques à zéro', 'antoine-codex-immersive' ), 'delete', 'submit', false ); ?>
		</form>
	</div>
	<?php
}
