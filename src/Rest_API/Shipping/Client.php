<?php
/**
 * Class Rest_API\Shipping\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipping;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */
class Client extends Base {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/v1/shipment?confirm=true';

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		/*
		Example body request.
		array(
			'Customer'  => array(
				'Address'            => array(
					'AddressType' => '02',
					'City'        => 'Hoofddorp',
					'CompanyName' => 'PostNL',
					'Countrycode' => 'NL',
					'HouseNr'     => '42',
					'Street'      => 'Siriusdreef',
					'Zipcode'     => '2132WT',
				),
				'CollectionLocation' => '1234506',
				'ContactPerson'      => 'Janssen',
				'CustomerCode'       => 'DEVC',
				'CustomerNumber'     => '11223344',
				'Email'              => 'email@company.com',
				'Name'               => 'Janssen',
			),
			'Message'   => array(
				'MessageID'        => '36209c3d-14d2-478f-85de-abccd84fa790',
				'MessageTimeStamp' => '28-04-2020 14:21:08',
				'Printertype'      => 'GraphicFile|PDF',
			),
			'Shipments' => array(
				array(
					'Addresses'           => array(
						array(
							'AddressType' => '01',
							'City'        => 'Utrecht',
							'Countrycode' => 'NL',
							'FirstName'   => 'Peter',
							'HouseNr'     => '9',
							'HouseNrExt'  => 'a bis',
							'Name'        => 'de Ruiter',
							'Street'      => 'Bilderdijkstraat',
							'Zipcode'     => '3532VA',
						),
					),
					'Contacts'            => array(
						array(
							'ContactType' => '01',
							'Email'       => 'receiver@email.com',
							'SMSNr'       => '+31612345678',
						),
					),
					'Dimension'           => array(
						'Weight' => '4300',
					),
					'ProductCodeDelivery' => '3085',
				),
			),
		)
		*/
		return array(
			'Customer'  => array(
				'Address'            => array(
					'AddressType' => '02',
					'City'        => $this->item_info->shipper['city'],
					'CompanyName' => $this->item_info->shipper['company'],
					'Countrycode' => $this->item_info->shipper['country'],
					'HouseNr'     => $this->item_info->shipper['address_2'],
					'Street'      => $this->item_info->shipper['address_1'],
					'Zipcode'     => $this->item_info->shipper['postcode'],
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
			'Shipments' => array(
				array(
					'Addresses'           => array(
						array(
							'AddressType' => '01',
							'City'        => $this->item_info->receiver['city'],
							'Countrycode' => $this->item_info->receiver['country'],
							'FirstName'   => $this->item_info->receiver['first_name'],
							'HouseNr'     => $this->item_info->receiver['address_2'],
							'HouseNrExt'  => '',
							'Name'        => $this->item_info->receiver['last_name'],
							'Street'      => $this->item_info->receiver['address_1'],
							'Zipcode'     => $this->item_info->receiver['postcode'],
						),
					),
					'Contacts'            => array(
						array(
							'ContactType' => '01',
							'Email'       => $this->item_info->shipment['email'],
							'SMSNr'       => $this->item_info->shipment['phone'],
						),
					),
					'Dimension'           => array(
						'Weight' => $this->item_info->shipment['total_weight'],
					),
					'ProductCodeDelivery' => $this->item_info->shipment['product_code'],
				),
			),
		);
	}

	/**
	 * Get selected features in the order admin.
	 *
	 * @return Array
	 */
	public function get_selected_features() {
		return array_filter(
			$this->api_args['backend_data'],
			function( $value ) {
				return ( 'yes' === $value );
			}
		);
	}

	/**
	 * Get product code from api args.
	 *
	 * @return String.
	 */
	public function get_product_code() {
		$checked_features = $this->get_selected_features();

		$features = array_keys( $checked_features );

		$product_code = '3085';

		if ( in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3385';
		}

		if ( in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3090';
		}

		if ( in_array( 'return_no_answer', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3390';
		}

		if ( in_array( 'insured_shipping', $features, true ) ) {
			$product_code = '3087';
		}

		if ( in_array( 'insured_shipping', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3094';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) ) {
			$product_code = '3189';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3089';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3096';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3389';
		}

		if ( in_array( 'letterbox', $features, true ) ) {
			$product_code = '2928';
		}

		return $product_code;
	}

	/**
	 * Get product code that based on NL.
	 *
	 * @return String
	 */
	public function get_product_code_nl() {
		$checked_features = $this->get_selected_features();

		$features = array_keys( $checked_features );

		$product_code = '3085';

		if ( in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3385';
		}

		if ( in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3090';
		}

		if ( in_array( 'return_no_answer', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3390';
		}

		if ( in_array( 'insured_shipping', $features, true ) ) {
			$product_code = '3087';
		}

		if ( in_array( 'insured_shipping', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3094';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) ) {
			$product_code = '3189';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3089';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3096';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3389';
		}

		if ( in_array( 'letterbox', $features, true ) ) {
			$product_code = '2928';
		}

		return $product_code;
	}
}
