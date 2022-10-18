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
			add_filter( 'woocommerce_default_address_fields', array( $this, 'add_house_number' ) );
			add_filter( 'woocommerce_get_country_locale', array( $this, 'get_country_locale' ) );
			add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'country_locale_field_selectors' ) );

			add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'admin_shipping_fields' ) );
			add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'display_house_number' ), 10, 2 );
		}
	}

	/**
	 * Add House Number to address fields.
	 *
	 * @param $address_fields_array
	 *
	 * @return array
	 */
	public function add_house_number( $address_fields_array ) {
		$address_fields_array['house_number'] = array(
			'type'        => 'text',
			'label'       => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder' => esc_attr__( 'House number', 'postnl-for-woocommerce' ),
			'required'    => false,
			'hidden'      => true
		);

		return $address_fields_array;
	}

	/**
	 * Localization for NL address fields.
	 *
	 * @param $checkout_fields
	 *
	 * @return array
	 */
	function get_country_locale( $checkout_fields ) {
		$fields_to_order = array(
			'first_name'   => array(
				'priority' => 1
			),
			'last_name'    => array(
				'priority' => 2
			),
			'company'      => array(
				'priority' => 3
			),
			'country'      => array(
				'priority' => 4
			),
			'postcode'     => array(
				'priority' => 5
			),
			'house_number' => array(
				'priority' => 6,
				'required' => true,
				'hidden'   => false
			),
			'address_2'    => array(
				'placeholder' => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
				'priority'    => 7
			),
			'address_1'    => array(
				'placeholder' => esc_attr__( 'Street Name', 'postnl-for-woocommerce' ),
				'priority'    => 8
			),
			'city'         => array(
				'priority' => 9
			)
		);

		foreach ( $fields_to_order as $field_key => $field ) {
			foreach ( $field as $override => $value ) {
				$checkout_fields['NL'][ $field_key ][ $override ] = $value;
			}
		}

		return $checkout_fields;
	}

	/**
	 * Add fields selectors
	 *
	 * @param $locale_fields
	 *
	 * @return mixed
	 */
	function country_locale_field_selectors( $locale_fields ) {
		$additional_selectors = array(
			'house_number' => '#billing_house_number_field, #shipping_house_number_field',
			'first_name'   => '#billing_first_name_field, #shipping_first_name_field',
			'last_name'    => '#billing_last_name_field, #shipping_last_name_field'
		);

		return array_merge( $locale_fields, $additional_selectors );
	}

	/**
	 * Add house number field to admin shipping fields.
	 *
	 * @param array $fields
	 *
	 * @return array
	 */
	public function admin_shipping_fields( $fields ) {
		$new_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( 'address_1' === $key ) {
				$new_fields['house_number'] = array(
					'label' => __( 'House number', 'postnl-for-woocommerce' ),
					'show'  => false
				);
			}

			$new_fields[ $key ] = $field;
		}

		return $new_fields;
	}

	/**
	 * Display house number in admin order shipping address.
	 *
	 * @param array $address
	 * @param \WC_Order $order
	 *
	 * @return array
	 */
	public function display_house_number( $address, $order ) {
		$house_number = $order->get_meta( '_shipping_house_number' );
		if ( $house_number ) {
			$address['address_1'] .= ' ' . $house_number;
		}

		return $address;
	}

}