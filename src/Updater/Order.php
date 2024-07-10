<?php
/**
 * Class Updater/Order file.
 *
 * @package PostNLWooCommerce\Updater
 */

namespace PostNLWooCommerce\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Order
 *
 * @package PostNLWooCommerce\Updater
 */
class Order {

	/**
	 * Prefix for meta box fields.
	 *
	 * @var prefix
	 */
	protected $prefix = POSTNL_SETTINGS_ID . '_';

	/**
	 * Primary field name.
	 *
	 * @var primary_field
	 */
	protected $primary_field;

	/**
	 * Prefix for meta box fields.
	 *
	 * @var meta_name
	 */
	protected $meta_name;

	public function __construct() {
		$this->meta_name = '_' . $this->prefix . 'order_metadata';
		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', array( $this, 'handle_custom_query_variable' ), 10, 2 );

        if ( ! get_transient( 'updated_postnl_orders' ) ) {
            $this->update_existing_orders();

            set_transient( 'updated_postnl_orders', 'done', YEAR_IN_SECONDS );
        }
	}

	public function update_existing_orders() {
		// Query orders that have _postnl_order_metadata but do not have _postnl_old_orders_delivery_date
		$orders = wc_get_orders(
			array(
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => $this->meta_name,
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_postnl_old_orders_delivery_date',
						'compare' => 'NOT EXISTS',
					)
				),
				'limit'      => - 1,
				'return'     => 'ids',
				'status'     => array( 'on-hold', 'pending' ),
			)
		);

		if ( ! empty( $orders ) ) {
			foreach ( $orders as $order_id ) {
				$order       = wc_get_order( $order_id );
				$postnl_meta = $order->get_meta( $this->meta_name );

				// Extract the delivery_day_date and save it under new_postnl_delivery_date
				if ( isset( $postnl_meta['frontend']['delivery_day_date'] ) ) {
					$delivery_date = $postnl_meta['frontend']['delivery_day_date'];
					$order->update_meta_data( '_postnl_old_orders_delivery_date', $delivery_date );
					$order->save();
				}
			}
		}
	}

	/**
	 * Handle a custom 'postnl_ordermeta' query var to get orders with the '_postnl_order_metadata' meta.
	 *
	 * @param array $query - Args for WP_Query.
	 * @param array $query_vars - Query vars from WC_Order_Query.
	 *
	 * @return array modified $query.
	 */
	public function handle_custom_query_variable( $query, $query_vars ) {
		if ( ! empty( $query_vars['postnl_meta_exists'] ) ) {
			$query['meta_query'][] = array(
				'key'     => $this->meta_name,
				'compare' => 'EXISTS',
			);
		}

		if ( ! empty( $query_vars['postnl_has_single_deliverydate'] ) ) {
			$has_single_value = esc_attr( $query_vars['postnl_has_single_deliverydate'] );
			
			$query['meta_query'][] = array(
				'key'     => '_' . $this->prefix . 'frontend_delivery_day_date',
				'compare' => ( 'yes' === $has_single_value ) ? 'EXISTS' : 'NOT EXISTS',
			);
		}
	
		return $query;
	}
}
