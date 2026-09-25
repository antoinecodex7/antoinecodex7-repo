<?php
/**
 * Composants d'affichage réutilisables.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Bouton / lien d'action.
 *
 * @param string $label   Libellé.
 * @param string $url     Destination.
 * @param string $variant primary | ghost | text.
 * @param array  $attrs   Attributs supplémentaires.
 */
function acx_button( $label, $url, $variant = 'primary', $attrs = array() ) {
	$attr_html = '';
	foreach ( $attrs as $name => $value ) {
		$attr_html .= ' ' . esc_attr( $name ) . '="' . esc_attr( $value ) . '"';
	}
	$magnetic = 'text' === $variant ? '' : ' data-magnetic';
	printf(
		'<a class="btn btn--%1$s" href="%2$s"%3$s%4$s><span class="btn__label">%5$s</span><span class="btn__icon">%6$s</span></a>',
		esc_attr( $variant ),
		esc_url( $url ),
		$magnetic, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- constante.
		$attr_html, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus.
		esc_html( $label ),
		wp_kses( acx_icon( 'arrow' ), acx_kses_svg() )
	);
}

/**
 * En-tête de section : index + étiquette + titre + introduction.
 *
 * @param array $args index, label, title, lead, id, tag, class.
 */
function acx_section_head( $args ) {
	$args = wp_parse_args(
		$args,
		array(
			'index' => '',
			'label' => '',
			'title' => '',
			'lead'  => '',
			'id'    => '',
			'tag'   => 'h2',
			'class' => '',
		)
	);
	$tag = in_array( $args['tag'], array( 'h1', 'h2', 'h3' ), true ) ? $args['tag'] : 'h2';
	?>
	<header class="section-head <?php echo esc_attr( $args['class'] ); ?>">
		<?php if ( $args['index'] || $args['label'] ) : ?>
			<p class="section-head__meta mono" data-reveal>
				<?php if ( $args['index'] ) : ?><span class="section-head__index"><?php echo esc_html( $args['index'] ); ?></span><?php endif; ?>
				<?php if ( $args['label'] ) : ?><span class="section-head__label"><?php echo esc_html( $args['label'] ); ?></span><?php endif; ?>
			</p>
		<?php endif; ?>
		<<?php echo esc_html( $tag ); ?> class="section-head__title"<?php echo $args['id'] ? ' id="' . esc_attr( $args['id'] ) . '"' : ''; ?> data-split><?php echo esc_html( $args['title'] ); ?></<?php echo esc_html( $tag ); ?>>
		<?php if ( $args['lead'] ) : ?>
			<p class="section-head__lead" data-reveal><?php echo esc_html( $args['lead'] ); ?></p>
		<?php endif; ?>
	</header>
	<?php
}

/**
 * Ouverture des pages intérieures.
 *
 * @param array $args title, lead, label, field (bool), class.
 */
function acx_page_hero( $args = array() ) {
	$post_id = get_queried_object_id();
	$args    = wp_parse_args(
		$args,
		array(
			'title' => is_singular() ? get_the_title( $post_id ) : '',
			'lead'  => is_singular() && has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : '',
			'label' => '',
			'field' => true,
			'class' => '',
			'extra' => '',
		)
	);
	?>
	<section class="page-hero <?php echo esc_attr( $args['class'] ); ?>" data-hero>
		<?php if ( $args['field'] ) : ?>
			<div class="page-hero__visual" aria-hidden="true">
				<div class="field-poster field-poster--lite"></div>
				<canvas class="field-canvas" data-acx-field="lite"></canvas>
			</div>
		<?php endif; ?>
		<div class="page-hero__inner container">
			<?php acx_breadcrumb( $args['label'] ); ?>
			<h1 class="page-hero__title" data-split><?php echo esc_html( $args['title'] ); ?></h1>
			<?php if ( $args['lead'] ) : ?>
				<p class="page-hero__lead" data-reveal><?php echo esc_html( wp_strip_all_tags( $args['lead'] ) ); ?></p>
			<?php endif; ?>
			<?php
			if ( $args['extra'] ) {
				echo wp_kses_post( $args['extra'] );
			}
			?>
		</div>
		<div class="page-hero__rule" aria-hidden="true"></div>
	</section>
	<?php
}

/**
 * Fil d'Ariane minimal (monospace).
 *
 * @param string $label Étiquette de la page courante (optionnelle).
 */
function acx_breadcrumb( $label = '' ) {
	// Fil d'Ariane d'une extension SEO si disponible.
	if ( function_exists( 'yoast_breadcrumb' ) && current_theme_supports( 'yoast-seo-breadcrumbs' ) ) {
		yoast_breadcrumb( '<nav class="breadcrumb mono" aria-label="' . esc_attr__( 'Fil d’Ariane', 'antoine-codex-immersive' ) . '">', '</nav>' );
		return;
	}

	$items   = array();
	$items[] = '<a href="' . esc_url( home_url( '/' ) ) . '">ANTOINE CODEX</a>';

	if ( is_page() ) {
		$ancestors = array_reverse( get_post_ancestors( get_queried_object_id() ) );
		foreach ( $ancestors as $ancestor ) {
			$items[] = '<a href="' . esc_url( get_permalink( $ancestor ) ) . '">' . esc_html( get_the_title( $ancestor ) ) . '</a>';
		}
	} elseif ( is_singular( 'post' ) ) {
		$blog = (int) get_option( 'page_for_posts' );
		if ( $blog ) {
			$items[] = '<a href="' . esc_url( get_permalink( $blog ) ) . '">' . esc_html( get_the_title( $blog ) ) . '</a>';
		}
	}

	$current = $label ? $label : ( is_singular() ? get_the_title() : wp_get_document_title() );
	$items[] = '<span aria-current="page">' . esc_html( wp_strip_all_tags( $current ) ) . '</span>';

	echo '<nav class="breadcrumb mono" aria-label="' . esc_attr__( 'Fil d’Ariane', 'antoine-codex-immersive' ) . '" data-reveal>' . implode( '<span class="breadcrumb__sep" aria-hidden="true">/</span>', $items ) . '</nav>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- éléments échappés.
}

/**
 * Carte d'expertise.
 *
 * @param string $slug    Identifiant.
 * @param array  $service Données.
 * @param string $heading Balise du titre.
 */
function acx_service_card( $slug, $service, $heading = 'h3' ) {
	$heading = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h3';
	?>
	<article class="service-card" id="service-<?php echo esc_attr( $slug ); ?>" data-tilt data-reveal>
		<div class="service-card__glow" aria-hidden="true"></div>
		<div class="service-card__top">
			<span class="service-card__index mono"><?php echo esc_html( $service['index'] ); ?> / <?php echo esc_html( str_pad( (string) count( acx_service_defaults() ), 2, '0', STR_PAD_LEFT ) ); ?></span>
			<span class="service-card__arrow"><?php acx_the_icon( 'arrow-ne' ); ?></span>
		</div>
		<<?php echo esc_html( $heading ); ?> class="service-card__title">
			<a class="service-card__link" href="<?php echo esc_url( $service['url'] ); ?>"><?php echo esc_html( $service['title'] ); ?></a>
		</<?php echo esc_html( $heading ); ?>>
		<p class="service-card__text"><?php echo esc_html( $service['text'] ); ?></p>
		<?php if ( $service['points'] ) : ?>
			<ul class="service-card__points mono">
				<?php foreach ( $service['points'] as $point ) : ?>
					<li><?php echo esc_html( $point ); ?></li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</article>
	<?php
}

/**
 * Grille des expertises.
 *
 * @param string $heading Balise des titres de cartes.
 */
function acx_services_grid( $heading = 'h3' ) {
	echo '<div class="services-grid">';
	foreach ( acx_services() as $slug => $service ) {
		acx_service_card( $slug, $service, $heading );
	}
	echo '</div>';
}

/**
 * Les trois portes d'entrée du diagnostic.
 *
 * @param string $context front | page.
 */
function acx_diagnostic_paths( $context = 'front' ) {
	$core_on      = acx_core_is_public();
	$is_diag_page = 'page' === $context;
	?>
	<div class="diag-paths<?php echo $core_on ? '' : ' diag-paths--two'; ?>">
		<article class="diag-path diag-path--quick" data-reveal>
			<p class="diag-path__meta mono"><span>A</span><?php esc_html_e( 'Quelques minutes', 'antoine-codex-immersive' ); ?></p>
			<h3 class="diag-path__title"><?php esc_html_e( 'Quick Diagnostic', 'antoine-codex-immersive' ); ?></h3>
			<p class="diag-path__text"><?php esc_html_e( 'Un questionnaire court pour une première lecture de votre situation et des pistes prioritaires.', 'antoine-codex-immersive' ); ?></p>
			<p class="diag-path__kind mono"><?php esc_html_e( 'Questionnaire · résultat immédiat', 'antoine-codex-immersive' ); ?></p>
			<?php acx_button( __( 'Faire le Quick Diagnostic', 'antoine-codex-immersive' ), acx_page_url( 'quick' ), 'ghost' ); ?>
		</article>

		<article class="diag-path diag-path--full" data-reveal>
			<p class="diag-path__meta mono"><span>B</span><?php esc_html_e( 'Analyse approfondie', 'antoine-codex-immersive' ); ?></p>
			<h3 class="diag-path__title"><?php esc_html_e( 'Diagnostic d’entreprise & Stratégie de croissance', 'antoine-codex-immersive' ); ?></h3>
			<p class="diag-path__text"><?php esc_html_e( 'Entretiens, données, organisation, offre et marché : une analyse complète pour construire une stratégie de croissance argumentée et une feuille de route.', 'antoine-codex-immersive' ); ?></p>
			<p class="diag-path__kind mono"><?php esc_html_e( 'Accompagnement · feuille de route', 'antoine-codex-immersive' ); ?></p>
			<?php
			if ( $is_diag_page ) {
				acx_button( __( 'Voir le détail', 'antoine-codex-immersive' ), '#diagnostic-contenu', 'primary' );
			} else {
				acx_button( __( 'Découvrir le diagnostic complet', 'antoine-codex-immersive' ), acx_page_url( 'diagnostic' ), 'primary' );
			}
			?>
		</article>

		<?php if ( $core_on ) : ?>
			<article class="diag-path diag-path--core" data-reveal>
				<p class="diag-path__meta mono"><span>C</span><?php esc_html_e( 'Conversation IA', 'antoine-codex-immersive' ); ?></p>
				<h3 class="diag-path__title">ANTOINE CODEX CORE</h3>
				<p class="diag-path__text"><?php esc_html_e( 'Posez vos questions librement à notre assistant IA : il vous répond et vous oriente vers la démarche adaptée. Une conversation, pas un questionnaire.', 'antoine-codex-immersive' ); ?></p>
				<p class="diag-path__kind mono"><?php esc_html_e( 'Échange libre · assistant IA', 'antoine-codex-immersive' ); ?></p>
				<button type="button" class="btn btn--core" data-acx-core-open data-magnetic>
					<span class="btn__label"><?php esc_html_e( 'Ouvrir la conversation', 'antoine-codex-immersive' ); ?></span>
					<span class="btn__icon"><?php acx_the_icon( 'spark' ); ?></span>
				</button>
			</article>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Méthode en trois temps.
 */
function acx_method_steps() {
	?>
	<ol class="method-steps" data-progress>
		<li class="method-steps__line" aria-hidden="true"><span></span></li>
		<?php for ( $i = 1; $i <= 3; $i++ ) : ?>
			<li class="method-step" data-reveal>
				<span class="method-step__num" aria-hidden="true"><?php echo esc_html( str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) ); ?></span>
				<div class="method-step__body">
					<h3 class="method-step__title"><?php echo esc_html( acx_text( 'method_' . $i . '_title' ) ); ?></h3>
					<p class="method-step__text"><?php echo esc_html( acx_text( 'method_' . $i . '_text' ) ); ?></p>
				</div>
			</li>
		<?php endfor; ?>
	</ol>
	<?php
}

/**
 * Coordonnées (uniquement les champs renseignés).
 *
 * @param string $class Classe CSS.
 * @return bool Vrai si au moins une coordonnée a été affichée.
 */
function acx_contact_details( $class = '' ) {
	$email   = sanitize_email( (string) acx_opt( 'contact_email' ) );
	$phone   = trim( (string) acx_opt( 'contact_phone' ) );
	$address = trim( acx_text( 'contact_address' ) );
	$hours   = trim( acx_text( 'contact_hours' ) );

	if ( ! $email && ! $phone && ! $address && ! $hours ) {
		return false;
	}
	?>
	<ul class="contact-details <?php echo esc_attr( $class ); ?>">
		<?php if ( $email ) : ?>
			<li><?php acx_the_icon( 'mail' ); ?><a href="<?php echo esc_url( 'mailto:' . antispambot( $email ) ); ?>"><?php echo esc_html( antispambot( $email ) ); ?></a></li>
		<?php endif; ?>
		<?php if ( $phone ) : ?>
			<li><?php acx_the_icon( 'phone' ); ?><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a></li>
		<?php endif; ?>
		<?php if ( $address ) : ?>
			<li><?php acx_the_icon( 'pin' ); ?><span><?php echo nl2br( esc_html( $address ) ); ?></span></li>
		<?php endif; ?>
		<?php if ( $hours ) : ?>
			<li><?php acx_the_icon( 'clock' ); ?><span><?php echo esc_html( $hours ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php
	return true;
}

/**
 * Liens vers les réseaux sociaux renseignés.
 */
function acx_social_links() {
	$nets  = array(
		'linkedin'  => 'LinkedIn',
		'instagram' => 'Instagram',
		'facebook'  => 'Facebook',
		'youtube'   => 'YouTube',
	);
	$links = array();
	foreach ( $nets as $key => $label ) {
		$url = acx_opt( 'social_' . $key );
		if ( $url ) {
			$links[] = '<li><a href="' . esc_url( $url ) . '" rel="noopener" target="_blank">' . esc_html( $label ) . '<span class="screen-reader-text"> ' . esc_html__( '(nouvel onglet)', 'antoine-codex-immersive' ) . '</span></a></li>';
		}
	}
	if ( $links ) {
		echo '<ul class="social-links mono">' . implode( '', $links ) . '</ul>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus.
	}
}

/**
 * Titre en lignes : chaque phrase terminée par un point sur sa propre ligne.
 * La dernière phrase reçoit l'accent orange.
 *
 * @param string $title Titre.
 */
function acx_hero_title_lines( $title ) {
	$parts = preg_split( '/(?<=[.!?…])\s+/u', trim( $title ) );
	$parts = array_values( array_filter( $parts, 'strlen' ) );
	$count = count( $parts );
	foreach ( $parts as $i => $part ) {
		$class = 'hero__line' . ( $i === $count - 1 && $count > 1 ? ' hero__line--accent' : '' );
		echo '<span class="' . esc_attr( $class ) . '" style="--line:' . (int) $i . '"><span class="hero__line-inner">' . esc_html( $part ) . '</span></span> ';
	}
}

/**
 * Métadonnées d'un article.
 */
function acx_post_meta() {
	printf(
		'<p class="post-meta mono"><time datetime="%1$s">%2$s</time>%3$s</p>',
		esc_attr( get_the_date( DATE_W3C ) ),
		esc_html( get_the_date() ),
		has_category() ? '<span aria-hidden="true"> — </span>' . get_the_category_list( ', ' ) : '' // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fonction du cœur.
	);
}

/**
 * Pagination des archives.
 */
function acx_pagination() {
	the_posts_pagination(
		array(
			'mid_size'  => 1,
			'prev_text' => __( 'Précédent', 'antoine-codex-immersive' ),
			'next_text' => __( 'Suivant', 'antoine-codex-immersive' ),
		)
	);
}
