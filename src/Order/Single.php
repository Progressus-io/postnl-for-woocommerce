<?php
/**
 * Class Order\Single file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use PostNLWooCommerce\Utils;

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

		add_action( 'wp_ajax_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );
		add_action( 'wp_ajax_nopriv_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );

		add_action( 'wp_ajax_postnl_order_delete_data', array( $this, 'delete_meta_data_ajax' ) );
		add_action( 'wp_ajax_nopriv_postnl_order_delete_data', array( $this, 'delete_meta_data_ajax' ) );

		add_action( 'init', array( $this, 'get_label_file' ), 10 );
	}

	/**
	 * Enqueue CSS file in order detail page.
	 */
	public function enqueue_order_single_css_script() {
		$screen = get_current_screen();

		if ( ! empty( $screen->id ) && 'shop_order' === $screen->id && ! empty( $screen->base ) && 'post' === $screen->base ) {
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
	}

	/**
	 * Adding meta box in order admin page.
	 */
	public function add_meta_box() {
		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', sprintf( __( '%s Label & Tracking', 'postnl-for-woocommerce' ), $this->service ), array( $this, 'meta_box_html' ), $screen, 'side', 'high' );
	}

	/**
	 * Add value to meta box fields.
	 *
	 * @param WC_Order $order current order object.
	 *
	 * @return array
	 */
	public function add_meta_box_value( $order ) {
		$meta_fields = $this->meta_box_fields();

		if ( is_a( $order, 'WC_Order' ) ) {
			$order_data = $order->get_meta( $this->meta_name );

			foreach ( $meta_fields as $index => $field ) {
				$field_name = Utils::remove_prefix_field( $this->prefix, $field['id'] );

				if ( ! empty( $order_data['frontend'][ $field_name ] ) ) {
					$meta_fields[ $index ]['value'] = $order_data['frontend'][ $field_name ];
				}

				if ( isset( $order_data['backend'][ $field_name ] ) ) {
					$meta_fields[ $index ]['custom_attributes']['disabled'] = 'disabled';
					$meta_fields[ $index ]['value']                         = $order_data['backend'][ $field_name ];
				}
			}
		}

		return $meta_fields;
	}

	/**
	 * Get dropoff points information.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return Array.
	 */
	public function get_dropoff_points_info( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$order_data = $order->get_meta( $this->meta_name );

		if ( ! empty( $order_data['frontend'] ) ) {
			$dropoff_value = array();

			foreach ( $order_data['frontend'] as $key => $value ) {
				if ( false !== strpos( $key, 'dropoff_points_' ) ) {
					$dropoff_value[ $key ] = $value;
				}
			}

			return $dropoff_value;
		}
	}

	/**
	 * Generate the dropoff points html information.
	 *
	 * @param Array $infos Dropoff points informations.
	 */
	public function generate_dropoff_points_html( $infos ) {
		$filtered_infos = array_filter(
			$infos,
			function ( $info ) {
				$displayed_info = array(
					'dropoff_points_company',
					'dropoff_points_address',
					'dropoff_points_date',
					'dropoff_points_time',
				);

				return in_array( $info, $displayed_info, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( empty( $filtered_infos ) ) {
			return;
		}
		?>
		<label for="postnl_dropoff_points"><?php esc_html_e( 'Dropoff Points:', 'postnl-for-woocommerce' ); ?></label>
		<?php
		foreach ( $filtered_infos as $info_idx => $info_val ) {
			switch ( $info_idx ) {
				case 'dropoff_points_date':
					$additional_text = esc_html__( 'Date:', 'postnl-for-woocommerce' );
					break;

				case 'dropoff_points_time':
					$additional_text = esc_html__( 'Time:', 'postnl-for-woocommerce' );
					break;

				default:
					$additional_text = '';
					break;
			}
			?>
			<div class="postnl-info <?php echo esc_attr( $info_idx ); ?>">
				<?php echo esc_html( $additional_text . ' ' . $info_val ); ?>
			</div>
			<?php
		}
	}
	/**
	 * Additional fields of the meta box for child class.
	 *
	 * @param WP_Post|WC_Order $post_or_order_object current order object.
	 */
	public function meta_box_html( $post_or_order_object ) {
		if ( ! is_a( $post_or_order_object, 'WC_Order' ) && empty( $post_or_order_object->ID ) ) {
			return;
		}

		$order        = ( is_a( $post_or_order_object, 'WP_Post' ) ) ? wc_get_order( $post_or_order_object->ID ) : $post_or_order_object;
		$form_class   = ( $this->have_backend_data( $order ) ) ? 'generated' : '';
		$dropoff_info = $this->get_dropoff_points_info( $order );
		?>
		<div id="shipment-postnl-label-form" class="<?php echo esc_attr( $form_class ); ?>">
			<div class="postnl-info-container dropoff-points-info">
				<?php $this->generate_dropoff_points_html( $dropoff_info ); ?>
			</div>
			<?php $this->fields_generator( $this->add_meta_box_value( $order ) ); ?>

			<div class="button-container">
				<button class="button button-primary button-save-form"><?php esc_html_e( 'Generate Label', 'postnl-for-woocommerce' ); ?></button>
				<a href="<?php echo esc_url( $this->get_download_label_url( $order->get_id() ) ); ?>" class="button button-primary button-download-label"><?php esc_html_e( 'Download Label', 'postnl-for-woocommerce' ); ?></a>
				<a class="button button-secondary delete-label" href="#"><?php esc_html_e( 'Delete Label', 'postnl-for-woocommerce' ); ?></a>
			</div>
			<div id="shipment-postnl-error-text"></div>
		</div>
		<?php
	}

	/**
	 * Additional fields of the meta box for child class.
	 *
	 * @param WC_Order $order current order object.
	 *
	 * @return boolean
	 */
	public function have_backend_data( $order ) {
		$order_data = $order->get_meta( $this->meta_name );

		return ! empty( $order_data['backend'] );
	}

	/**
	 * Saving meta box in order admin page using ajax.
	 *
	 * @throws \Exception Throw error for invalid nonce.
	 */
	public function save_meta_box_ajax() {
		try {
			// Get array of nonce fields.
			$nonce_fields = array_values( $this->get_nonce_fields() );

			if ( empty( $nonce_fields ) ) {
				throw new \Exception( esc_html__( 'Cannot find nonce field!', 'postnl-for-woocommerce' ) );
			}

			// Check nonce before proceed.
			$nonce_result = check_ajax_referer( $this->nonce_key, $nonce_fields[0]['id'], false );
			if ( false === $nonce_result ) {
				throw new \Exception( esc_html__( 'Nonce is invalid!', 'postnl-for-woocommerce' ) );
			}

			$order_id = ! empty( $_REQUEST['order_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

			// Check if order id is really an ID from shop_order post type.
			$order = wc_get_order( $order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				throw new \Exception( esc_html__( 'Order does not exists!', 'postnl-for-woocommerce' ) );
			}

			$saved_data = $this->save_meta_value( $order_id, $_REQUEST );
			wp_send_json_success( $saved_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}

	/**
	 * Delete meta data in order admin page using ajax.
	 *
	 * @throws \Exception Throw error for invalid nonce.
	 */
	public function delete_meta_data_ajax() {
		try {
			// Get array of nonce fields.
			$nonce_fields = array_values( $this->get_nonce_fields() );

			if ( empty( $nonce_fields ) ) {
				throw new \Exception( esc_html__( 'Cannot find nonce field!', 'postnl-for-woocommerce' ) );
			}

			// Check nonce before proceed.
			$nonce_result = check_ajax_referer( $this->nonce_key, $nonce_fields[0]['id'], false );
			if ( false === $nonce_result ) {
				throw new \Exception( esc_html__( 'Nonce is invalid!', 'postnl-for-woocommerce' ) );
			}

			$order_id = ! empty( $_REQUEST['order_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

			// Check if order id is really an ID from shop_order post type.
			$order = wc_get_order( $order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				throw new \Exception( esc_html__( 'Order does not exists!', 'postnl-for-woocommerce' ) );
			}

			$saved_data = $this->delete_meta_value( $order_id );
			wp_send_json_success( $saved_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}
}
