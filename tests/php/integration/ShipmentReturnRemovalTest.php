<?php
/**
 * Integration tests for the discontinued "Shipment & Return" removal.
 *
 * Covers both safeguards introduced when PostNL discontinued the product on
 * 1 July 2026: the one-time revert migration and the read-time coercion that
 * neutralises any value the migration has not reached.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Main;
use PostNLWooCommerce\Shipping_Method\Settings;

/**
 * Exercises Main::maybe_revert_discontinued_return_option() and the coercion in
 * Settings::get_return_shipment_and_labels().
 */
class ShipmentReturnRemovalTest extends IntegrationTestCase {

	/**
	 * Sentinel marking an option that did not exist before the test ran.
	 */
	private const ABSENT = '__postnl_absent__';

	/**
	 * Option names this suite reads and writes.
	 *
	 * @var string[]
	 */
	private const TOUCHED_OPTIONS = array(
		'woocommerce_postnl_settings',
		'postnl_shipping_return_reverted',
		'woocommerce_default_country',
	);

	/**
	 * Snapshot of the touched options, keyed by option name.
	 *
	 * @var array
	 */
	private $option_snapshot = array();

	/**
	 * Capture original option values and force a Netherlands base country so the
	 * return-option field is active for the coercion assertions.
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( self::TOUCHED_OPTIONS as $option ) {
			$this->option_snapshot[ $option ] = get_option( $option, self::ABSENT );
		}

		update_option( 'woocommerce_default_country', 'NL' );
	}

	/**
	 * Restore every option this suite touched.
	 */
	protected function tearDown(): void {
		foreach ( $this->option_snapshot as $option => $value ) {
			if ( self::ABSENT === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		parent::tearDown();
	}

	/**
	 * Run the one-time migration against the current option state.
	 */
	private function run_migration(): void {
		Main::instance()->maybe_revert_discontinued_return_option();
	}

	/**
	 * Read the standard return option through the coercing getter.
	 *
	 * A fresh Settings instance is built each call because WC_Settings_API caches
	 * its stored values at construction time.
	 *
	 * @return string
	 */
	private function read_return_option(): string {
		$settings = new Settings();

		return $settings->get_return_shipment_and_labels();
	}

	/**
	 * @testdox Migration reverts a stored Shipment & Return selection to None and preserves other settings.
	 */
	public function test_migration_reverts_shipping_return_to_none(): void {
		update_option(
			'woocommerce_postnl_settings',
			array(
				'return_shipment_and_labels' => 'shipping_return',
				'api_keys'                   => 'keep-me',
			)
		);
		delete_option( 'postnl_shipping_return_reverted' );

		$this->run_migration();

		$settings = get_option( 'woocommerce_postnl_settings' );

		$this->assertSame( 'none', $settings['return_shipment_and_labels'] );
		$this->assertSame( 'keep-me', $settings['api_keys'] );
		$this->assertSame( 'done', get_option( 'postnl_shipping_return_reverted' ) );
	}

	/**
	 * @testdox Migration leaves a Label in the box selection untouched.
	 */
	public function test_migration_leaves_in_box_untouched(): void {
		update_option( 'woocommerce_postnl_settings', array( 'return_shipment_and_labels' => 'in_box' ) );
		delete_option( 'postnl_shipping_return_reverted' );

		$this->run_migration();

		$settings = get_option( 'woocommerce_postnl_settings' );

		$this->assertSame( 'in_box', $settings['return_shipment_and_labels'] );
		$this->assertSame( 'done', get_option( 'postnl_shipping_return_reverted' ) );
	}

	/**
	 * @testdox Migration does not run again once the completion flag is set.
	 */
	public function test_migration_short_circuits_when_already_done(): void {
		update_option( 'woocommerce_postnl_settings', array( 'return_shipment_and_labels' => 'shipping_return' ) );
		update_option( 'postnl_shipping_return_reverted', 'done' );

		$this->run_migration();

		$settings = get_option( 'woocommerce_postnl_settings' );

		$this->assertSame( 'shipping_return', $settings['return_shipment_and_labels'] );
	}

	/**
	 * @testdox Migration marks itself complete without writing settings when no revert is required.
	 */
	public function test_migration_sets_flag_when_no_revert_needed(): void {
		delete_option( 'woocommerce_postnl_settings' );
		delete_option( 'postnl_shipping_return_reverted' );

		$this->run_migration();

		$this->assertSame( self::ABSENT, get_option( 'woocommerce_postnl_settings', self::ABSENT ) );
		$this->assertSame( 'done', get_option( 'postnl_shipping_return_reverted' ) );
	}

	/**
	 * @testdox Getter coerces a lingering Shipment & Return value to None.
	 */
	public function test_getter_coerces_shipping_return_to_none(): void {
		update_option( 'woocommerce_postnl_settings', array( 'return_shipment_and_labels' => 'shipping_return' ) );

		$this->assertSame( 'none', $this->read_return_option() );
	}

	/**
	 * @testdox Getter passes through a Label in the box value unchanged.
	 */
	public function test_getter_passes_through_in_box(): void {
		update_option( 'woocommerce_postnl_settings', array( 'return_shipment_and_labels' => 'in_box' ) );

		$this->assertSame( 'in_box', $this->read_return_option() );
	}
}
