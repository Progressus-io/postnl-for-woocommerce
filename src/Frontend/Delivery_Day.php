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
	 * Set the primary field name.
	 */
	public function set_primary_field_name() {
		$this->primary_field = 'delivery_day';
	}

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
				'id'         => $this->prefix . $this->primary_field,
				'primary'    => true,
				'error_text' => esc_html__( 'Please choose the delivery day!', 'postnl-for-woocommerce' ),
			),
			array(
				'id'      => $this->prefix . $this->primary_field . '_date',
				'primary' => false,
				'hidden'  => true,
			),
			array(
				'id'      => $this->prefix . $this->primary_field . '_from',
				'primary' => false,
				'hidden'  => true,
			),
			array(
				'id'      => $this->prefix . $this->primary_field . '_to',
				'primary' => false,
				'hidden'  => true,
			),
			array(
				'id'      => $this->prefix . $this->primary_field . '_price',
				'primary' => false,
				'hidden'  => true,
			),
			array(
				'id'      => $this->prefix . $this->primary_field . '_type',
				'primary' => false,
				'hidden'  => true,
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
	 * Adding a tab in the frontend checkout.
	 *
	 * @param array $tabs List of displayed tabs.
	 * @param array $response Response from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function add_checkout_tab( $tabs, $response ) {
		if ( empty( $response['DeliveryOptions'] ) ) {
			return $tabs;
		}

		$tabs[] = array(
			'id'   => $this->primary_field,
			'name' => esc_html__( 'Delivery Day', 'postnl-for-woocommerce' ),
		);

		return $tabs;
	}

	/**
	 * Get delivery option value from the API response.
	 *
	 * @param array $response PostNL API response.
	 * @param array $post_data Post data on checkout page.
	 *
	 * @return array.
	 */
	public function get_content_data( $response, $post_data ) {
		if ( empty( $response['DeliveryOptions'] ) ) {
			return array();
		}

		$non_standard_fees = Base::non_standard_fees_data();
		$return_data       = $this->get_init_content_data( $post_data );

		foreach ( $response['DeliveryOptions'] as $delivery_option ) {
			if ( empty( $delivery_option['DeliveryDate'] ) || empty( $delivery_option['Timeframe'] ) ) {
				continue;
			}

			$options = array_map(
				function( $timeframe ) use ( $non_standard_fees ) {
					$type  = array_shift( $timeframe['Options'] );
					$price = isset( $non_standard_fees[ $type ] ) ? $non_standard_fees[ $type ]['fee_price'] : 0;

					return array(
						'from'  => Utils::get_hour_min( $timeframe['From'] ),
						'to'    => Utils::get_hour_min( $timeframe['To'] ),
						'type'  => $type,
						'price' => $price,
					);
				},
				$delivery_option['Timeframe']
			);

			$timestamp    = strtotime( $delivery_option['DeliveryDate'] );
			$day          = strtolower( gmdate( 'D', $timestamp ) );
			$days_of_week = Utils::days_of_week();
			$day_name     = $days_of_week[ $day ];

			$return_data['delivery_options'][] = array(
				'day'     => $day_name,
				'date'    => gmdate( 'Y-m-d', $timestamp ),
				'options' => $options,
			);
		}

		return $return_data;
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
			if ( empty( $posted_data[ $field['id'] ] ) && ! empty( $field['error_text'] ) ) {
				wc_add_notice( $field['error_text'], 'error' );
				return $data;
			}

			$data[ $field['id'] ] = sanitize_text_field( wp_unslash( $posted_data[ $field['id'] ] ) );
		}

		return $data;
	}
}
