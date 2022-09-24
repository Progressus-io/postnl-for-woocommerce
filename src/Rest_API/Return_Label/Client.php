<?php
/**
 * Class Rest_API\Return_Label\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */

namespace PostNLWooCommerce\Rest_API\Return_Label;

use PostNLWooCommerce\Rest_API\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */
class Client extends Shipping\Client {
	/**
	 * Get customer address information for Rest API.
	 *
	 * @return Array
	 */
	public function get_customer_address() {
		return array(
			'AddressType' => '02',
			'City'        => $this->item_info->customer['return_address_city'],
			'CompanyName' => $this->item_info->customer['return_company'],
			'Countrycode' => $this->item_info->shipper['country'],
			'HouseNr'     => $this->item_info->customer['return_address_2'],
			'Street'      => $this->item_info->customer['return_address_1'],
			'Zipcode'     => $this->item_info->customer['return_address_zip'],
		);
	}
}
