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
	 * Get available country.
	 *
	 * @return array.
	 */
	public static function get_available_currency() {
		return array( 'EUR', 'USD', 'GBP', 'CNY' );
	}

	/**
	 * Get currency from WooCommerce settings.
	 */
	public static function get_woocommerce_currency() {
		return get_woocommerce_currency();
	}

	/**
	 * Check if the current settings use available currency.
	 *
	 * @return Boolean.
	 */
	public static function use_available_currency() {
		return self::check_available_currency( self::get_woocommerce_currency() );
	}

	/**
	 * Check if the currency use available currency.
	 *
	 * @param String $currency Currency code.
	 *
	 * @return Boolean.
	 */
	public static function check_available_currency( $currency = '' ) {
		if ( empty( $currency ) ) {
			return false;
		}

		return ( in_array( $currency, self::get_available_currency(), true ) );
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
	 * Get Unit of Measurement value that is used in PostNL REST API.
	 *
	 * @return String.
	 */
	public static function used_api_uom() {
		// API use Grams.
		return 'g';
	}

	/**
	 * Get Unit of Measurement value from WooCommerce settings.
	 *
	 * @return String.
	 */
	public static function get_uom() {
		return get_option( 'woocommerce_weight_unit' );
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
	 * Check if the current settings use available country.
	 */
	public static function use_available_country() {
		return ( in_array( self::get_base_country(), self::get_available_country(), true ) );
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

		return self::set_address_house_number( $post_data );
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
	 * Convert the distance to KM if needs be.
	 *
	 * @param Float $distance distance in meter.
	 *
	 * @return String.
	 */
	public static function maybe_convert_km( $distance ) {
		$distance = intval( $distance );
		return ( 999 < $distance ) ? round( ( $distance / 1000 ), 2 ) . ' km' : $distance . ' m';
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
	 * Generate PostNL tracking URL.
	 *
	 * @param String $barcode Generated barcode when creating the label.
	 * @param String $destination Two digits ISO country code.
	 * @param String $postcode Destination postcode (optional).
	 *
	 * @return String
	 */
	public static function generate_tracking_url( $barcode, $destination, $postcode = '' ) {
		$url      = 'https://postnl.nl/tracktrace/';
		$url_args = array_filter(
			array(
				'B' => $barcode,
				'P' => $postcode,
				'D' => $destination,
				'T' => 'C',
			),
			function( $arg ) {
				return ! empty( $arg );
			}
		);

		return add_query_arg( $url_args, $url );
	}

	/**
	 * Get the type of tracking note to be saved in the order.
	 *
	 * @return String
	 */
	public static function get_tracking_note_type() {
		return 'customer';
	}

	/**
	 * Get the available barcode type.
	 *
	 * @return String
	 */
	public static function get_available_barcode_type() {
		return array(
			'3S' => '3S',
			'2S' => '2S',
			'CC' => 'CC',
			'CP' => 'CP',
			'CD' => 'CD',
			'CF' => 'CF',
			'LA' => 'LA',
		);
	}

	/**
	 * Generate the label file name.
	 *
	 * @param Int    $order_id ID of the order object.
	 * @param String $label_type Type of label.
	 * @param String $barcode Barcode string.
	 *
	 * @return String.
	 */
	public static function generate_label_name( $order_id, $label_type, $barcode ) {
		return 'postnl-' . $order_id . '-' . $label_type . '-' . $barcode . '.pdf';
	}
	/**
	 * Get the type of label response.
	 *
	 * @return Array.
	 */
	public static function get_label_response_type() {
		return array(
			'MergedLabels'      => array(
				'content_type_key'   => 'Labeltype',
				'content_type_value' => 'Label',
				'barcode_key'        => 'Barcodes',
			),
			'ResponseShipments' => array(
				'content_type_key'   => 'OutputType',
				'content_type_value' => 'PDF',
				'barcode_key'        => 'Barcode',
			),
		);
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

	/**
	 * Generating meta box fields.
	 *
	 * @param array $fields list of fields.
	 */
	public static function fields_generator( $fields ) {
		foreach ( $fields as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}

			if ( ! empty( $field['container'] ) && true === $field['container'] ) {
				?>
				<div class="shipment-postnl-row-container shipment-<?php echo esc_attr( $field['id'] ); ?>">
				<?php
			}

			switch ( $field['type'] ) {
				case 'select':
					woocommerce_wp_select( $field );
					break;

				case 'checkbox':
					woocommerce_wp_checkbox( $field );
					break;

				case 'hidden':
					woocommerce_wp_hidden_input( $field );
					break;

				case 'radio':
					woocommerce_wp_radio( $field );
					break;

				case 'textarea':
					woocommerce_wp_textarea_input( $field );
					break;

				case 'break':
					echo '<div class="postnl-break-line ' . esc_attr( $field['id'] ) . '"><hr id="' . esc_attr( $field['id'] ) . '" /></div>';
					break;

				case 'text':
				case 'number':
				default:
					woocommerce_wp_text_input( $field );
					break;
			}

			if ( ! empty( $field['container'] ) && true === $field['container'] ) {
				?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Get log URL in the admin.
	 *
	 * @return String.
	 */
	public static function get_log_url() {
		return Logger::get_log_url();
	}

    /**
     * Get house number from address.
     *
     */
    public static function set_address_house_number( $post_data ) {
        // Return house number if posted
        if ( isset( $post_data['billing_house_number'] ) && empty( $post_data['ship_to_different_address'] ) ) {
            // Set shipping house number
	        $post_data['shipping_house_number' ] = $post_data['billing_house_number'];
            return $post_data;
        } elseif ( isset( $post_data['shipping_house_number' ] ) ) {
            // Nothing to do
            return $post_data;
        }

        // Split Address 1 then set house number & House Number Extension
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
	    $house_number_key = false;
	    // Break address into pieces by spaces
	    $address_exploded = explode( ' ', $post_data['shipping_address_1'] );

	    // If no spaces found
	    if( count($address_exploded) == 1 ) {
		    // Break address into pieces by '.'
		    $address_exploded = explode( '.', $post_data['shipping_address_1'] );
	    }

	    // If greater than 1, means there are two parts to the address
	    if ( count( $address_exploded ) > 1 ) {
		    foreach ( $address_exploded as $address_key => $address_value ) {
			    if ( is_numeric( $address_value ) ) {
				    // Set last index as street number
				    $house_number_key = $address_key;
			    }

                /*
                 * Todo: check if $address_value is roman number
                 */
		    }

		    $post_data['shipping_house_number' ] = $address_exploded[ $house_number_key ];
	    }

        return $post_data;
    }
}
