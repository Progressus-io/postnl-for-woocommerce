<?php
/**
 * Integration tests for Order\Base::harvest_barcodes_or_fail() (V4 reorder).
 *
 * The V4 branch generates the label before it knows the barcode, so a response
 * without one fails only after PDFs are already on disk. These tests pin the
 * cleanup contract: a failed harvest discards those files so a retry starts
 * clean instead of stacking orphans, and a successful harvest keeps them.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Base;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * @covers \PostNLWooCommerce\Order\Base::harvest_barcodes_or_fail
 */
class OrderBarcodeHarvestTest extends IntegrationTestCase {

	/**
	 * Build a minimal concrete Order\Base exposing the protected harvest.
	 *
	 * @return Base
	 */
	private function probe(): Base {
		return new class() extends Base {
			/**
			 * No hooks; the probe only exercises the harvest.
			 */
			public function init_hooks() {}

			/**
			 * Expose the protected harvest for the test.
			 *
			 * @param array $labels Label records.
			 * @return array
			 */
			public function harvest( $labels ) {
				return $this->harvest_barcodes_or_fail( $labels );
			}
		};
	}

	/**
	 * Create a real temp file standing in for a written label PDF.
	 *
	 * @return string Absolute file path.
	 */
	private function temp_label_file(): string {
		$path = tempnam( sys_get_temp_dir(), 'postnl-test-label' );
		file_put_contents( $path, 'pdf-stand-in' );

		return $path;
	}

	/** @testdox harvest_barcodes_or_fail() returns the barcodes and keeps the label files. */
	public function test_returns_barcodes_and_keeps_files(): void {
		$file   = $this->temp_label_file();
		$labels = array(
			'label' => array(
				'type'     => 'label',
				'barcode'  => '3SDEVC1',
				'filepath' => $file,
			),
		);

		$this->assertSame( array( '3SDEVC1' ), $this->probe()->harvest( $labels ) );
		$this->assertFileExists( $file );

		unlink( $file );
	}

	/** @testdox harvest_barcodes_or_fail() throws and discards the merged file and its parts when no barcode is found. */
	public function test_throws_and_discards_files_when_no_barcode(): void {
		$merged = $this->temp_label_file();
		$part_a = $this->temp_label_file();
		$part_b = $this->temp_label_file();

		$labels = array(
			'label' => array(
				'type'         => 'label',
				'barcode'      => '',
				'filepath'     => $merged,
				'merged_files' => array( $part_a, $part_b ),
			),
		);

		// try/catch instead of expectException: the file assertions must run
		// after the throw, and expectException ends the test at the boundary.
		try {
			$this->probe()->harvest( $labels );
			$this->fail( 'Expected an exception when the label response has no barcode.' );
		} catch ( \Exception $e ) {
			$this->assertStringContainsString( 'barcode', $e->getMessage() );
		}

		$this->assertFileDoesNotExist( $merged );
		$this->assertFileDoesNotExist( $part_a );
		$this->assertFileDoesNotExist( $part_b );
	}
}
