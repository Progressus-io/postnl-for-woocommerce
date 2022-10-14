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
		if ( $this->settings->is_reorder_nl_address_enabled() ) {
			add_filter( 'woocommerce_checkout_fields', array( $this, 'reorganize_checkout_fields' ) );
			add_filter( 'woocommerce_get_country_locale', array( $this, 'country_locale_field_order' ) );
		}
	}

	/**
	 * Add delivery day fields.
	 */
	public function reorganize_checkout_fields( $fields ) {
		// Reorder the fields if setting enabled.
		if ( 'NL' === $this->get_billing_country() ) {
			$fields = $this->reorganize_address_fields( 'billing', $fields );
		}

		if ( 'NL' === $this->get_shipping_country() ) {
			$fields = $this->reorganize_address_fields( 'shipping', $fields );
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
	protected function reorganize_address_fields( string $address_type, array $fields ) {
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
		$priority       = ( count( $fields_to_order ) + 1 ) * 10;
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

	/**
	 * Change postcode field priority for NL country
	 *
	 * @param $checkout_fields
	 *
	 * @return array
	 */
	function country_locale_field_order( $checkout_fields ) {
		if ( isset( $checkout_fields['NL']['postcode']['priority'] ) ) {
			$checkout_fields['NL']['postcode']['priority'] = 40;
		}

		return $checkout_fields;
	}

}