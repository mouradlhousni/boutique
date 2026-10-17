<?php
/**
 * Plugin Name:          Mira Studio
 * Plugin URI:           https://github.com/mouradlhousni/boutique
 * Description:          Business features of the Atelier Mira art studio: artwork portfolio, numbered limited editions, gift messages and commission requests on top of WooCommerce, plus WP-CLI seeding.
 * Version:              1.0.0
 * Requires at least:    6.5
 * Requires PHP:         8.1
 * Requires Plugins:     woocommerce
 * WC requires at least: 9.0
 * WC tested up to:      11.1
 * Author:               Atelier Mira
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:          mira-studio
 *
 * @package AtelierMira\Studio
 */

defined( 'ABSPATH' ) || exit;

define( 'MIRA_STUDIO_VERSION', '1.0.0' );
define( 'MIRA_STUDIO_FILE', __FILE__ );
define( 'MIRA_STUDIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'MIRA_STUDIO_URL', plugin_dir_url( __FILE__ ) );

/*
 * Minimal PSR-4 autoloader for the AtelierMira\Studio namespace: the plugin ships
 * without a vendor folder so it can be installed straight from Git (Playground).
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'AtelierMira\\Studio\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$file = MIRA_STUDIO_DIR . 'src/' . str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) ) . '.php';

		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

require_once MIRA_STUDIO_DIR . 'includes/functions.php';

/*
 * Declare compatibility with WooCommerce features: High-Performance Order Storage
 * (orders are only read and written through the WC_Order CRUD API) and the
 * Cart & Checkout blocks (the gift message is also registered as a block checkout field).
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

register_activation_hook( __FILE__, array( \AtelierMira\Studio\Plugin::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \AtelierMira\Studio\Plugin::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \AtelierMira\Studio\Plugin::class, 'instance' ) );
