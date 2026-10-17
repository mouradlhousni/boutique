<?php
/**
 * Template Name: Commissions
 *
 * Page content (how commissions work) followed by the request form. In the static
 * export the form is replaced by a presentation of the service and a link to the live demo.
 *
 * @package AtelierMira\Theme
 */

get_header();

while ( have_posts() ) :
	the_post();
	?>
	<article <?php post_class( 'commissions' ); ?>>
		<header class="page-head page-head--split">
			<div>
				<p class="eyebrow"><?php esc_html_e( 'Commissions', 'atelier-mira' ); ?></p>
				<h1 class="page-title"><?php the_title(); ?></h1>
			</div>
			<?php if ( has_excerpt() ) : ?>
				<p class="lede"><?php echo esc_html( get_the_excerpt() ); ?></p>
			<?php endif; ?>
		</header>

		<div class="commissions__layout">
			<div class="entry-content commissions__content">
				<?php the_content(); ?>
			</div>

			<section class="commissions__form" aria-labelledby="request-title">
				<h2 id="request-title" class="section__title"><?php esc_html_e( 'Request a commission', 'atelier-mira' ); ?></h2>
				<?php
				if ( function_exists( 'mira_is_static_build' ) && mira_is_static_build() ) {
					get_template_part( 'template-parts/static-commission' );
				} elseif ( function_exists( 'mira_commission_form' ) ) {
					mira_commission_form();
				}
				?>
			</section>
		</div>
	</article>
	<?php
endwhile;

get_footer();
