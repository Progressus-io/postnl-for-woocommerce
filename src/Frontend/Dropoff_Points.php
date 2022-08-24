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
	 * Set the primary field name.
	 */
	public function set_primary_field_name() {
		$this->primary_field = 'dropoff_points';
	}

	/**
	 * Check if this feature is enabled from the settings.
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->settings->is_pickup_points_enabled();
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
		if ( empty( $response['PickupOptions'] ) ) {
			return $tabs;
		}

		$tabs[] = array(
			'id'   => $this->primary_field,
			'name' => esc_html__( 'Dropoff Points', 'postnl-for-woocommerce' ),
		);

		return $tabs;
	}

	/**
	 * Get dropoff points value from the API response.
	 *
	 * @param array $response PostNL API response.
	 * @param array $post_data Post data on checkout page.
	 *
	 * @return array.
	 */
	public function get_content_data( $response, $post_data ) {
		if ( empty( $response['PickupOptions'] ) ) {
			return array();
		}

		$pickup_points = $response['PickupOptions'];
		$return_data   = $this->get_init_content_data( $post_data );

		foreach ( $pickup_points as $pickup_point ) {
			$date = ! empty( $pickup_point['PickupDate'] ) ? $pickup_point['PickupDate'] : '';
			$type = ! empty( $pickup_point['Option'] ) ? $pickup_point['Option'] : '';

			if ( empty( $pickup_point['Locations'] ) ) {
				continue;
			}

			foreach ( $pickup_point['Locations'] as $dropoff_option ) {
				if ( empty( $dropoff_option['PartnerID'] ) || empty( $dropoff_option['PickupTime'] ) || empty( $dropoff_option['Distance'] ) || empty( $dropoff_option['Address'] ) ) {
					continue;
				}

				$timestamp = strtotime( $date );
				$company   = $dropoff_option['Address']['CompanyName'];
				$address   = implode( ', ', array_values( $dropoff_option['Address'] ) );

				$return_data['dropoff_options'][] = array(
					'partner_id' => $dropoff_option['PartnerID'],
					'loc_code'   => $dropoff_option['LocationCode'],
					'time'       => $dropoff_option['PickupTime'],
					'distance'   => $dropoff_option['Distance'],
					'date'       => $date,
					'company'    => $company,
					'address'    => $address,
					'type'       => $type,
				);
			}
		}

		return $return_data;
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
					'id'         => $this->prefix . $this->primary_field,
					'error_text' => esc_html__( 'Please choose the dropoff points!', 'postnl-for-woocommerce' ),
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_company',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_distance',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_id',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_date',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_time',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_type',
					'primary' => false,
					'hidden'  => true,
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
			if ( empty( $posted_data[ $field['id'] ] ) && ! empty( $field['error_text'] ) ) {
				wc_add_notice( $field['error_text'], 'error' );
				return $data;
			}

			$data[ $field['id'] ] = sanitize_text_field( wp_unslash( $posted_data[ $field['id'] ] ) );
		}

		return $data;
	}
}
