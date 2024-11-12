<?php

namespace PostNLWooCommerce\Checkout_Blocks;

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
		add_action( 'init', [ $this, 'register_store_api_callback' ] );

		// Register fee calculation
		add_action( 'woocommerce_cart_calculate_fees', [ $this, 'postnl_add_custom_fee' ] );
		$this->register_additional_checkout_fields();

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
}
