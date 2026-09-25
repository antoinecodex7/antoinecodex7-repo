<?php
/**
 * ANTOINE CODEX CORE — passerelle serveur et panneau de conversation.
 *
 * Le navigateur ne parle JAMAIS directement au serveur de CORE : il appelle
 * /wp-json/antoine-codex/v1/core/message, et WordPress relaie la requête côté
 * serveur avec la clé d'API. Ni la clé ni l'adresse du serveur CORE ne sont
 * exposées dans le HTML ou le JavaScript public.
 *
 * Modes :
 *  - off      : aucun bouton, aucun panneau.
 *  - proxy    : WordPress relaie vers l'API HTTP de CORE (format « générique » JSON
 *               ou « OpenAI » /v1/chat/completions).
 *  - hook     : une extension PHP existante répond via le filtre
 *               `antoine_codex_core_reply` (CORE installé comme extension WordPress).
 *  - external : CORE possède déjà son propre widget (script/extension) ; le thème
 *               n'affiche que son bouton et ouvre le widget existant.
 *
 * Réglages : Apparence → ANTOINE CODEX CORE, ou constantes dans wp-config.php
 * (prioritaires) : ACX_CORE_MODE, ACX_CORE_ENDPOINT, ACX_CORE_TOKEN,
 * ACX_CORE_AUTH_HEADER, ACX_CORE_FORMAT, ACX_CORE_MODEL.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Réglages par défaut.
 *
 * @return array
 */
function acx_core_defaults() {
	return array(
		'mode'              => 'off',
		'endpoint'          => '',
		'token'             => '',
		'auth_header'       => 'Authorization',
		'format'            => 'generic',
		'model'             => '',
		'system_prompt'     => '',
		'timeout'           => 45,
		'rate_limit'        => 30,
		'external_js'       => '',
		'external_selector' => '',
		'suggestions'       => '',
		'show_launcher'     => 1,
	);
}

/**
 * Réglages effectifs (option + constantes de wp-config.php).
 *
 * @return array
 */
function acx_core_settings() {
	$saved = get_option( 'acx_core', array() );
	$s     = wp_parse_args( is_array( $saved ) ? $saved : array(), acx_core_defaults() );

	$constants = array(
		'mode'        => 'ACX_CORE_MODE',
		'endpoint'    => 'ACX_CORE_ENDPOINT',
		'token'       => 'ACX_CORE_TOKEN',
		'auth_header' => 'ACX_CORE_AUTH_HEADER',
		'format'      => 'ACX_CORE_FORMAT',
		'model'       => 'ACX_CORE_MODEL',
	);
	foreach ( $constants as $key => $const ) {
		if ( defined( $const ) && '' !== (string) constant( $const ) ) {
			$s[ $key ] = (string) constant( $const );
		}
	}

	if ( ! in_array( $s['mode'], array( 'off', 'proxy', 'hook', 'external' ), true ) ) {
		$s['mode'] = 'off';
	}
	if ( ! in_array( $s['format'], array( 'generic', 'openai' ), true ) ) {
		$s['format'] = 'generic';
	}
	$s['timeout']    = max( 5, min( 120, (int) $s['timeout'] ) );
	$s['rate_limit'] = max( 1, min( 500, (int) $s['rate_limit'] ) );

	return apply_filters( 'acx_core_settings', $s );
}

/**
 * CORE est-il relié (configuration complète) ?
 *
 * @return bool
 */
function acx_core_is_configured() {
	$s = acx_core_settings();
	switch ( $s['mode'] ) {
		case 'proxy':
			return '' !== trim( $s['endpoint'] );
		case 'hook':
			return has_filter( 'antoine_codex_core_reply' );
		case 'external':
			return '' !== trim( $s['external_js'] ) || '' !== trim( $s['external_selector'] );
	}
	return false;
}

/**
 * CORE est-il proposé aux visiteurs ?
 *
 * @return bool
 */
function acx_core_is_public() {
	return (bool) apply_filters( 'acx_core_is_public', acx_core_is_configured() );
}

/**
 * Le panneau doit-il être rendu sur cette page ?
 * Un administrateur le voit même non configuré (avec un avertissement) pour tester.
 *
 * @return bool
 */
function acx_core_should_render() {
	$s = acx_core_settings();
	if ( 'off' === $s['mode'] ) {
		return false;
	}
	return acx_core_is_public() || current_user_can( 'manage_options' );
}

/**
 * Suggestions de questions (une par ligne).
 *
 * @return array
 */
function acx_core_suggestions() {
	$s     = acx_core_settings();
	$lines = trim( (string) $s['suggestions'] );
	if ( '' === $lines ) {
		$lines = implode(
			"\n",
			array(
				__( 'Par où commencer pour mon entreprise ?', 'antoine-codex-immersive' ),
				__( 'Quelle différence entre le Quick Diagnostic et le diagnostic complet ?', 'antoine-codex-immersive' ),
				__( 'Comment l’IA peut-elle faire gagner du temps à mon équipe ?', 'antoine-codex-immersive' ),
			)
		);
	}
	return array_slice( array_values( array_filter( array_map( 'trim', explode( "\n", $lines ) ) ) ), 0, 4 );
}

/**
 * Scripts du panneau.
 */
function acx_core_enqueue() {
	if ( ! acx_core_should_render() ) {
		return;
	}
	$s = acx_core_settings();

	wp_enqueue_script(
		'acx-core',
		ACX_URI . '/assets/js/core-chat.js',
		array(),
		acx_asset_ver( 'assets/js/core-chat.js' ),
		array(
			'in_footer' => true,
			'strategy'  => 'defer',
		)
	);

	$config = array(
		'mode'             => $s['mode'],
		'configured'       => acx_core_is_configured(),
		'restUrl'          => esc_url_raw( rest_url( 'antoine-codex/v1/core/message' ) ),
		'externalJs'       => 'external' === $s['mode'] ? (string) $s['external_js'] : '',
		'externalSelector' => 'external' === $s['mode'] ? (string) $s['external_selector'] : '',
		'timeout'          => (int) $s['timeout'] + 5,
		'maxLength'        => 2000,
		'locale'           => get_locale(),
		'storageKey'       => 'acx-core-' . substr( md5( home_url() ), 0, 8 ),
		'i18n'             => array(
			'you'          => __( 'Vous', 'antoine-codex-immersive' ),
			'core'         => 'CORE',
			'thinking'     => __( 'CORE analyse votre message…', 'antoine-codex-immersive' ),
			'error'        => __( 'ANTOINE CODEX CORE n’a pas pu répondre. Vérifiez votre connexion puis réessayez.', 'antoine-codex-immersive' ),
			'errorBusy'    => __( 'Trop de messages en peu de temps. Merci de patienter quelques minutes.', 'antoine-codex-immersive' ),
			'errorOff'     => __( 'ANTOINE CODEX CORE est momentanément indisponible. Vous pouvez utiliser le Quick Diagnostic ou nous contacter.', 'antoine-codex-immersive' ),
			'errorTimeout' => __( 'La réponse prend plus de temps que prévu. Merci de réessayer.', 'antoine-codex-immersive' ),
			'offline'      => __( 'Vous semblez hors ligne. Le message sera à renvoyer une fois la connexion rétablie.', 'antoine-codex-immersive' ),
			'retry'        => __( 'Réessayer', 'antoine-codex-immersive' ),
			'tooLong'      => __( 'Message trop long.', 'antoine-codex-immersive' ),
			'reset'        => __( 'La conversation a été effacée.', 'antoine-codex-immersive' ),
			'opened'       => __( 'Conversation ANTOINE CODEX CORE ouverte.', 'antoine-codex-immersive' ),
			'diagReceived' => __( 'Résultat du Quick Diagnostic reçu. Vous pouvez en discuter avec CORE.', 'antoine-codex-immersive' ),
			'diagSend'     => __( 'Analyser mon résultat avec CORE', 'antoine-codex-immersive' ),
			'diagPrompt'   => __( 'Voici le résultat de mon Quick Diagnostic. Pouvez-vous m’aider à l’interpréter et à identifier les priorités ?', 'antoine-codex-immersive' ),
			'externalMissing' => __( 'Le widget ANTOINE CODEX CORE existant est introuvable sur cette page.', 'antoine-codex-immersive' ),
		),
	);
	wp_add_inline_script( 'acx-core', 'window.ACXCoreConfig=' . wp_json_encode( $config ) . ';', 'before' );
}
add_action( 'wp_enqueue_scripts', 'acx_core_enqueue', 20 );

/**
 * Bouton + panneau (rendu dans le pied de page).
 */
function acx_core_render() {
	if ( ! acx_core_should_render() ) {
		return;
	}
	$s           = acx_core_settings();
	$configured  = acx_core_is_configured();
	$is_external = 'external' === $s['mode'];
	?>
	<div class="acx-core" data-acx-core data-mode="<?php echo esc_attr( $s['mode'] ); ?>">
		<?php if ( $s['show_launcher'] ) : ?>
			<button type="button" class="acx-core__launcher" data-acx-core-launcher aria-controls="acx-core-panel" aria-expanded="false" hidden>
				<span class="acx-core__launcher-orb" aria-hidden="true"><span></span></span>
				<span class="acx-core__launcher-text">
					<span class="acx-core__launcher-kicker mono"><?php esc_html_e( 'Assistant IA', 'antoine-codex-immersive' ); ?></span>
					<span class="acx-core__launcher-name">ANTOINE CODEX <b>CORE</b></span>
				</span>
			</button>
		<?php endif; ?>

		<?php if ( ! $is_external ) : ?>
			<div class="acx-core__backdrop" data-acx-core-close hidden></div>
			<section id="acx-core-panel" class="acx-core__panel" role="dialog" aria-modal="false" aria-labelledby="acx-core-title" aria-describedby="acx-core-desc" hidden>
				<header class="acx-core__head">
					<div class="acx-core__id">
						<span class="acx-core__orb" aria-hidden="true"><span></span></span>
						<div>
							<p class="acx-core__kicker mono"><?php esc_html_e( 'Assistant IA · conversation', 'antoine-codex-immersive' ); ?></p>
							<h2 class="acx-core__title" id="acx-core-title">ANTOINE CODEX <b>CORE</b></h2>
						</div>
					</div>
					<div class="acx-core__tools">
						<button type="button" class="acx-core__icon-btn" data-acx-core-reset title="<?php esc_attr_e( 'Nouvelle conversation', 'antoine-codex-immersive' ); ?>">
							<?php acx_the_icon( 'reset' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Nouvelle conversation', 'antoine-codex-immersive' ); ?></span>
						</button>
						<button type="button" class="acx-core__icon-btn" data-acx-core-close title="<?php esc_attr_e( 'Fermer', 'antoine-codex-immersive' ); ?>">
							<?php acx_the_icon( 'close' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Fermer la conversation', 'antoine-codex-immersive' ); ?></span>
						</button>
					</div>
				</header>
				<div class="acx-core__rule" aria-hidden="true"></div>

				<?php if ( ! $configured ) : ?>
					<p class="acx-core__admin-notice" role="note">
						<?php esc_html_e( 'Visible uniquement par les administrateurs : ANTOINE CODEX CORE n’est pas encore relié.', 'antoine-codex-immersive' ); ?>
						<a href="<?php echo esc_url( admin_url( 'themes.php?page=acx-core' ) ); ?>"><?php esc_html_e( 'Configurer la connexion', 'antoine-codex-immersive' ); ?></a>
					</p>
				<?php endif; ?>

				<div class="acx-core__body" data-acx-core-scroll>
					<div class="acx-core__intro" data-acx-core-intro>
						<p id="acx-core-desc"><?php esc_html_e( 'Posez une question sur votre entreprise, nos expertises ou la démarche de diagnostic. ANTOINE CODEX CORE vous répond et vous oriente.', 'antoine-codex-immersive' ); ?></p>
						<ul class="acx-core__suggestions">
							<?php foreach ( acx_core_suggestions() as $suggestion ) : ?>
								<li><button type="button" class="acx-core__chip" data-acx-core-suggest><?php echo esc_html( $suggestion ); ?></button></li>
							<?php endforeach; ?>
						</ul>
						<a class="acx-core__quick" href="<?php echo esc_url( acx_page_url( 'quick' ) ); ?>">
							<span class="mono"><?php esc_html_e( 'Autre option', 'antoine-codex-immersive' ); ?></span>
							<strong><?php esc_html_e( 'Quick Diagnostic', 'antoine-codex-immersive' ); ?></strong>
							<span><?php esc_html_e( 'Un questionnaire guidé de quelques minutes, avec un résultat structuré.', 'antoine-codex-immersive' ); ?></span>
							<?php acx_the_icon( 'arrow' ); ?>
						</a>
					</div>
					<div class="acx-core__diag" data-acx-core-diag hidden></div>
					<ol class="acx-core__log" data-acx-core-log role="log" aria-live="polite" aria-relevant="additions" aria-label="<?php esc_attr_e( 'Messages de la conversation', 'antoine-codex-immersive' ); ?>"></ol>
				</div>

				<form class="acx-core__form" data-acx-core-form>
					<label class="screen-reader-text" for="acx-core-input"><?php esc_html_e( 'Votre message à ANTOINE CODEX CORE', 'antoine-codex-immersive' ); ?></label>
					<textarea id="acx-core-input" class="acx-core__input" rows="1" maxlength="2000" placeholder="<?php esc_attr_e( 'Écrivez votre message…', 'antoine-codex-immersive' ); ?>" data-acx-core-input></textarea>
					<button type="submit" class="acx-core__send" data-acx-core-send>
						<?php acx_the_icon( 'send' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Envoyer', 'antoine-codex-immersive' ); ?></span>
					</button>
					<p class="acx-core__foot mono">
						<span><?php esc_html_e( 'Entrée pour envoyer · Maj+Entrée pour aller à la ligne', 'antoine-codex-immersive' ); ?></span>
						<span data-acx-core-count>0 / 2000</span>
					</p>
					<p class="acx-core__privacy">
						<?php esc_html_e( 'Réponses générées par IA, à vérifier. Évitez de partager des données sensibles.', 'antoine-codex-immersive' ); ?>
						<?php if ( get_privacy_policy_url() ) : ?>
							<a href="<?php echo esc_url( get_privacy_policy_url() ); ?>"><?php esc_html_e( 'Confidentialité', 'antoine-codex-immersive' ); ?></a>
						<?php endif; ?>
					</p>
				</form>
			</section>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'wp_footer', 'acx_core_render', 5 );

/**
 * Routes REST.
 */
function acx_core_register_routes() {
	register_rest_route(
		'antoine-codex/v1',
		'/core/message',
		array(
			'methods'             => 'POST',
			'callback'            => 'acx_core_rest_message',
			'permission_callback' => '__return_true', // Public : protégé par contrôle d'origine + limitation de débit.
		)
	);
	register_rest_route(
		'antoine-codex/v1',
		'/core/test',
		array(
			'methods'             => 'POST',
			'callback'            => 'acx_core_rest_test',
			'permission_callback' => function () {
				return current_user_can( 'manage_options' );
			},
		)
	);
}
add_action( 'rest_api_init', 'acx_core_register_routes' );

/**
 * Refuse les appels provenant d'un autre site.
 *
 * @param WP_REST_Request $request Requête.
 * @return bool
 */
function acx_core_same_origin( $request ) {
	$home   = wp_parse_url( home_url(), PHP_URL_HOST );
	$origin = $request->get_header( 'origin' );
	$ref    = $request->get_header( 'referer' );
	$source = $origin ? $origin : $ref;
	if ( ! $source ) {
		return (bool) apply_filters( 'acx_core_allow_missing_origin', true );
	}
	$host    = wp_parse_url( $source, PHP_URL_HOST );
	$allowed = apply_filters( 'acx_core_allowed_hosts', array( $home ) );
	return in_array( $host, $allowed, true );
}

/**
 * Nettoie l'historique envoyé par le navigateur.
 *
 * @param mixed $history Historique brut.
 * @return array
 */
function acx_core_clean_history( $history ) {
	$clean = array();
	if ( ! is_array( $history ) ) {
		return $clean;
	}
	foreach ( array_slice( $history, -12 ) as $item ) {
		if ( ! is_array( $item ) || empty( $item['content'] ) || empty( $item['role'] ) ) {
			continue;
		}
		$role = 'assistant' === $item['role'] ? 'assistant' : 'user';
		$clean[] = array(
			'role'    => $role,
			'content' => mb_substr( sanitize_textarea_field( (string) $item['content'] ), 0, 4000 ),
		);
	}
	return $clean;
}

/**
 * Point d'entrée public : un message → une réponse de CORE.
 *
 * @param WP_REST_Request $request Requête.
 * @return WP_REST_Response|WP_Error
 */
function acx_core_rest_message( WP_REST_Request $request ) {
	$s = acx_core_settings();

	if ( ! in_array( $s['mode'], array( 'proxy', 'hook' ), true ) || ! acx_core_is_configured() ) {
		return new WP_Error( 'acx_core_disabled', __( 'ANTOINE CODEX CORE n’est pas relié.', 'antoine-codex-immersive' ), array( 'status' => 503 ) );
	}
	if ( ! acx_core_same_origin( $request ) ) {
		return new WP_Error( 'acx_core_forbidden', __( 'Origine non autorisée.', 'antoine-codex-immersive' ), array( 'status' => 403 ) );
	}
	if ( ! acx_rate_limit( 'core', $s['rate_limit'], 10 * MINUTE_IN_SECONDS ) ) {
		return new WP_Error( 'acx_core_rate', __( 'Trop de messages.', 'antoine-codex-immersive' ), array( 'status' => 429 ) );
	}

	$message = trim( sanitize_textarea_field( (string) $request->get_param( 'message' ) ) );
	if ( '' === $message || mb_strlen( $message ) > 2000 ) {
		return new WP_Error( 'acx_core_invalid', __( 'Message vide ou trop long.', 'antoine-codex-immersive' ), array( 'status' => 400 ) );
	}

	$session = preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $request->get_param( 'session_id' ) );
	$session = substr( $session ? $session : wp_generate_uuid4(), 0, 64 );
	$history = acx_core_clean_history( $request->get_param( 'history' ) );

	$raw_ctx = $request->get_param( 'context' );
	$raw_ctx = is_array( $raw_ctx ) ? $raw_ctx : array();
	$page    = isset( $raw_ctx['page_url'] ) ? esc_url_raw( (string) $raw_ctx['page_url'] ) : '';
	$context = array(
		'page_url'   => $page && wp_parse_url( $page, PHP_URL_HOST ) === wp_parse_url( home_url(), PHP_URL_HOST ) ? $page : '',
		'page_title' => isset( $raw_ctx['page_title'] ) ? mb_substr( sanitize_text_field( (string) $raw_ctx['page_title'] ), 0, 200 ) : '',
		'source'     => isset( $raw_ctx['source'] ) ? sanitize_key( (string) $raw_ctx['source'] ) : 'chat',
		'locale'     => isset( $raw_ctx['locale'] ) ? sanitize_text_field( (string) $raw_ctx['locale'] ) : get_locale(),
		'diagnostic' => isset( $raw_ctx['diagnostic'] ) ? mb_substr( sanitize_textarea_field( is_string( $raw_ctx['diagnostic'] ) ? $raw_ctx['diagnostic'] : wp_json_encode( $raw_ctx['diagnostic'] ) ), 0, 8000 ) : '',
	);

	$result = 'hook' === $s['mode']
		? acx_core_reply_via_hook( $message, $history, $context, $session )
		: acx_core_reply_via_proxy( $s, $message, $history, $context, $session );

	if ( is_wp_error( $result ) ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'ANTOINE CODEX CORE: ' . $result->get_error_code() . ' — ' . $result->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $result;
	}

	$response = rest_ensure_response(
		array(
			'reply'       => $result['reply'],
			'suggestions' => $result['suggestions'],
			'session_id'  => $result['session_id'] ? $result['session_id'] : $session,
		)
	);
	$response->header( 'Cache-Control', 'no-store' );
	return $response;
}

/**
 * Normalise une réponse (texte ou tableau) de CORE.
 *
 * @param mixed  $data    Données décodées.
 * @param string $session Session courante.
 * @return array|WP_Error
 */
function acx_core_normalize_reply( $data, $session ) {
	$reply       = '';
	$suggestions = array();
	$sid         = '';

	if ( is_string( $data ) ) {
		$reply = $data;
	} elseif ( is_array( $data ) ) {
		if ( isset( $data['choices'][0]['message']['content'] ) ) {
			$reply = $data['choices'][0]['message']['content'];
		} elseif ( isset( $data['message']['content'] ) ) { // Format Ollama /api/chat.
			$reply = $data['message']['content'];
		} else {
			$containers = array( $data );
			if ( isset( $data['data'] ) && is_array( $data['data'] ) ) {
				$containers[] = $data['data'];
			}
			foreach ( $containers as $c ) {
				foreach ( array( 'reply', 'response', 'answer', 'output', 'message', 'text', 'content' ) as $key ) {
					if ( isset( $c[ $key ] ) && is_string( $c[ $key ] ) && '' !== trim( $c[ $key ] ) ) {
						$reply = $c[ $key ];
						break 2;
					}
				}
			}
		}
		if ( isset( $data['suggestions'] ) && is_array( $data['suggestions'] ) ) {
			$suggestions = $data['suggestions'];
		}
		if ( isset( $data['session_id'] ) && is_string( $data['session_id'] ) ) {
			$sid = $data['session_id'];
		}
	}

	$reply = trim( wp_kses( (string) $reply, array() ) );
	if ( '' === $reply ) {
		return new WP_Error( 'acx_core_empty', __( 'Réponse vide de CORE.', 'antoine-codex-immersive' ), array( 'status' => 502 ) );
	}

	$suggestions = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', array_filter( $suggestions, 'is_string' ) ) ) ), 0, 4 );

	return array(
		'reply'       => $reply,
		'suggestions' => $suggestions,
		'session_id'  => substr( preg_replace( '/[^A-Za-z0-9_-]/', '', $sid ), 0, 64 ),
	);
}

/**
 * Mode « hook » : CORE installé comme extension PHP.
 *
 * Exemple dans l'extension CORE :
 *   add_filter( 'antoine_codex_core_reply', function ( $reply, $message, $history, $context, $session ) {
 *       return my_core_answer( $message, $history, $context ); // string ou array( 'reply' => ..., 'suggestions' => [...] )
 *   }, 10, 5 );
 *
 * @param string $message Message.
 * @param array  $history Historique.
 * @param array  $context Contexte.
 * @param string $session Session.
 * @return array|WP_Error
 */
function acx_core_reply_via_hook( $message, $history, $context, $session ) {
	$reply = apply_filters( 'antoine_codex_core_reply', null, $message, $history, $context, $session );
	if ( is_wp_error( $reply ) ) {
		return $reply;
	}
	if ( null === $reply ) {
		return new WP_Error( 'acx_core_disabled', __( 'Aucune extension CORE ne répond.', 'antoine-codex-immersive' ), array( 'status' => 503 ) );
	}
	return acx_core_normalize_reply( $reply, $session );
}

/**
 * Mode « proxy » : appel HTTP serveur → serveur.
 *
 * @param array  $s       Réglages.
 * @param string $message Message.
 * @param array  $history Historique.
 * @param array  $context Contexte.
 * @param string $session Session.
 * @return array|WP_Error
 */
function acx_core_reply_via_proxy( $s, $message, $history, $context, $session ) {
	$headers = array(
		'Content-Type' => 'application/json',
		'Accept'       => 'application/json',
	);
	if ( '' !== $s['token'] ) {
		$header_name = trim( $s['auth_header'] ) ? trim( $s['auth_header'] ) : 'Authorization';
		$headers[ $header_name ] = 'authorization' === strtolower( $header_name ) ? 'Bearer ' . $s['token'] : $s['token'];
	}

	if ( 'openai' === $s['format'] ) {
		$messages = array();
		if ( '' !== trim( $s['system_prompt'] ) ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => $s['system_prompt'],
			);
		}
		if ( $context['diagnostic'] ) {
			$messages[] = array(
				'role'    => 'system',
				'content' => "Résultat du Quick Diagnostic transmis par le visiteur :\n" . $context['diagnostic'],
			);
		}
		$messages   = array_merge( $messages, $history );
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);
		$body = array(
			'messages' => $messages,
			'stream'   => false,
			'user'     => $session,
		);
		if ( '' !== $s['model'] ) {
			$body['model'] = $s['model'];
		}
	} else {
		$body = array(
			'message'    => $message,
			'session_id' => $session,
			'history'    => $history,
			'context'    => $context,
			'site'       => home_url( '/' ),
		);
		if ( '' !== trim( $s['system_prompt'] ) ) {
			$body['system_prompt'] = $s['system_prompt'];
		}
	}

	$body = apply_filters( 'acx_core_request_body', $body, $s, $message, $history, $context );

	$response = wp_remote_post(
		$s['endpoint'],
		array(
			'timeout'     => $s['timeout'],
			'headers'     => $headers,
			'body'        => wp_json_encode( $body ),
			'data_format' => 'body',
			'user-agent'  => 'ANTOINE-CODEX-Immersive/' . ACX_VERSION . '; ' . home_url( '/' ),
		)
	);

	if ( is_wp_error( $response ) ) {
		$msg  = $response->get_error_message();
		$code = false !== stripos( $msg, 'timed out' ) || false !== stripos( $msg, 'timeout' ) ? 'acx_core_timeout' : 'acx_core_upstream';
		return new WP_Error( $code, $msg, array( 'status' => 'acx_core_timeout' === $code ? 504 : 502 ) );
	}

	$status = (int) wp_remote_retrieve_response_code( $response );
	$raw    = wp_remote_retrieve_body( $response );

	if ( $status < 200 || $status >= 300 ) {
		return new WP_Error( 'acx_core_upstream', sprintf( 'HTTP %d', $status ), array( 'status' => 429 === $status ? 429 : 502 ) );
	}

	$data = json_decode( $raw, true );
	if ( null === $data && '' !== trim( $raw ) ) {
		$data = $raw; // Réponse texte brut acceptée.
	}

	return acx_core_normalize_reply( apply_filters( 'acx_core_response_data', $data, $raw, $s ), $session );
}

/**
 * Test de connexion depuis l'administration.
 *
 * @return WP_REST_Response
 */
function acx_core_rest_test() {
	$s = acx_core_settings();
	if ( ! in_array( $s['mode'], array( 'proxy', 'hook' ), true ) ) {
		return rest_ensure_response(
			array(
				'ok'      => false,
				'message' => __( 'Le test s’applique aux modes « Passerelle HTTP » et « Extension PHP ».', 'antoine-codex-immersive' ),
			)
		);
	}
	if ( ! acx_core_is_configured() ) {
		return rest_ensure_response(
			array(
				'ok'      => false,
				'message' => __( 'Configuration incomplète.', 'antoine-codex-immersive' ),
			)
		);
	}

	$start   = microtime( true );
	$context = array(
		'page_url'   => home_url( '/' ),
		'page_title' => 'Test',
		'source'     => 'admin-test',
		'locale'     => get_locale(),
		'diagnostic' => '',
	);
	$message = __( 'Test de connexion depuis WordPress. Répondez brièvement.', 'antoine-codex-immersive' );
	$session = 'admin-test-' . wp_generate_password( 8, false );
	$result  = 'hook' === $s['mode']
		? acx_core_reply_via_hook( $message, array(), $context, $session )
		: acx_core_reply_via_proxy( $s, $message, array(), $context, $session );
	$ms      = (int) round( ( microtime( true ) - $start ) * 1000 );

	if ( is_wp_error( $result ) ) {
		return rest_ensure_response(
			array(
				'ok'      => false,
				'message' => $result->get_error_code() . ' — ' . $result->get_error_message(),
				'ms'      => $ms,
			)
		);
	}
	return rest_ensure_response(
		array(
			'ok'      => true,
			'message' => mb_substr( $result['reply'], 0, 300 ),
			'ms'      => $ms,
		)
	);
}
