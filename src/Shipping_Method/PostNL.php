<?php
/**
 * Class Shipping_Method/PostNL file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL
 *
 * @package PostNLWooCommerce\Shipping_Method
 */
class PostNL extends \WC_Shipping_Flat_Rate {
	/**
	 * Init and hook in the integration.
	 *
	 * @param int $instance_id Instance ID.
	 */
	public function __construct( $instance_id = 0 ) {
		$this->id           = POSTNL_SETTINGS_ID;
		$this->instance_id  = absint( $instance_id );
		$this->method_title = POSTNL_SERVICE_NAME;

		// translators: %1$s & %2$s is replaced with <a> tag.
		$this->method_description = sprintf( __( 'Below you will find all functions for controlling, preparing and processing your shipment with PostNL. Prerequisite is a valid PostNL business customer contract. If you are not yet a PostNL business customer, you can request a quote %1$shere%2$s.', 'postnl-for-woocommerce' ), '<a href="https://mijnpostnlzakelijk.postnl.nl/s/become-a-customer?language=nl_NL#/" target="_blank">', '</a>' );
		$this->supports           = array(
			'shipping-zones',
			'instance-settings',
			'instance-settings-modal',
			'settings',
		);

		$this->postnl_init();
	}

	/**
	 * Shipping method initialization.
	 */
	public function postnl_init() {
		$this->init();
		$this->init_form_fields();
		$this->init_settings();

		add_filter( 'woocommerce_shipping_instance_form_fields_' . $this->id, array( $this, 'instance_form_fields' ), 10, 1 );
		add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * Calculate the shipping costs.
	 *
	 * @param array $package Package of items from cart.
	 */
	public function calculate_shipping( $package = array() ) {
		// Set free shipping rate if cart subtotal exceed minimum_for_free_shipping
		$minimum_for_free_shipping = $this->get_option( 'minimum_for_free_shipping' );
		if ( '' !== $minimum_for_free_shipping && $package['cart_subtotal'] > $minimum_for_free_shipping ) {
			$rate = array(
				'id'      => $this->get_rate_id(),
				'label'   => $this->title,
				'cost'    => 0,
				'package' => $package,
			);

			$this->add_rate( $rate );
		} else {
			parent::calculate_shipping( $package );
		}
	}

	/**
	 * Add form fields for PostNL.
	 *
	 * @param Array $form_fields List of instance form fields.
	 *
	 * @return Array
	 */
	public function instance_form_fields( $form_fields ) {
		// Change title default value.
		$form_fields['title']['default'] = $this->method_title;

		// Minimum for free shipping.
		$currency_symbol =  get_woocommerce_currency_symbol();

		$form_fields['minimum_for_free_shipping'] = array(
			'title' 		=> sprintf( esc_html__( 'Free shipping from %s', 'postnl-for-woocommerce' ), $currency_symbol ),
			'type' 			=> 'number',
			'desc_tip'      => esc_html__( 'Keep empty if you don’t want to use Free shipping', 'postnl-for-woocommerce' ),
			'default' 		=> 0,
		);

		return $form_fields;
	}

	/**
	 * Initialize integration settings form fields.
	 *
	 * @return void
	 */
	public function init_form_fields() {
		$base_country = Utils::get_base_country();
		$settings     = Settings::get_instance();

		if ( 'NL' === $base_country ) {
			$form_fields = $settings->nl_setting_fields();
		} elseif ( 'BE' === $base_country ) {
			$form_fields = $settings->be_setting_fields();
		} else {
			$form_fields = $settings->filter_setting_fields( '' );
		}

		$this->form_fields = $form_fields;
	}
}
