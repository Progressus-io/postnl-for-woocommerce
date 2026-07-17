<?php
/**
 * Interface Postcode_Check_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for Dutch postcode / address validation services.
 *
 * Covers the PostNL postal-code check API
 * (/shipment/checkout/v1/postalcodecheck).  Called from
 * Frontend\Container::validated_address() during the classic checkout
 * order-review refresh, and from Checkout_Blocks\Extend_Block_Core for
 * blocks-checkout validation.
 *
 * This service REMAINS on the Legacy (V1) transport permanently; it is not
 * routed through the V4 API.  The interface exists solely to keep the
 * service-factory layer uniform so callers do not need a special case.
 *
 * Both the Legacy implementation and any future passthrough implementation
 * must implement this interface.
 */
interface Postcode_Check_Service_Interface {

	/**
	 * Validate a Dutch postcode and house number against the PostNL address database.
	 *
	 * Derived from Frontend\Container::validated_address(), which:
	 *   1. Constructs Postcode_Check\Item_Info from $post_data (reads
	 *      shipping_postcode, shipping_house_number, shipping_address_2).
	 *   2. Constructs Postcode_Check\Client with that Item_Info.
	 *   3. Calls send_request() on the client.
	 *   4. Reads $response[0]['city'], $response[0]['streetName'], and
	 *      $response[0]['houseNumber'] to populate the WC session key
	 *      POSTNL_SETTINGS_ID . '_validated_address'.
	 *   5. Sets the '_invalid_address_marker' session key when $response is empty.
	 *
	 * The current PostNL endpoint is /shipment/checkout/v1/postalcodecheck (POST).
	 * Only Dutch (NL) addresses are validated; other countries are not sent.
	 *
	 * @param array $post_data {
	 *     Checkout POST data as collected from the classic checkout form or the
	 *     blocks-checkout AJAX handler.
	 *
	 *     @type string $shipping_postcode    Required. Dutch postcode in the format
	 *                                        '1234AB' (spaces are stripped by
	 *                                        Base_Info::set_settings_data()).
	 *     @type string $shipping_house_number Required. House number string,
	 *                                        e.g. '10' or '10a'.
	 *     @type string $shipping_address_2   Optional. House number extension,
	 *                                        e.g. 'bis'.  Sent as
	 *                                        housenumberaddition in the request body.
	 * }
	 *
	 * @return array Indexed array of address records that match the query.
	 *               Returns an empty array when no matching address is found
	 *               (i.e. the postcode / house number combination is invalid).
	 *               Each record contains at minimum:
	 *               {
	 *                   @type string     $city        City name used to populate the
	 *                                                 billing/shipping city field.
	 *                   @type string     $streetName  Street name used to populate
	 *                                                 the address_1 field.
	 *                   @type string|int $houseNumber House number confirmed by PostNL.
	 *               }
	 *
	 * @throws \Exception If the API request fails at transport level (network error
	 *                    or authentication failure).  An empty or non-matching
	 *                    response is NOT an exception; the caller handles it by
	 *                    setting the invalid-address session marker.
	 */
	public function check( array $post_data ): array;
}
