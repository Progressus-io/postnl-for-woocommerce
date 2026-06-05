<?php
/**
 * Interface Pickup_Location_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for pickup-location (dropoff-point) retrieval services.
 *
 * Covers the PickupOptions portion of the PostNL Checkout API response
 * (/shipment/v1/checkout).  The same API endpoint also returns DeliveryOptions;
 * those are covered by Timeframe_Service_Interface.
 *
 * Both the Legacy (V1) and any future V4 transport must implement this interface.
 * Callers in Frontend\Container and the blocks postnl-dropoff-points component
 * never need to know which transport provided the data.
 */
interface Pickup_Location_Service_Interface {

	/**
	 * Retrieve available PostNL pickup locations for a given checkout address.
	 *
	 * Derived from Frontend\Container::get_checkout_data(), which uses the same
	 * Checkout\Client request that returns both DeliveryOptions and PickupOptions.
	 * The PickupOptions portion is consumed by Frontend\Dropoff_Points and the
	 * blocks postnl-dropoff-points component to render the pickup-point tab.
	 *
	 * The current PostNL endpoint is /shipment/v1/checkout (POST).
	 * The number of returned locations is controlled by the
	 * number_pickup_points setting (currently hardcoded to 20 via
	 * Settings::get_number_pickup_points()).
	 *
	 * @param array $post_data {
	 *     Checkout POST data as collected from the classic checkout or the blocks
	 *     AJAX handler.  All keys map to the fields set by Address_Utils and the
	 *     shipping settings injected by Base_Info::set_settings_data().
	 *
	 *     @type string $shipping_postcode  Required. Receiver postcode.
	 *     @type string $shipping_country   Required. Receiver country code (NL or BE).
	 *     @type string $shipping_address_1 Street name.
	 *     @type string $shipping_address_2 House number or extension.
	 *     @type string $shipping_city      City.
	 * }
	 *
	 * @return array {
	 *     JSON-decoded PostNL /shipment/v1/checkout response body.
	 *     The PickupOptions key is consumed by Frontend\Dropoff_Points and the
	 *     blocks postnl-dropoff-points component.
	 *
	 *     @type array $PickupOptions {
	 *         Indexed array of pickup-option groups.
	 *         @type array $item {
	 *             @type string $PickupDate   Earliest available customer pickup date.
	 *             @type string $ShippingDate Date the parcel needs to be dispatched.
	 *             @type array  $Locations    Indexed array of pickup location records.
	 *                 @type array $location {
	 *                     @type string $LocationCode Unique location identifier stored
	 *                                                in _postnl_order_metadata['frontend']
	 *                                                as dropoff_points_id.
	 *                     @type string $Name         Human-readable location name stored
	 *                                                as dropoff_points_company.
	 *                     @type array  $Address {
	 *                         @type string $Street      Street name.
	 *                         @type string $Zipcode     Postal code.
	 *                         @type string $City        City name.
	 *                         @type string $Countrycode ISO-2 country code.
	 *                     }
	 *                     @type string $OpeningHours  Location opening hours (optional).
	 *                     @type string $Distance      Distance from the receiver address
	 *                                                 in metres (optional).
	 *                 }
	 *         }
	 *     }
	 * }
	 *
	 * @throws \Exception If the API request fails (network error or authentication
	 *                    failure).  An empty or missing PickupOptions key is not an
	 *                    exception; the caller hides the pickup-point tab in that case.
	 */
	public function get_pickup_locations( array $post_data ): array;
}
