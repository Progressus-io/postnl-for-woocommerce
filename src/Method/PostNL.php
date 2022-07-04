<?php
/**
 * Class Method/PostNL file.
 *
 * @package PostNLWooCommerce\Method
 */

namespace PostNLWooCommerce\Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package PostNLWooCommerce\Method
 */
class PostNL extends \WC_Shipping_Method {
	/**
	 * Init and hook in the integration.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = 'postnl';
		$this->instance_id  = absint( $instance_id );
		$this->method_title = __( 'PostNL', 'postnl-for-woocommerce' );

		// translators: %1$s & %2$s is replaced with <a> tag.
		$this->method_description = sprintf( __( 'Below you will find all functions for controlling, preparing and processing your shipment with PostNL. Prerequisite is a valid PostNL business customer contract. If you are not yet a PostNL business customer, you can request a quote %1$shere%2$s.', 'postnl-for-woocommerce' ), '<a href="https://www.postnl.nl/en/business-solutions/business-customers/" target="_blank">', '</a>' );

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
		$this->form_fields = array(
			// Account Settings.
			'account_settings_title'   => array(
				'title'       => esc_html__( 'Account Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your shipping parameters and your access towards the PostNL APIs by means of authentication.', 'postnl-for-woocommerce' ),
			),
			'customer_num'             => array(
				'title'             => esc_html__( 'Customer Number', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your customer number (10 digits - numerical). This will be provided by your local PostNL sales organization.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '1234567890',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'customer_code'            => array(
				'title'             => esc_html__( 'Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your customer code (10 digits - numerical). This will be provided by your local PostNL sales organization.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '1234567890',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'location_code'            => array(
				'title'             => esc_html__( 'Location Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your location code', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'globalpack_type_barcode'  => array(
				'title'             => esc_html__( 'Type Barcode GlobalPack', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your type barcode for GlobalPack', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'globalpack_customer_code' => array(
				'title'             => esc_html__( 'GlobalPack Customer Code', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your customer code for GlobalPack', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'api_keys'                 => array(
				'title'             => esc_html__( 'API Keys', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => esc_html__( 'Your API keys to authenticate the PostNL API', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
			'enable_logging'           => array(
				'title'             => esc_html__( 'Enable Logging', 'postnl-for-woocommerce' ),
				'type'              => 'checkbox',
				'description'       => esc_html__( 'Enable logging.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),

			// Label Settings.
			'label_title'               => array(
				'title'       => esc_html__( 'Label', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your label parameters.', 'postnl-for-woocommerce' ),
			),
			'default_return_address'    => array(
				'title'       => esc_html__( 'Default Return Address', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'description' => esc_html__( 'Default whether to create a return address or not.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'return_name'              => array(
				'title'       => esc_html__( 'Name', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Name.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
			),
			'return_company'            => array(
				'title'       => esc_html__( 'Company', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Company.', 'postnl-for-woocommerce' ),
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
				'title'       => esc_html__( 'Street Address Number', 'postnl-for-woocommerce' ),
				'type'        => 'text',
				'description' => esc_html__( 'Enter Return Street Address Number.', 'postnl-for-woocommerce' ),
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
			'label_format'              => array(
				'title'       => esc_html__( 'Label Format', 'postnl-for-woocommerce' ),
				'type'        => 'select',
				'description' => esc_html__( 'Option to select label size format.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'options'     => array(
					'' => esc_html__( 'Option', 'postnl-for-woocommerce' ),
				),
				'class'       => 'wc-enhanced-select',
			),
			'track_trace_email'         => array(
				'title'       => esc_html__( 'Track &amp; Trace Email', 'postnl-for-woocommerce' ),
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

			// Services Settings.
			'services_title'            => array(
				'title'       => esc_html__( 'Services', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => esc_html__( 'Please configure your service parameters.', 'postnl-for-woocommerce' ),
			),
			'cut_off_time'              => array(
				'title'       => esc_html__( 'Cut Off Time', 'postnl-for-woocommerce' ),
				'type'        => 'time',
				'description' => esc_html__( 'Cut off time for pickup.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'delivery_time'             => array(
				'title'       => esc_html__( 'Delivery Time', 'postnl-for-woocommerce' ),
				'type'        => 'number',
				'description' => esc_html__( 'Number of days required to deliver the package.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
				'default'     => '',
				'placeholder' => '',
			),
			'excluded_delivery_mon'     => array(
				'title'       => __( 'Excluded Delivery Days', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Monday', 'postnl-for-woocommerce' ),
				'description' => __( 'Exclude days to delivery packages to PostNL.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'excluded_delivery_tue'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Tuesday', 'postnl-for-woocommerce' ),
			),
			'excluded_delivery_wed'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Wednesday', 'postnl-for-woocommerce' ),
			),
			'excluded_delivery_thu'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Thursday', 'postnl-for-woocommerce' ),
			),
			'excluded_delivery_fri'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Friday', 'postnl-for-woocommerce' ),
			),
			'excluded_delivery_sat'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Saturday', 'postnl-for-woocommerce' ),
			),
			'excluded_delivery_sun'     => array(
				'type'  => 'checkbox',
				'label' => __( 'Sunday', 'postnl-for-woocommerce' ),
			),
			'enable_day_choice'         => array(
				'title'       => __( 'Enable Day Choice', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable the frontend option to display delivery day.', 'postnl-for-woocommerce' ),
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
			'enable_drop_off_points'    => array(
				'title'       => __( 'Enable Drop Off Points', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable drop off points on frontend.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
			'enable_address_validation' => array(
				'title'       => __( 'Enable Address Validation', 'postnl-for-woocommerce' ),
				'type'        => 'checkbox',
				'label'       => __( 'Enable', 'postnl-for-woocommerce' ),
				'description' => __( 'Enable address validation on the frontend.', 'postnl-for-woocommerce' ),
				'desc_tip'    => true,
			),
		);
	}
}
