<?php
/**
 * Portfolio demo mode.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Everything MIRA_DEMO_MODE changes outside the forms (which short-circuit themselves):
 *
 * - a discreet "Demo site" banner on the front end and a notice in the admin
 *   (the static export shows its own banner, pointing to the live demo);
 * - checkout limited to the test gateway (Cash on delivery);
 * - every WooCommerce email disabled, and `wp_mail()` short-circuited as a safety net,
 *   so nothing ever leaves the site.
 *
 * The real code paths stay intact: with the constant off, nothing here runs.
 */
final class DemoMode {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_body_open', array( $this, 'banner' ), 5 );

		if ( ! mira_is_demo_mode() ) {
			return;
		}

		add_action( 'admin_notices', array( $this, 'admin_notice' ) );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'gateways' ) );
		add_action( 'woocommerce_email', array( $this, 'disable_emails' ) );
		add_filter( 'pre_wp_mail', array( $this, 'block_mail' ), 999 );
	}

	/**
	 * Front-end banner.
	 *
	 * @return void
	 */
	public function banner(): void {
		if ( mira_is_static_build() ) {
			printf(
				'<p class="mira-demo-banner" role="note">%s <a href="%s" rel="noopener">%s</a></p>',
				esc_html__( 'Static preview of a portfolio project.', 'mira-studio' ),
				esc_url( mira_playground_url() ),
				esc_html__( 'Try the shop live in WordPress Playground', 'mira-studio' )
			);
			return;
		}

		if ( mira_is_demo_mode() ) {
			printf(
				'<p class="mira-demo-banner" role="note"><strong>%s</strong> %s</p>',
				esc_html__( 'Demo site.', 'mira-studio' ),
				esc_html__( 'Pay with “Cash on delivery”: orders work end to end, no email is sent and forms store nothing.', 'mira-studio' )
			);
		}
	}

	/**
	 * Admin notice.
	 *
	 * @return void
	 */
	public function admin_notice(): void {
		printf(
			'<div class="notice notice-info"><p><strong>%s</strong> %s</p></div>',
			esc_html__( 'Demo mode is on.', 'mira-studio' ),
			esc_html__( 'Forms validate normally but store nothing, commission status changes are not saved, uploads are refused and no email is sent (MIRA_DEMO_MODE in wp-config.php).', 'mira-studio' )
		);
	}

	/**
	 * Only the test gateway at checkout.
	 *
	 * @param array<string, \WC_Payment_Gateway> $gateways Available gateways.
	 * @return array<string, \WC_Payment_Gateway>
	 */
	public function gateways( array $gateways ): array {
		return array_intersect_key( $gateways, array( 'cod' => true ) );
	}

	/**
	 * Disables every WooCommerce email.
	 *
	 * @param \WC_Emails $mailer Mailer.
	 * @return void
	 */
	public function disable_emails( $mailer ): void {
		foreach ( $mailer->get_emails() as $email ) {
			add_filter( 'woocommerce_email_enabled_' . $email->id, '__return_false' );
		}
	}

	/**
	 * Short-circuits wp_mail(): reported as handled, never sent.
	 *
	 * @return bool
	 */
	public function block_mail(): bool {
		return true;
	}
}
