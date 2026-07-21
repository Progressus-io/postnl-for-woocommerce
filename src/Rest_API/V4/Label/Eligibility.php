<?php
/**
 * Class Rest_API\V4\Label\Eligibility file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Label;

use PostNLWooCommerce\Helper\Product_Mapper\V4_Mapper;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure decision logic for whether an order is the happy-path domestic parcel
 * the V4 label service handles. Kept free of WooCommerce and Order\Base so the
 * gate — the highest-risk part of the flow — can be asserted in isolation.
 *
 * @since   5.9.9
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */
class Eligibility {

	/**
	 * Resolve the V4 mapper result for an order's product combination.
	 *
	 * The selected options are passed through so a service-bearing combination
	 * that keeps product 3085 (e.g. insured) resolves to a services row (or an
	 * unknown combination) and is rejected by is_eligible(), rather than
	 * silently masquerading as the base parcel.
	 *
	 * @param string $origin       Origin country (store base).
	 * @param string $destination  Shipping zone (NL|BE|EU|ROW).
	 * @param bool   $is_pickup    Whether a pickup point was selected.
	 * @param array  $backend_raw  Raw backend feature flags ('yes' strings).
	 * @param string $product_code Legacy resolved product code.
	 * @return array V4_Mapper::map() result.
	 */
	public static function resolve_mapped( string $origin, string $destination, bool $is_pickup, array $backend_raw, string $product_code ): array {
		return V4_Mapper::map(
			array(
				'origin'              => $origin,
				'destination'         => $destination,
				'flow'                => $is_pickup ? 'pickup_points' : 'delivery_day',
				'options'             => array_keys( Utils::get_selected_label_features( $backend_raw ) ),
				'legacy_product_code' => $product_code,
			)
		);
	}

	/**
	 * Decide whether the collected signals describe the happy-path domestic parcel.
	 *
	 * @param array $signals {
	 *     Signal set assembled by Service::gather_signals().
	 *
	 *     @type int    $num_labels          Collo count.
	 *     @type bool   $is_delivery_day     A delivery-day option was selected.
	 *     @type bool   $is_pickup           A pickup point was selected.
	 *     @type bool   $has_return          A return label/barcode is involved.
	 *     @type string $delivery_type       'Standard' or 'Evening'.
	 *     @type string $origin              Origin country.
	 *     @type string $destination         Shipping zone.
	 *     @type array  $mapped              V4_Mapper::map() result.
	 * }
	 * @return bool
	 */
	public static function is_eligible( array $signals ): bool {
		if ( 1 !== (int) ( $signals['num_labels'] ?? 1 ) ) {
			return false;
		}

		if ( ! empty( $signals['is_delivery_day'] ) || ! empty( $signals['is_pickup'] ) ) {
			return false;
		}

		if ( ! empty( $signals['has_return'] ) ) {
			return false;
		}

		if ( 'Standard' !== ( $signals['delivery_type'] ?? 'Standard' ) ) {
			return false;
		}

		if ( 'NL' !== ( $signals['origin'] ?? '' ) || 'NL' !== ( $signals['destination'] ?? '' ) ) {
			return false;
		}

		// The mapper is authoritative: for a combination it marks as having a V4
		// equivalent, its services array is the full V4 representation of every
		// selected option, so no separate product-option gate is needed. Only a
		// pickup DeliveryLocation is excluded here — that variant lands separately.
		$mapped = $signals['mapped'] ?? array();

		return ! empty( $mapped['has_v4_equivalent'] )
			&& 'parcel' === ( $mapped['shipmentType'] ?? '' )
			&& empty( $mapped['deliveryLocation'] );
	}

	/**
	 * Resolve the mapper's service placeholders into concrete request values.
	 *
	 * The matrix stores insuredValue as the '<order_total>' placeholder; it is
	 * replaced here with the order's insured amount. All other flags
	 * (deliveryConfirmation, statedAddressOnly, returnWhenNotHome, minimalAgeCheck)
	 * pass through unchanged.
	 *
	 * @param array $mapped_services Services array from V4_Mapper::map().
	 * @param float $insured_value   Amount to insure (order subtotal).
	 * @return array Concrete service flags for Request_Builder.
	 */
	public static function resolve_services( array $mapped_services, float $insured_value ): array {
		if ( array_key_exists( 'insuredValue', $mapped_services ) ) {
			$mapped_services['insuredValue'] = $insured_value;
		}

		return $mapped_services;
	}
}
