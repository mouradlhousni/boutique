<?php
/**
 * Print formats (A4, A3, A2…) and their physical size.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Formats are the terms of the global `pa_format` product attribute (variations of
 * variable products, or a fixed attribute of limited editions). Each term stores its
 * paper size in millimetres, used by the "to scale" preview on the product page.
 */
final class PrintFormats {

	public const TAXONOMY = 'pa_format';
	public const FRAME    = 'pa_frame';

	private const NONCE = 'mira_format_size';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::TAXONOMY . '_add_form_fields', array( $this, 'add_fields' ) );
		add_action( self::TAXONOMY . '_edit_form_fields', array( $this, 'edit_fields' ) );
		add_action( 'created_' . self::TAXONOMY, array( $this, 'save' ) );
		add_action( 'edited_' . self::TAXONOMY, array( $this, 'save' ) );
	}

	/**
	 * Fields on the "Add new format" form.
	 *
	 * @return void
	 */
	public function add_fields(): void {
		wp_nonce_field( self::NONCE, '_mira_format_nonce' );
		?>
		<div class="form-field">
			<label for="mira-width-mm"><?php esc_html_e( 'Width (mm)', 'mira-studio' ); ?></label>
			<input type="number" id="mira-width-mm" name="mira_width_mm" min="1" max="5000">
		</div>
		<div class="form-field">
			<label for="mira-height-mm"><?php esc_html_e( 'Height (mm)', 'mira-studio' ); ?></label>
			<input type="number" id="mira-height-mm" name="mira_height_mm" min="1" max="5000">
			<p><?php esc_html_e( 'Paper size, used by the "to scale" preview on product pages.', 'mira-studio' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Fields on the "Edit format" form.
	 *
	 * @param \WP_Term $term Term.
	 * @return void
	 */
	public function edit_fields( \WP_Term $term ): void {
		$size = self::size( $term->term_id );
		?>
		<tr class="form-field">
			<th scope="row"><label for="mira-width-mm"><?php esc_html_e( 'Width (mm)', 'mira-studio' ); ?></label></th>
			<td>
				<?php wp_nonce_field( self::NONCE, '_mira_format_nonce' ); ?>
				<input type="number" id="mira-width-mm" name="mira_width_mm" min="1" max="5000" value="<?php echo esc_attr( $size ? (string) $size[0] : '' ); ?>">
			</td>
		</tr>
		<tr class="form-field">
			<th scope="row"><label for="mira-height-mm"><?php esc_html_e( 'Height (mm)', 'mira-studio' ); ?></label></th>
			<td>
				<input type="number" id="mira-height-mm" name="mira_height_mm" min="1" max="5000" value="<?php echo esc_attr( $size ? (string) $size[1] : '' ); ?>">
				<p class="description"><?php esc_html_e( 'Paper size, used by the "to scale" preview on product pages.', 'mira-studio' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves the size.
	 *
	 * @param int $term_id Term ID.
	 * @return void
	 */
	public function save( int $term_id ): void {
		if ( ! isset( $_POST['_mira_format_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_mira_format_nonce'] ) ), self::NONCE ) || ! current_user_can( 'manage_product_terms' ) ) {
			return;
		}

		foreach ( array( 'mira_width_mm', 'mira_height_mm' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? absint( wp_unslash( $_POST[ $key ] ) ) : 0;
			if ( $value ) {
				update_term_meta( $term_id, $key, min( 5000, $value ) );
			} else {
				delete_term_meta( $term_id, $key );
			}
		}
	}

	/**
	 * Paper size of a format term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{0:int, 1:int}|null Width and height in mm.
	 */
	public static function size( int $term_id ): ?array {
		$width  = (int) get_term_meta( $term_id, 'mira_width_mm', true );
		$height = (int) get_term_meta( $term_id, 'mira_height_mm', true );

		return $width && $height ? array( $width, $height ) : null;
	}

	/**
	 * Formats offered by a product, in attribute order.
	 *
	 * @param \WC_Product $product Product.
	 * @return array<string, array{name:string, width:int, height:int}> Keyed by term slug.
	 */
	public static function for_product( \WC_Product $product ): array {
		$formats = array();
		$terms   = wc_get_product_terms( $product->get_id(), self::TAXONOMY, array( 'fields' => 'all' ) );

		foreach ( $terms as $term ) {
			$size = self::size( $term->term_id );
			if ( $size ) {
				$formats[ $term->slug ] = array(
					'name'   => $term->name,
					'width'  => $size[0],
					'height' => $size[1],
				);
			}
		}

		return $formats;
	}
}
