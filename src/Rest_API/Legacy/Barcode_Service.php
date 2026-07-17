<?php
/**
 * Class Rest_API\Legacy\Barcode_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Barcode\Client;
use PostNLWooCommerce\Rest_API\Legacy\Barcode\Item_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Barcode_Service
 *
 * Stateless service wrapper around Legacy\Barcode\Client.
 * Zero-arg constructable so Service_Factory can return it before request data exists.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Barcode_Service implements Barcode_Service_Interface {

	/**
	 * Generate a barcode for the given post data.
	 *
	 * @param array $post_data Post data for barcode generation.
	 *
	 * @return array
	 */
	public function generate( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new Client( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}
}
