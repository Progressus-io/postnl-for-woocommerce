<?php
/**
 * Unit tests for Router.
 *
 * @package PostNLWooCommerce\Tests\Rest_API
 */

namespace PostNLWooCommerce\Tests\Rest_API;

use PHPUnit\Framework\TestCase;
use PostNLWooCommerce\Rest_API\Router;

/**
 * @covers \PostNLWooCommerce\Rest_API\Router
 */
class RouterTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['postnl_wp_filters'] = array();
	}

	/**
	 * Every flow in SUPPORTED_FLOWS must return false when no filter is registered.
	 *
	 * @dataProvider supported_flows_provider
	 */
	public function test_every_supported_flow_defaults_to_false( string $flow ): void {
		$this->assertFalse( Router::sdk_enabled_for( $flow ) );
	}

	/** @return array<string, array{string}> */
	public function supported_flows_provider(): array {
		$cases = array();
		foreach ( Router::SUPPORTED_FLOWS as $flow ) {
			$cases[ $flow ] = array( $flow );
		}
		return $cases;
	}

	public function test_add_filter_enables_barcode(): void {
		add_filter( 'postnl_sdk_enable_barcode', '__return_true' );

		$this->assertTrue( Router::sdk_enabled_for( 'barcode' ) );
	}

	public function test_enabling_barcode_does_not_enable_other_flows(): void {
		add_filter( 'postnl_sdk_enable_barcode', '__return_true' );

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

	public function test_unknown_flow_returns_false(): void {
		$this->assertFalse( Router::sdk_enabled_for( 'postcode_check' ) );
		$this->assertFalse( Router::sdk_enabled_for( 'unknown_flow' ) );
		$this->assertFalse( Router::sdk_enabled_for( '' ) );
	}

	public function test_postcode_check_is_not_in_supported_flows(): void {
		$this->assertFalse( in_array( 'postcode_check', Router::SUPPORTED_FLOWS, true ) );
	}
}
