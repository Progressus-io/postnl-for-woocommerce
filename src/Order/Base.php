<?php
/**
 * Class Order\Base file.
 *
 * @package PostNLWooCommerce\Order
 */

namespace PostNLWooCommerce\Order;

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
	 * Current service.
	 *
	 * @var service
	 */
	protected $service = 'PostNL';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Abstract function for collection of hooks when initiation.
	 */
	abstract public function init_hooks();

	/**
	 * Generating meta box fields.
	 */
	public function meta_box_fields() {
		return apply_filters(
			'postnl_order_meta_box_fields',
			array(
				array(
					'id'    => 'postnl_label_nonce',
					'type'  => 'hidden',
					'value' => wp_create_nonce( 'create-postnl-label' ),
				),
				array(
					'id'          => 'postnl_insured_shipping',
					'type'        => 'checkbox',
					'label'       => __( 'Insured Shipping: ', 'postnl-for-woocommerce' ),
					'placeholder' => '',
					'description' => '',
					'value'       => '',
					'container'   => true,
				),
				array(
					'id'          => 'postnl_return_no_answer',
					'type'        => 'checkbox',
					'label'       => __( 'Return if no answer: ', 'postnl-for-woocommerce' ),
					'placeholder' => '',
					'description' => '',
					'value'       => '',
					'container'   => true,
				),
				array(
					'id'          => 'postnl_signature_on_delivery',
					'type'        => 'checkbox',
					'label'       => __( 'Signature on Delivery: ', 'postnl-for-woocommerce' ),
					'placeholder' => '',
					'description' => '',
					'value'       => '',
					'container'   => true,
				),
				array(
					'id'          => 'postnl_only_home_address',
					'type'        => 'checkbox',
					'label'       => __( 'Only Home Address: ', 'postnl-for-woocommerce' ),
					'placeholder' => '',
					'description' => '',
					'value'       => '',
					'container'   => true,
				),
				array(
					'id'                => 'postnl_num_labels',
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
					'container'         => true,
				),
				array(
					'id'          => 'postnl_create_return_label',
					'type'        => 'checkbox',
					'label'       => __( 'Create Return Label: ', 'postnl-for-woocommerce' ),
					'placeholder' => '',
					'description' => '',
					'value'       => '',
					'container'   => true,
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

			if ( ! empty( $field['use_container'] ) && true === $field['use_container'] ) {
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
					echo '<div class="postnl-break-line ' . esc_attr( $field['id'] ) . '"><hr /></div>';
					break;

				case 'text':
				case 'number':
				default:
					woocommerce_wp_text_input( $field );
					break;
			}

			if ( ! empty( $field['use_container'] ) && true === $field['use_container'] ) {
				?>
				</div>
				<?php
			}
		}
	}

	/**
	 * Additional fields of the meta box for child class.
	 */
	public function meta_box_html() {
		?>
		<div id="shipment-postnl-label-form">
			<?php $this->fields_generator( $this->meta_box_fields() ); ?>
		</div>
		<?php
	}
}
