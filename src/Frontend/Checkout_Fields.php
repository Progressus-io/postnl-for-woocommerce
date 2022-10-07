<?php
/**
 * Class Frontend/Checkout_Fields file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Postcode_Check;
use PostNLWooCommerce\Utils;

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

		add_action( 'woocommerce_checkout_update_order_review', array( $this, 'validate_nl_address' ) );
		add_filter('woocommerce_update_order_review_fragments', array( $this, 'fill_validated_address' ) );
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
	protected function reorder_fields_by_key( string $key, array $fields ) {
		// Add House number field.
		$fields[ $key ][ $key.'_house_number'] = array(
			'type'          => 'text',
			'label'         => __( 'House number', 'postnl-for-woocommerce' ),
			'placeholder'   => _x( 'House number', 'placeholder', 'postnl-for-woocommerce' ),
			'required'      => true
		);

		$fields_to_order    = [ 'first_name', 'last_name', 'country', 'postcode', 'house_number', 'address_2', 'address_1', 'city' ];
		foreach ( $fields_to_order as $priority => $field ) {
			$fields[ $key ][ $key.'_'.$field ][ 'priority' ] = $priority + 1;
		}

		return $fields;
	}

	/**
	 * Check address by PostNL Checkout Rest API.
	 *
	 * @return array|void
	 */
	public function validate_nl_address( ) {
		if ( ! $this->settings->is_validate_nl_address_enabled() ) {
			return;
		}

		if ( 'NL' !== $this->get_billing_country() && 'NL' !== $this->get_shipping_country() ) {
			return;
		}

		try {
			$post_data = $this->get_checkout_post_data();

			if ( empty( $post_data ) ) {
				return array();
			}

			$shipping_address = Utils::set_post_data_address( $post_data );

			if ( ! isset( $shipping_address[ 'shipping_house_number' ] ) || '' === $shipping_address[ 'shipping_house_number' ] ) {
				return array();
			}

			if ( ! isset( $shipping_address[ 'shipping_postcode' ] ) || '' === $shipping_address[ 'shipping_postcode' ] ) {
				return array();
			}

			$item_info = new Postcode_Check\Item_Info( $shipping_address );
			$api_call  = new Postcode_Check\Client( $item_info );
			$response  = $api_call->send_request();

			WC()->session->set( 'validated_address_city', $response[0]['city'] ?? '' );
			WC()->session->set( 'validated_address_streetName', $response[0]['streetName'] ?? '' );
			WC()->session->set( 'is_shipping_address', isset( $post_data['ship_to_different_address'] ) );

		} catch ( \Exception $e ) {

			wp_send_json_error( [
				'message'    => $e->getMessage()
			] );

		}
	}

	public function fill_validated_address( $fragments ){
		if ( ! WC()->session->get( 'is_shipping_address' ) ) {
			$address_type = 'billing';
		} else {
			$address_type = 'shipping';
		}

		$city       = WC()->session->get( 'validated_address_city' ) ?? '';
		$streetName = WC()->session->get( 'validated_address_streetName' ) ?? '';

		if ( '' !== $city ) {
			$fragments['#'.$address_type.'_city'] = '<input type="text" class="input-text " name="'.$address_type.'_city" id="'.$address_type.'_city" placeholder="" value="'.$city.'" autocomplete="address-level2">';
		}

		if ( '' !== $streetName ) {
			$fragments['#'.$address_type.'_address_1'] = '<input type="text" class="input-text " name="'.$address_type.'_address_1" id="'.$address_type.'_address_1" value="'.$streetName.'" autocomplete="address-line1">';
		}

		return $fragments;
	}

	/**
	 * Get checkout $_POST['post_data'].
	 *
	 * @return array
	 */
	public function get_checkout_post_data() {
		if ( empty( $_REQUEST['post_data'] ) ) {
			return array();
		}

		$post_data = array();

		if ( isset( $_REQUEST['post_data'] ) ) {
			parse_str( sanitize_text_field( wp_unslash( $_REQUEST['post_data'] ) ), $post_data );
		}

		return $post_data;
	}
}