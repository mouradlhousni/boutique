<?php
/**
 * Artwork read model.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Portfolio;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the details of an artwork in one place, for the theme (through the
 * `mira_artwork()` template function), the block and the admin columns.
 */
final class Artwork {

	/**
	 * Details of an artwork.
	 *
	 * @param int $post_id Artwork ID.
	 * @return array{year:int, technique:string, dimensions:string, gallery:array<int,int>, product_id:int, series:array<int,\WP_Term>}
	 */
	public static function get( int $post_id ): array {
		$product_id = (int) get_post_meta( $post_id, '_mira_product_id', true );

		if ( $product_id && 'publish' !== get_post_status( $product_id ) ) {
			$product_id = 0;
		}

		$series = get_the_terms( $post_id, PostTypes::TAXONOMY );

		return array(
			'year'       => (int) get_post_meta( $post_id, '_mira_year', true ),
			'technique'  => (string) get_post_meta( $post_id, '_mira_technique', true ),
			'dimensions' => (string) get_post_meta( $post_id, '_mira_dimensions', true ),
			'gallery'    => PostTypes::sanitize_ids( get_post_meta( $post_id, PostTypes::GALLERY_META, true ) ),
			'product_id' => $product_id,
			'series'     => is_array( $series ) ? $series : array(),
		);
	}

	/**
	 * Artwork linked to a product (reverse lookup of `_mira_product_id`).
	 *
	 * @param int $product_id Product ID.
	 * @return int Artwork ID, 0 when none.
	 */
	public static function for_product( int $product_id ): int {
		$ids = get_posts(
			array(
				'post_type'      => PostTypes::POST_TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Single indexed lookup, cached by the caller.
				'meta_query'     => array(
					array(
						'key'   => '_mira_product_id',
						'value' => $product_id,
						'type'  => 'NUMERIC',
					),
				),
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}
}
