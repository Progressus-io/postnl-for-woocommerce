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
						'08:00-12:00' => esc_html__( 'Morning Delivery', 'postnl-for-woocommerce' ),
						'Daytime'     => esc_html__( 'Standard Shipment', 'postnl-for-woocommerce' ),
						'Evening'     => esc_html__( 'Evening Delivery', 'postnl-for-woocommerce' ),
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
				'ROW' => esc_html__( 'Non-EU Shipment', 'postnl-for-woocommerce' ),
			),
			'BE' => array(
				'BE'  => esc_html__( 'Belgium Domestic', 'postnl-for-woocommerce' ),
				'NL'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'EU'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'ROW' => esc_html__( 'Non-EU Shipment', 'postnl-for-woocommerce' ),
			),
		);
	}

	/**
	 * Products code & required options mapping.
	 *
	 * @return array[].
	 */
	public static function products_data() {
		return array(
			'NL' => array(
				'NL'  => array(
					'delivery_day'  => array(
						array(
							'combination' => array(),
							'code'        => '3085',
							'options'     => array(),
						),
						array(
							'combination' => array( 'only_home_address' ),
							'code'        => '3385',
							'options'     => array(),
						),
						array(
							'combination' => array( 'return_no_answer' ),
							'code'        => '3090',
							'options'     => array(),
						),
						// array(
						// 	'combination' => array( 'insured_shipping' ),
						// 	'code'        => '3087',
						// 	'options'     => array(),
						// ),
						array(
							'combination' => array( 'signature_on_delivery' ),
							'code'        => '3189',
							'options'     => array(),
						),
						array(
							'combination' => array( 'return_no_answer', 'only_home_address' ),
							'code'        => '3390',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'insured_shipping', 'return_no_answer' ),
							'code'        => '3094',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address' ),
							'code'        => '3089',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'signature_on_delivery' ),
							'code'        => '3087',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'return_no_answer' ),
							'code'        => '3389',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ),
							'code'        => '3096',
							'options'     => array(),
						),
						array(
							'combination' => array( 'letterbox' ),
							'code'        => '2928',
							'options'     => array(),
						),
						array(
							'combination' => array( 'id_check' ),
							'code'        => '3438',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						)
					),
					'pickup_points' => array(
						array(
							'combination' => array(),
							'code'        => '3533',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping' ),
							'code'        => '3534',
							'options'     => array(),
						),
					)
				),
				'BE'  => array(
					'delivery_day'  => array(
						array(
							'combination' => array(),
							'code'        => '4946',
							'options'     => array(),
						),
						array(
							'combination' => array( 'only_home_address' ),
							'code'        => '4941',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery' ),
							'code'        => '4912',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'mailboxpacket' ),
							'code'        => '6440',
							'options'     => array(),
						),
						array(
							'combination' => array( 'mailboxpacket', 'track_and_trace' ),
							'code'        => '6972',
							'options'     => array(),
						),
						array(
							'combination' => array( 'packets' ),
							'code'        => '6405',
							'options'     => array(),
						),
						array(
							'combination' => array( 'packets', 'track_and_trace' ),
							'code'        => '6350',
							'options'     => array(),
						),
						array(
							'combination' => array( 'packets', 'track_and_trace', 'insured_shipping' ),
							'code'        => '6906',
							'options'     => array(),
						),
					),
					'pickup_points' => array(
						array(
							'combination' => array(),
							'code'        => '4936',
							'options'     => array(),
						)
					),
				),
				'EU'  => array(
					'delivery_day'  => self::european_shipment_products(),
					'pickup_points' => array(
						array(
							'combination' => array(),
							'code'        => '4907',
							'options'     => array(
								array(
									'characteristic' => '005',
									'option'         => '025',
								),
								array(
									'characteristic' => '101',
									'option'         => '012',
								)
							)
						)
					),
				),
				'ROW' => array(
					'delivery_day'  => array_merge( self::globalpack_products(), self::EU_ROW_products() ),
					'pickup_points' => array(
						array(
							'combination' => array(),
							'code'        => '4909',
							'options'     => array(
								array(
									'characteristic' => '005',
									'option'         => '025',
								)
							)
						)
					),
				)
			),
			'BE' => array(
				'BE'  => array(
					'delivery_day'  => array(
						array(
							'combination' => array(),
							'code'        => '4961',
							'options'     => array(),
						),
						array(
							'combination' => array( 'only_home_address' ),
							'code'        => '4960',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery' ),
							'code'        => '4963',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address' ),
							'code'        => '4962',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'only_home_address' ),
							'code'        => '4965',
							'options'     => array(),
						)
					),
					'pickup_points' => array(
						array(
							'combination' => array(),
							'code'        => '4880',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping' ),
							'code'        => '4878',
							'options'     => array(),
						)
					),
				),
				'NL'  => array(
					'delivery_day'  => array(
						array(
							'combination' => array(),
							'code'        => '4890',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery' ),
							'code'        => '4891',
							'options'     => array(),
						),
						array(
							'combination' => array( 'only_home_address' ),
							'code'        => '4893',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address' ),
							'code'        => '4894',
							'options'     => array(),
						),
						array(
							'combination' => array( 'id_check', 'signature_on_delivery', 'only_home_address' ),
							'code'        => '4895',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ),
							'code'        => '4896',
							'options'     => array(),
						),
						array(
							'combination' => array( 'signature_on_delivery', 'only_home_address', 'insured_shipping' ),
							'code'        => '4897',
							'options'     => array(),
						)
					),
					'pickup_points' => array(
						array(
							'combination' => array( 'signature_on_delivery' ),
							'code'        => '4898',
							'options'     => array(),
						),
						array(
							'combination' => array(),
							'code'        => '4898',
							'options'     => array(),
						)
					),

				),
				'EU'  => array(
					'delivery_day' => self::european_shipment_products(),
				),
				'ROW' => array(
					'delivery_day' => self::globalpack_products(),
				)
			)
		);
	}

	/**
	 * Products code & options available for European and GlobalPack Shipments.
	 *
	 * @return array[].
	 */
	public static function EU_ROW_products() {
		return array(
			array(
				'combination' => array( 'mailboxpacket' ),
				'code'        => '6440',
				'options'     => array()
			),
			array(
				'combination' => array( 'track_and_trace', 'mailboxpacket' ),
				'code'        => '6972',
				'options'     => array()
			),
			array(
				'combination' => array( 'packets' ),
				'code'        => '6405',
				'options'     => array()
			),
			array(
				'combination' => array( 'track_and_trace', 'packets' ),
				'code'        => '6350',
				'options'     => array()
			),
			array(
				'combination' => array( 'track_and_trace', 'packets', 'insured_shipping' ),
				'code'        => '6906',
				'options'     => array()
			)
		);
	}

	/**
	 * Products code & options available for European Shipments.
	 *
	 * @return array[].
	 */
	public static function european_shipment_products() {
		return array_merge(
			array(
				array(
					'combination' => array(),
					'code'        => '4907',
					'options'     => array(
						array(
							'characteristic' => '005',
							'option'         => '025',
						),
						array(
							'characteristic' => '101',
							'option'         => '012',
						)
					)
				),
				array(
					'combination' => array( 'track_and_trace' ),
					'code'        => '4907',
					'options'     => array(
						array(
							'characteristic' => '005',
							'option'         => '025',
						),
						array(
							'characteristic' => '101',
							'option'         => '012',
						)
					)
				),
				array(
					'combination' => array( 'track_and_trace', 'insured_shipping' ),
					'code'        => '4907',
					'options'     => array(
						array(
							'characteristic' => '004',
							'option'         => '015',
						),
						array(
							'characteristic' => '101',
							'option'         => '012',
						)
					)
				),
				array(
					'combination' => array( 'track_and_trace', 'insured_plus' ),
					'code'        => '4907',
					'options'     => array(
						array(
							'characteristic' => '004',
							'option'         => '016',
						),
						array(
							'characteristic' => '101',
							'option'         => '012',
						)
					)
				)
			),
			self::EU_ROW_products()
		);
	}

	/**
	 * Products code & options available for GlobalPack Shipments.
	 *
	 * @return array[].
	 */
	public static function globalpack_products() {
		return array(
			array(
				'combination' => array(),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '004',
						'option'         => '015',
					)
				)
			),
			array(
				'combination' => array( 'track_and_trace' ),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '005',
						'option'         => '025',
					)
				)
			),
			array(
				'combination' => array( 'track_and_trace', 'insured_plus' ),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '004',
						'option'         => '016',
					)
				)
			)
		);
	}

	/**
	 * Label type mapping.
	 *
	 * @return array.
	 */
	public static function label_type_list() {
		return array(
			'NL' => array(
				// Return label is added here since smart return is not implemented yet.
				// If smart return is implemented, we might need to remove return-label from this list.
				'NL'  => array( 'label', 'return-label', 'buspakjeextra', 'printcodelabel' ),
				'BE'  => array( 'label' ),
				'EU'  => array( 'label' ),
				'ROW' => array( 'cn23', 'cp71', 'label', 'commercialinvoice' ),
			),
			'BE' => array(
				'BE'  => array( 'label' ),
				'NL'  => array( 'label' ),
				'EU'  => array( 'label' ),
				'ROW' => array( 'cn23', 'cp71', 'label', 'commercialinvoice' ),
			),
		);
	}

	/**
	 * Product code mapping.
	 *
	 * @return array.
	 */
	public static function option_available_list() {
		return array(
			'NL' => array(
				'NL'  => array( 'create_return_label', 'num_labels' ),
				'BE'  => array( 'create_return_label', 'num_labels' ),
				'EU'  => array( 'num_labels' ),
				'ROW' => array( 'num_labels' ),
			),
			'BE' => array(
				'BE'  => array( 'num_labels' ),
				'NL'  => array( 'num_labels' ),
				'EU'  => array( 'num_labels' ),
				'ROW' => array( 'num_labels' ),
			),
		);
	}

	/**
	 * Additional Product options mapping.
	 *
	 * @return array.
	 */
	public static function additional_product_options() {
		return array(
			'NL' => array(
				'NL' => array(
					'frontend_data' => array(
						'delivery_day' => array(
							'type' => array(
								'Evening'     => array(
									'characteristic' => '118',
									'option'         => '006',
								),
								'08:00-12:00' => array(
									'characteristic' => '118',
									'option'         => '008',
								),
							),
						),
					),
				),
			),
		);
	}

	/**
	 * List of countries that available for checkout feature.
	 *
	 * @return array.
	 */
	public static function available_country_for_checkout_feature() {
		return array(
			'NL' => array(
				'NL' => array( 'pickup_points', 'delivery_day', 'evening_delivery', '08:00-12:00' ),
				'BE' => array( 'pickup_points', 'delivery_day' ),
			),
			'BE' => array(
				'BE' => array( 'pickup_points' ),
				'NL' => array( 'pickup_points' ),
			),
		);
	}

	/**
	 * List of barcodes types used for specific products.
	 *
	 * @return array.
	 */
	public static function products_custom_barcode_types() {
		return array(
			'UE' => array(
				array( 'mailboxpacket' ),
				array( 'packets' )
			),
			'LA' => array(
				array( 'track_and_trace', 'mailboxpacket' ),
				array( 'track_and_trace', 'packets' )
			),
			'RI' => array(
				array( 'track_and_trace', 'packets', 'insured_shipping' )
			)
		);
	}
}
