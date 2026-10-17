<?php
/**
 * Limited editions on top of WooCommerce.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * - "Limited edition" tab in the product data box (simple and variable products);
 * - numbered prints issued when WooCommerce reduces the order stock, released when it
 *   restores it (cancellation), shown as "Print #13/50" on the order line everywhere
 *   WooCommerce prints item meta (admin, emails, thank-you page, My account);
 * - counter on the product page, badges in product lists, "Sold out" availability text.
 */
final class LimitedEditions {

	private const ITEM_SIZE_META = '_mira_edition_size';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Admin.
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'tab' ) );
		add_action( 'woocommerce_product_data_panels', array( $this, 'panel' ) );
		add_action( 'woocommerce_admin_process_product_object', array( $this, 'save' ) );

		// Orders.
		add_action( 'woocommerce_reduce_order_stock', array( $this, 'issue_numbers' ) );
		add_action( 'woocommerce_restore_order_stock', array( $this, 'release_numbers' ) );
		add_filter( 'woocommerce_order_item_get_formatted_meta_data', array( $this, 'item_meta' ), 10, 2 );

		// Storefront.
		add_action( 'woocommerce_single_product_summary', array( $this, 'counter' ), 15 );
		add_action( 'woocommerce_before_shop_loop_item_title', array( $this, 'badge' ), 9 );
		add_filter( 'woocommerce_get_availability_text', array( $this, 'availability' ), 10, 2 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'cart_item_data' ), 10, 2 );
	}

	/**
	 * Adds the "Limited edition" tab.
	 *
	 * @param array<string, array<string, mixed>> $tabs Tabs.
	 * @return array<string, array<string, mixed>>
	 */
	public function tab( array $tabs ): array {
		$tabs['mira_edition'] = array(
			'label'    => __( 'Limited edition', 'mira-studio' ),
			'target'   => 'mira_edition_data',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 25,
		);

		return $tabs;
	}

	/**
	 * Tab panel.
	 *
	 * @return void
	 */
	public function panel(): void {
		global $product_object;

		$product_id = $product_object instanceof \WC_Product ? $product_object->get_id() : 0;
		$numbers    = $product_id ? Edition::numbers( $product_id ) : array();
		$size       = $product_id ? Edition::size( $product_id ) : 0;
		?>
		<div id="mira_edition_data" class="panel woocommerce_options_panel hidden">
			<div class="options_group">
				<?php
				woocommerce_wp_text_input(
					array(
						'id'                => Edition::SIZE_META,
						'label'             => __( 'Edition size', 'mira-studio' ),
						'type'              => 'number',
						'value'             => $size ? (string) $size : '',
						'placeholder'       => __( 'Open edition', 'mira-studio' ),
						'desc_tip'          => true,
						'description'       => __( 'Number of numbered prints. Leave empty for an open edition. Stock is managed automatically: it always equals the prints left.', 'mira-studio' ),
						'custom_attributes' => array(
							'min'  => '0',
							'step' => '1',
						),
					)
				);
				?>
				<p class="form-field">
					<span class="description">
						<?php
						if ( $size ) {
							printf(
								/* translators: 1: prints sold, 2: edition size, 3: issued numbers. */
								esc_html__( '%1$d of %2$d prints sold. Issued numbers: %3$s', 'mira-studio' ),
								count( $numbers ),
								(int) $size,
								$numbers ? esc_html( implode( ', ', $numbers ) ) : esc_html__( 'none yet', 'mira-studio' )
							);
						} else {
							esc_html_e( 'Prints are numbered automatically when an order reduces the stock (processing, on hold or completed).', 'mira-studio' );
						}
						?>
					</span>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Saves the edition size and aligns the stock with it.
	 *
	 * The nonce and capability are checked by WooCommerce before this action runs.
	 *
	 * @param \WC_Product $product Product being saved.
	 * @return void
	 */
	public function save( \WC_Product $product ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified by WC_Admin_Meta_Boxes::save_meta_boxes().
		$size   = isset( $_POST[ Edition::SIZE_META ] ) ? absint( wp_unslash( $_POST[ Edition::SIZE_META ] ) ) : 0;
		$issued = $product->get_id() ? count( Edition::numbers( $product->get_id(), true ) ) : 0;

		if ( $size && $size < $issued ) {
			\WC_Admin_Meta_Boxes::add_error(
				/* translators: %d: prints already sold. */
				sprintf( __( 'The edition size cannot be lower than the %d prints already sold.', 'mira-studio' ), $issued )
			);
			$size = $issued;
		}

		if ( ! $size ) {
			$product->delete_meta_data( Edition::SIZE_META );
			return;
		}

		$product->update_meta_data( Edition::SIZE_META, $size );
		$product->set_manage_stock( true );
		$product->set_backorders( 'no' );
		$product->set_stock_quantity( $size - $issued );
	}

	/**
	 * Issues print numbers for the edition lines of an order whose stock was just reduced.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function issue_numbers( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$notes = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product || ! $item->get_meta( '_reduced_stock' ) || $item->get_meta( Edition::NUMBERS_META ) ) {
				continue;
			}

			$edition_id = (int) $item->get_product_id();
			$size       = Edition::size( $edition_id );
			if ( ! $size ) {
				continue;
			}

			try {
				$numbers = Edition::issue( $edition_id, (int) $item->get_quantity() );
			} catch ( \RuntimeException $e ) {
				$order->add_order_note( __( 'Print numbers could not be issued (edition busy). Edit the order to retry.', 'mira-studio' ) );
				continue;
			}

			if ( ! $numbers ) {
				continue;
			}

			$item->update_meta_data( Edition::NUMBERS_META, $numbers );
			$item->update_meta_data( self::ITEM_SIZE_META, $size );
			$item->save();
			$notes[] = $item->get_name() . ' ' . Edition::label( $numbers, $size );
		}

		if ( $notes ) {
			/* translators: %s: list of products and print numbers. */
			$order->add_order_note( sprintf( __( 'Print numbers issued: %s.', 'mira-studio' ), implode( '; ', $notes ) ) );
		}
	}

	/**
	 * Releases the print numbers of an order whose stock was restored.
	 *
	 * @param \WC_Order $order Order.
	 * @return void
	 */
	public function release_numbers( $order ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		$notes = array();

		foreach ( $order->get_items() as $item ) {
			$numbers = $item instanceof \WC_Order_Item_Product ? $item->get_meta( Edition::NUMBERS_META ) : null;

			if ( ! $numbers || ! is_array( $numbers ) ) {
				continue;
			}

			Edition::release( (int) $item->get_product_id(), $numbers );
			$notes[] = $item->get_name() . ' ' . Edition::label( $numbers, (int) $item->get_meta( self::ITEM_SIZE_META ) );
			$item->delete_meta_data( Edition::NUMBERS_META );
			$item->delete_meta_data( self::ITEM_SIZE_META );
			$item->save();
		}

		if ( $notes ) {
			/* translators: %s: list of products and print numbers. */
			$order->add_order_note( sprintf( __( 'Print numbers released: %s.', 'mira-studio' ), implode( '; ', $notes ) ) );
		}
	}

	/**
	 * Adds "Print: #13/50" to the displayed meta of an order line.
	 *
	 * @param array<int|string, object> $formatted Formatted meta.
	 * @param \WC_Order_Item            $item      Order item.
	 * @return array<int|string, object>
	 */
	public function item_meta( array $formatted, $item ): array {
		if ( ! $item instanceof \WC_Order_Item_Product || ! $item->get_id() ) {
			return $formatted;
		}

		/*
		 * Read from the item store, not from the object: order emails are rendered with an
		 * order object loaded before the numbers were issued in the same request.
		 */
		$numbers = wc_get_order_item_meta( $item->get_id(), Edition::NUMBERS_META, true );

		if ( $numbers && is_array( $numbers ) ) {
			$label                     = Edition::label( $numbers, (int) wc_get_order_item_meta( $item->get_id(), self::ITEM_SIZE_META, true ) );
			$formatted['mira_edition'] = (object) array(
				'key'           => 'mira_edition',
				'value'         => $label,
				'display_key'   => _n( 'Print', 'Prints', count( $numbers ), 'mira-studio' ),
				'display_value' => '<p>' . esc_html( $label ) . '</p>',
			);
		}

		return $formatted;
	}

	/**
	 * Edition counter on the product page.
	 *
	 * @return void
	 */
	public function counter(): void {
		global $product;

		if ( $product instanceof \WC_Product ) {
			mira_edition_counter( $product );
		}
	}

	/**
	 * "Limited edition" / "Sold out" badge in product lists.
	 *
	 * @return void
	 */
	public function badge(): void {
		global $product;

		$state = $product instanceof \WC_Product ? Edition::state( $product ) : null;

		if ( ! $state ) {
			return;
		}

		printf(
			'<span class="mira-badge%s">%s</span>',
			$state['sold_out'] ? ' mira-badge--sold-out' : '',
			$state['sold_out'] ? esc_html__( 'Sold out', 'mira-studio' ) : esc_html(
				/* translators: %d: edition size. */
				sprintf( __( 'Edition of %d', 'mira-studio' ), $state['size'] )
			)
		);
	}

	/**
	 * Availability text for editions.
	 *
	 * @param string      $text    Default text.
	 * @param \WC_Product $product Product.
	 * @return string
	 */
	public function availability( $text, $product ): string {
		$state = $product instanceof \WC_Product ? Edition::state( $product ) : null;

		if ( ! $state ) {
			return (string) $text;
		}

		if ( $state['sold_out'] || ! $product->is_in_stock() ) {
			/* translators: %d: edition size. */
			return sprintf( __( 'Sold out – the edition of %d is complete.', 'mira-studio' ), $state['size'] );
		}

		if ( $state['remaining'] <= 5 ) {
			/* translators: %d: prints left. */
			return sprintf( _n( 'Only %d print left', 'Only %d prints left', $state['remaining'], 'mira-studio' ), $state['remaining'] );
		}

		return '';
	}

	/**
	 * "Limited edition" line under the product in the cart and checkout.
	 *
	 * @param array<int, array<string, string>> $data      Item data.
	 * @param array<string, mixed>              $cart_item Cart item.
	 * @return array<int, array<string, string>>
	 */
	public function cart_item_data( array $data, array $cart_item ): array {
		$size = isset( $cart_item['product_id'] ) ? Edition::size( (int) $cart_item['product_id'] ) : 0;

		if ( $size ) {
			$data[] = array(
				'key'   => __( 'Limited edition', 'mira-studio' ),
				/* translators: %d: edition size. */
				'value' => sprintf( __( 'numbered print, edition of %d', 'mira-studio' ), $size ),
			);
		}

		return $data;
	}
}
