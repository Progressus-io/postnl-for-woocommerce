<?php
/**
 * Unit tests for Utils.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Unit;

use PostNLWooCommerce\Tests\UnitTestCase;
use PostNLWooCommerce\Utils;

/**
 * Covers the static helper methods that carry no WordPress dependencies.
 */
class UtilsTest extends UnitTestCase {

	/**
	 * @testdox Should return NL and BE as the available shipping countries.
	 */
	public function test_get_available_country_returns_nl_and_be(): void {
		$this->assertSame( array( 'NL', 'BE' ), Utils::get_available_country() );
	}

	/**
	 * @testdox Should return only NL as an available letterbox shipping country.
	 */
	public function test_get_available_country_for_letterbox_returns_nl_only(): void {
		$this->assertSame( array( 'NL' ), Utils::get_available_country_for_letterbox() );
	}

	/**
	 * @testdox Should return only NL as an available adults-only shipping country.
	 */
	public function test_get_adults_only_shipping_countries_returns_nl_only(): void {
		$this->assertSame( array( 'NL' ), Utils::get_adults_only_shipping_countries() );
	}

	/**
	 * @testdox Should collapse an explicit 24h letterbox selection onto the generic feature and report the 24h variant.
	 */
	public function test_normalize_letterbox_options_reports_24h_variant(): void {
		$result = Utils::normalize_letterbox_options( array( 'letterbox' => 'yes' ) );

		$this->assertSame( 'letterbox', $result['type'], '24h selection should resolve to the letterbox variant' );
		$this->assertSame( array( 'letterbox' => 'yes' ), $result['options'], 'The generic letterbox feature should remain set' );
	}

	/**
	 * @testdox Should collapse a 48h letterbox selection onto the generic feature and report the 48h variant.
	 */
	public function test_normalize_letterbox_options_reports_48h_variant(): void {
		$result = Utils::normalize_letterbox_options( array( 'letterbox_48' => 'yes' ) );

		$this->assertSame( 'letterbox_48', $result['type'], '48h selection should resolve to the letterbox_48 variant' );
		$this->assertArrayNotHasKey( 'letterbox_48', $result['options'], 'The 48h token should be collapsed onto the generic feature' );
		$this->assertSame( 'yes', $result['options']['letterbox'], 'The generic letterbox feature should be set for a 48h selection' );
	}

	/**
	 * @testdox Should prefer the 48h variant when both letterbox options are selected.
	 */
	public function test_normalize_letterbox_options_prefers_48h_when_both_set(): void {
		$result = Utils::normalize_letterbox_options(
			array(
				'letterbox'    => 'yes',
				'letterbox_48' => 'yes',
			)
		);

		$this->assertSame( 'letterbox_48', $result['type'], '48h should win when both variants are selected' );
		$this->assertSame( array( 'letterbox' => 'yes' ), $result['options'], 'Only the generic letterbox feature should remain' );
	}

	/**
	 * @testdox Should preserve other options and report no variant when no letterbox is selected.
	 */
	public function test_normalize_letterbox_options_reports_no_variant_without_letterbox(): void {
		$result = Utils::normalize_letterbox_options( array( 'id_check' => 'yes' ) );

		$this->assertSame( '', $result['type'], 'No letterbox selection should report an empty variant' );
		$this->assertSame( array( 'id_check' => 'yes' ), $result['options'], 'Unrelated options should be preserved untouched' );
	}

	/**
	 * @testdox Should return empty options and no variant for non-array input.
	 */
	public function test_normalize_letterbox_options_handles_non_array_input(): void {
		$result = Utils::normalize_letterbox_options( 'letterbox' );

		$this->assertSame( array(), $result['options'], 'Non-array input should yield empty options' );
		$this->assertSame( '', $result['type'], 'Non-array input should report an empty variant' );
	}
}
