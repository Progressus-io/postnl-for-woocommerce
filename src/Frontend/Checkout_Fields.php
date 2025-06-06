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
		if ( ! $this->settings->is_reorder_nl_address_enabled() ) {
			return;
		}

		if ( ! $this->is_blocks_checkout() ) {
			add_filter( 'woocommerce_default_address_fields', array( $this, 'add_house_number' ) );
		}

		add_filter( 'woocommerce_get_country_locale', array( $this, 'get_country_locale' ) );
		add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'country_locale_field_selectors' ) );
	}

	/**
	 * Add House Number to address fields.
	 *
	 * @param array $address_fields_array Address fields.
	 *
	 * @return array
	 */
	public function add_house_number( array $address_fields_array ): array {
		$address_fields_array['house_number'] = array(
			'type'        => 'text',
			'label'       => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder' => esc_attr__( 'House number', 'postnl-for-woocommerce' ),
			'required'    => false,
			'hidden'      => true,
		);

		return $address_fields_array;
	}

	/**
	 * Localization for NL address fields.
	 *
	 * @param array $checkout_fields Checkout fields.
	 *
	 * @return array
	 */
	public function get_country_locale( array $checkout_fields ): array {
		$is_blocks_checkout = $this->is_blocks_checkout();

		if ( $is_blocks_checkout ) {
			$house_number_key = 'postnl/house_number';
		} else {
			$house_number_key = 'house_number';
		}

		$fields_to_order = array(
			'first_name'      => array( 'priority' => 1 ),
			'last_name'       => array( 'priority' => 2 ),
			'company'         => array( 'priority' => 3 ),
			'country'         => array( 'priority' => 4 ),
			'postcode'        => array( 'priority' => 5 ),
			$house_number_key => array(
				'priority' => 6,
				'required' => true,
				'hidden'   => false,
			),
			'address_2'       => array(
				'label'       => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
				'placeholder' => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
				'priority'    => 7,
			),
			'address_1'       => array(
				'label'       => __( 'Street', 'postnl-for-woocommerce' ),
				'placeholder' => esc_attr__( 'Street Name', 'postnl-for-woocommerce' ),
				'priority'    => 8,
			),
			'city'            => array( 'priority' => 9 ),
		);

		if ( $is_blocks_checkout ) {
			unset( $fields_to_order['company'] );
		}

		foreach ( $fields_to_order as $field_key => $field ) {
			foreach ( $field as $override => $value ) {
				$checkout_fields['NL'][ $field_key ][ $override ] = $value;
			}
		}

		/**
		 * Hide house number from the other countries .
		 */
		if ( $is_blocks_checkout ) {
			$countries_codes = array_keys( WC()->countries->get_countries() );
			foreach ( $countries_codes as $country_code ) {
				if ( 'NL' !== $country_code ) {
					$checkout_fields[ $country_code ][ $house_number_key ]['hidden']   = true;
					$checkout_fields[ $country_code ][ $house_number_key ]['required'] = false;
				}
			}
		}

		return $checkout_fields;
	}

	/**
	 * Add fields selectors
	 *
	 * @param array $locale_fields local fields.
	 *
	 * @return array
	 */
	public function country_locale_field_selectors( $locale_fields ) {
		$additional_selectors = array(
			'house_number' => '#billing_house_number_field, #shipping_house_number_field',
			'first_name'   => '#billing_first_name_field, #shipping_first_name_field',
			'last_name'    => '#billing_last_name_field, #shipping_last_name_field',
		);

		return array_merge( $locale_fields, $additional_selectors );
	}

	/**
	 * Is using blocks checkout.
	 *
	 * @return boolean
	 */
	public function is_blocks_checkout(): bool {
		$checkout_page_id = wc_get_page_id( 'checkout' );

		return has_block( 'woocommerce/checkout', $checkout_page_id );
	}
}
