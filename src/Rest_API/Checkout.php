<?php
/**
 * Class Rest_API/Checkout file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

use PostNLWooCommerce\Utils;

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
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'OrderDate'        => $this->api_args['current_time'],
			'ShippingDuration' => $this->api_args['settings']['transit_time'],
			'CutOffTimes'      => $this->get_cutoff_times(),
			'HolidaySorting'   => true,
			'Options'          => $this->get_checkout_options(),
			/* Temporarily hardcoded in Settings::get_number_pickup_points(). */
			'Locations'        => $this->api_args['settings']['number_pickup_points'],
			'Days'             => $this->api_args['settings']['number_delivery_days'],
			'Addresses'        => array(
				$this->get_shipping_address(),
				$this->get_shipper_address(),
			),
		);
	}

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return array
	 */
	public function get_cutoff_times() {
		$cutoff_time  = $this->api_args['settings']['cut_off_time'];
		$dropoff_days = $this->api_args['settings']['dropoff_days'];
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

		if ( $this->api_args['settings']['pickup_points_enabled'] ) {
			$options[] = 'Pickup';
		}

		if ( $this->api_args['settings']['delivery_days_enabled'] ) {
			$options[] = 'Daytime';
		}

		if ( $this->api_args['settings']['evening_delivery_enabled'] ) {
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

		$address['Street']      = $this->api_args['shipping_address']['address_1'];
		$address['HouseNr']     = $this->api_args['shipping_address']['address_2'];
		$address['HouseNrExt']  = '';
		$address['Zipcode']     = $this->api_args['shipping_address']['postcode'];
		$address['City']        = $this->api_args['shipping_address']['city'];
		$address['CountryCode'] = $this->api_args['shipping_address']['country'];

		return $address;
	}

	/**
	 * Get shipper address info from the WC Cart data.
	 *
	 * @return array
	 */
	public function get_shipper_address() {
		$address = array(
			'AddressType' => '02',
			'City'        => $this->api_args['store_address']['city'],
			'Countrycode' => $this->api_args['store_address']['country'],
			'HouseNr'     => $this->api_args['store_address']['address_2'],
			'Street'      => $this->api_args['store_address']['address_1'],
			'Zipcode'     => $this->api_args['store_address']['postcode'],
		);

		return $address;
	}
}
