<?php
/**
 * Formulaire de contact de secours [acx_contact_form].
 *
 * Il ne remplace PAS vos formulaires existants (Contact Form 7, WPForms, etc.) :
 * il n'apparaît que si vous l'activez dans le Personnaliseur ou insérez le code court.
 * Chaque message est envoyé par e-mail ET conservé dans l'administration
 * (menu « Messages »), pour qu'aucune demande ne soit perdue si l'e-mail échoue.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Le menu « Messages » n'apparaît que si le formulaire du thème est utilisé.
 *
 * @return bool
 */
function acx_messages_menu_visible() {
	if ( acx_opt( 'contact_form_enabled' ) ) {
		return true;
	}
	$count = get_transient( 'acx_message_count' );
	if ( false === $count ) {
		global $wpdb;
		$count = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = 'acx_message'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		set_transient( 'acx_message_count', $count, DAY_IN_SECONDS );
	}
	return $count > 0;
}

/**
 * Type de contenu privé pour archiver les messages.
 */
function acx_register_message_cpt() {
	register_post_type(
		'acx_message',
		array(
			'labels'              => array(
				'name'          => __( 'Messages', 'antoine-codex-immersive' ),
				'singular_name' => __( 'Message', 'antoine-codex-immersive' ),
			),
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => acx_messages_menu_visible(),
			'menu_icon'           => 'dashicons-email-alt',
			'menu_position'       => 26,
			'capability_type'     => 'post',
			'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
			'map_meta_cap'        => true,
			'supports'            => array( 'title', 'editor' ),
			'exclude_from_search' => true,
			'show_in_rest'        => false,
		)
	);
}
add_action( 'init', 'acx_register_message_cpt' );

/**
 * Sujets proposés.
 *
 * @return array
 */
function acx_contact_subjects() {
	$subjects = array( 'general' => __( 'Demande générale', 'antoine-codex-immersive' ) );
	foreach ( acx_services() as $slug => $service ) {
		$subjects[ $slug ] = $service['title'];
	}
	return $subjects;
}

/**
 * Rendu du formulaire.
 *
 * @return string
 */
function acx_contact_form_shortcode() {
	$status = isset( $_GET['acx_contact'] ) ? sanitize_key( wp_unslash( $_GET['acx_contact'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- affichage seulement.
	$action = admin_url( 'admin-post.php' );
	$uid    = wp_unique_id( 'acx-cf-' );

	ob_start();
	?>
	<form class="acx-form" action="<?php echo esc_url( $action ); ?>" method="post" id="contact-form">
		<?php if ( 'sent' === $status ) : ?>
			<p class="acx-form__notice acx-form__notice--ok" role="status"><?php esc_html_e( 'Merci, votre message a bien été envoyé. Nous vous répondons dans les meilleurs délais.', 'antoine-codex-immersive' ); ?></p>
		<?php elseif ( 'invalid' === $status ) : ?>
			<p class="acx-form__notice acx-form__notice--error" role="alert"><?php esc_html_e( 'Merci de vérifier les champs obligatoires (nom, e-mail valide, message et consentement).', 'antoine-codex-immersive' ); ?></p>
		<?php elseif ( 'error' === $status ) : ?>
			<p class="acx-form__notice acx-form__notice--error" role="alert"><?php esc_html_e( 'Le message n’a pas pu être envoyé. Merci de réessayer dans quelques instants.', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>

		<input type="hidden" name="action" value="acx_contact">
		<input type="hidden" name="acx_ts" value="<?php echo esc_attr( (string) time() ); ?>">
		<input type="hidden" name="acx_back" value="<?php echo esc_url( get_permalink() ? get_permalink() : home_url( '/' ) ); ?>">
		<div class="acx-form__hp" aria-hidden="true">
			<label for="<?php echo esc_attr( $uid ); ?>-website">Website</label>
			<input type="text" name="acx_website" id="<?php echo esc_attr( $uid ); ?>-website" tabindex="-1" autocomplete="off">
		</div>

		<div class="acx-form__grid">
			<p class="acx-field">
				<label for="<?php echo esc_attr( $uid ); ?>-name"><?php esc_html_e( 'Nom et prénom', 'antoine-codex-immersive' ); ?> <span aria-hidden="true">*</span></label>
				<input type="text" name="acx_name" id="<?php echo esc_attr( $uid ); ?>-name" required autocomplete="name" maxlength="120">
			</p>
			<p class="acx-field">
				<label for="<?php echo esc_attr( $uid ); ?>-company"><?php esc_html_e( 'Entreprise', 'antoine-codex-immersive' ); ?></label>
				<input type="text" name="acx_company" id="<?php echo esc_attr( $uid ); ?>-company" autocomplete="organization" maxlength="160">
			</p>
			<p class="acx-field">
				<label for="<?php echo esc_attr( $uid ); ?>-email"><?php esc_html_e( 'E-mail', 'antoine-codex-immersive' ); ?> <span aria-hidden="true">*</span></label>
				<input type="email" name="acx_email" id="<?php echo esc_attr( $uid ); ?>-email" required autocomplete="email" maxlength="160">
			</p>
			<p class="acx-field">
				<label for="<?php echo esc_attr( $uid ); ?>-phone"><?php esc_html_e( 'Téléphone', 'antoine-codex-immersive' ); ?></label>
				<input type="tel" name="acx_phone" id="<?php echo esc_attr( $uid ); ?>-phone" autocomplete="tel" maxlength="40">
			</p>
			<p class="acx-field acx-field--full">
				<label for="<?php echo esc_attr( $uid ); ?>-subject"><?php esc_html_e( 'Sujet', 'antoine-codex-immersive' ); ?></label>
				<select name="acx_subject" id="<?php echo esc_attr( $uid ); ?>-subject">
					<?php foreach ( acx_contact_subjects() as $key => $label ) : ?>
						<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p class="acx-field acx-field--full">
				<label for="<?php echo esc_attr( $uid ); ?>-message"><?php esc_html_e( 'Votre message', 'antoine-codex-immersive' ); ?> <span aria-hidden="true">*</span></label>
				<textarea name="acx_message" id="<?php echo esc_attr( $uid ); ?>-message" rows="6" required maxlength="5000"></textarea>
			</p>
			<p class="acx-field acx-field--full acx-field--check">
				<input type="checkbox" name="acx_consent" id="<?php echo esc_attr( $uid ); ?>-consent" value="1" required>
				<label for="<?php echo esc_attr( $uid ); ?>-consent">
					<?php esc_html_e( 'J’accepte que mes données soient utilisées pour répondre à ma demande.', 'antoine-codex-immersive' ); ?>
					<?php
					$privacy = get_privacy_policy_url();
					if ( $privacy ) {
						echo ' <a href="' . esc_url( $privacy ) . '">' . esc_html__( 'Politique de confidentialité', 'antoine-codex-immersive' ) . '</a>';
					}
					?>
					<span aria-hidden="true">*</span>
				</label>
			</p>
		</div>
		<p class="acx-form__actions">
			<button type="submit" class="btn btn--primary" data-magnetic><span class="btn__label"><?php esc_html_e( 'Envoyer le message', 'antoine-codex-immersive' ); ?></span><span class="btn__icon"><?php acx_the_icon( 'arrow' ); ?></span></button>
			<span class="mono acx-form__required"><?php esc_html_e( '* Champs obligatoires', 'antoine-codex-immersive' ); ?></span>
		</p>
	</form>
	<?php
	return ob_get_clean();
}
add_shortcode( 'acx_contact_form', 'acx_contact_form_shortcode' );

/**
 * Adresse IP du visiteur (pour la limitation de débit uniquement, hachée).
 *
 * @return string
 */
function acx_client_fingerprint() {
	$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
	// Derrière un CDN / proxy (ex. Cloudflare), fournir la vraie IP via ce filtre.
	$ip = (string) apply_filters( 'acx_client_ip', $ip );
	return substr( hash_hmac( 'sha256', $ip, wp_salt( 'nonce' ) ), 0, 32 );
}

/**
 * Limitation de débit simple par visiteur.
 *
 * @param string $bucket Nom du compteur.
 * @param int    $max    Nombre maximal.
 * @param int    $window Fenêtre en secondes.
 * @return bool Vrai si la requête est autorisée.
 */
function acx_rate_limit( $bucket, $max, $window ) {
	$key   = 'acx_rl_' . $bucket . '_' . acx_client_fingerprint();
	$count = (int) get_transient( $key );
	if ( $count >= $max ) {
		return false;
	}
	set_transient( $key, $count + 1, $window );
	return true;
}

/**
 * Traitement du formulaire.
 * Protection : champ piège, délai minimal, limitation de débit (pas de nonce afin
 * de rester compatible avec la mise en cache des pages).
 */
function acx_handle_contact() {
	// phpcs:disable WordPress.Security.NonceVerification.Missing
	$back = isset( $_POST['acx_back'] ) ? esc_url_raw( wp_unslash( $_POST['acx_back'] ) ) : home_url( '/' );
	$back = wp_validate_redirect( $back, home_url( '/' ) );

	$redirect = function ( $status ) use ( $back ) {
		wp_safe_redirect( add_query_arg( 'acx_contact', $status, $back ) . '#contact-form' );
		exit;
	};

	$honeypot = isset( $_POST['acx_website'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['acx_website'] ) ) ) : '';
	$ts       = isset( $_POST['acx_ts'] ) ? absint( $_POST['acx_ts'] ) : 0;
	if ( '' !== $honeypot || ( time() - $ts ) < 3 ) {
		$redirect( 'sent' ); // Robot : on ne signale rien.
	}
	if ( ! acx_rate_limit( 'contact', 5, HOUR_IN_SECONDS ) ) {
		$redirect( 'error' );
	}

	$name    = isset( $_POST['acx_name'] ) ? sanitize_text_field( wp_unslash( $_POST['acx_name'] ) ) : '';
	$company = isset( $_POST['acx_company'] ) ? sanitize_text_field( wp_unslash( $_POST['acx_company'] ) ) : '';
	$email   = isset( $_POST['acx_email'] ) ? sanitize_email( wp_unslash( $_POST['acx_email'] ) ) : '';
	$phone   = isset( $_POST['acx_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['acx_phone'] ) ) : '';
	$subject = isset( $_POST['acx_subject'] ) ? sanitize_key( wp_unslash( $_POST['acx_subject'] ) ) : 'general';
	$message = isset( $_POST['acx_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['acx_message'] ) ) : '';
	$consent = ! empty( $_POST['acx_consent'] );
	// phpcs:enable

	if ( '' === $name || ! is_email( $email ) || '' === $message || ! $consent ) {
		$redirect( 'invalid' );
	}

	$subjects      = acx_contact_subjects();
	$subject_label = isset( $subjects[ $subject ] ) ? $subjects[ $subject ] : $subjects['general'];

	$body  = sprintf( "%s : %s\n", __( 'Nom', 'antoine-codex-immersive' ), $name );
	$body .= $company ? sprintf( "%s : %s\n", __( 'Entreprise', 'antoine-codex-immersive' ), $company ) : '';
	$body .= sprintf( "%s : %s\n", __( 'E-mail', 'antoine-codex-immersive' ), $email );
	$body .= $phone ? sprintf( "%s : %s\n", __( 'Téléphone', 'antoine-codex-immersive' ), $phone ) : '';
	$body .= sprintf( "%s : %s\n\n%s\n", __( 'Sujet', 'antoine-codex-immersive' ), $subject_label, $message );

	$title = sprintf( '[%s] %s — %s', get_bloginfo( 'name' ), $subject_label, $name );

	$stored = wp_insert_post(
		array(
			'post_type'    => 'acx_message',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_content' => $body,
		),
		true
	);

	delete_transient( 'acx_message_count' );

	$to     = sanitize_email( (string) acx_opt( 'contact_email' ) );
	$to     = $to ? $to : get_option( 'admin_email' );
	$mailed = wp_mail( $to, $title, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

	if ( is_wp_error( $stored ) && ! $mailed ) {
		$redirect( 'error' );
	}
	$redirect( 'sent' );
}
add_action( 'admin_post_nopriv_acx_contact', 'acx_handle_contact' );
add_action( 'admin_post_acx_contact', 'acx_handle_contact' );
