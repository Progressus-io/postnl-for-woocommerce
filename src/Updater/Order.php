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

use PostNLWooCommerce\Utils;

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
		//add_action( 'wp_footer', array( $this, 'update_existing_orders' ) );
	}

	public function update_existing_orders() {
		$orders = wc_get_orders(
			array(
				'postnl_meta_exists'             => '1',
				'postnl_has_single_deliverydate' => 'no',
				'limit'                          => -1,
				'return'                         => 'ids',
			)
		);

		if ( ! empty( $orders ) ) {
			$postnl_instance   = \PostNLWooCommerce\postnl();
			$postnl_frontend   = $postnl_instance->get_frontend();
			$postnl_orderslist = $postnl_instance->get_orders_list();

			foreach ( $orders as $order_id ) {

				$order = wc_get_order( $order_id );

				$fields_saved = false;

				foreach ( $postnl_frontend['delivery_day']->get_fields() as $field ) {
					if ( ! isset( $field['single'] ) || true !== $field['single'] ) {
						continue;
					}

					$field_name    = Utils::remove_prefix_field( $this->prefix, $field['id'] );
					$delivery_date = $postnl_orderslist->get_order_frontend_info( $order, $field_name );

					if ( ! empty( $delivery_date[ $field_name ] ) ) {
						$field_value = $delivery_date[ $field_name ];
					} else {
						$field_value = '';
					}

					$fields_saved = true;
					$order->update_meta_data( '_' . $this->prefix . 'frontend_' . $field_name, $field_value );
				}

				if ( $fields_saved ) {
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
