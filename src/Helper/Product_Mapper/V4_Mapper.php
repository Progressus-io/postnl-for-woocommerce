<?php
/**
 * Class V4_Mapper file.
 *
 * @package PostNLWooCommerce\Helper\Product_Mapper
 */

namespace PostNLWooCommerce\Helper\Product_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure mapper: V1 combination → V4 ShipmentType + Services shape, or Legacy-only marker.
 *
 * Input combination shape:
 *   array(
 *       'origin'              => 'NL|BE',
 *       'destination'         => 'NL|BE|EU|ROW',
 *       'flow'                => 'delivery_day|pickup_points',
 *       'options'             => array(),
 *       'legacy_product_code' => optional string,
 *   )
 *
 * Runtime outcomes: has_v4_equivalent = true (28 rows) or false (60 rows).
 * needs_confirmation rows behave as Legacy-only at runtime until promoted to v4_mapped.
 *
 * Not-yet-available codes are always Legacy-only; see NOT_YET_AVAILABLE_CODES.
 * EU/ROW stay Legacy-only — see SDK_SERVICES_BUNDLE_GAP.
 *
 * Source: PostNL Product Overview documentation.
 */
class V4_Mapper {

	const NOT_YET_AVAILABLE_CODES = array(
		'1175',
		'3571',
		'3574',
		'4936',
		'4960',
		'4961',
		'4962',
		'4963',
		'4965',
		'4983',
	);

	const REASON_NOT_YET_AVAILABLE     = 'not_yet_available_in_v4';
	const REASON_NEEDS_CONFIRMATION    = 'needs_confirmation';
	const REASON_UNKNOWN_COMBINATION   = 'unknown_combination';
	const REASON_PRODUCT_CODE_MISMATCH = 'product_code_mismatch';

	// V4 Services DTO has no `bundle` property, so EU/ROW stay Legacy-only. Pending SDK update.
	const SDK_SERVICES_BUNDLE_GAP = 'sdk_v4_services_dto_missing_bundle_property';

	/**
	 * Returns true if a confirmed V4 equivalent exists for the given combination.
	 *
	 * @param array $combination See class PHPDoc.
	 * @return bool
	 */
	public static function has_v4_equivalent( array $combination ): bool {
		return static::map( $combination )['has_v4_equivalent'];
	}

	/**
	 * Maps a combination to its V4 result or a Legacy-only marker.
	 *
	 * @param array $combination See class PHPDoc.
	 * @return array
	 */
	public static function map( array $combination ): array {
		$origin      = $combination['origin'] ?? '';
		$destination = $combination['destination'] ?? '';
		$flow        = $combination['flow'] ?? '';
		$options     = $combination['options'] ?? array();
		$input_code  = $combination['legacy_product_code'] ?? null;

		$matrix = static::matrix();
		$key    = static::options_key( $options );

		$row = $matrix[ $origin ][ $destination ][ $flow ][ $key ] ?? null;

		if ( null === $row ) {
			return array(
				'has_v4_equivalent'   => false,
				'legacy_product_code' => $input_code,
				'source_row_id'       => 0,
				'legacy_only_reason'  => self::REASON_UNKNOWN_COMBINATION,
			);
		}

		if ( null !== $input_code && $row['legacy_product_code'] !== $input_code ) {
			return array(
				'has_v4_equivalent'   => false,
				'legacy_product_code' => $input_code,
				'source_row_id'       => 0,
				'legacy_only_reason'  => self::REASON_PRODUCT_CODE_MISMATCH,
			);
		}

		// Not-yet-available codes never map to V4, even if a future matrix edit says otherwise.
		if ( in_array( $row['legacy_product_code'], self::NOT_YET_AVAILABLE_CODES, true ) ) {
			return static::legacy_result( $row['source_row_id'], $row['legacy_product_code'], self::REASON_NOT_YET_AVAILABLE );
		}

		return $row;
	}

	/**
	 * Produces a canonical, order-independent lookup key from an options array.
	 *
	 * @param string[] $options Options flags.
	 * @return string
	 */
	private static function options_key( array $options ): string {
		if ( empty( $options ) ) {
			return '(base)';
		}
		sort( $options );
		return implode( '+', $options );
	}

	/**
	 * Builds a V4-mapped result entry.
	 *
	 * @param int    $row_id             CSV row_id for traceability.
	 * @param string $code               Legacy product code.
	 * @param string $shipment_type      V4 ShipmentType value ('parcel' or 'letterbox').
	 * @param array  $services           V4 Services flags.
	 * @param array  $delivery_location  V4 DeliveryLocation data (pickup flows only).
	 * @param array  $international_data V4 InternationalShipmentData hints.
	 * @return array
	 */
	private static function v4_result(
		int $row_id,
		string $code,
		string $shipment_type,
		array $services = array(),
		array $delivery_location = array(),
		array $international_data = array()
	): array {
		return array(
			'has_v4_equivalent'         => true,
			'legacy_product_code'       => $code,
			'source_row_id'             => $row_id,
			'shipmentType'              => $shipment_type,
			'services'                  => $services,
			'deliveryLocation'          => $delivery_location,
			'internationalShipmentData' => $international_data,
		);
	}

	/**
	 * Builds a Legacy-only result entry.
	 *
	 * @param int    $row_id CSV row_id for traceability.
	 * @param string $code   Legacy product code.
	 * @param string $reason One of the REASON_* constants.
	 * @return array
	 */
	private static function legacy_result( int $row_id, string $code, string $reason ): array {
		return array(
			'has_v4_equivalent'   => false,
			'legacy_product_code' => $code,
			'source_row_id'       => $row_id,
			'legacy_only_reason'  => $reason,
		);
	}

	/**
	 * 88-row combination matrix indexed for O(1) lookup.
	 *
	 * @return array
	 */
	private static function matrix(): array {
		static $cache = null;

		if ( null !== $cache ) {
			return $cache;
		}

		$nc     = self::REASON_NEEDS_CONFIRMATION;
		$nya    = self::REASON_NOT_YET_AVAILABLE;
		$pickup = array( 'pickupLocationId' => '<from_selected_location>' );

		$cache = array(
			'NL' => array(
				'NL'  => array(
					'delivery_day'  => array(
						'(base)'                         => self::v4_result( 1, '3085', 'parcel' ),
						'delivery_code_at_door+insured_shipping' => self::v4_result(
							2,
							'3085',
							'parcel',
							array(
								'deliveryConfirmation' => 'deliverycode',
								'insuredValue'         => '<order_total>',
							)
						),
						'only_home_address'              => self::v4_result( 3, '3385', 'parcel', array( 'statedAddressOnly' => true ) ),
						'return_no_answer'               => self::v4_result( 4, '3090', 'parcel', array( 'returnWhenNotHome' => true ) ),
						'signature_on_delivery'          => self::v4_result( 5, '3189', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
						'only_home_address+return_no_answer' => self::v4_result(
							6,
							'3390',
							'parcel',
							array(
								'returnWhenNotHome' => true,
								'statedAddressOnly' => true,
							)
						),
						'insured_shipping+return_no_answer+signature_on_delivery' => self::v4_result(
							7,
							'3094',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'insuredValue'         => '<order_total>',
								'returnWhenNotHome'    => true,
							)
						),
						'only_home_address+signature_on_delivery' => self::v4_result(
							8,
							'3089',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'statedAddressOnly'    => true,
							)
						),
						'insured_shipping+signature_on_delivery' => self::v4_result(
							9,
							'3087',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'insuredValue'         => '<order_total>',
							)
						),
						'return_no_answer+signature_on_delivery' => self::v4_result(
							10,
							'3389',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'returnWhenNotHome'    => true,
							)
						),
						'only_home_address+return_no_answer+signature_on_delivery' => self::v4_result(
							11,
							'3096',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'returnWhenNotHome'    => true,
								'statedAddressOnly'    => true,
							)
						),
						'letterbox'                      => self::v4_result( 12, '2928', 'letterbox' ),
						'id_check'                       => self::legacy_result( 13, '3438', $nc ),
						'id_check+signature_on_delivery' => self::legacy_result( 14, '3438', $nc ),
						'id_check+only_home_address'     => self::legacy_result( 15, '3438', $nc ),
						'id_check+only_home_address+signature_on_delivery' => self::legacy_result( 16, '3438', $nc ),
						'id_check+insured_shipping'      => self::legacy_result( 17, '3443', $nc ),
						'id_check+insured_shipping+signature_on_delivery' => self::legacy_result( 18, '3443', $nc ),
						'id_check+insured_shipping+only_home_address' => self::legacy_result( 19, '3443', $nc ),
						'id_check+insured_shipping+only_home_address+signature_on_delivery' => self::legacy_result( 20, '3443', $nc ),
					),
					'pickup_points' => array(
						'(base)'                    => self::v4_result( 21, '3533', 'parcel', array(), $pickup ),
						'insured_shipping'          => self::v4_result( 22, '3534', 'parcel', array( 'insuredValue' => '<order_total>' ), $pickup ),
						'id_check'                  => self::legacy_result( 23, '3571', $nya ),
						'id_check+insured_shipping' => self::legacy_result( 24, '3581', $nc ),
					),
				),
				'BE'  => array(
					'delivery_day'  => array(
						'(base)'                           => self::v4_result( 25, '4946', 'parcel' ),
						'only_home_address'                => self::v4_result( 26, '4941', 'parcel', array( 'statedAddressOnly' => true ) ),
						'signature_on_delivery'            => self::v4_result( 27, '4912', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
						'insured_shipping'                 => self::v4_result( 28, '4914', 'parcel', array( 'insuredValue' => '<order_total>' ) ),
						'insured_shipping+track_and_trace' => self::legacy_result( 29, '4914', $nc ),
						'insured_shipping+signature_on_delivery' => self::legacy_result( 30, '4914', $nc ),
						'insured_shipping+only_home_address' => self::legacy_result( 31, '4914', $nc ),
						'insured_shipping+only_home_address+signature_on_delivery' => self::legacy_result( 32, '4914', $nc ),
						'insured_shipping+signature_on_delivery+track_and_trace' => self::legacy_result( 33, '4914', $nc ),
						'insured_shipping+only_home_address+track_and_trace' => self::legacy_result( 34, '4914', $nc ),
						'insured_shipping+only_home_address+signature_on_delivery+track_and_trace' => self::legacy_result( 35, '4914', $nc ),
						'mailboxpacket'                    => self::legacy_result( 36, '6440', $nc ),
						'mailboxpacket+track_and_trace'    => self::legacy_result( 37, '6972', $nc ),
						'packets'                          => self::legacy_result( 38, '6405', $nc ),
						'packets+track_and_trace'          => self::legacy_result( 39, '6350', $nc ),
						'insured_shipping+packets+track_and_trace' => self::legacy_result( 40, '6906', $nc ),
					),
					'pickup_points' => array(
						'(base)' => self::legacy_result( 41, '4936', $nya ),
					),
				),
				'EU'  => array(
					'delivery_day'  => array(
						'(base)'                           => self::legacy_result( 42, '4907', $nc ),
						'track_and_trace'                  => self::legacy_result( 43, '4907', $nc ),
						'insured_shipping+track_and_trace' => self::legacy_result( 44, '4907', $nc ),
						'insured_plus+track_and_trace'     => self::legacy_result( 45, '4907', $nc ),
						'mailboxpacket'                    => self::legacy_result( 46, '6440', $nc ),
						'mailboxpacket+track_and_trace'    => self::legacy_result( 47, '6972', $nc ),
						'packets'                          => self::legacy_result( 48, '6405', $nc ),
						'packets+track_and_trace'          => self::legacy_result( 49, '6350', $nc ),
						'insured_shipping+packets+track_and_trace' => self::legacy_result( 50, '6906', $nc ),
					),
					'pickup_points' => array(
						'(base)' => self::legacy_result( 51, '4907', $nc ),
					),
				),
				'ROW' => array(
					'delivery_day'  => array(
						'(base)'                        => self::legacy_result( 52, '4909', $nc ),
						'track_and_trace'               => self::legacy_result( 53, '4909', $nc ),
						'insured_plus+track_and_trace'  => self::legacy_result( 54, '4909', $nc ),
						'mailboxpacket'                 => self::legacy_result( 55, '6440', $nc ),
						'mailboxpacket+track_and_trace' => self::legacy_result( 56, '6972', $nc ),
						'packets'                       => self::legacy_result( 57, '6405', $nc ),
						'packets+track_and_trace'       => self::legacy_result( 58, '6350', $nc ),
						'insured_shipping+packets+track_and_trace' => self::legacy_result( 59, '6906', $nc ),
					),
					'pickup_points' => array(
						'(base)' => self::legacy_result( 60, '4909', $nc ),
					),
				),
			),
			'BE' => array(
				'BE'  => array(
					'delivery_day'  => array(
						'(base)'                => self::legacy_result( 61, '4961', $nya ),
						'only_home_address'     => self::legacy_result( 62, '4960', $nya ),
						'signature_on_delivery' => self::legacy_result( 63, '4963', $nya ),
						'only_home_address+signature_on_delivery' => self::legacy_result( 64, '4962', $nya ),
						'insured_shipping+only_home_address' => self::legacy_result( 65, '4965', $nya ),
					),
					'pickup_points' => array(
						'(base)'           => self::v4_result( 66, '4880', 'parcel', array(), $pickup ),
						'insured_shipping' => self::v4_result( 67, '4878', 'parcel', array( 'insuredValue' => '<order_total>' ), $pickup ),
					),
				),
				'NL'  => array(
					'delivery_day'  => array(
						'(base)'                => self::v4_result( 68, '4890', 'parcel' ),
						'signature_on_delivery' => self::v4_result( 69, '4891', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
						'only_home_address'     => self::v4_result( 70, '4893', 'parcel', array( 'statedAddressOnly' => true ) ),
						'only_home_address+signature_on_delivery' => self::v4_result(
							71,
							'4894',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'statedAddressOnly'    => true,
							)
						),
						'id_check+only_home_address+signature_on_delivery' => self::legacy_result( 72, '4895', $nc ),
						'only_home_address+return_no_answer+signature_on_delivery' => self::v4_result(
							73,
							'4896',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'returnWhenNotHome'    => true,
								'statedAddressOnly'    => true,
							)
						),
						'insured_shipping+only_home_address+signature_on_delivery' => self::v4_result(
							74,
							'4897',
							'parcel',
							array(
								'deliveryConfirmation' => 'signature',
								'insuredValue'         => '<order_total>',
								'statedAddressOnly'    => true,
							)
						),
					),
					'pickup_points' => array(
						'signature_on_delivery' => self::v4_result( 75, '4898', 'parcel', array( 'deliveryConfirmation' => 'signature' ), $pickup ),
						'(base)'                => self::v4_result( 76, '4898', 'parcel', array(), $pickup ),
					),
				),
				'EU'  => array(
					'delivery_day' => array(
						'(base)'                           => self::legacy_result( 77, '4907', $nc ),
						'track_and_trace'                  => self::legacy_result( 78, '4907', $nc ),
						'insured_shipping+track_and_trace' => self::legacy_result( 79, '4907', $nc ),
						'insured_plus+track_and_trace'     => self::legacy_result( 80, '4907', $nc ),
						'mailboxpacket'                    => self::legacy_result( 81, '6440', $nc ),
						'mailboxpacket+track_and_trace'    => self::legacy_result( 82, '6972', $nc ),
						'packets'                          => self::legacy_result( 83, '6405', $nc ),
						'packets+track_and_trace'          => self::legacy_result( 84, '6350', $nc ),
						'insured_shipping+packets+track_and_trace' => self::legacy_result( 85, '6906', $nc ),
					),
				),
				'ROW' => array(
					'delivery_day' => array(
						'(base)'                       => self::legacy_result( 86, '4909', $nc ),
						'track_and_trace'              => self::legacy_result( 87, '4909', $nc ),
						'insured_plus+track_and_trace' => self::legacy_result( 88, '4909', $nc ),
					),
				),
			),
		);

		return $cache;
	}
}
