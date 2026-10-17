<?php
/**
 * Template helpers.
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Details of an artwork through the plugin (empty values without it).
 *
 * @param int $post_id Artwork ID.
 * @return array{year:int, technique:string, dimensions:string, gallery:array<int,int>, product_id:int, series:array<int,WP_Term>}
 */
function atelier_mira_artwork( int $post_id = 0 ): array {
	if ( function_exists( 'mira_artwork' ) ) {
		return mira_artwork( $post_id );
	}

	return array(
		'year'       => 0,
		'technique'  => '',
		'dimensions' => '',
		'gallery'    => array(),
		'product_id' => 0,
		'series'     => array(),
	);
}

/**
 * Artwork series ordered as in the studio (term meta `mira_order`).
 *
 * @return array<int, WP_Term>
 */
function atelier_mira_series(): array {
	$terms = get_terms(
		array(
			'taxonomy'   => 'artwork_series',
			'hide_empty' => true,
		)
	);

	if ( ! is_array( $terms ) ) {
		return array();
	}

	usort( $terms, static fn( WP_Term $a, WP_Term $b ) => (int) get_term_meta( $a->term_id, 'mira_order', true ) <=> (int) get_term_meta( $b->term_id, 'mira_order', true ) );

	return $terms;
}

/**
 * Artwork featured on the home page wall: the `atelier_mira_hero` theme mod, else the latest artwork.
 *
 * @return int Artwork ID, 0 when none.
 */
function atelier_mira_hero_artwork(): int {
	$id = (int) get_theme_mod( 'atelier_mira_hero', 0 );

	if ( $id && 'artwork' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}

	$latest = get_posts(
		array(
			'post_type'      => 'artwork',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		)
	);

	return $latest ? (int) $latest[0] : 0;
}

/**
 * Photo behind the home hero: the `atelier_mira_hero_image` theme mod (Customizer, or set by
 * `wp mira seed`), else the featured image of the hero artwork.
 *
 * @return int Attachment ID, 0 when none.
 */
function atelier_mira_hero_image_id(): int {
	$id = (int) get_theme_mod( 'atelier_mira_hero_image', 0 );

	if ( $id && wp_attachment_is_image( $id ) ) {
		return $id;
	}

	$artwork = atelier_mira_hero_artwork();

	return $artwork ? (int) get_post_thumbnail_id( $artwork ) : 0;
}

/**
 * Images of the studio section: the `atelier_mira_studio_image` theme mod, else an artwork,
 * plus a second artwork laid over the first.
 *
 * @return array<int, int> Up to two attachment IDs.
 */
function atelier_mira_studio_images(): array {
	$ids    = array();
	$studio = (int) get_theme_mod( 'atelier_mira_studio_image', 0 );

	if ( $studio && wp_attachment_is_image( $studio ) ) {
		$ids[] = $studio;
	}

	$works = get_posts(
		array(
			'post_type'      => 'artwork',
			'posts_per_page' => 2,
			'offset'         => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'post__not_in'   => array( atelier_mira_hero_artwork() ),
		)
	);

	foreach ( $works as $work ) {
		$ids[] = (int) get_post_thumbnail_id( $work );
	}

	return array_slice( array_values( array_filter( $ids ) ), 0, 2 );
}

/**
 * Museum-style wall label: title, year, technique, dimensions.
 *
 * @param int    $post_id Artwork ID.
 * @param string $heading Heading tag for the title (h1, h2, p…).
 * @return void
 */
function atelier_mira_wall_label( int $post_id, string $heading = 'p' ): void {
	$artwork = atelier_mira_artwork( $post_id );
	$tag     = tag_escape( $heading );
	?>
	<div class="wall-label">
		<<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape(). ?> class="wall-label__title"><?php echo esc_html( get_the_title( $post_id ) ); ?><?php echo $artwork['year'] ? '<span class="wall-label__year">, ' . esc_html( (string) $artwork['year'] ) . '</span>' : ''; ?></<?php echo $tag; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- tag_escape(). ?>>
		<?php if ( $artwork['technique'] || $artwork['dimensions'] ) : ?>
			<p class="wall-label__meta"><?php echo esc_html( implode( ' · ', array_filter( array( $artwork['technique'], $artwork['dimensions'] ) ) ) ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Lowest price of a product as text ("from €35"), for artwork pages and the block.
 *
 * @param int $product_id Product ID.
 * @return string Plain text, empty without WooCommerce.
 */
function atelier_mira_price_from( int $product_id ): string {
	if ( ! function_exists( 'wc_get_product' ) ) {
		return '';
	}

	$product = wc_get_product( $product_id );
	if ( ! $product ) {
		return '';
	}

	$price = $product->is_type( 'variable' ) ? $product->get_variation_price( 'min', true ) : $product->get_price();

	return wp_strip_all_tags(
		$product->is_type( 'variable' )
			/* translators: %s: price. */
			? sprintf( __( 'from %s', 'atelier-mira' ), wc_price( (float) $price ) )
			: wc_price( (float) $price )
	);
}

/**
 * Sizes attribute of the portfolio grid cards.
 *
 * @return string
 */
function atelier_mira_card_sizes(): string {
	return '(min-width: 1100px) 340px, (min-width: 700px) 45vw, calc(100vw - 2.5rem)';
}
