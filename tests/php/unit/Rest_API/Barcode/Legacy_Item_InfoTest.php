<?php
/**
 * Unit tests for the legacy barcode type selection in Rest_API\Legacy\Barcode\Item_Info.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\Barcode
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\Barcode;

use PostNLWooCommerce\Rest_API\Legacy\Barcode\Item_Info;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\Legacy\Barcode\Item_Info::check_product_barcode_type
 */
class Legacy_Item_InfoTest extends UnitTestCase {

	/**
	 * Run check_product_barcode_type() for the given saved backend data.
	 *
	 * The constructor reads settings and the order, so it is skipped and only the
	 * backend data the method reads is set.
	 *
	 * @param array $backend_data Saved meta box values for the order.
	 * @return string
	 */
	private function barcode_type( array $backend_data ): string {
		$item_info = ( new \ReflectionClass( Item_Info::class ) )->newInstanceWithoutConstructor();

		$property = new \ReflectionProperty( Item_Info::class, 'api_args' );
		$property->setAccessible( true );
		$property->setValue( $item_info, array( 'backend_data' => $backend_data ) );

		return $item_info->check_product_barcode_type( '3S' );
	}

	/**
	 * @testdox A bulk-set EU order saved with Packets and Track & Trace gets an LA barcode
	 *
	 * Bulk "Change shipping options" stores the eu_parcel product marker as a "yes"
	 * value, and it survives a later meta box save. Counted as a selected option, it
	 * stopped the selection matching any custom barcode combination, so the order
	 * fell back to a 3S barcode instead of the LA one a meta-box-only order gets.
	 */
	public function test_bulk_marker_does_not_block_the_la_barcode(): void {
		$this->assertSame(
			'LA',
			$this->barcode_type(
				array(
					'eu_parcel'       => 'yes',
					'track_and_trace' => 'yes',
					'packets'         => 'yes',
				)
			)
		);
	}

	/**
	 * @testdox The same options saved from the meta box only also get an LA barcode
	 */
	public function test_meta_box_only_order_gets_la_barcode(): void {
		$this->assertSame(
			'LA',
			$this->barcode_type(
				array(
					'track_and_trace' => 'yes',
					'packets'         => 'yes',
				)
			)
		);
	}

	/**
	 * @testdox A bulk-set order without a custom combination keeps the default barcode type
	 */
	public function test_bulk_marker_alone_keeps_default_type(): void {
		$this->assertSame( '3S', $this->barcode_type( array( 'eu_parcel' => 'yes' ) ) );
	}
}
