<?php
/**
 * Class Shipping_Method/PostNL file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package PostNLWooCommerce\Shipping_Method
 */
class PostNL extends \WC_Shipping_Method {
	/**
	 * Init and hook in the integration.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = POSTNL_SETTINGS_ID;
		$this->instance_id  = absint( $instance_id );
		$this->method_title = __( 'PostNL', 'postnl-for-woocommerce' );

		// translators: %1$s & %2$s is replaced with <a> tag.
		$this->method_description = sprintf( __( 'Below you will find all functions for controlling, preparing and processing your shipment with PostNL. Prerequisite is a valid PostNL business customer contract. If you are not yet a PostNL business customer, you can request a quote %1$shere%2$s.', 'postnl-for-woocommerce' ), '<a href="https://mijnpostnlzakelijk.postnl.nl/s/become-a-customer?language=nl_NL#/" target="_blank">', '</a>' );

		$this->init();
	}

	/**
	 * Shipping method initialization.
	 */
	public function init() {
		$this->init_form_fields();
		$this->init_settings();

		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		// Filter to only get Netherlands and Belgium.
		$available_countries = array_filter(
			WC()->countries->get_countries(),
			function( $key ) {
				return ( 'NL' === $key || 'BE' === $key );
			},
			ARRAY_FILTER_USE_KEY
		);

		$this->form_fields = array(
			// Account Settings.
			'account_settings_title'    => array(
				'title'       => esc_html__( 'Account Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description' => sprintf( __( 'Please configure your shipping parameters and your access towards the PostNL APIs by means of authentication. You can find the details of your PostNL account in Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
			),
			'api_keys'                  => array(
				'title'             => esc_html__( 'API Key', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				// translators: %1$s & %2$s is replaced with <a> tag.
				'description'       => sprintf( __( 'Insert your PostNL production API-key. You can find your API-key on Mijn %1$sPostNL%2$s under "My Account".', 'postnl-for-woocommerce' ), '<a href="https://mijn.postnl.nl/c/BP2_Mod_Login.app" target="_blank">', '</a>' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'customer_num'              => array(
				'title'             => esc_html__( 'Customer Number', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "11223344"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '11223344',
				'custom_attributes' => array( 'maxlength' => '10' ),
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
			'location_code'             => array(
				'title'             => esc_html__( 'Location Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'e.g. "123456"', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '123456',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),

			// Address Details.
			'address_title'             => array(
				'title'       => esc_html__( 'Address Details', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please insert your company\’s address details.', 'postnl-for-woocommerce' ),
			),
			'address_company'           => array(
				'title'       => esc_html__( 'Company Name', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Company Name.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_street'            => array(
				'title'       => esc_html__( 'Street Address', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Street Address.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_street_no'         => array(
				'title'       => esc_html__( 'Housenumber', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Street Housenumber.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_street_ext'        => array(
				'title'       => esc_html__( 'Housenumber Extension', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Street Housenumber extension.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_city'              => array(
				'title'       => esc_html__( 'City', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter City.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_state'             => array(
				'title'       => esc_html__( 'State', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return County.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'address_country'           => array(
				'title'       => esc_html__( 'Country', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Enter Country.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => $available_countries,
				'default'     => '',
			),
			'address_zip'               => array(
				'title'       => esc_html__( 'Zipcode', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter zipcode.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),

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
				'title'       => esc_html__( 'Company', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Company.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_replynumber'        => array(
				'title'       => esc_html__( 'Replynumber', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter replynumber.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_address'            => array(
				'title'       => esc_html__( 'Street Address', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Street Address.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_address_no'         => array(
				'title'       => esc_html__( 'House Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter return house number.', 'postnl-for-woocommerce' ),
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
			'return_address_state'      => array(
				'title'       => esc_html__( 'State', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return County.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_address_zip'        => array(
				'title'       => esc_html__( 'Postcode', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Postcode.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_phone'              => array(
				'title'       => esc_html__( 'Phone Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Phone Number.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_email'              => array(
				'title'       => esc_html__( 'Email', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Email.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_customer_code'      => array(
				'title'       => esc_html__( 'Customer Code', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter return customer code.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_direct_print_label' => array(
				'title'       => esc_html__( 'Print returnlabel directly with shippinglabel', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'When you create the shippinglabel, you can choose to directly create a return label. The returnlabel can then be inserted in the box of the shipment so it is easier for your customer to return (part of) the order.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),

			// Delivery Options Settings.
			'delivery_options_title'    => array(
				'title'       => esc_html__( 'Delivery Options Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your delivery options parameters.', 'postnl-for-woocommerce' ),
			),
			'enable_delivery'           => array(
				'title'       => __( 'Enable PostNL Delivery', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show PostNL shipping in your check-out.', 'postnl-for-woocommerce' ),
				'default'     => 'yes',
				'desc_tip'    => true,
			),
			'enable_pickup_points'      => array(
				'title'       => __( 'Enable PostNL Pick-up Points', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show PostNL pick-up points in the checkout so that your customers can choose to get their orders delivered at a PostNL pick-up point.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'enable_delivery_days'      => array(
				'title'       => __( 'Enable Delivery Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Show delivery days in the checkout so that your customers can choose which day to receive their order.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'enable_evening_delivery'   => array(
				'title'       => __( 'Enable Evening Delivery', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable evening delivery on the frontend.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'evening_delivery_fee'      => array(
				'title'       => __( 'Evening Delivery Fee', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => __( 'Fee for evening delivery option.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'class'       => 'wc_input_price',
			),
			'transit_time'             => array(
				'title'       => esc_html__( 'Transit Time', 'postnl-for-woocommerce' ),
				'type'        => 'number',
				'description' => esc_html__( 'The number of days it takes for the order to be delivered after the order has been placed.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'cut_off_time'              => array(
				'title'       => esc_html__( 'Cut Off Time', 'postnl-for-woocommerce' ),
				'type'        => 'time',
				'description' => esc_html__( 'If an order is ordered after this time, one day will be added to the transit time.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'dropoff_day_mon'     => array(
				'title'       => __( 'Drop off Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Monday', 'postnl-for-woocommerce' ),
				'description' => __( 'Select which days you ship orders.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'dropoff_day_tue'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Tuesday', 'postnl-for-woocommerce' ),
			),
			'dropoff_day_wed'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Wednesday', 'postnl-for-woocommerce' ),
			),
			'dropoff_day_thu'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Thursday', 'postnl-for-woocommerce' ),
			),
			'dropoff_day_fri'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Friday', 'postnl-for-woocommerce' ),
			),
			'dropoff_day_sat'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Saturday', 'postnl-for-woocommerce' ),
			),
			'dropoff_day_sun'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Sunday', 'postnl-for-woocommerce' ),
			),

			// Shipping Outside Europe Settings.
			'shipping_outside_eu_title'    => array(
				'title'       => esc_html__( 'Shipping Outside Europe Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your shipping outside Europe option parameters.', 'postnl-for-woocommerce' ),
			),
			'globalpack_type_barcode'   => array(
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
				'description' => esc_html__( 'Default HS tariff code if none is set in the product.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'country_origin'            => array(
				'title'       => esc_html__( 'Default Country of Origin', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Default country of origin if none is set in the product.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
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
				'options'     => array(
					''   => esc_html__( 'Option', 'postnl-for-woocommerce' ),
					'A6' => 'A6',
					'A4' => 'A4',
				),
				'class'       => 'wc-enhanced-select',
			),
			'ask_position_a4'           => array(
				'title'       => esc_html__( 'Ask for start position A4 format', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'When creating shipment labels, select which position on the A4 to start printing.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'track_trace_email'         => array(
				'title'       => esc_html__( 'Track & Trace Email', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Enable PostNL tracking email.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
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
				'placeholder' => '',
			),

			// Address Validation and Extra Settings.
			'validation_extra_title'    => array(
				'title'       => esc_html__( 'Address Validation & Extra Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your address validation and extra option parameters.', 'postnl-for-woocommerce' ),
			),
			'check_dutch_address'       => array(
				'title'       => __( 'Check Dutch addresses', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Based on zipcode and housenumber the address is checked.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => 'yes',
			),
			'enable_logging'            => array(
				'title'             => esc_html__( 'Enable Logging', 'postnl-for-woocommerce' ),
				'type'              => 'checkbox',
				'description'       => esc_html__( 'Enable logging.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
		);
	}
}
