<?php
/**
 * Class Shipping_Method/Settings file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

use PostNLWooCommerce\Utils;

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
	 * The unique instance of the plugin.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	private static $instance;

	/**
	 * Gets an instance of the settings.
	 *
	 * @return Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Get all setting fields.
	 *
	 * @return array
	 */
	public function get_setting_fields() {
		// Filter to only get Netherlands and Belgium.
		$available_countries = array_filter(
			WC()->countries->get_countries(),
			function( $key ) {
				return ( 'NL' === $key || 'BE' === $key );
			},
			ARRAY_FILTER_USE_KEY
		);

		return array(
			// Account Settings.
			'account_settings_title'    => array(
				'title'       => esc_html__( 'Account Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Please configure your shipping parameters and your access towards the PostNL APIs by means of authentication. You can find the details of your PostNL account in Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
			),
			'api_keys'                  => array(
				'title'       => esc_html__( 'API Key', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Insert your PostNL production API-key. You can find your API-key on Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'environment_mode'          => array(
				'title'       => esc_html__( 'Environment Mode', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Choose the environment mode.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'production' => esc_html__( 'Production', 'postnl-for-woocommerce' ),
					'sandbox'    => esc_html__( 'Sandbox', 'postnl-for-woocommerce' ),
				),
				'class'       => 'wc-enhanced-select',
				'default'     => 'production',
				'placeholder' => '',
			),
			'enable_logging'            => array(
				'title'             => esc_html__( 'Enable Logging', 'postnl-for-woocommerce' ),
				'type'              => 'checkbox',
				'description'       => esc_html__( 'Log files can be used to diagnose problems.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
			),
			'customer_num'              => array(
				'title'             => esc_html__( 'Customer Number', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "11223344"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '11223344',
			),
			'customer_code'             => array(
				'title'             => esc_html__( 'Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "DEVC"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => 'DEVC',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),

			/*
			Temporarily hardcoded.
			'location_code'             => array(
				'title'             => esc_html__( 'Location Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "123456"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '123456',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			*/

			// Return Settings.
			'return_settings_title'               => array(
				'title'       => esc_html__( 'Return Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your return parameters.', 'postnl-for-woocommerce' ),
			),
			'return_address_default'    => array(
				'title'       => esc_html__( 'Default Return Address', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Default whether to create a return address or not.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'return_company'            => array(
				'title'       => esc_html__( 'Company Name', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter return company name.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_replynumber'        => array(
				'title'       => esc_html__( 'Replynumber', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter replynumber.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'return_address'            => array(
				'title'       => esc_html__( 'Street Address', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Street Address.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'BE' ),
				'class'       => 'country-be',
			),
			'return_address_no'         => array(
				'title'       => esc_html__( 'House Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter return house number.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'BE' ),
				'class'       => 'country-be',
			),
			'return_address_zip'        => array(
				'title'       => esc_html__( 'Zipcode', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Zipcode.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_address_city'       => array(
				'title'       => esc_html__( 'City', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return City.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_customer_code'      => array(
				'title'       => esc_html__( 'Return Customer Code', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Be aware that the Return Customer Code differs from the regular Customer Code. You can find your Return customer code in Mijn PostNL.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),

			// Delivery Options Settings.
			'delivery_options_title'    => array(
				'title'       => esc_html__( 'Frontend Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your frontend options parameters.', 'postnl-for-woocommerce' ),
			),
			'enable_pickup_points'      => array(
				'title'       => __( 'Enable PostNL Pick-up Points', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show PostNL pick-up points in the checkout so that your customers can choose to get their orders delivered at a PostNL pick-up point.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
				'for_country' => array( 'NL', 'BE' ),
				'class'       => 'country-nl country-be',
			),

			/*
			Temporarily commented out.
			'number_pickup_points'      => array(
				'title'             => __( 'Number of Pickup Points', 'postnl-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Number of pickup points displayed in the frontend. Maximum will be 20.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'class'             => '',
				'default'           => '10',
				'custom_attributes' => array(
					'min' => '1',
					'max' => '20',
				),
				'for_country'       => array( 'NL', 'BE' ),
				'class'             => 'country-nl country-be',
			),
			*/
			'enable_delivery_days'      => array(
				'title'       => __( 'Enable Delivery Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show delivery days in the checkout so that your customers can choose which day to receive their order.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'number_delivery_days'      => array(
				'title'             => __( 'Number of Delivery Days', 'postnl-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Number of delivery days displayed in the frontend. Maximum will be 12.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'class'             => '',
				'default'           => '10',
				'for_country'       => array( 'NL' ),
				'custom_attributes' => array(
					'min' => '1',
					'max' => '12',
				),
				'class'             => 'country-nl',
			),
			'enable_evening_delivery'   => array(
				'title'       => __( 'Enable Evening Delivery', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable evening delivery on the frontend.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'evening_delivery_fee'      => array(
				'title'       => __( 'Evening Delivery Fee', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Fee for evening delivery option.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc_input_price',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'transit_time'             => array(
				'title'       => esc_html__( 'Transit Time', 'postnl-for-woocommerce' ),
				'type'        => 'number',
				'description' => esc_html__( 'The number of days it takes for the order to be delivered after the order has been placed.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '1',
				'placeholder' => '',
			),
			'cut_off_time'              => array(
				'title'       => esc_html__( 'Cut Off Time', 'postnl-for-woocommerce' ),
				'type'        => 'time',
				'description' => esc_html__( 'If an order is ordered after this time, one day will be added to the transit time.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '18',
				'placeholder' => '',
			),
			'dropoff_day_mon'           => array(
				'title'       => __( 'Drop off Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Monday', 'postnl-for-woocommerce' ),
				'description' => __( 'Select which days you ship orders.', 'postnl-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'dropoff_day_tue'           => array(
				'type'    => 'checkbox',
				'label'   => __( 'Tuesday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_wed'           => array(
				'type'    => 'checkbox',
				'label'   => __( 'Wednesday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_thu'           => array(
				'type'    => 'checkbox',
				'label'   => __( 'Thursday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_fri'           => array(
				'type'    => 'checkbox',
				'label'   => __( 'Friday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_sat'           => array(
				'type'    => 'checkbox',
				'label'   => __( 'Saturday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_sun'           => array(
				'type'  => 'checkbox',
				'label' => __( 'Sunday', 'postnl-for-woocommerce' ),
			),
			'check_dutch_address'       => array(
				'title'       => __( 'Check Dutch addresses', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Based on zipcode and housenumber the address is checked.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),

			// Shipping Outside Europe Settings.
			'shipping_outside_eu_title' => array(
				'title'       => esc_html__( 'Shipping Outside Europe Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your shipping outside Europe option parameters.', 'postnl-for-woocommerce' ),
			),
			'globalpack_barcode_type'   => array(
				'title'             => esc_html__( 'GlobalPack Barcode Type', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "CD"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'globalpack_customer_code'  => array(
				'title'             => esc_html__( 'GlobalPack Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "1234"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'hs_tariff_code'            => array(
				'title'       => esc_html__( 'Default HS Tariff Code', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'The HS tariff code is used by customs to classify goods. The HS tariff code can be found on the website of the Dutch Chamber of Commerce.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'country_origin'            => array(
				'title'       => esc_html__( 'Default Country of Origin', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Default country of origin if none is set in the product.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => Utils::get_base_country(),
				'options'     => WC()->countries->get_countries(),
				'placeholder' => '',
			),

			// Shipping Outside Europe Settings.
			'printer_email_title'       => array(
				'title'       => esc_html__( 'Printer &amp; Email Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your printer and email option parameters.', 'postnl-for-woocommerce' ),
			),
			'label_format'              => array(
				'title'       => esc_html__( 'Label Format', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Use A6 format in case you use a labelprinter. Use A4 format for other regular printers.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'Label (A6)',
				'options'     => array(
					'Label (A6)'             => 'A6',
					'Commercialinvoice (A4)' => 'A4',
				),
				'class'       => 'wc-enhanced-select',
			),
			'woocommerce_email'         => array(
				'title'       => esc_html__( 'WooCommerce Email', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Enable WooCommerce tracking note email.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'woocommerce_email_text'    => array(
				'title'       => esc_html__( 'WooCommerce Email Text', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Text added for tracking note email.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => esc_html__( 'Tracking Number: {tracking-link}', 'postnl-for-woocommerce' ),
			),
		);
	}

	/**
	 * Filter the setting fields based on store country.
	 *
	 * @param String $country Two characters country code.
	 * @param bool   $only_field_country Flag to check if it only return for the field with 'for_country' array exists.
	 *
	 * @return array
	 */
	public function filter_setting_fields( $country, $only_field_country = false ) {
		$setting_fields = $this->get_setting_fields();
		$country_fields = array_filter(
			$setting_fields,
			function ( $field ) use ( $country, $only_field_country ) {
				if ( empty( $field['for_country'] ) && false === $only_field_country ) {
					return true;
				}

				if ( ! empty( $field['for_country'] ) && is_array( $field['for_country'] ) && in_array( $country, $field['for_country'], true ) ) {
					return true;
				}

				if ( ! empty( $field['for_country'] ) && $field['for_country'] === $country ) {
					return true;
				}

				return false;
			}
		);

		return $country_fields;
	}

	/**
	 * Return NL setting fields only.
	 *
	 * @param bool $only_field_country Flag to check if it only return for the field with 'for_country' array exists.
	 *
	 * @return array
	 */
	public function nl_setting_fields( $only_field_country = false ) {
		return $this->filter_setting_fields( 'NL', $only_field_country );
	}

	/**
	 * Return BE setting fields only.
	 *
	 * @param bool $only_field_country Flag to check if it only return for the field with 'for_country' array exists.
	 *
	 * @return array
	 */
	public function be_setting_fields( $only_field_country = false ) {
		return $this->filter_setting_fields( 'BE', $only_field_country );
	}

	/**
	 * Get setting option value based on country.
	 *
	 * @param String $field Field name.
	 * @param String $default_value Default value if the field value is empty.
	 *
	 * @return String
	 */
	public function get_country_option( $field, $default_value = '' ) {
		$base_country   = Utils::get_base_country();
		$fields_country = array_keys( $this->filter_setting_fields( $base_country, false ) );

		return in_array( $field, $fields_country, true ) ? $this->get_option( $field, $default_value ) : '';
	}

	/**
	 * Get API Key from the settings.
	 *
	 * @return String
	 */
	public function get_api_key() {
		return $this->get_country_option( 'api_keys', '' );
	}

	/**
	 * Get customer number from the settings.
	 *
	 * @return String
	 */
	public function get_customer_num() {
		return $this->get_country_option( 'customer_num', '' );
	}

	/**
	 * Get customer code from the settings.
	 *
	 * @return String
	 */
	public function get_customer_code() {
		return $this->get_country_option( 'customer_code', '' );
	}

	/**
	 * Get location code from the settings.
	 *
	 * @return String
	 */
	public function get_location_code() {
		/*
		Temporarily hardcoded.
		return $this->get_country_option( 'location_code', '' );
		*/

		return '1234506';
	}

	/**
	 * Return true if sandbox mode is ticked.
	 *
	 * @return String
	 */
	public function get_environment_mode() {
		return $this->get_country_option( 'environment_mode', '' );
	}

	/**
	 * Return true if sandbox mode is ticked.
	 *
	 * @return Bool
	 */
	public function is_sandbox() {
		return ( 'sandbox' === $this->get_environment_mode() );
	}

	/**
	 * Get return address default from the settings.
	 *
	 * @return String
	 */
	public function get_return_address_default() {
		return $this->get_country_option( 'return_address_default', '' );
	}

	/**
	 * Get return company name from the settings.
	 *
	 * @return String
	 */
	public function get_return_company_name() {
		return $this->get_country_option( 'return_company', '' );
	}

	/**
	 * Get return reply number from the settings.
	 *
	 * @return String
	 */
	public function get_return_reply_number() {
		return $this->get_country_option( 'return_replynumber', '' );
	}

	/**
	 * Get return address from the settings.
	 *
	 * @return String
	 */
	public function get_return_address() {
		return $this->get_country_option( 'return_address', '' );
	}

	/**
	 * Get return address from the settings.
	 *
	 * @return String
	 */
	public function get_return_streetnumber() {
		return $this->get_country_option( 'return_address_no', '' );
	}

	/**
	 * Get return city address from the settings.
	 *
	 * @return String
	 */
	public function get_return_city() {
		return $this->get_country_option( 'return_address_city', '' );
	}

	/**
	 * Get return state address from the settings.
	 *
	 * @return String
	 */
	public function get_return_state() {
		return $this->get_country_option( 'return_address_state', '' );
	}

	/**
	 * Get return state address from the settings.
	 *
	 * @return String
	 */
	public function get_return_zipcode() {
		return $this->get_country_option( 'return_address_zip', '' );
	}

	/**
	 * Get return phone number from the settings.
	 *
	 * @return String
	 */
	public function get_return_phone() {
		return $this->get_country_option( 'return_phone', '' );
	}

	/**
	 * Get return email from the settings.
	 *
	 * @return String
	 */
	public function get_return_email() {
		return $this->get_country_option( 'return_email', '' );
	}

	/**
	 * Get return customer code from the settings.
	 *
	 * @return String
	 */
	public function get_return_customer_code() {
		return $this->get_country_option( 'return_customer_code', '' );
	}

	/**
	 * Get return customer code from the settings.
	 *
	 * @return String
	 */
	public function get_return_direct_print_label() {
		return $this->get_country_option( 'return_direct_print_label', '' );
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
		return $this->get_country_option( 'enable_delivery', '' );
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
		return $this->get_country_option( 'enable_pickup_points' );
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
	 * Get number pickup points from the settings.
	 *
	 * @return Int
	 */
	public function get_number_pickup_points() {
		/*
		Temporarily hardcoded.
		return $this->get_country_option( 'number_pickup_points' );
		*/

		return 3;
	}

	/**
	 * Get enable delivery days from the settings.
	 *
	 * @return String
	 */
	public function get_enable_delivery_days() {
		return $this->get_country_option( 'enable_delivery_days' );
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
	 * Get number delivery days from the settings.
	 *
	 * @return Int
	 */
	public function get_number_delivery_days() {
		return $this->get_country_option( 'number_delivery_days' );
	}

	/**
	 * Get enable evening delivery from the settings.
	 *
	 * @return String
	 */
	public function get_enable_evening_delivery() {
		return $this->get_country_option( 'enable_evening_delivery' );
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
		return $this->get_country_option( 'evening_delivery_fee' );
	}

	/**
	 * Get transit time value from the settings.
	 *
	 * @return String
	 */
	public function get_transit_time() {
		return $this->get_country_option( 'transit_time', '' );
	}

	/**
	 * Get cut off time value from the settings.
	 *
	 * @return String
	 */
	public function get_cut_off_time() {
		return $this->get_country_option( 'cut_off_time', '' );
	}

	/**
	 * Get dropoff monday value from the settings.
	 *
	 * @return String
	 */
	public function get_dropoff_monday() {
		return $this->get_country_option( 'dropoff_day_mon', '' );
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
		return $this->get_country_option( 'dropoff_day_tue', '' );
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
		return $this->get_country_option( 'dropoff_day_wed', '' );
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
		return $this->get_country_option( 'dropoff_day_thu', '' );
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
		return $this->get_country_option( 'dropoff_day_fri', '' );
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
		return $this->get_country_option( 'dropoff_day_sat', '' );
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
		return $this->get_country_option( 'dropoff_day_sun', '' );
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
	 * Get excluded dropoff days from the settings.
	 *
	 * @return Array
	 */
	public function get_excluded_dropoff_days() {
		$completed_days = array_keys( Utils::days_of_week() );
		$dropoff_days   = $this->get_dropoff_days();

		return array_diff( $completed_days, $dropoff_days );
	}

	/**
	 * Get globalpack type barcode from the settings.
	 *
	 * @return String
	 */
	public function get_globalpack_barcode_type() {
		return $this->get_country_option( 'globalpack_barcode_type', '' );
	}

	/**
	 * Get globalpack customer code from the settings.
	 *
	 * @return String
	 */
	public function get_globalpack_customer_code() {
		return $this->get_country_option( 'globalpack_customer_code', '' );
	}

	/**
	 * Get HS Tariff code from the settings.
	 *
	 * @return String
	 */
	public function get_hs_tariff_code() {
		return $this->get_country_option( 'hs_tariff_code', '' );
	}

	/**
	 * Get HS Tariff code from the settings.
	 *
	 * @return String
	 */
	public function get_country_origin() {
		return $this->get_country_option( 'country_origin', '' );
	}

	/**
	 * Get label format from the settings.
	 *
	 * @return String
	 */
	public function get_label_format() {
		return $this->get_country_option( 'label_format', '' );
	}

	/**
	 * Get ask position A4 from the settings.
	 *
	 * @return String
	 */
	public function get_ask_position_a4() {
		return $this->get_country_option( 'ask_position_a4', '' );
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
	 * Get woocommerce email checkbox value from the settings.
	 *
	 * @return String
	 */
	public function get_woocommerce_email() {
		return $this->get_country_option( 'woocommerce_email', '' );
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
		return $this->get_country_option( 'woocommerce_email_text', '' );
	}

	/**
	 * Get check dutch address value from the settings.
	 *
	 * @return String
	 */
	public function get_check_dutch_address() {
		return $this->get_country_option( 'check_dutch_address', '' );
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
		return $this->get_country_option( 'enable_logging', '' );
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
