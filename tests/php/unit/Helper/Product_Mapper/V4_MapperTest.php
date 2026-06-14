<?php
/**
 * Unit tests for V4_Mapper.
 *
 * @package PostNLWooCommerce\Tests\Unit\Helper\Product_Mapper
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Unit\Helper\Product_Mapper;

use PostNLWooCommerce\Helper\Product_Mapper\V1_Mapper;
use PostNLWooCommerce\Helper\Product_Mapper\V4_Mapper;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * Exhaustive tests for V4_Mapper, driven by the 88-row combination matrix.
 *
 * Coverage:
 *  - Total row count = 88 (from provider data and from runtime calls)
 *  - has_v4_equivalent true  count = 28 (provider + runtime)
 *  - has_v4_equivalent false count = 60 (provider + runtime)
 *  - All v4_mapped rows: expected shipmentType / services / deliveryLocation / internationalShipmentData
 *  - All legacy_only rows: reason = not_yet_available_in_v4
 *  - All needs_confirmation rows: reason = needs_confirmation
 *  - NOT_YET_AVAILABLE_CODES contains all 10 required codes (incl. absent 1175, 3574, 4983)
 *  - Unknown combinations return reason = unknown_combination
 *  - Optional legacy_product_code validation (REASON_PRODUCT_CODE_MISMATCH)
 *  - No silent gaps: every V1_Mapper::products_data() combination maps to a V4 shape or an
 *    explicit Legacy-only reason, never REASON_UNKNOWN_COMBINATION
 *  - SDK bundle gap marker and the load-bearing not-yet-available guard
 *
 * Source: PostNL Product Overview documentation; the 88 rows mirror V1_Mapper::products_data().
 *
 * @covers \PostNLWooCommerce\Helper\Product_Mapper\V4_Mapper
 */
class V4_MapperTest extends UnitTestCase {

	// =========================================================================
	// Structural / count assertions (provider-based)
	// =========================================================================

	/**
	 * @testdox combination_matrix_provider() covers exactly 88 rows
	 */
	public function test_total_covered_rows(): void {
		$this->assertCount( 88, self::combination_matrix_provider() );
	}

	/**
	 * @testdox Provider expected data: has_v4_equivalent true count = 28
	 */
	public function test_provider_v4_equivalent_true_count(): void {
		$count = 0;
		foreach ( self::combination_matrix_provider() as $row ) {
			if ( true === $row[1]['has_v4_equivalent'] ) {
				$count++;
			}
		}
		$this->assertSame( 28, $count, 'Exactly 28 rows must be marked has_v4_equivalent=true.' );
	}

	/**
	 * @testdox Provider expected data: has_v4_equivalent false count = 60
	 */
	public function test_provider_v4_equivalent_false_count(): void {
		$count = 0;
		foreach ( self::combination_matrix_provider() as $row ) {
			if ( false === $row[1]['has_v4_equivalent'] ) {
				$count++;
			}
		}
		$this->assertSame( 60, $count, 'Exactly 60 rows must be marked has_v4_equivalent=false.' );
	}

	// =========================================================================
	// Runtime count assertions (calls actual V4_Mapper::has_v4_equivalent())
	// =========================================================================

	/**
	 * @testdox Runtime: V4_Mapper::has_v4_equivalent() returns true for exactly 28 provider inputs
	 */
	public function test_runtime_v4_equivalent_true_count(): void {
		$count = 0;
		foreach ( self::combination_matrix_provider() as $row ) {
			if ( V4_Mapper::has_v4_equivalent( $row[0] ) ) {
				$count++;
			}
		}
		$this->assertSame( 28, $count );
	}

	/**
	 * @testdox Runtime: V4_Mapper::has_v4_equivalent() returns false for exactly 60 provider inputs
	 */
	public function test_runtime_v4_equivalent_false_count(): void {
		$count = 0;
		foreach ( self::combination_matrix_provider() as $row ) {
			if ( ! V4_Mapper::has_v4_equivalent( $row[0] ) ) {
				$count++;
			}
		}
		$this->assertSame( 60, $count );
	}

	// =========================================================================
	// NOT_YET_AVAILABLE_CODES constant
	// =========================================================================

	/**
	 * @testdox NOT_YET_AVAILABLE_CODES contains exactly 10 entries
	 */
	public function test_not_yet_available_codes_count(): void {
		$this->assertCount( 10, V4_Mapper::NOT_YET_AVAILABLE_CODES );
	}

	/**
	 * @testdox NOT_YET_AVAILABLE_CODES contains all 10 required codes
	 */
	public function test_not_yet_available_codes_contains_all_required(): void {
		$required = array( '1175', '3571', '3574', '4936', '4960', '4961', '4962', '4963', '4965', '4983' );
		foreach ( $required as $code ) {
			$this->assertContains( $code, V4_Mapper::NOT_YET_AVAILABLE_CODES );
		}
	}

	/**
	 * @testdox Absent codes 1175, 3574, 4983 (no V1 matrix entry) are blocked via NOT_YET_AVAILABLE_CODES
	 */
	public function test_absent_not_yet_available_codes_1175_3574_4983(): void {
		foreach ( array( '1175', '3574', '4983' ) as $code ) {
			$this->assertContains( $code, V4_Mapper::NOT_YET_AVAILABLE_CODES );
		}
	}

	// =========================================================================
	// Unknown combination
	// =========================================================================

	/**
	 * @testdox Unknown option returns false with reason unknown_combination
	 */
	public function test_unknown_combination_returns_false_with_explicit_reason(): void {
		$result = V4_Mapper::map(
			array(
				'origin'      => 'NL',
				'destination' => 'NL',
				'flow'        => 'delivery_day',
				'options'     => array( 'completely_unknown_option' ),
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_UNKNOWN_COMBINATION, $result['legacy_only_reason'] );
		$this->assertNull( $result['legacy_product_code'] );
	}

	/**
	 * @testdox Unknown origin returns false with reason unknown_combination
	 */
	public function test_unknown_origin_returns_explicit_result(): void {
		$result = V4_Mapper::map(
			array(
				'origin'      => 'DE',
				'destination' => 'NL',
				'flow'        => 'delivery_day',
				'options'     => array(),
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_UNKNOWN_COMBINATION, $result['legacy_only_reason'] );
	}

	/**
	 * @testdox Empty combination array returns false with reason unknown_combination
	 */
	public function test_empty_combination_returns_explicit_result(): void {
		$result = V4_Mapper::map( array() );

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_UNKNOWN_COMBINATION, $result['legacy_only_reason'] );
	}

	// =========================================================================
	// Optional legacy_product_code validation
	// =========================================================================

	/**
	 * @testdox Matching legacy_product_code on a v4_mapped row returns the V4 result unchanged
	 */
	public function test_matching_product_code_on_v4_row_returns_v4_result(): void {
		$result = V4_Mapper::map(
			array(
				'origin'              => 'NL',
				'destination'         => 'NL',
				'flow'                => 'delivery_day',
				'options'             => array( 'signature_on_delivery' ),
				'legacy_product_code' => '3189',
			)
		);

		$this->assertTrue( $result['has_v4_equivalent'] );
		$this->assertSame( '3189', $result['legacy_product_code'] );
		$this->assertSame( 'signature', $result['services']['deliveryConfirmation'] );
	}

	/**
	 * @testdox Non-matching legacy_product_code on a v4_mapped row returns product_code_mismatch
	 */
	public function test_mismatched_product_code_on_v4_row_returns_mismatch(): void {
		$result = V4_Mapper::map(
			array(
				'origin'              => 'NL',
				'destination'         => 'NL',
				'flow'                => 'delivery_day',
				'options'             => array( 'signature_on_delivery' ),
				'legacy_product_code' => '9999',
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_PRODUCT_CODE_MISMATCH, $result['legacy_only_reason'] );
		$this->assertSame( '9999', $result['legacy_product_code'] );
		$this->assertSame( 0, $result['source_row_id'] );
	}

	/**
	 * @testdox Matching legacy_product_code on a legacy-only row returns the legacy result unchanged
	 */
	public function test_matching_product_code_on_legacy_row_returns_legacy_result(): void {
		$result = V4_Mapper::map(
			array(
				'origin'              => 'NL',
				'destination'         => 'NL',
				'flow'                => 'pickup_points',
				'options'             => array( 'id_check' ),
				'legacy_product_code' => '3571',
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_NOT_YET_AVAILABLE, $result['legacy_only_reason'] );
		$this->assertSame( '3571', $result['legacy_product_code'] );
	}

	/**
	 * @testdox Non-matching legacy_product_code on a legacy-only row returns product_code_mismatch
	 */
	public function test_mismatched_product_code_on_legacy_row_returns_mismatch(): void {
		$result = V4_Mapper::map(
			array(
				'origin'              => 'NL',
				'destination'         => 'NL',
				'flow'                => 'pickup_points',
				'options'             => array( 'id_check' ),
				'legacy_product_code' => '9999',
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_PRODUCT_CODE_MISMATCH, $result['legacy_only_reason'] );
		$this->assertSame( '9999', $result['legacy_product_code'] );
	}

	/**
	 * @testdox Unknown combination with legacy_product_code returns unknown_combination (not mismatch)
	 */
	public function test_unknown_combination_with_code_returns_unknown_combination(): void {
		$result = V4_Mapper::map(
			array(
				'origin'              => 'NL',
				'destination'         => 'NL',
				'flow'                => 'delivery_day',
				'options'             => array( 'completely_unknown_option' ),
				'legacy_product_code' => '3085',
			)
		);

		$this->assertFalse( $result['has_v4_equivalent'] );
		$this->assertSame( V4_Mapper::REASON_UNKNOWN_COMBINATION, $result['legacy_only_reason'] );
		$this->assertSame( '3085', $result['legacy_product_code'] );
	}

	/**
	 * @testdox No legacy_product_code in combination falls through to normal map result
	 */
	public function test_no_product_code_in_combination_does_not_trigger_mismatch(): void {
		$result = V4_Mapper::map(
			array(
				'origin'      => 'NL',
				'destination' => 'NL',
				'flow'        => 'delivery_day',
				'options'     => array( 'signature_on_delivery' ),
			)
		);

		$this->assertTrue( $result['has_v4_equivalent'] );
		$this->assertArrayNotHasKey( 'legacy_only_reason', $result );
	}

	// =========================================================================
	// has_v4_equivalent() delegation spot-checks
	// =========================================================================

	/**
	 * @testdox has_v4_equivalent() returns true for a known v4_mapped combination
	 */
	public function test_has_v4_equivalent_returns_true_for_v4_mapped(): void {
		$this->assertTrue(
			V4_Mapper::has_v4_equivalent(
				array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery' ) )
			)
		);
	}

	/**
	 * @testdox has_v4_equivalent() returns false for a not_yet_available combination
	 */
	public function test_has_v4_equivalent_returns_false_for_not_yet_available(): void {
		$this->assertFalse(
			V4_Mapper::has_v4_equivalent(
				array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array( 'id_check' ) )
			)
		);
	}

	/**
	 * @testdox has_v4_equivalent() returns false for a needs_confirmation combination
	 */
	public function test_has_v4_equivalent_returns_false_for_needs_confirmation(): void {
		$this->assertFalse(
			V4_Mapper::has_v4_equivalent(
				array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check' ) )
			)
		);
	}

	// =========================================================================
	// No silent gaps — derived from V1_Mapper::products_data()
	// =========================================================================

	/**
	 * Flattens V1_Mapper::products_data() into V4_Mapper combination inputs.
	 *
	 * @return array<array{origin:string,destination:string,flow:string,options:array}>
	 */
	private static function v1_combinations(): array {
		$combinations = array();
		foreach ( V1_Mapper::products_data() as $origin => $destinations ) {
			foreach ( $destinations as $destination => $flows ) {
				foreach ( $flows as $flow => $entries ) {
					foreach ( $entries as $entry ) {
						$combinations[] = array(
							'origin'      => $origin,
							'destination' => $destination,
							'flow'        => $flow,
							'options'     => $entry['combination'],
						);
					}
				}
			}
		}
		return $combinations;
	}

	/**
	 * @testdox V1_Mapper::products_data() flattens to exactly 88 combinations (binds V1 source to matrix)
	 */
	public function test_v1_products_data_covers_88_combinations(): void {
		$this->assertCount( 88, self::v1_combinations() );
	}

	/**
	 * @testdox No silent gaps: every V1 combination maps to a V4 shape or an explicit Legacy-only reason
	 */
	public function test_every_v1_combination_maps_without_silent_gaps(): void {
		$allowed_legacy = array(
			V4_Mapper::REASON_NEEDS_CONFIRMATION,
			V4_Mapper::REASON_NOT_YET_AVAILABLE,
		);

		foreach ( self::v1_combinations() as $combination ) {
			$result = V4_Mapper::map( $combination );
			$label  = sprintf(
				'%s→%s/%s [%s]',
				$combination['origin'],
				$combination['destination'],
				$combination['flow'],
				implode( ',', $combination['options'] ) ?: '(base)'
			);

			if ( $result['has_v4_equivalent'] ) {
				$this->assertArrayNotHasKey( 'legacy_only_reason', $result, "Unexpected legacy reason for {$label}" );
				continue;
			}

			$this->assertNotSame(
				V4_Mapper::REASON_UNKNOWN_COMBINATION,
				$result['legacy_only_reason'],
				"V1 combination resolved to unknown_combination (silent gap): {$label}"
			);
			$this->assertContains(
				$result['legacy_only_reason'],
				$allowed_legacy,
				"Unexpected legacy reason for {$label}"
			);
		}
	}

	// =========================================================================
	// SDK bundle gap marker + load-bearing not-yet-available guard
	// =========================================================================

	/**
	 * @testdox SDK_SERVICES_BUNDLE_GAP marker is present and stable so the deferral can't be silently dropped
	 */
	public function test_sdk_services_bundle_gap_marker_is_present(): void {
		$this->assertTrue( defined( V4_Mapper::class . '::SDK_SERVICES_BUNDLE_GAP' ) );
		$this->assertSame( 'sdk_v4_services_dto_missing_bundle_property', V4_Mapper::SDK_SERVICES_BUNDLE_GAP );
	}

	/**
	 * @testdox No mapped row emits services.bundle or internationalShipmentData (the SDK gap is honoured)
	 */
	public function test_no_row_emits_bundle_or_international_data(): void {
		foreach ( self::combination_matrix_provider() as $name => $row ) {
			$result = V4_Mapper::map( $row[0] );
			if ( ! $result['has_v4_equivalent'] ) {
				continue;
			}
			$this->assertArrayNotHasKey( 'bundle', $result['services'], "services.bundle must not be emitted ({$name})" );
			$this->assertSame( array(), $result['internationalShipmentData'], "internationalShipmentData must stay empty ({$name})" );
		}
	}

	/**
	 * @testdox All EU/ROW combinations stay Legacy-only (international deferred on the SDK bundle gap)
	 */
	public function test_all_international_combinations_stay_legacy(): void {
		foreach ( self::v1_combinations() as $combination ) {
			if ( ! in_array( $combination['destination'], array( 'EU', 'ROW' ), true ) ) {
				continue;
			}
			$this->assertFalse(
				V4_Mapper::has_v4_equivalent( $combination ),
				sprintf( 'International %s→%s must stay Legacy-only', $combination['origin'], $combination['destination'] )
			);
		}
	}

	/**
	 * @testdox Load-bearing guard: every combination resolving to a NOT_YET_AVAILABLE code returns REASON_NOT_YET_AVAILABLE
	 */
	public function test_not_yet_available_guard_is_load_bearing(): void {
		$asserted = 0;
		foreach ( self::v1_combinations() as $combination ) {
			$result = V4_Mapper::map( $combination );
			if ( in_array( $result['legacy_product_code'], V4_Mapper::NOT_YET_AVAILABLE_CODES, true ) ) {
				$this->assertFalse( $result['has_v4_equivalent'] );
				$this->assertSame( V4_Mapper::REASON_NOT_YET_AVAILABLE, $result['legacy_only_reason'] );
				$asserted++;
			}
		}
		$this->assertGreaterThan( 0, $asserted, 'Expected at least one not-yet-available code in the matrix.' );
	}

	// =========================================================================
	// Per-row assertions (data provider — all 88 rows)
	// =========================================================================

	/**
	 * @dataProvider combination_matrix_provider
	 * @testdox [row $source_row_id] map() returns expected shape
	 */
	public function test_map_returns_expected_shape( array $input, array $expected ): void {
		$result = V4_Mapper::map( $input );

		$label = sprintf(
			'[row %d] %s→%s/%s [%s]',
			$expected['source_row_id'],
			$input['origin'],
			$input['destination'],
			$input['flow'],
			implode( ', ', $input['options'] ) ?: '(base)'
		);

		$this->assertSame( $expected['has_v4_equivalent'], $result['has_v4_equivalent'], "has_v4_equivalent mismatch for {$label}" );
		$this->assertSame( $expected['legacy_product_code'], $result['legacy_product_code'], "legacy_product_code mismatch for {$label}" );
		$this->assertSame( $expected['source_row_id'], $result['source_row_id'], "source_row_id mismatch for {$label}" );

		if ( $expected['has_v4_equivalent'] ) {
			$this->assertSame( $expected['shipmentType'], $result['shipmentType'], "shipmentType mismatch for {$label}" );
			$this->assertSame( $expected['services'], $result['services'], "services mismatch for {$label}" );
			$this->assertSame( $expected['deliveryLocation'], $result['deliveryLocation'], "deliveryLocation mismatch for {$label}" );
			$this->assertSame( $expected['internationalShipmentData'], $result['internationalShipmentData'], "internationalShipmentData mismatch for {$label}" );
		} else {
			$this->assertSame( $expected['legacy_only_reason'], $result['legacy_only_reason'], "legacy_only_reason mismatch for {$label}" );
		}
	}

	// =========================================================================
	// Data provider — all 88 rows
	// =========================================================================

	/**
	 * All 88 combinations mirroring V1_Mapper::products_data().
	 *
	 * Format: [ input_combination, expected_result_fields ]
	 * Options are in V1_Mapper natural order to verify options_key() normalises via sort().
	 *
	 * @return array<string, array{array, array}>
	 */
	public static function combination_matrix_provider(): array {
		$nc  = V4_Mapper::REASON_NEEDS_CONFIRMATION;
		$nya = V4_Mapper::REASON_NOT_YET_AVAILABLE;
		$pickup = array( 'pickupLocationId' => '<from_selected_location>' );

		$v4 = static function (
			int $row_id,
			string $code,
			string $shipment_type,
			array $services = array(),
			array $delivery_location = array(),
			array $international_data = array()
		): array {
			return array(
				'has_v4_equivalent'        => true,
				'legacy_product_code'      => $code,
				'source_row_id'            => $row_id,
				'shipmentType'             => $shipment_type,
				'services'                 => $services,
				'deliveryLocation'         => $delivery_location,
				'internationalShipmentData' => $international_data,
			);
		};

		$leg = static function ( int $row_id, string $code, string $reason ): array {
			return array(
				'has_v4_equivalent'   => false,
				'legacy_product_code' => $code,
				'source_row_id'       => $row_id,
				'legacy_only_reason'  => $reason,
			);
		};

		return array(

			// -----------------------------------------------------------------
			// NL → NL / delivery_day  (20 rows)
			// -----------------------------------------------------------------

			'NL→NL/dd row 1: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array() ),
					$v4( 1, '3085', 'parcel' ),
				),
			'NL→NL/dd row 2: [delivery_code_at_door,insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'delivery_code_at_door', 'insured_shipping' ) ),
					$v4( 2, '3085', 'parcel', array( 'deliveryConfirmation' => 'deliverycode', 'insuredValue' => '<order_total>' ) ),
				),
			'NL→NL/dd row 3: [only_home_address]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'only_home_address' ) ),
					$v4( 3, '3385', 'parcel', array( 'statedAddressOnly' => true ) ),
				),
			'NL→NL/dd row 4: [return_no_answer]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'return_no_answer' ) ),
					$v4( 4, '3090', 'parcel', array( 'returnWhenNotHome' => true ) ),
				),
			'NL→NL/dd row 5: [signature_on_delivery]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery' ) ),
					$v4( 5, '3189', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
				),
			'NL→NL/dd row 6: [return_no_answer,only_home_address]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'return_no_answer', 'only_home_address' ) ),
					$v4( 6, '3390', 'parcel', array( 'returnWhenNotHome' => true, 'statedAddressOnly' => true ) ),
				),
			'NL→NL/dd row 7: [signature_on_delivery,insured_shipping,return_no_answer]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'insured_shipping', 'return_no_answer' ) ),
					$v4( 7, '3094', 'parcel', array( 'deliveryConfirmation' => 'signature', 'insuredValue' => '<order_total>', 'returnWhenNotHome' => true ) ),
				),
			'NL→NL/dd row 8: [signature_on_delivery,only_home_address]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address' ) ),
					$v4( 8, '3089', 'parcel', array( 'deliveryConfirmation' => 'signature', 'statedAddressOnly' => true ) ),
				),
			'NL→NL/dd row 9: [insured_shipping,signature_on_delivery]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'signature_on_delivery' ) ),
					$v4( 9, '3087', 'parcel', array( 'deliveryConfirmation' => 'signature', 'insuredValue' => '<order_total>' ) ),
				),
			'NL→NL/dd row 10: [signature_on_delivery,return_no_answer]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'return_no_answer' ) ),
					$v4( 10, '3389', 'parcel', array( 'deliveryConfirmation' => 'signature', 'returnWhenNotHome' => true ) ),
				),
			'NL→NL/dd row 11: [signature_on_delivery,only_home_address,return_no_answer]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ) ),
					$v4( 11, '3096', 'parcel', array( 'deliveryConfirmation' => 'signature', 'returnWhenNotHome' => true, 'statedAddressOnly' => true ) ),
				),
			'NL→NL/dd row 12: [letterbox]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'letterbox' ) ),
					$v4( 12, '2928', 'letterbox' ),
				),
			'NL→NL/dd row 13: [id_check] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check' ) ),
					$leg( 13, '3438', $nc ),
				),
			'NL→NL/dd row 14: [id_check,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'signature_on_delivery' ) ),
					$leg( 14, '3438', $nc ),
				),
			'NL→NL/dd row 15: [id_check,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'only_home_address' ) ),
					$leg( 15, '3438', $nc ),
				),
			'NL→NL/dd row 16: [id_check,only_home_address,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'only_home_address', 'signature_on_delivery' ) ),
					$leg( 16, '3438', $nc ),
				),
			'NL→NL/dd row 17: [id_check,insured_shipping] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'insured_shipping' ) ),
					$leg( 17, '3443', $nc ),
				),
			'NL→NL/dd row 18: [id_check,insured_shipping,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'insured_shipping', 'signature_on_delivery' ) ),
					$leg( 18, '3443', $nc ),
				),
			'NL→NL/dd row 19: [id_check,insured_shipping,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'insured_shipping', 'only_home_address' ) ),
					$leg( 19, '3443', $nc ),
				),
			'NL→NL/dd row 20: [id_check,insured_shipping,only_home_address,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'insured_shipping', 'only_home_address', 'signature_on_delivery' ) ),
					$leg( 20, '3443', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → NL / pickup_points  (4 rows)
			// -----------------------------------------------------------------

			'NL→NL/pp row 21: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array() ),
					$v4( 21, '3533', 'parcel', array(), $pickup ),
				),
			'NL→NL/pp row 22: [insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array( 'insured_shipping' ) ),
					$v4( 22, '3534', 'parcel', array( 'insuredValue' => '<order_total>' ), $pickup ),
				),
			'NL→NL/pp row 23: [id_check] not_yet_available'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array( 'id_check' ) ),
					$leg( 23, '3571', $nya ),
				),
			'NL→NL/pp row 24: [id_check,insured_shipping] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array( 'id_check', 'insured_shipping' ) ),
					$leg( 24, '3581', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → BE / delivery_day  (16 rows)
			// -----------------------------------------------------------------

			'NL→BE/dd row 25: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array() ),
					$v4( 25, '4946', 'parcel' ),
				),
			'NL→BE/dd row 26: [only_home_address]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'only_home_address' ) ),
					$v4( 26, '4941', 'parcel', array( 'statedAddressOnly' => true ) ),
				),
			'NL→BE/dd row 27: [signature_on_delivery]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery' ) ),
					$v4( 27, '4912', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
				),
			'NL→BE/dd row 28: [insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping' ) ),
					$v4( 28, '4914', 'parcel', array( 'insuredValue' => '<order_total>' ) ),
				),
			'NL→BE/dd row 29: [insured_shipping,track_and_trace] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'track_and_trace' ) ),
					$leg( 29, '4914', $nc ),
				),
			'NL→BE/dd row 30: [insured_shipping,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'signature_on_delivery' ) ),
					$leg( 30, '4914', $nc ),
				),
			'NL→BE/dd row 31: [insured_shipping,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'only_home_address' ) ),
					$leg( 31, '4914', $nc ),
				),
			'NL→BE/dd row 32: [insured_shipping,signature_on_delivery,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'signature_on_delivery', 'only_home_address' ) ),
					$leg( 32, '4914', $nc ),
				),
			'NL→BE/dd row 33: [insured_shipping,track_and_trace,signature_on_delivery] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery' ) ),
					$leg( 33, '4914', $nc ),
				),
			'NL→BE/dd row 34: [insured_shipping,track_and_trace,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'track_and_trace', 'only_home_address' ) ),
					$leg( 34, '4914', $nc ),
				),
			'NL→BE/dd row 35: [insured_shipping,track_and_trace,signature_on_delivery,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery', 'only_home_address' ) ),
					$leg( 35, '4914', $nc ),
				),
			'NL→BE/dd row 36: [mailboxpacket] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'mailboxpacket' ) ),
					$leg( 36, '6440', $nc ),
				),
			'NL→BE/dd row 37: [mailboxpacket,track_and_trace] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'mailboxpacket', 'track_and_trace' ) ),
					$leg( 37, '6972', $nc ),
				),
			'NL→BE/dd row 38: [packets] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'packets' ) ),
					$leg( 38, '6405', $nc ),
				),
			'NL→BE/dd row 39: [packets,track_and_trace] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'packets', 'track_and_trace' ) ),
					$leg( 39, '6350', $nc ),
				),
			'NL→BE/dd row 40: [packets,track_and_trace,insured_shipping] needs_confirmation'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'packets', 'track_and_trace', 'insured_shipping' ) ),
					$leg( 40, '6906', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → BE / pickup_points  (1 row)
			// -----------------------------------------------------------------

			'NL→BE/pp row 41: (base) not_yet_available'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'BE', 'flow' => 'pickup_points', 'options' => array() ),
					$leg( 41, '4936', $nya ),
				),

			// -----------------------------------------------------------------
			// NL → EU / delivery_day  (9 rows — all needs_confirmation)
			// -----------------------------------------------------------------

			'NL→EU/dd row 42: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array() ),
					$leg( 42, '4907', $nc ),
				),
			'NL→EU/dd row 43: [track_and_trace]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace' ) ),
					$leg( 43, '4907', $nc ),
				),
			'NL→EU/dd row 44: [track_and_trace,insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_shipping' ) ),
					$leg( 44, '4907', $nc ),
				),
			'NL→EU/dd row 45: [track_and_trace,insured_plus]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_plus' ) ),
					$leg( 45, '4907', $nc ),
				),
			'NL→EU/dd row 46: [mailboxpacket]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'mailboxpacket' ) ),
					$leg( 46, '6440', $nc ),
				),
			'NL→EU/dd row 47: [track_and_trace,mailboxpacket]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'mailboxpacket' ) ),
					$leg( 47, '6972', $nc ),
				),
			'NL→EU/dd row 48: [packets]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'packets' ) ),
					$leg( 48, '6405', $nc ),
				),
			'NL→EU/dd row 49: [track_and_trace,packets]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets' ) ),
					$leg( 49, '6350', $nc ),
				),
			'NL→EU/dd row 50: [track_and_trace,packets,insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets', 'insured_shipping' ) ),
					$leg( 50, '6906', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → EU / pickup_points  (1 row)
			// -----------------------------------------------------------------

			'NL→EU/pp row 51: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'EU', 'flow' => 'pickup_points', 'options' => array() ),
					$leg( 51, '4907', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → ROW / delivery_day  (8 rows — all needs_confirmation)
			// -----------------------------------------------------------------

			'NL→ROW/dd row 52: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array() ),
					$leg( 52, '4909', $nc ),
				),
			'NL→ROW/dd row 53: [track_and_trace]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace' ) ),
					$leg( 53, '4909', $nc ),
				),
			'NL→ROW/dd row 54: [track_and_trace,insured_plus]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_plus' ) ),
					$leg( 54, '4909', $nc ),
				),
			'NL→ROW/dd row 55: [mailboxpacket]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'mailboxpacket' ) ),
					$leg( 55, '6440', $nc ),
				),
			'NL→ROW/dd row 56: [track_and_trace,mailboxpacket]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'mailboxpacket' ) ),
					$leg( 56, '6972', $nc ),
				),
			'NL→ROW/dd row 57: [packets]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'packets' ) ),
					$leg( 57, '6405', $nc ),
				),
			'NL→ROW/dd row 58: [track_and_trace,packets]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets' ) ),
					$leg( 58, '6350', $nc ),
				),
			'NL→ROW/dd row 59: [track_and_trace,packets,insured_shipping]'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets', 'insured_shipping' ) ),
					$leg( 59, '6906', $nc ),
				),

			// -----------------------------------------------------------------
			// NL → ROW / pickup_points  (1 row)
			// -----------------------------------------------------------------

			'NL→ROW/pp row 60: (base)'
				=> array(
					array( 'origin' => 'NL', 'destination' => 'ROW', 'flow' => 'pickup_points', 'options' => array() ),
					$leg( 60, '4909', $nc ),
				),

			// -----------------------------------------------------------------
			// BE → BE / delivery_day  (5 rows — all not_yet_available)
			// -----------------------------------------------------------------

			'BE→BE/dd row 61: (base) not_yet_available'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array() ),
					$leg( 61, '4961', $nya ),
				),
			'BE→BE/dd row 62: [only_home_address] not_yet_available'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'only_home_address' ) ),
					$leg( 62, '4960', $nya ),
				),
			'BE→BE/dd row 63: [signature_on_delivery] not_yet_available'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery' ) ),
					$leg( 63, '4963', $nya ),
				),
			'BE→BE/dd row 64: [signature_on_delivery,only_home_address] not_yet_available'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address' ) ),
					$leg( 64, '4962', $nya ),
				),
			'BE→BE/dd row 65: [insured_shipping,only_home_address] not_yet_available'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'delivery_day', 'options' => array( 'insured_shipping', 'only_home_address' ) ),
					$leg( 65, '4965', $nya ),
				),

			// -----------------------------------------------------------------
			// BE → BE / pickup_points  (2 rows — v4_mapped)
			// -----------------------------------------------------------------

			'BE→BE/pp row 66: (base)'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'pickup_points', 'options' => array() ),
					$v4( 66, '4880', 'parcel', array(), $pickup ),
				),
			'BE→BE/pp row 67: [insured_shipping]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'BE', 'flow' => 'pickup_points', 'options' => array( 'insured_shipping' ) ),
					$v4( 67, '4878', 'parcel', array( 'insuredValue' => '<order_total>' ), $pickup ),
				),

			// -----------------------------------------------------------------
			// BE → NL / delivery_day  (7 rows)
			// -----------------------------------------------------------------

			'BE→NL/dd row 68: (base)'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array() ),
					$v4( 68, '4890', 'parcel' ),
				),
			'BE→NL/dd row 69: [signature_on_delivery]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery' ) ),
					$v4( 69, '4891', 'parcel', array( 'deliveryConfirmation' => 'signature' ) ),
				),
			'BE→NL/dd row 70: [only_home_address]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'only_home_address' ) ),
					$v4( 70, '4893', 'parcel', array( 'statedAddressOnly' => true ) ),
				),
			'BE→NL/dd row 71: [signature_on_delivery,only_home_address]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address' ) ),
					$v4( 71, '4894', 'parcel', array( 'deliveryConfirmation' => 'signature', 'statedAddressOnly' => true ) ),
				),
			'BE→NL/dd row 72: [id_check,signature_on_delivery,only_home_address] needs_confirmation'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'id_check', 'signature_on_delivery', 'only_home_address' ) ),
					$leg( 72, '4895', $nc ),
				),
			'BE→NL/dd row 73: [signature_on_delivery,only_home_address,return_no_answer]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ) ),
					$v4( 73, '4896', 'parcel', array( 'deliveryConfirmation' => 'signature', 'returnWhenNotHome' => true, 'statedAddressOnly' => true ) ),
				),
			'BE→NL/dd row 74: [signature_on_delivery,only_home_address,insured_shipping]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'delivery_day', 'options' => array( 'signature_on_delivery', 'only_home_address', 'insured_shipping' ) ),
					$v4( 74, '4897', 'parcel', array( 'deliveryConfirmation' => 'signature', 'insuredValue' => '<order_total>', 'statedAddressOnly' => true ) ),
				),

			// -----------------------------------------------------------------
			// BE → NL / pickup_points  (2 rows — v4_mapped)
			// -----------------------------------------------------------------

			'BE→NL/pp row 75: [signature_on_delivery]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array( 'signature_on_delivery' ) ),
					$v4( 75, '4898', 'parcel', array( 'deliveryConfirmation' => 'signature' ), $pickup ),
				),
			'BE→NL/pp row 76: (base)'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'NL', 'flow' => 'pickup_points', 'options' => array() ),
					$v4( 76, '4898', 'parcel', array(), $pickup ),
				),

			// -----------------------------------------------------------------
			// BE → EU / delivery_day  (9 rows — all needs_confirmation)
			// -----------------------------------------------------------------

			'BE→EU/dd row 77: (base)'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array() ),
					$leg( 77, '4907', $nc ),
				),
			'BE→EU/dd row 78: [track_and_trace]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace' ) ),
					$leg( 78, '4907', $nc ),
				),
			'BE→EU/dd row 79: [track_and_trace,insured_shipping]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_shipping' ) ),
					$leg( 79, '4907', $nc ),
				),
			'BE→EU/dd row 80: [track_and_trace,insured_plus]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_plus' ) ),
					$leg( 80, '4907', $nc ),
				),
			'BE→EU/dd row 81: [mailboxpacket]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'mailboxpacket' ) ),
					$leg( 81, '6440', $nc ),
				),
			'BE→EU/dd row 82: [track_and_trace,mailboxpacket]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'mailboxpacket' ) ),
					$leg( 82, '6972', $nc ),
				),
			'BE→EU/dd row 83: [packets]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'packets' ) ),
					$leg( 83, '6405', $nc ),
				),
			'BE→EU/dd row 84: [track_and_trace,packets]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets' ) ),
					$leg( 84, '6350', $nc ),
				),
			'BE→EU/dd row 85: [track_and_trace,packets,insured_shipping]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'EU', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'packets', 'insured_shipping' ) ),
					$leg( 85, '6906', $nc ),
				),

			// -----------------------------------------------------------------
			// BE → ROW / delivery_day  (3 rows — all needs_confirmation)
			// -----------------------------------------------------------------

			'BE→ROW/dd row 86: (base)'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array() ),
					$leg( 86, '4909', $nc ),
				),
			'BE→ROW/dd row 87: [track_and_trace]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace' ) ),
					$leg( 87, '4909', $nc ),
				),
			'BE→ROW/dd row 88: [track_and_trace,insured_plus]'
				=> array(
					array( 'origin' => 'BE', 'destination' => 'ROW', 'flow' => 'delivery_day', 'options' => array( 'track_and_trace', 'insured_plus' ) ),
					$leg( 88, '4909', $nc ),
				),

		);
		// Total: NL→NL 20+4 + NL→BE 16+1 + NL→EU 9+1 + NL→ROW 8+1
		//      + BE→BE 5+2  + BE→NL 7+2  + BE→EU 9   + BE→ROW 3 = 88
	}
}
