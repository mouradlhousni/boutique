<?php
/**
 * Artwork admin screens.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Portfolio;

defined( 'ABSPATH' ) || exit;

/**
 * "Artwork details" meta box (year, technique, dimensions, linked product, gallery)
 * and list table columns.
 */
final class Admin {

	private const NONCE = 'mira_artwork_details';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'add_meta_boxes_' . PostTypes::POST_TYPE, array( $this, 'add_meta_box' ) );
		add_action( 'save_post_' . PostTypes::POST_TYPE, array( $this, 'save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'manage_' . PostTypes::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . PostTypes::POST_TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	/**
	 * Registers the meta box.
	 *
	 * @return void
	 */
	public function add_meta_box(): void {
		add_meta_box( 'mira-artwork-details', __( 'Artwork details', 'mira-studio' ), array( $this, 'render' ), PostTypes::POST_TYPE, 'normal', 'high' );
	}

	/**
	 * Media modal and gallery picker script on the artwork edit screen.
	 *
	 * @param string $hook Admin page.
	 * @return void
	 */
	public function assets( string $hook ): void {
		$screen = get_current_screen();

		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! $screen || PostTypes::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_media();
		wp_enqueue_script( 'mira-admin-gallery', MIRA_STUDIO_URL . 'assets/js/admin-gallery.js', array(), MIRA_STUDIO_VERSION, true );
		wp_enqueue_style( 'mira-admin', MIRA_STUDIO_URL . 'assets/css/admin.css', array(), MIRA_STUDIO_VERSION );
		wp_localize_script(
			'mira-admin-gallery',
			'miraGallery',
			array(
				'title'  => __( 'Artwork gallery', 'mira-studio' ),
				'button' => __( 'Use these images', 'mira-studio' ),
				'remove' => __( 'Remove image', 'mira-studio' ),
			)
		);
	}

	/**
	 * Meta box markup.
	 *
	 * @param \WP_Post $post Artwork.
	 * @return void
	 */
	public function render( \WP_Post $post ): void {
		$artwork  = Artwork::get( $post->ID );
		$products = function_exists( 'wc_get_products' )
			? wc_get_products(
				array(
					'status'  => 'publish',
					'limit'   => 200,
					'orderby' => 'title',
					'order'   => 'ASC',
					'return'  => 'objects',
				)
			)
			: array();
		$linked   = (int) get_post_meta( $post->ID, '_mira_product_id', true );

		wp_nonce_field( self::NONCE, '_mira_artwork_nonce' );
		?>
		<div class="mira-fields">
			<p>
				<label for="mira-year"><?php esc_html_e( 'Year', 'mira-studio' ); ?></label>
				<input type="number" id="mira-year" name="mira_year" min="1900" max="2100" value="<?php echo $artwork['year'] ? esc_attr( (string) $artwork['year'] ) : ''; ?>">
			</p>
			<p>
				<label for="mira-technique"><?php esc_html_e( 'Technique', 'mira-studio' ); ?></label>
				<input type="text" id="mira-technique" name="mira_technique" class="widefat" value="<?php echo esc_attr( $artwork['technique'] ); ?>" placeholder="<?php esc_attr_e( 'Gouache on paper', 'mira-studio' ); ?>">
			</p>
			<p>
				<label for="mira-dimensions"><?php esc_html_e( 'Dimensions', 'mira-studio' ); ?></label>
				<input type="text" id="mira-dimensions" name="mira_dimensions" class="widefat" value="<?php echo esc_attr( $artwork['dimensions'] ); ?>" placeholder="<?php esc_attr_e( '30 × 40 cm', 'mira-studio' ); ?>">
			</p>
			<p>
				<label for="mira-product"><?php esc_html_e( 'Matching print in the shop', 'mira-studio' ); ?></label>
				<select id="mira-product" name="mira_product_id" class="widefat">
					<option value="0"><?php esc_html_e( '— None —', 'mira-studio' ); ?></option>
					<?php foreach ( $products as $product ) : ?>
						<option value="<?php echo esc_attr( (string) $product->get_id() ); ?>" <?php selected( $linked, $product->get_id() ); ?>><?php echo esc_html( $product->get_name() ); ?></option>
					<?php endforeach; ?>
				</select>
			</p>
		</div>

		<fieldset class="mira-gallery" data-mira-gallery>
			<legend><?php esc_html_e( 'Gallery (details, process shots…)', 'mira-studio' ); ?></legend>
			<input type="hidden" name="mira_gallery" value="<?php echo esc_attr( implode( ',', $artwork['gallery'] ) ); ?>" data-mira-gallery-input>
			<ul class="mira-gallery__list" data-mira-gallery-list>
				<?php foreach ( $artwork['gallery'] as $attachment_id ) : ?>
					<li data-id="<?php echo esc_attr( (string) $attachment_id ); ?>">
						<?php echo wp_get_attachment_image( $attachment_id, 'thumbnail' ); ?>
						<button type="button" class="button-link mira-gallery__remove" aria-label="<?php esc_attr_e( 'Remove image', 'mira-studio' ); ?>">&times;</button>
					</li>
				<?php endforeach; ?>
			</ul>
			<button type="button" class="button" data-mira-gallery-add><?php esc_html_e( 'Add images', 'mira-studio' ); ?></button>
		</fieldset>
		<?php
	}

	/**
	 * Saves the meta box.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public function save( int $post_id ): void {
		if ( ! isset( $_POST['_mira_artwork_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['_mira_artwork_nonce'] ) ), self::NONCE ) ) {
			return;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$fields = array(
			'_mira_year'       => 'mira_year',
			'_mira_technique'  => 'mira_technique',
			'_mira_dimensions' => 'mira_dimensions',
			'_mira_product_id' => 'mira_product_id',
		);

		foreach ( $fields as $meta_key => $field ) {
			$value = isset( $_POST[ $field ] ) ? call_user_func( PostTypes::META[ $meta_key ][1], wp_unslash( $_POST[ $field ] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized by the registered callback.

			if ( '' === $value || 0 === $value ) {
				delete_post_meta( $post_id, $meta_key );
			} else {
				update_post_meta( $post_id, $meta_key, $value );
			}
		}

		$gallery = isset( $_POST['mira_gallery'] ) ? PostTypes::sanitize_ids( sanitize_text_field( wp_unslash( $_POST['mira_gallery'] ) ) ) : array();
		$gallery = array_values( array_filter( $gallery, static fn( int $id ) => wp_attachment_is_image( $id ) ) );
		update_post_meta( $post_id, PostTypes::GALLERY_META, $gallery );
	}

	/**
	 * List table columns.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public function columns( array $columns ): array {
		$result = array();

		foreach ( $columns as $key => $label ) {
			if ( 'title' === $key ) {
				$result['mira_thumb'] = '<span class="screen-reader-text">' . esc_html__( 'Image', 'mira-studio' ) . '</span>';
			}
			$result[ $key ] = $label;
			if ( 'title' === $key ) {
				$result['mira_year']    = __( 'Year', 'mira-studio' );
				$result['mira_product'] = __( 'Print', 'mira-studio' );
			}
		}

		return $result;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Artwork ID.
	 * @return void
	 */
	public function column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'mira_thumb':
				echo get_the_post_thumbnail( $post_id, array( 48, 48 ) );
				break;
			case 'mira_year':
				$year = (int) get_post_meta( $post_id, '_mira_year', true );
				echo $year ? esc_html( (string) $year ) : '—';
				break;
			case 'mira_product':
				$product_id = Artwork::get( $post_id )['product_id'];
				if ( $product_id ) {
					printf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $product_id ) ), esc_html( get_the_title( $product_id ) ) );
				} else {
					echo '—';
				}
				break;
		}
	}
}
