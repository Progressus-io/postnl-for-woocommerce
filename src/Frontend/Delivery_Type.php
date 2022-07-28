<?php
/**
 * Class Frontend/Delivery_Type file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delivery_Type
 *
 * @package PostNLWooCommerce\Frontend
 */
class Delivery_Type extends Base {

	/**
	 * Set the template filename.
	 */
	public function set_template_file() {
		$this->template_file = 'checkout/postnl-delivery-type.php';
	}

	/**
	 * List of frontend delivery type fields.
	 */
	public function get_fields() {
		return apply_filters(
			'postnl_frontend_delivery_type_fields',
			array(
				array(
					'id'          => $this->prefix . 'delivery_type',
					'type'        => 'select',
					'label'       => __( 'Delivery Type:', 'postnl-for-woocommerce' ),
					'description' => '',
					'class'       => 'postnl-checkout-field',
					'options'     => array(
						''         => esc_html__( '- Choose Delivery Type -', 'postnl-for-woocommerce' ),
						'standard' => esc_html__( 'Standard', 'postnl-for-woocommerce' ),
						'evening'  => esc_html__( 'Evening', 'postnl-for-woocommerce' ),
					),
					'error_text'  => esc_html__( 'Please choose the delivery type!', 'postnl-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Validate delivery type fields.
	 *
	 * @param array $posted_data Array of posted data.
	 *
	 * @return array
	 */
	public function validate_fields( $posted_data ) {
		foreach ( $this->get_fields() as $field ) {
			if ( empty( $posted_data[ $field['id'] ] ) ) {
				wc_add_notice( $field['error_text'], 'error' );
				return $data;
			}

			$data[ $field['id'] ] = sanitize_text_field( wp_unslash( $posted_data[ $field['id'] ] ) );
		}

		return $data;
	}
}
