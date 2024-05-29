<?php
/**
 * Class Order\Bulk file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use PostNLWooCommerce\Utils;

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
	 * Field name for action confirmation option text.
	 *
	 * @var String
	 */
	public $bulk_option_text_name = '_postnl_bulk_action_confirmation';

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_filter( 'bulk_actions-edit-shop_order', array( $this, 'add_order_bulk_actions' ), 10, 1 );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'bulk_action_create_label' ), 10, 3 );
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'bulk_action_change_shipping_options' ), 10, 3 );

		add_filter( 'bulk_actions-woocommerce_page_wc-orders', array( $this, 'add_order_bulk_actions' ), 10, 1 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_action_create_label' ), 10, 3 );
		add_filter( 'handle_bulk_actions-woocommerce_page_wc-orders', array( $this, 'bulk_action_change_shipping_options' ), 10, 3 );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_assets' ) );
		add_action( 'admin_footer', array( $this, 'modal_create_label' ) );
		add_action( 'admin_footer', array( $this, 'modal_change_shipping_options' ) );
		add_filter( 'postnl_order_meta_box_fields', array( $this, 'additional_meta_box' ), 10, 1 );

		// Display admin notices for bulk actions.
		add_action( 'admin_notices', array( $this, 'render_messages' ) );
		add_action( 'init', array( $this, 'get_bulk_file' ), 10 );

		// Add 'Create Label' action button.
		add_action( 'wp_ajax_postnl_create_label', array( $this, 'postnl_create_label_ajax' ) );
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_create_label_actions_button' ), 10, 2 );
	}

	/**
	 * Add new bulk actions.
	 *
	 * @param array $bulk_actions List of bulk actions.
	 *
	 * @return array
	 */
	public function add_order_bulk_actions( $bulk_actions ) {
		$base_country = Utils::get_base_country();		
		$bulk_actions['postnl-create-label']            = esc_html__( 'PostNL Create Label', 'postnl-for-woocommerce' );
		if($base_country != 'BE'){
			$bulk_actions['postnl-change-shipping-options'] = esc_html__( 'PostNL Change Shipping Options', 'postnl-for-woocommerce' );
		}
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
	public function bulk_action_create_label( $redirect, $doaction, $object_ids ) {
		if ( 'postnl-create-label' !== $doaction ) {
			return $redirect;
		}

		$array_messages = array(
			'user_id' => get_current_user_id(),
		);

		$gen_labels = array(); // Generated labels.

		if ( ! empty( $object_ids ) ) {
			foreach ( $object_ids as $order_id ) {
				$result = $this->generate_label_and_notes( $order_id, $_REQUEST );
				if ( isset( $result['message'] ) ) {
					$array_messages[] = $result['message'];
				}
				if ( isset( $result['labels_data']['labels'] ) ) {
					$gen_labels[] = $result['labels_data']['labels'];
				}
			}
		}

		if ( ! empty( $gen_labels ) ) {
			$array_messages[] = $this->merge_bulk_labels( $gen_labels );
		}

		update_option( $this->bulk_option_text_name, $array_messages );

		return $redirect;
	}

	/**
	 * Process PostNL in bulk.
	 *
	 * @param String $redirect Redirect URL after the bulk has been processed.
	 * @param String $doaction Chosen action.
	 * @param array  $object_ids Chose IDs.
	 *
	 * @return string
	 */
	public function bulk_action_change_shipping_options( $redirect, $doaction, $object_ids ) {

		if ( 'postnl-change-shipping-options' !== $doaction ) {
			return $redirect;
		}

		$array_messages = array(
			'user_id' => get_current_user_id(),
		);

		$selected_shipping_options = $this->prepare_default_options( $_REQUEST );
		$zone                      = strtoupper( sanitize_text_field( $_REQUEST['postnl_shipping_zone'] ) );

		if ( ! empty( $object_ids ) ) {
			foreach ( $object_ids as $order_id ) {
				$order                = wc_get_order( $order_id );
				$have_label_file      = $this->have_label_file( $order );
				$match_shipping_zones = $zone === $this->get_shipping_zone( $order );

				if ( $have_label_file ) {
					$array_messages[] = array(
						'message' => sprintf( esc_html__( 'Order #%1$d already has a label.', 'postnl-for-woocommerce' ), $order_id ),
						'type'    => 'error',
					);
				}

				if ( ! $match_shipping_zones ) {
					$array_messages[] = array(
						'message' => sprintf( esc_html__( 'Order #%1$d is from another shipping zone.', 'postnl-for-woocommerce' ), $order_id ),
						'type'    => 'error',
					);
				}

				if ( ! $have_label_file && $match_shipping_zones ) {
					$order->delete_meta_data( $this->meta_name );
					$order->update_meta_data( $this->meta_name, array( 'backend' => $selected_shipping_options ) );
					$order->save();
				}
			}
		}

		update_option( $this->bulk_option_text_name, $array_messages );

		return $redirect;
	}

	/**
	 * Prepare default shipping options based on user selection from the bulk modal.
	 *
	 * @param array $options Selected options by the user.
	 *
	 * @return array
	 */
	protected function prepare_default_options( $options ) {
		$zone            = sanitize_text_field( $options['postnl_shipping_zone'] );
		$selected_option = sanitize_text_field( $options[ 'postnl_default_shipping_options_' . strtolower( $zone ) ] );

		return Utils::prepare_shipping_options( $selected_option );
	}

	/**
	 * Merge bulk labels.
	 *
	 * @param Array $gen_labels Generated labels.
	 */
	public function merge_bulk_labels( $gen_labels ) {
		$label_format  = $this->settings->get_label_format();
		$label_paths   = array();
		$array_messags = array();

		foreach ( $gen_labels as $idx => $label ) {
			foreach ( $label as $label_type => $label_info ) {
				if ( empty( $label_info['filepath'] ) ) {
					continue 2;
				}

				if ( 'A6' === $label_format ) {
					$label_paths[] = $label_info['filepath'];
					continue 2;
				}

				if ( empty( $label_info['merged_files'] ) ) {
					continue 2;
				}

				foreach ( $label_info['merged_files'] as $path ) {
					$label_paths[] = $path;
				}
			}
		}

		$filename    = 'postnl-bulk-' . get_current_user_id() . '.pdf';

		if ( isset( $_GET['postnl_position_printing_labels'] ) ) {
			$start_position = sanitize_text_field( $_GET['postnl_position_printing_labels'] );
		} else {
			$start_position = 'top-left';
		}

		$merged_info = $this->merge_labels( $label_paths, $filename, $start_position );

		foreach ( $gen_labels as $labels ) {
			$this->delete_label_files( $labels );
		}

		if ( file_exists( $merged_info ['filepath'] ) ) {
			// We're saving the bulk file path temporarily and access it later during the download process.
			// This information expires in 3 minutes (180 seconds), just enough for the user to see the
			// Displayed link and click it if he or she wishes to download the bulk labels.
			set_transient( '_postnl_bulk_download_labels_file_' . get_current_user_id(), $merged_info ['filepath'], 180 );

			// Construct URL pointing to the download label endpoint (with bulk param).
			$bulk_download_label_url = $this->get_download_bulk_url();

			return array(
				// translators: %1$s is anchor tag opener. %2$s is anchor tag closer.
				'message' => sprintf( esc_html__( 'Bulk PostNL labels file created - %1$sdownload file%2$s', 'postnl-for-woocommerce' ), '<a href="' . esc_url( $bulk_download_label_url ) . '" download>', '</a>' ),
				'type'    => 'success',
			);
		}

		return array(
			'message' => esc_html__( 'Could not create bulk PostNL label file, download individually.', 'postnl-for-woocommerce' ),
			'type'    => 'error',
		);
	}

	/**
	 * Generate download label url
	 *
	 * @return String.
	 */
	public function get_download_bulk_url() {
		$download_url = add_query_arg(
			array(
				'postnl_label_bulk_nonce' => wp_create_nonce( 'postnl_download_label_bulk_nonce' ),
			),
			home_url()
		);

		return $download_url;
	}

	/**
	 * Get label file.
	 *
	 * @since   1.0.0
	 * @return  void
	 */
	public function get_bulk_file() {

		if ( empty( $_GET['postnl_label_bulk_nonce'] ) ) {
			return;
		}

		// Check nonce before proceed.
		$nonce_result = check_ajax_referer( 'postnl_download_label_bulk_nonce', sanitize_text_field( wp_unslash( $_GET['postnl_label_bulk_nonce'] ) ), false );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$label_path = get_transient( '_postnl_bulk_download_labels_file_' . get_current_user_id() );

		if ( empty( $label_path ) ) {
			return;
		}

		$this->download_label( $label_path );
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function enqueue_bulk_assets() {
		$screen = get_current_screen();

		$is_legacy_order = ! empty( $screen->id ) && 'edit-shop_order' === $screen->id && ! empty( $screen->base ) && 'edit' === $screen->base;
		$is_hpos_order   = ! empty( $screen->id ) && 'woocommerce_page_wc-orders' === $screen->id && ( empty( $_GET['action'] ) || 'edit' !== $_GET['action'] );
		if ( $is_legacy_order || $is_hpos_order ) {
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

			wp_enqueue_script(
				'postnl-admin-shipment-track-trace',
				POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-shipment-track-trace.js',
				array( 'jquery' ),
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
					return ( ! empty( $field['show_in_bulk'] ) && true === $field['show_in_bulk'] );
				}
			);
		}

		return $fields;
	}

	/**
	 * Create modal wrapper with given id and fields definition.
	 *
	 * @param string $modal_id Modal id.
	 * @param array $fields Fields to be added to the modal contend.
	 *
	 * @return void
	 */
	protected function create_modal_content_wrapper( $modal_id, $fields ) {
		global $thepostid, $post;

		$screen = get_current_screen();

		$is_legacy_order = ! empty( $screen->id ) && 'edit-shop_order' === $screen->id && ! empty( $screen->base ) && 'edit' === $screen->base;
		$is_hpos_order   = ! empty( $screen->id ) && 'woocommerce_page_wc-orders' === $screen->id && ( empty( $_GET['action'] ) || 'edit' !== $_GET['action'] );

		// Bugfix, warnings shown for Order table results with no Orders.
		if ( $is_legacy_order && empty( $thepostid ) && empty( $post ) ) {
			return;
		}

		if ( $is_legacy_order || $is_hpos_order ) {
			?>
			<div id="<?php echo esc_attr( $modal_id ); ?>-modal" style="display:none;">
				<div class="postnl-modal <?php echo esc_attr( $modal_id . '-content' ) ?>">
					<?php Utils::fields_generator( $fields ); ?>
					<button type="button" class="button button-primary" id="<?php echo esc_attr( $modal_id ); ?>-proceed"><?php esc_html_e( 'Submit', 'postnl-for-woocommerce' ); ?></button>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Prepare fields for the Change shipping options modal.
	 *
	 * @return array[]
	 */
	protected function create_label_fields() {
		return array(
			array(
				'id'                => $this->prefix . 'num_labels',
				'type'              => 'number',
				'label'             => __( 'Number of Labels: ', 'postnl-for-woocommerce' ),
				'placeholder'       => '',
				'description'       => '',
				'class'             => 'short',
				'value'             => '',
				'container'         => true,
				'custom_attributes' =>
					array(
						'step' => 'any',
						'min'  => '0',
					),
			),
			array(
				'id'            => $this->prefix . 'create_return_label',
				'type'          => 'checkbox',
				'label'         => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'container'     => true,
				'value'         => $this->settings->get_return_address_default(),
			),
			array(
				'id'            => $this->prefix . 'position_printing_labels',
				'type'          => 'select',
				'label'         => __( 'Start position printing label: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'container'     => true,
				'options'       => array(
					'top-left'     => __( 'Top Left', 'postnl-for-woocommerce' ),
					'top-right'    => __( 'Top Right', 'postnl-for-woocommerce' ),
					'bottom-left'  => __( 'Bottom Left', 'postnl-for-woocommerce' ),
					'bottom-right' => __( 'Bottom Right', 'postnl-for-woocommerce' ),
				),
			),
		);
	}

	/**
	 * Collection of fields in create label bulk action.
	 */
	public function modal_create_label() {
		$this->create_modal_content_wrapper( 'postnl-create-label', $this->create_label_fields() );
	}

	/**
	 * Get shipping options per given zone from the plugin settings.
	 *
	 * @param string $zone Zone you want shipping options to, available nl, be, eu, row.
	 *
	 * @return array
	 */
	protected function get_available_shipping_options_per_zone( $zone ) {
		return $this->settings->get_setting_fields()[ 'default_shipping_options_' . strtolower( $zone ) ]['options'];
	}

	/**
	 * Prepare fields for the Change shipping options modal.
	 *
	 * @return array[]
	 */
	protected function change_shipping_options_fields() {
		return array(
			array(
				'id'            => $this->prefix . 'shipping_zone',
				'type'          => 'select',
				'label'         => __( 'Shipping zone', 'postnl-for-woocommerce' ),
				'value'         => 'nl',
				'container'     => true,
				'options'       => array(
					'nl'  => __( 'Domestic', 'postnl-for-woocommerce' ),
					'be'  => __( 'Belgium', 'postnl-for-woocommerce' ),
					'eu'  => __( 'EU Parcel', 'postnl-for-woocommerce' ),
					'row' => __( 'Non-EU Shipment', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'id' => $this->prefix . 'default_shipping_options_nl',
				'type'          => 'select',
				'label'         => __( 'Shipping options domestic', 'postnl-for-woocommerce' ),
				'wrapper_class' => 'conditional nl',
				'container'     => true,
				'value'         => $this->settings->get_country_option( 'default_shipping_options_' . 'nl' ),
				'options'       => $this->get_available_shipping_options_per_zone( 'nl' ),
			),
			array(
				'id' => $this->prefix . 'default_shipping_options_be',
				'type'          => 'select',
				'label'         => __( 'Belgium', 'postnl-for-woocommerce' ),
				'wrapper_class' => 'conditional be',
				'container'     => true,
				'value'         => $this->settings->get_country_option( 'default_shipping_options_' . 'be' ),
				'options'       => $this->get_available_shipping_options_per_zone( 'be' ),
			),
			array(
				'id' => $this->prefix . 'default_shipping_options_eu',
				'type'          => 'select',
				'label'         => __( 'Shipping options EU', 'postnl-for-woocommerce' ),
				'wrapper_class' => 'conditional eu',
				'container'     => true,
				'value'         => $this->settings->get_country_option( 'default_shipping_options_' . 'eu' ),
				'options'       => $this->get_available_shipping_options_per_zone( 'eu' ),
			),
			array(
				'id' => $this->prefix . 'default_shipping_options_row',
				'type'          => 'select',
				'label'         => __( 'Shipping options non-EU', 'postnl-for-woocommerce' ),
				'wrapper_class' => 'conditional row',
				'container'     => true,
				'value'         => $this->settings->get_country_option( 'default_shipping_options_' . 'row' ),
				'options'       => $this->get_available_shipping_options_per_zone( 'row' ),
			),
		);
	}

	/**
	 * Collection of fields in create label bulk action.
	 */
	public function modal_change_shipping_options() {
		$this->create_modal_content_wrapper( 'postnl-change-shipping-options', $this->change_shipping_options_fields() );
	}

	/**
	 * Display messages on order view screen.
	 */
	public function render_messages() {
		$current_screen = get_current_screen();

		$is_legacy_order = isset( $current_screen->id ) && in_array( $current_screen->id, array( 'shop_order', 'edit-shop_order' ), true );
		$is_hpos_order   = ! empty( $current_screen->id ) && 'woocommerce_page_wc-orders' === $current_screen->id;

		if ( $is_legacy_order || $is_hpos_order ) {

			$bulk_action_message_opt = get_option( $this->bulk_option_text_name );

			if ( ( $bulk_action_message_opt ) && is_array( $bulk_action_message_opt ) ) {

				// Remove first element from array and verify if it is the user id.
				$user_id = array_shift( $bulk_action_message_opt );
				if ( get_current_user_id() !== (int) $user_id ) {
					return;
				}

				foreach ( $bulk_action_message_opt as $key => $value ) {
					$message = $value['message'];
					$type    = wp_kses_post( $value['type'] );

					switch ( $type ) {
						case 'error':
							echo '<div class="notice notice-error is-dismissible"><ul><li>' . wp_kses_post( $message ) . '</li></ul></div>';
							break;
						case 'success':
							echo '<div class="notice notice-success is-dismissible"><ul><li><strong>' . wp_kses_post( $message ) . '</strong></li></ul></div>';
							break;
						default:
							echo '<div class="notice notice-warning is-dismissible"><ul><li><strong>' . wp_kses_post( $message ) . '</strong></li></ul></div>';
					}
				}

				delete_option( $this->bulk_option_text_name );
			}
		}
	}

	/**
	 * Add Crete Label button to the action buttons within the order list table.
	 *
	 * @param  array  $actions  Order actions.
	 * @param  \WC_Order  $order  Current order object.
	 *
	 */
	public function add_create_label_actions_button( array $actions, \WC_Order $order ) {
		// Display the button for all orders that have a PostNL as a shipping method.
		if ( ! $this->is_postnl_shipping_method( $order ) ) {
			return $actions;
		}

		if ( $this->have_label_file( $order ) ) {
			$actions['postnl-label'] = array(
				'url'    => $this->get_download_label_url( $order->get_id() ),
				'name'   => esc_html__( 'PostNL Print Label', 'postnl-for-woocommerce' ),
				'action' => 'postnl-action-download-label',
			);
		} else {
			$actions['postnl-label'] = array(
				'url'    => wp_nonce_url( admin_url( 'admin-ajax.php?action=postnl_create_label&order_id=' . $order->get_id() ), 'postnl_create_label' ),
				'name'   => esc_html__( 'PostNL Create Label', 'postnl-for-woocommerce' ),
				'action' => 'postnl-action-create-label',
			);
		}

		return $actions;
	}

	/**
	 * Process PostNL by action button.
	 */
	public function postnl_create_label_ajax() {
		if ( current_user_can( 'edit_shop_orders' ) && isset( $_GET['order_id'] ) ) {
			$order_id = absint( wp_unslash( $_GET['order_id'] ) );

			$array_messages   = array(
				'user_id' => get_current_user_id(),
			);
			$result           = $this->generate_label_and_notes( $order_id, $_REQUEST );
			$array_messages[] = $result['message'];

			update_option( $this->bulk_option_text_name, $array_messages );
		}

		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'edit.php?post_type=shop_order' ) );
		exit;
	}

	/**
	 * Generate shipping label and add order note.
	 *
	 * @param  int   $order_id Order post ID.
	 * @param  array $post_data posted data.
	 *
	 * @return array Array of labels & messages.
	 */
	public function generate_label_and_notes( $order_id, $post_data ) {
		$result = array();

		try {
			$order            = wc_get_order( $order_id );
			$default_settings = $this->get_shipping_options( $order );
			foreach( $default_settings as $name => $value ) {
				if ( $value  ) {
					$post_data[ $this->prefix . $name ] = $value;
				}
			}
			$result['labels_data'] = $this->save_meta_value( $order_id, $post_data );
			$tracking_note         = $this->get_tracking_note( $order_id );
			$customer_note         = false;

			if ( $this->settings->is_woocommerce_email_enabled() && ! empty( $tracking_note ) ) {
				$customer_note = true;
			}

			$order->add_order_note( $tracking_note, $customer_note );

			$result['message'] = array(
				'message' => sprintf( esc_html__( '#%1$s : PostNL label has been created.', 'postnl-for-woocommerce' ),
					$order_id ),
				'type'    => 'success',
			);
		} catch ( \Exception $e ) {
			$result['message'] = array(
				'message' => sprintf( '#%1$s : %2$s', $order_id, $e->getMessage() ),
				'type'    => 'error',
			);
		}

		return $result;
	}
}
