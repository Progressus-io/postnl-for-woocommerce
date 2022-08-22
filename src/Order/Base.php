<?php
/**
 * Class Order\Base file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Rest_API\Shipping;

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
	 * Saved shipping settings.
	 *
	 * @var shipping_settings
	 */
	protected $shipping_settings = array();

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
	protected $service = 'PostNL';

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
					'id'                => $this->prefix . 'delivery_type',
					'type'              => 'text',
					'label'             => __( 'Delivery Type:', 'postnl-for-woocommerce' ),
					'description'       => '',
					'class'             => 'long',
					'value'             => 'Standard',
					'custom_attributes' => array( 'readonly' => 'readonly' ),
					'show_in_bulk'      => false,
					'container'         => true,
				),
				array(
					'id'           => $this->prefix . 'insured_shipping',
					'type'         => 'checkbox',
					'label'        => __( 'Insured Shipping: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
					'container'    => true,
				),
				array(
					'id'   => $this->prefix . 'break_1',
					'type' => 'break',
				),
				array(
					'id'           => $this->prefix . 'return_no_answer',
					'type'         => 'checkbox',
					'label'        => __( 'Return if no answer: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
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
					'container'    => true,
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
					'container'         => true,
				),
				array(
					'id'           => $this->prefix . 'create_return_label',
					'type'         => 'checkbox',
					'label'        => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
					'placeholder'  => '',
					'description'  => '',
					'value'        => '',
					'show_in_bulk' => true,
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
					'container'    => true,
				),
				array(
					'id'           => $this->prefix . 'label_nonce',
					'type'         => 'hidden',
					'nonce'        => true,
					'value'        => wp_create_nonce( $this->nonce_key ),
					'show_in_bulk' => true,
					'container'    => true,
				),
			)
		);
	}

	/**
	 * Generating meta box fields.
	 *
	 * @param array $fields list of fields.
	 */
	public function fields_generator( $fields ) {
		foreach ( $fields as $field ) {
			if ( empty( $field['id'] ) ) {
				continue;
			}

			if ( ! empty( $field['container'] ) && true === $field['container'] ) {
				?>
				<div class="shipment-postnl-row-container shipment-<?php echo esc_attr( $field['id'] ); ?>">
				<?php
			}

			switch ( $field['type'] ) {
				case 'select':
					woocommerce_wp_select( $field );
					break;

				case 'checkbox':
					woocommerce_wp_checkbox( $field );
					break;

				case 'hidden':
					woocommerce_wp_hidden_input( $field );
					break;

				case 'radio':
					woocommerce_wp_radio( $field );
					break;

				case 'textarea':
					woocommerce_wp_textarea_input( $field );
					break;

				case 'break':
					echo '<div class="postnl-break-line ' . esc_attr( $field['id'] ) . '"><hr id="' . esc_attr( $field['id'] ) . '" /></div>';
					break;

				case 'text':
				case 'number':
				default:
					woocommerce_wp_text_input( $field );
					break;
			}

			if ( ! empty( $field['container'] ) && true === $field['container'] ) {
				?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Get frontend data from Order object.
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
	 */
	public function create_label( $post_data ) {
		$order    = $post_data['order'];

		$shipping = new Shipping( $post_data );
		$response = $shipping->send_request();
		$response = json_decode( $response, true );

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
}
