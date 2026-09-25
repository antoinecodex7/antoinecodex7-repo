<?php
/**
 * Article.
 *
 * @package AntoineCodexImmersive
 */

defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
	the_post();
	ob_start();
	if ( 'post' === get_post_type() ) {
		acx_post_meta();
	}
	$acx_meta = ob_get_clean();
	acx_page_hero( array( 'extra' => $acx_meta ) );
	?>
	<article id="post-<?php the_ID(); ?>" <?php post_class( 'section section--content' ); ?>>
		<div class="container">
			<?php if ( has_post_thumbnail() ) : ?>
				<figure class="single-cover" data-reveal><?php the_post_thumbnail( 'acx-wide' ); ?></figure>
			<?php endif; ?>
			<div class="entry-content">
				<?php
				the_content();
				wp_link_pages();
				?>
			</div>
			<?php if ( has_tag() ) : ?>
				<p class="post-tags mono"><?php the_tags( '', ' · ', '' ); ?></p>
			<?php endif; ?>
			<?php
			the_post_navigation(
				array(
					'prev_text' => '<span class="mono">' . esc_html__( 'Précédent', 'antoine-codex-immersive' ) . '</span><span>%title</span>',
					'next_text' => '<span class="mono">' . esc_html__( 'Suivant', 'antoine-codex-immersive' ) . '</span><span>%title</span>',
				)
			);
			?>
		</div>
	</article>
	<?php
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="container container--narrow">';
		comments_template();
		echo '</div>';
	}
endwhile;

get_footer();
