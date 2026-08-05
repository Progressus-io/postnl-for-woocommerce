<?php
/**
 * Unit tests for the V4 no-op barcode service.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Barcode
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Barcode;

use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\V4\Barcode\Service;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Barcode\Service
 */
class ServiceTest extends UnitTestCase {

	/** @testdox Service implements the shared Barcode_Service_Interface */
	public function test_implements_barcode_interface(): void {
		$this->assertInstanceOf( Barcode_Service_Interface::class, new Service() );
	}

	/**
	 * @testdox generate() is a no-op that returns an empty array without any HTTP request
	 *
	 * V4 has no standalone barcode endpoint; the barcode is issued by the label
	 * call. Brain\Monkey makes no HTTP function available, so a request here would
	 * fatal — the empty return proves generate() performs none.
	 */
	public function test_generate_returns_empty_array(): void {
		$this->assertSame( array(), ( new Service() )->generate( array( 'anything' => 'ignored' ) ) );
	}
}
