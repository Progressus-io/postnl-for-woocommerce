<?php
/**
 * Class Frontend/Container file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Checkout;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delivery_Day
 *
 * @package PostNLWooCommerce\Frontend
 */
class Container {
	/**
	 * Template file name.
	 *
	 * @var string
	 */
	public $template_file;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->set_template_file();
		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'display_fields' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );
	}

	/**
	 * Enqueue scripts and style.
	 */
	public function enqueue_scripts_styles() {
		// Enqueue styles.
		wp_enqueue_style(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/fe-checkout.css',
			array(),
			POSTNL_WC_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/fe-checkout.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);
	}

	/**
	 * Set the template filename.
	 */
	public function set_template_file() {
		$this->template_file = 'checkout/postnl-container.php';
	}

	/**
	 * Get enabled tabs.
	 *
	 * @return array
	 */
	public function get_available_tabs() {
		return apply_filters( 'postnl_frontend_checkout_tab', array() );
	}

	/**
	 * Get delivery option value from the API response.
	 *
	 * @param array $response PostNL API response.
	 *
	 * @return array.
	 */
	public function get_delivery_options( $response ) {
		if ( empty( $response['DeliveryOptions'] ) ) {
			return array();
		}

		$delivery_options = array();

		foreach ( $response['DeliveryOptions'] as $delivery_option ) {
			if ( empty( $delivery_option['DeliveryDate'] ) || empty( $delivery_option['Timeframe'] ) ) {
				continue;
			}

			$options = array_map(
				function( $timeframe ) {
					return array(
						'from' => $timeframe['From'],
						'to'   => $timeframe['To'],
						'type' => array_shift( $timeframe['Options'] ),
					);
				},
				$delivery_option['Timeframe']
			);

			$timestamp = strtotime( $delivery_option['DeliveryDate'] );

			$delivery_options[] = array(
				'day'     => gmdate( 'l', $timestamp ),
				'date'    => gmdate( 'Y-m-d', $timestamp ),
				'options' => $options,
			);
		}

		return $delivery_options;
	}

	/**
	 * Get dropoff points value from the API response.
	 *
	 * @param array $response PostNL API response.
	 *
	 * @return array.
	 */
	public function get_dropoff_points( $response ) {
		if ( empty( $response['PickupOptions'] ) ) {
			return array();
		}

		$pickup_points = array_filter(
			$response['PickupOptions'],
			function ( $pickup_point ) {
				return ( ! empty( $pickup_point['Option'] ) && 'Pickup' === $pickup_point['Option'] );
			}
		);
		$pickup_point  = array_shift( $pickup_points );
		$date          = ! empty( $pickup_point['PickupDate'] ) ? $pickup_point['PickupDate'] : '';

		if ( empty( $pickup_point['Locations'] ) ) {
			return array();
		}

		$dropoff_options = array();

		foreach ( $pickup_point['Locations'] as $dropoff_option ) {
			if ( empty( $dropoff_option['PartnerID'] ) || empty( $dropoff_option['PickupTime'] ) || empty( $dropoff_option['Distance'] ) || empty( $dropoff_option['Address'] ) ) {
				continue;
			}

			$timestamp = strtotime( $date );
			$company   = $dropoff_option['PartnerID']['CompanyName'];
			$address   = implode( ', ', array_values( $dropoff_option['Address'] ) );

			$dropoff_options[] = array(
				'partner_id' => $dropoff_option['PartnerID'],
				'time'       => $dropoff_option['PickupTime'],
				'distance'   => $dropoff_option['Distance'],
				'date'       => $date,
				'company'    => $company,
				'address'    => $address,
			);
		}

		return $dropoff_options;
	}

	/**
	 * Add delivery day fields.
	 */
	public function display_fields() {
		$response = Checkout::send_request();
		$response = json_decode( $response, true );

		$template_args = array(
			'tabs'           => $this->get_available_tabs(),
			'deliveries'     => $this->get_delivery_options( $response ),
			'dropoff_points' => $this->get_dropoff_points( $response ),
		);

		wc_get_template( $this->template_file, $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}
}
