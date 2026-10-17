<?php
/**
 * Theme supports, menus and small front-end clean-ups.
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Theme supports and menus.
 *
 * @return void
 */
function atelier_mira_setup(): void {
	load_theme_textdomain( 'atelier-mira', ATELIER_MIRA_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'editor-styles' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_editor_style( 'assets/css/editor.css' );

	add_theme_support(
		'woocommerce',
		array(
			'thumbnail_image_width'         => 450,
			'single_image_width'            => 900,
			'gallery_thumbnail_image_width' => 150,
			'product_grid'                  => array(
				'default_rows'    => 4,
				'min_rows'        => 1,
				'default_columns' => 3,
				'min_columns'     => 2,
				'max_columns'     => 4,
			),
		)
	);

	register_nav_menus(
		array(
			'primary' => __( 'Primary menu', 'atelier-mira' ),
			'footer'  => __( 'Footer menu', 'atelier-mira' ),
		)
	);

	add_theme_support(
		'editor-color-palette',
		array(
			array(
				'name'  => __( 'Ink', 'atelier-mira' ),
				'slug'  => 'ink',
				'color' => '#1a1917',
			),
			array(
				'name'  => __( 'Label grey', 'atelier-mira' ),
				'slug'  => 'label',
				'color' => '#5b574f',
			),
			array(
				'name'  => __( 'Wall', 'atelier-mira' ),
				'slug'  => 'wall',
				'color' => '#faf8f3',
			),
			array(
				'name'  => __( 'Mat', 'atelier-mira' ),
				'slug'  => 'mat',
				'color' => '#ebe5d8',
			),
			array(
				'name'  => __( 'Ochre', 'atelier-mira' ),
				'slug'  => 'ochre',
				'color' => '#7a5f22',
			),
			array(
				'name'  => __( 'Night', 'atelier-mira' ),
				'slug'  => 'night',
				'color' => '#161513',
			),
		)
	);
}
add_action( 'after_setup_theme', 'atelier_mira_setup' );

/**
 * Front-end clean-up: no emoji script, no generator tag, no RSD/WLW links.
 *
 * @return void
 */
function atelier_mira_cleanup(): void {
	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_generator' );
	remove_action( 'wp_head', 'wlwmanifest_link' );
	remove_action( 'wp_head', 'rsd_link' );
}
add_action( 'init', 'atelier_mira_cleanup' );

/**
 * The portfolio shows every artwork on one page: the series filter works without reloading.
 *
 * @param WP_Query $query Main query.
 * @return void
 */
function atelier_mira_portfolio_query( WP_Query $query ): void {
	if ( is_admin() || ! $query->is_main_query() ) {
		return;
	}

	if ( $query->is_post_type_archive( 'artwork' ) || $query->is_tax( 'artwork_series' ) ) {
		$query->set( 'posts_per_page', 100 );
		$query->set(
			'orderby',
			array(
				'menu_order' => 'ASC',
				'date'       => 'DESC',
			)
		);
		$query->set( 'no_found_rows', true );
	}
}
add_action( 'pre_get_posts', 'atelier_mira_portfolio_query' );

/**
 * A series page is the portfolio with that series pre-selected.
 *
 * @param string $template Template.
 * @return string
 */
function atelier_mira_series_template( string $template ): string {
	if ( is_tax( 'artwork_series' ) ) {
		$portfolio = locate_template( 'archive-artwork.php' );
		return $portfolio ? $portfolio : $template;
	}

	return $template;
}
add_filter( 'template_include', 'atelier_mira_series_template' );

/**
 * Body classes.
 *
 * @param array<int, string> $classes Classes.
 * @return array<int, string>
 */
function atelier_mira_body_class( array $classes ): array {
	// The header floats over the hero photo (front-page.php renders it when there is one).
	if ( is_front_page() && atelier_mira_hero_image_id() ) {
		$classes[] = 'has-hero';
	}

	return $classes;
}
add_filter( 'body_class', 'atelier_mira_body_class' );

/**
 * Shorter, quieter excerpts.
 *
 * @return string
 */
function atelier_mira_excerpt_more(): string {
	return '…';
}
add_filter( 'excerpt_more', 'atelier_mira_excerpt_more' );

/**
 * "Portfolio" rather than the post type name in the archive's <title>.
 *
 * @param array<string, string> $parts Title parts.
 * @return array<string, string>
 */
function atelier_mira_title_parts( array $parts ): array {
	if ( is_post_type_archive( 'artwork' ) ) {
		$parts['title'] = __( 'Portfolio', 'atelier-mira' );
	}

	return $parts;
}
add_filter( 'document_title_parts', 'atelier_mira_title_parts' );

/**
 * Meta description: excerpt, term description or a page-specific text (160 characters max).
 *
 * @return void
 */
function atelier_mira_meta_description(): void {
	$text = '';

	if ( is_front_page() ) {
		$text = __( 'Atelier Mira, an illustration studio in Marseille: tides, balcony gardens and cities after dark, as art prints, signed limited editions and commissions.', 'atelier-mira' );
	} elseif ( is_post_type_archive( 'artwork' ) ) {
		$text = __( 'Portfolio of Atelier Mira: three ongoing series in gouache, cut paper and ink, 2022–2025.', 'atelier-mira' );
	} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
		$text = __( 'Art prints in A4, A3 and A2, framed or not, and numbered limited editions signed by hand. Shipped from Marseille.', 'atelier-mira' );
	} elseif ( is_category() || is_tag() || is_tax() ) {
		$text = term_description();
	} elseif ( is_singular() ) {
		$post = get_post();
		$text = $post && 'product' === $post->post_type && function_exists( 'wc_get_product' )
			? wc_get_product( $post )->get_short_description()
			: get_the_excerpt();
	}

	$text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $text ) ) );

	if ( '' === $text ) {
		$text = (string) get_bloginfo( 'description' );
	}

	printf( '<meta name="description" content="%s">' . "\n", esc_attr( wp_html_excerpt( $text, 160, '…' ) ) );
}
add_action( 'wp_head', 'atelier_mira_meta_description', 1 );
