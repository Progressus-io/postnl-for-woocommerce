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
 * @covers \PostNLWooCommerce\Order\Base::resolve_parent_barcode
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

	/**
	 * Invoke the protected static parent-barcode resolver.
	 *
	 * @param mixed  $labels         Raw label records built from the API response.
	 * @param string $parent_barcode Prefetched parent barcode, empty on the V4 path.
	 * @return string
	 */
	private function resolve( $labels, string $parent_barcode ): string {
		$method = new ReflectionMethod( Base::class, 'resolve_parent_barcode' );
		$method->setAccessible( true );
		return $method->invoke( null, $labels, $parent_barcode );
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

	/**
	 * @testdox resolve_parent_barcode() keeps the prefetched barcode so the Legacy merge is unchanged.
	 *
	 * Legacy always passes main_barcode; the resolver must never override it.
	 */
	public function test_keeps_prefetched_parent_barcode(): void {
		$labels = array(
			array( 'barcode' => '3SFIRST' ),
			array( 'barcode' => '3SSECOND' ),
		);

		$this->assertSame( '3SMAIN', $this->resolve( $labels, '3SMAIN' ) );
	}

	/**
	 * @testdox resolve_parent_barcode() falls back to the first response barcode when none was prefetched.
	 *
	 * The V4 path has no prefetched barcode. Without this fallback maybe_merge_labels()
	 * writes an empty barcode into the merged record for every non-A6 or multi-collo
	 * order, so the harvest finds nothing and label generation throws.
	 */
	public function test_falls_back_to_first_response_barcode(): void {
		$labels = array(
			array( 'barcode' => '3SFIRST' ),
			array( 'barcode' => '3SSECOND' ),
		);

		$this->assertSame( '3SFIRST', $this->resolve( $labels, '' ) );
	}

	/** @testdox resolve_parent_barcode() skips empty barcodes and tolerates non-arrays. */
	public function test_resolver_skips_empty_and_tolerates_non_arrays(): void {
		$this->assertSame( '', $this->resolve( 'not-an-array', '' ) );
		$this->assertSame( '', $this->resolve( array(), '' ) );
		$this->assertSame(
			'3SONLY',
			$this->resolve(
				array(
					array( 'type' => 'label' ),
					array( 'barcode' => '' ),
					array( 'barcode' => '3SONLY' ),
				),
				''
			)
		);
	}

	/**
	 * @testdox get_barcodes_from_labels() returns one barcode for a merged multi-collo record.
	 *
	 * Characterises the deferred parity gap: the merge collapses N collo into a
	 * single record, so the harvest yields one barcode where the Legacy prefetch
	 * records N. Recovering all N is part of the V4 multi-collo label work; this
	 * fails loudly if the merge shape changes before that lands.
	 */
	public function test_merged_multi_collo_record_yields_single_barcode(): void {
		$merged = array(
			'label' => array(
				'type'         => 'label',
				'barcode'      => '3SCOLLO1',
				'filepath'     => '/tmp/merged.pdf',
				'merged_files' => array( '/tmp/a.pdf', '/tmp/b.pdf', '/tmp/c.pdf' ),
			),
		);

		$this->assertSame( array( '3SCOLLO1' ), $this->harvest( $merged ) );
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
