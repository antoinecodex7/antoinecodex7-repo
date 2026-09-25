<?php
/**
 * Gabarit de repli : blog, archives.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

$acx_title = '';
$acx_lead  = '';
if ( is_home() && ! is_front_page() ) {
	$acx_title = get_the_title( (int) get_option( 'page_for_posts' ) );
} elseif ( is_search() ) {
	/* translators: %s: search query. */
	$acx_title = sprintf( __( 'Recherche : %s', 'antoine-codex-immersive' ), get_search_query() );
} elseif ( is_archive() ) {
	$acx_title = wp_strip_all_tags( get_the_archive_title() );
	$acx_lead  = wp_strip_all_tags( get_the_archive_description() );
} else {
	$acx_title = __( 'Journal', 'antoine-codex-immersive' );
}

acx_page_hero(
	array(
		'title' => $acx_title,
		'lead'  => $acx_lead,
		'label' => $acx_title,
	)
);
?>
<section class="section section--content">
	<div class="container">
		<?php if ( is_search() ) : ?>
			<div class="search-again" data-reveal><?php get_search_form(); ?></div>
		<?php endif; ?>

		<?php if ( have_posts() ) : ?>
			<div class="post-grid">
				<?php
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', 'card' );
				endwhile;
				?>
			</div>
			<?php acx_pagination(); ?>
		<?php else : ?>
			<p class="empty-state"><?php esc_html_e( 'Aucun contenu ne correspond à cette recherche.', 'antoine-codex-immersive' ); ?></p>
		<?php endif; ?>
	</div>
</section>
<?php
get_footer();
