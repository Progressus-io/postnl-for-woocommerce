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
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'validate_nl_address' ));
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
	 * Validate NL address
	 * @return void
	 */
	public function validate_nl_address() {
		if ( ! $this->settings->is_validate_nl_address_enabled() ) {
			return;
		}

		$checkout_data = $this->validate_address();
		if ( ! empty( $checkout_data['error'] ) ) {
			wc_add_notice( $checkout_data['error'], 'error' );
		}
	}

	/**
	 * Check address by PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function validate_address() {
		try {
			$post_data = ( new Container() )->get_checkout_post_data();

			if ( empty( $post_data ) ) {
				return array();
			}

			$post_data = Utils::set_post_data_address( $post_data );

			$item_info = new Postcode_Check\Item_Info( $post_data );
			$api_call  = new Postcode_Check\Client( $item_info );
			$response  = $api_call->send_request();

			return array(
				'response'  => $response,
				'post_data' => $post_data,
			);
		} catch ( \Exception $e ) {
			return array(
				'response' => array(),
				'error'    => $e->getMessage(),
			);
		}
	}
}
