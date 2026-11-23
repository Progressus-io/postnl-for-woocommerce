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
	 * Treat a 4xx or 5xx response as a failed check, such as a 401 for an invalid API key. A 400 is let through.
	 *
	 * @var bool
	 */
	protected $throw_on_http_error = true;

	/**
	 * Let a 400 through: PostNL returns it for an address it can't parse, which the checkout treats as an unknown address.
	 *
	 * @param array  $response      Response from wp_remote_request().
	 * @param string $body_response Response body.
	 *
	 * @throws \Exception On any other status of 400 or higher.
	 */
	protected function check_response_status( $response, $body_response ) {
		if ( 400 === (int) wp_remote_retrieve_response_code( $response ) ) {
			return;
		}

		parent::check_response_status( $response, $body_response );
	}

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
