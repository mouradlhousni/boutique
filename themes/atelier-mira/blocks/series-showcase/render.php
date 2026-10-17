<?php
/**
 * Server render of the "Artwork Series Showcase" block.
 *
 * Each series is a row: a card with the latest work as its background (title and a link
 * to the series), then the following works.
 *
 * Available variables: $attributes, $content, $block.
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

$atelier_mira_ids   = array_map( 'absint', (array) ( $attributes['series'] ?? array() ) );
$atelier_mira_count = max( 2, min( 6, (int) ( $attributes['worksPerSeries'] ?? 3 ) ) );
$atelier_mira_terms = array_values(
	array_filter(
		atelier_mira_series(),
		static fn( WP_Term $term ) => ! $atelier_mira_ids || in_array( $term->term_id, $atelier_mira_ids, true )
	)
);

if ( ! $atelier_mira_terms ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		echo '<p>' . esc_html__( 'No series with published artworks yet.', 'atelier-mira' ) . '</p>';
	}
	return;
}

$atelier_mira_heading = (string) ( $attributes['heading'] ?? '' );
?>
<section <?php echo get_block_wrapper_attributes( array( 'class' => 'series-showcase' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core escapes the attributes. ?>>
	<?php if ( '' !== $atelier_mira_heading ) : ?>
		<header class="series-showcase__head">
			<p class="eyebrow eyebrow--rule"><span><?php esc_html_e( 'Our', 'atelier-mira' ); ?></span><span><?php esc_html_e( 'Series', 'atelier-mira' ); ?></span></p>
			<h2 class="series-showcase__heading"><?php echo esc_html( $atelier_mira_heading ); ?></h2>
		</header>
	<?php endif; ?>

	<?php foreach ( $atelier_mira_terms as $atelier_mira_term ) : ?>
		<?php
		$atelier_mira_works  = get_posts(
			array(
				'post_type'      => 'artwork',
				// One more than the row: the latest work is the background of the series card.
				'posts_per_page' => $atelier_mira_count + 1,
				'no_found_rows'  => true,
				'orderby'        => 'date',
				'order'          => 'DESC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Small, cached taxonomy query.
				'tax_query'      => array(
					array(
						'taxonomy' => 'artwork_series',
						'terms'    => $atelier_mira_term->term_id,
					),
				),
			)
		);
		$atelier_mira_cover  = array_shift( $atelier_mira_works );
		$atelier_mira_accent = sanitize_hex_color( (string) get_term_meta( $atelier_mira_term->term_id, 'mira_accent', true ) );
		$atelier_mira_link   = (string) get_term_link( $atelier_mira_term );
		$atelier_mira_title  = 'series-' . $atelier_mira_term->slug;
		?>
		<article class="series-row" aria-labelledby="<?php echo esc_attr( $atelier_mira_title ); ?>" style="<?php echo esc_attr( ( $atelier_mira_accent ? '--series-accent:' . $atelier_mira_accent . ';' : '' ) . '--sc-columns:' . $atelier_mira_count ); ?>">
			<header class="series-row__head">
				<?php if ( $atelier_mira_cover ) : ?>
					<span class="series-row__cover">
						<?php echo atelier_mira_image( (int) get_post_thumbnail_id( $atelier_mira_cover ), 'large', '(min-width: 800px) 32vw, 100vw', false, array( 'alt' => '' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(), decorative. ?>
					</span>
				<?php endif; ?>
				<p class="eyebrow">
					<?php
					/* translators: %d: number of works. */
					echo esc_html( sprintf( _n( 'Series · %d work', 'Series · %d works', $atelier_mira_term->count, 'atelier-mira' ), $atelier_mira_term->count ) );
					?>
				</p>
				<h3 class="series-row__title" id="<?php echo esc_attr( $atelier_mira_title ); ?>"><?php echo esc_html( $atelier_mira_term->name ); ?></h3>
				<?php if ( ! empty( $attributes['showDescription'] ) && $atelier_mira_term->description ) : ?>
					<p class="series-row__text"><?php echo esc_html( $atelier_mira_term->description ); ?></p>
				<?php endif; ?>
				<a class="button button--light" href="<?php echo esc_url( $atelier_mira_link ); ?>">
					<?php
					/* translators: %s: series name. */
					echo esc_html( sprintf( __( 'See all of %s', 'atelier-mira' ), $atelier_mira_term->name ) );
					?>
				</a>
			</header>

			<?php if ( $atelier_mira_works ) : ?>
				<ul class="series-row__works">
					<?php foreach ( $atelier_mira_works as $atelier_mira_work ) : ?>
						<li>
							<a href="<?php echo esc_url( get_permalink( $atelier_mira_work ) ); ?>">
								<span class="series-row__mat">
									<?php echo atelier_mira_image( (int) get_post_thumbnail_id( $atelier_mira_work ), 'large', '(min-width: 1100px) 220px, (min-width: 800px) 17vw, 40vw' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
								</span>
								<span class="series-row__caption"><?php echo esc_html( get_the_title( $atelier_mira_work ) ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</article>
	<?php endforeach; ?>
</section>
