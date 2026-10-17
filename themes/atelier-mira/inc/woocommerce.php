<?php
/**
 * WooCommerce integration – hooks and filters only, no template override.
 *
 * - Assets: WooCommerce's stylesheets are replaced by woocommerce.css (loaded only where
 *   shop markup renders); its jQuery scripts are dropped where they are not needed.
 * - Shop: own wrappers, category filters, framed product cards with explicit sizes.
 * - Product page: gallery with zoom, format buttons + "to scale" preview (product.js),
 *   edition counter (plugin), details section instead of tabs.
 * - Cart drawer (styles in cart.css, loaded on every page): a native <dialog> filled with
 *   the mini cart, kept up to date through WooCommerce cart fragments, fed by vanilla
 *   fetch() calls (cart.js).
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

// Assets.

add_filter( 'woocommerce_enqueue_styles', '__return_empty_array' );

/**
 * Whether the current page renders WooCommerce markup.
 *
 * @return bool
 */
function atelier_mira_is_shop_page(): bool {
	return is_woocommerce() || is_cart() || is_checkout() || is_account_page() || is_front_page();
}

/**
 * Shop styles and scripts.
 *
 * @return void
 */
function atelier_mira_wc_assets(): void {
	$defer = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);

	if ( atelier_mira_is_shop_page() ) {
		wp_enqueue_style( 'atelier-mira-shop', atelier_mira_asset( 'css/woocommerce.css' ), array( 'atelier-mira' ), atelier_mira_asset_version( 'css/woocommerce.css' ) );
	}

	// The cart drawer lives in every page header.
	wp_enqueue_style( 'atelier-mira-cart', atelier_mira_asset( 'css/cart.css' ), array( 'atelier-mira' ), atelier_mira_asset_version( 'css/cart.css' ) );
	wp_enqueue_script( 'atelier-mira-cart', atelier_mira_asset( 'js/cart.js' ), array(), atelier_mira_asset_version( 'js/cart.js' ), $defer );
	wp_localize_script(
		'atelier-mira-cart',
		'atelierMiraCart',
		array(
			'endpoint' => WC_AJAX::get_endpoint( '%%endpoint%%' ),
			'i18n'     => array(
				'adding' => __( 'Adding…', 'atelier-mira' ),
				'added'  => __( 'Added to your cart', 'atelier-mira' ),
				'error'  => __( 'This print could not be added. Please try again.', 'atelier-mira' ),
			),
		)
	);

	if ( is_product() ) {
		wp_enqueue_script( 'atelier-mira-product', atelier_mira_asset( 'js/product.js' ), array(), atelier_mira_asset_version( 'js/product.js' ), $defer );
		wp_localize_script(
			'atelier-mira-product',
			'atelierMiraProduct',
			array(
				'i18n' => array(
					'zoom'      => __( 'Zoom in', 'atelier-mira' ),
					'unzoom'    => __( 'Zoom out', 'atelier-mira' ),
					/* translators: 1: image number, 2: number of images. */
					'showImage' => __( 'Show image %1$d of %2$d', 'atelier-mira' ),
					'framed'    => __( 'framed', 'atelier-mira' ),
				),
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 'atelier_mira_wc_assets', 20 );

/**
 * Drops WooCommerce scripts and styles the theme replaces or does not need.
 *
 * - `wc-add-to-cart` and `wc-cart-fragments` (jQuery): replaced by cart.js everywhere;
 * - `woocommerce` (jQuery: notices, quantity, password toggle): only cart, checkout, account;
 * - block styles: the shop uses classic templates; order attribution: checkout only.
 *
 * @return void
 */
function atelier_mira_wc_trim_assets(): void {
	wp_dequeue_script( 'wc-add-to-cart' );
	wp_dequeue_script( 'wc-cart-fragments' );

	if ( ! is_cart() && ! is_checkout() && ! is_account_page() ) {
		wp_dequeue_script( 'woocommerce' );
		wp_dequeue_script( 'sourcebuster-js' );
		wp_dequeue_script( 'wc-order-attribution' );
	}

	foreach ( array( 'wc-blocks-style', 'wc-blocks-vendors-style', 'wc-block-style', 'woocommerce-inline' ) as $style ) {
		wp_dequeue_style( $style );
	}
}
add_action( 'wp_enqueue_scripts', 'atelier_mira_wc_trim_assets', 99 );

// Layout.

remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );
remove_action( 'woocommerce_sidebar', 'woocommerce_get_sidebar', 10 );

/**
 * Opens the shop wrapper.
 *
 * @return void
 */
function atelier_mira_wc_wrapper_start(): void {
	echo '<div class="shop' . ( is_product() ? ' shop--product' : '' ) . '">';
}
add_action( 'woocommerce_before_main_content', 'atelier_mira_wc_wrapper_start', 10 );

/**
 * Closes the shop wrapper.
 *
 * @return void
 */
function atelier_mira_wc_wrapper_end(): void {
	echo '</div>';
}
add_action( 'woocommerce_after_main_content', 'atelier_mira_wc_wrapper_end', 10 );

/**
 * Quieter breadcrumb.
 *
 * @return array<string, string>
 */
function atelier_mira_wc_breadcrumb(): array {
	return array(
		'delimiter'   => '<span aria-hidden="true"> / </span>',
		'wrap_before' => '<nav class="breadcrumb" aria-label="' . esc_attr__( 'Breadcrumb', 'atelier-mira' ) . '">',
		'wrap_after'  => '</nav>',
		'before'      => '',
		'after'       => '',
		'home'        => _x( 'Home', 'breadcrumb', 'atelier-mira' ),
	);
}
add_filter( 'woocommerce_breadcrumb_defaults', 'atelier_mira_wc_breadcrumb' );

/**
 * Category filters under the shop title (real links, current one marked).
 *
 * @return void
 */
function atelier_mira_wc_category_filters(): void {
	if ( ! is_shop() && ! is_product_category() ) {
		return;
	}

	$terms   = get_terms(
		array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => true,
			'exclude'    => array( (int) get_option( 'default_product_cat' ) ),
		)
	);
	$current = is_product_category() ? get_queried_object_id() : 0;
	?>
	<nav class="filters filters--shop" aria-label="<?php esc_attr_e( 'Shop categories', 'atelier-mira' ); ?>">
		<ul class="filters__list">
			<li><a class="filters__link" href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>><?php esc_html_e( 'Everything', 'atelier-mira' ); ?></a></li>
			<?php foreach ( is_array( $terms ) ? $terms : array() as $term ) : ?>
				<li>
					<a class="filters__link" href="<?php echo esc_url( (string) get_term_link( $term ) ); ?>"<?php echo $current === $term->term_id ? ' aria-current="page"' : ''; ?>>
						<?php echo esc_html( $term->name ); ?>
						<span class="filters__count"><?php echo esc_html( (string) $term->count ); ?></span>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</nav>
	<?php
}
add_action( 'woocommerce_archive_description', 'atelier_mira_wc_category_filters', 20 );

/**
 * Shop page intro text.
 *
 * @return void
 */
function atelier_mira_wc_shop_intro(): void {
	if ( is_shop() && ! is_search() ) {
		echo '<p class="lede">' . esc_html__( 'Open-edition prints in three sizes, framed or not, and small numbered editions signed by hand. Everything ships flat or framed from Marseille.', 'atelier-mira' ) . '</p>';
	}
}
add_action( 'woocommerce_archive_description', 'atelier_mira_wc_shop_intro', 5 );

// Product cards.

remove_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail', 10 );
remove_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating', 5 );

/**
 * Framed card image with explicit sizes (3 columns desktop, 2 tablet/mobile).
 *
 * @return void
 */
function atelier_mira_wc_loop_image(): void {
	global $product;

	static $index = 0;

	echo '<span class="product-card__frame">';
	echo atelier_mira_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image().
		(int) $product->get_image_id(),
		'woocommerce_thumbnail',
		'(min-width: 1100px) 300px, (min-width: 700px) 30vw, 45vw',
		false,
		array( 'loading' => is_shop() && $index++ < 3 ? 'eager' : 'lazy' )
	);
	echo '</span>';
}
add_action( 'woocommerce_before_shop_loop_item_title', 'atelier_mira_wc_loop_image', 10 );

/**
 * Small-caps line above the card title: "Limited edition", else the product category.
 *
 * @return void
 */
function atelier_mira_wc_loop_kicker(): void {
	global $product;

	$kicker = '';

	if ( function_exists( 'mira_edition' ) && mira_edition( $product->get_id() ) ) {
		$kicker = __( 'Limited edition', 'atelier-mira' );
	} else {
		$terms = get_the_terms( $product->get_id(), 'product_cat' );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			if ( (int) get_option( 'default_product_cat' ) !== $term->term_id ) {
				$kicker = $term->name;
				break;
			}
		}
	}

	if ( '' !== $kicker ) {
		echo '<span class="product-card__kicker">' . esc_html( $kicker ) . '</span>';
	}
}
add_action( 'woocommerce_shop_loop_item_title', 'atelier_mira_wc_loop_kicker', 5 );

/**
 * Card button labels.
 *
 * @param string     $text    Label.
 * @param WC_Product $product Product.
 * @return string
 */
function atelier_mira_wc_loop_button( string $text, $product ): string {
	if ( $product instanceof WC_Product && $product->is_type( 'variable' ) ) {
		return __( 'Choose a size', 'atelier-mira' );
	}
	if ( $product instanceof WC_Product && ! $product->is_in_stock() ) {
		return __( 'See the edition', 'atelier-mira' );
	}

	return $text;
}
add_filter( 'woocommerce_product_add_to_cart_text', 'atelier_mira_wc_loop_button', 10, 2 );

/**
 * Single product button label.
 *
 * @return string
 */
function atelier_mira_wc_single_button(): string {
	return __( 'Add to cart', 'atelier-mira' );
}
add_filter( 'woocommerce_product_single_add_to_cart_text', 'atelier_mira_wc_single_button' );

/**
 * "From €35" rather than a price range for prints.
 *
 * @param string     $price   Price HTML.
 * @param WC_Product $product Product.
 * @return string
 */
function atelier_mira_wc_price_from( string $price, $product ): string {
	if ( $product instanceof WC_Product_Variable && $product->get_variation_price( 'min' ) !== $product->get_variation_price( 'max' ) ) {
		/* translators: %s: lowest price. */
		return sprintf( __( 'from %s', 'atelier-mira' ), wc_price( (float) $product->get_variation_price( 'min', true ) ) );
	}

	return $price;
}
add_filter( 'woocommerce_get_price_html', 'atelier_mira_wc_price_from', 10, 2 );

/**
 * Three columns, twelve per page.
 *
 * @return int
 */
function atelier_mira_wc_columns(): int {
	return 3;
}
add_filter( 'loop_shop_columns', 'atelier_mira_wc_columns' );

// Product page.

remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_sale_flash', 10 );
remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_rating', 10 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );
remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_sharing', 50 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_product_data_tabs', 10 );
remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_upsell_display', 15 );

/**
 * Product gallery: main image with zoom and thumbnail buttons (product.js).
 *
 * Without JavaScript every image is still shown, stacked (the controls only exist with
 * JavaScript). The `js` class is set in <head>, so the CSS lays out the enhanced gallery
 * from the first paint: no layout shift when the script runs.
 *
 * @return void
 */
function atelier_mira_wc_gallery(): void {
	global $product;

	$ids = array_values( array_filter( array_merge( array( (int) $product->get_image_id() ), $product->get_gallery_image_ids() ) ) );

	if ( ! $ids ) {
		echo '<div class="product-gallery">' . wc_placeholder_img( 'woocommerce_single' ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core markup.
		return;
	}

	$sizes = '(min-width: 1000px) 55vw, calc(100vw - 2.5rem)';
	?>
	<div class="product-gallery" data-gallery>
		<div class="product-gallery__stage">
			<?php foreach ( $ids as $index => $id ) : ?>
				<?php $full = wp_get_attachment_image_src( $id, 'mira-xl' ); ?>
				<figure class="product-gallery__slide<?php echo 0 === $index ? ' is-active' : ''; ?>" data-slide="<?php echo esc_attr( (string) $index ); ?>" data-zoom="<?php echo esc_url( $full ? $full[0] : '' ); ?>">
					<?php echo atelier_mira_image( $id, 'woocommerce_single', $sizes, 0 === $index ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image(). ?>
				</figure>
			<?php endforeach; ?>
			<button type="button" class="product-gallery__zoom" data-zoom-toggle aria-pressed="false">
				<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="18" height="18"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M15.5 15.5 21 21M10.5 7.5v6M7.5 10.5h6" stroke="currentColor" stroke-width="1.5"/></svg>
				<span data-zoom-label><?php esc_html_e( 'Zoom in', 'atelier-mira' ); ?></span>
			</button>
		</div>
		<?php if ( count( $ids ) > 1 ) : ?>
			<ul class="product-gallery__thumbs" data-thumbs>
				<?php foreach ( $ids as $index => $id ) : ?>
					<li>
						<button type="button" data-thumb="<?php echo esc_attr( (string) $index ); ?>" aria-pressed="<?php echo 0 === $index ? 'true' : 'false'; ?>">
							<?php echo wp_get_attachment_image( $id, 'woocommerce_gallery_thumbnail', false, array( 'alt' => '' ) ); ?>
							<span class="screen-reader-text">
								<?php
								/* translators: 1: image number, 2: number of images. */
								echo esc_html( sprintf( __( 'Show image %1$d of %2$d', 'atelier-mira' ), $index + 1, count( $ids ) ) );
								?>
							</span>
						</button>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	</div>
	<?php
}
add_action( 'woocommerce_before_single_product_summary', 'atelier_mira_wc_gallery', 20 );

/**
 * Wraps the gallery and the summary in their own grid, so the sticky gallery stays in
 * that first band instead of sliding over the details and related products below.
 *
 * @return void
 */
function atelier_mira_wc_hero_open(): void {
	echo '<div class="product-hero">';
}
add_action( 'woocommerce_before_single_product_summary', 'atelier_mira_wc_hero_open', 1 );

/**
 * Closes the wrapper opened by atelier_mira_wc_hero_open().
 *
 * @return void
 */
function atelier_mira_wc_hero_close(): void {
	echo '</div>';
}
add_action( 'woocommerce_after_single_product_summary', 'atelier_mira_wc_hero_close', 1 );

/**
 * "To scale" preview: the print (per format) above a 2 m sofa, drawn in centimetres.
 *
 * Data for product.js: formats with their paper size, the frame attribute, the artwork image.
 *
 * @return void
 */
function atelier_mira_wc_scale_preview(): void {
	global $product;

	$formats = function_exists( 'mira_print_formats' ) ? mira_print_formats( $product ) : array();

	if ( ! $formats ) {
		return;
	}

	$first = reset( $formats );
	$image = wp_get_attachment_image_src( (int) $product->get_image_id(), 'medium' );
	$ratio = $image && $image[2] ? $image[1] / $image[2] : 0.75;
	?>
	<figure class="scale-preview" data-scale-preview data-formats="<?php echo esc_attr( (string) wp_json_encode( $formats ) ); ?>" data-ratio="<?php echo esc_attr( (string) round( $ratio, 3 ) ); ?>">
		<svg viewBox="0 0 320 200" role="img" aria-labelledby="scale-preview-caption" focusable="false">
			<rect class="scale-preview__wall" x="0" y="0" width="320" height="200" />
			<rect class="scale-preview__floor" x="0" y="188" width="320" height="12" />
			<g class="scale-preview__sofa" transform="translate(60 118)">
				<rect x="0" y="22" width="200" height="40" rx="6" />
				<rect x="8" y="0" width="184" height="34" rx="8" />
				<rect x="-6" y="16" width="20" height="46" rx="6" />
				<rect x="186" y="16" width="20" height="46" rx="6" />
				<rect x="10" y="62" width="4" height="8" />
				<rect x="186" y="62" width="4" height="8" />
			</g>
			<g class="scale-preview__print" data-print>
				<rect class="scale-preview__frame" data-frame x="0" y="0" width="0" height="0" />
				<rect class="scale-preview__mat" data-mat x="0" y="0" width="0" height="0" />
				<?php if ( $image ) : ?>
					<image data-art href="<?php echo esc_url( $image[0] ); ?>" x="0" y="0" width="0" height="0" preserveAspectRatio="xMidYMid slice" />
				<?php endif; ?>
			</g>
			<g class="scale-preview__ruler">
				<line x1="60" y1="194" x2="260" y2="194" />
				<text x="160" y="198">200 cm</text>
			</g>
		</svg>
		<figcaption id="scale-preview-caption">
			<?php esc_html_e( 'To scale, above a 2 m sofa:', 'atelier-mira' ); ?>
			<strong data-scale-label>
				<?php
				/* translators: 1: format name, 2: width in cm, 3: height in cm. */
				echo esc_html( sprintf( __( '%1$s, %2$s × %3$s cm', 'atelier-mira' ), $first['name'], number_format_i18n( $first['width'] / 10, 1 ), number_format_i18n( $first['height'] / 10, 1 ) ) );
				?>
			</strong>
		</figcaption>
	</figure>
	<?php
}
add_action( 'woocommerce_single_product_summary', 'atelier_mira_wc_scale_preview', 35 );

/**
 * Details under the product: the description, print specs and the original artwork.
 *
 * @return void
 */
function atelier_mira_wc_details(): void {
	global $product;

	$artwork_id = (int) $product->get_meta( '_mira_artwork_id' );
	$artwork    = $artwork_id && 'publish' === get_post_status( $artwork_id ) ? $artwork_id : 0;
	?>
	<section class="product-details" aria-labelledby="product-details-title">
		<h2 id="product-details-title" class="section__title"><?php esc_html_e( 'About this print', 'atelier-mira' ); ?></h2>
		<div class="product-details__grid">
			<div class="product-details__text">
				<?php echo wp_kses_post( wpautop( $product->get_description() ) ); ?>
				<?php if ( $artwork ) : ?>
					<p><a class="link-arrow" href="<?php echo esc_url( get_permalink( $artwork ) ); ?>"><?php esc_html_e( 'See the original in the portfolio', 'atelier-mira' ); ?></a></p>
				<?php endif; ?>
			</div>
			<dl class="studio__facts product-details__facts">
				<div><dt><?php esc_html_e( 'Paper', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( '310 g cotton rag, matt', 'atelier-mira' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Inks', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Archival pigments, 100+ years', 'atelier-mira' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Frame', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Solid oak, museum glass, ready to hang', 'atelier-mira' ); ?></dd></div>
				<div><dt><?php esc_html_e( 'Shipping', 'atelier-mira' ); ?></dt><dd><?php esc_html_e( 'Tracked, free from €150', 'atelier-mira' ); ?></dd></div>
			</dl>
		</div>
	</section>
	<?php
}
add_action( 'woocommerce_after_single_product_summary', 'atelier_mira_wc_details', 10 );

/**
 * Related products: three, with a gallery title.
 *
 * @param array<string, mixed> $args Arguments.
 * @return array<string, mixed>
 */
function atelier_mira_wc_related( array $args ): array {
	$args['posts_per_page'] = 3;
	$args['columns']        = 3;

	return $args;
}
add_filter( 'woocommerce_output_related_products_args', 'atelier_mira_wc_related' );

/**
 * Related products heading.
 *
 * @return string
 */
function atelier_mira_wc_related_heading(): string {
	return __( 'You might also like', 'atelier-mira' );
}
add_filter( 'woocommerce_product_related_products_heading', 'atelier_mira_wc_related_heading' );

/**
 * Format attributes are chosen with buttons: drop WooCommerce's "Clear" link.
 *
 * @return string
 */
function atelier_mira_wc_reset_link(): string {
	return '';
}
add_filter( 'woocommerce_reset_variations_link', 'atelier_mira_wc_reset_link' );

// Cart drawer.

/**
 * Header cart button. A link to the cart page without JavaScript; cart.js turns it
 * into the drawer toggle.
 *
 * @return void
 */
function atelier_mira_wc_cart_button(): void {
	$count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	?>
	<a class="cart-button" href="<?php echo esc_url( wc_get_cart_url() ); ?>" data-cart-open aria-haspopup="dialog">
		<svg aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="20" height="20"><path d="M5 8h14l-1.2 12H6.2L5 8Z" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M9 8V6a3 3 0 0 1 6 0v2" fill="none" stroke="currentColor" stroke-width="1.5"/></svg>
		<span class="cart-button__label"><?php esc_html_e( 'Cart', 'atelier-mira' ); ?></span>
		<?php atelier_mira_wc_cart_count( $count ); ?>
	</a>
	<?php
}
add_action( 'atelier_mira_header_actions', 'atelier_mira_wc_cart_button' );

/**
 * Cart count badge (also a cart fragment).
 *
 * @param int $count Items.
 * @return void
 */
function atelier_mira_wc_cart_count( int $count ): void {
	printf(
		'<span class="cart-count" data-count="%1$d"><span aria-hidden="true">%1$d</span><span class="screen-reader-text">%2$s</span></span>',
		(int) $count,
		/* translators: %d: number of items. */
		esc_html( sprintf( _n( '%d item', '%d items', $count, 'atelier-mira' ), $count ) )
	);
}

/**
 * Drawer content (also a cart fragment).
 *
 * @return void
 */
function atelier_mira_wc_drawer_content(): void {
	echo '<div class="cart-drawer__content">';
	woocommerce_mini_cart();
	echo '</div>';
}

/**
 * The drawer itself, after the footer.
 *
 * @return void
 */
function atelier_mira_wc_drawer(): void {
	if ( is_cart() || is_checkout() ) {
		return;
	}
	?>
	<dialog class="cart-drawer" aria-labelledby="cart-drawer-title" data-cart-drawer>
		<div class="cart-drawer__panel">
			<header class="cart-drawer__head">
				<h2 id="cart-drawer-title" class="cart-drawer__title"><?php esc_html_e( 'Your cart', 'atelier-mira' ); ?></h2>
				<button type="button" class="cart-drawer__close" data-cart-close>
					<span aria-hidden="true">×</span>
					<span class="screen-reader-text"><?php esc_html_e( 'Close the cart', 'atelier-mira' ); ?></span>
				</button>
			</header>
			<p class="cart-drawer__status" role="status" aria-live="polite" data-cart-status></p>
			<?php atelier_mira_wc_drawer_content(); ?>
		</div>
	</dialog>
	<?php
}
add_action( 'atelier_mira_after_footer', 'atelier_mira_wc_drawer' );

/**
 * Cart fragments: header count and drawer content.
 *
 * @param array<string, string> $fragments Fragments (selector => HTML).
 * @return array<string, string>
 */
function atelier_mira_wc_fragments( array $fragments ): array {
	ob_start();
	atelier_mira_wc_cart_count( WC()->cart->get_cart_contents_count() );
	$fragments['span.cart-count'] = (string) ob_get_clean();

	ob_start();
	atelier_mira_wc_drawer_content();
	$fragments['div.cart-drawer__content'] = (string) ob_get_clean();

	return $fragments;
}
add_filter( 'woocommerce_add_to_cart_fragments', 'atelier_mira_wc_fragments' );

/**
 * AJAX add to cart for any product type (`?wc-ajax=mira_add_to_cart`), used by cart.js
 * for the product page form and the shop cards. Answers with the cart fragments, or
 * with WooCommerce's error notices.
 *
 * @return void
 */
function atelier_mira_wc_ajax_add_to_cart(): void {
	// phpcs:disable WordPress.Security.NonceVerification.Missing -- Same contract as WooCommerce's own add-to-cart endpoints.
	$product_id   = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
	$variation_id = isset( $_POST['variation_id'] ) ? absint( wp_unslash( $_POST['variation_id'] ) ) : 0;
	$quantity     = isset( $_POST['quantity'] ) ? max( 1, wc_stock_amount( sanitize_text_field( wp_unslash( $_POST['quantity'] ) ) ) ) : 1;
	$attributes   = array();

	foreach ( $_POST as $key => $value ) {
		if ( is_string( $key ) && str_starts_with( $key, 'attribute_' ) ) {
			$attributes[ sanitize_title( $key ) ] = wc_clean( wp_unslash( $value ) );
		}
	}
	// phpcs:enable

	$product = wc_get_product( $product_id );

	if ( ! $product || 'publish' !== $product->get_status() ) {
		wp_send_json_error( array( 'messages' => array( __( 'This print is not available.', 'atelier-mira' ) ) ), 404 );
	}

	wc_clear_notices();

	$valid = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $attributes ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core filter.
	$added = $valid ? WC()->cart->add_to_cart( $product_id, $quantity, $variation_id, $attributes ) : false;

	if ( ! $added ) {
		$messages = array_map( static fn( $notice ) => wp_strip_all_tags( $notice['notice'] ), wc_get_notices( 'error' ) );
		wc_clear_notices();
		wp_send_json_error( array( 'messages' => $messages ? $messages : array( __( 'This print could not be added.', 'atelier-mira' ) ) ), 422 );
	}

	wc_clear_notices();
	do_action( 'woocommerce_ajax_added_to_cart', $product_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core action.
	WC_AJAX::get_refreshed_fragments();
}
add_action( 'wc_ajax_mira_add_to_cart', 'atelier_mira_wc_ajax_add_to_cart' );

/**
 * Mini cart buttons: "View cart" quiet, "Checkout" primary.
 *
 * @return void
 */
function atelier_mira_wc_mini_cart_buttons(): void {
	printf( '<a href="%s" class="button button--quiet wc-forward">%s</a>', esc_url( wc_get_cart_url() ), esc_html__( 'View cart', 'atelier-mira' ) );
	printf( '<a href="%s" class="button checkout wc-forward">%s</a>', esc_url( wc_get_checkout_url() ), esc_html__( 'Checkout', 'atelier-mira' ) );
}
remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_proceed_to_checkout', 20 );
add_action( 'woocommerce_widget_shopping_cart_buttons', 'atelier_mira_wc_mini_cart_buttons', 10 );

// Cart and checkout.

remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

/**
 * Reassurance under the checkout button.
 *
 * @return void
 */
function atelier_mira_wc_checkout_note(): void {
	echo '<p class="checkout-note">' . esc_html__( 'Prints are packed flat between boards, framed prints in custom crates. Limited edition numbers are assigned when your order is confirmed.', 'atelier-mira' ) . '</p>';
}
add_action( 'woocommerce_review_order_after_submit', 'atelier_mira_wc_checkout_note' );

/**
 * Card button accessible names start with their visible text (WCAG 2.5.3).
 *
 * @param string     $description Accessible description.
 * @param WC_Product $product     Product.
 * @return string
 */
function atelier_mira_wc_button_label( string $description, $product ): string {
	if ( ! $product instanceof WC_Product ) {
		return $description;
	}

	/* translators: 1: button text, 2: product name. */
	return sprintf( __( '%1$s: %2$s', 'atelier-mira' ), atelier_mira_wc_loop_button( $product->add_to_cart_text(), $product ), $product->get_name() );
}
add_filter( 'woocommerce_product_add_to_cart_description', 'atelier_mira_wc_button_label', 10, 2 );

/**
 * Moves jQuery (needed by WooCommerce's variation and checkout scripts) to the end of the
 * page, where it no longer blocks rendering; jquery-migrate is not needed by any
 * front-end script used here. `wc-single-product` only drives the tabs and the gallery,
 * both replaced by the theme.
 *
 * @return void
 */
function atelier_mira_wc_defer_jquery(): void {
	if ( is_admin() || is_customize_preview() ) {
		return;
	}

	$scripts = wp_scripts();
	foreach ( array( 'jquery', 'jquery-core' ) as $handle ) {
		if ( isset( $scripts->registered[ $handle ] ) ) {
			$scripts->add_data( $handle, 'group', 1 );
		}
	}
	if ( isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array( 'jquery-core' );
	}

	wp_dequeue_script( 'wc-single-product' );
}
add_action( 'wp_enqueue_scripts', 'atelier_mira_wc_defer_jquery', 100 );
