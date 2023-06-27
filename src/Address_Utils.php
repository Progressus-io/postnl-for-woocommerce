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
			$post_data['shipping_first_name'] = $post_data['billing_first_name'] ?? '';
			$post_data['shipping_last_name']  = $post_data['billing_last_name'] ?? '';
			$post_data['shipping_company']    = $post_data['billing_company'] ?? '';
			$post_data['shipping_address_1']  = $post_data['billing_address_1'] ?? '';
			$post_data['shipping_address_2']  = $post_data['billing_address_2'] ?? '';
			$post_data['shipping_city']       = $post_data['billing_city'] ?? '';
			$post_data['shipping_state']      = $post_data['billing_state'] ?? '';
			$post_data['shipping_country']    = $post_data['billing_country'] ?? '';
			$post_data['shipping_postcode']   = $post_data['billing_postcode'] ?? '';
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
		return self::split_address( $post_data, true );
	}

	/**
	 * Split address into street and house number.
	 *
	 * @param $post_data
	 *
	 * @return mixed|string
	 */
	public static function split_address( $post_data, $is_checkout_data = false ) {
		// If its checkout posted data, add shipping_ prefix to array keys
		$address_type = '';
		if ( $is_checkout_data ) {
			$address_type = 'shipping_';
		}

		// If its contain house number, nothing to do
		if ( ! empty( $post_data[ $address_type . 'house_number' ] ) ) {
			return $post_data;
		}

		// Break address into pieces by spaces
		$address_exploded   = explode( ' ', $post_data[ $address_type . 'address_1' ] );
		$address_has_number = false;

		foreach ( $address_exploded as $address_key => $address_value ) {
			// Number count in address 1
			if ( is_numeric( $address_value ) ) {
				$set_key            = $address_key;
				$address_has_number = true;
			}
		}

		if ( ! $address_has_number ) {
			return $post_data;
		}

		// The number is the first part of address 1
		if ( 0 === $set_key ) {
			// Set "house_number" first, as first part
			$post_data[ $address_type . 'house_number' ] = implode( ' ', array_slice( $address_exploded, 0, 1 ) );

			// Remove "house_number" from "address_1"
			$post_data[ $address_type . 'address_1' ] = implode( ' ', array_slice( $address_exploded, 1 ) );
		} else {
			if ( empty( $post_data[ $address_type . 'address_2' ] ) ) {
				// Set "address_2" to be house number extension
				$post_data[ $address_type . 'address_2' ] = implode( ' ', array_slice( $address_exploded, $set_key + 1, count( $address_exploded ) ) );
			}

			$post_data[ $address_type . 'house_number' ] = $address_exploded[ $set_key ];
			$post_data[ $address_type . 'address_1' ]    = implode( ' ', array_slice( $address_exploded, 0, $set_key ) );
		}

		return $post_data;
	}
}