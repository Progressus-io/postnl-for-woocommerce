<?php
/**
 * Class Frontend/Delivery_Day file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delivery_Day
 *
 * @package PostNLWooCommerce\Frontend
 */
class Delivery_Day extends Base {

	/**
	 * Set the template filename.
	 */
	public function set_template_file() {
		$this->template_file = 'checkout/postnl-delivery-day.php';
	}

	/**
	 * List of frontend delivery day fields.
	 */
	public function get_fields() {
		$fields = array(
			array(
				'id'          => $this->prefix . 'delivery_day',
				'type'        => 'select',
				'label'       => __( 'Delivery Day:', 'postnl-for-woocommerce' ),
				'description' => '',
				'class'       => 'postnl-checkout-field',
				'options'     => array(
					'2022-08-01' => gmdate( 'F j Y', strtotime( '2022-08-01' ) ),
					'2022-08-02' => gmdate( 'F j Y', strtotime( '2022-08-02' ) ),
				),
				'error_text'  => esc_html__( 'Please choose the delivery day!', 'postnl-for-woocommerce' ),
			),
		);

		return apply_filters( 'postnl_frontend_delivery_day_fields', $fields );
	}

	/**
	 * Check if this feature is enabled from the settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->settings->is_delivery_days_enabled();
	}

	/**
	 * Get the enabled dropoff days from the settings.
	 *
	 * @return array
	 */
	public function get_dropoff_days() {
		$dropoff_days = $this->settings->get_dropoff_days();

		return array_filter(
			Utils::days_of_week(),
			function( $key ) use ( $dropoff_days ) {
				return in_array( $key, $dropoff_days, true );
			},
			ARRAY_FILTER_USE_KEY
		);
	}

	/**
	 * Validate delivery type fields.
	 *
	 * @param array $data Array of posted data.
	 * @param array $posted_data Array of global _POST data.
	 *
	 * @return array
	 */
	public function validate_fields( $data, $posted_data ) {
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
