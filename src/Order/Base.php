<?php
/**
 * Class Order\Base file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Rest_API\Barcode;
use PostNLWooCommerce\Rest_API\Shipping;
use PostNLWooCommerce\Rest_API\Return_Label;
use PostNLWooCommerce\Rest_API\Letterbox;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Library\CustomizedPDFMerger;
use Imagick;

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
	 * @var string nonce_key.
	 */
	protected $nonce_key = 'create-postnl-label';

	/**
	 * Current service.
	 *
	 * @var string service.
	 */
	protected $service = POSTNL_SERVICE_NAME;

	/**
	 * Prefix for meta box fields.
	 *
	 * @var string prefix.
	 */
	protected $prefix = POSTNL_SETTINGS_ID . '_';

	/**
	 * Meta name for saved fields.
	 *
	 * @var string.
	 */
	protected $meta_name;

	/**
	 * Is return activated flag meta name.
	 *
	 * @var string.
	 */
	protected $is_return_activated_meta;

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->settings                 = Settings::get_instance();
		$this->meta_name                = '_' . $this->prefix . 'order_metadata';
		$this->is_return_activated_meta = '_' . $this->prefix . 'return_activated';
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
			function ( $field ) {
				return ( ! empty( $field['nonce'] ) && true === $field['nonce'] );
			}
		);
	}

	/**
	 * Get shipping options from the PostNL meta, if those no-exists then form the plugin settings.
	 *
	 * @param \WC_Order $order
	 *
	 * @return array
	 *
	 * @internal
	 */
	public function get_shipping_options( $order ) {

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		// Return shipping options already selected by the user.
		$default_options = $this->get_backend_data( $order->get_id() );
		if ( ! empty( $default_options ) ) {
			return $default_options;
		}

		if ( Utils::is_adults_only_order( $order ) ) {
			return array( 'id_check' => 'yes' );
		}

		// Get from the plugin settings.
		$shipping_zone = Utils::get_shipping_zone( $order->get_shipping_country(), $order->get_shipping_state() );
		$frontend_data = $this->get_frontend_data( $order->get_id() );

		if ( ! empty( $frontend_data['dropoff_points'] ) ) {
			$shipping_zone = 'PICKUP';
		}

		if ( 'NL' === $shipping_zone && Utils::is_order_eligible_auto_letterbox( $order ) ) {
			return array( 'letterbox' => 'yes' );
		}

		return $this->settings->get_default_shipping_options( $shipping_zone );
	}

	/**
	 * List of meta box fields.
	 *
	 * @param \WC_Order $order WooCommerce order ID.
	 */
	public function meta_box_fields( $order = false ) {

		$default_options = $this->get_shipping_options( $order );
		$fields          = array(
			array(
				'id'            => $this->prefix . 'id_check',
				'type'          => 'checkbox',
				'label'         => __( 'ID Check: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['id_check'] ?? '',
				'show_in_bulk'  => false,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'insured_shipping',
				'type'          => 'checkbox',
				'label'         => __( 'Insured Shipping: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['insured_shipping'] ?? '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'insured_plus',
				'type'          => 'checkbox',
				'label'         => __( 'Insured Plus: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'return_no_answer',
				'type'          => 'checkbox',
				'label'         => __( 'Return if no answer: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['return_no_answer'] ?? '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'signature_on_delivery',
				'type'          => 'checkbox',
				'label'         => __( 'Signature on Delivery: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['signature_on_delivery'] ?? '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'only_home_address',
				'type'          => 'checkbox',
				'label'         => __( 'Only Home Address: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['only_home_address'] ?? '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'letterbox',
				'type'          => 'checkbox',
				'label'         => __( 'Letterbox: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => $default_options['letterbox'] ?? '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'packets',
				'type'          => 'checkbox',
				'label'         => __( 'Packets: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'mailboxpacket',
				'type'          => 'checkbox',
				'label'         => __( 'Mailbox Packet (International): ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'track_and_trace',
				'type'          => 'checkbox',
				'label'         => __( 'Track & Trace: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => false,
				'container'     => true,
			),
			array(
				'id'            => $this->prefix . 'break_2',
				'standard_feat' => false,
				'const_field'   => true,
				'type'          => 'break',
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
				'standard_feat'     => true,
				'const_field'       => false,
				'container'         => true,
			),
			array(
				'id'            => $this->prefix . 'label_nonce',
				'type'          => 'hidden',
				'nonce'         => true,
				'value'         => wp_create_nonce( $this->nonce_key ),
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => true,
				'container'     => true,
			),
		);

		if ( 'in_box' === $this->settings->get_return_shipment_and_labels() ) {
			$fields[] = array(
				'id'            => $this->prefix . 'create_return_label',
				'type'          => 'checkbox',
				'label'         => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'value'         => 'yes',
				'show_in_bulk'  => true,
				'standard_feat' => true,
				'const_field'   => false,
				'container'     => true,
			);
		}

		if ( 'A6' !== $this->settings->get_label_format() ) {
			$fields[] = array(
				'id'            => $this->prefix . 'position_printing_labels',
				'type'          => 'select',
				'label'         => __( 'Start position printing label: ', 'postnl-for-woocommerce' ),
				'placeholder'   => '',
				'description'   => '',
				'options'       => array(
					'top-left'     => __( 'Top Left', 'postnl-for-woocommerce' ),
					'top-right'    => __( 'Top Right', 'postnl-for-woocommerce' ),
					'bottom-left'  => __( 'Bottom Left', 'postnl-for-woocommerce' ),
					'bottom-right' => __( 'Bottom Right', 'postnl-for-woocommerce' ),
				),
				'value'         => '',
				'show_in_bulk'  => true,
				'standard_feat' => false,
				'const_field'   => true,
				'container'     => true,
			);
		}

		return apply_filters(
			'postnl_order_meta_box_fields',
			$fields
		);
	}

	/**
	 * Get available option based on the countries and chosen option in the frontend checkout.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return array.
	 */
	public function get_available_options( $order ) {
		if ( ! is_a( $order, 'WC_Order' ) ) {
			return array();
		}

		$product_map  = Mapping::products_data();
		$from_country = Utils::get_base_country();
		$to_country   = Utils::get_shipping_zone( $order->get_shipping_country(), $order->get_shipping_state() );
		$saved_data   = $this->get_data( $order->get_id() );

		if ( empty( $saved_data['frontend'] ) ) {
			$saved_data['frontend'] = array();
		}

		$selected_option   = 'delivery_day';
		$available_options = array();

		foreach ( $saved_data['frontend'] as $key => $value ) {
			$converted_key = Utils::convert_data_key( $key );

			if ( ! empty( $product_map[ $from_country ][ $to_country ][ $converted_key ] ) ) {
				$selected_option = $converted_key;
				break;
			}
		}

		foreach ( $product_map[ $from_country ][ $to_country ][ $selected_option ] as $product ) {
			$available_options = array_merge( $available_options, $product['combination'] );
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
			if ( in_array( $shipping_item->get_method_id(), $this->settings->get_supported_shipping_methods() ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Saving meta box in order admin page.
	 *
	 * @param int   $order_id Order post ID.
	 * @param array $meta_values PostNL meta values.
	 *
	 * @throws \Exception Throw error for invalid order id.
	 */
	public function save_meta_value( $order_id, $meta_values ) {

		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			throw new \Exception( esc_html__( 'Order does not exist!', 'postnl-for-woocommerce' ) );
		}

		$saved_data = $this->get_data( $order_id );

		// Check if label is already created.
		if ( isset( $saved_data['labels']['label'] ) ) {
			return array(
				'saved_data' => $saved_data,
				'labels'     => $saved_data['labels'],
			);
		}

		// Get array of nonce fields.
		$nonce_fields = array_values( $this->get_nonce_fields() );

		// Loop through inputs within id 'shipment-postnl-label-form'.
		foreach ( $this->meta_box_fields( $order_id ) as $field ) {
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

		$barcodes                        = $this->maybe_create_multi_barcodes( $label_post_data );
		$label_post_data['main_barcode'] = $barcodes[0]; // for MainBarcode.
		$label_post_data['barcodes']     = $barcodes;

		// Need to be refactored.
		$shipping_item_info                         = new Shipping\Item_Info( $label_post_data );
		$label_post_data['return_barcode']          = $this->maybe_create_return_barcode( $label_post_data, $shipping_item_info );
		$label_post_data['shipping_return_barcode'] = $this->maybe_create_shipping_return_barcode( $label_post_data, $shipping_item_info );
		$label_post_data['is_return_activated']     = $this->is_return_function_activated( $order );

		$labels = $this->create_label( $label_post_data );

		/*
		Temporarily commented.
		$return_post_data            = $label_post_data;
		$return_post_data['barcode'] = $this->create_barcode( $order );
		$return_labels               = $this->maybe_create_return_label( $label_post_data );

		$saved_data['labels'] = array_merge( $labels, $return_labels );
		*/

		$saved_data['barcodes'] = array_map(
			function ( $barc ) {
				return array(
					'value'      => $barc,
					'created_at' => current_time( 'timestamp' ),
				);
			},
			$barcodes
		);

		$saved_data['labels'] = $labels;

		if ( $this->settings->is_auto_complete_order_enabled() ) {
			// Updating the order status to completed.
			$order->update_status( 'completed' );
		}

		$order->update_meta_data( $this->meta_name, $saved_data );
		$order->save();

		// Need to add labels in array to remove the merged files later.
		return array(
			'saved_data' => $saved_data,
			'labels'     => $labels,
		);
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
	 * Get order information from frontend data.
	 *
	 * @param WC_Order $order Order object.
	 * @param String   $needle String that will be used to search the frontend value.
	 *
	 * @return array.
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
	 * Get delivery type string.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return String.
	 */
	public function get_delivery_type( $order ) {
		$from_country      = Utils::get_base_country();
		$to_country        = $order->get_shipping_country();
		$to_state          = $order->get_shipping_state();
		$delivery_type_map = Mapping::delivery_type();
		$filtered_frontend = $this->get_order_frontend_info( $order, '_type' );
		$destination       = Utils::get_shipping_zone( $to_country, $to_state );

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
	 * Delete meta data in order admin page.
	 *
	 * @param int $order_id Order post ID.
	 *
	 * @throws \Exception Throw error for invalid order.
	 */
	public function delete_meta_value( $order_id ) {
		$order = wc_get_order( $order_id );

		if ( ! is_a( $order, 'WC_Order' ) ) {
			throw new \Exception( esc_html__( 'Order does not exist!', 'postnl-for-woocommerce' ) );
		}

		$saved_data = $this->get_data( $order_id );

		// Delete label file.
		$this->delete_label( $saved_data );
		unset( $saved_data['backend'] );
		unset( $saved_data['labels'] );
		unset( $saved_data['barcodes'] );

		$order->update_meta_data( $this->meta_name, $saved_data );
		$order->save();

		return $saved_data;
	}

	/**
	 * Put the label content into PDF files.
	 *
	 * @param array    $response Response from PostNL API.
	 * @param WC_Order $order Order object.
	 * @param String   $parent_barcode Generated barcode string.
	 * @param String   $parent_label_type Type of label.
	 *
	 * @return array
	 */
	public function put_label_content( $response, $order, $parent_barcode, $parent_label_type ) {
		$message_types = Utils::get_label_response_type();
		$labels        = array();

		foreach ( $message_types as $type => $content_type ) {
			if ( empty( $response[ $type ] ) ) {
				continue;
			}

			foreach ( $response[ $type ] as $shipment_idx => $shipment_contents ) {

				if ( empty( $shipment_contents['Labels'] ) ) {
					continue 2;
				}

				foreach ( $shipment_contents['Labels'] as $label_idx => $label_contents ) {
					if ( empty( $label_contents['Content'] ) ) {
						continue 3;
					}

					$label_type      = ! empty( $label_contents['Labeltype'] ) ? sanitize_title( $label_contents['Labeltype'] ) : 'unknown-type';
					$label_extension = ! empty( $label_contents['OutputType'] ) ? sanitize_title( $label_contents['OutputType'] ) : 'pdf';
					$barcode         = $response[ $type ][ $shipment_idx ][ $content_type['barcode_key'] ];
					$barcode         = is_array( $barcode ) ? array_shift( $barcode ) : $barcode;
					$filename        = Utils::generate_label_name( $order->get_id(), $label_type, $barcode, 'A6', $label_extension );
					$filepath        = trailingslashit( POSTNL_UPLOADS_DIR ) . $filename;

					if ( wp_mkdir_p( POSTNL_UPLOADS_DIR ) && ! file_exists( $filepath ) ) {
						$content  = base64_decode( $label_contents['Content'] );
						$file_ret = file_put_contents( $filepath, $content );
					}

					$labels[] = array(
						'type'       => $label_type,
						'barcode'    => $barcode,
						'created_at' => current_time( 'timestamp' ),
						'filepath'   => $filepath,
					);
				}
			}
		}

		// if ( 'PDF' === $label_contents['OutputType'] ) {
		$labels = $this->maybe_merge_labels( $labels, $order, $parent_barcode, $parent_label_type );

		// }
		return $labels;
	}

	/**
	 * Create PostNL barcode for current order
	 *
	 * @param array $label_post_data .
	 *
	 * @return array
	 *
	 * @throws \Exception Error when response does not have Barcode value.
	 */
	public function create_barcode( $label_post_data ) {
		$data = array(
			'order'      => $label_post_data['order'],
			'saved_data' => $label_post_data['saved_data'],
		);

		$item_info = new Barcode\Item_Info( $data );
		$barcode   = new Barcode\Client( $item_info );
		$response  = $barcode->send_request();

		if ( empty( $response['Barcode'] ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the barcode.', 'postnl-for-woocommerce' )
			);
		}

		return $response['Barcode'];
	}

	/**
	 * Get multi barcodes from cacne or create new barcodes for current order.
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function maybe_create_multi_barcodes( $post_data ) {
		// Minimum number of labels is 1 so it will create at least 1 barcode.
		$num_labels = 1;
		$barcodes   = array();
		$saved_data = $post_data['saved_data'];

		if ( isset( $saved_data['backend']['num_labels'] ) && 1 < intval( $saved_data['backend']['num_labels'] ) ) {
			$num_labels = intval( $saved_data['backend']['num_labels'] );
		}

		for ( $i = 0; $i < $num_labels; $i++ ) {
			// Check if barcode has been created on the last 7 days before creating a new one.
			if ( ! empty( $saved_data['barcodes'][ $i ]['created_at'] ) && ! empty( $saved_data['barcodes'][ $i ]['value'] ) ) {
				$time_deviation = current_time( 'timestamp' ) - intval( $saved_data['barcodes'][ $i ]['created_at'] );

				if ( $time_deviation <= 7 * DAY_IN_SECONDS ) {
					$barcodes[ $i ] = $saved_data['barcodes'][ $i ]['value'];
					continue;
				}
			}

			$barcodes[ $i ] = $this->create_barcode( $post_data );
		}

		return $barcodes;
	}


	/**
	 * Create PostNL return barcode for current order
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array|Boolean
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function maybe_create_shipping_return_barcode( $label_post_data, $shipping_item_info ) {
		$shipment_return_type = $this->settings->get_return_shipment_and_labels();

		if ( 'none' === $shipment_return_type ) {
			return '';
		}

		if ( 'shipping_return' !== $shipment_return_type ) {
			return '';
		}

		if ( 'NL' !== $shipping_item_info->receiver['country'] ) {
			return '';
		}

		if ( '2928' === $shipping_item_info->shipment['shipping_product']['code'] ) {
			return '';
		}

		return $label_post_data['main_barcode'];
	}

	/**
	 * Create PostNL return barcode for current order
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array|Boolean
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function maybe_create_return_barcode( $post_data, $shipping_item_info ) {
		$shipment_return_type = $this->settings->get_return_shipment_and_labels();
		if ( 'none' === $shipment_return_type ) {
			return '';
		}

		if ( ! in_array( $shipping_item_info->receiver['country'], array( 'BE', 'NL' ) ) ) {
			return '';
		}

		if ( 'shipping_return' === $shipment_return_type &&
			'BE' !== $shipping_item_info->receiver['country'] ) {
			return '';
		}

		if ( 'in_box' === $shipment_return_type &&
			( ! isset( $post_data['saved_data']['backend']['create_return_label'] ) ||
				'yes' !== $post_data['saved_data']['backend']['create_return_label'] )
		) {
			return '';
		}

		$not_allowed = array( '6440', '6972', '6405', '6350', '6906' );
		if ( 'BE' === $shipping_item_info->receiver['country'] && in_array( $shipping_item_info->shipment['shipping_product']['code'], $not_allowed ) ) {
			return '';
		}

		$return_code = $this->settings->get_return_customer_code();

		$data = array(
			'order'         => $post_data['order'],
			'customer_code' => $return_code,
		);

		$item_info = new Barcode\Item_Info( $data );
		$barcode   = new Barcode\Client( $item_info );
		$response  = $barcode->send_request();

		if ( empty( $response['Barcode'] ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create return barcode.', 'postnl-for-woocommerce' )
			);
		}

		return $response['Barcode'];
	}

	/**
	 * Merging the label.
	 *
	 * @param Array    $labels List of labels.
	 * @param WC_Order $order Order object.
	 * @param String   $barcode Generated barcode string.
	 * @param String   $label_type Type of label.
	 *
	 * @return Array.
	 */
	public function maybe_merge_labels( $labels, $order, $barcode, $label_type ) {
		$label_format  = $this->settings->get_label_format();
		$merged_labels = array();

		if ( ! is_array( $labels ) ) {
			return $merged_labels;
		}

		if ( 1 === count( $labels ) && 'A6' === $label_format ) {
			return array(
				$label_type => array_shift( $labels ),
			);
		}

		$from_country    = Utils::get_base_country();
		$to_country      = $order->get_shipping_country();
		$to_state        = $order->get_shipping_state();
		$destination     = Utils::get_shipping_zone( $to_country, $to_state );
		$label_type_list = Mapping::label_type_list();

		$available_type = ( ! empty( $label_type_list[ $from_country ][ $destination ] ) ) ? $label_type_list[ $from_country ][ $destination ] : array( 'label' );

		$file_paths = array();
		foreach ( $labels as $label ) {
			if ( ! in_array( $label['type'], $available_type, true ) ) {
				continue;
			}

			$file_paths[] = $label['filepath'];
		}
		$extension = pathinfo( $file_paths[0], PATHINFO_EXTENSION );

		$filename    = Utils::generate_label_name( $order->get_id(), $label_type, $barcode, $label_format . '-merged', $extension );
		$merged_info = $this->merge_labels( $file_paths, $filename );

		$merged_labels[ $label_type ] = array(
			'type'         => $label_type,
			'barcode'      => $barcode,
			'created_at'   => current_time( 'timestamp' ),
			'filepath'     => $merged_info['filepath'],
			'merged_files' => $file_paths,
		);

		return $merged_labels;
	}

	/**
	 * Merge given files into the single one.
	 *
	 * @param array  $label_paths Array of files to be merged.
	 * @param string $merge_filename The final/merged filename with extension.
	 * @param string $start_position Start position for the pdf file only.
	 *
	 * @return array
	 */
	protected function merge_labels( $label_paths, $merge_filename, $start_position = 'top-left' ) {
		$extension = pathinfo( $label_paths[0], PATHINFO_EXTENSION );

		try {
			switch ( $extension ) {
				case 'pdf':
					return $this->merge_pdf_labels( $label_paths, $merge_filename, $start_position );
				case 'jpg':
					return $this->merge_jpg_files( $label_paths, $merge_filename, 'horizontal' );
				case 'gif':
					return $this->merge_graphic_labels( $label_paths, $merge_filename );
				case 'zpl_rle':
					return $this->merge_text_files( $label_paths, $merge_filename );
			}
		} catch ( \Exception $e ) {
		}

		return array();
	}

	/**
	 * Merge PDF Labels.
	 *
	 * @param array  $label_paths List of label path.
	 * @param String $merge_filename Name of the file after the merge process.
	 *
	 * @return array List of filepath that has been merged.
	 */
	protected function merge_pdf_labels( $label_paths, $merge_filename, $start_position = 'top-left' ) {
		$pdf          = new CustomizedPDFMerger();
		$merged_paths = array();

		foreach ( $label_paths as $path ) {
			$pdf->addPDF( $path, 'all' );
			$merged_paths[] = $path;
		}

		$filepath = trailingslashit( POSTNL_UPLOADS_DIR ) . $merge_filename;

		if ( isset( $_POST['postnl_position_printing_labels'] ) ) {
			$start_position = sanitize_text_field( $_POST['postnl_position_printing_labels'] );
		}

		$pdf->merge( 'file', $filepath, 'A', $start_position );

		return array(
			'merged_filepaths' => $merged_paths,
			'filepath'         => $filepath,
		);
	}

	/**
	 * Merge JPG Labels.
	 *
	 * @param array  $image_paths List of label path.
	 * @param String $merge_filename Name of the file after the merge process.
	 *
	 * @return array List of filepath that has been merged.
	 */
	protected function merge_jpg_files( $image_paths, $merge_filename, $direction = 'vertical' ) {
		$images = array();
		$width  = 0;
		$height = 0;

		// Load images and calculate dimensions
		foreach ( $image_paths as $path ) {
			$img      = imagecreatefromjpeg( $path );
			$images[] = $img;
			$width    = max( $width, imagesx( $img ) );
			$height  += imagesy( $img );
		}

		// Create a blank canvas for the merged image
		if ( $direction == 'horizontal' ) {
			$canvas = imagecreatetruecolor( $width * count( $images ), $height );
		} else {
			$canvas = imagecreatetruecolor( $width, $height );
		}

		// Set white background
		$white = imagecolorallocate( $canvas, 255, 255, 255 );
		imagefill( $canvas, 0, 0, $white );

		// Copy each image onto the canvas
		$offset = 0;
		foreach ( $images as $img ) {
			if ( $direction == 'horizontal' ) {
				imagecopy( $canvas, $img, $offset, 0, 0, 0, imagesx( $img ), imagesy( $img ) );
				$offset += imagesx( $img );
			} else {
				imagecopy( $canvas, $img, 0, $offset, 0, 0, imagesx( $img ), imagesy( $img ) );
				$offset += imagesy( $img );
			}
			imagedestroy( $img );
		}

		// Set the output file path
		$filepath = trailingslashit( POSTNL_UPLOADS_DIR ) . $merge_filename;

		// Save the merged image
		imagejpeg( $canvas, $filepath );
		imagedestroy( $canvas );

		return array(
			'merged_filepaths' => $image_paths,
			'filepath'         => $filepath,
		);
	}

	/**
	 * Merge graphic labels.
	 *
	 * @param Array  $label_paths List of label path.
	 * @param String $merge_filename Name of the file after the merge process.
	 *
	 * @return array
	 * @throws \ImagickException
	 */
	protected function merge_graphic_labels( $label_paths, $merge_filename ) {

		if ( empty( $label_paths ) ) {
			throw new Exception( __( 'There are no files to merge.', 'postnl-for-woocommerce' ) );
		}

		if ( ! class_exists( 'Imagick' ) ) {
			throw new Exception( __( '"Imagick" must be installed on the server to merge png files.', 'postnl-for-woocommerce' ) );
		}
		$merged_paths     = array();
		$final_label_path = trailingslashit( POSTNL_UPLOADS_DIR ) . $merge_filename;
		$final_label      = new \Imagick();
		foreach ( $label_paths as $path ) {
			$final_label->addImage( new \Imagick( $path ) );
			$merged_paths[] = $path;
		}

		/* Append the images into one */
		$final_label->resetIterator();
		$combined = $final_label->appendImages( true );
		$combined->setImageFormat( pathinfo( $final_label_path, PATHINFO_EXTENSION ) );
		$combined->writeimage( $final_label_path );

		return array(
			'merged_filepaths' => $merged_paths,
			'filepath'         => $final_label_path,
		);
	}

	/**
	 * Merge text files, for the ZEBRA printer.
	 *
	 * @param array  $label_paths List of label path.
	 * @param String $merge_filename Name of the file after the merge process.
	 *
	 * @return array
	 */
	protected function merge_text_files( $label_paths, $merge_filename ) {
		$merged_paths = array();
		$filepath     = trailingslashit( POSTNL_UPLOADS_DIR ) . $merge_filename;

		$output = fopen( $filepath, 'w' );

		foreach ( $label_paths as $path ) {
			if ( ! file_exists( $path ) ) {
				continue; // Skip if the file does not exist
			}

			$input = fopen( $path, 'r' );
			if ( ! $input ) {
				continue; // Skip if unable to open the file
			}

			// Read each line and write it to the output file
			while ( ( $line = fgets( $input ) ) !== false ) {
				fwrite( $output, $line );
			}

			fclose( $input ); // Close each input file after reading
			$merged_paths[] = $path;
		}

		fclose( $output ); // Close the output file after writing

		return array(
			'merged_filepaths' => $merged_paths,
			'filepath'         => $filepath,
		);
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
		$order              = $post_data['order'];
		$shipping_item_info = new Shipping\Item_Info( $post_data );
		$shipping           = new Shipping\Client( $shipping_item_info );
		$response           = $shipping->send_request();

		// Check any errors.
		$this->check_label_and_barcode( $response );

		$labels = $this->put_label_content( $response, $order, $post_data['main_barcode'], 'label' );

		if ( empty( $labels ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the label. Label content is missing', 'postnl-for-woocommerce' )
			);
		}

		return $labels;
	}

	/**
	 * Create PostNL return label for current order
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array|Boolean
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function maybe_create_return_label( $post_data ) {
		if ( 'yes' !== $post_data['saved_data']['backend']['create_return_label'] ) {
			return array();
		}

		$order = $post_data['order'];

		$item_info    = new Return_Label\Item_Info( $post_data );
		$return_label = new Return_Label\Client( $item_info );
		$response     = $return_label->send_request();

		$labels = $this->put_label_content( $response, $order, $post_data['main_barcode'], 'return-label' );

		if ( empty( $labels ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the return label. Label content is missing', 'postnl-for-woocommerce' )
			);
		}

		return $labels;
	}

	/**
	 * Create PostNL return label for current order
	 *
	 * @param array $post_data Order post data.
	 *
	 * @return array|Boolean
	 *
	 * @throws \Exception Error when response has an error.
	 */
	public function maybe_create_letterbox( $post_data ) {
		if ( 'yes' !== $post_data['saved_data']['backend']['letterbox'] ) {
			return array();
		}

		$order = $post_data['order'];

		$item_info    = new Letterbox\Item_Info( $post_data );
		$return_label = new Letterbox\Client( $item_info );
		$response     = $return_label->send_request();

		$labels = $this->put_label_content( $response, $order, $post_data['main_barcode'], 'letterbox' );

		if ( empty( $labels ) ) {
			throw new \Exception(
				esc_html__( 'Cannot create the letterbox. Label content is missing', 'postnl-for-woocommerce' )
			);
		}

		return $labels;
	}

	/**
	 * Make sure the barcode and label content is exists before printing.
	 *
	 * @param Array $response Response from API Call.
	 *
	 * @throws \Exception Error when barcode or label content is missing.
	 */
	public function check_label_and_barcode( $response ) {
		$message_types = Utils::get_label_response_type();

		$has_barcode = false;
		$has_content = false;
		foreach ( $message_types as $type => $content_type ) {
			if ( ! empty( $response[ $type ][0]['Barcode'] ) ) {
				$has_barcode = true;
			}

			if ( ! empty( $response[ $type ][0]['Labels'][0]['Content'] ) ) {
				$has_content = true;
			}
		}

		if ( ! $has_barcode ) {
			throw new \Exception(
				esc_html__( 'Cannot create the label. Barcode data is missing', 'postnl-for-woocommerce' )
			);
		}

		if ( ! $has_content ) {
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
	 * @return bool
	 */
	public function delete_label( $saved_data ) {
		if ( empty( $saved_data['labels']['label']['filepath'] ) ) {
			return false;
		}

		if ( isset( $saved_data['labels']['label']['merged_files'] ) ) {
			foreach ( $saved_data['labels']['label']['merged_files'] as $label_path ) {
				unlink( $label_path );
			}
		}

		return unlink( $saved_data['labels']['label']['filepath'] );
	}

	/**
	 * Generate download label url
	 *
	 * @param int    $order_id ID of the order post.
	 * @param String $label_type Type of the label. Possible options : 'label', 'return-label'.
	 *
	 * @return String.
	 */
	public function get_download_label_url( $order_id, $label_type = 'label' ) {
		$download_url = add_query_arg(
			array(
				'postnl_label_order_id' => $order_id,
				'label_type'            => $label_type,
				'postnl_label_nonce'    => wp_create_nonce( 'postnl_download_label_nonce' ),
			),
			home_url()
		);

		return $download_url;
	}

	/**
	 * Get label file.
	 *
	 * @return  void
	 * @since   1.0.0
	 */
	public function get_label_file() {
		if ( empty( $_GET['postnl_label_nonce'] ) ) {
			return;
		}

		if ( empty( $_GET['label_type'] ) ) {
			return;
		}

		// Check nonce before proceed.
		$nonce_result = check_ajax_referer( 'postnl_download_label_nonce', sanitize_text_field( wp_unslash( $_GET['postnl_label_nonce'] ) ), false );

		if ( empty( $_GET['postnl_label_order_id'] ) ) {
			return;
		}

		$order_id   = sanitize_text_field( wp_unslash( $_GET['postnl_label_order_id'] ) );
		$label_type = sanitize_text_field( wp_unslash( $_GET['label_type'] ) );

		if ( ! ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'view_order', $order_id ) ) ) {
			return;
		}

		$saved_data = $this->get_data( $order_id );

		if ( empty( $saved_data['labels'][ $label_type ]['filepath'] ) ) {
			return;
		}

		$this->download_label( $saved_data['labels'][ $label_type ]['filepath'] );
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

		if ( empty( $saved_data['labels']['label']['barcode'] ) || ! is_a( $order, 'WC_Order' ) ) {
			return '';
		}

		$tracking_url = Utils::generate_tracking_url( $saved_data['labels']['label']['barcode'], $order->get_shipping_country(), $order->get_shipping_postcode() );

		return sprintf( '<a href="%1$s" target="_blank" class="postnl-tracking-link">%2$s</a>', esc_url( $tracking_url ), $saved_data['labels']['label']['barcode'] );
	}

	/**
	 * Check if the order have the label data.
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
	 * Check if the order have the label file.
	 *
	 * @param \WC_Order $order current order object.
	 *
	 * @return boolean.
	 */
	public function have_label_file( $order ) {
		$order_data = $order->get_meta( $this->meta_name );

		return ! empty( $order_data['labels']['label']['filepath'] );
	}

	/**
	 * Check if the return function is activated for the order.
	 *
	 * @param int|\WC_Order $order .
	 *
	 * @return bool
	 */
	public function is_return_function_activated( $order ) {
		$order = ( $order instanceof \WC_Order ) ? $order : wc_get_order( $order );

		if ( ! $order instanceof \WC_Order ) {
			return false;
		}

		return 'yes' === $order->get_meta( $this->is_return_activated_meta );
	}
}
