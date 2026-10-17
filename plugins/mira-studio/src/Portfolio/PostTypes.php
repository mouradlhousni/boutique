<?php
/**
 * Portfolio content types.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Portfolio;

defined( 'ABSPATH' ) || exit;

/**
 * The `artwork` post type (portfolio pieces, not for sale), the `artwork_series`
 * taxonomy and their meta fields.
 */
final class PostTypes {

	public const POST_TYPE = 'artwork';
	public const TAXONOMY  = 'artwork_series';

	/**
	 * Artwork meta: key => [type, sanitize callback].
	 */
	public const META = array(
		'_mira_year'       => array( 'integer', 'absint' ),
		'_mira_technique'  => array( 'string', 'sanitize_text_field' ),
		'_mira_dimensions' => array( 'string', 'sanitize_text_field' ),
		'_mira_product_id' => array( 'integer', 'absint' ),
	);

	public const GALLERY_META = '_mira_gallery';

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the post type, the taxonomy and the meta (also called on activation).
	 *
	 * @return void
	 */
	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Artworks', 'mira-studio' ),
					'singular_name'      => __( 'Artwork', 'mira-studio' ),
					'menu_name'          => __( 'Portfolio', 'mira-studio' ),
					'add_new_item'       => __( 'Add new artwork', 'mira-studio' ),
					'edit_item'          => __( 'Edit artwork', 'mira-studio' ),
					'new_item'           => __( 'New artwork', 'mira-studio' ),
					'view_item'          => __( 'View artwork', 'mira-studio' ),
					'search_items'       => __( 'Search artworks', 'mira-studio' ),
					'not_found'          => __( 'No artworks found.', 'mira-studio' ),
					'not_found_in_trash' => __( 'No artworks found in Trash.', 'mira-studio' ),
					'all_items'          => __( 'All artworks', 'mira-studio' ),
					'featured_image'     => __( 'Artwork image', 'mira-studio' ),
					'set_featured_image' => __( 'Set artwork image', 'mira-studio' ),
				),
				'public'        => true,
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-art',
				'menu_position' => 54,
				'has_archive'   => 'portfolio',
				'rewrite'       => array(
					'slug'       => 'portfolio',
					'with_front' => false,
				),
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'custom-fields', 'page-attributes' ),
				'taxonomies'    => array( self::TAXONOMY ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Series', 'mira-studio' ),
					'singular_name' => __( 'Series', 'mira-studio' ),
					'all_items'     => __( 'All series', 'mira-studio' ),
					'edit_item'     => __( 'Edit series', 'mira-studio' ),
					'add_new_item'  => __( 'Add new series', 'mira-studio' ),
					'search_items'  => __( 'Search series', 'mira-studio' ),
					'not_found'     => __( 'No series found.', 'mira-studio' ),
				),
				'hierarchical'      => true,
				'public'            => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array(
					'slug'       => 'series',
					'with_front' => false,
				),
			)
		);

		foreach ( self::META as $key => list( $type, $sanitize ) ) {
			register_post_meta(
				self::POST_TYPE,
				$key,
				array(
					'type'              => $type,
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => array( self::class, 'can_edit' ),
				)
			);
		}

		register_post_meta(
			self::POST_TYPE,
			self::GALLERY_META,
			array(
				'type'              => 'array',
				'single'            => true,
				'default'           => array(),
				'sanitize_callback' => array( self::class, 'sanitize_ids' ),
				'auth_callback'     => array( self::class, 'can_edit' ),
				'show_in_rest'      => array(
					'schema' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'integer' ),
					),
				),
			)
		);

		register_term_meta(
			self::TAXONOMY,
			'mira_accent',
			array(
				'type'              => 'string',
				'single'            => true,
				'show_in_rest'      => true,
				'sanitize_callback' => 'sanitize_hex_color',
			)
		);
	}

	/**
	 * Meta auth callback: whoever can edit the artwork.
	 *
	 * @param bool   $allowed  Default.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ): bool {
		return current_user_can( 'edit_post', (int) $post_id );
	}

	/**
	 * Sanitizes a list of attachment IDs.
	 *
	 * @param mixed $value Raw value (array or comma-separated string).
	 * @return array<int, int>
	 */
	public static function sanitize_ids( $value ): array {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
	}
}
