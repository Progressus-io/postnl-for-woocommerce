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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_modal_box_assets' ) );
		add_action( 'admin_footer', array( $this, 'model_content_fields_create_label' ) );
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
	 * Collection of hooks when initiation.
	 */
	public function enqueue_modal_box_assets() {
		global $pagenow, $typenow;

		if ( 'shop_order' === $typenow && 'edit.php' === $pagenow ) {
			// Enqueue the assets.
			wp_enqueue_style( 'thickbox' );
			wp_enqueue_script( 'thickbox' );

			wp_enqueue_script(
				'postnl-create-label-bulk',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-postnl-create-label-bulk.js',
				array( 'thickbox' ),
				POSTNL_WC_VERSION,
				true
			);
		}
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

					<?php $this->meta_box_html(); ?>

					<br>
					<button type="button" class="button button-primary" id="postnl_create_label_proceed"><?php esc_html_e( 'Submit', 'postnl-for-woocommerce' ); ?></button>
				</div>
			</div>
			<?php
		}
	}
}
