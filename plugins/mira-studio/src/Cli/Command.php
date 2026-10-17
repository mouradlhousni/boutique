<?php
/**
 * WP-CLI commands.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Cli;

defined( 'ABSPATH' ) || exit;

/**
 * Manages the Atelier Mira demo content.
 */
final class Command {

	/**
	 * Registers `wp mira`.
	 *
	 * @return void
	 */
	public static function register(): void {
		\WP_CLI::add_command( 'mira', self::class );
	}

	/**
	 * Seeds the demo studio: series, artworks, prints, limited editions, orders and commissions.
	 *
	 * Images are painted locally with GD (WebP when supported). Running the command again
	 * replaces the previously seeded content and leaves everything else untouched.
	 *
	 * ## OPTIONS
	 *
	 * [--images=<count>]
	 * : Unique artwork images to paint (1–20). Artworks beyond reuse them; lower it for WordPress Playground.
	 * ---
	 * default: 20
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp mira seed
	 *     wp mira seed --images=8
	 *
	 * @param array<int, string>    $args       Positional arguments.
	 * @param array<string, string> $assoc_args Options.
	 * @return void
	 */
	public function seed( array $args, array $assoc_args ): void {
		if ( ! extension_loaded( 'gd' ) ) {
			\WP_CLI::error( 'The GD extension is required to paint the demo images.' );
		}

		$start  = microtime( true );
		$seeder = new Seeder(
			min( 20, max( 1, (int) ( $assoc_args['images'] ?? 20 ) ) ),
			static function ( string $message ) {
				\WP_CLI::log( '  ' . $message );
			}
		);

		$seeder->run();

		\WP_CLI::success( sprintf( 'Atelier Mira seeded in %.1fs.', microtime( true ) - $start ) );
	}
}
