<?php
/**
 * Class Order\Bulk file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Bulk
 *
 * @package PostNLWooCommerce\Order
 */
class Bulk extends Base {

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_order_bulk_actions' ), 10, 1 );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'process_order_bulk_actions' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_assets' ) );
		add_action( 'admin_footer', array( $this, 'model_content_fields_create_label' ) );
		add_filter( 'postnl_order_meta_box_fields', array( $this, 'additional_meta_box' ), 10, 1 );
	}

	/**
	 * Add new bulk actions.
	 *
	 * @param array $bulk_actions List of bulk actions.
	 *
	 * @return array
	 */
	public function add_order_bulk_actions( $bulk_actions ) {
		$bulk_actions['postnl-create-label'] = esc_html__( 'PostNL Create Label', 'postnl-for-woocommerce' );

		return $bulk_actions;
	}

	/**
	 * Process PostNL in bulk.
	 *
	 * @param String $redirect Redirect URL after the bulk has been processed.
	 * @param String $doaction The chosen action.
	 * @param array  $object_ids All chose IDs.
	 *
	 * @return string
	 */
	public function process_order_bulk_actions( $redirect, $doaction, $object_ids ) {
		if ( 'postnl-create-label' !== $doaction ) {
			return $redirect;
		}

		if ( ! empty( $object_ids ) ) {
			foreach ( $object_ids as $order_id ) {
				$this->save_meta_value( $order_id, $_REQUEST );
			}
		}

		return $redirect;
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function enqueue_bulk_assets() {
		$screen = get_current_screen();

		if ( ! empty( $screen->id ) && 'edit-shop_order' === $screen->id && ! empty( $screen->base ) && 'edit' === $screen->base ) {
			// Enqueue the assets.
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'thickbox' );

			wp_enqueue_style(
				'postnl-admin-order-bulk',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-order-bulk.css',
				array(),
				POSTNL_WC_VERSION
			);

			wp_enqueue_script(
				'postnl-admin-order-bulk',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-order-bulk.js',
				array( 'thickbox' ),
				POSTNL_WC_VERSION,
				true
			);
		}
	}

	/**
	 * Adding additional meta box in order admin page.
	 *
	 * @param array $fields List of fields for order admin page.
	 */
	public function additional_meta_box( $fields ) {
		$screen = get_current_screen();

		if ( empty( $screen ) ) {
			return $fields;
		}

		if ( 'edit' === $screen->base && 'shop_order' === $screen->post_type && $screen->in_admin() ) {
			return array_filter(
				$fields,
				function( $field ) {
					return ( $this->prefix . 'delivery_type' !== $field['id'] );
				}
			);
		}

		return $fields;
	}

	/**
	 * Collection of fields in create label bulk action.
	 */
	public function model_content_fields_create_label() {
		global $pagenow, $typenow, $thepostid, $post;

		// Bugfix, warnings shown for Order table results with no Orders.
		if ( empty( $thepostid ) && empty( $post ) ) {
			return;
		}

		if ( 'shop_order' === $typenow && 'edit.php' === $pagenow ) {
			?>
			<div id="postnl-create-label-modal" style="display:none;">
				<div id="postnl-action-create-label">
					<?php $this->fields_generator( $this->meta_box_fields() ); ?>

					<br>
					<button type="button" class="button button-primary" id="postnl_create_label_proceed"><?php esc_html_e( 'Submit', 'postnl-for-woocommerce' ); ?></button>
				</div>
			</div>
			<?php
		}
	}
}
