<?php
/**
 * Public template API of the plugin.
 *
 * These global, `mira_`-prefixed functions are the only contract the theme relies on,
 * so the theme never touches plugin internals and degrades gracefully without the plugin.
 *
 * @package AtelierMira\Studio
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the site runs in portfolio demo mode (forms validate but never store or send anything).
 *
 * Single source of truth for the MIRA_DEMO_MODE constant.
 *
 * @return bool
 */
function mira_is_demo_mode(): bool {
	$enabled = defined( 'MIRA_DEMO_MODE' ) && MIRA_DEMO_MODE;

	/**
	 * Filters whether demo mode is active.
	 *
	 * @param bool $enabled Demo mode state.
	 */
	return (bool) apply_filters( 'mira_is_demo_mode', $enabled );
}

/**
 * Whether the site is being rendered for the static HTML export.
 *
 * @return bool
 */
function mira_is_static_build(): bool {
	return defined( 'MIRA_STATIC_BUILD' ) && MIRA_STATIC_BUILD;
}

/**
 * URL of the WordPress Playground live demo.
 *
 * @return string
 */
function mira_playground_url(): string {
	$url = 'https://playground.wordpress.net/?blueprint-url=' . rawurlencode( 'https://raw.githubusercontent.com/mouradlhousni/boutique/master/blueprint.json' );

	/**
	 * Filters the Playground demo URL.
	 *
	 * @param string $url URL.
	 */
	return (string) apply_filters( 'mira_playground_url', $url );
}

/**
 * Message returned by every form in demo mode.
 *
 * @return string
 */
function mira_demo_message(): string {
	return __( 'Demo mode: the form works, but no data is stored or sent.', 'mira-studio' );
}

/**
 * Whether WooCommerce is loaded.
 *
 * @return bool
 */
function mira_has_woocommerce(): bool {
	return class_exists( 'WooCommerce' );
}

/**
 * Details of an artwork: year, technique, dimensions, gallery IDs, linked product ID, series terms.
 *
 * @param int $post_id Artwork ID (current post when 0).
 * @return array{year:int, technique:string, dimensions:string, gallery:array<int,int>, product_id:int, series:array<int,\WP_Term>}
 */
function mira_artwork( int $post_id = 0 ): array {
	return \AtelierMira\Studio\Portfolio\Artwork::get( $post_id ? $post_id : (int) get_the_ID() );
}

/**
 * State of a limited edition: size, prints sold, prints left, sold out. Null for open editions.
 *
 * @param \WC_Product|int $product Product or ID (variations resolve to their parent).
 * @return array{size:int, sold:int, remaining:int, sold_out:bool}|null
 */
function mira_edition( $product ): ?array {
	return mira_has_woocommerce() ? \AtelierMira\Studio\Shop\Edition::state( $product ) : null;
}

/**
 * Prints the "12 / 50 sold" counter of a limited edition.
 *
 * @param \WC_Product $product Product.
 * @return void
 */
function mira_edition_counter( $product ): void {
	$state = mira_edition( $product );

	if ( ! $state ) {
		return;
	}
	?>
	<div class="mira-edition<?php echo $state['sold_out'] ? ' is-sold-out' : ''; ?>">
		<p class="mira-edition__title">
			<?php
			/* translators: %d: edition size. */
			echo esc_html( sprintf( __( 'Limited edition of %d · numbered and signed', 'mira-studio' ), $state['size'] ) );
			?>
		</p>
		<p class="mira-edition__count">
			<?php
			printf(
				/* translators: 1: prints sold, 2: edition size. */
				esc_html__( '%1$s sold', 'mira-studio' ),
				'<strong>' . esc_html( $state['sold'] . ' / ' . $state['size'] ) . '</strong>'
			);
			?>
		</p>
		<progress class="mira-edition__bar" max="<?php echo esc_attr( (string) $state['size'] ); ?>" value="<?php echo esc_attr( (string) $state['sold'] ); ?>" aria-hidden="true"></progress>
		<?php if ( ! $state['sold_out'] ) : ?>
			<p class="mira-edition__note"><?php esc_html_e( 'Your print number is assigned when the order is confirmed.', 'mira-studio' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Print formats of a product with their paper size in mm, keyed by term slug.
 *
 * @param \WC_Product $product Product.
 * @return array<string, array{name:string, width:int, height:int}>
 */
function mira_print_formats( $product ): array {
	return mira_has_woocommerce() && $product instanceof \WC_Product ? \AtelierMira\Studio\Shop\PrintFormats::for_product( $product ) : array();
}

/**
 * Prints the commission request form.
 *
 * @return void
 */
function mira_commission_form(): void {
	echo do_shortcode( '[mira_commission_form]' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by the form renderer.
}
