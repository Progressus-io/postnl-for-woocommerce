<?php
/**
 * Class Order file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order
 *
 * @package PostNLWooCommerce
 */
class Order {
	/**
	 * Order class constructor.
	 *
	 * @return void
	 */
	public function __construct() {

		$this->init_hooks();
	}

	/**
	 * First method to be called.
	 *
	 * @return void
	 */
	public function init_hooks() {
		add_shortcode( 'mc_order_num', array( $this, 'shortcode_order_number' ) );
		add_action( 'woocommerce_order_actions', array( $this, 'add_order_meta_box_action' ) );
		add_action( 'woocommerce_order_action_wc_populate_customer_info', array( $this, 'process_populate_customer_info_action' ) );
		add_filter( 'woocommerce_display_item_meta', array( $this, 'customize_order_item_meta_display' ), 10, 3 );
	}

	/**
	 * Add a custom action to order actions select box on edit order page
	 * Only added for paid orders that haven't fired this action yet
	 *
	 * @param array $actions order actions array to display.
	 * @return array - updated actions
	 */
	public function add_order_meta_box_action( $actions ) {
		// Add "mark printed" custom action.
		$actions['wc_populate_customer_info'] = __( 'Populate customer info', 'postnl-for-woocommerce' );
		return $actions;
	}
}

// "default" should be in the end of id.
// remove "pr_"
// remove "postnl_" 
// use postnl from the ID.
// remove Progresus/ from PostNL
// Check the number field.