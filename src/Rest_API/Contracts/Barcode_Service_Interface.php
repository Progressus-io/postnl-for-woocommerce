<?php
/**
 * Interface Barcode_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for barcode generation services.
 *
 * Both the Legacy (V1) and any future V4 transport must implement this interface.
 * Callers in Order\Base never need to know which transport answered the request.
 */
interface Barcode_Service_Interface {

	/**
	 * Generate a PostNL barcode for a single shipment.
	 *
	 * Derived from Order\Base::create_barcode() and
	 * Order\Base::maybe_create_return_barcode(), which both:
	 *   1. Construct Barcode\Item_Info from $post_data.
	 *   2. Construct Barcode\Client with that Item_Info.
	 *   3. Call send_request() on the client.
	 *   4. Read $response['Barcode'] as the generated tracking number.
	 *
	 * The current PostNL Barcode API endpoint is /shipment/v1_1/barcode (GET).
	 * Domestic NL/BE shipments use barcode type '3S'; EU uses 'UE'/'LA';
	 * rest-of-world uses the GlobalPack barcode type from settings.
	 *
	 * @param array $post_data {
	 *     Context needed to build and send the barcode request.
	 *
	 *     @type \WC_Order $order        Required. The WooCommerce order for which
	 *                                   to generate the barcode.  Used to read the
	 *                                   shipping country/state and address.
	 *     @type array     $saved_data   Required. Order-level saved data.  The
	 *                                   'backend' sub-key contains the admin-selected
	 *                                   option flags (e.g. 'packets', 'mailboxpacket')
	 *                                   that determine the barcode type via
	 *                                   Barcode\Item_Info::check_product_barcode_type().
	 * }
	 *
	 * Note: return barcodes use a customer code derived internally from
	 * Settings::get_return_customer_code(); it is never supplied by the caller.
	 *
	 * @return array {
	 *     JSON-decoded PostNL barcode API response body.
	 *
	 *     @type string $Barcode The generated PostNL barcode string,
	 *                           e.g. '3SXXXXXXXXXX' for domestic NL,
	 *                           'LA000000000NL' for EU registered packets.
	 *                           This value is stored in
	 *                           _postnl_order_metadata['barcodes'][n]['value'].
	 * }
	 *
	 * @throws \Exception If the API request fails (network error, authentication
	 *                    failure, or missing Barcode key in the response).
	 */
	public function generate( array $post_data ): array;
}
