<?php
/**
 * Class Rest_API\Legacy\Postcode_Check_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Rest_API\Contracts\Postcode_Check_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check\Client;
use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check\Item_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Postcode_Check_Service
 *
 * Stateless service wrapper around Legacy\Postcode_Check\Client.
 * Zero-arg constructable so Service_Factory can return it before request data exists.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Postcode_Check_Service implements Postcode_Check_Service_Interface {

	/**
	 * Check the given postcode data.
	 *
	 * @param array $post_data Post data for postcode check.
	 *
	 * @return array
	 */
	public function check( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new Client( $item_info );
		return $client->send_request();
	}
}
