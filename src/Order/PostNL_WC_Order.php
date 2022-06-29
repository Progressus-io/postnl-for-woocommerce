<?php
/**
 * Class Order\PostNL file.
 *
 * @package Progressus\PostNLWooCommerce\Order
 */

namespace Progressus\PostNLWooCommerce\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PostNL_WC_Order
 *
 * @package Progressus\PostNLWooCommerce\Order
 */
abstract class PostNL_WC_Order {
	/**
	 * Saved shipping settings.
	 *
	 * @var shipping_settings
	 */
	protected $shipping_settings = array();

	/**
	 * Current service.
	 *
	 * @var service
	 */
	protected $service = 'PostNL';

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_meta_box' ), 0, 2 );
	}

	/**
	 * Adding meta box in order admin page.
	 */
	public function add_meta_box() {
		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-dhl-label', sprintf( __( '%s Label & Tracking', 'postnl-for-woocommerce' ), $this->service ), array( $this, 'meta_box' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Fields of the meta box.
	 */
	public function meta_box() {

	}

	/**
	 * Saving meta box in order admin page.
	 *
	 * @param int     $post_id Order post ID.
	 * @param WP_Post $post Order post object.
	 */
	public function save_meta_box( $post_id, $post = null ) {
		// Loop through inputs within id 'shipment-dhl-label-form'.
	}
}
