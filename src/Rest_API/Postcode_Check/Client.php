<?php
/**
 * Class Rest_API\Postcode_Check\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Postcode_Check
 */

namespace PostNLWooCommerce\Rest_API\Postcode_Check;

use PostNLWooCommerce\Rest_API\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */
class Client extends Base {
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
			'postalcode'            => $this->item_info->receiver['postcode'],
			'housenumber'           => $this->item_info->receiver['house_number'],
			'housenumberaddition'   => $this->item_info->receiver['address_2']
		);
	}
}
