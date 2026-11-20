<?php
/**
 * Integration tests for label PDF merging on top of setasign/fpdi.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Library\CustomizedPDFMerger;
use PostNLWooCommerce\Library\PDF_Rotate;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\PdfReader\PdfReaderException;

/**
 * Merges generated labels and feeds the parser malformed PDFs.
 */
class LabelPdfMergeTest extends IntegrationTestCase {

	/**
	 * Files created during the test.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backup of the store country.
	 *
	 * @var mixed
	 */
	private $orig_country;

	/**
	 * Use an NL store so the label format setting is read.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$this->orig_settings = Settings::get_instance()->settings;
	}

	/**
	 * Remove generated files and restore global state.
	 */
	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		Settings::get_instance()->settings = $this->orig_settings;
		update_option( 'woocommerce_default_country', $this->orig_country );

		parent::tearDown();
	}

	/**
	 * Write a one page label PDF of the given size in millimetres.
	 *
	 * @param float $width  Page width.
	 * @param float $height Page height.
	 *
	 * @return string Path to the PDF.
	 */
	private function make_label( float $width, float $height ): string {
		$pdf = new \FPDF( $width > $height ? 'L' : 'P', 'mm', array( $width, $height ) );
		$pdf->AddPage();
		$pdf->SetFont( 'Helvetica', '', 12 );
		$pdf->Cell( 40, 10, 'PostNL label' );

		$path          = tempnam( sys_get_temp_dir(), 'postnl-label' ) . '.pdf';
		$this->files[] = $path;
		$pdf->Output( 'F', $path );

		return $path;
	}

	/**
	 * Write a PDF body with a correct cross-reference table.
	 *
	 * @param string[] $objects Object bodies, numbered from 1.
	 * @param string   $trailer Extra trailer entries; %XREF% is replaced by the xref offset.
	 *
	 * @return string Path to the PDF.
	 */
	private function make_raw_pdf( array $objects, string $trailer ): string {
		$pdf     = "%PDF-1.4\n";
		$offsets = array();

		foreach ( $objects as $index => $body ) {
			$offsets[] = strlen( $pdf );
			$pdf      .= ( $index + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}

		$xref = strlen( $pdf );
		$pdf .= "xref\n0 " . ( count( $objects ) + 1 ) . "\n0000000000 65535 f \n";

		foreach ( $offsets as $offset ) {
			$pdf .= sprintf( "%010d 00000 n \n", $offset );
		}

		$pdf .= 'trailer << /Size ' . ( count( $objects ) + 1 ) . ' /Root 1 0 R ' . str_replace( '%XREF%', (string) $xref, $trailer ) . " >>\n";
		$pdf .= "startxref\n" . $xref . "\n%%EOF\n";

		$path          = tempnam( sys_get_temp_dir(), 'postnl-raw' ) . '.pdf';
		$this->files[] = $path;
		file_put_contents( $path, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return $path;
	}

	/**
	 * Count the pages of a PDF string.
	 *
	 * @param string $pdf PDF content.
	 *
	 * @return int
	 */
	private function page_count( string $pdf ): int {
		$path          = tempnam( sys_get_temp_dir(), 'postnl-merged' ) . '.pdf';
		$this->files[] = $path;
		file_put_contents( $path, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return ( new PDF_Rotate() )->setSourceFile( $path );
	}

	/**
	 * Label formats and start positions with the pages each merge must produce.
	 *
	 * @return array<string, array{0:string,1:string,2:int}>
	 */
	public static function merge_layouts(): array {
		return array(
			'A4 top-left'     => array( 'A4', 'top-left', 1 ),
			'A4 bottom-right' => array( 'A4', 'bottom-right', 2 ),
			'A6'              => array( 'A6', 'top-left', 3 ),
		);
	}

	/**
	 * @testdox Merging portrait and landscape A6 labels gives a valid PDF with the expected pages.
	 *
	 * @dataProvider merge_layouts
	 *
	 * @param string $format   Label format setting.
	 * @param string $position Start position on the first A4 page.
	 * @param int    $pages    Expected page count.
	 */
	public function test_merge_labels( string $format, string $position, int $pages ): void {
		Settings::get_instance()->settings['label_format'] = $format;

		$merger = new CustomizedPDFMerger();
		$merger->addPDF( $this->make_label( 105, 148 ) );
		$merger->addPDF( $this->make_label( 148, 105 ) );
		$merger->addPDF( $this->make_label( 105, 148 ) );

		$output = $merger->merge( 'string', '', 'A', $position );

		$this->assertStringStartsWith( '%PDF-', $output );
		$this->assertSame( $pages, $this->page_count( $output ) );
	}

	/**
	 * @testdox A PDF whose cross-reference table points back to itself is rejected instead of looping.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_self_referencing_xref_is_rejected(): void {
		set_time_limit( 20 );
		ini_set( 'memory_limit', '128M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed

		$path = $this->make_raw_pdf(
			array(
				'<< /Type /Catalog /Pages 2 0 R >>',
				'<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
				'<< /Type /Page /Parent 2 0 R /MediaBox [0 0 298 420] >>',
			),
			'/Prev %XREF%'
		);

		$this->expectException( PdfParserException::class );

		( new PDF_Rotate() )->setSourceFile( $path );
	}

	/**
	 * @testdox A PDF whose page tree contains itself is rejected instead of recursing.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_self_referencing_page_tree_is_rejected(): void {
		set_time_limit( 20 );
		ini_set( 'memory_limit', '128M' ); // phpcs:ignore WordPress.PHP.IniSet.memory_limit_Disallowed

		$path = $this->make_raw_pdf(
			array(
				'<< /Type /Catalog /Pages 2 0 R >>',
				'<< /Type /Pages /Kids [2 0 R] /Count 1 >>',
			),
			''
		);

		$this->expectException( PdfReaderException::class );
		$this->expectExceptionMessage( 'Recursive pages dictionary detected.' );

		$pdf = new PDF_Rotate();
		$pdf->setSourceFile( $path );
		$pdf->importPage( 1 );
	}
}
