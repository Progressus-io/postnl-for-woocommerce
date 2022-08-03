<?php
/**
 * Class Frontend/Dropoff_Points file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dropoff_Points
 *
 * @package PostNLWooCommerce\Frontend
 */
class Dropoff_Points extends Base {

	/**
	 * Check if this feature is enabled from the settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->settings->is_pickup_points_enabled();
	}

	/**
	 * Set the template filename.
	 */
	public function set_template_file() {
		$this->template_file = 'checkout/postnl-dropoff-points.php';
	}

	/**
	 * List of frontend dropoff points fields.
	 */
	public function get_fields() {
		return apply_filters(
			'postnl_frontend_dropoff_points_fields',
			array(
				array(
					'id'          => $this->prefix . 'dropoff_points',
					'type'        => 'select',
					'label'       => __( 'Dropoff Points:', 'postnl-for-woocommerce' ),
					'description' => '',
					'class'       => 'postnl-checkout-field',
					'options'     => array(
						''         => esc_html__( '- Choose Dropoff Points -', 'postnl-for-woocommerce' ),
						'point_1' => esc_html__( 'Point 1', 'postnl-for-woocommerce' ),
						'point_2' => esc_html__( 'Point 2', 'postnl-for-woocommerce' ),
					),
					'error_text'  => esc_html__( 'Please choose the dropoff points!', 'postnl-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Validate dropoff points fields.
	 *
	 * @param array $data Array of posted data.
	 * @param array $posted_data Array of global _POST data.
	 *
	 * @return array
	 */
	public function validate_fields( $data, $posted_data ) {
		foreach ( $this->get_fields() as $field ) {
			$data[ $field['id'] ] = 'test';
		}

		return $data;
	}
}
