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
}
