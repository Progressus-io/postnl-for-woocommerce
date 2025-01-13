<?php
/**
 * Class Order\Single file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use PostNLWooCommerce\Rest_API\Shipment_and_Return\Item_Info;
use PostNLWooCommerce\Rest_API\Shipment_and_Return\Client;
use PostNLWooCommerce\Rest_API\Smart_Returns\Item_Info as smart_info;
use PostNLWooCommerce\Rest_API\Smart_Returns\Client as smart_client;
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
		add_filter( 'woocommerce_admin_shipping_fields', array( $this, 'admin_address_fields' ) );
		add_filter( 'woocommerce_admin_billing_fields', array( $this, 'admin_address_fields' ) );
		add_filter( 'woocommerce_order_formatted_shipping_address', array( $this, 'display_shipping_house_number' ), 10, 2 );
		add_filter( 'woocommerce_order_formatted_billing_address', array( $this, 'display_billing_house_number' ), 10, 2 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_order_single_css_script' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ), 20, 2 );

		add_action( 'wp_ajax_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );
		add_action( 'wp_ajax_nopriv_postnl_order_save_form', array( $this, 'save_meta_box_ajax' ) );

		add_action( 'wp_ajax_postnl_order_delete_data', array( $this, 'delete_meta_data_ajax' ) );
		add_action( 'wp_ajax_nopriv_postnl_order_delete_data', array( $this, 'delete_meta_data_ajax' ) );

		add_action( 'init', array( $this, 'get_label_file' ), 10 );

		add_action( 'wp_ajax_postnl_activate_return_function', array( $this, 'postnl_activate_return_function' ) );
		add_action( 'wp_ajax_nopriv_postnl_activate_return_function', array( $this, 'postnl_activate_return_function' ) );

		add_action( 'wp_ajax_postnl_send_smart_return_email', array( $this, 'postnl_send_smart_return_email' ) );
		add_action( 'wp_ajax_nopriv_postnl_send_smart_return_email', array( $this, 'postnl_send_smart_return_email' ) );
	}

	/**
	 * Enqueue CSS file in order detail page.
	 */
	public function enqueue_order_single_css_script() {
		$screen = get_current_screen();

		if ( empty( $screen->id ) ) {
			return;
		}

		if ( ! empty( $screen->id ) && 'woocommerce_page_wc-settings' === $screen->id && ! empty( $_GET['section'] ) && POSTNL_SETTINGS_ID === wp_unslash( $_GET['section'] ) ) {
			wp_enqueue_script(
				'postnl-admin-settings',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-settings.js',
				array( 'jquery' ),
				POSTNL_WC_VERSION,
				true
			);
		}

		if ( ( 'shop_order' === $screen->id && 'post' === $screen->base ) || 'woocommerce_page_wc-orders' === $screen->id ) {
			wp_enqueue_style( 'postnl-admin-order-single', POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/admin-order-single.css', array(), POSTNL_WC_VERSION );

			wp_enqueue_script(
				'postnl-admin-order-single',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-order-single.js',
				array( 'jquery' ),
				POSTNL_WC_VERSION,
				true
			);

			wp_enqueue_script(
				'postnl-admin-shipment-track-trace',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-shipment-track-trace.js',
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
						function ( $meta_field ) {
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
	 * @param String $post_type Post type for current admin page.
	 * @param WP_POST|WC_Order $post_or_order_object Either WP_Post or WC_Order object.
	 */
	public function add_meta_box( $post_type, $post_or_order_object ) {
		$order = $this->init_order_object( $post_or_order_object );

		if ( ! is_a( $order, 'WC_Order' ) || ! $this->is_postnl_shipping_method( $order ) ) {
			return;
		}

		try {
			$screen = wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled()
				? wc_get_page_screen_id( 'shop-order' )
				: 'shop_order';
		} catch ( \Exception $e ) {
			$screen = 'shop_order';
		}

		// translators: %s will be replaced by service name.
		add_meta_box( 'woocommerce-shipment-postnl-label', esc_html__( 'Label & Tracking', 'postnl-for-woocommerce' ), array(
			$this,
			'meta_box_html'
		), $screen, 'side', 'high' );
	}

	/**
	 * Add value to meta box fields.
	 *
	 * @param WC_Order $order current order object.
	 *
	 * @return array
	 */
	public function add_meta_box_value( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}
		$meta_fields = $this->meta_box_fields( $order );

		$order_data   = $order->get_meta( $this->meta_name );
		$option_map   = Mapping::option_available_list();
		$from_country = Utils::get_base_country();
		$to_country   = $order->get_shipping_country();
		$destination  = Utils::get_shipping_zone( $to_country );

		foreach ( $meta_fields as $index => $field ) {
			if ( isset( $field['nonce'] ) && true === $field['nonce'] ) {
				continue;
			}

			$field_name = Utils::remove_prefix_field( $this->prefix, $field['id'] );

			if ( ! empty( $order_data['frontend'][ $field_name ] ) ) {
				$meta_fields[ $index ]['value'] = $order_data['frontend'][ $field_name ];
			}

			if ( isset( $order_data['backend'][ $field_name ] ) ) {
				if ( $this->have_label_file( $order ) ) {
					$meta_fields[ $index ]['custom_attributes']['disabled'] = 'disabled';
				}
				$meta_fields[ $index ]['value'] = $order_data['backend'][ $field_name ];
			}

			if ( isset( $option_map[ $from_country ][ $destination ] ) ) {
				$meta_fields[ $index ]['standard_feat'] = in_array( $field_name, $option_map[ $from_country ][ $destination ] );
			} else {
				$meta_fields[ $index ]['standard_feat'] = false;
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
			function ( $field ) use ( $available_options ) {
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
				// Convert to the Dutch date format
				$date_obj   = date_create_from_format( 'Y-m-d', $info_val );
				$dutch_date = date_format( $date_obj, 'd/m/Y' );
				?>
                <div class="postnl-info <?php echo esc_attr( $info_idx ); ?>">
					<?php echo esc_html( $dutch_date ); ?>
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
		$filtered_infos = Utils::get_filtered_pickup_points_infos($infos);

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
	 * Adds an 'Activate return function' button.
	 */
	public function activate_return_function_html( $order ) {
		if ( 'shipping_return' === $this->settings->get_return_shipment_and_labels() && 'no' === $this->settings->get_return_shipment_and_labels_all() && 'NL' === $order->get_shipping_country() && 'NL' === Utils::get_base_country() && ! Utils::is_order_eligible_auto_letterbox( $order ) ) {
			?>
            <hr id="postnl_break_2">
            <p class="form-field">

				<?php wp_nonce_field( 'postnl_activate_return_function', 'activate_return_function_nonce' ); ?>
                <button type="button" class="button button-activate-return" <?php disabled( $this->is_return_function_activated( $order ) ); ?>><?php esc_html_e( 'Activate return function', 'postnl-for-woocommerce' ); ?></button>

                <div class="postnl-info activated-return-info" <?php echo ( $this->is_return_function_activated( $order ) ) ? '' : 'style="display:none;"'; ?> >
                    <?php esc_html_e( 'Return function is activated for this label', 'postnl-for-woocommerce' ); ?>
                </div>
                <div class="postnl-info activate-return-info" <?php echo ( $this->is_return_function_activated( $order ) ) ? 'style="display:none;"' : ''; ?> >
                    <?php esc_html_e( 'Click here to activate the return function of this label', 'postnl-for-woocommerce' ); ?>
                </div>
            </p>
			<?php
		}
	}

	/**
	 * Adds an 'Send Smart Return' button.
	 */
	public function send_smart_return_email_html( $order ) {
		if ( 'NL' === $order->get_shipping_country() && $this->settings->get_activate_smart_return() ) {
			$check_for_barcode = empty( $this->get_backend_data( $order->get_ID() ) );
			?>
            <hr id="postnl_break_2">
            <p class="form-field">
				<?php wp_nonce_field( 'postnl_send_smart_return_email', 'send_smart_return_email_nonce' ); ?>
                <button type="button"
                        class="button button-send-smart-return" <?php disabled( $check_for_barcode ); ?>><?php esc_html_e( 'Send email with Smart Return', 'postnl-for-woocommerce' ); ?></button>
            </p>
			<?php
		}
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

		$form_class = ( $this->have_label_file( $order ) ) ? 'generated' : '';
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
                <a href="<?php echo esc_url( $this->get_download_label_url( $order->get_id() ) ); ?>"
                   class="button button-primary button-download-label"><?php esc_html_e( 'Print Label', 'postnl-for-woocommerce' ); ?></a>
                <a class="button button-secondary delete-label"
                   href="#"><?php esc_html_e( 'Delete Label', 'postnl-for-woocommerce' ); ?></a>
            </div>
			<?php $this->activate_return_function_html( $order ) ?>
			<?php $this->send_smart_return_email_html( $order ) ?>
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

			$result        = $this->save_meta_value( $order_id, $_REQUEST );
			$return_data   = $result['saved_data'];
			$labels        = $result['labels'];
			$tracking_note = $this->get_tracking_note( $order_id );

			if ( ! empty( $tracking_note ) ) {
				$return_data = array_merge(
					$result['saved_data'],
					array(
						'tracking_note' => $tracking_note,
						'note_type'     => $this->settings->is_woocommerce_email_enabled() ? 'customer' : 'private',
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
				throw new \Exception( esc_html__( 'Order does not exist!', 'postnl-for-woocommerce' ) );
			}

			$saved_data = $this->delete_meta_value( $order_id );
			wp_send_json_success( $saved_data );
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}

	/**
	 * Add house number field to the order address fields within the dashboard.
	 *
	 * @param array $fields address fields.
	 *
	 * @return array.
	 */
	public function admin_address_fields( $fields ) {
		$new_fields = array();
		foreach ( $fields as $key => $field ) {
			if ( 'address_1' === $key ) {
				$new_fields['house_number'] = array(
					'label' => __( 'House number', 'postnl-for-woocommerce' ),
					'show'  => false,
				);
			}

			$new_fields[ $key ] = $field;
		}

		return $new_fields;
	}

	/**
	 * Modify address and add house number.
	 *
	 * @param array $address Array of shipping address.
	 * @param \WC_Order $order Order object.
	 * @param String $type Address type.
	 *
	 * @return mixed
	 */
	public function add_house_number_to_address( $address, $order, $type = 'shipping' ) {
		if ( 'shipping' === $type ) {
			$house_number_meta = '_shipping_house_number';
		} else {
			$house_number_meta = '_billing_house_number';
		}

		$house_number = $order->get_meta( $house_number_meta );

		if ( $house_number ) {
			$address['address_1'] .= ' ' . $house_number;
		}

		return $address;
	}

	/**
	 * Add house number to the shipping address within the order.
	 *
	 * @param array $address Array of shipping address.
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function display_shipping_house_number( $address, $order ) {
		return $this->add_house_number_to_address( $address, $order, 'shipping' );
	}

	/**
	 * Add house number to the billing address within the order.
	 *
	 * @param array $address Array of shipping address.
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function display_billing_house_number( $address, $order ) {
		return $this->add_house_number_to_address( $address, $order, 'billing' );
	}

	/**
	 * Ajax action to activate return function.
	 *
	 * @return void
	 */
	public function postnl_activate_return_function() {
		try {
			if ( ! isset( $_POST['security'] ) ) {
				throw new \Exception( esc_html__( 'Cannot find nonce field!', 'postnl-for-woocommerce' ) );
			}

			// Check nonce before proceed.
			if ( ! wp_verify_nonce( $_POST['security'], 'postnl_activate_return_function' ) ) {
				throw new \Exception( esc_html__( 'Nonce is invalid', 'postnl-for-woocommerce' ) );
			}

			$order_id = ! empty( $_REQUEST['order_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

			// Check if order id is really an ID from shop_order post type.
			$order = wc_get_order( $order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				throw new \Exception( esc_html__( 'Order does not exist!', 'postnl-for-woocommerce' ) );
			}

			if ( $this->is_return_function_activated( $order ) ) {
				throw new \Exception( esc_html__( 'Already activated!', 'postnl-for-woocommerce' ) );
			}

			$item_info = new Item_Info( $order_id );
			$api_call  = new Client( $item_info );
			$response  = $api_call->send_request();

			if ( isset( $response['successFulBarcodes'][0] ) ) {
				$order->update_meta_data( $this->is_return_activated_meta, 'yes' );
				$order->save_meta_data();

				wp_send_json_success();
			} else {
				$error_message = isset( $response['errorsPerBarcode'][0]['errors'][0] ) ? $response['errorsPerBarcode'][0]['errors'][0]['description'] : 'Unknown error';

				// Translators: %s is the error message.
				throw new \Exception( sprintf( esc_html__( 'Error: %s', 'postnl-for-woocommerce' ), esc_html( $error_message ) ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}

	/**
	 * Ajax action to send smart return email.
	 *
	 * @return void
	 */
	public function postnl_send_smart_return_email() {
		try {
			if ( ! isset( $_POST['security'] ) ) {
				throw new \Exception( esc_html__( 'Cannot find nonce field!', 'postnl-for-woocommerce' ) );
			}

			// Check nonce before proceed.
			if ( ! wp_verify_nonce( $_POST['security'], 'postnl_send_smart_return_email' ) ) {
				throw new \Exception( esc_html__( 'Nonce is invalid', 'postnl-for-woocommerce' ) );
			}

			$order_id = ! empty( $_REQUEST['order_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order_id'] ) ) : 0;

			// Check if order id is really an ID from shop_order post type.
			$order = wc_get_order( $order_id );
			if ( ! is_a( $order, 'WC_Order' ) ) {
				throw new \Exception( esc_html__( 'Order does not exist!', 'postnl-for-woocommerce' ) );
			}

			$item_info = new smart_info( $order );
			$api_call  = new smart_client( $item_info );
			$response  = $api_call->send_request();
			if ( ! empty( $response ) ) {
				$printcodeLabelContent = null;

				// Iterate through the ResponseShipments
				if ( isset( $response['ResponseShipments'] ) ) {
					foreach ( $response['ResponseShipments'] as $shipment ) {
						// Iterate through the Labels
						if ( isset( $shipment['Labels'] ) ) {
							foreach ( $shipment['Labels'] as $label ) {
								// Check if the Labeltype is "PrintcodeLabel"
								if ( isset( $label['Labeltype'] ) && $label['Labeltype'] === 'PrintcodeLabel' ) {
									// Save the Content to a PHP variable
									$printcodeLabelContent = $label['Content'];
									break 2; // Exit both loops once the label is found
								}
							}
						}
					}
				}
				//wp_send_json_success($printcodeLabelContent);
			} else {
				throw new \Exception( esc_html__( 'PrintcodeLabel could not found', 'postnl-for-woocommerce' ) );
			}
			if ( $printcodeLabelContent ) {
				$pdf_content = base64_decode( $printcodeLabelContent );

				// Save the PDF content to a file
				$upload_dir = wp_upload_dir();
				$file_path  = $upload_dir['path'] . '/printcode_label.pdf';

				// Write the content to the file
				file_put_contents( $file_path, $pdf_content );
			}

			$to            = $order->get_billing_email();
			$is_successful = false;
			$emails        = WC()->mailer()->get_emails();

			if ( ! empty( $emails ) && isset( $emails['WC_Smart_Return_Email'] ) ) {
				$emails['WC_Smart_Return_Email']->recipient = $to;

				// Set the attachment path property
				$emails['WC_Smart_Return_Email']->attachment = $file_path;

				// Trigger the email
				$is_successful = $emails['WC_Smart_Return_Email']->trigger( $order_id );
			}

			if ( $is_successful ) {
				wp_send_json_success( $to );
			} else {

				throw new \Exception( esc_html__( 'Email could not be send', 'postnl-for-woocommerce' ) );
			}
		} catch ( \Exception $e ) {
			wp_send_json_error(
				array( 'message' => $e->getMessage() ),
			);
		}
	}
}
