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

		$show_desc     = ( empty( $response['DeliveryOptions'] ) || ! $this->settings->is_delivery_days_enabled() );
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

				$return_data['dropoff_options'][] = array(
					'show_desc'  => $show_desc,
					'partner_id' => $dropoff_option['PartnerID'],
					'loc_code'   => $dropoff_option['LocationCode'],
					'time'       => $dropoff_option['PickupTime'],
					'distance'   => $dropoff_option['Distance'],
					'date'       => $date,
					'address'    => array(
						'company'   => $dropoff_option['Address']['CompanyName'],
						'address_1' => $dropoff_option['Address']['Street'],
						'address_2' => $dropoff_option['Address']['HouseNr'],
						'postcode'  => $dropoff_option['Address']['Zipcode'],
						'city'      => $dropoff_option['Address']['City'],
						'country'   => $dropoff_option['Address']['Countrycode'],
					),
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
					'id' => $this->prefix . $this->primary_field,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_company',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_distance',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_address_1',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_address_2',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_city',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_postcode',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_address_country',
					'primary' => false,
					'hidden'  => true,
				),
				array(
					'id'      => $this->prefix . $this->primary_field . '_partner_id',
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

			$data[ $field['id'] ] = ! empty( $posted_data[ $field['id'] ] ) ? sanitize_text_field( wp_unslash( $posted_data[ $field['id'] ] ) ) : '';
		}

		return $data;
	}
}
