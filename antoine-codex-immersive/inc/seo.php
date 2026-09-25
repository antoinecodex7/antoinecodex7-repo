<?php
/**
 * SEO de secours. Le thème n'écrit AUCUNE métadonnée si une extension SEO est active :
 * vos réglages Yoast / Rank Math / AIOSEO / SEOPress / The SEO Framework restent maîtres.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

/**
 * Une extension SEO est-elle active ?
 *
 * @return bool
 */
function acx_seo_plugin_active() {
	$active = defined( 'WPSEO_VERSION' )
		|| class_exists( 'RankMath' )
		|| defined( 'RANK_MATH_VERSION' )
		|| defined( 'AIOSEO_VERSION' )
		|| function_exists( 'aioseo' )
		|| defined( 'SEOPRESS_VERSION' )
		|| defined( 'THE_SEO_FRAMEWORK_VERSION' )
		|| defined( 'SLIM_SEO_VER' )
		|| class_exists( 'Smartcrawl_Loader' );
	return (bool) apply_filters( 'acx_seo_plugin_active', $active );
}

/**
 * Description de la page courante.
 *
 * @return string
 */
function acx_seo_description() {
	$desc = '';
	if ( is_front_page() ) {
		$desc = acx_text( 'hero_lead' );
	} elseif ( is_singular() ) {
		$post = get_queried_object();
		if ( $post instanceof WP_Post ) {
			$desc = has_excerpt( $post ) ? $post->post_excerpt : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30, '…' );
		}
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$desc = term_description();
	}
	if ( ! $desc ) {
		$desc = get_bloginfo( 'description' );
	}
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $desc ) ) );
}

/**
 * Métadonnées de secours : description, Open Graph, données structurées.
 */
function acx_seo_fallback() {
	if ( acx_seo_plugin_active() || ! apply_filters( 'acx_output_seo_fallback', true ) ) {
		return;
	}

	$desc  = acx_seo_description();
	$title = wp_get_document_title();
	$url   = is_singular() ? get_permalink() : home_url( add_query_arg( array(), $GLOBALS['wp']->request ? '/' . $GLOBALS['wp']->request . '/' : '/' ) );
	$image = '';
	if ( is_singular() && has_post_thumbnail() ) {
		$image = get_the_post_thumbnail_url( null, 'acx-wide' );
	} elseif ( has_custom_logo() ) {
		$image = wp_get_attachment_image_url( get_theme_mod( 'custom_logo' ), 'full' );
	}

	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( wp_html_excerpt( $desc, 160, '…' ) ) . '">' . "\n";
	}
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo( 'name' ) ) . '">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( $title ) . '">' . "\n";
	echo '<meta property="og:url" content="' . esc_url( $url ) . '">' . "\n";
	echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( wp_html_excerpt( $desc, 200, '…' ) ) . '">' . "\n";
	}
	if ( $image ) {
		echo '<meta property="og:image" content="' . esc_url( $image ) . '">' . "\n";
	}
	echo '<meta name="twitter:card" content="' . ( $image ? 'summary_large_image' : 'summary' ) . '">' . "\n";

	if ( is_front_page() ) {
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'ProfessionalService',
			'name'       => get_bloginfo( 'name' ),
			'url'        => home_url( '/' ),
			'areaServed' => array(
				array( '@type' => 'City', 'name' => 'Nice' ),
				array( '@type' => 'AdministrativeArea', 'name' => 'Côte d’Azur' ),
			),
		);
		if ( $desc ) {
			$data['description'] = $desc;
		}
		if ( $image ) {
			$data['image'] = $image;
		}
		$email = sanitize_email( (string) acx_opt( 'contact_email' ) );
		if ( $email ) {
			$data['email'] = $email;
		}
		$phone = trim( (string) acx_opt( 'contact_phone' ) );
		if ( $phone ) {
			$data['telephone'] = $phone;
		}
		$same = array_values( array_filter( array( acx_opt( 'social_linkedin' ), acx_opt( 'social_instagram' ), acx_opt( 'social_facebook' ), acx_opt( 'social_youtube' ) ) ) );
		if ( $same ) {
			$data['sameAs'] = $same;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( apply_filters( 'acx_schema', $data ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
	}
}
add_action( 'wp_head', 'acx_seo_fallback', 5 );
