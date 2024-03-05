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
	public $endpoint = '/parcels/v1/shipment/activatereturn';

	/**
	 * Function for composing API request in the URL for GET request.
	 *
	 * @return array.
	 */
	public function compose_url_params() {
		return array(
			'CustomerCode'   => $this->item_info->query_args['customer_code'],
			'CustomerNumber' => $this->item_info->query_args['customer_num'],
			'Barcode'        => $this->item_info->shipment['main_barcode'],
		);
	}

}
