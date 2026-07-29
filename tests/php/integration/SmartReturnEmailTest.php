<?php
/**
 * Integration tests for the Smart Return email printcode rendering.
 *
 * Covers the V4 change: the retailPrint printcode/barcode is embedded inline in
 * the email body (no PDF attachment), while the legacy PDF-attachment path is
 * preserved.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Emails\WC_Email_Smart_Return;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises WC_Email_Smart_Return content and attachment behaviour.
 */
class SmartReturnEmailTest extends IntegrationTestCase {

	/**
	 * Build an order with a billing first name for the email greeting.
	 *
	 * @return \WC_Order
	 */
	private function make_order(): \WC_Order {
		$order = new \WC_Order();
		$order->set_billing_first_name( 'Jan' );
		$order->set_billing_email( 'buyer@example.com' );
		$order->save();

		return $order;
	}

	/**
	 * @testdox The V4 printcode renders inline (cid) in the body with no PDF attachment.
	 */
	public function test_v4_printcode_renders_inline_without_attachment(): void {
		$email                    = new WC_Email_Smart_Return();
		$email->object            = $this->make_order();
		$email->printcode_content = 'RAWPNGBYTES';
		$email->printcode_mime    = 'image/png';
		$email->printcode_barcode = '3SDEVC12345678';

		$html = $email->get_content_html();

		$this->assertStringContainsString( 'cid:' . $email->printcode_cid, $html, 'The printcode image must be referenced inline in the body.' );
		$this->assertStringContainsString( '3SDEVC12345678', $html, 'The barcode must be shown as a text fallback when the image is blocked.' );
		$this->assertSame( array(), $email->get_attachments(), 'The V4 printcode path must not attach a PDF.' );
	}

	/**
	 * @testdox The legacy path attaches the PDF and renders no inline printcode image.
	 */
	public function test_legacy_path_attaches_pdf(): void {
		$file = wp_tempnam( 'postnl-printcode' );
		file_put_contents( $file, '%PDF-1.4 test' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture file.

		$email             = new WC_Email_Smart_Return();
		$email->object     = $this->make_order();
		$email->attachment = $file;

		$html = $email->get_content_html();

		$this->assertStringNotContainsString( 'cid:', $html, 'The legacy path must not embed an inline printcode image.' );
		$this->assertContains( $file, $email->get_attachments(), 'The legacy path must attach the printcode PDF.' );

		unlink( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Cleaning up the test fixture.
	}

	/**
	 * @testdox embed_printcode_image() adds the printcode as an inline cid attachment.
	 */
	public function test_embed_adds_inline_cid_image(): void {
		if ( ! class_exists( \PHPMailer\PHPMailer\PHPMailer::class ) ) {
			require_once ABSPATH . WPINC . '/PHPMailer/PHPMailer.php';
			require_once ABSPATH . WPINC . '/PHPMailer/Exception.php';
		}

		$email                    = new WC_Email_Smart_Return();
		$email->printcode_content = 'RAWPNGBYTES';
		$email->printcode_mime    = 'image/png';

		$phpmailer = new \PHPMailer\PHPMailer\PHPMailer( true );
		$email->embed_printcode_image( $phpmailer );

		$attachments = $phpmailer->getAttachments();
		$this->assertCount( 1, $attachments );
		$this->assertSame( 'inline', $attachments[0][6], 'The image must be an inline attachment.' );
		$this->assertSame( $email->printcode_cid, $attachments[0][7], 'The inline image must carry the printcode cid.' );
	}
}
