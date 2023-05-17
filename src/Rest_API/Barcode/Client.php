<?php
/**
 * Class Rest_API\Barcode\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */
class Client extends Base {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/shipment/v1_1/barcode';

	/**
	 * PostnL API Method.
	 *
	 * @var string
	 */
	public $method = 'GET';

	/**
	 * Function for composing API request in the URL for GET request.
	 *
	 * @return Array.
	 */
	public function compose_url_params() {
		$range = Utils::get_barcode_range( $this->item_info->query_args['barcode_type'], $this->item_info->query_args['globalpack_customer_code'] );

		return array(
			'Type'           => $this->item_info->query_args['barcode_type'],
			'Serie'          => $this->item_info->query_args['serie'],
			'CustomerCode'   => $this->item_info->query_args['customer_code'],
			'CustomerNumber' => $this->item_info->query_args['customer_num'],
			'Range'          => $range,
		);
	}
}
