<?php
/**
 * Formulaire de recherche.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

$acx_sid = wp_unique_id( 'search-' );
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $acx_sid ); ?>"><?php esc_html_e( 'Rechercher', 'antoine-codex-immersive' ); ?></label>
	<input type="search" id="<?php echo esc_attr( $acx_sid ); ?>" class="search-form__input" placeholder="<?php esc_attr_e( 'Rechercher…', 'antoine-codex-immersive' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s">
	<button type="submit" class="search-form__submit"><?php acx_the_icon( 'arrow' ); ?><span class="screen-reader-text"><?php esc_html_e( 'Lancer la recherche', 'antoine-codex-immersive' ); ?></span></button>
</form>
