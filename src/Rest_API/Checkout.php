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
class Checkout extends Base {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/shipment/v1/checkout';

	/**
	 * Send API request to PostNL Rest API.
	 */
	public function send_request() {
		$api_url      = esc_url( $this->get_api_url() );
		$request_args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers_args(),
			'body'    => wp_json_encode(
				array(
					'OrderDate'        => $this->get_current_time(),
					'ShippingDuration' => $this->settings->get_transit_time(),
					'CutOffTimes'      => $this->get_cutoff_times(),
					'HolidaySorting'   => true,
					'Options'          => $this->get_checkout_options(),
					'Locations'        => $this->settings->get_number_pickup_points(),
					'Days'             => $this->settings->get_number_delivery_days(),
					'Addresses'        => array(
						$this->get_shipping_address(),
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

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return String
	 */
	public function get_current_time() {
		return gmdate( 'd-m-Y H:i:s' );
	}

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return array
	 */
	public function get_cutoff_times() {
		$cutoff_time  = $this->settings->get_cut_off_time();
		$dropoff_days = $this->settings->get_dropoff_days();
		$cutoff       = array();

		if ( empty( $dropoff_days ) ) {
			return $cutoff;
		}

		if ( empty( $cutoff_time ) ) {
			$cutoff_time = '23:00';
		}

		foreach ( $dropoff_days as $day ) {
			$cutoff[] = array(
				'Day'       => $this->convert_day_to_number( $day ),
				'Available' => true,
				'Type'      => 'Regular',
				'Time'      => $cutoff_time,
			);
		}

		return $cutoff;
	}

	/**
	 * Convert day string to number.
	 *
	 * @param String $day Three character of day name.
	 *
	 * @return String
	 */
	public function convert_day_to_number( $day ) {
		$code = '';

		switch ( $day ) {
			case 'mon':
			case 'monday':
				$code = '01';
				break;

			case 'tue':
			case 'tuesday':
				$code = '02';
				break;

			case 'wed':
			case 'wednesday':
				$code = '03';
				break;

			case 'thu':
			case 'thursday':
				$code = '04';
				break;

			case 'fri':
			case 'friday':
				$code = '05';
				break;

			case 'sat':
			case 'saturday':
				$code = '06';
				break;

			case 'sun':
			case 'sunday':
				$code = '07';
				break;

			default:
				$code = '';
				break;
		}

		return $code;
	}

	/**
	 * Get options value from the settings.
	 *
	 * @return array
	 */
	public function get_checkout_options() {
		$options = array();

		if ( $this->settings->is_pickup_points_enabled() ) {
			$options[] = 'Pickup';
		}

		if ( $this->settings->is_delivery_days_enabled() ) {
			$options[] = 'Daytime';
		}

		if ( $this->settings->is_evening_delivery_enabled() ) {
			$options[] = 'Evening';
		}

		return $options;
	}

	/**
	 * Get shipping address info from the WC Cart data.
	 *
	 * @return array
	 */
	public function get_shipping_address() {
		$address = array(
			'AddressType' => '01',
		);

		$address['Street']      = ( ! empty( $this->post_data['shipping_address_1'] ) ) ? $this->post_data['shipping_address_1'] : '';
		$address['HouseNr']     = ( ! empty( $this->post_data['shipping_address_2'] ) ) ? $this->post_data['shipping_address_2'] : '';
		$address['HouseNrExt']  = '';
		$address['Zipcode']     = ( ! empty( $this->post_data['shipping_postcode'] ) ) ? $this->post_data['shipping_postcode'] : '';
		$address['City']        = ( ! empty( $this->post_data['shipping_city'] ) ) ? $this->post_data['shipping_city'] : '';
		$address['CountryCode'] = ( ! empty( $this->post_data['shipping_country'] ) ) ? $this->post_data['shipping_country'] : '';

		return $address;
	}
}
