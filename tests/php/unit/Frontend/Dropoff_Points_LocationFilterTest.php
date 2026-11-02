<?php
/**
 * Unit tests for the Frontend\Dropoff_Points location filter.
 *
 * @package PostNLWooCommerce\Tests\Frontend
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Frontend;

use PostNLWooCommerce\Frontend\Dropoff_Points;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Frontend\Dropoff_Points::is_usable_location
 */
class Dropoff_Points_LocationFilterTest extends UnitTestCase {

	/**
	 * Call the protected filter without running the WooCommerce-dependent constructor.
	 *
	 * @param array $location Location from the PickupOptions response.
	 * @param bool  $is_v4    Whether the location came from the V4 pickup service.
	 * @return bool
	 */
	private function is_usable( array $location, bool $is_v4 ): bool {
		$dropoff = ( new \ReflectionClass( Dropoff_Points::class ) )->newInstanceWithoutConstructor();
		$method  = new \ReflectionMethod( Dropoff_Points::class, 'is_usable_location' );
		$method->setAccessible( true );

		return $method->invoke( $dropoff, $location, $is_v4 );
	}

	/**
	 * A V4-shaped location: code and address, no PartnerID or PickupTime.
	 *
	 * @return array
	 */
	private function v4_location(): array {
		return array(
			'LocationCode' => '176227',
			'Distance'     => '',
			'Address'      => array( 'CompanyName' => 'Jumbo Den Haag' ),
		);
	}

	/**
	 * A complete legacy location.
	 *
	 * @return array
	 */
	private function legacy_location(): array {
		return array(
			'LocationCode' => '176227',
			'PartnerID'    => 'PNPNL-01',
			'PickupTime'   => '15:00:00',
			'Distance'     => '523',
			'Address'      => array( 'CompanyName' => 'Jumbo Den Haag' ),
		);
	}

	/**
	 * @testdox A V4 location without PartnerID, PickupTime or Distance is shown
	 */
	public function test_v4_location_without_legacy_fields_is_shown(): void {
		$this->assertTrue( $this->is_usable( $this->v4_location(), true ) );
	}

	/**
	 * @testdox A V4 location without a location code is dropped
	 */
	public function test_v4_location_without_code_is_dropped(): void {
		$location = $this->v4_location();
		unset( $location['LocationCode'] );

		$this->assertFalse( $this->is_usable( $location, true ) );
	}

	/**
	 * @testdox A complete legacy location is shown
	 */
	public function test_complete_legacy_location_is_shown(): void {
		$this->assertTrue( $this->is_usable( $this->legacy_location(), false ) );
	}

	/**
	 * @testdox A legacy location missing PartnerID, PickupTime or Distance is still dropped
	 *
	 * @dataProvider legacy_required_fields_provider
	 *
	 * @param string $field Field removed from the legacy location.
	 */
	public function test_legacy_location_missing_a_field_is_dropped( string $field ): void {
		$location = $this->legacy_location();
		unset( $location[ $field ] );

		$this->assertFalse( $this->is_usable( $location, false ) );
	}

	/**
	 * Fields the legacy check has always required.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function legacy_required_fields_provider(): array {
		return array(
			'PartnerID'  => array( 'PartnerID' ),
			'PickupTime' => array( 'PickupTime' ),
			'Distance'   => array( 'Distance' ),
			'Address'    => array( 'Address' ),
		);
	}
}
