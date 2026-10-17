<?php
/**
 * Atelier Mira theme bootstrap.
 *
 * The theme handles presentation only. Business features (portfolio data, editions,
 * commissions) live in the Mira Studio plugin and are reached through its `mira_*()`
 * template functions, so every call is guarded with function_exists().
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

define( 'ATELIER_MIRA_VERSION', '1.0.0' );
define( 'ATELIER_MIRA_DIR', get_template_directory() );
define( 'ATELIER_MIRA_URL', get_template_directory_uri() );

require ATELIER_MIRA_DIR . '/inc/setup.php';
require ATELIER_MIRA_DIR . '/inc/images.php';
require ATELIER_MIRA_DIR . '/inc/assets.php';
require ATELIER_MIRA_DIR . '/inc/template-tags.php';
require ATELIER_MIRA_DIR . '/inc/customizer.php';
require ATELIER_MIRA_DIR . '/inc/blocks.php';
require ATELIER_MIRA_DIR . '/inc/static-build.php';

if ( class_exists( 'WooCommerce' ) ) {
	require ATELIER_MIRA_DIR . '/inc/woocommerce.php';
}
