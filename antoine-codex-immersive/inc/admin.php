<?php
/**
 * Administration : réglages d'ANTOINE CODEX CORE et diagnostic de migration.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Menus.
 */
function acx_admin_menu() {
	add_theme_page(
		__( 'ANTOINE CODEX CORE', 'antoine-codex-immersive' ),
		__( 'ANTOINE CODEX CORE', 'antoine-codex-immersive' ),
		'manage_options',
		'acx-core',
		'acx_core_settings_page'
	);
	add_theme_page(
		__( 'Diagnostic de migration', 'antoine-codex-immersive' ),
		__( 'Diagnostic de migration', 'antoine-codex-immersive' ),
		'manage_options',
		'acx-migration',
		'acx_migration_page'
	);
}
add_action( 'admin_menu', 'acx_admin_menu' );

/**
 * Enregistrement de l'option CORE.
 */
function acx_core_register_setting() {
	register_setting(
		'acx_core',
		'acx_core',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'acx_core_sanitize',
			'default'           => acx_core_defaults(),
			'show_in_rest'      => false,
		)
	);
}
add_action( 'admin_init', 'acx_core_register_setting' );

/**
 * Nettoyage des réglages CORE.
 *
 * @param array $input Entrée.
 * @return array
 */
function acx_core_sanitize( $input ) {
	$old = get_option( 'acx_core', array() );
	$old = wp_parse_args( is_array( $old ) ? $old : array(), acx_core_defaults() );
	$in  = is_array( $input ) ? $input : array();
	$out = acx_core_defaults();

	$out['mode']   = isset( $in['mode'] ) && in_array( $in['mode'], array( 'off', 'proxy', 'hook', 'external' ), true ) ? $in['mode'] : 'off';
	$out['format'] = isset( $in['format'] ) && in_array( $in['format'], array( 'generic', 'openai' ), true ) ? $in['format'] : 'generic';

	$out['endpoint']    = isset( $in['endpoint'] ) ? esc_url_raw( trim( $in['endpoint'] ), array( 'http', 'https' ) ) : '';
	$out['auth_header'] = isset( $in['auth_header'] ) ? preg_replace( '/[^A-Za-z0-9-]/', '', $in['auth_header'] ) : 'Authorization';
	$out['auth_header'] = $out['auth_header'] ? $out['auth_header'] : 'Authorization';
	$out['model']       = isset( $in['model'] ) ? sanitize_text_field( $in['model'] ) : '';

	// Clé : champ vide = conserver l'ancienne ; case « effacer » = supprimer.
	if ( ! empty( $in['token_clear'] ) ) {
		$out['token'] = '';
	} elseif ( isset( $in['token'] ) && '' !== trim( $in['token'] ) ) {
		$out['token'] = trim( sanitize_text_field( $in['token'] ) );
	} else {
		$out['token'] = $old['token'];
	}

	$out['system_prompt']     = isset( $in['system_prompt'] ) ? sanitize_textarea_field( $in['system_prompt'] ) : '';
	$out['timeout']           = isset( $in['timeout'] ) ? max( 5, min( 120, absint( $in['timeout'] ) ) ) : 45;
	$out['rate_limit']        = isset( $in['rate_limit'] ) ? max( 1, min( 500, absint( $in['rate_limit'] ) ) ) : 30;
	$out['external_js']       = isset( $in['external_js'] ) ? preg_replace( '/[^A-Za-z0-9_.$]/', '', $in['external_js'] ) : '';
	$out['external_selector'] = isset( $in['external_selector'] ) ? sanitize_text_field( $in['external_selector'] ) : '';
	$out['suggestions']       = isset( $in['suggestions'] ) ? sanitize_textarea_field( $in['suggestions'] ) : '';
	$out['show_launcher']     = empty( $in['show_launcher'] ) ? 0 : 1;

	return $out;
}

/**
 * Page de réglages CORE.
 */
function acx_core_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$saved  = wp_parse_args( (array) get_option( 'acx_core', array() ), acx_core_defaults() );
	$eff    = acx_core_settings();
	$locked = function ( $const ) {
		return defined( $const ) && '' !== (string) constant( $const );
	};
	$name   = 'acx_core';
	?>
	<div class="wrap acx-admin">
		<h1><?php esc_html_e( 'ANTOINE CODEX CORE — connexion', 'antoine-codex-immersive' ); ?></h1>
		<p><?php esc_html_e( 'Le thème affiche le bouton et le panneau de conversation. Les réponses proviennent exclusivement de votre ANTOINE CODEX CORE : aucun assistant de démonstration n’est intégré.', 'antoine-codex-immersive' ); ?></p>

		<div class="notice notice-<?php echo acx_core_is_configured() ? 'success' : 'warning'; ?> inline">
			<p>
				<strong><?php esc_html_e( 'État :', 'antoine-codex-immersive' ); ?></strong>
				<?php
				if ( 'off' === $eff['mode'] ) {
					esc_html_e( 'désactivé — aucun bouton affiché.', 'antoine-codex-immersive' );
				} elseif ( acx_core_is_configured() ) {
					esc_html_e( 'configuré — le bouton est visible par les visiteurs. Utilisez « Tester la connexion » pour vérifier une vraie réponse.', 'antoine-codex-immersive' );
				} else {
					esc_html_e( 'configuration incomplète — le panneau n’est visible que par les administrateurs.', 'antoine-codex-immersive' );
				}
				?>
			</p>
		</div>

		<form method="post" action="options.php">
			<?php settings_fields( 'acx_core' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Mode d’intégration', 'antoine-codex-immersive' ); ?></th>
					<td>
						<?php if ( $locked( 'ACX_CORE_MODE' ) ) : ?>
							<p><code>ACX_CORE_MODE</code> = <code><?php echo esc_html( $eff['mode'] ); ?></code> <?php esc_html_e( '(défini dans wp-config.php)', 'antoine-codex-immersive' ); ?></p>
						<?php endif; ?>
						<fieldset>
							<?php
							$modes = array(
								'off'      => __( 'Désactivé', 'antoine-codex-immersive' ),
								'proxy'    => __( 'Passerelle HTTP — WordPress relaie les messages vers l’API de CORE (clé gardée côté serveur)', 'antoine-codex-immersive' ),
								'hook'     => __( 'Extension PHP — CORE est une extension WordPress qui répond via le filtre antoine_codex_core_reply', 'antoine-codex-immersive' ),
								'external' => __( 'Widget existant — CORE a déjà son propre script ; le thème ouvre ce widget', 'antoine-codex-immersive' ),
							);
							foreach ( $modes as $value => $label ) :
								?>
								<label style="display:block;margin:.3em 0"><input type="radio" name="<?php echo esc_attr( $name ); ?>[mode]" value="<?php echo esc_attr( $value ); ?>" <?php checked( $saved['mode'], $value ); ?>> <?php echo esc_html( $label ); ?></label>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr><th colspan="2"><h2 style="margin:0"><?php esc_html_e( 'Passerelle HTTP', 'antoine-codex-immersive' ); ?></h2></th></tr>
				<tr>
					<th scope="row"><label for="acx-endpoint"><?php esc_html_e( 'URL de l’API CORE', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<?php if ( $locked( 'ACX_CORE_ENDPOINT' ) ) : ?>
							<p><?php esc_html_e( 'Défini dans wp-config.php (ACX_CORE_ENDPOINT).', 'antoine-codex-immersive' ); ?></p>
						<?php else : ?>
							<input type="url" class="regular-text code" id="acx-endpoint" name="<?php echo esc_attr( $name ); ?>[endpoint]" value="<?php echo esc_attr( $saved['endpoint'] ); ?>" placeholder="https://core.exemple.fr/api/chat">
						<?php endif; ?>
						<p class="description"><?php esc_html_e( 'Adresse appelée par le serveur WordPress (jamais par le navigateur). Elle doit être joignable depuis votre hébergeur : une adresse locale (localhost, 192.168.x.x) ne fonctionne que si CORE tourne sur le même serveur ; sinon utilisez un tunnel sécurisé (Cloudflare Tunnel, Tailscale…).', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-format"><?php esc_html_e( 'Format de l’API', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<select id="acx-format" name="<?php echo esc_attr( $name ); ?>[format]">
							<option value="generic" <?php selected( $saved['format'], 'generic' ); ?>><?php esc_html_e( 'Générique JSON : { message, session_id, history, context } → { reply }', 'antoine-codex-immersive' ); ?></option>
							<option value="openai" <?php selected( $saved['format'], 'openai' ); ?>><?php esc_html_e( 'Compatible OpenAI : /v1/chat/completions (Ollama, LM Studio, vLLM…)', 'antoine-codex-immersive' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-model"><?php esc_html_e( 'Modèle (format OpenAI)', 'antoine-codex-immersive' ); ?></label></th>
					<td><input type="text" class="regular-text code" id="acx-model" name="<?php echo esc_attr( $name ); ?>[model]" value="<?php echo esc_attr( $saved['model'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-token"><?php esc_html_e( 'Clé d’API', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<?php if ( $locked( 'ACX_CORE_TOKEN' ) ) : ?>
							<p><?php esc_html_e( 'Définie dans wp-config.php (ACX_CORE_TOKEN) — recommandé.', 'antoine-codex-immersive' ); ?></p>
						<?php else : ?>
							<input type="password" class="regular-text code" id="acx-token" name="<?php echo esc_attr( $name ); ?>[token]" value="" autocomplete="new-password" placeholder="<?php echo $saved['token'] ? esc_attr__( '•••••••• (enregistrée — laisser vide pour conserver)', 'antoine-codex-immersive' ) : ''; ?>">
							<?php if ( $saved['token'] ) : ?>
								<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[token_clear]" value="1"> <?php esc_html_e( 'Effacer la clé enregistrée', 'antoine-codex-immersive' ); ?></label>
							<?php endif; ?>
							<p class="description"><?php esc_html_e( 'Jamais envoyée au navigateur. Pour plus de sécurité, préférez define( \'ACX_CORE_TOKEN\', \'…\' ); dans wp-config.php.', 'antoine-codex-immersive' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-auth"><?php esc_html_e( 'En-tête d’authentification', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="acx-auth" name="<?php echo esc_attr( $name ); ?>[auth_header]" value="<?php echo esc_attr( $saved['auth_header'] ); ?>">
						<p class="description"><?php esc_html_e( '« Authorization » envoie « Bearer <clé> ». Tout autre nom (ex. X-API-Key) envoie la clé brute.', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-system"><?php esc_html_e( 'Instructions système (facultatif)', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<textarea class="large-text code" rows="5" id="acx-system" name="<?php echo esc_attr( $name ); ?>[system_prompt]"><?php echo esc_textarea( $saved['system_prompt'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'À renseigner uniquement si la personnalité et les règles de CORE ne sont pas déjà définies côté serveur CORE. Conservé côté serveur, jamais exposé.', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-timeout"><?php esc_html_e( 'Délai maximal (s)', 'antoine-codex-immersive' ); ?></label></th>
					<td><input type="number" min="5" max="120" id="acx-timeout" name="<?php echo esc_attr( $name ); ?>[timeout]" value="<?php echo esc_attr( (string) $saved['timeout'] ); ?>"></td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-rate"><?php esc_html_e( 'Messages max. par visiteur / 10 min', 'antoine-codex-immersive' ); ?></label></th>
					<td><input type="number" min="1" max="500" id="acx-rate" name="<?php echo esc_attr( $name ); ?>[rate_limit]" value="<?php echo esc_attr( (string) $saved['rate_limit'] ); ?>"></td>
				</tr>
				<tr><th colspan="2"><h2 style="margin:0"><?php esc_html_e( 'Widget existant', 'antoine-codex-immersive' ); ?></h2></th></tr>
				<tr>
					<th scope="row"><label for="acx-extjs"><?php esc_html_e( 'Fonction JavaScript d’ouverture', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="acx-extjs" name="<?php echo esc_attr( $name ); ?>[external_js]" value="<?php echo esc_attr( $saved['external_js'] ); ?>" placeholder="AntoineCodexCore.open">
						<p class="description"><?php esc_html_e( 'Nom de la fonction globale exposée par le widget CORE existant (appelée sans argument).', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-extsel"><?php esc_html_e( 'Ou : sélecteur du bouton existant', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<input type="text" class="regular-text code" id="acx-extsel" name="<?php echo esc_attr( $name ); ?>[external_selector]" value="<?php echo esc_attr( $saved['external_selector'] ); ?>" placeholder="#core-chat-launcher">
						<p class="description"><?php esc_html_e( 'Le thème clique sur cet élément pour ouvrir le widget, et le masque au profit de son propre bouton.', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
				<tr><th colspan="2"><h2 style="margin:0"><?php esc_html_e( 'Interface', 'antoine-codex-immersive' ); ?></h2></th></tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Bouton flottant', 'antoine-codex-immersive' ); ?></th>
					<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[show_launcher]" value="1" <?php checked( (int) $saved['show_launcher'], 1 ); ?>> <?php esc_html_e( 'Afficher le bouton « ANTOINE CODEX CORE » sur toutes les pages', 'antoine-codex-immersive' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><label for="acx-sugg"><?php esc_html_e( 'Questions suggérées', 'antoine-codex-immersive' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="4" id="acx-sugg" name="<?php echo esc_attr( $name ); ?>[suggestions]"><?php echo esc_textarea( $saved['suggestions'] ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Une par ligne (4 maximum). Vide = suggestions par défaut.', 'antoine-codex-immersive' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>

		<h2><?php esc_html_e( 'Tester la connexion', 'antoine-codex-immersive' ); ?></h2>
		<p><?php esc_html_e( 'Envoie un vrai message de test à CORE depuis le serveur, avec les réglages enregistrés.', 'antoine-codex-immersive' ); ?></p>
		<p><button type="button" class="button button-secondary" id="acx-core-test"><?php esc_html_e( 'Tester la connexion', 'antoine-codex-immersive' ); ?></button></p>
		<pre id="acx-core-test-result" style="white-space:pre-wrap;max-width:60em;background:#fff;border:1px solid #ccd0d4;padding:1em;display:none"></pre>
		<script>
		( function () {
			var btn = document.getElementById( 'acx-core-test' );
			var out = document.getElementById( 'acx-core-test-result' );
			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				out.style.display = 'block';
				out.textContent = '…';
				fetch( <?php echo wp_json_encode( esc_url_raw( rest_url( 'antoine-codex/v1/core/test' ) ) ); ?>, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'X-WP-Nonce': <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?> }
				} ).then( function ( r ) { return r.json(); } ).then( function ( d ) {
					out.textContent = ( d.ok ? 'OK' : 'ÉCHEC' ) + ( d.ms ? ' (' + d.ms + ' ms)' : '' ) + '\n' + ( d.message || JSON.stringify( d ) );
				} ).catch( function ( e ) {
					out.textContent = 'ÉCHEC ' + e;
				} ).finally( function () { btn.disabled = false; } );
			} );
		} )();
		</script>

		<h2><?php esc_html_e( 'API JavaScript du panneau', 'antoine-codex-immersive' ); ?></h2>
		<p><?php esc_html_e( 'Pour relier votre Quick Diagnostic existant : à la fin du questionnaire, déclenchez l’événement suivant. Le panneau propose alors d’analyser le résultat avec CORE (résultat transmis dans context.diagnostic).', 'antoine-codex-immersive' ); ?></p>
		<pre class="code" style="background:#fff;border:1px solid #ccd0d4;padding:1em;max-width:60em">document.dispatchEvent( new CustomEvent( 'acx:quick-diagnostic:complete', {
  detail: { summary: 'Texte ou objet JSON du résultat', open: true }
} ) );

// Autres commandes :
window.ACXCore.open();               // ouvrir
window.ACXCore.close();              // fermer
window.ACXCore.send( 'Bonjour' );    // envoyer un message
// Tout lien href="#antoine-codex-core" ou élément [data-acx-core-open] ouvre le panneau.</pre>
	</div>
	<?php
}

/**
 * Lien vers le diagnostic de migration juste après l'activation.
 */
function acx_activation_notice() {
	if ( ! current_user_can( 'manage_options' ) || get_option( 'acx_migration_seen' ) ) {
		return;
	}
	$screen = get_current_screen();
	if ( $screen && 'appearance_page_acx-migration' === $screen->id ) {
		update_option( 'acx_migration_seen', 1, false );
		return;
	}
	?>
	<div class="notice notice-info">
		<p>
			<strong>ANTOINE CODEX Immersive</strong> —
			<?php esc_html_e( 'Avant de publier, consultez le diagnostic de migration : il liste les fonctions de votre ancien thème (codes courts, routes API, gabarits) qui doivent être conservées.', 'antoine-codex-immersive' ); ?>
			<a class="button button-primary" style="margin-left:.5em" href="<?php echo esc_url( admin_url( 'themes.php?page=acx-migration' ) ); ?>"><?php esc_html_e( 'Ouvrir le diagnostic', 'antoine-codex-immersive' ); ?></a>
		</p>
	</div>
	<?php
}
add_action( 'admin_notices', 'acx_activation_notice' );

/**
 * Mémorise l'ancien thème à l'activation (WordPress efface « theme_switched » juste après).
 *
 * @param string        $old_name  Nom de l'ancien thème.
 * @param WP_Theme|bool $old_theme Ancien thème.
 */
function acx_after_switch( $old_name, $old_theme = false ) {
	delete_option( 'acx_migration_seen' );
	if ( $old_theme instanceof WP_Theme && $old_theme->get_stylesheet() !== get_stylesheet() ) {
		update_option( 'acx_previous_theme', $old_theme->get_stylesheet(), false );
	}
}
add_action( 'after_switch_theme', 'acx_after_switch', 10, 2 );

/**
 * Analyse le code d'un thème (ancien thème) : codes courts, routes, AJAX, scripts, gabarits.
 *
 * @param string $dir Dossier du thème.
 * @return array
 */
function acx_scan_theme_dir( $dir ) {
	$found = array(
		'shortcodes' => array(),
		'rest'       => array(),
		'ajax'       => array(),
		'post_types' => array(),
		'scripts'    => array(),
		'templates'  => array(),
		'keywords'   => array(),
		'files'      => 0,
	);
	if ( ! $dir || ! is_dir( $dir ) ) {
		return $found;
	}

	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		/** @var SplFileInfo $file */
		$ext = strtolower( $file->getExtension() );
		if ( ! in_array( $ext, array( 'php', 'js' ), true ) || $file->getSize() > 1024 * 1024 ) {
			continue;
		}
		$path = $file->getPathname();
		if ( false !== strpos( $path, '/node_modules/' ) || false !== strpos( $path, '/vendor/' ) ) {
			continue;
		}
		++$found['files'];
		$rel  = ltrim( str_replace( $dir, '', $path ), '/\\' );
		$code = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( 'php' === $ext ) {
			if ( preg_match_all( '/add_shortcode\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $m ) ) {
				foreach ( $m[1] as $v ) {
					$found['shortcodes'][ $v ] = $rel;
				}
			}
			if ( preg_match_all( '/register_rest_route\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]/', $code, $m, PREG_SET_ORDER ) ) {
				foreach ( $m as $v ) {
					$found['rest'][ '/' . trim( $v[1], '/' ) . '/' . ltrim( $v[2], '/' ) ] = $rel;
				}
			}
			if ( preg_match_all( '/[\'"]wp_ajax_(nopriv_)?([A-Za-z0-9_\-]+)[\'"]/', $code, $m ) ) {
				foreach ( $m[2] as $v ) {
					$found['ajax'][ $v ] = $rel;
				}
			}
			if ( preg_match_all( '/register_post_type\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $m ) ) {
				foreach ( $m[1] as $v ) {
					$found['post_types'][ $v ] = $rel;
				}
			}
			if ( preg_match_all( '/wp_(?:enqueue|register)_script\s*\(\s*[\'"]([^\'"]+)[\'"]/', $code, $m ) ) {
				foreach ( $m[1] as $v ) {
					$found['scripts'][ $v ] = $rel;
				}
			}
			if ( preg_match( '/Template Name:\s*(.+)/', $code, $m ) ) {
				$found['templates'][ $rel ] = trim( preg_replace( '/\*\/.*$/', '', $m[1] ) );
			}
		}

		foreach ( array( 'codex core', 'antoine_codex_core', 'antoinecodexcore', 'quick diagnostic', 'quick_diagnostic', 'chatbot', 'openai', 'ollama', 'anthropic', 'mistral', 'polylang', 'pll_', 'wpml' ) as $kw ) {
			if ( false !== stripos( $code, $kw ) ) {
				$found['keywords'][ $kw ][] = $rel;
			}
		}
	}
	foreach ( $found['keywords'] as $kw => $files ) {
		$found['keywords'][ $kw ] = array_values( array_unique( $files ) );
	}
	return $found;
}

/**
 * Codes courts et blocs utilisés dans le contenu publié mais non disponibles.
 *
 * @return array
 */
function acx_scan_content() {
	global $wpdb;
	$result = array(
		'missing_shortcodes' => array(),
		'present_shortcodes' => array(),
		'missing_blocks'     => array(),
		'orphan_templates'   => array(),
	);

	// Contenus visibles uniquement (les formulaires internes d'extensions, ex. Contact Form 7, sont exclus).
	$types = array_values( array_diff( array_merge( get_post_types( array( 'public' => true ) ), array( 'wp_block', 'wp_template', 'wp_template_part' ) ), array( 'attachment' ) ) );
	$in    = implode( ',', array_fill( 0, count( $types ), '%s' ) );
	// $in ne contient que des marqueurs %s générés ci-dessus.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_title, post_type, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','private','draft') AND post_type IN ($in) LIMIT 2000", $types ) );

	$registry = class_exists( 'WP_Block_Type_Registry' ) ? WP_Block_Type_Registry::get_instance() : null;

	foreach ( $rows as $row ) {
		$content = (string) $row->post_content;
		if ( preg_match_all( '/\[([A-Za-z][A-Za-z0-9_\-]*)[\s\]\/]/', $content, $m ) ) {
			foreach ( array_unique( $m[1] ) as $tag ) {
				$bucket = shortcode_exists( $tag ) ? 'present_shortcodes' : 'missing_shortcodes';
				$result[ $bucket ][ $tag ][ $row->ID ] = $row->post_title;
			}
		}
		if ( $registry && preg_match_all( '/<!--\s+wp:([a-z0-9\-]+\/[a-z0-9\-]+)/', $content, $m ) ) {
			foreach ( array_unique( $m[1] ) as $block ) {
				if ( ! $registry->is_registered( $block ) ) {
					$result['missing_blocks'][ $block ][ $row->ID ] = $row->post_title;
				}
			}
		}
	}

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$tpls = $wpdb->get_results( "SELECT p.ID, p.post_title, m.meta_value FROM {$wpdb->postmeta} m JOIN {$wpdb->posts} p ON p.ID = m.post_id WHERE m.meta_key = '_wp_page_template' AND m.meta_value NOT IN ('', 'default') AND p.post_status IN ('publish','private','draft')" );
	foreach ( $tpls as $t ) {
		if ( ! locate_template( $t->meta_value ) && ! in_array( $t->meta_value, array( 'elementor_canvas', 'elementor_header_footer' ), true ) ) {
			$result['orphan_templates'][ $t->ID ] = array( $t->post_title, $t->meta_value );
		}
	}

	return $result;
}

/**
 * Page « Diagnostic de migration ».
 */
function acx_migration_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	update_option( 'acx_migration_seen', 1, false );

	$previous = get_option( 'acx_previous_theme', '' );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture seule.
	if ( isset( $_GET['acx_scan'] ) ) {
		$requested = sanitize_text_field( wp_unslash( $_GET['acx_scan'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( wp_get_theme( $requested )->exists() ) {
			$previous = $requested;
		}
	}
	$prev_dir = '';
	$prev_obj = null;
	if ( $previous ) {
		$prev_obj = wp_get_theme( $previous );
		if ( $prev_obj->exists() ) {
			$prev_dir = $prev_obj->get_stylesheet_directory();
		}
	}
	$scan    = acx_scan_theme_dir( $prev_dir );
	$content = acx_scan_content();

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$all_plugins = get_plugins();
	$active      = (array) get_option( 'active_plugins', array() );

	$categories = array(
		'SEO'            => '/seo|rank-?math|yoast|aioseo|seopress|slim/i',
		'Multilingue'    => '/polylang|wpml|sitepress|translatepress|weglot|gtranslate/i',
		'Formulaires'    => '/contact-form|wpforms|gravity|fluentform|formidable|ninja-forms|forminator/i',
		'IA / chatbot'   => '/codex|core|chat|bot|ai-|openai|gpt|assistant|diagnostic/i',
		'Constructeur'   => '/elementor|beaver|divi|oxygen|bricks|brizy|kadence-blocks|spectra|stackable/i',
		'Cache'          => '/cache|rocket|litespeed|autoptimize|perfmatters|sg-cachepress|w3-total/i',
	);

	$front_id    = (int) get_option( 'page_on_front' );
	$front_chars = $front_id ? strlen( trim( wp_strip_all_tags( (string) get_post_field( 'post_content', $front_id ) ) ) ) : 0;

	$roles = array(
		'diagnostic' => __( 'Diagnostic d’entreprise & Stratégie de croissance', 'antoine-codex-immersive' ),
		'services'   => __( 'Services', 'antoine-codex-immersive' ),
		'method'     => __( 'Méthodologie', 'antoine-codex-immersive' ),
		'founder'    => __( 'Fondateur', 'antoine-codex-immersive' ),
		'contact'    => __( 'Contact', 'antoine-codex-immersive' ),
		'quick'      => __( 'Quick Diagnostic', 'antoine-codex-immersive' ),
	);
	?>
	<div class="wrap acx-admin">
		<h1><?php esc_html_e( 'Diagnostic de migration', 'antoine-codex-immersive' ); ?></h1>
		<p><?php esc_html_e( 'Ce rapport compare votre site à ce nouveau thème. Les fonctions fournies par des extensions continuent de fonctionner ; celles qui étaient codées dans l’ancien thème doivent être déplacées dans une extension (mu-plugin) avant la mise en ligne. Réalisez cette vérification sur une copie de préproduction.', 'antoine-codex-immersive' ); ?></p>

		<h2>1. <?php esc_html_e( 'Ancien thème', 'antoine-codex-immersive' ); ?></h2>
		<form method="get" style="margin:0 0 1em">
			<input type="hidden" name="page" value="acx-migration">
			<label for="acx-scan"><?php esc_html_e( 'Thème à analyser :', 'antoine-codex-immersive' ); ?></label>
			<select name="acx_scan" id="acx-scan">
				<?php foreach ( wp_get_themes() as $slug => $theme ) : ?>
					<?php if ( get_stylesheet() === $slug ) { continue; } ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $previous, $slug ); ?>><?php echo esc_html( $theme->get( 'Name' ) . ' (' . $slug . ')' ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Analyser', 'antoine-codex-immersive' ), 'secondary', '', false ); ?>
		</form>
		<?php if ( $prev_obj && $prev_obj->exists() ) : ?>
			<p><strong><?php echo esc_html( $prev_obj->get( 'Name' ) ); ?></strong> <code><?php echo esc_html( $previous ); ?></code> — <?php echo esc_html( sprintf( /* translators: %d: number of files. */ __( '%d fichiers PHP/JS analysés', 'antoine-codex-immersive' ), $scan['files'] ) ); ?></p>
			<?php
			$groups = array(
				'shortcodes' => __( 'Codes courts déclarés dans l’ancien thème (disparaissent avec le changement de thème)', 'antoine-codex-immersive' ),
				'rest'       => __( 'Routes API REST déclarées dans l’ancien thème', 'antoine-codex-immersive' ),
				'ajax'       => __( 'Actions AJAX déclarées dans l’ancien thème', 'antoine-codex-immersive' ),
				'post_types' => __( 'Types de contenu déclarés dans l’ancien thème', 'antoine-codex-immersive' ),
				'scripts'    => __( 'Scripts chargés par l’ancien thème', 'antoine-codex-immersive' ),
				'templates'  => __( 'Gabarits de page de l’ancien thème', 'antoine-codex-immersive' ),
			);
			foreach ( $groups as $key => $label ) :
				?>
				<h3><?php echo esc_html( $label ); ?> (<?php echo count( $scan[ $key ] ); ?>)</h3>
				<?php if ( $scan[ $key ] ) : ?>
					<table class="widefat striped" style="max-width:60em"><tbody>
					<?php foreach ( $scan[ $key ] as $k => $v ) : ?>
						<tr><td><code><?php echo esc_html( $k ); ?></code></td><td><?php echo esc_html( $v ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table>
				<?php else : ?>
					<p>—</p>
				<?php endif; ?>
			<?php endforeach; ?>
			<h3><?php esc_html_e( 'Mentions à vérifier (CORE, Quick Diagnostic, IA, multilingue)', 'antoine-codex-immersive' ); ?></h3>
			<?php if ( $scan['keywords'] ) : ?>
				<ul>
				<?php foreach ( $scan['keywords'] as $kw => $files ) : ?>
					<li><code><?php echo esc_html( $kw ); ?></code> → <?php echo esc_html( implode( ', ', array_slice( $files, 0, 12 ) ) ); ?></li>
				<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p>—</p>
			<?php endif; ?>
		<?php else : ?>
			<p><?php esc_html_e( 'Ancien thème introuvable (information disponible après un changement de thème, tant que l’ancien thème reste installé).', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>

		<h2>2. <?php esc_html_e( 'Contenu publié', 'antoine-codex-immersive' ); ?></h2>
		<h3><?php esc_html_e( 'Codes courts utilisés mais NON disponibles actuellement', 'antoine-codex-immersive' ); ?></h3>
		<?php if ( $content['missing_shortcodes'] ) : ?>
			<div class="notice notice-error inline"><p><?php esc_html_e( 'Ces codes courts s’affichent en texte brut : leur code provenait probablement de l’ancien thème ou d’une extension désactivée.', 'antoine-codex-immersive' ); ?></p></div>
			<table class="widefat striped" style="max-width:60em"><tbody>
			<?php foreach ( $content['missing_shortcodes'] as $tag => $posts ) : ?>
				<tr><td><code>[<?php echo esc_html( $tag ); ?>]</code></td><td>
				<?php foreach ( $posts as $pid => $title ) : ?>
					<a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( $title ? $title : '#' . $pid ); ?></a>&nbsp;
				<?php endforeach; ?>
				</td></tr>
			<?php endforeach; ?>
			</tbody></table>
		<?php else : ?>
			<p>✅ <?php esc_html_e( 'Aucun code court manquant.', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Codes courts utilisés et disponibles', 'antoine-codex-immersive' ); ?></h3>
		<p><?php echo $content['present_shortcodes'] ? '<code>[' . implode( ']</code> <code>[', array_map( 'esc_html', array_keys( $content['present_shortcodes'] ) ) ) . ']</code>' : '—'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé. ?></p>

		<h3><?php esc_html_e( 'Blocs non enregistrés', 'antoine-codex-immersive' ); ?></h3>
		<?php if ( $content['missing_blocks'] ) : ?>
			<ul>
			<?php foreach ( $content['missing_blocks'] as $block => $posts ) : ?>
				<li><code><?php echo esc_html( $block ); ?></code> — <?php echo esc_html( implode( ', ', $posts ) ); ?></li>
			<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p>✅ <?php esc_html_e( 'Aucun bloc manquant.', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Pages utilisant un gabarit de l’ancien thème', 'antoine-codex-immersive' ); ?></h3>
		<?php if ( $content['orphan_templates'] ) : ?>
			<p><?php esc_html_e( 'Ces pages s’affichent désormais avec le gabarit standard (ou le gabarit immersif si ce sont des pages clés). Vérifiez que rien n’était codé en dur dans l’ancien gabarit.', 'antoine-codex-immersive' ); ?></p>
			<ul>
			<?php foreach ( $content['orphan_templates'] as $pid => $info ) : ?>
				<li><a href="<?php echo esc_url( get_edit_post_link( $pid ) ); ?>"><?php echo esc_html( $info[0] ); ?></a> — <code><?php echo esc_html( $info[1] ); ?></code></li>
			<?php endforeach; ?>
			</ul>
		<?php else : ?>
			<p>✅ —</p>
		<?php endif; ?>

		<h2>3. <?php esc_html_e( 'Pages clés détectées', 'antoine-codex-immersive' ); ?></h2>
		<table class="widefat striped" style="max-width:60em"><tbody>
		<?php foreach ( $roles as $role => $label ) : ?>
			<?php $pid = acx_page_id( $role ); ?>
			<tr>
				<td><?php echo esc_html( $label ); ?></td>
				<td>
					<?php if ( $pid ) : ?>
						<a href="<?php echo esc_url( get_permalink( $pid ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ); ?></a>
					<?php elseif ( 'quick' === $role && acx_opt( 'quick_diag_url' ) ) : ?>
						<code><?php echo esc_html( acx_opt( 'quick_diag_url' ) ); ?></code>
					<?php else : ?>
						⚠️ <?php esc_html_e( 'non trouvée — à choisir dans Apparence → Personnaliser → ANTOINE CODEX → Pages clés', 'antoine-codex-immersive' ); ?>
					<?php endif; ?>
				</td>
			</tr>
		<?php endforeach; ?>
		</tbody></table>
		<?php if ( $front_chars > 0 && 'designed' === acx_opt( 'front_content_mode' ) ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of characters. */
						__( 'Votre page d’accueil statique contient %d caractères de contenu, actuellement non affichés (mode « Sections immersives »). Ils ne sont pas supprimés. Pour les afficher : Personnaliser → ANTOINE CODEX → Accueil — Ouverture.', 'antoine-codex-immersive' ),
						$front_chars
					)
				);
				?>
			</p></div>
		<?php endif; ?>

		<h2>4. <?php esc_html_e( 'Extensions actives', 'antoine-codex-immersive' ); ?></h2>
		<table class="widefat striped" style="max-width:60em"><tbody>
		<?php foreach ( $active as $plugin_file ) : ?>
			<?php
			$data = isset( $all_plugins[ $plugin_file ] ) ? $all_plugins[ $plugin_file ] : array( 'Name' => $plugin_file );
			$cats = array();
			foreach ( $categories as $cat => $re ) {
				if ( preg_match( $re, $plugin_file . ' ' . $data['Name'] ) ) {
					$cats[] = $cat;
				}
			}
			?>
			<tr><td><?php echo esc_html( $data['Name'] ); ?></td><td><code><?php echo esc_html( $plugin_file ); ?></code></td><td><?php echo esc_html( implode( ', ', $cats ) ); ?></td></tr>
		<?php endforeach; ?>
		</tbody></table>
		<p>
			<?php esc_html_e( 'SEO :', 'antoine-codex-immersive' ); ?>
			<?php echo acx_seo_plugin_active() ? esc_html__( 'extension détectée — le thème n’ajoute aucune métadonnée.', 'antoine-codex-immersive' ) : esc_html__( 'aucune extension détectée — le thème ajoute description, Open Graph et données structurées de base.', 'antoine-codex-immersive' ); ?>
			<br>
			<?php esc_html_e( 'Langues :', 'antoine-codex-immersive' ); ?>
			<?php
			$langs = acx_languages();
			echo $langs ? esc_html( implode( ' / ', wp_list_pluck( $langs, 'code' ) ) ) : esc_html__( 'aucune extension multilingue détectée — pas de sélecteur FR/EN.', 'antoine-codex-immersive' );
			?>
			<br>
			<?php esc_html_e( 'ANTOINE CODEX CORE :', 'antoine-codex-immersive' ); ?>
			<a href="<?php echo esc_url( admin_url( 'themes.php?page=acx-core' ) ); ?>"><?php echo acx_core_is_configured() ? esc_html__( 'configuré', 'antoine-codex-immersive' ) : esc_html__( 'non relié', 'antoine-codex-immersive' ); ?></a>
		</p>

		<h2>5. <?php esc_html_e( 'Emplacements de menus', 'antoine-codex-immersive' ); ?></h2>
		<?php $locations = get_nav_menu_locations(); ?>
		<ul>
		<?php foreach ( get_registered_nav_menus() as $loc => $label ) : ?>
			<li><?php echo esc_html( $label ); ?> : <?php echo ! empty( $locations[ $loc ] ) ? esc_html( wp_get_nav_menu_object( $locations[ $loc ] )->name ) : '⚠️ ' . esc_html__( 'aucun menu (menu automatique des pages clés)', 'antoine-codex-immersive' ); ?></li>
		<?php endforeach; ?>
		</ul>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'nav-menus.php?action=locations' ) ); ?>"><?php esc_html_e( 'Gérer les emplacements', 'antoine-codex-immersive' ); ?></a></p>
	</div>
	<?php
}
