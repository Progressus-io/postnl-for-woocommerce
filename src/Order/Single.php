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
class Single extends Base {
	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_single_css' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_meta_box' ), 0, 2 );
		add_filter( 'postnl_order_meta_box_fields', array( $this, 'additional_meta_box' ), 10, 1 );
	}

	/**
	 * Enqueue CSS file in order detail page.
	 */
	public function enqueue_order_single_css() {
		wp_enqueue_style( 'postnl-admin-order-single', POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-order-single.css', array(), POSTNL_WC_VERSION );
	}

	/**
	 * Adding meta box in order admin page.
	 */
	public function add_meta_box() {
		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', sprintf( __( '%s Label & Tracking', 'postnl-for-woocommerce' ), $this->service ), array( $this, 'meta_box_html' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Adding additional meta box in order admin page.
	 *
	 * @param array $fields List of fields for order admin page.
	 */
	public function additional_meta_box( $fields ) {
		$screen = get_current_screen();

		if ( $screen->in_admin() && 'post' === $screen->base && 'shop_order' === $screen->post_type ) {
			$new_fields = array();

			foreach ( $fields as $field ) {
				$new_fields[] = $field;
				if ( 'postnl_label_nonce' === $field['id'] ) {
					$new_fields[] = array(
						'id'                => 'postnl_delivery_type',
						'type'              => 'text',
						'label'             => __( 'Delivery Type:', 'postnl-for-woocommerce' ),
						'description'       => '',
						'class'             => 'long',
						'value'             => 'Standard',
						'custom_attributes' => array( 'readonly' => 'readonly' ),
						'container'         => true,
					);
				}
			}

			return $new_fields;
		}

		return $fields;
	}

	/**
	 * Additional fields of the meta box for child class.
	 */
	public function meta_box_html() {
		?>
		<div id="shipment-postnl-label-form">
			<?php $this->fields_generator( $this->meta_box_fields() ); ?>

			<div class="button-container">
				<button class="button button-primary button-save-form"><?php esc_html_e( 'Save Shipment', 'postnl-for-woocommerce' ); ?></button>
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
