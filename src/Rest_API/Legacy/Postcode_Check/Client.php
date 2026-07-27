<?php
/**
 * Class Rest_API\Postcode_Check\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy\Postcode_Check
 */

namespace PostNLWooCommerce\Rest_API\Legacy\Postcode_Check;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Rest_API\Contracts\Postcode_Check_Service_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Legacy\Postcode_Check
 */
class Client extends Base implements Postcode_Check_Service_Interface {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/shipment/checkout/v1/postalcodecheck';

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'postalcode'          => $this->item_info->receiver['postcode'],
			'housenumber'         => $this->item_info->receiver['house_number'],
			'housenumberaddition' => $this->item_info->receiver['address_2'],
		);
	}

	/**
	 * Check the given postcode data.
	 *
	 * @param array $post_data Post data for postcode check.
	 *
	 * @return array
	 */
	public function check( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new self( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}
}
