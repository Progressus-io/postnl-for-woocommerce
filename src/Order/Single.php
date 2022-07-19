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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_single_css_script' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20 );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_meta_box' ), 0, 2 );

		add_action( 'wp_ajax_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );
		add_action( 'wp_ajax_nopriv_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );
	}

	/**
	 * Enqueue CSS file in order detail page.
	 */
	public function enqueue_order_single_css_script() {
		wp_enqueue_style( 'postnl-admin-order-single', POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-order-single.css', array(), POSTNL_WC_VERSION );

		wp_enqueue_script(
			'postnl-admin-order-single',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-order-single.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);
		wp_localize_script(
			'postnl-admin-order-single',
			'postnl_admin_order_obj',
			array(
				'prefix' => $this->prefix,
				'fields' => array_map(
					function( $meta_field ) {
						return empty( $meta_field['id'] ) ? '' : $meta_field['id'];
					},
					$this->meta_box_fields(),
				),
			)
		);
	}

	/**
	 * Adding meta box in order admin page.
	 */
	public function add_meta_box() {
		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', sprintf( __( '%s Label & Tracking', 'postnl-for-woocommerce' ), $this->service ), array( $this, 'meta_box_html' ), 'shop_order', 'side', 'high' );
	}

	/**
	 * Additional fields of the meta box for child class.
	 */
	public function meta_box_html() {
		?>
		<div id="shipment-postnl-label-form">
			<?php $this->fields_generator( $this->meta_box_fields() ); ?>

			<div class="button-container">
				<button class="button button-primary button-save-form"><?php esc_html_e( 'Generate Label', 'postnl-for-woocommerce' ); ?></button>
				<a class="button button-secondary delete-label" href="#"><?php esc_html_e( 'Delete Label', 'postnl-for-woocommerce' ); ?></a>
			</div>
		</div>
		<?php
	}

	/**
	 * Saving meta box in order admin page using ajax.
	 */
	public function save_meta_box_ajax() {
		try {
			$saved_data = $this->save_meta_box( false );
			wp_send_json_success( $saved_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}
}
