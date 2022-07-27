<?php
/**
 * Class Frontend/Delivery_Day file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delivery_Day
 *
 * @package PostNLWooCommerce\Frontend
 */
class Delivery_Day {
	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'add_fields' ) );
		add_filter( 'woocommerce_checkout_posted_data', array( $this, 'validate_fields' ) );
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_data' ), 10, 2 );
	}

	/**
	 * Add delivery day fields.
	 */
	public function add_fields() {
		$template_args = array();
		wc_get_template( 'checkout/postnl-delivery-day.php', $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}

	/**
	 * Validate delivery day fields.
	 *
	 * @param array $data Array of posted data.
	 */
	public function validate_fields( $data ) {
		$nonce_value    = wc_get_var( $_REQUEST['woocommerce-process-checkout-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // phpcs:ignore
		$expiry_message = sprintf(
			/* translators: %s: shop cart url */
			__( 'Sorry, your session has expired. <a href="%s" class="wc-backward">Return to shop</a>', 'woocommerce' ),
			esc_url( wc_get_page_permalink( 'shop' ) )
		);

		if ( empty( $nonce_value ) || ! wp_verify_nonce( $nonce_value, 'woocommerce-process_checkout' ) ) {
			return $data;
		}

		if ( empty( $_POST['postnl_delivery_day'] ) ) {
			wc_add_notice( __( 'Please choose the delivery day!', 'postnl-for-woocommerce' ), 'error' );
			return $data;
		}

		$data['postnl_delivery_day'] = sanitize_text_field( wp_unslash( $_POST['postnl_delivery_day'] ) );

		return $data;
	}

	/**
	 * Save delivery day value to meta.
	 *
	 * @param int   $order_id ID of the order.
	 * @param array $posted_data Posted values.
	 */
	public function save_data( $order_id, $posted_data ) {

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		foreach ( $posted_data as $key => $value ) {
			if ( false !== strpos( $key, 'postnl_' ) ) {
				$order->update_meta_data( $key, $value );
			}
		}
		$order->save();
	}
}
