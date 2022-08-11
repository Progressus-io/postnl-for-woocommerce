<?php
/**
 * Class Rest_API/Checkout file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout
 *
 * @package PostNLWooCommerce\Rest_API
 */
class Checkout {
	/**
	 * Send API request to PostNL Rest API.
	 */
	public static function send_request() {
		$api_url      = 'https://api-sandbox.postnl.nl/shipment/v1/checkout';
		$request_args = array(
			'method'  => 'POST',
			'headers' => array(
				'apikey'       => 'x5Ki8rRTrfG1EJTnJL7s6qi2H8dQuJUy',
				'accept'       => 'application/json',
				'Content-Type' => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'OrderDate'        => '31-07-2022 23:00:00',
					'ShippingDuration' => '2',
					'CutOffTimes'      => array(
						array(
							'Day'       => '00',
							'Available' => true,
							'Type'      => 'Regular',
							'Time'      => '23:00:00',
						),
						array(
							'Day'       => '07',
							'Available' => false,
							'Type'      => 'Regular',
						),
					),
					'HolidaySorting'   => true,
					'Options'          => array(
						'Daytime',
						'Evening',
						'Pickup',
					),
					'Locations'        => 3,
					'Days'             => 5,
					'Addresses'        => array(
						array(
							'AddressType' => '01',
							'Street'      => 'Molengraaffplantsoen',
							'HouseNr'     => '74',
							'HouseNrExt'  => 'bis',
							'Zipcode'     => '3571ZZ',
							'City'        => 'Utrecht',
							'CountryCode' => 'NL',
						),
						array(
							'AddressType' => '02',
							'CountryCode' => 'NL',
						),
					),
				)
			),
		);

		$response = wp_remote_request( $api_url, $request_args );
		$body     = wp_remote_retrieve_body( $response );

		return $body;
	}
}
