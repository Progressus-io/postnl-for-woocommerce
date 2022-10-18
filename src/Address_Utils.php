<?php
/**
 * Class Address Utils file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AddressUtils
 *
 * @package PostNLWooCommerce
 */
class Address_Utils {
	/**
	 * Get Cart Billing country
	 *
	 * @return string|null
	 */
	public static function get_customer_billing_country() {
		return WC()->customer->get_billing_country();
	}

	/**
	 * Get Cart Shipping country
	 *
	 * @return string|null
	 */
	public static function get_customer_shipping_country() {
		return WC()->customer->get_shipping_country();
	}

	/**
	 * Set shipping address based on post data from checkout page.
	 *
	 * @param array $post_data Post data from checkout page.
	 *
	 * @return array
	 */
	public static function set_post_data_address( $post_data ) {
		if ( empty( $post_data['ship_to_different_address'] ) ) {
			$post_data['shipping_first_name'] = $post_data['billing_first_name'];
			$post_data['shipping_last_name']  = $post_data['billing_last_name'];
			$post_data['shipping_company']    = $post_data['billing_company'];
			$post_data['shipping_address_1']  = $post_data['billing_address_1'];
			$post_data['shipping_address_2']  = $post_data['billing_address_2'];
			$post_data['shipping_city']       = $post_data['billing_city'];
			$post_data['shipping_state']      = $post_data['billing_state'];
			$post_data['shipping_country']    = $post_data['billing_country'];
			$post_data['shipping_postcode']   = $post_data['billing_postcode'];
		}

		return self::set_address_house_number( $post_data );
	}

	/**
	 * Get house number from address.
	 *
	 */
	public static function set_address_house_number( $post_data ) {
		// Return house number if posted
		if ( isset( $post_data['billing_house_number'] ) && empty( $post_data['ship_to_different_address'] ) ) {
			// Set shipping house number
			$post_data['shipping_house_number'] = $post_data['billing_house_number'];

			return $post_data;
		} elseif ( isset( $post_data['shipping_house_number'] ) ) {
			// Nothing to do
			return $post_data;
		}

		// Split Address 1 then set HouseNumber & HouseNumber Extension
		return self::split_address( $post_data );
	}

	/**
	 * Split address into street and house number.
	 *
	 * @param $post_data
	 *
	 * @return mixed|string
	 */
	private static function split_address( $post_data ) {
		// Break address into pieces by spaces
		$address_exploded = explode( ' ', $post_data['shipping_address_1'] );

		// If no spaces found
		if ( count( $address_exploded ) == 1 ) {
			// Break address into pieces by '.'
			$address_exploded = explode( '.', $post_data['shipping_address_1'] );
		}

		$house_number_key = false;
		foreach ( $address_exploded as $address_key => $address_value ) {
			if ( is_numeric( $address_value ) ) {
				// Set last index as street number
				$house_number_key = $address_key;
			}
		}

		// if no house number found
		if ( ! $house_number_key ) {
			if ( is_numeric( $post_data['shipping_address_2'] ) ) {
				// Set Address 2 as house number if its number
				$post_data['shipping_house_number'] = $post_data['shipping_address_2'];
				$post_data['shipping_address_2']    = '';
			}

			return $post_data;
		}

		// if Address contains street name and house number
		if ( 2 == count( $address_exploded ) ) {
			// Set house number
			$post_data['shipping_house_number'] = $address_exploded[ $house_number_key ];

			return $post_data;
		}

		if ( 3 === count( $address_exploded ) ) {
			if ( ! is_numeric( $address_exploded[1] ) && 2 === $house_number_key ) {
				// ex: De Lindelaan 20
				$post_data['shipping_house_number'] = $address_exploded[ $house_number_key ];

				return $post_data;
			}

			// if address contains 2 numbers
			if ( is_numeric( $address_exploded[1] ) ) {
				// Set house number and extension
				if ( ! empty( $post_data['shipping_address_2'] ) ) {
					$post_data['shipping_address_1']    = $address_exploded[0] . ' ' . $address_exploded[1];
					$post_data['shipping_house_number'] = $address_exploded[2];
				} else {
					$post_data['shipping_address_1']    = $address_exploded[0];
					$post_data['shipping_house_number'] = $address_exploded[1];
					$post_data['shipping_address_2']    = $address_exploded[2];
				}

				return $post_data;
			}
		}

		if ( 4 === count( $address_exploded ) && empty( $post_data['shipping_address_2'] ) ) {
			if ( is_numeric( $address_exploded[1] ) ) {
				// Set house number and extension
				$post_data['shipping_address_1']    = $address_exploded[0] . ' ' . $address_exploded[1];
				$post_data['shipping_house_number'] = $address_exploded[2];
				$post_data['shipping_address_2']    = $address_exploded[3];

				return $post_data;
			}
		}

		return $post_data;
	}
}