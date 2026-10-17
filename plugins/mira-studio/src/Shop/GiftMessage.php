<?php
/**
 * Gift message at checkout.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Optional gift message, stored in the `_mira_gift_message` order meta (HPOS-safe CRUD).
 *
 * - Classic (shortcode) checkout: field after the order notes, validated and saved with checkout hooks.
 * - Block checkout: registered with the Additional Checkout Fields API and mirrored into the same meta.
 * - Shown in the order admin screen, in every order email (HTML and plain text), on the
 *   thank-you page and in My account.
 */
final class GiftMessage {

	public const META       = '_mira_gift_message';
	public const FIELD      = 'mira_gift_message';
	public const BLOCK_ID   = 'mira/gift-message';
	public const MAX_LENGTH = 300;

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'woocommerce_after_order_notes', array( $this, 'field' ) );
		add_action( 'woocommerce_after_checkout_validation', array( $this, 'validate' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save' ) );

		add_action( 'woocommerce_init', array( $this, 'register_block_field' ) );
		add_action( 'woocommerce_set_additional_field_value', array( $this, 'save_block_field' ), 10, 4 );

		add_action( 'woocommerce_admin_order_data_after_shipping_address', array( $this, 'admin' ) );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'email' ), 10, 3 );
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'customer' ) );
	}

	/**
	 * Classic checkout field.
	 *
	 * @param \WC_Checkout $checkout Checkout.
	 * @return void
	 */
	public function field( $checkout ): void {
		woocommerce_form_field(
			self::FIELD,
			array(
				'type'              => 'textarea',
				'class'             => array( 'form-row-wide', 'mira-gift-message' ),
				'label'             => __( 'Gift message', 'mira-studio' ),
				'placeholder'       => __( 'Happy birthday, Lou! x', 'mira-studio' ),
				'description'       => __( 'Optional. Handwritten on a card; prices are left out of the parcel.', 'mira-studio' ),
				'required'          => false,
				'maxlength'         => self::MAX_LENGTH,
				'custom_attributes' => array( 'rows' => '3' ),
			),
			$checkout instanceof \WC_Checkout ? $checkout->get_value( self::FIELD ) : ''
		);
	}

	/**
	 * Validates the classic field.
	 *
	 * @param array<string, mixed> $data   Posted data.
	 * @param \WP_Error            $errors Errors.
	 * @return void
	 */
	public function validate( $data, $errors ): void {
		if ( mb_strlen( $this->posted() ) > self::MAX_LENGTH ) {
			/* translators: %d: maximum length. */
			$errors->add( 'mira_gift_message', sprintf( __( 'The gift message is limited to %d characters.', 'mira-studio' ), self::MAX_LENGTH ) );
		}
	}

	/**
	 * Saves the classic field on the new order.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function save( $order ): void {
		$message = $this->posted();

		if ( '' !== $message ) {
			$order->update_meta_data( self::META, mb_substr( $message, 0, self::MAX_LENGTH ) );
		}
	}

	/**
	 * Registers the field for the Checkout block (WooCommerce 8.9+).
	 *
	 * @return void
	 */
	public function register_block_field(): void {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'                => self::BLOCK_ID,
				'label'             => __( 'Gift message (optional)', 'mira-studio' ),
				'optionalLabel'     => __( 'Gift message (optional)', 'mira-studio' ),
				'location'          => 'order',
				'type'              => 'text',
				'required'          => false,
				'sanitize_callback' => static fn( $value ) => mb_substr( sanitize_text_field( (string) $value ), 0, self::MAX_LENGTH ),
			)
		);
	}

	/**
	 * Mirrors the block field into the shared meta, so every display hook reads one key.
	 *
	 * @param string   $key    Field ID.
	 * @param mixed    $value  Value.
	 * @param string   $group  Group.
	 * @param \WC_Data $wc_object Order (or customer).
	 * @return void
	 */
	public function save_block_field( $key, $value, $group, $wc_object ): void {
		if ( self::BLOCK_ID === $key && $wc_object instanceof \WC_Order && '' !== (string) $value ) {
			$wc_object->update_meta_data( self::META, (string) $value );
		}
	}

	/**
	 * Admin order screen.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function admin( $order ): void {
		$message = self::displayable( $order );

		if ( '' !== $message ) {
			printf(
				'<div class="mira-gift-message"><h3>%s</h3>%s</div>',
				esc_html__( 'Gift message', 'mira-studio' ),
				wp_kses_post( wpautop( esc_html( $message ) ) )
			);
		}
	}

	/**
	 * Order emails (HTML and plain text).
	 *
	 * @param \WC_Order $order         Order.
	 * @param bool      $sent_to_admin Whether the email goes to the shop.
	 * @param bool      $plain_text    Plain text email.
	 * @return void
	 */
	public function email( $order, $sent_to_admin, $plain_text ): void {
		$message = self::displayable( $order );

		if ( '' === $message ) {
			return;
		}

		if ( $plain_text ) {
			echo "\n" . esc_html( mb_strtoupper( __( 'Gift message', 'mira-studio' ) ) ) . "\n" . esc_html( $message ) . "\n\n";
			return;
		}

		printf(
			'<h2>%s</h2><blockquote style="margin:0 0 24px;padding:12px 16px;border-left:3px solid #c4532f;font-style:italic;">%s</blockquote>',
			esc_html__( 'Gift message', 'mira-studio' ),
			wp_kses_post( nl2br( esc_html( $message ) ) )
		);
	}

	/**
	 * Thank-you page and My account order view.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function customer( $order ): void {
		$message = self::displayable( $order );

		if ( '' !== $message ) {
			printf(
				'<section class="mira-gift-message"><h2>%s</h2><blockquote>%s</blockquote></section>',
				esc_html__( 'Gift message', 'mira-studio' ),
				wp_kses_post( wpautop( esc_html( $message ) ) )
			);
		}
	}

	/**
	 * Gift message of an order.
	 *
	 * @param \WC_Order|mixed $order Order.
	 * @return string
	 */
	public static function get( $order ): string {
		return $order instanceof \WC_Order ? (string) $order->get_meta( self::META ) : '';
	}

	/**
	 * Message to display ourselves: WooCommerce already prints block checkout fields.
	 *
	 * @param \WC_Order|mixed $order Order.
	 * @return string
	 */
	private static function displayable( $order ): string {
		if ( ! $order instanceof \WC_Order || '' !== (string) $order->get_meta( '_wc_other/' . self::BLOCK_ID ) ) {
			return '';
		}

		return self::get( $order );
	}

	/**
	 * Posted classic checkout value (the checkout nonce is verified by WooCommerce).
	 *
	 * @return string
	 */
	private function posted(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WC_Checkout::process_checkout().
		return isset( $_POST[ self::FIELD ] ) ? trim( sanitize_textarea_field( wp_unslash( $_POST[ self::FIELD ] ) ) ) : '';
	}
}
