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
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfReader\PdfReaderException;

/**
 * Merges generated labels and feeds the parser malformed PDFs.
 */
class LabelPdfMergeTest extends IntegrationTestCase {

	/**
	 * Content stream operator PDF_Rotate::Rotate( 90 ) writes for a portrait label on an A4 sheet.
	 */
	private const ROTATE_90 = 'q 0.00000 1.00000 -1.00000 0.00000';

	/**
	 * Files created during the test.
	 *
	 * @var string[]
	 */
	private $files = array();

	/**
	 * Backup of the settings singleton's loaded option array, when a test changed it.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * Backup of the store country, when a test changed it.
	 *
	 * @var mixed
	 */
	private $orig_country = null;

	/**
	 * Remove generated files and restore what a test changed.
	 */
	protected function tearDown(): void {
		foreach ( $this->files as $file ) {
			if ( file_exists( $file ) ) {
				unlink( $file );
			}
		}

		if ( null !== $this->orig_settings ) {
			Settings::get_instance()->settings = $this->orig_settings;
			update_option( 'woocommerce_default_country', $this->orig_country );
		}

		parent::tearDown();
	}

	/**
	 * Use an NL store with the given label format.
	 *
	 * @param string $format Label format setting, A4 or A6.
	 */
	private function use_label_format( string $format ): void {
		$this->orig_country  = get_option( 'woocommerce_default_country' );
		$this->orig_settings = Settings::get_instance()->settings;

		update_option( 'woocommerce_default_country', 'NL' );
		Settings::get_instance()->settings['label_format'] = $format;
	}

	/**
	 * Return a new temporary .pdf path; it and the file tempnam() creates are removed in tearDown.
	 *
	 * @param string $prefix File name prefix.
	 *
	 * @return string
	 */
	private function temp_path( string $prefix ): string {
		$base          = tempnam( sys_get_temp_dir(), $prefix );
		$this->files[] = $base;
		$this->files[] = $base . '.pdf';

		return $base . '.pdf';
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

		$path = $this->temp_path( 'postnl-label' );
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

		$path = $this->temp_path( 'postnl-raw' );
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
		$path = $this->temp_path( 'postnl-merged' );
		file_put_contents( $path, $pdf ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		return ( new PDF_Rotate() )->setSourceFile( $path );
	}

	/**
	 * Return the decoded content streams of a PDF string, joined.
	 *
	 * @param string $pdf PDF content.
	 *
	 * @return string
	 */
	private function content_streams( string $pdf ): string {
		preg_match_all( '/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches );

		$decoded = array_map(
			static function ( $stream ) {
				$inflated = @gzuncompress( $stream ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Uncompressed streams are kept as they are.

				return false === $inflated ? $stream : $inflated;
			},
			$matches[1]
		);

		return implode( "\n", $decoded );
	}

	/**
	 * Label formats and start positions with the pages each merge must produce.
	 *
	 * @return array<string, array{0:string,1:string,2:int,3:bool}>
	 */
	public static function merge_layouts(): array {
		return array(
			'A4 top-left'     => array( 'A4', 'top-left', 1, true ),
			'A4 bottom-right' => array( 'A4', 'bottom-right', 2, true ),
			'A6'              => array( 'A6', 'top-left', 3, false ),
		);
	}

	/**
	 * @testdox Merging portrait and landscape A6 labels gives a valid PDF with the expected pages and rotation.
	 *
	 * @dataProvider merge_layouts
	 *
	 * @param string $format   Label format setting.
	 * @param string $position Start position on the first A4 page.
	 * @param int    $pages    Expected page count.
	 * @param bool   $rotated  Whether portrait labels are rotated onto the A4 sheet.
	 */
	public function test_merge_labels( string $format, string $position, int $pages, bool $rotated ): void {
		$this->use_label_format( $format );

		$merger = new CustomizedPDFMerger();
		$merger->addPDF( $this->make_label( 105, 148 ) );
		$merger->addPDF( $this->make_label( 148, 105 ) );
		$merger->addPDF( $this->make_label( 105, 148 ) );

		$output = $merger->merge( 'string', '', 'A', $position );

		$this->assertStringStartsWith( '%PDF-', $output );
		$this->assertSame( $pages, $this->page_count( $output ) );
		$this->assertSame( $rotated, false !== strpos( $this->content_streams( $output ), self::ROTATE_90 ) );
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

		$this->expectException( CrossReferenceException::class );
		$this->expectExceptionCode( CrossReferenceException::CYCLIC_STRUCTURE );

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

		$pdf = new PDF_Rotate();
		$pdf->setSourceFile( $path );
		$pdf->importPage( 1 );
	}
}
