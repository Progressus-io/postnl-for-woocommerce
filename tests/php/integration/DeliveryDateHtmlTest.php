<?php
/**
 * Integration tests for the order metabox delivery-date rendering.
 *
 * Single::generate_delivery_date_html() converts the stored delivery_day_date
 * to the Dutch d/m/Y format for display. The stored value originates from
 * sanitised frontend input, so a value that does not parse as Y-m-d must not be
 * allowed to fatal the whole order edit screen.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Shipping_Method\Settings;

/**
 * Covers the delivery-date render guard on the order metabox.
 */
class DeliveryDateHtmlTest extends IntegrationTestCase {

	/**
	 * @testdox An unparseable delivery date renders without fataling the order screen.
	 */
	public function test_generate_delivery_date_html_does_not_fatal_on_unparseable_date(): void {
		$handler = $this->make_single_handler();

		$output = $this->capture_delivery_date_html( $handler, '31-12-2026' );

		$this->assertStringContainsString(
			'31-12-2026',
			$output,
			'A delivery date that is not Y-m-d must fall back to the stored value instead of throwing.'
		);
	}

	/**
	 * @testdox A valid Y-m-d delivery date is rendered in the Dutch d/m/Y format.
	 */
	public function test_generate_delivery_date_html_formats_valid_date_as_dutch(): void {
		$handler = $this->make_single_handler();

		$output = $this->capture_delivery_date_html( $handler, '2026-12-31' );

		$this->assertStringContainsString(
			'31/12/2026',
			$output,
			'A valid Y-m-d date must still be converted to the Dutch d/m/Y format.'
		);
	}

	/**
	 * Render generate_delivery_date_html() and return the captured markup.
	 *
	 * @param Single $handler Order handler under test.
	 * @param string $date    Stored delivery_day_date value.
	 * @return string
	 */
	private function capture_delivery_date_html( Single $handler, string $date ): string {
		ob_start();
		try {
			$handler->generate_delivery_date_html( array( 'delivery_day_date' => $date ) );
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	/**
	 * A minimal concrete Order\Single whose constructor does not register the
	 * real admin hooks.
	 *
	 * @return Single
	 */
	private function make_single_handler(): Single {
		return new class() extends Single {
			public function __construct() {
				$this->settings  = Settings::get_instance();
				$this->meta_name = '_' . $this->prefix . 'order_metadata';
			}
			public function init_hooks() {}
		};
	}
}
