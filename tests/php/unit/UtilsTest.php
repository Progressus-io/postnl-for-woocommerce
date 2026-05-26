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
}
