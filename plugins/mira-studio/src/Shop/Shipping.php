<?php
/**
 * Shipping rules.
 *
 * @package AtelierMira\Studio
 */

namespace AtelierMira\Studio\Shop;

defined( 'ABSPATH' ) || exit;

/**
 * When free shipping applies, it is the only option shown (no paid alternative).
 */
final class Shipping {

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'woocommerce_package_rates', array( $this, 'prefer_free' ), 100 );
	}

	/**
	 * Keeps only the free rates when there is one.
	 *
	 * @param array<string, \WC_Shipping_Rate> $rates Rates.
	 * @return array<string, \WC_Shipping_Rate>
	 */
	public function prefer_free( array $rates ): array {
		$free = array_filter( $rates, static fn( $rate ) => 'free_shipping' === $rate->get_method_id() );

		return $free ? $free : $rates;
	}
}
