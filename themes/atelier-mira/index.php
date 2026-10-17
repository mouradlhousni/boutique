<?php
/**
 * Fallback template (blog posts, search, archives).
 *
 * @package AtelierMira\Theme
 */

get_header();
?>
<div class="page-wrap">
	<header class="page-head">
		<h1 class="page-title">
			<?php
			if ( is_search() ) {
				/* translators: %s: search query. */
				echo esc_html( sprintf( __( 'Search: %s', 'atelier-mira' ), get_search_query() ) );
			} elseif ( is_archive() ) {
				echo esc_html( wp_strip_all_tags( get_the_archive_title() ) );
			} else {
				esc_html_e( 'Journal', 'atelier-mira' );
			}
			?>
		</h1>
	</header>

	<?php if ( have_posts() ) : ?>
		<div class="entry-list">
			<?php
			while ( have_posts() ) :
				the_post();
				?>
				<article <?php post_class( 'entry-list__item' ); ?>>
					<h2 class="entry-list__title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
					<div class="entry-list__excerpt"><?php the_excerpt(); ?></div>
				</article>
			<?php endwhile; ?>
		</div>
		<?php the_posts_pagination(); ?>
	<?php else : ?>
		<p><?php esc_html_e( 'Nothing here yet.', 'atelier-mira' ); ?></p>
	<?php endif; ?>
</div>
<?php
get_footer();
