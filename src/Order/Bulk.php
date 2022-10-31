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

		$saved_datas    = array();

		try {
			if ( ! empty( $object_ids ) ) {
				foreach ( $object_ids as $order_id ) {
					$saved_datas[] = $this->save_meta_value( $order_id, $_REQUEST );
					$tracking_note = $this->get_tracking_note( $order_id );

					if ( $this->settings->is_woocommerce_email_enabled() && ! empty( $tracking_note ) ) {
						$order = wc_get_order( $order_id );
						$order->add_order_note( $tracking_note, 1 );
					}

					array_push(
						$array_messages,
						array(
							// translators: %1$s is an order ID.
							'message' => sprintf( esc_html__( '#%1$s : PostNL label has been created.', 'postnl-for-woocommerce' ), $order_id ),
							'type'    => 'success',
						)
					);
				}
			}
		} catch ( \Exception $e ) {
			array_push(
				$array_messages,
				array(
					'message' => $e->getMessage(),
					'type'    => 'error',
				)
			);
		}

		update_option( $this->bulk_option_text_name, $array_messages );

		return $redirect;
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
}
