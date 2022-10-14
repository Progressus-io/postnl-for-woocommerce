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
	 * @var Settings
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
		add_filter( 'woocommerce_checkout_fields', array( $this, 'reorder_fields' ) );
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
			$fields = $this->reorder_fields_by_key( 'billing', $fields );
		}

		if ( 'NL' === $this->get_shipping_country() ) {
			$fields = $this->reorder_fields_by_key( 'shipping', $fields );
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
	 * Reorder fields by key.
	 *
	 * @param string $key
	 * @param array $fields
	 *
	 * @return array
	 */
	protected function reorder_fields_by_key( string $address_type, array $fields ) {
		// Add House number field.
		$fields[ $address_type ][ $address_type . '_house_number' ] = array(
			'type'        => 'text',
			'label'       => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder' => _x( 'House number', 'placeholder', 'postnl-for-woocommerce' ),
			'required'    => true
		);

		$fields_to_order = [
			$address_type . '_' . 'first_name'   => 10,
			$address_type . '_' . 'last_name'    => 20,
			$address_type . '_' . 'country'      => 30,
			$address_type . '_' . 'postcode'     => 40,
			$address_type . '_' . 'house_number' => 50,
			$address_type . '_' . 'address_2'    => 60,
			$address_type . '_' . 'address_1'    => 70,
			$address_type . '_' . 'city'         => 80
		];

		$ordered_fields = array();
		$priority       = count( $fields_to_order ) * 10;
		foreach ( $fields[ $address_type ] as $field_key => $field ) {
			$ordered_fields[ $field_key ] = $field;
			if ( isset( $fields_to_order[ $field_key ] ) ) {
				$ordered_fields[ $field_key ]['priority'] = $fields_to_order[ $field_key ];
			} else {
				$ordered_fields[ $field_key ]['priority'] = $priority;
				$priority += 10;
			}
		}

		$fields[ $address_type ] = $ordered_fields;

		return $fields;
	}

}