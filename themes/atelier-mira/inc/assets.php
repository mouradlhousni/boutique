<?php
/**
 * Styles and scripts.
 *
 * One small global stylesheet, a shop stylesheet only where WooCommerce renders, and
 * page-specific vanilla scripts loaded with `defer`. Minified files are used unless
 * SCRIPT_DEBUG is on (`npm run build:assets` regenerates them).
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * URL of a theme asset, minified unless SCRIPT_DEBUG.
 *
 * @param string $path Path under assets/, e.g. "css/main.css".
 * @return string
 */
function atelier_mira_asset( string $path ): string {
	return ATELIER_MIRA_URL . '/assets/' . atelier_mira_asset_file( $path );
}

/**
 * Version string of a theme asset: the modification time of the file actually served,
 * so browsers fetch it again after each `npm run build:assets`.
 *
 * @param string $path Path under assets/, e.g. "css/main.css".
 * @return string
 */
function atelier_mira_asset_version( string $path ): string {
	$mtime = filemtime( ATELIER_MIRA_DIR . '/assets/' . atelier_mira_asset_file( $path ) );

	return $mtime ? (string) $mtime : ATELIER_MIRA_VERSION;
}

/**
 * Path under assets/ of the file served for an asset: minified unless SCRIPT_DEBUG.
 *
 * @param string $path Path under assets/, e.g. "css/main.css".
 * @return string
 */
function atelier_mira_asset_file( string $path ): string {
	$min = preg_replace( '/\.(css|js)$/', '.min.$1', $path );

	if ( ! ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) && file_exists( ATELIER_MIRA_DIR . '/assets/' . $min ) ) {
		return $min;
	}

	return $path;
}

/**
 * Enqueues the front-end assets.
 *
 * @return void
 */
function atelier_mira_enqueue(): void {
	wp_enqueue_style( 'atelier-mira', atelier_mira_asset( 'css/main.css' ), array(), atelier_mira_asset_version( 'css/main.css' ) );

	$defer = array(
		'strategy'  => 'defer',
		'in_footer' => true,
	);

	wp_enqueue_script( 'atelier-mira', atelier_mira_asset( 'js/theme.js' ), array(), atelier_mira_asset_version( 'js/theme.js' ), $defer );
	wp_localize_script(
		'atelier-mira',
		'atelierMira',
		array(
			'i18n' => array(
				'newsletter' => __( 'Thank you! This is a demo site: your address was not sent anywhere.', 'atelier-mira' ),
				/* translators: %d: number of works. */
				'works'      => __( '%d works', 'atelier-mira' ),
				'work'       => __( '1 work', 'atelier-mira' ),
			),
		)
	);

	if ( is_post_type_archive( 'artwork' ) || is_tax( 'artwork_series' ) ) {
		wp_enqueue_script( 'atelier-mira-portfolio', atelier_mira_asset( 'js/portfolio.js' ), array( 'atelier-mira' ), atelier_mira_asset_version( 'js/portfolio.js' ), $defer );
	}
}
add_action( 'wp_enqueue_scripts', 'atelier_mira_enqueue' );

/**
 * Preloads the two fonts used above the fold.
 *
 * @return void
 */
function atelier_mira_preload_fonts(): void {
	foreach ( array( 'playfair-display.woff2', 'hanken-grotesk.woff2' ) as $font ) {
		printf( '<link rel="preload" href="%s" as="font" type="font/woff2" crossorigin>' . "\n", esc_url( ATELIER_MIRA_URL . '/assets/fonts/' . $font ) );
	}
}
add_action( 'wp_head', 'atelier_mira_preload_fonts', 1 );

/**
 * Font faces, inlined so the preloaded files are used before main.css arrives.
 *
 * @return void
 */
function atelier_mira_font_faces(): void {
	$base = esc_url( ATELIER_MIRA_URL . '/assets/fonts/' );
	?>
	<style id="atelier-mira-fonts">
		@font-face{font-family:"Playfair Display";font-style:normal;font-weight:400 900;font-display:swap;src:url(<?php echo $base; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>playfair-display.woff2) format("woff2")}
		@font-face{font-family:"Playfair Display";font-style:italic;font-weight:400 900;font-display:swap;src:url(<?php echo $base; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>playfair-display-italic.woff2) format("woff2")}
		@font-face{font-family:"Hanken Grotesk";font-style:normal;font-weight:100 900;font-display:swap;src:url(<?php echo $base; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped above. ?>hanken-grotesk.woff2) format("woff2")}
	</style>
	<?php
}
add_action( 'wp_head', 'atelier_mira_font_faces', 2 );

/**
 * Block library styles: only on content built with blocks, as a separate light file.
 *
 * @return void
 */
function atelier_mira_trim_block_styles(): void {
	if ( is_singular() && has_blocks( get_post() ) ) {
		return;
	}

	wp_dequeue_style( 'wp-block-library' );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'global-styles' );
	wp_dequeue_style( 'classic-theme-styles' );
}
add_action( 'wp_enqueue_scripts', 'atelier_mira_trim_block_styles', 100 );

/**
 * Load only the styles of the blocks present on the page.
 */
add_filter( 'should_load_separate_core_block_assets', '__return_true' );

/**
 * SVG favicon (when no site icon is set).
 *
 * @return void
 */
function atelier_mira_favicon(): void {
	if ( ! has_site_icon() ) {
		printf( '<link rel="icon" href="%s" type="image/svg+xml">' . "\n", esc_url( ATELIER_MIRA_URL . '/assets/images/favicon.svg' ) );
	}
}
add_action( 'wp_head', 'atelier_mira_favicon', 3 );
