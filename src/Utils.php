<?php
/**
 * Class Utils file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Product\Single;
use WC_Product;
use PostNLWooCommerce\Shipping_Method\Settings;

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
	 * Get the list of countries where adults-only products can be shipped.
	 *
	 * @return array.
	 */
	public static function get_adults_only_shipping_countries(): array {
		return array( 'NL' );
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
	 * @param Int    $order_id ID of the order object.
	 * @param String $label_type Type of label.
	 * @param String $barcode Barcode string.
	 * @param String $label_format Label Format whether A4 or A6.
	 * @param String $extension Label extension format given from API response. The extension could be change by the settings page.
	 *
	 * @return String.
	 */
	public static function generate_label_name( $order_id, $label_type, $barcode, $label_format, $extension ) {
		return 'postnl-' . $order_id . '-' . $label_type . '-' . $barcode . '-' . $label_format . '.' . $extension;
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
	 * Get shipping zone base on the shipping country and state.
	 *
	 * @param String $to_country 2 digit country code.
	 * @param String $to_state 2 digit state code.
	 *
	 * @return String
	 */
	public static function get_shipping_zone( string $to_country, string $to_state ): string {
		if ( in_array( $to_country, array( 'NL', 'BE' ) ) ) {
			return $to_country;
		}

		if ( self::is_canary_island( $to_state, $to_country ) ) {
			return 'ROW';
		}

		if ( in_array( $to_country, WC()->countries->get_european_union_countries(), true ) ) {
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

		// Get the day abbreviation (Mon, Tue, Wed, etc.) and convert to lowercase
		$day_key = strtolower( date( 'D', strtotime( $delivery_info['delivery_day_date'] ) ) );
		
		// Get translated day names from existing method
		$days_of_week = self::days_of_week();
		$day          = $days_of_week[ $day_key ];

		// Convert to the Dutch date format
		$date_obj   = date_create_from_format( 'Y-m-d', $delivery_info['delivery_day_date'] );
		$dutch_date = date_format( $date_obj, 'd/m/Y' );

		return $day . ' ' . $dutch_date;
	}


	/**
	 * Generate selected shipping options html.
	 *
	 * @param $backend_data.
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
		$shipping_destination = self::get_shipping_zone( $order->get_shipping_country(), $order->get_shipping_state() );

		// Base shipping options (common to all destinations).
		$base_options = array(
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
			'insured_shipping'      => esc_html__( 'Insured Shipping', 'postnl-for-woocommerce' ),
			'delivery_code_at_door' => esc_html__( 'Delivery Code at Door', 'postnl-for-woocommerce' ),
		);

		// Modify options based on shipping destination.
		switch ( $shipping_destination ) {
			case 'BE':
			case 'NL':
				$destination_options = array(
					'eu_parcel'     => esc_html__( 'Parcels Non-EU Insured', 'postnl-for-woocommerce' ),
					'parcel_non_eu' => esc_html__( 'Parcels non-EU Insured Plus', 'postnl-for-woocommerce' ),
				);
				break;

			case 'EU':
				$destination_options = array(
					'eu_parcel'    => esc_html__( 'Parcels EU', 'postnl-for-woocommerce' ),
					'insured_plus' => esc_html__( 'Insured Plus', 'postnl-for-woocommerce' ),
				);
				break;

			default:
				$destination_options = array(
					'parcel_non_eu' => esc_html__( 'Parcels Non-EU', 'postnl-for-woocommerce' ),
					'insured_plus'  => esc_html__( 'Insured Plus', 'postnl-for-woocommerce' ),
				);
				break;
		}

		return array_merge( $base_options, $destination_options );
	}

	/**
	 * Check if current cart is eligible for automatically use letterbox.
	 *
	 * @param \WC_Cart|null $cart Cart object.
	 *
	 * @return boolean
	 */
	public static function is_cart_eligible_auto_letterbox( ?\WC_Cart $cart ): bool {
		if ( is_null( $cart ) ) {
			return false;
		}

		if ( ! in_array( WC()->customer->get_shipping_country(), self::get_available_country_for_letterbox(), true ) ) {
			return false;
		}

		
		if ( self::contains_adults_only_products( $cart->get_cart() ) ) {
			return false;
		}

		return self::check_products_for_letterbox( $cart->get_cart() );
	}

	/**
	 * Check if current order/cart is eligible for automatically use letterbox.
	 *
	 * @param \WC_Order|int $order \WC_order or Order ID.
	 *
	 * @return boolean
	 */
	public static function is_order_eligible_auto_letterbox( $order ) {
		if ( wc_get_base_location()['country'] == 'BE' ) {
			return false;
		}

		// Check if order id provided.
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		if ( $order->meta_exists( '_postnl_letterbox' ) ) {
			return (bool) $order->get_meta( '_postnl_letterbox', true );
		}

		if ( ! in_array( $order->get_shipping_country(), self::get_available_country_for_letterbox(), true ) ) {
			$order->update_meta_data( '_postnl_letterbox', false );
			$order->save_meta_data();

			return false;
		}

		$products    = $order->get_items();
		$is_eligible = self::check_products_for_letterbox( $products );

		$order->update_meta_data( '_postnl_letterbox', $is_eligible );
		$order->save_meta_data();

		return $is_eligible;
	}

	/**
	 * Check if given products are suitable for the letterbox.
	 *
	 * @param array $products WC_Products[] or order_item[].
	 *
	 * @return bool
	 */
	public static function check_products_for_letterbox( array $products ): bool {
		$total_fill_ratio = 0;
		$is_eligible      = false;

		foreach ( $products as $item ) {
			$variation_id = $item['variation_id'] ?? $item->get_variation_id();
			$product_id   = $item['product_id'] ?? $item->get_product_id();
			$target_id    = $variation_id > 0 ? $variation_id : $product_id;
			$product      = wc_get_product( $target_id );

			// If the product is not found, consider the order not eligible.
			if ( ! $product instanceof WC_Product ) {
				return false;
			}

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			$is_eligible = self::is_letterbox_parcel_product( $product );

			if ( ! $is_eligible ) {
				return false;
			}

			$quantity = is_array( $item ) ? ( $item['quantity'] ?? 1 ) : $item->get_quantity();
			$max_qty  = (int) $product->get_meta( Product\Single::MAX_QTY_PER_LETTERBOX );
			$parent   = ( $variation_id > 0 ) ? wc_get_product( $product->get_parent_id() ) : null;

			if ( $max_qty <= 0 && $parent ) {
				$max_qty = (int) $parent->get_meta( Product\Single::MAX_QTY_PER_LETTERBOX );
			}

			if ( $max_qty > 0 ) {
				$total_fill_ratio += ( $quantity / $max_qty );
			}
		}

		return $is_eligible && $total_fill_ratio <= 1;
	}

	/**
	 * Determine if the given order contains any adults-only products.
	 *
	 * @param \WC_Order|int $order \WC_order or Order ID.
	 *
	 * @return boolean
	 */
	public static function is_adults_only_order( $order ): bool {
		if ( 'BE' === wc_get_base_location()['country'] ) {
			return false;
		}

		// Check if order id provided.
		if ( is_int( $order ) ) {
			$order = wc_get_order( $order );
		}

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		if ( ! in_array( $order->get_shipping_country(), self::get_adults_only_shipping_countries(), true ) ) {
			return false;
		}

		return self::contains_adults_only_products( $order->get_items() );
	}

	/**
	 * Determine if any products are marked as adults-only.
	 *
	 * @param array $products WC_Products[] or order_item[].
	 *
	 * @return bool
	 */
	public static function contains_adults_only_products( $products ): bool {

		foreach ( $products as $item_id => $item ) {
			$product = wc_get_product( $item['product_id'] ?? $item->get_product_id() );
			if ( ! is_a( $product, 'WC_Product' ) ) {
				continue;
			}

			if ( ! $product->needs_shipping() ) {
				continue;
			}

			if ( self::is_adults_only_product( $product ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if the given product is marked as adults-only.
	 *
	 * @param WC_Product $product Product object.
	 * @return bool
	 */
	public static function is_adults_only_product( WC_Product $product ): bool {
		return 'yes' === $product->get_meta( Single::ADULTS_ONLY_FIELD );
	}

	/**
	 * Check if the given product is marked as Letterbox Parcel.
	 *
	 * @param WC_Product $product Product object.
	 *
	 * @return bool
	 */
	public static function is_letterbox_parcel_product( WC_Product $product ): bool {
		if ( 'yes' === $product->get_meta( Single::LETTERBOX_PARCEL ) ) {
			return true;
		}

		if ( $product instanceof \WC_Product_Variation ) {
			$parent = wc_get_product( $product->get_parent_id() );

			return $parent && 'yes' === $parent->get_meta( Single::LETTERBOX_PARCEL );
		}

		return false;
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

	/**
	 * Get filtered pickup points specific infos.
	 *
	 * @param array $infos Dropoff points informations.
	 *
	 * @return array
	 */
	public static function get_filtered_pickup_points_infos( $infos ) {
		$filtered_infos = array_filter(
			$infos,
			function ( $info ) {
				$displayed_info = array(
					'dropoff_points_date',
					'dropoff_points_time',
				);

				return in_array( $info, $displayed_info, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		$address_info = array_filter(
			$infos,
			function ( $info ) {
				return false !== strpos( $info, '_address_' );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( ! empty( $address_info ) ) {
			$filtered_infos['address'] = implode( ', ', $address_info );
			ksort( $filtered_infos );
		}

		return $filtered_infos;
	}

	/**
	 * The Canary Islands, due to the distance from mainland Spain, count as a non-EU destination from a transport point of view.
	 * This means the regular EU shipments cannot be used for these destinations,
	 * and instead the non-EU product code must be used, along with country code IC.
	 *
	 * Return true if for Spanish states "Santa Cruz de Tenerife" or "Las Palmas".
	 *
	 * @param $state String Shipping state.
	 * @param $country String Shipping country.
	 *
	 * @return bool
	 */
	public static function is_canary_island( string $state, string $country ): bool {
		if ( 'ES' !== strtoupper( $country ) ) {
			return false;
		}

		if ( in_array( $state, array( 'TF', 'GC' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Get the frontend locations.
	 *
	 * @return array $locations
	 */
	public static function get_frontend_locations(): array {
		// Allow filtering of the locations.
		return apply_filters(
			'postnl_frontend_locations',
			array(
				'cart_before_checkout'             => array(
					'woocommerce_proceed_to_checkout',
					'postnl_before_woocommerce/proceed-to-checkout-block',
				),
				'cart_after_checkout'              => array(
					'woocommerce_after_cart_totals',
					'postnl_after_woocommerce/proceed-to-checkout-block',
				),
				'checkout_before_customer_details' => array(
					'woocommerce_checkout_before_customer_details',
				),
				'checkout_after_customer_details'  => array(
					'woocommerce_after_order_notes',
				),
				'minicart_before_buttons'          => array(
					'woocommerce_widget_shopping_cart_before_buttons',
					'postnl_before_woocommerce/mini-cart-footer-block',
				),
				'minicart_after_buttons'           => array(
					'woocommerce_widget_shopping_cart_after_buttons',
					'postnl_after_woocommerce/mini-cart-footer-block',
				),
			)
		);
	}

	/**
	 * Get the frontend location mapping.
	 *
	 * @return array $mapping
	 */
	public static function get_frontend_location_mapping(): array {
		// Allow filtering of the mapping.
		return apply_filters(
			'postnl_frontend_location_mapping',
			array(
				'cart_before_checkout'             => array( 'postnl_cart_auto_render_button', 'postnl_cart_button_placement', 'before_checkout' ),
				'cart_after_checkout'              => array( 'postnl_cart_auto_render_button', 'postnl_cart_button_placement', 'after_checkout' ),
				'checkout_before_customer_details' => array( 'postnl_checkout_auto_render_button', 'postnl_checkout_button_placement', 'before_customer_details' ),
				'checkout_after_customer_details'  => array( 'postnl_checkout_auto_render_button', 'postnl_checkout_button_placement', 'after_customer_details' ),
				'minicart_before_buttons'          => array( 'postnl_minicart_auto_render_button', 'postnl_minicart_button_placement', 'before_buttons' ),
				'minicart_after_buttons'           => array( 'postnl_minicart_auto_render_button', 'postnl_minicart_button_placement', 'after_buttons' ),
			)
		);
	}

	/**
	 * Check if customer default country is allowed.
	 *
	 * @param \WC_Customer $customer Customer object.
	 * @param array        $allowed_countries list of allowed countries.
	 *
	 * @return bool
	 */
	public static function is_customer_country_allowed( $customer, $allowed_countries ): bool {
		$billing_country  = $customer->get_billing_country();
		$shipping_country = $customer->get_shipping_country();

		if ( ! in_array( $billing_country, $allowed_countries, true ) &&
			! in_array( $shipping_country, $allowed_countries, true ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Get WooCommerce shop order screen ID.
	 *
	 * @return string
	 */
	public static function get_order_screen_id(): string {
		try {
			return wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
					? wc_get_page_screen_id( 'shop-order' )
					: 'shop_order';
		} catch ( \Exception $e ) {
			return 'shop_order';
		}
	}

	/**
	 * Get non-EU countries
	 *
	 * @return array
	 */
	public static function get_non_eu_countries() {
		$all_countries   = WC()->countries->get_countries();
		$eu_countries    = WC()->countries->get_european_union_countries();
		$european_non_eu = array( 'MC', 'SM', 'VA', 'AD', 'ME', 'RS', 'MK', 'AL', 'BA', 'XK', 'MD', 'UA', 'BY', 'RU', 'GE', 'AM', 'AZ', 'TR' );

		// Remove EU countries from the list.
		$non_eu_countries = array_diff_key( $all_countries, array_flip( $eu_countries ) );

		// Remove European non-EU countries from the list.
		$non_eu_countries = array_diff_key( $non_eu_countries, array_flip( $european_non_eu ) );

		// Also remove Netherlands specifically.
		unset( $non_eu_countries['NL'] );

		return $non_eu_countries;
	}

	/**
	 * Check if a country is non-EU (and not European)
	 *
	 * @param string $country_code Country code to check
	 * 
	 * @return bool
	 */
	public static function is_non_eu_country( $country_code ) {
		$non_eu_countries = self::get_non_eu_countries();
		return array_key_exists( $country_code, $non_eu_countries );
	}

	/**
	 * Get merchant code for a specific country
	 *
	 * @param string $country_code Country code
	 * 
	 * @return string|null Merchant code or null if not found
	 */
	public static function get_merchant_code_for_country( $country_code ) {
		$merchant_codes = get_option( Settings::MERCHANT_CODES_OPTION, array() );
		return isset( $merchant_codes[ $country_code ] ) ? $merchant_codes[ $country_code ] : null;
	}

	/**
	 * Get fee total price for display, respecting WooCommerce tax settings.
	 *
	 * This method calculates whether to display fees including or excluding tax
	 * based on WooCommerce tax settings and customer tax status.
	 *
	 * Note: Shipping and fee prices are always entered as base prices (excluding tax)
	 * in WooCommerce, regardless of the woocommerce_prices_include_tax setting.
	 *
	 * @param float $fee_amount The base fee amount (always excluding tax).
	 *
	 * @return float Fee amount adjusted for display per tax settings.
	 */
	public static function get_fee_total_price( float $fee_amount ): float {
		if (  empty( $fee_amount ) || $fee_amount <= 0 ) {
			return 0.0;
		}

		// if taxes disabled, return as-is.
		if ( is_null( WC()->cart ) || ! wc_tax_enabled() ) {
			return $fee_amount;
		}

		// Check if customer is tax-exempt.
		if ( WC()->customer && WC()->customer->is_vat_exempt() ) {
			return $fee_amount;
		}

		// Check how to display prices in cart (including or excluding tax).
		$display_mode = get_option( 'woocommerce_tax_display_cart', 'excl' );

		// If displaying prices excluding tax, return base amount.
		if ( 'incl' !== $display_mode ) {
			return $fee_amount;
		}

		// Display prices including tax - calculate tax and add to base amount.
		$tax_rates = \WC_Tax::get_shipping_tax_rates();
		if ( empty( $tax_rates ) ) {
			return $fee_amount;
		}

		$taxes = \WC_Tax::calc_shipping_tax( $fee_amount, $tax_rates );

		return $fee_amount + array_sum( $taxes );
	}

	/**
	 * Get formatted fee total price for display.
	 *
	 * This is a wrapper function that returns the fee amount formatted with currency.
	 * Returns plain text without HTML markup for use in JavaScript/React components.
	 *
	 * @param float $fee_amount The base fee amount.
	 *
	 * @return string Formatted price string with currency (plain text, no HTML).
	 */
	public static function get_formatted_fee_total_price( float $fee_amount ): string {
		$formatted_html = wc_price( self::get_fee_total_price( $fee_amount ) );
		return html_entity_decode( wp_strip_all_tags( $formatted_html ), ENT_QUOTES, 'UTF-8' );
	}
}
