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
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'Customer'  => array(
				'Address'            => array(
					'AddressType' => '02',
					'City'        => $this->item_info->customer['return_address_city'],
					'CompanyName' => $this->item_info->customer['return_company'],
					'Countrycode' => $this->item_info->shipper['country'],
					'HouseNr'     => $this->item_info->customer['return_address_2'],
					'Street'      => $this->item_info->customer['return_address_1'],
					'Zipcode'     => $this->item_info->customer['return_address_zip'],
				),
				/* Temporarily hardcoded in Settings::get_location_code(). */
				'CollectionLocation' => $this->item_info->customer['location_code'],
				'CustomerCode'       => $this->item_info->customer['customer_code'],
				'CustomerNumber'     => $this->item_info->customer['customer_num'],
				'ContactPerson'      => $this->item_info->customer['company'],
				'Email'              => $this->item_info->customer['email'],
				'Name'               => $this->item_info->customer['company'],
			),
			/** Hardcoded */
			'Message'   => array(
				'MessageID'        => '36209c3d-14d2-478f-85de-abccd84fa790',
				'MessageTimeStamp' => gmdate( 'd-m-Y H:i:s' ),
				'Printertype'      => 'GraphicFile|PDF',
			),
			'Shipments' => $this->get_shipments(),
		);
	}
}
