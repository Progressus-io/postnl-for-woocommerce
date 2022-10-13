<?php
/**
 * Class Order\OrdersList file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;

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
		// add 'Label Created' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_barcode_column_header' ), 30 );

		// add 'Label Created' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_barcode_column_content' ) );
	}

	public function add_order_barcode_column_header( $columns ) {

		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );
		$columns['postnl_tracking_link'] = esc_html__( 'PostNL Tracking', 'postnl-for-woocommerce' );
		$columns['wc_actions']           = $wc_actions;

		return $columns;
	}

	public function add_order_barcode_column_content( $column ) {
		global $post, $theorder;

		$order_id = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
			? $theorder->get_id()
			: $post->ID;

		if ( $order_id ) {
			if ( 'postnl_tracking_link' === $column ) {
				echo $this->get_tracking_link( $order_id );
			}
		}
	}
}