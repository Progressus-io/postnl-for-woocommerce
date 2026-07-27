<?php
/**
 * Class Rest_API\Legacy\Smart_Returns_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Client;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Item_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Smart_Returns_Service
 *
 * Stateless service wrapper around Legacy\Smart_Returns\Client.
 * Zero-arg constructable so Service_Factory can return it before request data exists.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Smart_Returns_Service implements Smart_Returns_Service_Interface {

	/**
	 * Generate a smart return label for the given order.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 *
	 * @return array
	 */
	public function generate( \WC_Order $order ): array {
		$item_info = new Item_Info( $order );
		$client    = new Client( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}
}
