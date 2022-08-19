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
	 * @param array $response Response from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_available_tabs( $response ) {
		return apply_filters( 'postnl_frontend_checkout_tab', array(), $response );
	}

	/**
	 * Get data from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_checkout_data() {
		if ( empty( $_REQUEST['post_data'] ) ) {
			return array();
		}

		$post_data = array();

		if ( isset( $_REQUEST['post_data'] ) ) {
			parse_str( sanitize_text_field( wp_unslash( $_REQUEST['post_data'] ) ), $post_data );
		}

		$api_call = new Checkout( $post_data );
		$response = $api_call->send_request();

		return array(
			'response'  => json_decode( $response, true ),
			'post_data' => $post_data,
		);
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
		);

		wc_get_template( $this->template_file, $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}
}
