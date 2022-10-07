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

		// Validate NL addresses ajax
		add_action( 'wp_ajax_validate_nl_address', array( $this, 'validate_nl_address' ) );
		add_action( 'wp_ajax_nopriv_validate_nl_address', array( $this, 'validate_nl_address' ) );
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
			if ( empty( $_POST ) ) {
				return array();
			}

			// Check if address filled
			if ( ! isset( $_POST[ 'house_number' ] ) || '' === $_POST[ 'house_number' ] ) {
				return array();
			}

			if ( ! isset( $_POST[ 'postcode' ] ) || '' === $_POST[ 'postcode' ] ) {
				return array();
			}

			// Sanitize data
			$post_data = [
				'shipping_postcode'     => sanitize_text_field( $_POST[ 'postcode' ] ),
				'shipping_house_number' => sanitize_text_field( $_POST[ 'house_number' ] ),
				'shipping_address_2'    => sanitize_text_field( $_POST[ 'address_2' ] )
			];

			$item_info = new Postcode_Check\Item_Info( $post_data );
			$api_call  = new Postcode_Check\Client( $item_info );
			$response  = $api_call->send_request();

			wp_send_json_success( $response[0] ?? [] );
			die();

		} catch ( \Exception $e ) {

			wp_send_json_error( [
				'message'    => $e->getMessage()
			] );

		}
	}
}
