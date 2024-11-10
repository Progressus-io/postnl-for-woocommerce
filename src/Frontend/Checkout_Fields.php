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
		if ( $this->settings->is_reorder_nl_address_enabled()) {
			if(!$this->is_using_checkout_block()){
				add_filter( 'woocommerce_default_address_fields', array( $this, 'add_house_number' ) );
			}
			add_filter( 'woocommerce_get_country_locale', array( $this, 'get_country_locale' ) );
			add_filter( 'woocommerce_country_locale_field_selectors', array( $this, 'country_locale_field_selectors' ) );
		}
	}

	/**
	 * Add House Number to address fields.
	 *
	 * @param Array $address_fields_array Address fields.
	 *
	 * @return array
	 */
	public function add_house_number( $address_fields_array ) {
		$address_fields_array['house_number'] = array(
			'type'        => 'text',
			'label'       => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder' => esc_attr__( 'House number', 'postnl-for-woocommerce' ),
			'required'    => true,
			'hidden'      => true,
		);

		return $address_fields_array;
	}

	/**
	 * Localization for NL address fields.
	 *
	 * @param Array $checkout_fields Checkout fields.
	 *
	 * @return array
	 */
	public function get_country_locale( $checkout_fields ) {

		if ( $this->is_using_checkout_block() ) {
			$fields_to_order = array(
				'first_name'          => array( 'priority' => 1 ),
				'last_name'           => array( 'priority' => 2 ),
				'company'             => array( 'priority' => 3 ),
				'country'             => array( 'priority' => 4 ),
				'postcode'            => array( 'priority' => 5 ),
				'postnl/house_number' => array(
					'priority' => 6,
					'required' => false,
					'hidden'   => false,
				),
				'address_2'           => array(
					'label'       => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
					'placeholder' => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
					'priority'    => 7,
				),
				'address_1'           => array(
					'label'       => __( 'Street', 'postnl-for-woocommerce' ),
					'placeholder' => esc_attr__( 'Street Name', 'postnl-for-woocommerce' ),
					'priority'    => 8,
				),
				'city'                => array( 'priority' => 9 ),
			);
		} else {
			// Old checkout configuration
			$fields_to_order = array(
				'first_name'   => array( 'priority' => 1 ),
				'last_name'    => array( 'priority' => 2 ),
				'company'      => array( 'priority' => 3 ),
				'country'      => array( 'priority' => 4 ),
				'postcode'     => array( 'priority' => 5 ),
				'house_number' => array(
					'priority' => 6,
					'required' => true,
					'hidden'   => false,
				),
				'address_2'    => array(
					'placeholder' => esc_attr__( 'House Number Extension', 'postnl-for-woocommerce' ),
					'priority'    => 7,
				),
				'address_1'    => array(
					'label'       => __( 'Street', 'postnl-for-woocommerce' ),
					'placeholder' => esc_attr__( 'Street Name', 'postnl-for-woocommerce' ),
					'priority'    => 8,
				),
				'city'         => array( 'priority' => 9 ),
			);
		}

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
	 * @param Array $locale_fields local fields.
	 *
	 * @return mixed
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
	 * is_using_checkout_block
	 *
	 * @param Array is_using_checkout_block local fields.
	 *
	 * @return boolean
	 */

	public function is_using_checkout_block() {
		$checkout_page_id = wc_get_page_id( 'checkout' );
		$has_block_checkout = $checkout_page_id && has_block( 'woocommerce/checkout', $checkout_page_id );

		if ( $has_block_checkout) {
			$is_using_checkout_block=true;
		}
		else{
			$is_using_checkout_block=false;
		}


		return $is_using_checkout_block;

	}

}
