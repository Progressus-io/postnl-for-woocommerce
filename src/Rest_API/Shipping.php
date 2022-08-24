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
	 * Send API request to PostNL Rest API.
	 */
	public function send_request() {
		$api_url = esc_url( $this->get_api_url() );

		/*
		$request_args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers_args(),
			'body'    => wp_json_encode(
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
			),
		);
		*/

		$request_args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers_args(),
			'body'    => wp_json_encode(
				array(
					'Customer'  => $this->get_customer_info(),
					/** Hardcoded */
					'Message'   => array(
						'MessageID'        => '36209c3d-14d2-478f-85de-abccd84fa790',
						'MessageTimeStamp' => '28-04-2020 14:21:08',
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
							/** Hardcoded */
							'Dimension'           => array(
								'Weight' => '4300',
							),
							'ProductCodeDelivery' => '3085',
						),
					),
				)
			),
		);

		$response = wp_remote_request( $api_url, $request_args );
		$body     = wp_remote_retrieve_body( $response );

		return $body;
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
			'CollectionLocation' => $this->settings->get_location_code(),
			'ContactPerson'      => get_bloginfo( 'name' ),
			'CustomerCode'       => $this->settings->get_customer_code(),
			'CustomerNumber'     => $this->settings->get_customer_num(),
			'Email'              => get_bloginfo( 'admin_email' ),
			'Name'               => get_bloginfo( 'name' ),
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
			'City'        => WC()->countries->get_base_city(),
			'CompanyName' => get_bloginfo( 'name' ),
			'Countrycode' => WC()->countries->get_base_country(),
			'HouseNr'     => WC()->countries->get_base_address_2(),
			'Street'      => WC()->countries->get_base_address(),
			'Zipcode'     => WC()->countries->get_base_postcode(),
		);
	}

	/**
	 * Get shipment address data from post data.
	 *
	 * @return array Shipment address array.
	 */
	public function get_shipment_address() {
		$order = $this->post_data['order'];

		return array(
			'AddressType' => '01',
			'City'        => $order->get_shipping_city(),
			'Countrycode' => $order->get_shipping_country(),
			'FirstName'   => $order->get_shipping_first_name(),
			'HouseNr'     => $order->get_shipping_address_2(),
			'HouseNrExt'  => '',
			'Name'        => $order->get_shipping_last_name(),
			'Street'      => $order->get_shipping_address_1(),
			'Zipcode'     => $order->get_shipping_postcode(),
		);
	}

	/**
	 * Get shipment contact data from post data.
	 *
	 * @return array Shipment contact array.
	 */
	public function get_shipment_contact() {
		$order = $this->post_data['order'];

		return array(
			'ContactType' => '01',
			'Email'       => $order->get_billing_email(),
			'SMSNr'       => $order->get_billing_phone(),
		);
	}
}
