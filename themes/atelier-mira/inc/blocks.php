<?php
/**
 * Custom blocks.
 *
 * Sources live in blocks/, the compiled versions in build/blocks/ (`npm run build:blocks`).
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "Artwork Series Showcase" block from its compiled metadata.
 *
 * @return void
 */
function atelier_mira_register_blocks(): void {
	$path = ATELIER_MIRA_DIR . '/build/blocks/series-showcase';

	if ( file_exists( $path . '/block.json' ) ) {
		register_block_type( $path );
	}
}
add_action( 'init', 'atelier_mira_register_blocks' );
