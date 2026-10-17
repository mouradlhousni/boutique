<?php
/**
 * Static export mode (plugin side).
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * While MIRA_STATIC_BUILD is on (only during bin/export-static.sh), the pages carry no
 * link to dynamic endpoints: no REST/oEmbed/RSD discovery links, no shortlinks, no admin
 * bar. The theme handles the visible changes (data-static-build on <body>, shop buttons).
 */
final class StaticBuild {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		if ( ! mira_is_static_build() ) {
			return;
		}

		remove_action( 'wp_head', 'rest_output_link_wp_head' );
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'rsd_link' );
		remove_action( 'wp_head', 'wlwmanifest_link' );
		remove_action( 'wp_head', 'wp_shortlink_wp_head' );
		remove_action( 'wp_head', 'feed_links', 2 );
		remove_action( 'wp_head', 'feed_links_extra', 3 );
		remove_action( 'template_redirect', 'rest_output_link_header', 11 );
		remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
		add_filter( 'show_admin_bar', '__return_false' );
	}
}
