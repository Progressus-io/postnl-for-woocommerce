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
		$dropoff_days = $this->get_dropoff_days();
		$fields       = array();

		if ( ! empty( $dropoff_days ) ) {
			$fields[] = array(
				'id'          => $this->prefix . 'delivery_day',
				'type'        => 'radio',
				'label'       => __( 'Delivery Day:', 'postnl-for-woocommerce' ),
				'description' => '',
				'class'       => 'postnl-checkout-field',
				'options'     => $this->get_dropoff_days(),
				'error_text'  => esc_html__( 'Please choose the delivery day!', 'postnl-for-woocommerce' ),
			);
		}

		return apply_filters( 'postnl_frontend_delivery_day_fields', $fields );
	}

	/**
	 * Check if this feature is enabled from the settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return (
			$this->settings->is_delivery_enabled() &&
			$this->settings->is_delivery_days_enabled() &&
			! empty( $this->get_fields() )
		);
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
