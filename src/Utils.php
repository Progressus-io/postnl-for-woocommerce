<?php
/**
 * Class Utils file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

use PostNLWooCommerce\Helper\Mapping;

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
	 * Get available country for the letterbox.
	 *
	 * @return array.
	 */
	public static function get_available_country_for_letterbox() {
		return array( 'NL' );
	}

	/**
	 * Get available country.
	 *
	 * @return array.
	 */
	public static function get_available_currency() {
		// Get all WooCommerce currencies
		$woocommerce_currencies = array_keys( get_woocommerce_currencies() );

		return $woocommerce_currencies;
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
			function ( $arg ) {
				return ! empty( $arg );
			}
		);

		return add_query_arg( $url_args, $url );
	}

	/**
	 * Generate the label file name.
	 *
	 * @param Int $order_id ID of the order object.
	 * @param String $label_type Type of label.
	 * @param String $barcode Barcode string.
	 * @param String $label_format Label Format whether A4 or A6.
	 *
	 * @return String.
	 */
	public static function generate_label_name( $order_id, $label_type, $barcode, $label_format ) {
		return 'postnl-' . $order_id . '-' . $label_type . '-' . $barcode . '-' . $label_format . '.pdf';
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
	 * @param array $args The arguments to parse.
	 * @param array $scheme The scheme to parse with, or a fixed scalar value.
	 *
	 * @return array The parsed arguments.
	 *
	 * @throws \Exception If an argument does not exist in $args and has no `default` in the $scheme.
	 * @since [*next-version*]
	 *
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
	 *
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
	 * Get paper size information.
	 *
	 * @param String $paper Paper name.
	 *
	 * @return Array.
	 */
	public static function get_paper_size( $paper = 'A4' ) {
		$papers = array(
			'A4' => array(
				'width'  => '297.03888888889',
				'height' => '209.90277777778',
			),
			'A6' => array(
				'width'  => '148.00086111111',
				'height' => '105.00077777778',
			),
		);

		return isset( $papers[ $paper ] ) ? $papers[ $paper ] : array();
	}

	/**
	 * @param string $shipping_method Cart shipping method.
	 *
	 * @return string.
	 */
	public static function get_cart_shipping_method_id( $shipping_method ) {
		if ( empty( $shipping_method ) ) {
			return $shipping_method;
		}

		// Assumes format 'name:id'
		$shipping_method = explode( ':', $shipping_method );

		return $shipping_method[0] ?? $shipping_method;
	}

	/**
	 * Get barcode range.
	 *
	 * @param $barcode_type .
	 * @param $globalpack_customer_code .
	 *
	 * @return string.
	 */
	public static function get_barcode_range( $barcode_type, $globalpack_customer_code ) {
		$globalpack_barcodes = Mapping::products_custom_barcode_types();

		if ( isset( $globalpack_barcodes[ $barcode_type ] ) ) {
			return 'NL';
		}

		if ( 0 === strpos( $barcode_type, 'C' ) ) {
			return $globalpack_customer_code;
		}

		return '';
	}

	/**
	 * Get selected features in the order admin.
	 *
	 * @param array $backend_data list of backend data.
	 *
	 * @return array.
	 */
	public static function get_selected_label_features( $backend_data ) {
		$selected_features = array_filter(
			$backend_data,
			function ( $value ) {
				return ( 'yes' === $value );
			}
		);

		if ( isset( $selected_features['create_return_label'] ) ) {
			unset( $selected_features['create_return_label'] );
		}

		return $selected_features;
	}

	/**
	 * Generate Delivery Date.
	 *
	 * @param $delivery_info
	 *
	 * @return string.
	 */
	public static function generate_delivery_date_html( $delivery_info ) {
		if ( ! isset( $delivery_info['delivery_day_date'] ) ) {
			return __( 'As soon as possible', 'postnl-for-woocommerce' );
		}

		$day = date( 'l', strtotime( $delivery_info['delivery_day_date'] ) );

		// Convert to the Dutch date format
		$date_obj   = date_create_from_format( 'Y-m-d', $delivery_info['delivery_day_date'] );
		$dutch_date = date_format( $date_obj, 'd/m/Y' );

		return $day . ' ' . $dutch_date;
	}


	/**
	 * Generate selected hipping options html.
	 *
	 * @param $backend_data .
	 *
	 * @return string.
	 */
	public static function generate_shipping_options_html( $backend_data, $order_id ) {
		$options_to_display = self::get_shipping_options( $order_id );
		$selected_options   = array();

		foreach ( $backend_data as $option_key => $value ) {
			if ( isset( $options_to_display[ $option_key ] ) && 'yes' === $value ) {
				$selected_options[] = $options_to_display[ $option_key ];
			}
		}

		if ( empty( $selected_options ) ) {
			return '-';
		}

		return implode( ', ', $selected_options );
	}

	/**
	 * Get available shipping options.
	 *
	 * @return array.
	 */
	public static function get_shipping_options( $order_id ) {
		$order                = wc_get_order( $order_id );
		$shipping_destination = Utils::get_shipping_zone( $order->get_shipping_country() );

		if ( 'NL' === $shipping_destination ) {
			return array(
				'standard_shipment'     => esc_html__( 'Standard shipment', 'postnl-for-woocommerce' ),
				'id_check'              => esc_html__( 'ID Check', 'postnl-for-woocommerce' ),
				'insured_shipping'      => esc_html__( 'Insured Shipping', 'postnl-for-woocommerce' ),
				'return_no_answer'      => esc_html__( 'Return if no answer', 'postnl-for-woocommerce' ),
				'signature_on_delivery' => esc_html__( 'Signature on Delivery', 'postnl-for-woocommerce' ),
				'only_home_address'     => esc_html__( 'Only Home Address', 'postnl-for-woocommerce' ),
				'letterbox'             => esc_html__( 'Letterbox', 'postnl-for-woocommerce' ),
				'packets'               => esc_html__( 'Packets', 'postnl-for-woocommerce' ),
				'standard_belgium'      => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
				'mailboxpacket'         => esc_html__( 'Boxable Packet', 'postnl-for-woocommerce' ),
				'track_and_trace'       => esc_html__( 'Track & Trace', 'postnl-for-woocommerce' ),
				'eu_parcel'             => esc_html__( 'Parcels Non-EU Insured', 'postnl-for-woocommerce' ),
				'parcel_non_eu'         => esc_html__( 'Parcels non-EU Insured Plus', 'postnl-for-woocommerce' ),
			);
		} elseif ( 'BE' === $shipping_destination ) {
			return array(
				'standard_shipment'     => esc_html__( 'Standard shipment', 'postnl-for-woocommerce' ),
				'id_check'              => esc_html__( 'ID Check', 'postnl-for-woocommerce' ),
				'return_no_answer'      => esc_html__( 'Return if no answer', 'postnl-for-woocommerce' ),
				'signature_on_delivery' => esc_html__( 'Signature on Delivery', 'postnl-for-woocommerce' ),
				'only_home_address'     => esc_html__( 'Only Home Address', 'postnl-for-woocommerce' ),
				'letterbox'             => esc_html__( 'Letterbox', 'postnl-for-woocommerce' ),
				'packets'               => esc_html__( 'Packet', 'postnl-for-woocommerce' ),
				'standard_belgium'      => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
				'mailboxpacket'         => esc_html__( 'Boxable Packet', 'postnl-for-woocommerce' ),
				'track_and_trace'       => esc_html__( 'Track & Trace', 'postnl-for-woocommerce' ),
				'eu_parcel'             => esc_html__( 'Parcels Non-EU Insured', 'postnl-for-woocommerce' ),
				'insured_shipping'      => esc_html__( 'Insured Shipping', 'postnl-for-woocommerce' ),
				'parcel_non_eu'         => esc_html__( 'Parcels non-EU Insured Plus', 'postnl-for-woocommerce' ),
			);
		} elseif ( 'EU' === $shipping_destination ) {
			return array(
				'standard_shipment'     => esc_html__( 'Standard shipment', 'postnl-for-woocommerce' ),
				'id_check'              => esc_html__( 'ID Check', 'postnl-for-woocommerce' ),
				'return_no_answer'      => esc_html__( 'Return if no answer', 'postnl-for-woocommerce' ),
				'signature_on_delivery' => esc_html__( 'Signature on Delivery', 'postnl-for-woocommerce' ),
				'only_home_address'     => esc_html__( 'Only Home Address', 'postnl-for-woocommerce' ),
				'letterbox'             => esc_html__( 'Letterbox', 'postnl-for-woocommerce' ),
				'packets'               => esc_html__( 'Packet', 'postnl-for-woocommerce' ),
				'standard_belgium'      => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
				'mailboxpacket'         => esc_html__( 'Boxable Packet', 'postnl-for-woocommerce' ),
				'eu_parcel'             => esc_html__( 'Parcels EU', 'postnl-for-woocommerce' ),
				'track_and_trace'       => esc_html__( 'Track & Trace', 'postnl-for-woocommerce' ),
				'insured_shipping'      => esc_html__( 'Insured Shipping', 'postnl-for-woocommerce' ),
				'insured_plus'          => esc_html__( 'Insured Plus', 'postnl-for-woocommerce' ),
			);
		} else {
			return array(
				'standard_shipment'     => esc_html__( 'Standard shipment', 'postnl-for-woocommerce' ),
				'id_check'              => esc_html__( 'ID Check', 'postnl-for-woocommerce' ),
				'return_no_answer'      => esc_html__( 'Return if no answer', 'postnl-for-woocommerce' ),
				'signature_on_delivery' => esc_html__( 'Signature on Delivery', 'postnl-for-woocommerce' ),
				'only_home_address'     => esc_html__( 'Only Home Address', 'postnl-for-woocommerce' ),
				'letterbox'             => esc_html__( 'Letterbox', 'postnl-for-woocommerce' ),
				'packets'               => esc_html__( 'Packet', 'postnl-for-woocommerce' ),
				'standard_belgium'      => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
				'mailboxpacket'         => esc_html__( 'Boxable Packet', 'postnl-for-woocommerce' ),
				'parcel_non_eu'         => esc_html__( 'Parcels Non-EU', 'postnl-for-woocommerce' ),
				'track_and_trace'       => esc_html__( 'Track & Trace', 'postnl-for-woocommerce' ),
				'insured_shipping'      => esc_html__( 'Insured Shipping', 'postnl-for-woocommerce' ),
				'insured_plus'          => esc_html__( 'Insured Plus', 'postnl-for-woocommerce' ),
			);
		}
	}

	/**
	 * Check if current order/cart is eligible for automatically use letterbox.
	 *
	 * @param \WC_Order|\WC_Cart|int $order \WC_order, \WC_Cart or Order ID.
	 *
	 * @return boolean
	 */
	public static function is_eligible_auto_letterbox( $order ) {

		// Check order
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}
		if ( wc_get_base_location()['country'] == 'BE' ) {
			return false;
		}
		if ( is_a( $order, 'WC_Order' ) ) {
			if ( $order->meta_exists( '_postnl_letterbox' ) ) {
				return (bool) $order->get_meta( '_postnl_letterbox', true );
			}
			if ( ! in_array( $order->get_shipping_country(), Utils::get_available_country_for_letterbox(), true ) ) {
				$order->update_meta_data( '_postnl_letterbox', false );
				$order->save();

				return false;
			}
			$products = $order->get_items();
		}

		// Check cart items
		if ( is_a( $order, 'WC_Cart' ) ) {
			if ( ! in_array( WC()->customer->get_shipping_country(), Utils::get_available_country_for_letterbox(), true ) ) {
				return false;
			}
			$products = $order->get_cart();
		}

		$is_eligible = self::check_products_for_letterbox( $products );

		// Save the state for the order.
		if ( is_a( $order, 'WC_Order' ) ) {
			$order->update_meta_data( '_postnl_letterbox', $is_eligible );
			$order->save();
		}

		return $is_eligible;
	}

	/**
	 * Check if given products are suitable for the letterbox.
	 *
	 * @param array $products WC_Products[] or order_item[].
	 *
	 * @return bool
	 */
	public static function check_products_for_letterbox( $products ) {
		$total_ratio_letterbox_item = 0;

		foreach ( $products as $item_id => $item ) {
			$product              = wc_get_product( $item['product_id'] ?? $item->get_product_id() );
			if ( ! is_a( $product, 'WC_Product' ) ) {
				// If the product is not found, consider the order not eligible.
				return false;
			}

			$is_letterbox_product = $product->get_meta( Product\Single::LETTERBOX_PARCEL );

			// If one of the item is not letterbox product, then the order is not eligible automatic letterbox.
			// Thus should return false immediately.
			if ( 'yes' !== $is_letterbox_product ) {
				return false;
			}

			$quantity                   = $item['quantity'] ?? $item->get_quantity();
			$qty_per_letterbox          = intval( $product->get_meta( Product\Single::MAX_QTY_PER_LETTERBOX ) );
			$ratio_letterbox_item       = 0 != $qty_per_letterbox ? 1 / $qty_per_letterbox : 0;
			$total_ratio_letterbox_item += ( $ratio_letterbox_item * $quantity );
		}

		// If the total ratio is more than 1, that means order items cannot be packed using letterbox.
		return ( $total_ratio_letterbox_item <= 1 ) ? true : false;
	}

	/**
	 * Prepare array of selected by the user shipping option.
	 *
	 * @param string $selected_value Selected default shipping option value.
	 *
	 * @return array
	 */
	public static function prepare_shipping_options( $selected_value ) {
		$shipping_options = explode( '|', $selected_value );
		$shipping_options = array_fill_keys( $shipping_options, 'yes' );

		return $shipping_options;
	}
}
