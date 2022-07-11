<?php
/**
 * Class Order\Single file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Single
 *
 * @package PostNLWooCommerce\Order
 */
class Single {
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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_detail_css' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_meta_box' ), 0, 2 );
	}

	/**
	 * Enqueue CSS file in order detail page.
	 */
	public function enqueue_order_detail_css() {
		wp_enqueue_style( 'postnl_order_detail', POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-postnl-order-detail.css', array(), POSTNL_WC_VERSION );
	}

	/**
	 * Adding meta box in order admin page.
	 */
	public function add_meta_box() {
		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', sprintf( __( '%s Label & Tracking', 'postnl-for-woocommerce' ), $this->service ), array( $this, 'meta_box' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Fields of the meta box.
	 */
	public function meta_box() {

		$this->additional_meta_box();
	}

	/**
	 * Additional fields of the meta box for child class.
	 */
	public function additional_meta_box() {
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
						'id'                => 'postnl_num_labels',
						'label'             => __( 'Number of Labels: ', 'postnl-for-woocommerce' ),
						'placeholder'       => '',
						'description'       => '',
						'class'             => 'short',
						'value'             => '',
						'custom_attributes' => array(
							'step' => 'any',
							'min'  => '0',
						),
						'type'              => 'number',
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

	/**
	 * Saving meta box in order admin page.
	 *
	 * @param int     $post_id Order post ID.
	 * @param WP_Post $post Order post object.
	 */
	public function save_meta_box( $post_id, $post = null ) {
		// Loop through inputs within id 'shipment-postnl-label-form'.
	}
}
