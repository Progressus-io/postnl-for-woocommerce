<?php
/**
 * Unit tests for Order\Base barcode harvesting (V4 reorder).
 *
 * @package PostNLWooCommerce\Tests\Order
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Order;

use PostNLWooCommerce\Order\Base;
use PostNLWooCommerce\Tests\UnitTestCase;
use ReflectionMethod;

/**
 * @covers \PostNLWooCommerce\Order\Base::get_barcodes_from_labels
 */
class BaseTest extends UnitTestCase {

	/**
	 * Invoke the protected static harvest helper without instantiating Order\Base
	 * (its constructor needs WooCommerce/Settings; static invocation needs neither).
	 *
	 * @param mixed $labels Label records passed to the helper.
	 * @return array
	 */
	private function harvest( $labels ): array {
		$method = new ReflectionMethod( Base::class, 'get_barcodes_from_labels' );
		$method->setAccessible( true );
		return $method->invoke( null, $labels );
	}

	/** @testdox get_barcodes_from_labels() reads the barcode key out of each label record */
	public function test_harvests_barcode_from_label_records(): void {
		$labels = array(
			'label' => array(
				'type'     => 'label',
				'barcode'  => '3SDEVC123456789',
				'filepath' => '/tmp/x.pdf',
			),
		);

		$this->assertSame( array( '3SDEVC123456789' ), $this->harvest( $labels ) );
	}

	/** @testdox get_barcodes_from_labels() collects distinct barcodes across multiple records */
	public function test_harvests_distinct_barcodes(): void {
		$labels = array(
			array( 'barcode' => '3SAAA' ),
			array( 'barcode' => '3SBBB' ),
			array( 'barcode' => '3SAAA' ),
		);

		$this->assertSame( array( '3SAAA', '3SBBB' ), $this->harvest( $labels ) );
	}

	/** @testdox get_barcodes_from_labels() skips records with no barcode and tolerates non-arrays */
	public function test_ignores_empty_and_non_array_input(): void {
		$this->assertSame( array(), $this->harvest( array() ) );
		$this->assertSame( array(), $this->harvest( 'not-an-array' ) );
		$this->assertSame(
			array( '3SONLY' ),
			$this->harvest(
				array(
					array( 'type' => 'label' ),
					array( 'barcode' => '' ),
					array( 'barcode' => '3SONLY' ),
				)
			)
		);
	}
}
