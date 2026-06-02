<?php
/**
 * Class V1_Mapper file.
 *
 * @package PostNLWooCommerce\Helper\Product_Mapper
 */

namespace PostNLWooCommerce\Helper\Product_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure data class: the V1 combination → product-code mapping.
 *
 */
class V1_Mapper {

	/**
	 * Products code & required options mapping.
	 *
	 * @return array[]
	 */
	public static function products_data(): array {
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
							'combination' => array( 'delivery_code_at_door', 'insured_shipping' ),
							'code'        => '3085',
							'options'     => array(
								array(
									'characteristic' => '004',
									'option'         => '020',
								),
							),
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
						),
						array(
							'combination' => array( 'id_check', 'signature_on_delivery' ),
							'code'        => '3438',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'only_home_address' ),
							'code'        => '3438',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'only_home_address', 'signature_on_delivery' ),
							'code'        => '3438',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'insured_shipping' ),
							'code'        => '3443',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'insured_shipping', 'signature_on_delivery' ),
							'code'        => '3443',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'insured_shipping', 'only_home_address' ),
							'code'        => '3443',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'insured_shipping', 'only_home_address', 'signature_on_delivery' ),
							'code'        => '3443',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
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
						array(
							'combination' => array( 'id_check' ),
							'code'        => '3571',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
						array(
							'combination' => array( 'id_check', 'insured_shipping' ),
							'code'        => '3581',
							'options'     => array(
								array(
									'characteristic' => '002',
									'option'         => '014',
								),
							),
						),
					),
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
							'combination' => array( 'insured_shipping', 'track_and_trace' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'signature_on_delivery' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'only_home_address' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'signature_on_delivery', 'only_home_address' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'track_and_trace', 'only_home_address' ),
							'code'        => '4914',
							'options'     => array(),
						),
						array(
							'combination' => array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery', 'only_home_address' ),
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
						),
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
								),
							),
						),
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
								),
							),
						),
					),
				),
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
						),
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
						),
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
						),
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
						),
					),
				),
				'EU'  => array(
					'delivery_day' => self::european_shipment_products(),
				),
				'ROW' => array(
					'delivery_day' => self::globalpack_products(),
				),
			),
		);
	}

	/**
	 * Products code & options available for European Shipments.
	 *
	 * @return array[]
	 */
	public static function european_shipment_products(): array {
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
						),
					),
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
						),
					),
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
						),
					),
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
						),
					),
				),
			),
			self::EU_ROW_products()
		);
	}

	/**
	 * Products code & options available for GlobalPack Shipments.
	 *
	 * @return array[]
	 */
	public static function globalpack_products(): array {
		return array(
			array(
				'combination' => array(),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '004',
						'option'         => '015',
					),
				),
			),
			array(
				'combination' => array( 'track_and_trace' ),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '005',
						'option'         => '025',
					),
				),
			),
			array(
				'combination' => array( 'track_and_trace', 'insured_plus' ),
				'code'        => '4909',
				'options'     => array(
					array(
						'characteristic' => '004',
						'option'         => '016',
					),
				),
			),
		);
	}

	/**
	 * Products code & options available for European and GlobalPack Shipments.
	 *
	 * @return array[]
	 */
	public static function EU_ROW_products(): array {
		return array(
			array(
				'combination' => array( 'mailboxpacket' ),
				'code'        => '6440',
				'options'     => array(),
			),
			array(
				'combination' => array( 'track_and_trace', 'mailboxpacket' ),
				'code'        => '6972',
				'options'     => array(),
			),
			array(
				'combination' => array( 'packets' ),
				'code'        => '6405',
				'options'     => array(),
			),
			array(
				'combination' => array( 'track_and_trace', 'packets' ),
				'code'        => '6350',
				'options'     => array(),
			),
			array(
				'combination' => array( 'track_and_trace', 'packets', 'insured_shipping' ),
				'code'        => '6906',
				'options'     => array(),
			),
		);
	}

	/**
	 * Shipment & Return labels options mapping.
	 *
	 * @return array[]
	 */
	public static function shipping_return_labels_options(): array {
		return array(
			'NL' => array(
				'NL' => array(
					'in_box'                       => array(
						'products' => array(),
						'options'  => array(
							array(
								'characteristic' => '152',
								'option'         => '028',
							),
						),
					),
					'shipping_return'              => array(
						'products' => array(
							'3085',
							'3438',
							'3090',
							'3189',
							'3385',
							'3087',
							'3389',
							'3094',
							'3390',
							'3096',
							'3089',
							'3533',
							'3534',
							'3443',
							'3571',
						),
						'options'  => array(
							array(
								'characteristic' => '152',
								'option'         => '026',
							),
						),
					),
					'return_all_labels_not_active' => array(
						'products' => array(
							'3085',
							'3438',
							'3090',
							'3189',
							'3385',
							'3087',
							'3389',
							'3094',
							'3390',
							'3096',
							'3089',
							'3533',
							'3534',
							'3443',
							'3571',
						),
						'options'  => array(
							array(
								'characteristic' => '152',
								'option'         => '026',
							),
							array(
								'characteristic' => '191',
								'option'         => '004',
							),
						),
					),
				),
				'BE' => array(
					'in_box' => array(
						'products' => array( '4946', '4941', '4912', '4914', '4936' ),
						'options'  => array(
							array(
								'characteristic' => '152',
								'option'         => '028',
							),
						),
					),
				),
			),
		);
	}

	/**
	 * Additional Product options mapping.
	 *
	 * @return array
	 */
	public static function additional_product_options(): array {
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
}
