<?php
/**
 * Limited edition model.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * Edition size and print numbers of a product.
 *
 * - `_mira_edition_size` (product meta): number of prints in the edition, 0 when open.
 * - `_mira_edition_numbers` (product meta): print numbers already issued.
 * - `_mira_edition_numbers` (order item meta): numbers issued to that line.
 *
 * WooCommerce stock stays the source of truth for availability (stock = size − issued);
 * numbers follow stock movements, so "sold", stock and "sold out" never disagree.
 * Numbers are read and written directly in post meta, under a short database lock,
 * so a stale product object can never overwrite them (or the stock) concurrently.
 */
final class Edition {

	public const SIZE_META    = '_mira_edition_size';
	public const NUMBERS_META = '_mira_edition_numbers';

	/**
	 * Edition size of a product (variations use their parent's edition).
	 *
	 * @param \WC_Product|int $product Product or ID.
	 * @return int 0 for open editions.
	 */
	public static function size( $product ): int {
		$id = self::edition_id( $product );

		return $id ? (int) get_post_meta( $id, self::SIZE_META, true ) : 0;
	}

	/**
	 * Issued print numbers.
	 *
	 * @param int  $product_id Edition (parent) product ID.
	 * @param bool $fresh      Bypass the object cache.
	 * @return array<int, int>
	 */
	public static function numbers( int $product_id, bool $fresh = false ): array {
		if ( $fresh ) {
			wp_cache_delete( $product_id, 'post_meta' );
		}

		$numbers = get_post_meta( $product_id, self::NUMBERS_META, true );

		return is_array( $numbers ) ? array_values( array_map( 'intval', $numbers ) ) : array();
	}

	/**
	 * Public state of an edition, or null for open editions.
	 *
	 * @param \WC_Product|int $product Product or ID.
	 * @return array{size:int, sold:int, remaining:int, sold_out:bool}|null
	 */
	public static function state( $product ): ?array {
		$id   = self::edition_id( $product );
		$size = $id ? self::size( $id ) : 0;

		if ( ! $size ) {
			return null;
		}

		$sold = min( $size, count( self::numbers( $id ) ) );

		return array(
			'size'      => $size,
			'sold'      => $sold,
			'remaining' => $size - $sold,
			'sold_out'  => $sold >= $size,
		);
	}

	/**
	 * Issues the lowest free numbers.
	 *
	 * @param int $product_id Edition product ID.
	 * @param int $quantity   Prints.
	 * @return array<int, int> Issued numbers (fewer than requested if the edition ran out).
	 * @throws \RuntimeException When the lock cannot be acquired.
	 */
	public static function issue( int $product_id, int $quantity ): array {
		$size = self::size( $product_id );

		if ( ! $size || $quantity < 1 ) {
			return array();
		}

		return self::locked(
			$product_id,
			static function () use ( $product_id, $quantity, $size ) {
				$taken  = self::numbers( $product_id, true );
				$free   = array_values( array_diff( range( 1, $size ), $taken ) );
				$issued = array_slice( $free, 0, $quantity );

				if ( $issued ) {
					$taken = array_merge( $taken, $issued );
					sort( $taken );
					update_post_meta( $product_id, self::NUMBERS_META, $taken );
				}

				return $issued;
			}
		);
	}

	/**
	 * Releases numbers (cancelled or refunded order) so they can be issued again.
	 *
	 * @param int             $product_id Edition product ID.
	 * @param array<int, int> $numbers    Numbers.
	 * @return void
	 */
	public static function release( int $product_id, array $numbers ): void {
		self::locked(
			$product_id,
			static function () use ( $product_id, $numbers ) {
				$taken = array_values( array_diff( self::numbers( $product_id, true ), array_map( 'intval', $numbers ) ) );
				update_post_meta( $product_id, self::NUMBERS_META, $taken );

				return array();
			}
		);
	}

	/**
	 * Recomputes the stock of an edition from its size and issued numbers.
	 *
	 * @param int $product_id Edition product ID.
	 * @return void
	 */
	public static function sync_stock( int $product_id ): void {
		$product = wc_get_product( $product_id );
		$size    = self::size( $product_id );

		if ( ! $product || ! $size ) {
			return;
		}

		$product->set_manage_stock( true );
		$product->set_backorders( 'no' );
		$product->set_stock_quantity( max( 0, $size - count( self::numbers( $product_id, true ) ) ) );
		$product->save();
	}

	/**
	 * "#13/50" style label.
	 *
	 * @param array<int, int> $numbers Numbers.
	 * @param int             $size    Edition size.
	 * @return string
	 */
	public static function label( array $numbers, int $size ): string {
		return implode(
			', ',
			array_map(
				/* translators: 1: print number, 2: edition size. */
				static fn( int $number ) => sprintf( __( '#%1$d/%2$d', 'mira-studio' ), $number, $size ),
				$numbers
			)
		);
	}

	/**
	 * ID of the product that holds the edition (parent for variations).
	 *
	 * @param \WC_Product|int $product Product or ID.
	 * @return int
	 */
	public static function edition_id( $product ): int {
		if ( is_numeric( $product ) ) {
			$product = wc_get_product( (int) $product );
		}

		if ( ! $product instanceof \WC_Product ) {
			return 0;
		}

		return $product->get_parent_id() ? $product->get_parent_id() : $product->get_id();
	}

	/**
	 * Runs a callback under a per-product database lock.
	 *
	 * `INSERT IGNORE` on the unique `option_name` is atomic on MySQL and on the SQLite
	 * driver used by WordPress Playground; stale locks expire after 10 seconds.
	 *
	 * @param int      $product_id Product ID.
	 * @param callable $callback   Work to do.
	 * @return array<int, int>
	 * @throws \RuntimeException When the lock cannot be acquired.
	 */
	private static function locked( int $product_id, callable $callback ): array {
		global $wpdb;

		$name = 'mira_edition_lock_' . $product_id;

		for ( $attempt = 0; $attempt < 50; $attempt++ ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lock row, must not be cached.
			$acquired = $wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $name, (string) time() ) );

			if ( $acquired ) {
				try {
					return $callback();
				} finally {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock release.
					$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
				}
			}

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock age, must not be cached.
			$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
			if ( $since && time() - $since > 10 ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Stale lock.
				$wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
				continue;
			}

			usleep( 100000 );
		}

		throw new \RuntimeException( 'Could not lock the edition counter.' );
	}
}
