<?php
/**
 * Class Rest_API\Barcode\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Legacy\Barcode;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Legacy\Barcode
 */
class Client extends Base implements Barcode_Service_Interface {
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

	/**
	 * Generate a barcode for the given post data.
	 *
	 * @param array $post_data Post data for barcode generation.
	 *
	 * @return array
	 */
	public function generate( array $post_data ): array {
		$item_info = new Item_Info( $post_data );
		$client    = new self( $item_info );

		// send_request() can return null on an empty/non-JSON 200; normalize to honor the array return type.
		$response = $client->send_request();
		return is_array( $response ) ? $response : array();
	}
}
