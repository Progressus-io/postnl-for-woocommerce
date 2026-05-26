<?php
/**
 * Unit tests for Router.
 *
 * @package PostNLWooCommerce\Tests\Rest_API
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API;

use Brain\Monkey\Filters;
use PostNLWooCommerce\Rest_API\Router;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\Router
 */
class RouterTest extends UnitTestCase {

	/**
	 * Every flow in SUPPORTED_FLOWS must return false when no filter is registered.
	 *
	 * @dataProvider supported_flows_provider
	 * @testdox Default state — every supported flow returns false without an opt-in filter
	 */
	public function test_every_supported_flow_defaults_to_false( string $flow ): void {
		$this->assertFalse( Router::sdk_enabled_for( $flow ) );
	}

	/**
	 * Yields one case per entry in Router::SUPPORTED_FLOWS so the default-false
	 * contract is exercised against the live constant — adding a new flow
	 * automatically extends coverage.
	 *
	 * @return array<string, array{string}>
	 */
	public function supported_flows_provider(): array {
		$cases = array();
		foreach ( Router::SUPPORTED_FLOWS as $flow ) {
			$cases[ $flow ] = array( $flow );
		}
		return $cases;
	}

	/**
	 * @testdox A truthy filter on postnl_sdk_enable_barcode enables only barcode
	 */
	public function test_filter_enables_barcode(): void {
		Filters\expectApplied( 'postnl_sdk_enable_barcode' )->andReturn( true );

		$this->assertTrue( Router::sdk_enabled_for( 'barcode' ) );
	}

	/**
	 * @testdox Enabling barcode must not bleed into other flows
	 */
	public function test_enabling_barcode_does_not_enable_other_flows(): void {
		Filters\expectApplied( 'postnl_sdk_enable_barcode' )->andReturn( true );

		foreach ( Router::SUPPORTED_FLOWS as $flow ) {
			if ( 'barcode' === $flow ) {
				continue;
			}
			$this->assertFalse(
				Router::sdk_enabled_for( $flow ),
				"Expected flow '{$flow}' to remain false when only barcode filter is set."
			);
		}
	}

	/**
	 * @testdox Truthy non-bool filter returns are cast to strict bool true
	 */
	public function test_truthy_non_bool_filter_return_is_cast_to_strict_true(): void {
		Filters\expectApplied( 'postnl_sdk_enable_barcode' )->andReturn( 1 );

		$this->assertSame( true, Router::sdk_enabled_for( 'barcode' ) );
	}

	/**
	 * @testdox Unknown or empty flows return false without consulting any filter
	 */
	public function test_unknown_flow_returns_false(): void {
		$this->assertFalse( Router::sdk_enabled_for( 'postcode_check' ) );
		$this->assertFalse( Router::sdk_enabled_for( 'unknown_flow' ) );
		$this->assertFalse( Router::sdk_enabled_for( '' ) );
	}

	/**
	 * @testdox postcode_check is intentionally absent from SUPPORTED_FLOWS
	 */
	public function test_postcode_check_is_not_in_supported_flows(): void {
		$this->assertNotContains( 'postcode_check', Router::SUPPORTED_FLOWS );
	}
}
