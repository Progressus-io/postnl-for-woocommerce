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
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \PostNLWooCommerce\Order\Base::get_barcodes_from_labels
 * @covers \PostNLWooCommerce\Order\Base::resolve_parent_barcode
 * @covers \PostNLWooCommerce\Order\Base::merge_labels
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

	/**
	 * Run merge_labels() on a dispatch-recording Order\Base.
	 *
	 * @param Merge_Dispatch_Base $sut       Instance whose mergers are stubbed.
	 * @param string              $extension File extension the label paths carry.
	 * @return array
	 */
	private function merge( Merge_Dispatch_Base $sut, string $extension ): array {
		$method = new ReflectionMethod( Base::class, 'merge_labels' );
		$method->setAccessible( true );

		return $method->invoke(
			$sut,
			array( '/uploads/postnl/postnl-1-label-3SDEVC1-A6.' . $extension ),
			'postnl-1-label-3SDEVC1-A4-merged.' . $extension
		);
	}

	/**
	 * A dispatch-recording Order\Base built without its constructor, which would
	 * otherwise need the WooCommerce Settings singleton.
	 *
	 * @return Merge_Dispatch_Base
	 */
	private function merge_sut(): Merge_Dispatch_Base {
		return ( new ReflectionClass( Merge_Dispatch_Base::class ) )->newInstanceWithoutConstructor();
	}

	/**
	 * @testdox merge_labels() sends a .zpl label to the text merger.
	 *
	 * The V4 label path names its files from the SDK's LabelOutputType enum, whose
	 * Zebra case is the bare string 'zpl'. With no matching case the switch falls
	 * through to `return array()`, and maybe_merge_labels() then stamps a merged
	 * record whose filepath is null — a Zebra merchant on Label Format A4 gets an
	 * order with no downloadable label and no error. Eligibility gates neither the
	 * printer type nor the label format, so this is reachable on a single domestic
	 * parcel.
	 */
	public function test_zpl_labels_dispatch_to_the_text_merger(): void {
		$sut    = $this->merge_sut();
		$result = $this->merge( $sut, 'zpl' );

		$this->assertSame( 'text', $sut->dispatched );
		$this->assertSame( 'merged:postnl-1-label-3SDEVC1-A4-merged.zpl', $result['filepath'] );
	}

	/**
	 * @testdox merge_labels() still sends a .zpl_rle label to the text merger.
	 *
	 * Parity guard: the Legacy path derives the extension from the V1 response's
	 * OutputType, so its Zebra spelling must keep resolving to the same merger.
	 */
	public function test_zpl_rle_labels_dispatch_to_the_text_merger(): void {
		$sut = $this->merge_sut();
		$this->merge( $sut, 'zpl_rle' );

		$this->assertSame( 'text', $sut->dispatched );
	}

	/** @testdox merge_labels() still routes the other extensions to their own mergers. */
	public function test_other_extensions_keep_their_mergers(): void {
		foreach ( array(
			'pdf' => 'pdf',
			'jpg' => 'jpg',
			'gif' => 'graphic',
		) as $extension => $expected ) {
			$sut = $this->merge_sut();
			$this->merge( $sut, $extension );

			$this->assertSame( $expected, $sut->dispatched, $extension . ' must not change merger' );
		}
	}

	/** @testdox merge_labels() returns an empty array for an extension it cannot merge. */
	public function test_unknown_extension_merges_nothing(): void {
		$sut = $this->merge_sut();

		$this->assertSame( array(), $this->merge( $sut, 'txt' ) );
		$this->assertSame( '', $sut->dispatched );
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

/**
 * Concrete Order\Base that records which merger the extension switch picked.
 *
 * Every merger is stubbed, so the switch can be exercised without Imagick, the
 * PDF merger, POSTNL_UPLOADS_DIR or any filesystem access.
 */
class Merge_Dispatch_Base extends Base { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Merger the last merge_labels() call dispatched to.
	 *
	 * @var string
	 */
	public $dispatched = '';

	/**
	 * Required by Order\Base; nothing to hook in a unit test.
	 */
	public function init_hooks() {
		// Intentionally empty.
	}

	/**
	 * @param array  $label_paths    Files to merge.
	 * @param string $merge_filename Merged filename.
	 * @param string $start_position PDF start position.
	 * @return array
	 */
	protected function merge_pdf_labels( $label_paths, $merge_filename, $start_position = 'top-left' ) {
		return $this->record( 'pdf', $merge_filename );
	}

	/**
	 * @param array  $image_paths    Files to merge.
	 * @param string $merge_filename Merged filename.
	 * @param string $direction      Stacking direction.
	 * @return array
	 */
	protected function merge_jpg_files( $image_paths, $merge_filename, $direction = 'vertical' ) {
		return $this->record( 'jpg', $merge_filename );
	}

	/**
	 * @param array  $label_paths    Files to merge.
	 * @param string $merge_filename Merged filename.
	 * @return array
	 */
	protected function merge_graphic_labels( $label_paths, $merge_filename ) {
		return $this->record( 'graphic', $merge_filename );
	}

	/**
	 * @param array  $label_paths    Files to merge.
	 * @param string $merge_filename Merged filename.
	 * @return array
	 */
	protected function merge_text_files( $label_paths, $merge_filename ) {
		return $this->record( 'text', $merge_filename );
	}

	/**
	 * Record the dispatch and return a merge result in the production shape.
	 *
	 * @param string $merger         Merger name.
	 * @param string $merge_filename Merged filename.
	 * @return array
	 */
	private function record( string $merger, string $merge_filename ): array {
		$this->dispatched = $merger;

		return array(
			'merged_filepaths' => array(),
			'filepath'         => 'merged:' . $merge_filename,
		);
	}
}
