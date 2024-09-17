<?php
/**
 * Class Rest_API\Smart_Returns\Client
 *
 * @package PostNLWooCommerce\Rest_API\Smart_Returns
 */

namespace PostNLWooCommerce\Rest_API\Smart_Returns;

use PostNLWooCommerce\Rest_API\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Smart_Returns
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
	public $endpoint = '/shipment/v2_2/label/';

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'Customer'  => array(
				'Address'            => array(
					'AddressType' => '02',
					'City'        => $this->item_info->customer['city'],
					'CompanyName' => $this->item_info->customer['company'],
					'Countrycode' => $this->item_info->customer['country'],
					'Country'     => $this->item_info->customer['country'],
					'HouseNr'     => $this->item_info->customer['house_number'],
					'Street'      => $this->item_info->customer['address_1'],
					'Zipcode'     => $this->item_info->customer['postcode'],
				),
				'CollectionLocation' => $this->item_info->store['location_code'],
				'CustomerCode'       => $this->item_info->store['customer_code'],
				'CustomerNumber'     => $this->item_info->store['customer_number'],
				'Email'              => $this->item_info->store['email'],
			),
			'Message'   => array(
				'MessageID'        => $this->item_info->message['id'],
				'MessageTimeStamp' => $this->item_info->message['time_stamp'],
				'Printertype'      => $this->item_info->message['printer_type'],
			),
			'Shipments' => array(
				'Addresses'           => array(
					'AddressType' => '01',
					'City'        => $this->item_info->store['city'],
					'CompanyName' => $this->item_info->store['company'],
					'Countrycode' => $this->item_info->store['country'],
					'HouseNr'     => $this->item_info->store['address_2'],
					'Street'      => $this->item_info->store['address_1'],
					'Zipcode'     => $this->item_info->store['postcode'],
				),
				'Contacts'            => array(
					'ContactType' => '01',
					'Email'       => $this->item_info->store['email'],
				),
				'ProductCodeDelivery' => $this->settings->get_return_address_or_reply_no() ? '3285' : '2285',
				'ProductOptions'      => array(
					'Characteristic' => '152',
					'Option'         => '025',
				),
				'Reference'           => 'cust order ref ' . $this->item_info->order_id,
			)
		);
	}
}
