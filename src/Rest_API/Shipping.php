<?php
/**
 * Class Rest_API/Shipping file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use PostNLWooCommerce\Utils;

/**
 * Class Shipping
 *
 * @package PostNLWooCommerce\Rest_API
 */
class Shipping extends Base {
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
			'Customer'  => $this->get_customer_info(),
			/** Hardcoded */
			'Message'   => array(
				'MessageID'        => '36209c3d-14d2-478f-85de-abccd84fa790',
				'MessageTimeStamp' => gmdate( 'd-m-Y H:i:s' ),
				'Printertype'      => 'GraphicFile|PDF',
			),
			'Shipments' => array(
				array(
					'Addresses'           => array(
						$this->get_shipment_address(),
					),
					'Contacts'            => array(
						$this->get_shipment_contact(),
					),
					'Dimension'           => array(
						'Weight' => $this->api_args['order_details']['total_weight'],
					),
					'ProductCodeDelivery' => $this->get_product_code(),
				),
			),
		);
	}

	/**
	 * Get customer info from post data.
	 *
	 * @return array Customer info array.
	 */
	public function get_customer_info() {
		$blog_info = get_bloginfo();

		return array(
			'Address'            => $this->get_customer_address(),
			/* Temporarily hardcoded in Settings::get_location_code(). */
			'CollectionLocation' => $this->api_args['settings']['location_code'],
			'CustomerCode'       => $this->api_args['settings']['customer_code'],
			'CustomerNumber'     => $this->api_args['settings']['customer_num'],
			'ContactPerson'      => $this->api_args['store_address']['company'],
			'Email'              => $this->api_args['store_address']['email'],
			'Name'               => $this->api_args['store_address']['company'],
		);
	}

	/**
	 * Get customer address data from post data.
	 *
	 * @return array Customer address array.
	 */
	public function get_customer_address() {
		return array(
			'AddressType' => '02',
			'City'        => $this->api_args['store_address']['city'],
			'CompanyName' => $this->api_args['store_address']['company'],
			'Countrycode' => $this->api_args['store_address']['country'],
			'HouseNr'     => $this->api_args['store_address']['address_2'],
			'Street'      => $this->api_args['store_address']['address_1'],
			'Zipcode'     => $this->api_args['store_address']['postcode'],
		);
	}

	/**
	 * Get shipment address data from post data.
	 *
	 * @return array Shipment address array.
	 */
	public function get_shipment_address() {
		return array(
			'AddressType' => '01',
			'City'        => $this->api_args['shipping_address']['city'],
			'Countrycode' => $this->api_args['shipping_address']['country'],
			'FirstName'   => $this->api_args['shipping_address']['first_name'],
			'HouseNr'     => $this->api_args['shipping_address']['address_2'],
			'HouseNrExt'  => '',
			'Name'        => $this->api_args['shipping_address']['last_name'],
			'Street'      => $this->api_args['shipping_address']['address_1'],
			'Zipcode'     => $this->api_args['shipping_address']['postcode'],
		);
	}

	/**
	 * Get shipment contact data from post data.
	 *
	 * @return array Shipment contact array.
	 */
	public function get_shipment_contact() {
		return array(
			'ContactType' => '01',
			'Email'       => $this->api_args['billing_address']['email'],
			'SMSNr'       => $this->api_args['billing_address']['phone'],
		);
	}

	/**
	 * Get product code from api args.
	 *
	 * @return String.
	 */
	public function get_product_code() {
		$checked_features = array_filter(
			$this->api_args['backend_data'],
			function( $value ) {
				return ( 'yes' === $value );
			}
		);

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
