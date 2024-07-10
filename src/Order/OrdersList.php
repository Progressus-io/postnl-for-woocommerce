<?php
/**
 * Class Order\OrdersList file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class OrdersList
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
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_delivery_date_column_header' ), 29 );

		// add 'Delivery Date' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_delivery_date_column_content' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_order_delivery_date_column_content' ), 10, 2 );

		// add 'Shipping options' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_shipping_options_column_header' ), 30 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_shipping_options_column_header' ), 30 );

		// add 'Shipping options' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_shipping_options_column_content' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_order_shipping_options_column_content' ), 10, 2 );

		// add 'Label Created' orders page column header
		add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_order_barcode_column_header' ), 31 );
		add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_order_barcode_column_header' ), 31 );

		// add 'Label Created' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_order_barcode_column_content' ), 10, 2 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_order_barcode_column_content' ), 10, 2 );

		// Make delivery date column sortable.
		add_filter( 'manage_edit-shop_order_sortable_columns', array( $this, 'sort_delivery_date_column' ) );

		// Make sorting work properly.
		add_action( 'pre_get_posts', array( $this, 'sortable_orderby_delivery_date' ) );

		// Add 'Eligible Auto Letterbox' orders page column header
		if(wc_get_base_location()['country'] != 'BE'){
			add_filter( 'manage_edit-shop_order_columns', array( $this, 'add_eligible_auto_letterbox_column_header' ), 29, 3 );
			add_filter( 'manage_woocommerce_page_wc-orders_columns', array( $this, 'add_eligible_auto_letterbox_column_header' ), 29, 3 );
		}
		// Add 'Eligible Auto Letterbox' orders page column content
		add_action( 'manage_shop_order_posts_custom_column', array( $this, 'add_eligible_auto_letterbox_column_content' ), 10, 3 );
		add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'add_eligible_auto_letterbox_column_content' ), 10, 3 );
	}

	/**
	 * Add barcode column header.
	 *
	 * @param $columns.
	 *
	 * @return array.
	 */
	public function add_order_barcode_column_header( $columns ) {
		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );

		$columns['postnl_tracking_link'] = esc_html__( 'PostNL Tracking', 'postnl-for-woocommerce' );
		$columns['wc_actions']           = $wc_actions;

		return $columns;
	}

	/**
	 * Add barcode column content.
	 *
	 * @param $column.
	 * @param $order_id.
	 *
	 * @return void.
	 */
	public function add_order_barcode_column_content( $column, $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		if ( 'postnl_tracking_link' === $column ) {
			echo $this->get_tracking_link( $order_id );
		}
	}

	/**
	 * Add delivery date column header.
	 *
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
	 * Add delivery date column content.
	 *
	 * @param $column  .
	 * @param $order_id  .
	 *
	 * @return void.
	 */
	public function add_order_delivery_date_column_content( $column, $order_id ) {
		if ( empty( $order_id ) ) {
			return;
		}

		if ( 'postnl_delivery_date' === $column ) {
			$order = wc_get_order( $order_id );

			if ( ! is_a( $order, 'WC_Order' ) ) {
				return;
			}

			if ( Utils::is_eligible_auto_letterbox( $order ) ) {
				esc_html_e( 'As soon as possible', 'postnl-for-woocommerce' );
				return;
			}

			$delivery_info = $this->get_order_frontend_info( $order, 'delivery_day_date' );

			echo Utils::generate_delivery_date_html( $delivery_info );
		}
	}

	/**
	 * Add shipping options column header.
	 *
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
	 * Add shipping options column content.
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
			$shipping_options = $this->get_shipping_options( wc_get_order( $order_id ) );
			echo esc_html( Utils::generate_shipping_options_html( $shipping_options, $order_id ) );
		}
	}

	/**
	 * Make delivery date column sortable.
	 *
	 * @param array $columns Added columns.
	 */
	public function sort_delivery_date_column( $columns ) {
		$meta_key = 'postnl_delivery_date';
		return wp_parse_args( array( 'postnl_delivery_date' => $meta_key ), $columns );
	}

	/**
	 * Modify query to order by delivery date.
	 *
	 * @param \WP_Query $query.
	 *
	 * @return void
	 */
	public function sortable_orderby_delivery_date( $query ) {
		global $pagenow;
	
		if ( 'edit.php' !== $pagenow || ! isset( $_GET['post_type'] ) || 'shop_order' !== $_GET['post_type'] ) {
			return;
		}
	
		$orderby = $query->get( 'orderby' );
		$order = 'ASC' === strtoupper( $query->get('order') ) ? 'ASC' : 'DESC';
	
		if ( 'postnl_delivery_date' === $orderby ) {
			// Only if sorting by delivery date, filter the results to "on-hold" and "pending" statuses
			$query->set( 'post_status', array( 'wc-on-hold', 'wc-pending' ) );
	
			add_filter( 'posts_join', function( $join ) {
				global $wpdb;
				$join .= " LEFT JOIN {$wpdb->postmeta} AS m1 ON {$wpdb->posts}.ID = m1.post_id AND m1.meta_key = '_postnl_old_orders_delivery_date' ";
				$join .= " LEFT JOIN {$wpdb->postmeta} AS m2 ON {$wpdb->posts}.ID = m2.post_id AND m2.meta_key = '_postnl_frontend_delivery_day_date' ";
				return $join;
			});
	
			add_filter( 'posts_orderby', function( $orderby ) use ($order) {
				$orderby = "CASE 
								WHEN m1.meta_key IS NULL AND m2.meta_key IS NULL THEN 0 
								ELSE 1 
							END {$order}, 
							LEAST(IFNULL(m1.meta_value, '2999-12-31'), IFNULL(m2.meta_value, '2999-12-31')) {$order}";
				return $orderby;
			});
		}
	}

	/**
	 * Add eligible auto letterbox column header.
	 *
	 * @param array $columns Order table columns.
	 *
	 * @return array
	 */
	public function add_eligible_auto_letterbox_column_header( $columns ) {
		$wc_actions = $columns['wc_actions'];
		unset( $columns['wc_actions'] );

		$columns['postnl_eligible_auto_letterbox'] = esc_html__( 'Fits through letterbox', 'postnl-for-woocommerce' );
		$columns['wc_actions']           = $wc_actions;

		return $columns;
	}

	/**
	 * Add eligible auto letterbox column content - tick or cross.
	 *
	 * @param string $column order column ID.
	 * @param int $order_id \WC_Order ID.
	 *
	 * @return void
	 */
	public function add_eligible_auto_letterbox_column_content( $column, $order_id ) {
		if ( 'postnl_eligible_auto_letterbox' === $column ) {
			if ( Utils::is_eligible_auto_letterbox( $order_id ) ) {
				?>
				<span class="postnl_eligible_auto_letterbox eligible">&#10003;</span>
				<?php
			} else {
				?>
				<span class="postnl_eligible_auto_letterbox non-eligible">&#215;</span>
				<?php
			}
		}
	}
}
