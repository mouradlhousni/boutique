<?php
/**
 * Single artwork: the image with its gallery, the wall label, the print if there is one.
 *
 * @package AtelierMira\Theme
 */

get_header();

while ( have_posts() ) :
	the_post();
	$atelier_mira_id      = (int) get_the_ID();
	$atelier_mira_artwork = atelier_mira_artwork( $atelier_mira_id );
	$atelier_mira_series  = $atelier_mira_artwork['series'] ? $atelier_mira_artwork['series'][0] : null;
	$atelier_mira_sizes   = '(min-width: 1000px) 58vw, calc(100vw - 2.5rem)';
	?>
	<article <?php post_class( 'artwork' ); ?>>
		<div class="artwork__media">
			<figure class="artwork__main">
				<?php echo atelier_mira_image( (int) get_post_thumbnail_id(), 'mira-xl', $atelier_mira_sizes, true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
			</figure>
			<?php foreach ( $atelier_mira_artwork['gallery'] as $atelier_mira_image ) : ?>
				<figure class="artwork__extra">
					<?php echo atelier_mira_image( $atelier_mira_image, 'large', $atelier_mira_sizes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
				</figure>
			<?php endforeach; ?>
		</div>

		<aside class="artwork__aside">
			<div class="artwork__sticky">
				<?php if ( $atelier_mira_series ) : ?>
					<p class="eyebrow">
						<a href="<?php echo esc_url( (string) get_term_link( $atelier_mira_series ) ); ?>"><?php echo esc_html( $atelier_mira_series->name ); ?></a>
					</p>
				<?php endif; ?>
				<?php atelier_mira_wall_label( $atelier_mira_id, 'h1' ); ?>

				<div class="artwork__text entry-content">
					<?php the_content(); ?>
				</div>

				<?php if ( $atelier_mira_artwork['product_id'] ) : ?>
					<?php $atelier_mira_edition = function_exists( 'mira_edition' ) ? mira_edition( $atelier_mira_artwork['product_id'] ) : null; ?>
					<div class="artwork__buy">
						<a class="button" href="<?php echo esc_url( get_permalink( $atelier_mira_artwork['product_id'] ) ); ?>">
							<?php echo $atelier_mira_edition ? esc_html__( 'See the limited edition', 'atelier-mira' ) : esc_html__( 'Buy a print', 'atelier-mira' ); ?>
						</a>
						<span class="artwork__price"><?php echo esc_html( atelier_mira_price_from( $atelier_mira_artwork['product_id'] ) ); ?></span>
					</div>
				<?php else : ?>
					<p class="artwork__note"><?php esc_html_e( 'No print of this piece yet.', 'atelier-mira' ); ?></p>
				<?php endif; ?>

				<?php $atelier_mira_commissions = get_page_by_path( 'commissions' ); ?>
				<?php if ( $atelier_mira_commissions ) : ?>
					<p><a class="link-arrow" href="<?php echo esc_url( get_permalink( $atelier_mira_commissions ) ); ?>"><?php esc_html_e( 'Commission a piece like this', 'atelier-mira' ); ?></a></p>
				<?php endif; ?>
			</div>
		</aside>

		<?php
		$atelier_mira_prev = get_previous_post( true, '', 'artwork_series' );
		$atelier_mira_next = get_next_post( true, '', 'artwork_series' );
		?>
		<?php if ( $atelier_mira_prev || $atelier_mira_next ) : ?>
			<nav class="artwork__pager" aria-label="<?php esc_attr_e( 'More from the series', 'atelier-mira' ); ?>">
				<?php if ( $atelier_mira_prev ) : ?>
					<a class="artwork__pager-link" rel="prev" href="<?php echo esc_url( get_permalink( $atelier_mira_prev ) ); ?>">
						<span class="eyebrow"><?php esc_html_e( 'Previous', 'atelier-mira' ); ?></span>
						<span class="artwork__pager-title"><?php echo esc_html( get_the_title( $atelier_mira_prev ) ); ?></span>
					</a>
				<?php endif; ?>
				<?php if ( $atelier_mira_next ) : ?>
					<a class="artwork__pager-link artwork__pager-link--next" rel="next" href="<?php echo esc_url( get_permalink( $atelier_mira_next ) ); ?>">
						<span class="eyebrow"><?php esc_html_e( 'Next', 'atelier-mira' ); ?></span>
						<span class="artwork__pager-title"><?php echo esc_html( get_the_title( $atelier_mira_next ) ); ?></span>
					</a>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
	</article>
	<?php
endwhile;

get_footer();
