<?php
/**
 * En-tête.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?><!doctype html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<div class="bg-grid" aria-hidden="true"><span></span><span></span><span></span><span></span><span></span></div>

<header class="site-header" data-header>
	<div class="site-header__inner">
		<a class="brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home">
			<?php if ( has_custom_logo() ) : ?>
				<?php
				$logo_id = get_theme_mod( 'custom_logo' );
				echo wp_get_attachment_image( $logo_id, 'full', false, array( 'class' => 'brand__logo', 'alt' => get_bloginfo( 'name' ) ) );
				?>
			<?php else : ?>
				<span class="brand__mark" aria-hidden="true">
					<svg viewBox="0 0 40 40" width="34" height="34"><path d="M14 6H8a2 2 0 0 0-2 2v24a2 2 0 0 0 2 2h6M26 6h6a2 2 0 0 1 2 2v24a2 2 0 0 1-2 2h-6" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="20" cy="20" r="3.2" fill="#FF5500"/><path d="M13 20h3.5M23.5 20H27" stroke="#C9A45C" stroke-width="1.5"/></svg>
				</span>
				<span class="brand__word"><span>ANTOINE</span> <span class="brand__codex">CODEX</span></span>
			<?php endif; ?>
		</a>

		<nav class="primary-nav" aria-label="<?php esc_attr_e( 'Navigation principale', 'antoine-codex-immersive' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'primary-nav__list',
					'depth'          => 2,
					'fallback_cb'    => 'acx_fallback_menu',
				)
			);
			?>
		</nav>

		<div class="site-header__actions">
			<?php acx_language_switcher( 'lang-switch--header' ); ?>
			<a class="btn btn--primary btn--sm site-header__cta" href="<?php echo esc_url( acx_page_url( 'diagnostic' ) ); ?>">
				<span class="btn__label"><?php esc_html_e( 'Diagnostic', 'antoine-codex-immersive' ); ?></span><span class="btn__icon"><?php acx_the_icon( 'arrow' ); ?></span>
			</a>
			<button type="button" class="menu-toggle" aria-controls="mobile-nav" aria-expanded="false" data-menu-toggle>
				<span class="menu-toggle__label mono"><?php esc_html_e( 'Menu', 'antoine-codex-immersive' ); ?></span>
				<span class="menu-toggle__bars" aria-hidden="true"><span></span><span></span></span>
				<span class="screen-reader-text" data-menu-toggle-text><?php esc_html_e( 'Ouvrir le menu', 'antoine-codex-immersive' ); ?></span>
			</button>
		</div>
	</div>
	<div class="site-header__progress" aria-hidden="true"><span></span></div>
</header>

<div class="mobile-nav" id="mobile-nav" data-mobile-nav hidden>
	<div class="mobile-nav__inner">
		<nav aria-label="<?php esc_attr_e( 'Navigation mobile', 'antoine-codex-immersive' ); ?>">
			<?php
			wp_nav_menu(
				array(
					'theme_location' => 'primary',
					'container'      => false,
					'menu_class'     => 'mobile-nav__list',
					'depth'          => 2,
					'fallback_cb'    => 'acx_fallback_menu',
				)
			);
			?>
		</nav>
		<div class="mobile-nav__foot">
			<a class="btn btn--primary" href="<?php echo esc_url( acx_page_url( 'diagnostic' ) ); ?>"><span class="btn__label"><?php esc_html_e( 'Lancer le diagnostic', 'antoine-codex-immersive' ); ?></span><span class="btn__icon"><?php acx_the_icon( 'arrow' ); ?></span></a>
			<?php acx_language_switcher( 'lang-switch--mobile' ); ?>
			<p class="mono mobile-nav__coords">43.7102° N — 7.2620° E · Nice</p>
		</div>
	</div>
</div>

<main id="contenu" class="site-main" tabindex="-1">
