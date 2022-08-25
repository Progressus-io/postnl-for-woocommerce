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
	 * Tab field name.
	 *
	 * @var tab_field
	 */
	protected $tab_field = POSTNL_SETTINGS_ID . '_option';

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

			$api_call = new Checkout( $post_data );
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
