<?php

/**
 * Class Checkout_Blocks/Blocks_core file.
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */

namespace PostNLWooCommerce\Checkout_Blocks;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for Extend WC Core
 *
 * @package PostNLWooCommerce\Checkout_Blocks
 */
class Extend_Block_core {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	private $name = 'postnl';


	/**
	 * Bootstraps the class and hooks required data.
	 */
	public function init() {

		// Initialize hooks
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [
			$this,
			'save_postnl_checkout_fields'
		], 10, 2 );
	}

	/**
	 * Saves the PostNL delivery day or dropoff points fields to the order's metadata.
	 *
	 * @param \WC_Order        $order   Order object.
	 * @param \WP_REST_Request $request REST request object.
	 */
	public function save_postnl_checkout_fields( \WC_Order $order, \WP_REST_Request $request ) {

		// Check if 'extensions' and 'postnl' data exist in the request
		if ( ! isset( $request['extensions'][ $this->name ] ) ) {
			return;
		}

		$postnl_request_data = $request['extensions'][ $this->name ];

		// Extract billing and shipping house numbers with sanitization
		$billing_house_number  = isset( $postnl_request_data['billingHouseNumber'] ) ? sanitize_text_field( $postnl_request_data['billingHouseNumber'] ) : '';
		$shipping_house_number = isset( $postnl_request_data['shippingHouseNumber'] ) ? sanitize_text_field( $postnl_request_data['shippingHouseNumber'] ) : '';

		// Update billing and shipping house numbers
		$order->update_meta_data( '_billing_house_number', $billing_house_number );
		$order->update_meta_data( '_shipping_house_number', $shipping_house_number );

		/**
		 * Prepare Dropoff Points Data
		 */
		$drop_data = [
			'frontend' => [
				// Dropoff Points Data
				'dropoff_points' => isset( $postnl_request_data['dropoffPoints'] ) ? sanitize_text_field( $postnl_request_data['dropoffPoints'] ) : '',
				'dropoff_points_address_company' => isset( $postnl_request_data['dropoffPointsAddressCompany'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddressCompany'] ) : '',
				'dropoff_points_distance' => isset( $postnl_request_data['dropoffPointsDistance'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDistance'] ) : '',
				'dropoff_points_address_address_1' => isset( $postnl_request_data['dropoffPointsAddress1'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress1'] ) : '',
				'dropoff_points_address_address_2' => isset( $postnl_request_data['dropoffPointsAddress2'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress2'] ) : '',
				'dropoff_points_address_city' => isset( $postnl_request_data['dropoffPointsCity'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCity'] ) : '',
				'dropoff_points_address_postcode' => isset( $postnl_request_data['dropoffPointsPostcode'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPostcode'] ) : '',
				'dropoff_points_address_country' => isset( $postnl_request_data['dropoffPointsCountry'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCountry'] ) : '',
				'dropoff_points_partner_id' => isset( $postnl_request_data['dropoffPointsPartnerID'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPartnerID'] ) : '',
				'dropoff_points_date' => isset( $postnl_request_data['dropoffPointsDate'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDate'] ) : '',
				'dropoff_points_time' => isset( $postnl_request_data['dropoffPointsTime'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsTime'] ) : '',
				'dropoff_points_type' => isset( $postnl_request_data['dropoffPointsType'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsType'] ) : '',
			],
		];

		/**
		 * Prepare Delivery Day Data
		 */
		$delivery_day_data = [
			'frontend' => [
				'delivery_day'         => isset( $postnl_request_data['deliveryDay'] ) ? sanitize_text_field( $postnl_request_data['deliveryDay'] ) : '',
				'delivery_day_date'    => isset( $postnl_request_data['deliveryDayDate'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayDate'] ) : '',
				'delivery_day_from'    => isset( $postnl_request_data['deliveryDayFrom'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayFrom'] ) : '',
				'delivery_day_to'      => isset( $postnl_request_data['deliveryDayTo'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayTo'] ) : '',
				'delivery_day_price'   => isset( $postnl_request_data['deliveryDayPrice'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayPrice'] ) : '',
				'delivery_day_type'    => isset( $postnl_request_data['deliveryDayType'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayType'] ) : '',
			],
		];


		if ( ! empty( $drop_data['frontend']['dropoff_points'] ) ) {
			// Save Dropoff Points Data
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
