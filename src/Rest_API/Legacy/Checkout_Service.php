<?php
/**
 * Class Rest_API\Legacy\Checkout_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Checkout\Client;
use PostNLWooCommerce\Rest_API\Legacy\Checkout\Item_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout_Service
 *
 * Stateless service wrapper around Legacy\Checkout\Client.
 * Zero-arg constructable so Service_Factory can return it before request data exists.
 * The PostNL checkout endpoint returns both delivery options and pickup locations in a
 * single response; both interface methods delegate to the same underlying API call.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Checkout_Service implements Timeframe_Service_Interface, Pickup_Location_Service_Interface {

	/**
	 * Get delivery options for the given post data.
	 *
	 * @param array $post_data Post data for delivery options request.
	 *
	 * @return array
	 */
	public function get_delivery_options( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new Client( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}

	/**
	 * Get pickup locations for the given post data.
	 *
	 * @param array $post_data Post data for pickup locations request.
	 *
	 * @return array
	 */
	public function get_pickup_locations( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new Client( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}
}
