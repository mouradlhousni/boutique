<?php
/**
 * Image sizes, WebP output and loading priorities.
 *
 * Size budget (every size multiplies upload time, which matters for the Playground seed):
 * - thumbnail 150 (admin), medium 300 and medium_large 768 (srcset steps);
 * - WooCommerce: thumbnail 450 (shop cards), single 900 (product page), gallery 150 (crop);
 * - `mira-xl` 1600 for the hero and the zoom; core's 1536/2048 sizes are removed.
 * `large` is set to 1200 on theme activation. All are uncropped except the 150 squares,
 * so every size of an image shares its aspect ratio and lands in its srcset.
 *
 * @package AtelierMira\Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the theme size.
 *
 * @return void
 */
function atelier_mira_image_sizes(): void {
	add_image_size( 'mira-xl', 1600, 1600, false );
}
add_action( 'after_setup_theme', 'atelier_mira_image_sizes' );

/**
 * Drops core's very large sizes: artworks are at most 1600 px wide here.
 *
 * @param array<string, array<string, mixed>> $sizes Sizes to generate.
 * @return array<string, array<string, mixed>>
 */
function atelier_mira_intermediate_sizes( array $sizes ): array {
	unset( $sizes['1536x1536'], $sizes['2048x2048'] );

	return $sizes;
}
add_filter( 'intermediate_image_sizes_advanced', 'atelier_mira_intermediate_sizes' );

/**
 * Sets `large` to 1200 px when the theme is activated.
 *
 * @return void
 */
function atelier_mira_activate(): void {
	update_option( 'large_size_w', 1200 );
	update_option( 'large_size_h', 1200 );
	update_option( 'medium_large_size_w', 768 );
	update_option( 'woocommerce_thumbnail_cropping', 'uncropped' );
}
add_action( 'after_switch_theme', 'atelier_mira_activate' );

/**
 * JPEG uploads get WebP sub-sizes (smaller files, same quality).
 *
 * @param array<string, string> $formats Mime type map.
 * @return array<string, string>
 */
function atelier_mira_webp_output( array $formats ): array {
	if ( wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
		$formats['image/jpeg'] = 'image/webp';
	}

	return $formats;
}
add_filter( 'image_editor_output_format', 'atelier_mira_webp_output' );

/**
 * WebP quality.
 *
 * @param int    $quality Quality.
 * @param string $mime    Mime type.
 * @return int
 */
function atelier_mira_image_quality( int $quality, string $mime = '' ): int {
	return 'image/webp' === $mime ? 80 : $quality;
}
add_filter( 'wp_editor_set_quality', 'atelier_mira_image_quality', 10, 2 );

/**
 * Responsive image tag with explicit sizes and loading priority.
 *
 * @param int    $attachment_id Attachment.
 * @param string $size          Largest size to use.
 * @param string $sizes         `sizes` attribute.
 * @param bool   $priority      True for the LCP image (eager + fetchpriority high).
 * @param array  $attr          Extra attributes.
 * @return string
 */
function atelier_mira_image( int $attachment_id, string $size, string $sizes, bool $priority = false, array $attr = array() ): string {
	if ( ! $attachment_id ) {
		return '';
	}

	$attr = array_merge(
		array(
			'sizes'    => $sizes,
			'loading'  => $priority ? 'eager' : 'lazy',
			'decoding' => $priority ? 'sync' : 'async',
		),
		$attr
	);

	if ( $priority ) {
		$attr['fetchpriority'] = 'high';
	}

	return wp_get_attachment_image( $attachment_id, $size, false, $attr );
}
