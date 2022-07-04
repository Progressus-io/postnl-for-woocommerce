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
class Bulk {
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

					<?php $this->meta_box_fields(); ?>

					<br>
					<button type="button" class="button button-primary" id="postnl_create_label_proceed"><?php esc_html_e( 'Submit', 'postnl-for-woocommerce' ); ?></button>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Additional fields of the meta box for child class.
	 */
	public function meta_box_fields() {
		woocommerce_wp_hidden_input(
			array(
				'id'    => 'postnl_label_nonce',
				'value' => wp_create_nonce( 'create-postnl-label' ),
			)
		);
		?>
		<div id="shipment-postnl-label-form">
			<div class="shipment-postnl-row-container shipment-postnl-row-delivery-type">
			<?php
				woocommerce_wp_select(
					array(
						'id'          => 'postnl_delivery_type',
						'label'       => __( 'Delivery Type:', 'postnl-for-woocommerce' ),
						'description' => '',
						'value'       => '',
						'options'     => array(
							'standard' => esc_html__( 'Standard', 'postnl-for-woocommerce' ),
							'evening'  => esc_html__( 'Evening', 'postnl-for-woocommerce' ),
						),
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-insured-shipping">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_insured_shipping',
						'label'       => __( 'Insured Shipping: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-return-no-answer">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_return_no_answer',
						'label'       => __( 'Return if no answer: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-signature-on-delivery">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_signature_on_delivery',
						'label'       => __( 'Signature on Delivery: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-only-home-address">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_only_home_address',
						'label'       => __( 'Only Home Address: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-row-num-labels">
			<?php
				woocommerce_wp_text_input(
					array(
						'id'          => 'postnl_num_labels',
						'label'       => __( 'Number of Labels: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'class'       => 'short',
						'value'       => '',
					)
				);
			?>
			</div>

			<div class="shipment-postnl-row-container shipment-postnl-create-return-label">
			<?php
				woocommerce_wp_checkbox(
					array(
						'id'          => 'postnl_create_return_label',
						'label'       => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
						'placeholder' => '',
						'description' => '',
						'value'       => '',
					)
				);
			?>
			</div>
		</div>
		<?php
	}
}
