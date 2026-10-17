<?php
/**
 * Plugin bootstrap.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every component of the plugin.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Returns (and boots on first call) the plugin instance.
	 *
	 * @return Plugin
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}

		return self::$instance;
	}

	/**
	 * Registers the hooks of every component.
	 *
	 * @return void
	 */
	private function boot(): void {
		add_action(
			'init',
			static function () {
				load_plugin_textdomain( 'mira-studio', false, dirname( plugin_basename( MIRA_STUDIO_FILE ) ) . '/languages' );
			}
		);

		foreach ( $this->components() as $component ) {
			$component->register_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			Cli\Command::register();
		}
	}

	/**
	 * Components with a `register_hooks()` method.
	 *
	 * @return array<int, object>
	 */
	private function components(): array {
		$components = array(
			new Portfolio\PostTypes(),
			new Commissions\PostType(),
			new Commissions\Form(),
			new Commissions\Uploads(),
			new Frontend\DemoMode(),
			new Frontend\StaticBuild(),
		);

		if ( mira_has_woocommerce() ) {
			$components[] = new Shop\LimitedEditions();
			$components[] = new Shop\GiftMessage();
			$components[] = new Shop\PrintFormats();
			$components[] = new Shop\Shipping();
		}

		if ( is_admin() ) {
			$components[] = new Portfolio\Admin();
			$components[] = new Commissions\Admin();
		}

		return $components;
	}

	/**
	 * Activation: content types and rewrite rules.
	 *
	 * @return void
	 */
	public static function activate(): void {
		Portfolio\PostTypes::register();
		Commissions\PostType::register();
		flush_rewrite_rules();
	}

	/**
	 * Deactivation: rewrite rules only. Data is kept.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
