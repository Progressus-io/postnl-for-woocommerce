<?php
/**
 * Class Frontend/Container file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Checkout;
use PostNLWooCommerce\Utils;

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
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * Template file name.
	 *
	 * @var string
	 */
	public $template_file;

	/**
	 * Tab field name.
	 *
	 * @var tab_field
	 */
	protected $tab_field = POSTNL_SETTINGS_ID . '_option';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->settings = Settings::get_instance();

		$this->set_template_file();
		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'display_fields' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ), 10, 1 );
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
	 * @param array $response Response from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_available_tabs( $response ) {
		return apply_filters( 'postnl_frontend_checkout_tab', array(), $response );
	}

	/**
	 * Get tab field value.
	 *
	 * @param array $post_data Array of global _POST data.
	 *
	 * @return String
	 */
	public function get_tab_field_value( $post_data ) {
		return ( ! empty( $post_data[ $this->tab_field ] ) ) ? $post_data[ $this->tab_field ] : '';
	}

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return String
	 */
	public function get_current_time() {
		return gmdate( 'd-m-Y H:i:s' );
	}

	/**
	 * Compose label args.
	 *
	 * @param Array $post_data Saved post data and order data.
	 *
	 * @return Array
	 */
	public function get_api_args( $post_data ) {
		$args      = array();
		$post_data = Utils::set_post_data_address( $post_data );

		$args['current_time'] = $this->get_current_time();

		$args['shipping_address'] = array(
			'first_name' => ( ! empty( $post_data['shipping_first_name'] ) ) ? $post_data['shipping_first_name'] : '',
			'last_name'  => ( ! empty( $post_data['shipping_last_name'] ) ) ? $post_data['shipping_last_name'] : '',
			'company'    => ( ! empty( $post_data['shipping_company'] ) ) ? $post_data['shipping_company'] : '',
			'address_1'  => ( ! empty( $post_data['shipping_address_1'] ) ) ? $post_data['shipping_address_1'] : '',
			'address_2'  => ( ! empty( $post_data['shipping_address_2'] ) ) ? $post_data['shipping_address_2'] : '',
			'city'       => ( ! empty( $post_data['shipping_city'] ) ) ? $post_data['shipping_city'] : '',
			'state'      => ( ! empty( $post_data['shipping_state'] ) ) ? $post_data['shipping_state'] : '',
			'country'    => ( ! empty( $post_data['shipping_country'] ) ) ? $post_data['shipping_country'] : '',
			'postcode'   => ( ! empty( $post_data['shipping_postcode'] ) ) ? $post_data['shipping_postcode'] : '',
		);

		$args['store_address'] = array(
			'company'   => get_bloginfo( 'name' ),
			'email'     => get_bloginfo( 'admin_email' ),
			'address_1' => WC()->countries->get_base_address(),
			'address_2' => WC()->countries->get_base_address_2(),
			'city'      => WC()->countries->get_base_city(),
			'state'     => WC()->countries->get_base_state(),
			'country'   => WC()->countries->get_base_country(),
			'postcode'  => WC()->countries->get_base_postcode(),
		);

		$args['settings'] = array(
			'location_code'            => $this->settings->get_location_code(),
			'customer_code'            => $this->settings->get_customer_code(),
			'customer_num'             => $this->settings->get_customer_num(),
			'cut_off_time'             => $this->settings->get_cut_off_time(),
			'dropoff_days'             => $this->settings->get_dropoff_days(),
			'pickup_points_enabled'    => $this->settings->is_pickup_points_enabled(),
			'delivery_days_enabled'    => $this->settings->is_delivery_days_enabled(),
			'evening_delivery_enabled' => $this->settings->is_evening_delivery_enabled(),
			'transit_time'             => $this->settings->get_transit_time(),
			/* Temporarily hardcoded in Settings::get_number_pickup_points(). */
			'number_pickup_points'     => $this->settings->get_number_pickup_points(),
			'number_delivery_days'     => $this->settings->get_number_delivery_days(),
		);

		return $args;
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

	/**
	 * Get data from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_checkout_data() {
		try {
			$post_data = $this->get_checkout_post_data();

			if ( empty( $post_data ) ) {
				return array();
			}

			foreach ( $post_data as $post_key => $post_value ) {
				if ( false !== strpos( $post_key, 'shipping_method' ) && false === strpos( $post_value, POSTNL_SETTINGS_ID ) ) {
					return array();
				}
			}

			$api_args = $this->get_api_args( $post_data );
			$api_call = new Checkout( $api_args );
			$response = $api_call->send_request();

			return array(
				'response'  => json_decode( $response, true ),
				'post_data' => $post_data,
			);
		} catch ( \Exception $e ) {
			return array(
				'response' => array(),
				'error'    => $e->getMessage(),
			);
		}
	}

	/**
	 * Add delivery day fields.
	 */
	public function display_fields() {
		$checkout_data = $this->get_checkout_data();

		if ( empty( $checkout_data['response'] ) ) {
			return;
		}

		$template_args = array(
			'tabs'      => $this->get_available_tabs( $checkout_data['response'] ),
			'response'  => $checkout_data['response'],
			'post_data' => $checkout_data['post_data'],
			'fields'    => array(
				array(
					'name'  => $this->tab_field,
					'value' => $this->get_tab_field_value( $checkout_data['post_data'] ),
				),
			),
		);

		wc_get_template( $this->template_file, $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}

	/**
	 * Add cart fees.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function add_cart_fees( $cart ) {
		$post_data = $this->get_checkout_post_data();

		if ( empty( $post_data ) ) {
			return;
		}

		if ( ! empty( $post_data['postnl_delivery_day_price'] ) ) {
			$cart->add_fee( __( 'PostNL Evening Fee', 'dhl-for-woocommerce' ), wc_format_decimal( $post_data['postnl_delivery_day_price'] ) );
		}
	}
}
