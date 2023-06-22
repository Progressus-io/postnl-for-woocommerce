<?php
/**
 * Class Order\OrdersList file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk
 *
 * @package PostNLWooCommerce\Order
 */
class OrdersList extends Base {

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		// add 'Delivery Date' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_delivery_date_column_header' ), 29 );

		// add 'Delivery Date' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_delivery_date_column_content' ), 10, 2 );

		// add 'Delivery Date' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_shipping_options_column_header' ), 30 );

		// add 'Delivery Date' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_shipping_options_column_content' ), 10, 2 );

		// add 'Label Created' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_barcode_column_header' ), 31 );

		// add 'Label Created' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_barcode_column_content' ), 10, 2 );
	}

	public function add_order_barcode_column_header( $columns ) {

		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );
		$columns['postnl_tracking_link'] = esc_html__( 'PostNL Tracking', 'postnl-for-woocommerce' );
		$columns['wc_actions']           = $wc_actions;

		return $columns;
	}

	public function add_order_barcode_column_content( $column, $order_id ) {

		if ( $order_id ) {
			if ( 'postnl_tracking_link' === $column ) {
				echo $this->get_tracking_link( $order_id );
			}
		}
	}

	/**
	 * @param $columns  .
	 *
	 * @return array.
	 */
	public function add_order_delivery_date_column_header( $columns ) {

		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );
		$columns['postnl_delivery_date'] = esc_html__( 'Delivery Date', 'postnl-for-woocommerce' );
		$columns['wc_actions']           = $wc_actions;

		return $columns;
	}

	/**
	 * Generate column content.
	 *
	 * @param $column  .
	 * @param $order_id  .
	 *
	 * @return void.
	 */
	public function add_order_delivery_date_column_content( $column, $order_id ) {
		if ( $order_id ) {
			if ( 'postnl_delivery_date' === $column ) {
				$order = wc_get_order( $order_id );

				if ( ! is_a( $order, 'WC_Order' ) ) {
					return;
				}

				$delivery_info = $this->get_order_frontend_info( $order, 'delivery_day_date' );

				echo Utils::generate_delivery_date_html( $delivery_info );
			}
		}
	}

	/**
	 * @param $columns  .
	 *
	 * @return array.
	 */
	public function add_order_shipping_options_column_header( $columns ) {

		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );
		$columns['postnl_shipping_options'] = esc_html__( 'Shipping options', 'postnl-for-woocommerce' );
		$columns['wc_actions']              = $wc_actions;

		return $columns;
	}

	/**
	 * Generate column content.
	 *
	 * @param $column  .
	 * @param $order_id  .
	 *
	 * @return void.
	 */
	public function add_order_shipping_options_column_content( $column, $order_id ) {
		if ( empty( $order_id ) ) {
		     return;
		}
	
		if ( 'postnl_shipping_options' === $column ) {
			$backend_data = $this->get_backend_data( $order_id );

			echo Utils::generate_shipping_options_html( $backend_data );
		}
	}
}