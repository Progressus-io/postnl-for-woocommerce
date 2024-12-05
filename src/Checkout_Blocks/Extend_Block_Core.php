<?php

namespace PostNLWooCommerce\Checkout_Blocks;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Frontend\Dropoff_Points;
use PostNLWooCommerce\Frontend\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Extend_Block_Core {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	private $name = 'postnl';

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public function __construct() {

		// Initialize hooks
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [
			$this,
			'save_postnl_checkout_fields'
		], 10, 2 );

		// Register the update callback when WooCommerce Blocks is loaded
		add_action( 'init', array( $this, 'register_store_api_callback' ) );

		// Register fee calculation
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'postnl_add_custom_fee' ) );
		$this->register_additional_checkout_fields();

		//Validate adress in cart
		add_action( 'woocommerce_store_api_cart_errors',array($this, 'postnl_validate_address_in_cart'), 10, 2 );


		/**
		 * Registers AJAX actions.
		 */
		add_action( 'wp_ajax_postnl_set_checkout_post_data', [ $this, 'handle_set_checkout_post_data' ] );
		add_action( 'wp_ajax_nopriv_postnl_set_checkout_post_data', [ $this, 'handle_set_checkout_post_data' ] );
		add_action( 'wp_ajax_postnl_get_delivery_options', [ $this, 'handle_get_delivery_options' ] );
		add_action( 'wp_ajax_nopriv_postnl_get_delivery_options', [ $this, 'handle_get_delivery_options' ] );

	}


	/**
	 * Validate address in cart.
	 */
	public function postnl_validate_address_in_cart( $errors, $cart ) {
		$customer              = WC()->customer;
		$validated_address     = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );
		$shipping_country  = $customer->get_shipping_country();
		$shipping_postcode = $customer->get_shipping_postcode();

		$customer_data = wc()->session->get( 'customer' );
		if ( isset( $customer_data['meta_data'] ) && is_array( $customer_data['meta_data'] ) ) {
			// Create an associative array with keys and values
			$meta_keys = array_column( $customer_data['meta_data'], 'value', 'key' );

			// Check if the specific shipping house number exists
			if ( isset( $meta_keys['_wc_shipping/postnl/house_number'] ) ) {
				$shipping_house_number = sanitize_text_field( $meta_keys['_wc_shipping/postnl/house_number'] );
			}
		}
		$settings = Settings::get_instance();

		if ( $settings->is_validate_nl_address_enabled() && 'NL' === $shipping_country ) {
			if ( empty( $validated_address ) && ! empty( $shipping_postcode ) && ! empty( $shipping_house_number ) ) {
				$errors->add( 'invalid_address', __( 'This is not a valid address!', 'postnl-for-woocommerce' ) );
			}
		}
	}

	/**
	 * Register additional checkout fields .
	 */
	public function register_additional_checkout_fields() {
		add_action(
			'init',
			function () {
				woocommerce_register_additional_checkout_field(
					array(
						'id'         => 'postnl/house_number',
						'label'      => 'House Number',
						'location'   => 'address',
						'required'   => true,
						'attributes' => array(
							'autocomplete' => 'house-number',
						),
					),
				);
			}
		);
	}

	/**
	 * Register the update callback with WooCommerce Store API
	 */
	public function register_store_api_callback() {
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => $this->name,
					'callback'  => [ $this, 'postnl_store_api_callback' ],
				)
			);
		}
	}

	/**
	 * Callback function for the Store API update
	 *
	 * @param array $data Data sent from the client.
	 */
	public function postnl_store_api_callback( $data ) {
		if ( isset( $data['action'] ) && 'update_delivery_fee' === $data['action'] ) {
			$price = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
			$type  = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : '';

			// Store the fee amount and type in session
			WC()->session->set( 'postnl_delivery_fee', $price );
			WC()->session->set( 'postnl_delivery_type', $type );
		}
	}

	/**
	 * Adds the delivery fee to the WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The WooCommerce cart object.
	 */
	public function postnl_add_custom_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Get the fee amount and type from session
		$fee_amount = WC()->session->get( 'postnl_delivery_fee', 0 );
		$fee_type   = WC()->session->get( 'postnl_delivery_type', '' );

		// Define the fee label based on the type
		$fee_label = __( 'PostNL Delivery Fee', 'postnl-for-woocommerce' );
		if ( '08:00-12:00' === $fee_type || 'Morning' === $fee_type ) {
			$fee_label = __( 'PostNL Morning Fee', 'postnl-for-woocommerce' );
		} elseif ( 'Evening' === $fee_type ) {
			$fee_label = __( 'PostNL Evening Fee', 'postnl-for-woocommerce' );
		}

		// Remove existing PostNL fees if they exist
		$new_fees = array();
		foreach ( $cart->get_fees() as $fee ) {
			if ( strpos( $fee->name, 'PostNL' ) === false ) {
				$new_fees[] = $fee;
			}
		}
		$cart->fees_api()->set_fees( $new_fees );

		if ( $fee_amount > 0 ) {
			// Add the fee to the cart
			$cart->add_fee( $fee_label, $fee_amount, true, '' );
		}
	}

	/**
	 * Saves the PostNL delivery day or dropoff points fields to the order's metadata.
	 *
	 * @param \WC_Order $order Order object.
	 * @param \WP_REST_Request $request REST request object.
	 */
	public function save_postnl_checkout_fields( \WC_Order $order, \WP_REST_Request $request ) {

		$postnl_request_data = $request['extensions'][ $this->name ];

		// Extract billing and shipping house numbers with sanitization
		$billing_house_number  = isset( $postnl_request_data['postnl_billing_house_number'] ) ? sanitize_text_field( $postnl_request_data['postnl_billing_house_number'] ) : '';
		$shipping_house_number = isset( $postnl_request_data['postnl_shipping_house_number'] ) ? sanitize_text_field( $postnl_request_data['postnl_shipping_house_number'] ) : '';

		// Update billing and shipping house numbers
		$order->update_meta_data( '_billing_house_number', $billing_house_number );
		$order->update_meta_data( '_shipping_house_number', $shipping_house_number );

		/**
		 * Prepare Dropoff Points Data
		 */
		$drop_data = [
			'frontend' => [
				'dropoff_points'                   => isset( $postnl_request_data['dropoffPoints'] ) ? sanitize_text_field( $postnl_request_data['dropoffPoints'] ) : '',
				'dropoff_points_address_company'   => isset( $postnl_request_data['dropoffPointsAddressCompany'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddressCompany'] ) : '',
				'dropoff_points_distance'          => isset( $postnl_request_data['dropoffPointsDistance'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDistance'] ) : '',
				'dropoff_points_address_address_1' => isset( $postnl_request_data['dropoffPointsAddress1'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress1'] ) : '',
				'dropoff_points_address_address_2' => isset( $postnl_request_data['dropoffPointsAddress2'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress2'] ) : '',
				'dropoff_points_address_city'      => isset( $postnl_request_data['dropoffPointsCity'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCity'] ) : '',
				'dropoff_points_address_postcode'  => isset( $postnl_request_data['dropoffPointsPostcode'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPostcode'] ) : '',
				'dropoff_points_address_country'   => isset( $postnl_request_data['dropoffPointsCountry'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCountry'] ) : '',
				'dropoff_points_partner_id'        => isset( $postnl_request_data['dropoffPointsPartnerID'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPartnerID'] ) : '',
				'dropoff_points_date'              => isset( $postnl_request_data['dropoffPointsDate'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDate'] ) : '',
				'dropoff_points_time'              => isset( $postnl_request_data['dropoffPointsTime'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsTime'] ) : '',
				'dropoff_points_type'              => isset( $postnl_request_data['dropoffPointsType'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsType'] ) : '',
			],
		];

		/**
		 * Prepare Delivery Day Data
		 */
		$delivery_day_data = [
			'frontend' => [
				'delivery_day'       => isset( $postnl_request_data['deliveryDay'] ) ? sanitize_text_field( $postnl_request_data['deliveryDay'] ) : '',
				'delivery_day_date'  => isset( $postnl_request_data['deliveryDayDate'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayDate'] ) : '',
				'delivery_day_from'  => isset( $postnl_request_data['deliveryDayFrom'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayFrom'] ) : '',
				'delivery_day_to'    => isset( $postnl_request_data['deliveryDayTo'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayTo'] ) : '',
				'delivery_day_price' => isset( $postnl_request_data['deliveryDayPrice'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayPrice'] ) : '',
				'delivery_day_type'  => isset( $postnl_request_data['deliveryDayType'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayType'] ) : '',
			],
		];

		if ( ! empty( $drop_data['frontend']['dropoff_points'] ) ) {
			$order->update_meta_data( '_postnl_order_metadata', $drop_data );
		} elseif ( ! empty( $delivery_day_data['frontend']['delivery_day'] ) ) {
			// Save Delivery Day Data
			$order->update_meta_data( '_postnl_order_metadata', $delivery_day_data );
		}

		/**
		 * Save the order to persist changes
		 */
		$order->save();
	}

	/**
	 * Handle AJAX request to set checkout post data and return updated delivery options.
	 */
	public function handle_set_checkout_post_data() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
			wp_die();
		}

		// Check if data is provided
		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( [ 'message' => 'No data provided.' ], 400 );
			wp_die();
		}

		// Sanitize data
		$sanitized_data = array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) );

		// Validation
		$settings         = new Settings();
		$shipping_country = isset( $sanitized_data['shipping_country'] ) ? $sanitized_data['shipping_country'] : '';

		// Save the house number and postcode on WC customer
		if ( isset( $sanitized_data['shipping_house_number'] ) && isset( $sanitized_data['shipping_postcode'] ) ) {
			// Set the shipping postcode
			WC()->customer->set_shipping_postcode( $sanitized_data['shipping_postcode'] );

			// Update the house number meta data
			WC()->customer->update_meta_data( '_wc_shipping/postnl/house_number', $sanitized_data['shipping_house_number'] );

			// Save the customer data
			WC()->customer->save();
		}

		// If not NL, clear session and return
		if ( 'NL' !== $shipping_country ) {
			WC()->session->__unset( 'postnl_checkout_post_data' );
			wp_send_json_success( [
				'message'        => 'No delivery options available.',
				'show_container' => false,
			], 200 );
			wp_die();
		}

		// Check if required fields are present
		if ( empty( $sanitized_data['shipping_postcode'] ) || empty( $sanitized_data['shipping_house_number'] ) ) {
			WC()->session->__unset( 'postnl_checkout_post_data' );
			wp_send_json_success( [
				'message'        => 'Postcode or house number is missing.',
				'show_container' => false,
			], 200 );
			wp_die();
		}

		// Retrieve previously sanitized data from session
		$previous_sanitized_data = WC()->session->get( 'postnl_checkout_post_data' );

		// Determine if address has changed
		$address_changed = $previous_sanitized_data !== $sanitized_data;

		if ( $address_changed ) {
			// Clear previous validated address
			WC()->session->__unset( POSTNL_SETTINGS_ID . '_validated_address' );
		}

		// Retrieve validated address from session
		$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );

		// If validation is enabled and address has changed or not validated yet
		if ( $settings->is_validate_nl_address_enabled() && ( $address_changed || empty( $validated_address ) ) ) {
			// Create Container instance
			$container = new Container();

			// Validate the address
			try {
				$container->validated_address( $sanitized_data );
				// Get the validated address from the session
				$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );
				if ( empty( $validated_address ) ) {
					throw new \Exception( 'Address validation failed.' );
				}
			} catch ( \Exception $e ) {
			}
		}

		// Store data in WooCommerce session
		WC()->session->set( 'postnl_checkout_post_data', $sanitized_data );

		// Determine whether to show the container
		$show_container = true;

		// Prepare the response data
		$response_data = [
			'message'          => 'Data saved successfully.',
			'show_container'   => $show_container,
			'validated_address' => $validated_address,
		];

		wp_send_json_success( $response_data, 200 );
		wp_die();
	}

	/**
	 * Handle AJAX request to fetch updated delivery options.
	 */
	public function handle_get_delivery_options() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
			wp_die();
		}

		// Retrieve post_data from WooCommerce session
		$order_data = WC()->session->get( 'postnl_checkout_post_data' );


		$settings         = new Settings();
		$shipping_country = isset( $order_data['shipping_country'] ) ? $order_data['shipping_country'] : '';

		if ( empty( $order_data ) || ! is_array( $order_data ) || 'NL' !== $shipping_country ) {

			// Clear the session data
			WC()->session->__unset( 'postnl_checkout_post_data' );

			// Return empty response to notify frontend to clear options
			wp_send_json_success(
				[
					'delivery_options' => [],
					'dropoff_options'  => [],
				], 200 );
			wp_die();
		}
		if ( $settings->is_validate_nl_address_enabled() ) {

			// Check if shipping_postcode is provided
			if ( empty( $order_data['shipping_postcode'] ) || empty( $order_data['shipping_house_number'] ) ) {

				// Clear the session data
				WC()->session->__unset( 'postnl_checkout_post_data' );

				// Return empty response to notify frontend to clear options
				wp_send_json_success(
					[
						'delivery_options' => [],
						'dropoff_options'  => [],
					], 200 );
				wp_die();

			}

		}


		try {
			$container        = new Container();
			$delivery_day     = new Delivery_Day();
			$dropoff          = new Dropoff_Points();
			$checkout_data    = $container->get_checkout_data( $order_data );
			$delivery_options = $delivery_day->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );
			$dropoff_options  = $dropoff->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );

			wp_send_json_success(
				[
					'delivery_options' => isset( $delivery_options['delivery_options'] ) ? $delivery_options['delivery_options'] : [],
					'dropoff_options'  => isset( $dropoff_options['dropoff_options'] ) ? $dropoff_options['dropoff_options'] : [],
				], 200 );
		} catch ( \Exception $e ) {

			wp_send_json_error( [ 'message' => 'Failed to fetch delivery options.' ], 500 );
		}

		wp_die();
	}

}
