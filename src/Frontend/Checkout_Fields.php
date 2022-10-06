<?php
/**
 * Class Frontend/Checkout_Fields file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Checkout_Fields
 *
 * @package PostNLWooCommerce\Frontend
 */
class Checkout_Fields {
	/**
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->settings = Settings::get_instance();

		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_checkout_fields', array( $this, 'reorder_fields' ), 10 );
	}

	/**
	 * Add delivery day fields.
	 */
	public function reorder_fields( $fields ) {
		// Reorder the fields if setting enabled.
		if ( ! $this->settings->is_reorder_nl_address_enabled() ) {
			return $fields;
		}

		if ( 'NL' === $this->get_billing_country() ) {
			return $this->reorder_fields_by_key( 'billing', $fields );
		}

		if ( 'NL' === $this->get_shipping_country() ) {
			return $this->reorder_fields_by_key( 'shipping', $fields );
		}

		return $fields;
	}

	/**
	 * Get Cart Shipping country
	 *
	 * @return string|null
	 */
	public function get_shipping_country() {
		return WC()->customer->get_shipping_country();
	}

	/**
	 * Get Cart Billing country
	 *
	 * @return string|null
	 */
	public function get_billing_country() {
		return WC()->customer->get_billing_country();
	}

	/**
	 *
	 */
	protected function reorder_fields_by_key( $key, $fields ) {
		// Add House number field.
		$fields[ $key ][ $key.'_house_number'] = array(
			'type'          => 'text',
			'label'         => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder'   => _x( 'House number', 'placeholder', 'postnl-for-woocommerce' ),
			'required'      => true
		);

		$fields_to_order    = [ 'first_name', 'last_name', 'country', 'postcode', 'house_number', 'address_2', 'address_2', 'address_1', 'city' ];
		foreach ( $fields_to_order as $priority => $field ) {
			$fields[ $key ][ $key.'_'.$field ][ 'priority' ] = $priority + 1;
		}

		return $fields;
	}
}
