<?php
/**
 * Unit tests for Utils.
 */

namespace PostNLWooCommerce\Tests\Unit;

use PostNLWooCommerce\Tests\UnitTestCase;
use PostNLWooCommerce\Utils;

/**
 * Covers the static helper methods that carry no WordPress dependencies.
 */
class UtilsTest extends UnitTestCase {

	public function test_get_available_country_returns_nl_and_be(): void {
		$this->assertSame( array( 'NL', 'BE' ), Utils::get_available_country() );
	}

	public function test_get_available_country_for_letterbox_returns_nl_only(): void {
		$this->assertSame( array( 'NL' ), Utils::get_available_country_for_letterbox() );
	}

	public function test_get_adults_only_shipping_countries_returns_nl_only(): void {
		$this->assertSame( array( 'NL' ), Utils::get_adults_only_shipping_countries() );
	}
}
