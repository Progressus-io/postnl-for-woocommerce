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
			'id'    => 'dropoff_points',
			'price' => '$ 10',
			'name'  => esc_html__( 'Dropoff Points', 'postnl-for-woocommerce' ),
		);

		return $tabs;
	}

	/**
	 * Get dropoff points value from the API response.
	 *
	 * @param array $response PostNL API response.
	 *
	 * @return array.
	 */
	public function get_content_data( $response ) {
		if ( empty( $response['PickupOptions'] ) ) {
			return array();
		}

		$pickup_points = array_filter(
			$response['PickupOptions'],
			function ( $pickup_point ) {
				return ( ! empty( $pickup_point['Option'] ) && 'Pickup' === $pickup_point['Option'] );
			}
		);
		$pickup_point  = array_shift( $pickup_points );
		$date          = ! empty( $pickup_point['PickupDate'] ) ? $pickup_point['PickupDate'] : '';

		if ( empty( $pickup_point['Locations'] ) ) {
			return array();
		}

		$dropoff_options = array();

		foreach ( $pickup_point['Locations'] as $dropoff_option ) {
			if ( empty( $dropoff_option['PartnerID'] ) || empty( $dropoff_option['PickupTime'] ) || empty( $dropoff_option['Distance'] ) || empty( $dropoff_option['Address'] ) ) {
				continue;
			}

			$timestamp = strtotime( $date );
			$company   = $dropoff_option['Address']['CompanyName'];
			$address   = implode( ', ', array_values( $dropoff_option['Address'] ) );

			$dropoff_options[] = array(
				'partner_id' => $dropoff_option['PartnerID'],
				'loc_code'   => $dropoff_option['LocationCode'],
				'time'       => $dropoff_option['PickupTime'],
				'distance'   => $dropoff_option['Distance'],
				'date'       => $date,
				'company'    => $company,
				'address'    => $address,
			);
		}

		return $dropoff_options;
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
					'class'       => 'postnl-checkout-field select2',
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
