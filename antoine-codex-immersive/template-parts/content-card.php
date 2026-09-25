<?php
/**
 * Carte d'article dans les listes.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;
?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?> data-reveal data-tilt>
	<?php if ( has_post_thumbnail() ) : ?>
		<div class="post-card__media"><?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?></div>
	<?php endif; ?>
	<div class="post-card__body">
		<?php if ( 'post' === get_post_type() ) : ?>
			<?php acx_post_meta(); ?>
		<?php else : ?>
			<p class="post-meta mono"><?php echo esc_html( get_post_type_object( get_post_type() )->labels->singular_name ); ?></p>
		<?php endif; ?>
		<h2 class="post-card__title"><a class="post-card__link" href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		<p class="post-card__excerpt"><?php echo esc_html( wp_strip_all_tags( get_the_excerpt() ) ); ?></p>
		<span class="post-card__more mono" aria-hidden="true"><?php esc_html_e( 'Lire', 'antoine-codex-immersive' ); ?> →</span>
	</div>
</article>
