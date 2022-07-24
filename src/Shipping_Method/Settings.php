<?php
/**
 * Class Shipping_Method/Settings file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * @package PostNLWooCommerce\Shipping_Method
 */
class Settings extends \WC_Settings_API {
	/**
	 * ID of the class extending the settings API. Used in option names.
	 *
	 * @var string
	 */
	public $id = POSTNL_SETTINGS_ID;

	/**
	 * Get API Key from the settings.
	 *
	 * @return String
	 */
	public function get_api_key() {
		return $this->get_option( 'api_keys', '' );
	}

	/**
	 * Get customer number from the settings.
	 *
	 * @return String
	 */
	public function get_customer_num() {
		return $this->get_option( 'customer_num', '' );
	}

	/**
	 * Get customer code from the settings.
	 *
	 * @return String
	 */
	public function get_customer_code() {
		return $this->get_option( 'customer_code', '' );
	}

	/**
	 * Get location code from the settings.
	 *
	 * @return String
	 */
	public function get_location_code() {
		return $this->get_option( 'location_code', '' );
	}

	/**
	 * Get company name from the settings.
	 *
	 * @return String
	 */
	public function get_company_name() {
		return $this->get_option( 'address_company', '' );
	}

	/**
	 * Get company address from the settings.
	 *
	 * @return String
	 */
	public function get_company_address() {
		return $this->get_option( 'address_street', '' );
	}

	/**
	 * Get company address street no from the settings.
	 *
	 * @return String
	 */
	public function get_company_housenumber() {
		return $this->get_option( 'address_street_no', '' );
	}

	/**
	 * Get company housenumber ext from the settings.
	 *
	 * @return String
	 */
	public function get_company_housenumber_ext() {
		return $this->get_option( 'address_street_ext', '' );
	}

	/**
	 * Get company city address from the settings.
	 *
	 * @return String
	 */
	public function get_company_city() {
		return $this->get_option( 'address_city', '' );
	}

	/**
	 * Get company state address from the settings.
	 *
	 * @return String
	 */
	public function get_company_state() {
		return $this->get_option( 'address_state', '' );
	}

	/**
	 * Get company country address from the settings.
	 *
	 * @return String
	 */
	public function get_company_country() {
		return $this->get_option( 'address_country', '' );
	}

	/**
	 * Get company zipcode address from the settings.
	 *
	 * @return String
	 */
	public function get_company_zipcode() {
		return $this->get_option( 'address_zip', '' );
	}

	/**
	 * Get return address default from the settings.
	 *
	 * @return String
	 */
	public function get_return_address_default() {
		return $this->get_option( 'return_address_default', '' );
	}

	/**
	 * Get return company name from the settings.
	 *
	 * @return String
	 */
	public function get_return_company_name() {
		return $this->get_option( 'return_company', '' );
	}

	/**
	 * Get return reply number from the settings.
	 *
	 * @return String
	 */
	public function get_return_reply_number() {
		return $this->get_option( 'return_replynumber', '' );
	}

	/**
	 * Get return address from the settings.
	 *
	 * @return String
	 */
	public function get_return_address() {
		return $this->get_option( 'return_address', '' );
	}

	/**
	 * Get return address from the settings.
	 *
	 * @return String
	 */
	public function get_return_streetnumber() {
		return $this->get_option( 'return_address_no', '' );
	}

	/**
	 * Get return city address from the settings.
	 *
	 * @return String
	 */
	public function get_return_city() {
		return $this->get_option( 'return_address_city', '' );
	}

	/**
	 * Get return state address from the settings.
	 *
	 * @return String
	 */
	public function get_return_state() {
		return $this->get_option( 'return_address_state', '' );
	}

	/**
	 * Get return state address from the settings.
	 *
	 * @return String
	 */
	public function get_return_zipcode() {
		return $this->get_option( 'return_address_zip', '' );
	}

	/**
	 * Get return phone number from the settings.
	 *
	 * @return String
	 */
	public function get_return_phone() {
		return $this->get_option( 'return_phone', '' );
	}

	/**
	 * Get return email from the settings.
	 *
	 * @return String
	 */
	public function get_return_email() {
		return $this->get_option( 'return_email', '' );
	}

	/**
	 * Get return customer code from the settings.
	 *
	 * @return String
	 */
	public function get_return_customer_code() {
		return $this->get_option( 'return_customer_code', '' );
	}

	/**
	 * Get return customer code from the settings.
	 *
	 * @return String
	 */
	public function get_return_direct_print_label() {
		return $this->get_option( 'return_direct_print_label', '' );
	}

	/**
	 * Return true if 'print returnlabel directly with shipping label' field is ticked.
	 *
	 * @return Bool
	 */
	public function is_return_direct_print_enabled() {
		return ( 'yes' === $this->get_return_direct_print_label() );
	}

	/**
	 * Get enable delivery from the settings.
	 *
	 * @return String
	 */
	public function get_enable_delivery() {
		return $this->get_option( 'enable_delivery', '' );
	}

	/**
	 * Return true if delivery field is ticked.
	 *
	 * @return Bool
	 */
	public function is_delivery_enabled() {
		return ( 'yes' === $this->get_enable_delivery() );
	}

	/**
	 * Get enable pickup points from the settings.
	 *
	 * @return String
	 */
	public function get_enable_pickup_points() {
		return $this->get_option( 'enable_pickup_points', '' );
	}

	/**
	 * Return true if delivery days field is ticked.
	 *
	 * @return Bool
	 */
	public function is_pickup_points_enabled() {
		return ( 'yes' === $this->get_enable_pickup_points() );
	}

	/**
	 * Get enable delivery days from the settings.
	 *
	 * @return String
	 */
	public function get_enable_delivery_days() {
		return $this->get_option( 'enable_delivery_days', '' );
	}

	/**
	 * Return true if delivery days field is ticked.
	 *
	 * @return Bool
	 */
	public function is_delivery_days_enabled() {
		return ( 'yes' === $this->get_enable_delivery_days() );
	}

	/**
	 * Get enable evening delivery from the settings.
	 *
	 * @return String
	 */
	public function get_enable_evening_delivery() {
		return $this->get_option( 'enable_evening_delivery', '' );
	}

	/**
	 * Return true if evening delivery field is ticked.
	 *
	 * @return Bool
	 */
	public function is_evening_delivery_enabled() {
		return ( 'yes' === $this->get_enable_evening_delivery() );
	}

	/**
	 * Get evening delivery fee from the settings.
	 *
	 * @return String
	 */
	public function get_evening_delivery_fee() {
		return $this->get_option( 'evening_delivery_fee', '' );
	}

	/**
	 * Get transit time value from the settings.
	 *
	 * @return String
	 */
	public function get_transit_time() {
		return $this->get_option( 'transit_time', '' );
	}

	/**
	 * Get cut off time value from the settings.
	 *
	 * @return String
	 */
	public function get_cut_off_time() {
		return $this->get_option( 'cut_off_time', '' );
	}

	/**
	 * Get dropoff monday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_monday() {
		return $this->get_option( 'dropoff_day_mon', '' );
	}

	/**
	 * Return true if dropoff monday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_monday_enabled() {
		return ( 'yes' === $this->get_dropoff_monday() );
	}

	/**
	 * Get dropoff tuesday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_tuesday() {
		return $this->get_option( 'dropoff_day_tue', '' );
	}

	/**
	 * Return true if dropoff tuesday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_tuesday_enabled() {
		return ( 'yes' === $this->get_dropoff_tuesday() );
	}

	/**
	 * Get dropoff wednesday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_wednesday() {
		return $this->get_option( 'dropoff_day_wednesday', '' );
	}

	/**
	 * Return true if dropoff wednesday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_wednesday_enabled() {
		return ( 'yes' === $this->get_dropoff_wednesday() );
	}

	/**
	 * Get dropoff thursday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_thursday() {
		return $this->get_option( 'dropoff_day_thu', '' );
	}

	/**
	 * Return true if dropoff thursday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_thursday_enabled() {
		return ( 'yes' === $this->get_dropoff_thursday() );
	}

	/**
	 * Get dropoff friday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_friday() {
		return $this->get_option( 'dropoff_day_fri', '' );
	}

	/**
	 * Return true if dropoff friday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_friday_enabled() {
		return ( 'yes' === $this->get_dropoff_friday() );
	}

	/**
	 * Get dropoff saturday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_saturday() {
		return $this->get_option( 'dropoff_day_sat', '' );
	}

	/**
	 * Return true if dropoff saturday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_saturday_enabled() {
		return ( 'yes' === $this->get_dropoff_saturday() );
	}

	/**
	 * Get dropoff sunday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_sunday() {
		return $this->get_option( 'dropoff_day_sun', '' );
	}

	/**
	 * Return true if dropoff sunday field is ticked.
	 *
	 * @return Bool
	 */
	public function is_dropoff_sunday_enabled() {
		return ( 'yes' === $this->get_dropoff_sunday() );
	}

	/**
	 * Get dropoff days from the settings.
	 *
	 * @return Array
	 */
	public function get_dropoff_days() {
		$dropoff_days = array();

		if ( $this->is_dropoff_monday_enabled() ) {
			$dropoff_days[] = 'mon';
		}

		if ( $this->is_dropoff_tuesday_enabled() ) {
			$dropoff_days[] = 'tue';
		}

		if ( $this->is_dropoff_wednesday_enabled() ) {
			$dropoff_days[] = 'wed';
		}

		if ( $this->is_dropoff_thursday_enabled() ) {
			$dropoff_days[] = 'thu';
		}

		if ( $this->is_dropoff_friday_enabled() ) {
			$dropoff_days[] = 'fri';
		}

		if ( $this->is_dropoff_saturday_enabled() ) {
			$dropoff_days[] = 'sat';
		}

		if ( $this->is_dropoff_sunday_enabled() ) {
			$dropoff_days[] = 'sun';
		}

		return $dropoff_days;
	}

	/**
	 * Get globalpack type barcode from the settings.
	 *
	 * @return String
	 */
	public function get_globalpack_barcode_type() {
		return $this->get_option( 'globalpack_barcode_type', '' );
	}

	/**
	 * Get globalpack customer code from the settings.
	 *
	 * @return String
	 */
	public function get_globalpack_customer_code() {
		return $this->get_option( 'globalpack_customer_code', '' );
	}

	/**
	 * Get HS Tariff code from the settings.
	 *
	 * @return String
	 */
	public function get_hs_tariff_code() {
		return $this->get_option( 'hs_tariff_code', '' );
	}

	/**
	 * Get HS Tariff code from the settings.
	 *
	 * @return String
	 */
	public function get_country_origin() {
		return $this->get_option( 'country_origin', '' );
	}

	/**
	 * Get label format from the settings.
	 *
	 * @return String
	 */
	public function get_label_format() {
		return $this->get_option( 'label_format', '' );
	}

	/**
	 * Get ask position A4 from the settings.
	 *
	 * @return String
	 */
	public function get_ask_position_a4() {
		return $this->get_option( 'ask_position_a4', '' );
	}

	/**
	 * Return true if track trace email field is ticked.
	 *
	 * @return Bool
	 */
	public function is_ask_position_a4_enabled() {
		return ( 'yes' === $this->get_ask_position_a4() );
	}

	/**
	 * Get track trace email from the settings.
	 *
	 * @return String
	 */
	public function get_track_trace_email() {
		return $this->get_option( 'track_trace_email', '' );
	}

	/**
	 * Return true if track trace email field is ticked.
	 *
	 * @return Bool
	 */
	public function is_track_trace_email_enabled() {
		return ( 'yes' === $this->get_track_trace_email() );
	}

	/**
	 * Get woocommerce email checkbox value from the settings.
	 *
	 * @return String
	 */
	public function get_woocommerce_email() {
		return $this->get_option( 'woocommerce_email', '' );
	}

	/**
	 * Return true if woocommerce email field is ticked.
	 *
	 * @return Bool
	 */
	public function is_woocommerce_email_enabled() {
		return ( 'yes' === $this->get_woocommerce_email() );
	}

	/**
	 * Get woocommerce email text value from the settings.
	 *
	 * @return String
	 */
	public function get_woocommerce_email_text() {
		return $this->get_option( 'woocommerce_email_text', '' );
	}

	/**
	 * Get check dutch address value from the settings.
	 *
	 * @return String
	 */
	public function get_check_dutch_address() {
		return $this->get_option( 'check_dutch_address', '' );
	}

	/**
	 * Return true if check dutch address field is ticked.
	 *
	 * @return Bool
	 */
	public function is_check_dutch_address_enabled() {
		return ( 'yes' === $this->get_check_dutch_address() );
	}

	/**
	 * Get enable logging value from the settings.
	 *
	 * @return String
	 */
	public function get_enable_logging() {
		return $this->get_option( 'enable_logging', '' );
	}

	/**
	 * Return true if enable logging field is ticked.
	 *
	 * @return Bool
	 */
	public function is_logging_enabled() {
		return ( 'yes' === $this->get_enable_logging() );
	}
}
