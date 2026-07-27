<?php
/**
 * Interface Timeframe_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for delivery-day timeframe retrieval services.
 *
 * Covers the DeliveryOptions portion of the PostNL Checkout API response
 * (/shipment/v1/checkout).  The same API endpoint also returns PickupOptions;
 * those are covered by Pickup_Location_Service_Interface.
 *
 * Both the Legacy (V1) and any future V4 transport must implement this interface.
 * Callers in Frontend\Container and Checkout_Blocks\Extend_Block_Core never need
 * to know which transport provided the data.
 */
interface Timeframe_Service_Interface {

	/**
	 * Retrieve available delivery-day timeframes for a given checkout address.
	 *
	 * Derived from Frontend\Container::get_checkout_data(), which:
	 *   1. Constructs Checkout\Item_Info from $post_data.
	 *   2. Constructs Checkout\Client with that Item_Info.
	 *   3. Calls send_request() on the client.
	 *   4. Returns the full response as $checkout_data['response'], which is then
	 *      passed to the template and to get_default_value() for rendering the
	 *      delivery-day tab in the classic and blocks checkouts.
	 *
	 * The current PostNL endpoint is /shipment/v1/checkout (POST).
	 * The request includes cut-off times, drop-off days, shipping duration, and
	 * enabled options (Daytime, Evening, 08:00-12:00).
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
	 *     The DeliveryOptions key is consumed by Frontend\Delivery_Day and the
	 *     blocks postnl-delivery-day component.
	 *
	 *     @type array $DeliveryOptions {
	 *         Indexed array of delivery day entries.
	 *         @type array $item {
	 *             @type string $DeliveryDate Delivery date in 'd-m-Y' or 'Y-m-d' format.
	 *             @type array  $Timeframe    Indexed array of timeframe windows.
	 *                 @type array $window {
	 *                     @type string   $From    Window start time, e.g. '09:00:00'.
	 *                     @type string   $To      Window end time, e.g. '18:00:00'.
	 *                     @type string[] $Options Option codes for this window,
	 *                                             e.g. array( 'Daytime' ),
	 *                                             array( 'Evening' ),
	 *                                             array( '08:00-12:00' ).
	 *                 }
	 *         }
	 *     }
	 * }
	 *
	 * @throws \Exception If the API request fails (network error or authentication
	 *                    failure).  Missing or empty DeliveryOptions is not an
	 *                    exception; the caller handles empty responses gracefully.
	 */
	public function get_delivery_options( array $post_data ): array;
}
