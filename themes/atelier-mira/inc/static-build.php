<?php
/**
 * Static export (theme side).
 *
 * When the Mira Studio plugin reports a static build (MIRA_STATIC_BUILD), the theme renders
 * a catalogue that never needs PHP:
 *
 * - `<body data-static-build="1">`, read by the scripts;
 * - the header cart, the cart drawer, "Add to cart" and the shop card buttons become links
 *   to the live demo in WordPress Playground; the cart and account pages leave the menus;
 * - the format buttons and the "to scale" preview keep working (product.js reads the
 *   variation prices from the form's JSON instead of WooCommerce's jQuery script);
 * - the commission form is replaced by a presentation of the service.
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether the page is rendered for the static export.
 *
 * @return bool
 */
function atelier_mira_is_static(): bool {
	return function_exists( 'mira_is_static_build' ) && mira_is_static_build();
}

/**
 * Playground URL (through the plugin).
 *
 * @return string
 */
function atelier_mira_live_url(): string {
	return function_exists( 'mira_playground_url' ) ? mira_playground_url() : home_url( '/' );
}

/**
 * Extra attributes on <body>.
 *
 * @return void
 */
function atelier_mira_body_attributes(): void {
	if ( atelier_mira_is_static() ) {
		echo ' data-static-build="1"';
	}
}

if ( atelier_mira_is_static() ) {
	add_action( 'wp_enqueue_scripts', 'atelier_mira_static_scripts', 200 );
	// WooCommerce enqueues its variation script while rendering the product form.
	add_action( 'wp_print_footer_scripts', 'atelier_mira_static_scripts', 1 );
	add_action( 'after_setup_theme', 'atelier_mira_static_hooks', 20 );
	add_filter( 'wp_nav_menu_objects', 'atelier_mira_static_menu' );
	add_filter( 'woocommerce_loop_add_to_cart_link', 'atelier_mira_static_loop_link', 10, 2 );
}

/**
 * Drops every script that talks to the server (WooCommerce, cart, commission form).
 *
 * @return void
 */
function atelier_mira_static_scripts(): void {
	foreach ( array( 'atelier-mira-cart', 'wc-add-to-cart-variation', 'wc-add-to-cart', 'wc-cart-fragments', 'woocommerce', 'wc-single-product', 'sourcebuster-js', 'wc-order-attribution', 'mira-commission-form', 'jquery', 'jquery-blockui', 'wp-util', 'underscore' ) as $handle ) {
		wp_dequeue_script( $handle );
	}
}

/**
 * Swaps the shop actions for links to the live demo.
 *
 * @return void
 */
function atelier_mira_static_hooks(): void {
	remove_action( 'atelier_mira_header_actions', 'atelier_mira_wc_cart_button' );
	remove_action( 'atelier_mira_after_footer', 'atelier_mira_wc_drawer' );
	add_action( 'atelier_mira_header_actions', 'atelier_mira_static_header_link' );
	add_action( 'woocommerce_after_add_to_cart_form', 'atelier_mira_static_try_live' );
}

/**
 * Header link replacing the cart.
 *
 * @return void
 */
function atelier_mira_static_header_link(): void {
	printf( '<a class="cart-button" href="%s" rel="noopener">%s</a>', esc_url( atelier_mira_live_url() ), esc_html__( 'Try the shop live', 'atelier-mira' ) );
}

/**
 * "Try the shop live" box under the (inert) product form.
 *
 * @return void
 */
function atelier_mira_static_try_live(): void {
	?>
	<div class="try-live">
		<a class="button" href="<?php echo esc_url( atelier_mira_live_url() ); ?>" rel="noopener"><?php esc_html_e( 'Try the shop live', 'atelier-mira' ); ?></a>
		<p><?php esc_html_e( 'This is a static preview. The live demo runs the real shop in WordPress Playground, in your browser: add prints to the cart, check out and get a numbered edition.', 'atelier-mira' ); ?></p>
	</div>
	<?php
}

/**
 * Shop cards link to the product page instead of adding to the cart.
 *
 * @param string     $html    Button HTML.
 * @param WC_Product $product Product.
 * @return string
 */
function atelier_mira_static_loop_link( string $html, $product ): string {
	return sprintf( '<a class="button" href="%s">%s</a>', esc_url( $product->get_permalink() ), esc_html__( 'See the print', 'atelier-mira' ) );
}

/**
 * Cart, checkout and account pages are not exported: remove them from the menus.
 *
 * @param array<int, WP_Post> $items Menu items.
 * @return array<int, WP_Post>
 */
function atelier_mira_static_menu( array $items ): array {
	if ( ! function_exists( 'wc_get_page_id' ) ) {
		return $items;
	}

	$hidden = array_map( 'wc_get_page_id', array( 'cart', 'checkout', 'myaccount' ) );

	return array_values(
		array_filter(
			$items,
			static fn( $item ) => ! ( 'page' === $item->object && in_array( (int) $item->object_id, $hidden, true ) )
		)
	);
}
