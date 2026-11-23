<?php
/**
 * Unit tests for Address_Utils::is_postcode_complete().
 *
 * @package PostNLWooCommerce\Tests
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests;

use PostNLWooCommerce\Address_Utils;

/**
 * @covers \PostNLWooCommerce\Address_Utils::is_postcode_complete
 */
class AddressUtilsPostcodeTest extends UnitTestCase {

	/**
	 * Partial postcodes typed while the checkout refreshes must not be treated as complete.
	 *
	 * @dataProvider incomplete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_incomplete_postcodes_are_rejected( string $country, string $postcode ): void {
		$this->assertFalse( Address_Utils::is_postcode_complete( $country, $postcode ) );
	}

	/**
	 * Complete NL postcodes, with or without the space, and 4 digit BE postcodes are accepted.
	 *
	 * @dataProvider complete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_complete_postcodes_are_accepted( string $country, string $postcode ): void {
		$this->assertTrue( Address_Utils::is_postcode_complete( $country, $postcode ) );
	}

	/**
	 * Countries without a rule keep today's behaviour.
	 */
	public function test_other_countries_are_not_restricted(): void {
		$this->assertTrue( Address_Utils::is_postcode_complete( 'DE', '1' ) );
		$this->assertTrue( Address_Utils::is_postcode_complete( '', '' ) );
	}

	/**
	 * Partial postcodes per country.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function incomplete_postcodes(): array {
		return array(
			'NL 1 digit'          => array( 'NL', '6' ),
			'NL 2 digits'         => array( 'NL', '65' ),
			'NL 4 digits'         => array( 'NL', '6591' ),
			'NL 4 digits space'   => array( 'NL', '6591 ' ),
			'NL 1 letter'         => array( 'NL', '6591 B' ),
			'NL 1 letter no gap'  => array( 'NL', '6591B' ),
			'NL leading zero'     => array( 'NL', '0591 BA' ),
			'NL lowercase cc'     => array( 'nl', '6591 B' ),
			'BE 1 digit'          => array( 'BE', '2' ),
			'BE 3 digits'         => array( 'BE', '206' ),
			'BE 5 digits'         => array( 'BE', '20601' ),
			'BE letters'          => array( 'BE', '20AB' ),
		);
	}

	/**
	 * Complete postcodes per country.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function complete_postcodes(): array {
		return array(
			'NL with space'    => array( 'NL', '6591 BA' ),
			'NL without space' => array( 'NL', '6591BA' ),
			'NL lowercase'     => array( 'NL', '6591 ba' ),
			'NL padded'        => array( 'NL', ' 6591BA ' ),
			'BE'               => array( 'BE', '2060' ),
		);
	}
}
