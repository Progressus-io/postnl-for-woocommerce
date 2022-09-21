<?php
/**
 * Class Order\Base file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Rest_API\Shipping;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Helper\Mapping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Base
 *
 * @package PostNLWooCommerce\Order
 */
abstract class Base {
	/**
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * Nonce key for ajax call.
	 *
	 * @var nonce_key
	 */
	protected $nonce_key = 'create-postnl-label';

	/**
	 * Current service.
	 *
	 * @var service
	 */
	protected $service = POSTNL_SERVICE_NAME;

	/**
	 * Prefix for meta box fields.
	 *
	 * @var prefix
	 */
	protected $prefix = POSTNL_SETTINGS_ID . '_';

	/**
	 * Meta name for saved fields.
	 *
	 * @var meta_name
	 */
	protected $meta_name;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->settings  = Settings::get_instance();
		$this->meta_name = '_' . $this->prefix . 'order_metadata';
		$this->init_hooks();
	}

	/**
	 * Abstract function for collection of hooks when initiation.
	 */
	abstract public function init_hooks();

	/**
	 * Get nonce field.
	 *
	 * @return array
	 */
	public function get_nonce_fields() {
		return array_filter(
			$this->meta_box_fields(),
			function( $field ) {
				return ( ! empty( $field['nonce'] ) && true === $field['nonce'] );
			}
		);
	}

	/**
	 * List of meta box fields.
	 */
	public function meta_box_fields() {
		return apply_filters(
			'postnl_order_meta_box_fields',
			array(
				array(
					'id'          => $this->prefix . 'break_1',
					'option_feat' => false,
					'type'        => 'break',
				),
				array(
					'id'           => $this->prefix . 'insured_shipping',
					'type'         => 'checkbox',
					'label'        => __( 'Insured Shipping: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'option_feat'  => true,
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'return_no_answer',
					'type'         => 'checkbox',
					'label'        => __( 'Return if no answer: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'option_feat'  => true,
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'signature_on_delivery',
					'type'         => 'checkbox',
					'label'        => __( 'Signature on Delivery: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'option_feat'  => true,
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'only_home_address',
					'type'         => 'checkbox',
					'label'        => __( 'Only Home Address: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'option_feat'  => true,
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'letterbox',
					'type'         => 'checkbox',
					'label'        => __( 'Letterbox: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'option_feat'  => true,
					'container'    => true,
				),
				array(
					'id'          => $this->prefix . 'break_2',
					'option_feat' => false,
					'type'        => 'break',
				),
				array(
					'id'                => $this->prefix . 'num_labels',
					'type'              => 'number',
					'label'             => __( 'Number of Labels: ', 'postnl-for-woocommerce' ),
					'placeholder'       => '',
					'description'       => '',
					'class'             => 'short',
					'value'             => '',
					'custom_attributes' =>
						array(
							'step' => 'any',
							'min'  => '0',
						),
					'show_in_bulk'      => true,
					'option_feat'       => false,
					'container'         => true,
				),
				array(
					'id'           => $this->prefix . 'create_return_label',
					'type'         => 'checkbox',
					'label'        => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => $this->settings->get_return_address_default(),
					'show_in_bulk' => true,
					'option_feat'  => false,
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'label_nonce',
					'type'         => 'hidden',
					'nonce'        => true,
					'value'        => wp_create_nonce( $this->nonce_key ),
					'show_in_bulk' => true,
					'option_feat'  => false,
					'container'    => true,
				),
			)
		);
	}

	/**
	 * Get available option based on the countries and chosen option in the frontend checkout.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return Array.
	 */
	public function get_available_options( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$product_map  = Mapping::product_code();
		$from_country = Utils::get_base_country();
		$to_country   = $order->get_shipping_country();
		$saved_data   = $this->get_data( $order->get_id() );

		if ( empty( $saved_data['frontend'] ) ) {
			return array();
		}

		$selected_option   = '';
		$available_options = array();

		foreach ( $saved_data['frontend'] as $key => $value ) {
			$converted_key = Utils::convert_data_key( $key );

			if ( ! empty( $product_map[ $from_country ][ $to_country ][ $converted_key ] ) ) {
				$selected_option = $converted_key;
				break;
			}
		}

		foreach ( $product_map[ $from_country ][ $to_country ][ $selected_option ] as $product_code => $sub_options ) {
			$available_options = array_merge( $available_options, $sub_options );
		}

		return $available_options;
	}

	/**
	 * Get saved data from Order object.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return array.
	 */
	public function get_data( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$data = $order->get_meta( $this->meta_name );
		return ! empty( $data ) && is_array( $data ) ? $data : array();
	}

	/**
	 * Init order object for meta box.
	 *
	 * @param WP_POST|WC_Order $metabox_object Either WP_Post or WC_Order object.
	 */
	public function init_order_object( $metabox_object ) {
		if ( is_a( $metabox_object, 'WP_Post' ) ) {
			return wc_get_order( $metabox_object->ID );
		}

		if ( is_a( $metabox_object, 'WC_Order' ) ) {
			return $metabox_object;
		}

		return false;
	}

	/**
	 * Check if the current order is using PostNL shipping method.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function is_postnl_shipping_method( $order ) {

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$shipping_methods = $order->get_shipping_methods();

		if ( empty( $shipping_methods ) ) {
			return false;
		}

		foreach ( $shipping_methods as $shipping_item ) {
			if ( POSTNL_SETTINGS_ID === $shipping_item->get_method_id() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Saving meta box in order admin page.
	 *
	 * @param  int   $order_id Order post ID.
	 * @param  array $meta_values PostNL meta values.
	 */
	public function save_meta_value( $order_id, $meta_values ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return false;
		}

		$saved_data = $this->get_data( $order_id );

		// Get array of nonce fields.
		$nonce_fields = array_values( $this->get_nonce_fields() );

		// Loop through inputs within id 'shipment-postnl-label-form'.
		foreach ( $this->meta_box_fields() as $field ) {
			// Don't save nonce field.
			if ( $nonce_fields[0]['id'] === $field['id'] ) {
				continue;
			}

			$post_value = ! empty( $meta_values[ $field['id'] ] ) ? sanitize_text_field( wp_unslash( $meta_values[ $field['id'] ] ) ) : '';
			$post_field = Utils::remove_prefix_field( $this->prefix, $field['id'] );

			$saved_data['backend'][ $post_field ] = $post_value;
		}

		$label_post_data = array(
			'order'      => $order,
			'saved_data' => $saved_data,
		);

		$label_info          = $this->create_label( $label_post_data );
		$saved_data['label'] = $label_info;

		$order->update_meta_data( $this->meta_name, $saved_data );
		$order->save();

		return $saved_data;
	}

	/**
	 * Get frontend data from Order object.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return array.
	 */
	public function get_frontend_data( $order_id ) {
		$saved_data = $this->get_data( $order_id );

		return ! empty( $saved_data['frontend'] ) ? $saved_data['frontend'] : array();
	}

	/**
	 * Get backend data from Order object.
	 *
	 * @param int $order_id ID of the order.
	 *
	 * @return array.
	 */
	public function get_backend_data( $order_id ) {
		$saved_data = $this->get_data( $order_id );

		return ! empty( $saved_data['backend'] ) ? $saved_data['backend'] : array();
	}

	/**
	 * Delete meta data in order admin page.
	 *
	 * @param  int $order_id Order post ID.
	 *
	 * @throws \Exception Throw error for invalid order.
	 */
	public function delete_meta_value( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			throw new \Exception( esc_html__( 'Order does not exists!', 'postnl-for-woocommerce' ) );
		}

		$saved_data = $this->get_data( $order_id );

		// Delete label file.
		$this->delete_label( $saved_data );
		unset( $saved_data['backend'] );
		unset( $saved_data['label'] );

		$order->update_meta_data( $this->meta_name, $saved_data );
		$order->save();

		return $saved_data;
	}

	/**
	 * Create PostNL label for current order
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function create_label( $post_data ) {
		$order = $post_data['order'];

		$item_info = new Shipping\Item_Info( $post_data );
		$shipping  = new Shipping\Client( $item_info );
		$response  = $shipping->send_request();

		// Check any errors.
		$this->check_label_and_barcode( $response );

		$barcode  = $response['ResponseShipments'][0]['Barcode'];
		$filename = 'postnl-' . $order->get_id() . '-' . $response['ResponseShipments'][0]['Barcode'] . '.pdf';
		$filepath = trailingslashit( POSTNL_UPLOADS_DIR ) . $filename;

		$test     = base64_decode( $response['ResponseShipments'][0]['Labels'][0]['Content'] );
		$file_ret = file_put_contents( $filepath, $test );

		return array(
			'barcode'  => $barcode,
			'filepath' => $filepath,
		);
	}

	/**
	 * Make sure the barcode and label content is exists before printing.
	 *
	 * @param Array $response Response from API Call.
	 *
	 * @throws \Exception Error when barcode or label content is missing.
	 */
	public function check_label_and_barcode( $response ) {
		if ( empty( $response['ResponseShipments'][0]['Barcode'] ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the label. Barcode data is missing', 'postnl-for-woocommerce' )
			);
		}

		if ( empty( $response['ResponseShipments'][0]['Labels'][0]['Content'] ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the label. Label content is missing', 'postnl-for-woocommerce' )
			);
		}
	}

	/**
	 * Delete PostNL label for current order
	 *
	 * @param array $saved_data Order saved meta data.
	 *
	 * @return array
	 */
	public function delete_label( $saved_data ) {
		if ( empty( $saved_data['label']['filepath'] ) ) {
			return false;
		}

		return unlink( $saved_data['label']['filepath'] );
	}

	/**
	 * Generate download label url
	 *
	 * @param int $order_id ID of the order post.
	 *
	 * @return String.
	 */
	public function get_download_label_url( $order_id ) {
		$download_url = add_query_arg(
			array(
				'postnl_label_order_id' => $order_id,
				'postnl_label_nonce'    => wp_create_nonce( 'postnl_download_label_nonce' ),
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
	public function get_label_file() {
		if ( empty( $_GET['postnl_label_nonce'] ) ) {
			return;
		}

		// Check nonce before proceed.
		$nonce_result = check_ajax_referer( 'postnl_download_label_nonce', sanitize_text_field( wp_unslash( $_GET['postnl_label_nonce'] ) ), false );

		if ( empty( $_GET['postnl_label_order_id'] ) ) {
			return;
		}

		$order_id = sanitize_text_field( wp_unslash( $_GET['postnl_label_order_id'] ) );

		if ( ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_order', $order_id ) ) ) {
			return;
		}

		$saved_data = $this->get_data( $order_id );

		$this->download_label( $saved_data['label']['filepath'] );
	}

	/**
	 * Downloads the generated label file
	 *
	 * @param string $file_path File path to the label.
	 *
	 * @return boolean|void
	 */
	protected function download_label( $file_path ) {
		if ( ! empty( $file_path ) && is_string( $file_path ) && file_exists( $file_path ) ) {
			// Check if buffer exists, then flush any buffered output to prevent it from being included in the file's content.
			if ( ob_get_contents() ) {
				ob_clean();
			}

			$filename = basename( $file_path );

			header( 'Content-Description: File Transfer' );
			header( 'Content-Type: application/octet-stream' );
			header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
			header( 'Expires: 0' );
			header( 'Cache-Control: must-revalidate' );
			header( 'Pragma: public' );
			header( 'Content-Length: ' . filesize( $file_path ) );

			readfile( $file_path );
			exit;
		} else {
			return false;
		}
	}

	/**
	 * Get tracking note for the order.
	 *
	 * @param Int $order_id ID of the order object.
	 *
	 * @return String
	 */
	protected function get_tracking_note( $order_id ) {

		if ( ! empty( $this->settings->get_woocommerce_email_text() ) ) {
			$tracking_note = $this->settings->get_woocommerce_email_text();
		} else {
			// translators: %s the current service.
			$tracking_note = sprintf( __( '%s Tracking Number: {tracking-link}', 'postnl-for-woocommerce' ), $this->service );
		}

		$tracking_link = $this->get_tracking_link( $order_id );

		if ( empty( $tracking_link ) ) {
			return '';
		}

		$tracking_note_new = str_replace( '{tracking-link}', $tracking_link, $tracking_note, $count );

		if ( 0 === $count ) {
			$tracking_note_new = $tracking_note . ' ' . $tracking_link;
		}

		return $tracking_note_new;
	}

	/**
	 * Get tracking url for the order.
	 *
	 * @param Int $order_id ID of the order object.
	 */
	protected function get_tracking_link( $order_id ) {
		$saved_data = $this->get_data( $order_id );
		$order      = wc_get_order( $order_id );

		if ( empty( $saved_data['label']['barcode'] ) || ! is_a( $order, 'WC_Order' ) ) {
			return '';
		}

		$tracking_url = Utils::generate_tracking_url( $saved_data['label']['barcode'], $order->get_shipping_country() );

		return sprintf( '<a href="%1$s" target="_blank">%2$s</a>', $tracking_url, $saved_data['label']['barcode'] );
	}
}
