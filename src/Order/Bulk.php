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
		add_filter( 'handle_bulk_actions-edit-shop_order', array( $this, 'process_order_bulk_actions' ), 10, 3 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_bulk_assets' ) );
		add_action( 'admin_footer', array( $this, 'model_content_fields_create_label' ) );
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

		$array_messages = array(
			'user_id' => get_current_user_id(),
		);

		$gen_labels  = array(); // Generated labels.

		if ( ! empty( $object_ids ) ) {
			foreach ( $object_ids as $order_id ) {
				$result           = $this->generate_label_and_notes( $order_id, $_REQUEST );
				$array_messages[] = $result['message'];
				$gen_labels[]     = $result['labels_data']['labels'];
			}
		}

		if ( ! empty( $gen_labels ) ) {
			$array_messages[] = $this->merge_bulk_labels( $gen_labels );
		}

		update_option( $this->bulk_option_text_name, $array_messages );

		return $redirect;
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
		$merged_info = $this->merge_labels( $label_paths, $filename );

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
					return ( ! empty( $field['show_in_bulk'] ) && true === $field['show_in_bulk'] );
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
					<?php Utils::fields_generator( $this->meta_box_fields() ); ?>

					<br>
					<button type="button" class="button button-primary" id="postnl_create_label_proceed"><?php esc_html_e( 'Submit', 'postnl-for-woocommerce' ); ?></button>
				</div>
			</div>
			<?php
		}
	}

	/**
	 * Display messages on order view screen.
	 */
	public function render_messages() {
		$current_screen = get_current_screen();

		if ( isset( $current_screen->id ) && in_array( $current_screen->id, array( 'shop_order', 'edit-shop_order' ), true ) ) {

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

		if ( $this->have_backend_data( $order ) ) {
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

			$array_messages = array(
				'user_id' => get_current_user_id(),
			);
			$result = $this->generate_label_and_notes( $order_id, $_REQUEST );
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
		$result = array(
			'messages' => array(),
		);

		try {
			$result['labels_data'] = $this->save_meta_value( $order_id, $post_data );
			$tracking_note         = $this->get_tracking_note( $order_id );

			if ( $this->settings->is_woocommerce_email_enabled() && ! empty( $tracking_note ) ) {
				$order = wc_get_order( $order_id );
				$order->add_order_note( $tracking_note, 1 );
			}

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
