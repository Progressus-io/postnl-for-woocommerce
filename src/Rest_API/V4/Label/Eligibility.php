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
 * Pure decision logic for whether an order is a shipment the V4 label service
 * handles — a domestic NL parcel (home or pickup point), a domestic NL letterbox
 * (mailbox parcel), an NL to BE parcel, or an EU/ROW international parcel from
 * NL/BE. Kept free of WooCommerce and
 * Order\Base so the gate — the highest-risk part of the flow — can be asserted
 * in isolation.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */
class Eligibility {

	/**
	 * Legacy product code of the 48h letterbox (mailbox parcel).
	 */
	const LETTERBOX_48_CODE = '2948';

	/**
	 * Resolve the V4 mapper result for an order's product combination.
	 *
	 * The selected options are passed through so a service-bearing combination
	 * that keeps product 3085 (e.g. insured) resolves to a services row (or an
	 * unknown combination) and is rejected by is_eligible(), rather than
	 * silently masquerading as the base parcel.
	 *
	 * The order meta box collapses both letterbox variants onto the 'letterbox'
	 * option, so the 48h variant is only recognisable by its product code and is
	 * translated back to the matrix's 'letterbox_48' key here.
	 *
	 * @param string $origin       Origin country (store base).
	 * @param string $destination  Shipping zone (NL|BE|EU|ROW).
	 * @param bool   $is_pickup    Whether a pickup point was selected.
	 * @param array  $backend_raw  Raw backend feature flags ('yes' strings).
	 * @param string $product_code Legacy resolved product code.
	 * @return array V4_Mapper::map() result.
	 */
	public static function resolve_mapped( string $origin, string $destination, bool $is_pickup, array $backend_raw, string $product_code ): array {
		$options = array_keys( Utils::get_selected_label_features( $backend_raw ) );

		if ( self::LETTERBOX_48_CODE === $product_code ) {
			$options = array_map(
				static function ( $option ) {
					return 'letterbox' === $option ? 'letterbox_48' : $option;
				},
				$options
			);
		}

		return V4_Mapper::map(
			array(
				'origin'              => $origin,
				'destination'         => $destination,
				'flow'                => $is_pickup ? 'pickup_points' : 'delivery_day',
				'options'             => $options,
				'legacy_product_code' => $product_code,
			)
		);
	}

	/**
	 * Decide whether the collected signals describe a shipment the V4 service
	 * handles — a domestic NL, NL to BE or EU/ROW international parcel (single- or
	 * multi-collo), or a domestic NL letterbox (mailbox parcel 2928/2948).
	 *
	 * @param array $signals {
	 *     Signal set assembled by Service::gather_signals().
	 *
	 *     @type int    $num_labels          Collo count (>= 1).
	 *     @type bool   $is_delivery_day     A delivery-day option was selected.
	 *     @type bool   $is_pickup           A pickup point was selected.
	 *     @type string $pickup_id           PostNL location code of the selected pickup
	 *                                        point; empty when none could be resolved.
	 *     @type bool   $has_return          A return label/barcode is involved.
	 *     @type string $delivery_window     Normalised delivery-day window: 'evening',
	 *                                        'morning' or 'standard'.
	 *     @type string $origin              Origin country.
	 *     @type string $destination         Shipping zone.
	 *     @type array  $mapped              V4_Mapper::map() result.
	 * }
	 * @return bool
	 */
	public static function is_eligible( array $signals ): bool {
		// Multi-collo (num_labels > 1) is supported via multiple request items;
		// only a missing/invalid collo count is rejected here.
		if ( (int) ( $signals['num_labels'] ?? 1 ) < 1 ) {
			return false;
		}

		$is_pickup = ! empty( $signals['is_pickup'] );

		// A pickup point ships as a DeliveryLocation, which needs the location code.
		// Without one the label would silently go to the customer's home address.
		if ( $is_pickup && '' === (string) ( $signals['pickup_id'] ?? '' ) ) {
			return false;
		}

		if ( ! empty( $signals['has_return'] ) ) {
			return false;
		}

		// Evening is expressed as a deliveryWindow service on the same product code, so it
		// maps like any parcel and is handled here. Morning (08:00-12:00) stays on legacy:
		// the sandbox accepts guaranteedBefore 10:00 and 12:00, but guaranteed delivery may
		// be a different product from the morning window, which PostNL has yet to confirm.
		if ( 'morning' === ( $signals['delivery_window'] ?? '' ) ) {
			return false;
		}

		$origin      = (string) ( $signals['origin'] ?? '' );
		$destination = (string) ( $signals['destination'] ?? '' );

		// Domestic NL, NL to BE, or an EU/ROW international parcel from NL/BE. Parcels
		// from a BE origin to NL or BE stay on legacy: unverified against the sandbox.
		$is_domestic      = ( 'NL' === $origin && 'NL' === $destination );
		$is_cross_border  = ( 'NL' === $origin && 'BE' === $destination );
		$is_international = in_array( $origin, array( 'NL', 'BE' ), true )
			&& in_array( $destination, array( 'EU', 'ROW' ), true );

		if ( ! $is_domestic && ! $is_cross_border && ! $is_international ) {
			return false;
		}

		// The mapper is authoritative: for a combination it marks as having a V4
		// equivalent, its services array is the full V4 representation of every
		// selected option, so no separate product-option gate is needed. Its
		// deliveryLocation hint must agree with the order, so a pickup row never
		// ships to a home address and a home row never ships to a pickup point.
		$mapped        = $signals['mapped'] ?? array();
		$shipment_type = (string) ( $mapped['shipmentType'] ?? '' );

		if ( empty( $mapped['has_v4_equivalent'] ) || ! empty( $mapped['deliveryLocation'] ) !== $is_pickup ) {
			return false;
		}

		// minimalAgeCheck and an evening deliveryWindow cannot be combined on the V4
		// product — labelconfirm rejects the pair — so an 18+ order with an evening
		// slot stays on legacy rather than being routed to a request PostNL rejects.
		if ( 'evening' === ( $signals['delivery_window'] ?? '' )
			&& array_key_exists( 'minimalAgeCheck', (array) ( $mapped['services'] ?? array() ) ) ) {
			return false;
		}

		// Letterbox (mailbox parcel 2928/2948) is a domestic-NL-only variant. A parcel
		// may be domestic, NL to BE or EU/ROW international.
		if ( 'letterbox' === $shipment_type ) {
			return $is_domestic;
		}

		return 'parcel' === $shipment_type;
	}

	/**
	 * Resolve the mapper's service placeholders into concrete request values.
	 *
	 * The matrix stores insuredValue as the '<order_total>' placeholder — a misnomer
	 * kept for now to match V4_Mapper; the value substituted is the item subtotal, not
	 * the order total. It is replaced here with the order's insured amount. All other
	 * flags (deliveryConfirmation, statedAddressOnly, returnWhenNotHome, minimalAgeCheck,
	 * deliveryWindowDuration) pass through unchanged; the id_check rows emit minimalAgeCheck.
	 *
	 * The insured amount must remain the order item subtotal (WC_Order::get_subtotal),
	 * matching the value the legacy Shipping\Client puts in the Amounts block. Do not
	 * switch it to the order total — that would add tax/shipping and diverge from V1.
	 *
	 * @param array $mapped_services Services array from V4_Mapper::map().
	 * @param float $insured_value   Amount to insure (order item subtotal).
	 * @return array Concrete service flags for Request_Builder.
	 */
	public static function resolve_services( array $mapped_services, float $insured_value ): array {
		if ( array_key_exists( 'insuredValue', $mapped_services ) ) {
			$mapped_services['insuredValue'] = $insured_value;
		}

		return $mapped_services;
	}
}
