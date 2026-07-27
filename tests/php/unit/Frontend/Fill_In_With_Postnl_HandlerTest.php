<?php
/**
 * Unit tests for Fill_In_With_Postnl_Handler.
 *
 * @package PostNLWooCommerce\Tests\Frontend
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Frontend;

use Brain\Monkey\Functions;
use Mockery;
use PostNLWooCommerce\Frontend\Fill_In_With_Postnl_Handler;
use PostNLWooCommerce\Tests\UnitTestCase;
use ReflectionClass;

/**
 * Covers the country resolution logic that maps a PostNL primary address onto a
 * two-letter WooCommerce country code.
 *
 * @covers \PostNLWooCommerce\Frontend\Fill_In_With_Postnl_Handler
 */
class Fill_In_With_Postnl_HandlerTest extends UnitTestCase {

	/**
	 * Invoke the private resolve_country_code() against a handler instance built
	 * without its constructor, so no WordPress bootstrap is required.
	 *
	 * @param array       $address PostNL primaryAddress payload.
	 * @param object|null $logger  Optional fake logger injected into the SUT.
	 * @return string
	 */
	private function resolve( array $address, $logger = null ): string {
		$reflection = new ReflectionClass( Fill_In_With_Postnl_Handler::class );
		$sut        = $reflection->newInstanceWithoutConstructor();

		if ( null !== $logger ) {
			$logger_property = $reflection->getProperty( 'logger' );
			$logger_property->setAccessible( true );
			$logger_property->setValue( $sut, $logger );
		}

		$method = $reflection->getMethod( 'resolve_country_code' );
		$method->setAccessible( true );

		return $method->invoke( $sut, $address );
	}

	/**
	 * Stub WC()->countries->get_countries() with a fixed catalogue so the
	 * locale-name lookup branch can run without WooCommerce loaded.
	 *
	 * @param array $countries Map of code => localized country name.
	 * @return void
	 */
	private function stub_wc_countries( array $countries ): void {
		$countries_store = Mockery::mock();
		$countries_store->shouldReceive( 'get_countries' )->andReturn( $countries );

		$wc            = new \stdClass();
		$wc->countries = $countries_store;

		Functions\when( 'WC' )->justReturn( $wc );
	}

	/**
	 * @dataProvider country_code_provider
	 * @testdox Should prefer the explicit countryCode and upper-case it.
	 *
	 * @param array  $address  Address payload.
	 * @param string $expected Expected country code.
	 */
	public function test_resolve_country_code_prefers_country_code( array $address, string $expected ): void {
		$this->assertSame( $expected, $this->resolve( $address ) );
	}

	/**
	 * @return array<string, array{array<string, string>, string}>
	 */
	public static function country_code_provider(): array {
		return array(
			'uppercase BE'                => array( array( 'countryCode' => 'BE' ), 'BE' ),
			'lowercase be'                => array( array( 'countryCode' => 'be' ), 'BE' ),
			'lowercase nl'                => array( array( 'countryCode' => 'nl' ), 'NL' ),
			'code wins over country name' => array( array( 'countryCode' => 'BE', 'countryName' => 'Nederland' ), 'BE' ),
		);
	}

	/**
	 * @dataProvider known_country_name_provider
	 * @testdox Should map a known PostNL country name to the right code regardless of case.
	 *
	 * @param string $country_name Country name as supplied by PostNL.
	 * @param string $expected     Expected country code.
	 */
	public function test_resolve_country_code_maps_known_country_names( string $country_name, string $expected ): void {
		$this->assertSame( $expected, $this->resolve( array( 'countryName' => $country_name ) ) );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function known_country_name_provider(): array {
		return array(
			'nederland'            => array( 'nederland', 'NL' ),
			'netherlands'          => array( 'netherlands', 'NL' ),
			'belgie'               => array( 'belgie', 'BE' ),
			'belgie accented'      => array( 'belgië', 'BE' ),
			'belgium'              => array( 'belgium', 'BE' ),
			'belgique'             => array( 'belgique', 'BE' ),
			'belgien'              => array( 'belgien', 'BE' ),
			'mixed case belgium'   => array( 'Belgium', 'BE' ),
			'upper accented belgie' => array( 'BELGIË', 'BE' ),
		);
	}

	/**
	 * @testdox Should fall back to the WooCommerce locale lookup for an unmapped but valid country name.
	 */
	public function test_resolve_country_code_falls_back_to_woocommerce_lookup(): void {
		$this->stub_wc_countries( array( 'FR' => 'France', 'DE' => 'Germany' ) );

		$logger = Mockery::mock();
		$logger->shouldNotReceive( 'write' );

		$this->assertSame( 'FR', $this->resolve( array( 'countryName' => 'France' ), $logger ) );
	}

	/**
	 * @testdox Should default to NL and log the unmapped name when nothing matches.
	 */
	public function test_resolve_country_code_defaults_to_nl_and_logs_unmapped_name(): void {
		$this->stub_wc_countries( array( 'FR' => 'France' ) );

		$logger = Mockery::mock();
		$logger->shouldReceive( 'write' )
			->once()
			->with( Mockery::pattern( '/Unmapped country name "Atlantis"/' ) );

		$this->assertSame( 'NL', $this->resolve( array( 'countryName' => 'Atlantis' ), $logger ) );
	}

	/**
	 * @testdox Should default to NL and log when the address carries no country at all.
	 */
	public function test_resolve_country_code_defaults_to_nl_for_empty_address(): void {
		$this->stub_wc_countries( array( 'FR' => 'France' ) );

		$logger = Mockery::mock();
		$logger->shouldReceive( 'write' )->once();

		$this->assertSame( 'NL', $this->resolve( array(), $logger ) );
	}
}
