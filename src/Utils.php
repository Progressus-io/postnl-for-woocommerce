<?php
/**
 * Class Utils file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Utils
 *
 * @package PostNLWooCommerce
 */
class Utils {
	/**
	 * List of days of week.
	 *
	 * @return array.
	 */
	public static function days_of_week() {
		return array(
			'mon' => esc_html__( 'Monday', 'postnl-for-woocommerce' ),
			'tue' => esc_html__( 'Tuesday', 'postnl-for-woocommerce' ),
			'wed' => esc_html__( 'Wednesday', 'postnl-for-woocommerce' ),
			'thu' => esc_html__( 'Thursday', 'postnl-for-woocommerce' ),
			'fri' => esc_html__( 'Friday', 'postnl-for-woocommerce' ),
			'sat' => esc_html__( 'Saturday', 'postnl-for-woocommerce' ),
			'sun' => esc_html__( 'Sunday', 'postnl-for-woocommerce' ),
		);
	}

	/**
	 * Get available country.
	 *
	 * @return array.
	 */
	public static function get_available_country() {
		return array( 'NL', 'BE' );
	}

	/**
	 * Get store base country.
	 *
	 * @return String.
	 */
	public static function get_base_country() {
		$base_location = wc_get_base_location();
		return $base_location['country'];
	}

	/**
	 * Get store base state.
	 *
	 * @return String.
	 */
	public static function get_base_state() {
		$base_location = wc_get_base_location();
		return $base_location['state'];
	}

	/**
	 * Get field name without prefix.
	 *
	 * @param String $prefix Prefix of the field.
	 * @param String $field_name Name of the field.
	 *
	 * @return String
	 */
	public static function remove_prefix_field( $prefix, $field_name ) {
		return str_replace( $prefix, '', $field_name );
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
			$post_data['shipping_address_1'] = $post_data['billing_address_1'];
			$post_data['shipping_address_2'] = $post_data['billing_address_2'];
			$post_data['shipping_city']      = $post_data['billing_city'];
			$post_data['shipping_country']   = $post_data['billing_country'];
			$post_data['shipping_postcode']  = $post_data['billing_postcode'];
		}

		return $post_data;
	}

	/**
	 * Change time string to only display hour and minutes.
	 *
	 * @param String $time_string Time string example ( 23:33:00 ).
	 *
	 * @return String
	 */
	public static function get_hour_min( $time_string ) {
		$exp_time = explode( ':', $time_string );

		if ( empty( $exp_time ) ) {
			return $time_string;
		}

		if ( 2 > count( $exp_time ) ) {
			return $time_string;
		}

		return $exp_time[0] . ':' . $exp_time[1];
	}

	/**
	 * Convert the weight based on the weight unit.
	 *
	 * @param Float $weight Weight of the thing.
	 *
	 * @return Float in gram.
	 */
	public static function maybe_convert_weight( $weight ) {
		return wc_get_weight( $weight, 'g' );
	}
}
