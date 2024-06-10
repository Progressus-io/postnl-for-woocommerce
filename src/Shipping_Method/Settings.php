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
		return array(
			// Manual.
			'user_manual'                    => array(
				'title'       => esc_html__( 'Manual', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Consult the %1$smanual%2$s for help installing the plug-in.', 'postnl-for-woocommerce' ), '<a href="https://postnl.github.io/woocommerce/new-manual/?lang=nl" target="_blank">', '</a>' ),
			),
			// Account Settings.
			'account_settings_title'         => array(
				'title'       => esc_html__( 'Account Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Please configure your shipping parameters and your access towards the PostNL APIs by means of authentication. You can find the details of your PostNL account in Mijn %1$sPostNL%2$s under "My Account > API beheren".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
			),
			'environment_mode'               => array(
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
			'api_keys'                       => array(
				'title'       => esc_html__( 'Production API Key', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Insert your PostNL production API-key. You can find your API-key on Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'api_keys_sandbox'               => array(
				'title'       => esc_html__( 'Sandbox API Key', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Insert your PostNL staging API-key. You can find your API-key on Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'enable_logging'                 => array(
				'title'       => esc_html__( 'Logging', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => sprintf(
				// translators: %1$s is anchor opener tag and %2$s is anchor closer tag.
					esc_html__( 'A log file containing the communication to the PostNL server will be maintained if this option is checked. This can be used in case of technical issues and can be found %1$shere%2$s.', 'postnl-for-woocommerce' ),
					'<a href="' . esc_url( Utils::get_log_url() ) . '" target="_blank">',
					'</a>'
				),
				'label'       => esc_html__( 'Enable', 'postnl-for-woocommerce' ),
				'desc_tip'    => false,
				'default'     => '',
				'placeholder' => '',
			),
			'customer_num'                   => array(
				'title'       => esc_html__( 'Customer Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'e.g. "11223344"', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '11223344',
			),
			'customer_code'                  => array(
				'title'             => esc_html__( 'Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "DEVC"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => 'DEVC',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'return_company'                 => array(
				'title'       => esc_html__( 'Company Name', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter company name - this name will be noted as the sender on the label', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
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
			'return_settings_title'          => array(
				'title'       => esc_html__( 'Return Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please insert your return credentials.', 'postnl-for-woocommerce' ),
			),
			'return_address_default'         => array(
				'title'       => esc_html__( 'Always print returnlabel together with shipping label', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'With this setting enabled, the return-label of a shipment will automatically be downloaded and printed when the shipping label created.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'label'       => esc_html__( 'Enable', 'postnl-for-woocommerce' ),
				'placeholder' => '',
			),
			'return_replynumber'             => array(
				'title'       => esc_html__( 'Replynumber', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter replynumber.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'return_address'                 => array(
				'title'       => esc_html__( 'Street Address', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Street Address.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'BE' ),
				'class'       => 'country-be',
			),
			'return_address_no'              => array(
				'title'       => esc_html__( 'House Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter return house number.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'BE' ),
				'class'       => 'country-be',
			),
			'return_address_zip'             => array(
				'title'       => esc_html__( 'Zipcode', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Zipcode.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_address_city'            => array(
				'title'       => esc_html__( 'City', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return City.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_customer_code'           => array(
				'title'       => esc_html__( 'Return Customer Code', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Be aware that the Return Customer Code differs from the regular Customer Code. You can find your Return customer code in Mijn PostNL.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),

			// Delivery Options Settings.
			'delivery_options_title'         => array(
				'title'       => esc_html__( 'Checkout Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your checkout preferences.', 'postnl-for-woocommerce' ),
			),
			'supported_shipping_methods'     => array(
				'title'       => esc_html__( 'Shipping Methods', 'postnl-for-woocommerce' ),
				'type'        => 'multiselect',
				'description' => esc_html__( 'Select Shipping Methods can be associated with PostNL.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => $this->get_shipping_methods(),
				'class'       => 'wc-enhanced-select',
			),
			'enable_pickup_points'           => array(
				'title'       => __( 'PostNL Pick-up Points', 'postnl-for-woocommerce' ),
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
			'enable_delivery_days'           => array(
				'title'       => __( 'Delivery Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show delivery days in the checkout so that your customers can choose which day to receive their order.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'number_delivery_days'           => array(
				'title'             => __( 'Number of Delivery Days', 'postnl-for-woocommerce' ),
				'type'              => 'number',
				'description'       => __( 'Number of delivery days displayed in the frontend. Maximum will be 12.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '10',
				'for_country'       => array( 'NL' ),
				'custom_attributes' => array(
					'min' => '1',
					'max' => '12',
				),
				'class'             => 'country-nl',
			),
			'enable_morning_delivery'        => array(
				'title'       => __( 'Morning Delivery', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable morning delivery in the checkout so your customers can choose to receive their orders in the morning.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'morning_delivery_fee'           => array(
				'title'       => __( 'Morning Delivery Fee', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Fee for receiving orders in the morning.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'for_country' => array( 'NL' ),
				'class'       => 'wc_input_price country-nl',
			),
			'enable_evening_delivery'        => array(
				'title'       => __( 'Evening Delivery', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable evening delivery in the checkout so your customers can choose to receive their orders in the evening.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'for_country' => array( 'NL' ),
				'class'       => 'country-nl',
			),
			'evening_delivery_fee'           => array(
				'title'       => __( 'Evening Delivery Fee', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Fee for receiving orders in the evening.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'for_country' => array( 'NL' ),
				'class'       => 'wc_input_price country-nl',
			),
			'transit_time'                   => array(
				'title'       => esc_html__( 'Transit Time', 'postnl-for-woocommerce' ),
				'type'        => 'number',
				'description' => esc_html__( 'The number of days it takes for the order to be delivered after the order has been placed.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '1',
				'placeholder' => '',
			),
			'cut_off_time'                   => array(
				'title'       => esc_html__( 'Cut Off Time', 'postnl-for-woocommerce' ),
				'type'        => 'time',
				'description' => esc_html__( 'If an order is ordered after this time, one day will be added to the transit time.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '18',
				'placeholder' => '',
			),
			'dropoff_day_mon'                => array(
				'title'       => __( 'Drop off Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Monday', 'postnl-for-woocommerce' ),
				'description' => __( 'Select which days orders will be shipped.', 'postnl-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'dropoff_day_tue'                => array(
				'type'    => 'checkbox',
				'label'   => __( 'Tuesday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_wed'                => array(
				'type'    => 'checkbox',
				'label'   => __( 'Wednesday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_thu'                => array(
				'type'    => 'checkbox',
				'label'   => __( 'Thursday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_fri'                => array(
				'type'    => 'checkbox',
				'label'   => __( 'Friday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_sat'                => array(
				'type'    => 'checkbox',
				'label'   => __( 'Saturday', 'postnl-for-woocommerce' ),
				'default' => 'yes',
			),
			'dropoff_day_sun'                => array(
				'type'  => 'checkbox',
				'label' => __( 'Sunday', 'postnl-for-woocommerce' ),
			),
			'validate_nl_address'            => array(
				'title'       => __( 'Validate Dutch addresses', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Based on zipcode and housenumber combination the address is checked.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),
			'reorder_nl_address'             => array(
				'title'       => __( 'Use PostNL address-field', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'For zipcode, housenumber, housenumber extension and street separate address fields are displayed when this settings is enabled. This only applies for Dutch addresses.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),

			// Shipping Outside Europe Settings.
			'shipping_outside_eu_title'      => array(
				'title'       => esc_html__( 'Shipping Outside Europe Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please insert your Globalpack credentials.', 'postnl-for-woocommerce' ),
			),
			'globalpack_barcode_type'        => array(
				'title'             => esc_html__( 'GlobalPack Barcode Type', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => esc_html__( 'CD', 'postnl-for-woocommerce' ),
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'globalpack_customer_code'       => array(
				'title'             => esc_html__( 'GlobalPack Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => '',
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => esc_html__( '1234', 'postnl-for-woocommerce' ),
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'hs_tariff_code'                 => array(
				'title'       => esc_html__( 'Default HS Tariff Code', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'The HS tariff code is used by customs to classify goods. The HS tariff code can be found on the website of the Dutch Chamber of Commerce.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'country_origin'                 => array(
				'title'       => esc_html__( 'Default Country of Origin', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Default country of origin is used by customs.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => Utils::get_base_country(),
				'options'     => WC()->countries->get_countries(),
				'placeholder' => '',
			),

			// Shipping Outside Europe Settings.
			'printer_email_title'            => array(
				'title'       => esc_html__( 'Printer &amp; Email Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your printer and email preferences.', 'postnl-for-woocommerce' ),
			),
			'label_format'                   => array(
				'title'       => esc_html__( 'Label Format', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Use A6 format in case you use a labelprinter. Use A4 format for other regular printers.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'A6',
				'options'     => array(
					'A6' => 'A6',
					'A4' => 'A4',
				),
				'class'       => 'wc-enhanced-select',
			),
			'woocommerce_email'              => array(
				'title'       => esc_html__( 'WooCommerce Email', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'When PostNL label is created send email to customer.', 'postnl-for-woocommerce' ),
				'description' => esc_html__( 'When PostNL label is created send email to customer.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'woocommerce_email_text'         => array(
				'title'       => esc_html__( 'WooCommerce Email Text', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Text added for tracking note email.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => esc_html__( 'This is your track and track link {tracking-link}', 'postnl-for-woocommerce' ),
				'placeholder' => esc_html__( 'This is your track and track link {tracking-link}', 'postnl-for-woocommerce' ),
			),
			// Default shipping Options Settings.
			'default_shipping_options_title' => array(
				'title'       => esc_html__( 'Default shipping Options Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please select Default shipping Options.', 'postnl-for-woocommerce' ),
				'for_country' => array( 'NL' ),
			),
			'default_shipping_options_nl'    => array(
				'title'       => __( 'Shipping options domestic', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select a default shipping option for domestic orders that are shipped with PostNL.', 'postnl-for-woocommerce' ),
				'default'     => 'standard_shipment',
				'for_country' => array( 'NL' ),
				'options'     => array(
					'standard_shipment'                                        => __( 'Standard shipment', 'postnl-for-woocommerce' ),
					'id_check'                                                 => __( 'ID Check', 'postnl-for-woocommerce' ),
					// 'insured_shipping'                                         => __( 'Insured Shipping', 'postnl-for-woocommerce' ),
					'return_no_answer'                                         => __( 'Return if no answer', 'postnl-for-woocommerce' ),
					'signature_on_delivery'                                    => __( 'Signature on Delivery', 'postnl-for-woocommerce' ),
					'only_home_address'                                        => __( 'Only Home Address', 'postnl-for-woocommerce' ),
					'letterbox'                                                => __( 'Letterbox', 'postnl-for-woocommerce' ),
					'signature_on_delivery|insured_shipping'                   => __( 'Signature on Delivery + Insured Shipping', 'postnl-for-woocommerce' ),
					'signature_on_delivery|return_no_answer'                   => __( 'Signature on Delivery + Return if no answer', 'postnl-for-woocommerce' ),
					'insured_shipping|return_no_answer|signature_on_delivery'  => __( 'Insured Shipping + Return if no answer + Signature on Delivery', 'postnl-for-woocommerce' ),
					'only_home_address|return_no_answer'                       => __( 'Only Home Address + Return if no answer', 'postnl-for-woocommerce' ),
					'only_home_address|return_no_answer|signature_on_delivery' => __( 'Only Home Address + Return if no answer + Signature on Delivery', 'postnl-for-woocommerce' ),
					'only_home_address|signature_on_delivery'                  => __( 'Only Home Address + Signature on Delivery', 'postnl-for-woocommerce' ),
				),
			),
			'default_shipping_options_be'    => array(
				'title'       => __( 'Shipping options Belgium', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select a default shipping option for the orders shipped to Belgium with PostNL.', 'postnl-for-woocommerce' ),
				'default'     => 'standard_belgium',
				'for_country' => array( 'NL' ),
				'options'     => array(
					'standard_belgium'                         => __( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
					'standard_belgium|only_home_address'       => __( 'Standard Shipment Belgium + Only Home Address', 'postnl-for-woocommerce' ),
					'standard_belgium|signature_on_delivery'   => __( 'Standard Shipment Belgium + Signature on Delivery', 'postnl-for-woocommerce' ),
					'standard_belgium|insured_shipping'        => __( 'Standard Shipment Belgium + Insured Shipping', 'postnl-for-woocommerce' ),
					'mailboxpacket'                            => __( 'Boxable Packet', 'postnl-for-woocommerce' ),
					'mailboxpacket|track_and_trace'            => __( 'Boxable Packet + Track & Trace', 'postnl-for-woocommerce' ),
					'packets'                                  => __( 'Packets', 'postnl-for-woocommerce' ),
					'packets|track_and_trace'                  => __( 'Packets + Track & Trace', 'postnl-for-woocommerce' ),
					'packets|track_and_trace|insured_shipping' => __( 'Packets + Track & Trace + Insured', 'postnl-for-woocommerce' ),
				),
			),
			'default_shipping_options_eu'    => array(
				'title'       => __( 'Shipping options EU', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Select a default shipping option for the orders shipped within European Union zone.', 'postnl-for-woocommerce' ),
				'default'     => 'eu_parcel|track_and_trace',
				'for_country' => array( 'NL' ),
				'options'     => array(
					'eu_parcel|track_and_trace'                  => __( 'EU Parcel + Track & Trace', 'postnl-for-woocommerce' ),
					'eu_parcel|track_and_trace|insured_shipping' => __( 'EU Parcel + Track & Trace + Insured', 'postnl-for-woocommerce' ),
					'eu_parcel|track_and_trace|insured_plus'     => __( 'EU Parcel + Track & Trace + Insured Plus', 'postnl-for-woocommerce' ),
					'mailboxpacket'                              => __( 'Boxable Packet', 'postnl-for-woocommerce' ),
					'mailboxpacket|track_and_trace'              => __( 'Boxable Packet + Track & Trace', 'postnl-for-woocommerce' ),
					'packets'                                    => __( 'Packets', 'postnl-for-woocommerce' ),
					'packets|track_and_trace'                    => __( 'Packets + Track & Trace', 'postnl-for-woocommerce' ),
					'packets|track_and_trace|insured_shipping'   => __( 'Packets + Track & Trace + Insured', 'postnl-for-woocommerce' ),
				),
			),
			'default_shipping_options_row'   => array(
				'title'       => __( 'Default Shipping International', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => __( 'Shipping options non-EU (outside the EU borders).', 'postnl-for-woocommerce' ),
				'default'     => 'parcel_non_eu|track_and_trace',
				'for_country' => array( 'NL' ),
				'options'     => array(
					'parcel_non_eu|track_and_trace'                  => __( 'Parcel non-EU + Track & Trace', 'postnl-for-woocommerce' ),
					'parcel_non_eu|track_and_trace|insured_shipping' => __( 'Parcel non-EU + Track & Trace + Insured', 'postnl-for-woocommerce' ),
					'parcel_non_eu|track_and_trace|insured_plus'     => __( 'Parcel non-EU + Track & Trace + Insured Plus', 'postnl-for-woocommerce' ),
					'mailboxpacket'                                  => __( 'Boxable Packet', 'postnl-for-woocommerce' ),
					'mailboxpacket|track_and_trace'                  => __( 'Boxable Packet + Track & Trace', 'postnl-for-woocommerce' ),
					'packets'                                        => __( 'Packets', 'postnl-for-woocommerce' ),
					'packets|track_and_trace'                        => __( 'Packets + Track & Trace', 'postnl-for-woocommerce' ),
					'packets|track_and_trace|insured_shipping'       => __( 'Packets + Track & Trace + Insured', 'postnl-for-woocommerce' ),
				),
			),
			'auto_complete_order'            => array(
				'title'       => esc_html__( 'Automatically change order status to Completed', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => esc_html__( 'Automatically change order status to Completed once an order has been pre-alerted and printed', 'postnl-for-woocommerce' ),
				'description' => esc_html__( 'Automatically change order status to Completed once an order has been pre-alerted and printed', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),

		);
	}

	/**
	 * Filter the setting fields based on store country.
	 *
	 * @param String $country Two characters country code.
	 * @param bool $only_field_country Flag to check if it only return for the field with 'for_country' array exists.
	 *
	 * @return array
	 */
	public function filter_setting_fields( $country, $only_field_country = false, $settings = false ) {
		$setting_fields = $this->get_setting_fields();
		if ( $country == 'BE' && ! $settings ) {
			$setting_fields['default_shipping_options_title'] =
				array(
					'title'       => esc_html__( 'Default shipping Options Settings', 'postnl-for-woocommerce' ),
					'type'        => 'title',
					'description' => esc_html__( 'Please select Default shipping Options.', 'postnl-for-woocommerce' ),
					'for_country' => array( 'BE' ),
				);

			$setting_fields['default_shipping_options_row'] =
				array(
					'title'       => __( 'Default Shipping International', 'postnl-for-woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Select a default shipping option for the orders shipped internationally (outside the EU borders).', 'postnl-for-woocommerce' ),
					'default'     => 'parcel_non_eu|track_and_trace|insured_plus',
					'for_country' => array( 'BE' ),
					'options'     => array(
						'parcel_non_eu|track_and_trace|insured_plus' => __( 'Parcel non-EU + Track & Trace + Insured Plus', 'postnl-for-woocommerce' ),
					),
				);
		}
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
		return $this->filter_setting_fields( 'BE', $only_field_country, true );
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
	 * Get sandbox API Key from the settings.
	 *
	 * @return String
	 */
	public function get_api_key_sandbox() {
		return $this->get_country_option( 'api_keys_sandbox', '' );
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

		return '123456';
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
	 * Get enable morning delivery from the settings.
	 *
	 * @return String
	 */
	public function get_enable_morning_delivery() {
		return $this->get_country_option( 'enable_morning_delivery' );
	}

	/**
	 * Return true if evening delivery field is ticked.
	 *
	 * @return Bool
	 */
	public function is_morning_delivery_enabled() {
		return ( 'yes' === $this->get_enable_morning_delivery() );
	}

	/**
	 * Get evening delivery fee from the settings.
	 *
	 * @return String
	 */
	public function get_morning_delivery_fee() {
		return $this->get_country_option( 'morning_delivery_fee' );
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
	 * Get check Netherlands address value from the settings.
	 *
	 * @return String
	 */
	public function get_validate_nl_address() {
		return $this->get_country_option( 'validate_nl_address', '' );
	}

	/**
	 * Return true if check Netherlands address field is ticked.
	 *
	 * @return Bool
	 */
	public function is_validate_nl_address_enabled() {
		return ( 'yes' === $this->get_validate_nl_address() );
	}

	/**
	 * Get reorder Netherlands address value from the settings.
	 *
	 * @return String
	 */
	public function get_reorder_nl_address() {
		return $this->get_country_option( 'reorder_nl_address', '' );
	}

	/**
	 * Return true if reorder Netherlands address field is ticked.
	 *
	 * @return Bool
	 */
	public function is_reorder_nl_address_enabled() {
		return ( 'yes' === $this->get_reorder_nl_address() );
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

	/**
	 * Get all shipping options.
	 *
	 * @param string $zone Shipping zone, available options: 'ne' - to Netherlands, 'be' - to Belgium, 'eu' - to European Union, 'row' - international shipping.
	 *
	 * @return array
	 */
	public function get_default_shipping_options( $zone ) {
		$shipping_options = $this->get_country_option( 'default_shipping_options_' . strtolower( $zone ), '' );

		return Utils::prepare_shipping_options( $shipping_options );
	}

	/**
	 * Return array of shipping methods.
	 *
	 * @return array.
	 */
	public function get_shipping_methods() {
		return wp_list_pluck( WC()->shipping()->shipping_methods, 'method_title', 'id' );
	}

	/**
	 * Get supported shipping methods from the settings.
	 *
	 * @return array.
	 */
	public function get_supported_shipping_methods() {
		$suppoted_shipping_methods = (array) $this->get_option( 'supported_shipping_methods' );
		// Add PostNL method by default
		$suppoted_shipping_methods[] = POSTNL_SETTINGS_ID;

		return $suppoted_shipping_methods;
	}

	/**
	 * Get Automatically change order status to Completed value from the settings.
	 *
	 * @return String
	 */
	public function get_auto_complete_order() {
		return $this->get_country_option( 'auto_complete_order', '' );
	}

	/**
	 * Return true if Automatically change order status to Completed is ticked.
	 *
	 * @return Bool
	 */
	public function is_auto_complete_order_enabled() {
		return ( 'yes' === $this->get_auto_complete_order() );
	}
}
