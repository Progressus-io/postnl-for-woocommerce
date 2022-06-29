<?php
/**
 * Class Method/PostNL file.
 *
 * @package Progressus\PostNLWooCommerce\Method
 */

namespace Progressus\PostNLWooCommerce\Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package Progressus\PostNLWooCommerce\Method
 */
class PostNL extends \WC_Shipping_Method {
	/**
	 * Init and hook in the integration.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = 'pr_postnl';
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
			'dhl_pickup_api_dist' => array(
				'title'       => __( 'Account and API Settings', 'postnl-for-woocommerce' ),
				'type'        => 'title',
				'description' => __( 'Please configure your shipping parameters and your access towards the DHL Paket APIs by means of authentication.', 'postnl-for-woocommerce' ),
			),
			'dhl_account_num'     => array(
				'title'             => __( 'Account Number', 'postnl-for-woocommerce' ),
				'type'              => 'text',
				'description'       => __( 'Your account number (10 digits - numerical). This will be provided by your local PostNL sales organization.', 'postnl-for-woocommerce' ),
				'desc_tip'          => true,
				'default'           => '',
				'placeholder'       => '1234567890',
				'custom_attributes' => array( 'maxlength' => '10' ),
			),
		);
	}
}
