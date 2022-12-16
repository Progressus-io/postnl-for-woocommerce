<?php
/**
 * Class Rest_API\Checkout\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */

namespace PostNLWooCommerce\Rest_API\Checkout;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */
class Client extends Base {
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
			'OrderDate'        => $this->item_info->body['order_date'],
			'ShippingDuration' => $this->item_info->body['shipping_duration'],
			'CutOffTimes'      => $this->get_cutoff_times(),
			'HolidaySorting'   => true,
			'Options'          => $this->get_checkout_options(),
			/* Temporarily hardcoded in Settings::get_number_pickup_points(). */
			'Locations'        => $this->item_info->body['locations'],
			'Days'             => $this->item_info->body['days'],
			'Addresses'        => array(
				array(
					'AddressType' => '01',
					'Street'      => $this->item_info->receiver['address_1'],
					'HouseNr'     => $this->item_info->receiver['address_2'],
					'HouseNrExt'  => '',
					'Zipcode'     => $this->item_info->receiver['postcode'],
					'City'        => $this->item_info->receiver['city'],
					'CountryCode' => $this->item_info->receiver['country'],
				),
				array(
					'AddressType' => '02',
					'Street'      => $this->item_info->shipper['address_1'],
					'HouseNr'     => $this->item_info->shipper['address_2'],
					'Zipcode'     => $this->item_info->shipper['postcode'],
					'City'        => $this->item_info->shipper['city'],
					'CountryCode' => $this->item_info->shipper['country'],
				),
			),
		);
	}

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return array
	 */
	public function get_cutoff_times() {
		$cutoff_time       = $this->item_info->body['cut_off_time'];
		$excl_dropoff_days = $this->item_info->body['excluded_dropoff_days'];
		$cutoff            = array(
			array(
				'Day'       => '00',
				'Available' => true,
				'Type'      => 'Regular',
				'Time'      => $cutoff_time,
			),
		);

		if ( empty( $excl_dropoff_days ) ) {
			return $cutoff;
		}

		foreach ( $excl_dropoff_days as $day ) {
			$cutoff[] = array(
				'Day'       => $day,
				'Available' => false,
			);
		}

		return $cutoff;
	}

	/**
	 * Get options value from the settings.
	 *
	 * @return array
	 */
	public function get_checkout_options() {
		$options = array();

		if ( $this->item_info->body['pickup_points_enabled'] ) {
			$options[] = 'Pickup';
		}

		if ( $this->item_info->body['delivery_days_enabled'] ) {
			$options[] = 'Daytime';
		}

		if ( $this->item_info->body['evening_delivery_enabled'] ) {
			$options[] = 'Evening';
		}

		if ( $this->item_info->body['morning_delivery_enabled'] ) {
			$options[] = 'Morning';
		}

		return $options;
	}
}
