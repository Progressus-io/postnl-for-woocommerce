<?php
/**
 * Class Order\Single file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Helper\Mapping;

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
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );

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
	 *
	 * @param String           $post_type Post type for current admin page.
	 * @param WP_POST|WC_Order $post_or_order_object Either WP_Post or WC_Order object.
	 */
	public function add_meta_box( $post_type, $post_or_order_object ) {
		$order = $this->init_order_object( $post_or_order_object );

		if ( ! is_a( $order, 'WC_Order' ) || ! $this->is_postnl_shipping_method( $order ) ) {
			return;
		}

		$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
		? wc_get_page_screen_id( 'shop-order' )
		: 'shop_order';

		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', esc_html__( 'Label & Tracking', 'postnl-for-woocommerce' ), array( $this, 'meta_box_html' ), $screen, 'side', 'high' );
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
			$order_data   = $order->get_meta( $this->meta_name );
			$option_map   = Mapping::option_available_list();
			$from_country = Utils::get_base_country();
			$to_country   = $order->get_shipping_country();
			$destination  = Utils::get_shipping_zone( $to_country );

			foreach ( $meta_fields as $index => $field ) {
				$field_name = Utils::remove_prefix_field( $this->prefix, $field['id'] );

				if ( ! empty( $order_data['frontend'][ $field_name ] ) ) {
					$meta_fields[ $index ]['value'] = $order_data['frontend'][ $field_name ];
				}

				if ( isset( $order_data['backend'][ $field_name ] ) ) {
					$meta_fields[ $index ]['custom_attributes']['disabled'] = 'disabled';
					$meta_fields[ $index ]['value']                         = $order_data['backend'][ $field_name ];
				}

				if ( isset( $option_map[ $from_country ][ $destination ] ) ) {
					$meta_fields[ $index ]['standard_feat'] = in_array( $field_name, $option_map[ $from_country ][ $destination ] );
				} else {
					$meta_fields[ $index ]['standard_feat'] = false;
				}
			}
		}

		return $meta_fields;
	}

	/**
	 * Filter the fields to display the available fields only.
	 *
	 * @param Array $meta_fields Order meta fields.
	 * @param Array $available_options Available fields based on the countries and chosen option in checkout page.
	 *
	 * @return array
	 */
	public function filter_available_fields( $meta_fields, $available_options ) {
		$meta_fields = array_filter(
			$meta_fields,
			function( $field ) use ( $available_options ) {
				$field_name = Utils::remove_prefix_field( $this->prefix, $field['id'] );

				if ( true === $field['standard_feat'] ) {
					return true;
				}

				if ( true === $field['const_field'] ) {
					return true;
				}

				return in_array( $field_name, $available_options, true );
			}
		);

		return $meta_fields;
	}

	/**
	 * Get order information from frontend data.
	 *
	 * @param WC_Order $order Order object.
	 * @param String   $needle String that will be used to search the frontend value.
	 *
	 * @return Array.
	 */
	public function get_order_frontend_info( $order, $needle ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$order_data = $order->get_meta( $this->meta_name );

		if ( ! empty( $order_data['frontend'] ) ) {
			$info_value = array();

			foreach ( $order_data['frontend'] as $key => $value ) {
				if ( false !== strpos( $key, $needle ) ) {
					$info_value[ $key ] = $value;
				}
			}

			return $info_value;
		}

		return array();
	}

	/**
	 * Get dropoff points information.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return Array.
	 */
	public function get_pickup_points_info( $order ) {
		return $this->get_order_frontend_info( $order, 'dropoff_points_' );
	}

	/**
	 * Get delivery day information.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return Array.
	 */
	public function get_delivery_day_info( $order ) {
		return $this->get_order_frontend_info( $order, 'delivery_day_' );
	}

	/**
	 * Get delivery type string.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return String.
	 */
	public function get_delivery_type( $order ) {
		$from_country      = Utils::get_base_country();
		$to_country        = $order->get_shipping_country();
		$delivery_type_map = Mapping::delivery_type();
		$filtered_frontend = $this->get_order_frontend_info( $order, '_type' );
		$destination       = Utils::get_shipping_zone( $to_country );

		if ( ! is_array( $delivery_type_map[ $from_country ][ $destination ] ) ) {
			return ! empty( $delivery_type_map[ $from_country ][ $destination ] ) ? $delivery_type_map[ $from_country ][ $destination ] : '';
		}

		if ( empty( $filtered_frontend ) ) {
			return '';
		}

		foreach ( $filtered_frontend as $frontend_key => $frontend_value ) {
			if ( ! empty( $delivery_type_map[ $from_country ][ $destination ][ $frontend_key ][ $frontend_value ] ) ) {
				return $delivery_type_map[ $from_country ][ $destination ][ $frontend_key ][ $frontend_value ];
			}
		}

		return '';
	}

	/**
	 * Generate the dropoff points html information.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function generate_delivery_type_html( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$delivery_type = $this->get_delivery_type( $order );

		if ( empty( $delivery_type ) ) {
			return;
		}
		?>
			<div class="postnl-info-container delivery-type-info">
				<label for="postnl_delivery_type"><?php esc_html_e( 'Delivery Type:', 'postnl-for-woocommerce' ); ?></label>
				<div class="postnl-info delivery-type">
					<?php echo esc_html( $delivery_type ); ?>
				</div>
			</div>
		<?php
	}

	/**
	 * Generate the dropoff points html information.
	 *
	 * @param Array $infos Dropoff points informations.
	 */
	public function generate_delivery_date_html( $infos ) {
		$filtered_infos = array_filter(
			$infos,
			function ( $info ) {
				$displayed_info = array(
					'delivery_day_date',
				);

				return in_array( $info, $displayed_info, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( empty( $filtered_infos ) ) {
			return;
		}
		?>
			<div class="postnl-info-container delivery-date-info">
				<label for="postnl_pickup_points"><?php esc_html_e( 'Delivery Date:', 'postnl-for-woocommerce' ); ?></label>
				<?php
				foreach ( $filtered_infos as $info_idx => $info_val ) {
					?>
					<div class="postnl-info <?php echo esc_attr( $info_idx ); ?>">
						<?php echo esc_html( $info_val ); ?>
					</div>
					<?php
				}
				?>
			</div>
		<?php
	}

	/**
	 * Generate the dropoff points html information.
	 *
	 * @param Array $infos Dropoff points informations.
	 */
	public function generate_pickup_points_html( $infos ) {
		$filtered_infos = array_filter(
			$infos,
			function ( $info ) {
				$displayed_info = array(
					'dropoff_points_date',
					'dropoff_points_time',
				);

				return in_array( $info, $displayed_info, true );
			},
			ARRAY_FILTER_USE_KEY
		);

		$address_info = array_filter(
			$infos,
			function ( $info ) {
				return false !== strpos( $info, '_address_' );
			},
			ARRAY_FILTER_USE_KEY
		);

		if ( ! empty( $address_info ) ) {
			$filtered_infos['address'] = implode( ', ', $address_info );
			ksort( $filtered_infos );
		}

		if ( empty( $filtered_infos ) ) {
			return;
		}
		?>
		<div class="postnl-info-container pickup-points-info">
			<label for="postnl_pickup_points"><?php esc_html_e( 'Pickup Address:', 'postnl-for-woocommerce' ); ?></label>
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
			?>
		</div>
		<?php
	}
	/**
	 * Additional fields of the meta box for child class.
	 *
	 * @param WP_Post|WC_Order $post_or_order_object current order object.
	 */
	public function meta_box_html( $post_or_order_object ) {
		$order = $this->init_order_object( $post_or_order_object );
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return;
		}

		$form_class = ( $this->have_backend_data( $order ) ) ? 'generated' : '';
		if ( $this->have_backend_data( $order, 'create_return_label' ) ) {
			$form_class .= ' has-return';
		}

		if ( $this->have_backend_data( $order, 'letterbox' ) ) {
			$form_class .= ' has-letterbox';
		}

		$pickup_info       = $this->get_pickup_points_info( $order );
		$delivery_info     = $this->get_delivery_day_info( $order );
		$fields_with_value = $this->add_meta_box_value( $order );
		$available_options = $this->get_available_options( $order );
		$available_fields  = $this->filter_available_fields( $fields_with_value, $available_options );
		?>
		<div id="shipment-postnl-label-form" class="<?php echo esc_attr( $form_class ); ?>">
			<?php $this->generate_delivery_type_html( $order ); ?>
			<?php $this->generate_delivery_date_html( $delivery_info ); ?>
			<?php $this->generate_pickup_points_html( $pickup_info ); ?>
			<?php Utils::fields_generator( $available_fields ); ?>

			<div class="button-container">
				<button class="button button-primary button-save-form"><?php esc_html_e( 'Create Shipment', 'postnl-for-woocommerce' ); ?></button>
				<a href="<?php echo esc_url( $this->get_download_label_url( $order->get_id() ) ); ?>" class="button button-primary button-download-label"><?php esc_html_e( 'Print Label', 'postnl-for-woocommerce' ); ?></a>
				<a class="button button-secondary delete-label" href="#"><?php esc_html_e( 'Delete Label', 'postnl-for-woocommerce' ); ?></a>
			</div>
			<!-- 
			<div class="button-container return-container">
				<a href="<?php echo esc_url( $this->get_download_label_url( $order->get_id(), 'return-label' ) ); ?>" class="button button-primary button-download-label"><?php esc_html_e( 'Print Return Label', 'postnl-for-woocommerce' ); ?></a>
			</div>
			-->
			<!-- 
			<div class="button-container letterbox-container">
				<a href="<?php echo esc_url( $this->get_download_label_url( $order->get_id(), 'buspakjeextra' ) ); ?>" class="button button-primary button-download-label"><?php esc_html_e( 'Print Letterbox', 'postnl-for-woocommerce' ); ?></a>
			</div>
			-->
			<div id="shipment-postnl-error-text"></div>
		</div>
		<?php
	}

	/**
	 * Additional fields of the meta box for child class.
	 *
	 * @param WC_Order $order current order object.
	 * @param String   $field Backend field name.
	 *
	 * @return boolean
	 */
	public function have_backend_data( $order, $field = '' ) {
		$order_data = $order->get_meta( $this->meta_name );

		if ( ! empty( $field ) ) {
			return ! empty( $order_data['backend'][ $field ] );
		}

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

			$saved_data    = $this->save_meta_value( $order_id, $_REQUEST );
			$return_data   = $saved_data;
			$tracking_note = $this->get_tracking_note( $order_id );

			if ( $this->settings->is_woocommerce_email_enabled() && ! empty( $tracking_note ) ) {
				$return_data = array_merge(
					$saved_data,
					array(
						'tracking_note' => $tracking_note,
						'note_type'     => Utils::get_tracking_note_type(),
					)
				);
			}

			wp_send_json_success( $return_data );
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
