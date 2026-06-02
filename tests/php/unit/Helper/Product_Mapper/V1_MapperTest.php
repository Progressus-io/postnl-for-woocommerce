<?php
/**
 * Unit tests for V1_Mapper.
 *
 * @package PostNLWooCommerce\Tests\Unit\Helper\Product_Mapper
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Unit\Helper\Product_Mapper;

use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Helper\Product_Mapper\V1_Mapper;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * Golden-master tests for V1_Mapper.
 *
 * Each @dataProvider entry is one explicit combination → (code, options) assertion
 * derived from the original Mapping.php data. None of the expected values are
 * generated at runtime from V1_Mapper itself.
 *
 * Combination count: 88 total across all origin × destination × service entries.
 * (The task description estimated 72; the actual tally from Mapping.php is 88.)
 *
 * @covers \PostNLWooCommerce\Helper\Product_Mapper\V1_Mapper
 */
class V1_MapperTest extends UnitTestCase {

	// -------------------------------------------------------------------------
	// Helper
	// -------------------------------------------------------------------------

	/**
	 * Find the single products_data entry whose combination set matches $combo.
	 * Comparison is order-independent (both sides are sorted before comparing).
	 *
	 * @param array  $entries   Slice of products_data for one origin/dest/service.
	 * @param array  $combo     Target combination to look up.
	 * @return array|null
	 */
	private function find_entry( array $entries, array $combo ): ?array {
		$sorted_target = $combo;
		sort( $sorted_target );

		foreach ( $entries as $entry ) {
			$sorted_entry = $entry['combination'];
			sort( $sorted_entry );
			if ( $sorted_entry === $sorted_target ) {
				return $entry;
			}
		}

		return null;
	}

	// -------------------------------------------------------------------------
	// Structural: total combination count
	// -------------------------------------------------------------------------

	/**
	 * @testdox products_data() contains exactly 88 combination entries across all zones
	 */
	public function test_products_data_total_combination_count(): void {
		$data  = V1_Mapper::products_data();
		$count = 0;
		foreach ( $data as $from_zone ) {
			foreach ( $from_zone as $to_zone ) {
				foreach ( $to_zone as $service ) {
					$count += count( $service );
				}
			}
		}
		$this->assertSame(
			88,
			$count,
			'products_data() must contain exactly 88 combination entries (V1 golden-master count).'
		);
	}

	// -------------------------------------------------------------------------
	// Delegation: Mapping.php must proxy to V1_Mapper
	// -------------------------------------------------------------------------

	/**
	 * @testdox Mapping::products_data() output equals V1_Mapper::products_data()
	 */
	public function test_mapping_delegates_products_data(): void {
		$this->assertSame( V1_Mapper::products_data(), Mapping::products_data() );
	}

	/**
	 * @testdox Mapping::EU_ROW_products() output equals V1_Mapper::EU_ROW_products()
	 */
	public function test_mapping_delegates_eu_row_products(): void {
		$this->assertSame( V1_Mapper::EU_ROW_products(), Mapping::EU_ROW_products() );
	}

	/**
	 * @testdox Mapping::european_shipment_products() output equals V1_Mapper::european_shipment_products()
	 */
	public function test_mapping_delegates_european_shipment_products(): void {
		$this->assertSame( V1_Mapper::european_shipment_products(), Mapping::european_shipment_products() );
	}

	/**
	 * @testdox Mapping::globalpack_products() output equals V1_Mapper::globalpack_products()
	 */
	public function test_mapping_delegates_globalpack_products(): void {
		$this->assertSame( V1_Mapper::globalpack_products(), Mapping::globalpack_products() );
	}

	/**
	 * @testdox Mapping::shipping_return_labels_options() output equals V1_Mapper::shipping_return_labels_options()
	 */
	public function test_mapping_delegates_shipping_return_labels_options(): void {
		$this->assertSame( V1_Mapper::shipping_return_labels_options(), Mapping::shipping_return_labels_options() );
	}

	/**
	 * @testdox Mapping::additional_product_options() output equals V1_Mapper::additional_product_options()
	 */
	public function test_mapping_delegates_additional_product_options(): void {
		$this->assertSame( V1_Mapper::additional_product_options(), Mapping::additional_product_options() );
	}

	// -------------------------------------------------------------------------
	// Helper method counts
	// -------------------------------------------------------------------------

	/**
	 * @testdox EU_ROW_products() returns exactly 5 entries
	 */
	public function test_eu_row_products_count(): void {
		$this->assertCount( 5, V1_Mapper::EU_ROW_products() );
	}

	/**
	 * @testdox european_shipment_products() returns exactly 9 entries (4 EU + 5 EU_ROW)
	 */
	public function test_european_shipment_products_count(): void {
		$this->assertCount( 9, V1_Mapper::european_shipment_products() );
	}

	/**
	 * @testdox globalpack_products() returns exactly 3 entries
	 */
	public function test_globalpack_products_count(): void {
		$this->assertCount( 3, V1_Mapper::globalpack_products() );
	}

	// -------------------------------------------------------------------------
	// Per-combination assertions: products_data()
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider products_data_provider
	 * @testdox [$from→$to/$service] combination [$combo_label] maps to code $expected_code
	 */
	public function test_combination_maps_to_expected_product(
		string $from,
		string $to,
		string $service,
		array $combination,
		string $expected_code,
		array $expected_options
	): void {
		$data    = V1_Mapper::products_data();
		$entries = $data[ $from ][ $to ][ $service ] ?? array();

		$label = sprintf(
			'%s→%s/%s [%s]',
			$from,
			$to,
			$service,
			implode( ', ', $combination ) ?: '(base)'
		);

		$this->assertNotEmpty( $entries, "No entries found for {$label}" );

		$found = $this->find_entry( $entries, $combination );

		$this->assertNotNull( $found, "No entry matching combination for {$label}" );
		$this->assertSame( $expected_code, $found['code'], "Wrong code for {$label}" );
		$this->assertSame( $expected_options, $found['options'], "Wrong options for {$label}" );
	}

	/**
	 * Exhaustive golden-master data provider.
	 *
	 * Format per case: [ from, to, service, combination, expected_code, expected_options ]
	 *
	 * Expected values are copied verbatim from the original Mapping.php — none are
	 * derived at runtime from V1_Mapper.
	 *
	 * @return array<string, array{string, string, string, array, string, array}>
	 */
	public static function products_data_provider(): array {
		// Shared option arrays (defined once for readability; values are the
		// golden-master literals from the original Mapping.php).
		$id_check_opt = array(
			array(
				'characteristic' => '002',
				'option'         => '014',
			),
		);

		$insured_at_door_opt = array(
			array(
				'characteristic' => '004',
				'option'         => '020',
			),
		);

		$eu_standard_opts = array(
			array(
				'characteristic' => '005',
				'option'         => '025',
			),
			array(
				'characteristic' => '101',
				'option'         => '012',
			),
		);

		$eu_insured_opts = array(
			array(
				'characteristic' => '004',
				'option'         => '015',
			),
			array(
				'characteristic' => '101',
				'option'         => '012',
			),
		);

		$eu_insured_plus_opts = array(
			array(
				'characteristic' => '004',
				'option'         => '016',
			),
			array(
				'characteristic' => '101',
				'option'         => '012',
			),
		);

		$gp_base_opts = array(
			array(
				'characteristic' => '004',
				'option'         => '015',
			),
		);

		$gp_tt_opts = array(
			array(
				'characteristic' => '005',
				'option'         => '025',
			),
		);

		$gp_insured_plus_opts = array(
			array(
				'characteristic' => '004',
				'option'         => '016',
			),
		);

		return array(

			// =================================================================
			// NL → NL / delivery_day  (20 combinations)
			// =================================================================

			'NL→NL/dd: base → 3085'
				=> array( 'NL', 'NL', 'delivery_day', array(), '3085', array() ),

			'NL→NL/dd: [delivery_code_at_door,insured_shipping] → 3085+opt'
				=> array( 'NL', 'NL', 'delivery_day', array( 'delivery_code_at_door', 'insured_shipping' ), '3085', $insured_at_door_opt ),

			'NL→NL/dd: [only_home_address] → 3385'
				=> array( 'NL', 'NL', 'delivery_day', array( 'only_home_address' ), '3385', array() ),

			'NL→NL/dd: [return_no_answer] → 3090'
				=> array( 'NL', 'NL', 'delivery_day', array( 'return_no_answer' ), '3090', array() ),

			'NL→NL/dd: [signature_on_delivery] → 3189'
				=> array( 'NL', 'NL', 'delivery_day', array( 'signature_on_delivery' ), '3189', array() ),

			'NL→NL/dd: [return_no_answer,only_home_address] → 3390'
				=> array( 'NL', 'NL', 'delivery_day', array( 'return_no_answer', 'only_home_address' ), '3390', array() ),

			'NL→NL/dd: [signature_on_delivery,insured_shipping,return_no_answer] → 3094'
				=> array( 'NL', 'NL', 'delivery_day', array( 'signature_on_delivery', 'insured_shipping', 'return_no_answer' ), '3094', array() ),

			'NL→NL/dd: [signature_on_delivery,only_home_address] → 3089'
				=> array( 'NL', 'NL', 'delivery_day', array( 'signature_on_delivery', 'only_home_address' ), '3089', array() ),

			'NL→NL/dd: [insured_shipping,signature_on_delivery] → 3087'
				=> array( 'NL', 'NL', 'delivery_day', array( 'insured_shipping', 'signature_on_delivery' ), '3087', array() ),

			'NL→NL/dd: [signature_on_delivery,return_no_answer] → 3389'
				=> array( 'NL', 'NL', 'delivery_day', array( 'signature_on_delivery', 'return_no_answer' ), '3389', array() ),

			'NL→NL/dd: [signature_on_delivery,only_home_address,return_no_answer] → 3096'
				=> array( 'NL', 'NL', 'delivery_day', array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ), '3096', array() ),

			'NL→NL/dd: [letterbox] → 2928'
				=> array( 'NL', 'NL', 'delivery_day', array( 'letterbox' ), '2928', array() ),

			'NL→NL/dd: [id_check] → 3438'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check' ), '3438', $id_check_opt ),

			'NL→NL/dd: [id_check,signature_on_delivery] → 3438'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'signature_on_delivery' ), '3438', $id_check_opt ),

			'NL→NL/dd: [id_check,only_home_address] → 3438'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'only_home_address' ), '3438', $id_check_opt ),

			'NL→NL/dd: [id_check,only_home_address,signature_on_delivery] → 3438'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'only_home_address', 'signature_on_delivery' ), '3438', $id_check_opt ),

			'NL→NL/dd: [id_check,insured_shipping] → 3443'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'insured_shipping' ), '3443', $id_check_opt ),

			'NL→NL/dd: [id_check,insured_shipping,signature_on_delivery] → 3443'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'insured_shipping', 'signature_on_delivery' ), '3443', $id_check_opt ),

			'NL→NL/dd: [id_check,insured_shipping,only_home_address] → 3443'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'insured_shipping', 'only_home_address' ), '3443', $id_check_opt ),

			'NL→NL/dd: [id_check,insured_shipping,only_home_address,signature_on_delivery] → 3443'
				=> array( 'NL', 'NL', 'delivery_day', array( 'id_check', 'insured_shipping', 'only_home_address', 'signature_on_delivery' ), '3443', $id_check_opt ),

			// =================================================================
			// NL → NL / pickup_points  (4 combinations)
			// =================================================================

			'NL→NL/pp: base → 3533'
				=> array( 'NL', 'NL', 'pickup_points', array(), '3533', array() ),

			'NL→NL/pp: [insured_shipping] → 3534'
				=> array( 'NL', 'NL', 'pickup_points', array( 'insured_shipping' ), '3534', array() ),

			'NL→NL/pp: [id_check] → 3571'
				=> array( 'NL', 'NL', 'pickup_points', array( 'id_check' ), '3571', $id_check_opt ),

			'NL→NL/pp: [id_check,insured_shipping] → 3581'
				=> array( 'NL', 'NL', 'pickup_points', array( 'id_check', 'insured_shipping' ), '3581', $id_check_opt ),

			// =================================================================
			// NL → BE / delivery_day  (16 combinations)
			// =================================================================

			'NL→BE/dd: base → 4946'
				=> array( 'NL', 'BE', 'delivery_day', array(), '4946', array() ),

			'NL→BE/dd: [only_home_address] → 4941'
				=> array( 'NL', 'BE', 'delivery_day', array( 'only_home_address' ), '4941', array() ),

			'NL→BE/dd: [signature_on_delivery] → 4912'
				=> array( 'NL', 'BE', 'delivery_day', array( 'signature_on_delivery' ), '4912', array() ),

			'NL→BE/dd: [insured_shipping] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,track_and_trace] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'track_and_trace' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,signature_on_delivery] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'signature_on_delivery' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,only_home_address] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'only_home_address' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,signature_on_delivery,only_home_address] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'signature_on_delivery', 'only_home_address' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,track_and_trace,signature_on_delivery] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,track_and_trace,only_home_address] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'track_and_trace', 'only_home_address' ), '4914', array() ),

			'NL→BE/dd: [insured_shipping,track_and_trace,signature_on_delivery,only_home_address] → 4914'
				=> array( 'NL', 'BE', 'delivery_day', array( 'insured_shipping', 'track_and_trace', 'signature_on_delivery', 'only_home_address' ), '4914', array() ),

			'NL→BE/dd: [mailboxpacket] → 6440'
				=> array( 'NL', 'BE', 'delivery_day', array( 'mailboxpacket' ), '6440', array() ),

			'NL→BE/dd: [mailboxpacket,track_and_trace] → 6972'
				=> array( 'NL', 'BE', 'delivery_day', array( 'mailboxpacket', 'track_and_trace' ), '6972', array() ),

			'NL→BE/dd: [packets] → 6405'
				=> array( 'NL', 'BE', 'delivery_day', array( 'packets' ), '6405', array() ),

			'NL→BE/dd: [packets,track_and_trace] → 6350'
				=> array( 'NL', 'BE', 'delivery_day', array( 'packets', 'track_and_trace' ), '6350', array() ),

			'NL→BE/dd: [packets,track_and_trace,insured_shipping] → 6906'
				=> array( 'NL', 'BE', 'delivery_day', array( 'packets', 'track_and_trace', 'insured_shipping' ), '6906', array() ),

			// =================================================================
			// NL → BE / pickup_points  (1 combination)
			// =================================================================

			'NL→BE/pp: base → 4936'
				=> array( 'NL', 'BE', 'pickup_points', array(), '4936', array() ),

			// =================================================================
			// NL → EU / delivery_day  (9 combinations via european_shipment_products)
			// =================================================================

			'NL→EU/dd: base → 4907+eu_standard'
				=> array( 'NL', 'EU', 'delivery_day', array(), '4907', $eu_standard_opts ),

			'NL→EU/dd: [track_and_trace] → 4907+eu_standard'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace' ), '4907', $eu_standard_opts ),

			'NL→EU/dd: [track_and_trace,insured_shipping] → 4907+eu_insured'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace', 'insured_shipping' ), '4907', $eu_insured_opts ),

			'NL→EU/dd: [track_and_trace,insured_plus] → 4907+eu_insured_plus'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace', 'insured_plus' ), '4907', $eu_insured_plus_opts ),

			'NL→EU/dd: [mailboxpacket] → 6440'
				=> array( 'NL', 'EU', 'delivery_day', array( 'mailboxpacket' ), '6440', array() ),

			'NL→EU/dd: [track_and_trace,mailboxpacket] → 6972'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace', 'mailboxpacket' ), '6972', array() ),

			'NL→EU/dd: [packets] → 6405'
				=> array( 'NL', 'EU', 'delivery_day', array( 'packets' ), '6405', array() ),

			'NL→EU/dd: [track_and_trace,packets] → 6350'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace', 'packets' ), '6350', array() ),

			'NL→EU/dd: [track_and_trace,packets,insured_shipping] → 6906'
				=> array( 'NL', 'EU', 'delivery_day', array( 'track_and_trace', 'packets', 'insured_shipping' ), '6906', array() ),

			// =================================================================
			// NL → EU / pickup_points  (1 combination)
			// =================================================================

			'NL→EU/pp: base → 4907+eu_standard'
				=> array( 'NL', 'EU', 'pickup_points', array(), '4907', $eu_standard_opts ),

			// =================================================================
			// NL → ROW / delivery_day  (8 combinations via globalpack + EU_ROW)
			// =================================================================

			'NL→ROW/dd: base → 4909+gp_base'
				=> array( 'NL', 'ROW', 'delivery_day', array(), '4909', $gp_base_opts ),

			'NL→ROW/dd: [track_and_trace] → 4909+gp_tt'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'track_and_trace' ), '4909', $gp_tt_opts ),

			'NL→ROW/dd: [track_and_trace,insured_plus] → 4909+gp_insured_plus'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'track_and_trace', 'insured_plus' ), '4909', $gp_insured_plus_opts ),

			'NL→ROW/dd: [mailboxpacket] → 6440'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'mailboxpacket' ), '6440', array() ),

			'NL→ROW/dd: [track_and_trace,mailboxpacket] → 6972'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'track_and_trace', 'mailboxpacket' ), '6972', array() ),

			'NL→ROW/dd: [packets] → 6405'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'packets' ), '6405', array() ),

			'NL→ROW/dd: [track_and_trace,packets] → 6350'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'track_and_trace', 'packets' ), '6350', array() ),

			'NL→ROW/dd: [track_and_trace,packets,insured_shipping] → 6906'
				=> array( 'NL', 'ROW', 'delivery_day', array( 'track_and_trace', 'packets', 'insured_shipping' ), '6906', array() ),

			// =================================================================
			// NL → ROW / pickup_points  (1 combination)
			// =================================================================

			'NL→ROW/pp: base → 4909+gp_tt'
				=> array( 'NL', 'ROW', 'pickup_points', array(), '4909', $gp_tt_opts ),

			// =================================================================
			// BE → BE / delivery_day  (5 combinations)
			// =================================================================

			'BE→BE/dd: base → 4961'
				=> array( 'BE', 'BE', 'delivery_day', array(), '4961', array() ),

			'BE→BE/dd: [only_home_address] → 4960'
				=> array( 'BE', 'BE', 'delivery_day', array( 'only_home_address' ), '4960', array() ),

			'BE→BE/dd: [signature_on_delivery] → 4963'
				=> array( 'BE', 'BE', 'delivery_day', array( 'signature_on_delivery' ), '4963', array() ),

			'BE→BE/dd: [signature_on_delivery,only_home_address] → 4962'
				=> array( 'BE', 'BE', 'delivery_day', array( 'signature_on_delivery', 'only_home_address' ), '4962', array() ),

			'BE→BE/dd: [insured_shipping,only_home_address] → 4965'
				=> array( 'BE', 'BE', 'delivery_day', array( 'insured_shipping', 'only_home_address' ), '4965', array() ),

			// =================================================================
			// BE → BE / pickup_points  (2 combinations)
			// =================================================================

			'BE→BE/pp: base → 4880'
				=> array( 'BE', 'BE', 'pickup_points', array(), '4880', array() ),

			'BE→BE/pp: [insured_shipping] → 4878'
				=> array( 'BE', 'BE', 'pickup_points', array( 'insured_shipping' ), '4878', array() ),

			// =================================================================
			// BE → NL / delivery_day  (7 combinations)
			// =================================================================

			'BE→NL/dd: base → 4890'
				=> array( 'BE', 'NL', 'delivery_day', array(), '4890', array() ),

			'BE→NL/dd: [signature_on_delivery] → 4891'
				=> array( 'BE', 'NL', 'delivery_day', array( 'signature_on_delivery' ), '4891', array() ),

			'BE→NL/dd: [only_home_address] → 4893'
				=> array( 'BE', 'NL', 'delivery_day', array( 'only_home_address' ), '4893', array() ),

			'BE→NL/dd: [signature_on_delivery,only_home_address] → 4894'
				=> array( 'BE', 'NL', 'delivery_day', array( 'signature_on_delivery', 'only_home_address' ), '4894', array() ),

			'BE→NL/dd: [id_check,signature_on_delivery,only_home_address] → 4895'
				=> array( 'BE', 'NL', 'delivery_day', array( 'id_check', 'signature_on_delivery', 'only_home_address' ), '4895', $id_check_opt ),

			'BE→NL/dd: [signature_on_delivery,only_home_address,return_no_answer] → 4896'
				=> array( 'BE', 'NL', 'delivery_day', array( 'signature_on_delivery', 'only_home_address', 'return_no_answer' ), '4896', array() ),

			'BE→NL/dd: [signature_on_delivery,only_home_address,insured_shipping] → 4897'
				=> array( 'BE', 'NL', 'delivery_day', array( 'signature_on_delivery', 'only_home_address', 'insured_shipping' ), '4897', array() ),

			// =================================================================
			// BE → NL / pickup_points  (2 combinations)
			// =================================================================

			'BE→NL/pp: [signature_on_delivery] → 4898'
				=> array( 'BE', 'NL', 'pickup_points', array( 'signature_on_delivery' ), '4898', array() ),

			'BE→NL/pp: base → 4898'
				=> array( 'BE', 'NL', 'pickup_points', array(), '4898', array() ),

			// =================================================================
			// BE → EU / delivery_day  (9 combinations via european_shipment_products)
			// =================================================================

			'BE→EU/dd: base → 4907+eu_standard'
				=> array( 'BE', 'EU', 'delivery_day', array(), '4907', $eu_standard_opts ),

			'BE→EU/dd: [track_and_trace] → 4907+eu_standard'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace' ), '4907', $eu_standard_opts ),

			'BE→EU/dd: [track_and_trace,insured_shipping] → 4907+eu_insured'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace', 'insured_shipping' ), '4907', $eu_insured_opts ),

			'BE→EU/dd: [track_and_trace,insured_plus] → 4907+eu_insured_plus'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace', 'insured_plus' ), '4907', $eu_insured_plus_opts ),

			'BE→EU/dd: [mailboxpacket] → 6440'
				=> array( 'BE', 'EU', 'delivery_day', array( 'mailboxpacket' ), '6440', array() ),

			'BE→EU/dd: [track_and_trace,mailboxpacket] → 6972'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace', 'mailboxpacket' ), '6972', array() ),

			'BE→EU/dd: [packets] → 6405'
				=> array( 'BE', 'EU', 'delivery_day', array( 'packets' ), '6405', array() ),

			'BE→EU/dd: [track_and_trace,packets] → 6350'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace', 'packets' ), '6350', array() ),

			'BE→EU/dd: [track_and_trace,packets,insured_shipping] → 6906'
				=> array( 'BE', 'EU', 'delivery_day', array( 'track_and_trace', 'packets', 'insured_shipping' ), '6906', array() ),

			// =================================================================
			// BE → ROW / delivery_day  (3 combinations via globalpack_products)
			// =================================================================

			'BE→ROW/dd: base → 4909+gp_base'
				=> array( 'BE', 'ROW', 'delivery_day', array(), '4909', $gp_base_opts ),

			'BE→ROW/dd: [track_and_trace] → 4909+gp_tt'
				=> array( 'BE', 'ROW', 'delivery_day', array( 'track_and_trace' ), '4909', $gp_tt_opts ),

			'BE→ROW/dd: [track_and_trace,insured_plus] → 4909+gp_insured_plus'
				=> array( 'BE', 'ROW', 'delivery_day', array( 'track_and_trace', 'insured_plus' ), '4909', $gp_insured_plus_opts ),

		);
		// Total: 20 + 4 + 16 + 1 + 9 + 1 + 8 + 1 + 5 + 2 + 7 + 2 + 9 + 3 = 88
	}

	// -------------------------------------------------------------------------
	// shipping_return_labels_options() — structure tests
	// -------------------------------------------------------------------------

	/**
	 * @testdox shipping_return_labels_options() NL/NL in_box option is char 152 / opt 028
	 */
	public function test_shipping_return_nl_nl_in_box_option(): void {
		$opts = V1_Mapper::shipping_return_labels_options();
		$this->assertSame(
			array(
				array(
					'characteristic' => '152',
					'option'         => '028',
				),
			),
			$opts['NL']['NL']['in_box']['options']
		);
	}

	/**
	 * @testdox shipping_return_labels_options() NL/NL in_box has empty products (applies to all)
	 */
	public function test_shipping_return_nl_nl_in_box_products_is_empty(): void {
		$opts = V1_Mapper::shipping_return_labels_options();
		$this->assertSame( array(), $opts['NL']['NL']['in_box']['products'] );
	}

	/**
	 * @testdox shipping_return_labels_options() NL/NL shipping_return includes 15 product codes
	 */
	public function test_shipping_return_nl_nl_shipping_return_product_count(): void {
		$opts = V1_Mapper::shipping_return_labels_options();
		$this->assertCount( 15, $opts['NL']['NL']['shipping_return']['products'] );
	}

	/**
	 * @testdox shipping_return_labels_options() NL/NL return_all_labels_not_active has two options
	 */
	public function test_shipping_return_nl_nl_return_all_labels_option_count(): void {
		$opts = V1_Mapper::shipping_return_labels_options();
		$this->assertCount( 2, $opts['NL']['NL']['return_all_labels_not_active']['options'] );
	}

	/**
	 * @testdox shipping_return_labels_options() NL/BE in_box applies to 5 specific products
	 */
	public function test_shipping_return_nl_be_in_box_products(): void {
		$opts = V1_Mapper::shipping_return_labels_options();
		$this->assertSame(
			array( '4946', '4941', '4912', '4914', '4936' ),
			$opts['NL']['BE']['in_box']['products']
		);
	}

	// -------------------------------------------------------------------------
	// additional_product_options() — structure tests
	// -------------------------------------------------------------------------

	/**
	 * @testdox additional_product_options() NL/NL evening delivery uses char 118 / opt 006
	 */
	public function test_additional_options_nl_nl_evening(): void {
		$opts = V1_Mapper::additional_product_options();
		$this->assertSame(
			array(
				'characteristic' => '118',
				'option'         => '006',
			),
			$opts['NL']['NL']['frontend_data']['delivery_day']['type']['Evening']
		);
	}

	/**
	 * @testdox additional_product_options() NL/NL morning delivery uses char 118 / opt 008
	 */
	public function test_additional_options_nl_nl_morning(): void {
		$opts = V1_Mapper::additional_product_options();
		$this->assertSame(
			array(
				'characteristic' => '118',
				'option'         => '008',
			),
			$opts['NL']['NL']['frontend_data']['delivery_day']['type']['08:00-12:00']
		);
	}
}
