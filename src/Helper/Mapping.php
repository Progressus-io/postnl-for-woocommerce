<?php
/**
 * Class Mapping file.
 *
 * @package PostNLWooCommerce\Helper
 */

namespace PostNLWooCommerce\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mapping
 *
 * @package PostNLWooCommerce\Mapping
 */
class Mapping {
	/**
	 * Delivery type mapping.
	 *
	 * @return array.
	 */
	public static function delivery_type() {
		return array(
			'NL' => array(
				'NL'  => array(
					'delivery_day_type'   => array(
						'Daytime' => esc_html__( 'Standard Shipment', 'postnl-for-woocommerce' ),
						'Evening' => esc_html__( 'Evening Delivery', 'postnl-for-woocommerce' ),
					),
					'dropoff_points_type' => array(
						'Pickup' => esc_html__( 'Pickup at PostNL Point', 'postnl-for-woocommerce' ),
					),
				),
				'BE'  => array(
					'delivery_day_type'   => array(
						'Daytime' => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
					),
					'dropoff_points_type' => array(
						'Pickup' => esc_html__( 'Pickup at PostNL Point Belgium', 'postnl-for-woocommerce' ),
					),
				),
				'EU'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'ROW' => esc_html__( 'Globalpack', 'postnl-for-woocommerce' ),
			),
			'BE' => array(
				'BE' => esc_html__( 'Belgium Domestic', 'postnl-for-woocommerce' ),
			),
		);
	}

	/**
	 * Product code mapping.
	 *
	 * @return Array
	 */
	public static function product_code() {
		return array(
			'NL' => array(
				'NL'  => array(
					'delivery_day'  => array(
						'3085' => array(),
						'3385' => array( 'only_home_address' ),
						'3090' => array( 'return_no_answer' ),
						'3087' => array( 'insured_shipping' ),
						'3189' => array( 'signature_on_delivery' ),
						'2928' => array( 'letterbox' ),
						'3390' => array( 'return_no_answer', 'only_home_address' ),
						'3094' => array( 'insured_shipping', 'return_no_answer' ),
						'3089' => array( 'signature_on_delivery', 'only_home_address' ),
						'3389' => array( 'signature_on_delivery', 'return_no_answer' ),
						'3096' => array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ),
					),
					'pickup_points' => array(
						'3085' => array(),
						'3533' => array( 'signature_on_delivery' ),
						'3534' => array( 'insured_shipping' ),
					),
				),
				'BE'  => array(
					'delivery_day'  => array(
						'4946' => array(),
						'4941' => array( 'only_home_address' ),
						'4912' => array( 'signature_on_delivery' ),
						'4914' => array( 'insured_shipping' ),
					),
					'pickup_points' => array(
						'4936' => array(),
					),
				),
				'EU'  => array(
					'delivery_day'  => array(
						'4944' => array(),
					),
					'pickup_points' => array(
						'4944' => array(),
					),
				),
				'ROW' => array(
					'delivery_day'  => array(
						'4945' => array(),
					),
					'pickup_points' => array(
						'4945' => array(),
					),
				),
			),
			'BE' => array(
				'BE' => array(
					'delivery_day'  => array(
						'4961' => array(),
						'4960' => array( 'only_home_address' ),
						'4963' => array( 'signature_on_delivery' ),
						'4962' => array( 'signature_on_delivery', 'only_home_address' ),
						'4965' => array( 'insured_shipping', 'only_home_address' ),
					),
					'pickup_points' => array(
						'4880' => array(),
						'4878' => array( 'insured_shipping' ),
					),
				),
			),
		);
	}

	/**
	 * Product options mapping.
	 *
	 * @return Array
	 */
	public static function product_options() {
		return array(
			'NL' => array(
				'NL' => array(
					'frontend_data' => array(
						'delivery_day' => array(
							'type' => array(
								'Evening' => array(
									'characteristic' => '118',
									'option'         => '006',
								),
							),
						),
					),
				),
			),
		);
	}
}
