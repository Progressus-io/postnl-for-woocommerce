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
	 * Convert the key if it's different.
	 *
	 * @param String $key Key of the data.
	 *
	 * @return String.
	 */
	public static function convert_data_key( $key ) {
		$keys = array(
			'dropoff_points' => 'pickup_points',
		);

		return ! empty( $keys[ $key ] ) ? $keys[ $key ] : $key;
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

	/**
	 * Parsers a given array of arguments using a specific scheme.
	 *
	 * The scheme is a `key => array` associative array, where the `key` represents the argument key and the `array`
	 * represents the scheme for that single argument. Each scheme may have the following:
	 * * `default` - the default value to use if the arg is not given
	 * * `error` - the message of the exception if the arg is not given and no `default` is in the scheme
	 * * `validate` - a validation callback that receives the arg, the args array and the scheme as arguments.
	 * * `sanitize` - a sanitization callback similar to `validate` but should return the sanitized value.
	 * * `rename` - an optional new name for the argument key.
	 *
	 * @since [*next-version*]
	 *
	 * @param array $args   The arguments to parse.
	 * @param array $scheme The scheme to parse with, or a fixed scalar value.
	 *
	 * @return array The parsed arguments.
	 *
	 * @throws \Exception If an argument does not exist in $args and has no `default` in the $scheme.
	 */
	public static function parse_args( $args, $scheme ) {
		$final_args = array();

		foreach ( $scheme as $key => $s_scheme ) {
			// If not an array, just use it as a value.
			if ( ! is_array( $s_scheme ) ) {
				$final_args[ $key ] = $s_scheme;
				continue;
			}

			// Rename the key if "rename" was specified.
			$new_key = empty( $s_scheme['rename'] ) ? $key : $s_scheme['rename'];

			// Recurse for array values and nested schemes.
			if ( ! empty( $args[ $key ] ) && isset( $s_scheme[0] ) && is_array( $s_scheme[0] ) ) {
				$final_args[ $new_key ] = static::parse_args( $args[ $key ], $s_scheme );
				continue;
			}

			// If the key is not set in the args.
			if ( ! isset( $args[ $key ] ) ) {
				// If no default value is given, throw.
				if ( ! isset( $s_scheme['default'] ) ) {
					// If no default value is specified, throw an exception.
					$message = ! isset( $s_scheme['error'] )
						// translators: %s is a field argument.
						? sprintf( __( 'Please specify a "%s" argument', 'postnl-for-woocommerce' ), $key )
						: $s_scheme['error'];

					throw new \Exception( $message );
				}
				// If a default value is specified, use that as the value.
				$value = $s_scheme['default'];
			} else {
				$value = $args[ $key ];
			}

			// Call the validation function.
			if ( ! empty( $s_scheme['validate'] ) && is_callable( $s_scheme['validate'] ) ) {
				call_user_func_array( $s_scheme['validate'], array( $value, $args, $scheme ) );
			}

			// Call the sanitization function and get the sanitized value.
			if ( ! empty( $s_scheme['sanitize'] ) && is_callable( $s_scheme['sanitize'] ) ) {
				$value = call_user_func_array( $s_scheme['sanitize'], array( $value, $args, $scheme ) );
			}

			$final_args[ $new_key ] = $value;
		}

		return $final_args;
	}

	/**
	 * Unset/remove any items that are empty strings or 0
	 *
	 * @param array $array Array value.
	 * @return array
	 */
	public static function unset_empty_values( array $array ) {
		foreach ( $array as $k => $v ) {
			if ( is_array( $v ) ) {
				$array[ $k ] = self::unset_empty_values( $v );
			}

			if ( empty( $v ) ) {
				unset( $array[ $k ] );
			}
		}

		return $array;
	}

	/**
	 * Get shipping zone base on the shipping country.
	 *
	 * @param String $to_country 2 digit country code.
	 *
	 * @return String
	 */
	public static function get_shipping_zone( $to_country ) {
		if ( 'NL' === $to_country || 'BE' === $to_country ) {
			return $to_country;
		} elseif ( in_array( $to_country, WC()->countries->get_european_union_countries(), true ) ) {
			return 'EU';
		}

		return 'ROW';
	}

	/**
	 * Check if the string is JSON or not.
	 *
	 * @param Mixed $value String or value that will be validated.
	 *
	 * @return Boolean
	 */
	public static function is_json( $value ) {
		if ( is_array( $value ) || is_object( $value ) ) {
			return false;
		}

		json_decode( $value );
		return json_last_error() === JSON_ERROR_NONE;
	}
}
