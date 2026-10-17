<?php
/**
 * Pages (also cart, checkout and account, which WooCommerce fills through shortcodes).
 *
 * @package AtelierMira\Theme
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'page-wrap' ); ?>>
		<header class="page-head">
			<h1 class="page-title"><?php the_title(); ?></h1>
		</header>
		<div class="entry-content">
			<?php the_content(); ?>
		</div>
	</article>
	<?php
endwhile;

get_footer();
