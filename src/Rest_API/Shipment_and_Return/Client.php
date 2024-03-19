<?php
/**
 * Class Rest_API\Checkout\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */

namespace PostNLWooCommerce\Rest_API\Shipment_and_Return;

use PostNLWooCommerce\Rest_API\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Shipment_and_return
 */
class Client extends Base {

	/**
	 * PostnL API Method.
	 *
	 * @var string
	 */
	public $method = 'POST';

	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/parcels/v1/shipment/activatereturn/';

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'CustomerNumber' => $this->item_info->body['CustomerNumber'],
			'CustomerCode'   => $this->item_info->body['CustomerCode'],
			'Barcode'        => $this->item_info->body['Barcode'],
		);
	}

}
