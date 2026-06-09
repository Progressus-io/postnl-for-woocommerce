<?php
/**
 * Integration tests for letterbox shipping-rate injection.
 *
 * Guards the fixes for ClickUp 868h3dz6m (Joris Hoyle testing): the blocks
 * AJAX handler constructed a second Frontend\Container, which re-registered the
 * woocommerce_package_rates filters and made inject_letterbox_rates_for_all_methods
 * run twice in one request — duplicating the 24h / 48h options.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Product\Single;
use PostNLWooCommerce\Utils;

/**
 * Covers the double-registration root cause and the idempotency defence.
 */
class LetterboxRatesTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * Backup of the woocommerce_default_country option.
	 *
	 * @var mixed
	 */
	private $orig_country = null;

	/**
	 * ID of the letterbox product created for the cart, deleted on teardown.
	 *
	 * @var int
	 */
	private $product_id = 0;

	/**
	 * @testdox A Container built with hooks registers the package-rates filter.
	 */
	public function test_container_with_hooks_registers_rate_filter(): void {
		$container = new Container( true );

		$this->assertNotFalse(
			has_filter(
				'woocommerce_package_rates',
				array( $container, 'inject_letterbox_rates_for_all_methods' )
			),
			'The bootstrap Container must register the letterbox rate filter.'
		);

		// Do not leak this instance's filter into sibling tests.
		remove_filter(
			'woocommerce_package_rates',
			array( $container, 'inject_letterbox_rates_for_all_methods' ),
			15
		);
	}

	/**
	 * @testdox A Container built without hooks does NOT register the package-rates filter.
	 */
	public function test_container_without_hooks_does_not_register_rate_filter(): void {
		// This is the constructor the blocks AJAX handler now uses. Registering
		// the filter here is exactly the double-registration bug we fixed.
		$container = new Container( false );

		$this->assertFalse(
			has_filter(
				'woocommerce_package_rates',
				array( $container, 'inject_letterbox_rates_for_all_methods' )
			),
			'A helper-only Container must not re-register the rate filter.'
		);
	}

	/**
	 * @testdox inject_letterbox_rates_for_all_methods is idempotent — running it twice never duplicates the 24h/48h rates.
	 */
	public function test_inject_letterbox_rates_is_idempotent(): void {
		$this->make_cart_letterbox_eligible();

		// Precondition: the scenario actually reaches the injection branch.
		$this->assertTrue(
			Utils::is_cart_eligible_auto_letterbox( WC()->cart ),
			'Test setup failed to make the cart letterbox eligible.'
		);

		$container = new Container( false );
		$package   = array();

		$rates = array(
			'flat_rate:3' => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
		);

		$once = $container->inject_letterbox_rates_for_all_methods( $rates, $package );

		// First pass replaces flat_rate:3 with its 24h and 48h variants.
		$this->assertCount( 2, $once );
		$this->assertArrayHasKey( 'flat_rate:3:letterbox', $once );
		$this->assertArrayHasKey( 'flat_rate:3:letterbox_48', $once );

		// Second pass must be a no-op: the guard skips rates that already carry
		// letterbox_type meta, so no ':letterbox:letterbox' nesting is produced.
		$twice = $container->inject_letterbox_rates_for_all_methods( $once, $package );

		$this->assertCount( 2, $twice, 'Re-running the filter must not duplicate the letterbox rates.' );
		$this->assertSame( array_keys( $once ), array_keys( $twice ) );
		$this->assertArrayNotHasKey( 'flat_rate:3:letterbox:letterbox', $twice );
	}

	/**
	 * Build an NL store + an eligible single-item letterbox cart.
	 */
	private function make_cart_letterbox_eligible(): void {
		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;
		// Override the loaded option array directly so we don't have to persist
		// and reload the WC_Settings_API option store.
		$settings->settings['supported_shipping_methods']            = array( 'flat_rate' );
		$settings->settings['default_automatic_letterboxparcel_product'] = 'customer_decide';
		$settings->settings['letterbox_24_fee']                      = '';
		$settings->settings['letterbox_fee']                         = '';

		$product = new \WC_Product_Simple();
		$product->set_name( 'PostNL letterbox test product' );
		$product->set_regular_price( '10' );
		$product->set_manage_stock( false );
		$product->set_status( 'publish' );
		$product->update_meta_data( Single::LETTERBOX_PARCEL, 'yes' );
		$product->update_meta_data( Single::MAX_QTY_PER_LETTERBOX, 5 );
		$product->save();
		$this->product_id = $product->get_id();

		WC()->customer->set_shipping_country( 'NL' );
		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $this->product_id, 1 );
	}

	/**
	 * Restore global state mutated by make_cart_letterbox_eligible().
	 */
	protected function tearDown(): void {
		if ( null !== WC()->cart ) {
			WC()->cart->empty_cart();
		}

		if ( null !== $this->orig_settings ) {
			Settings::get_instance()->settings = $this->orig_settings;
			$this->orig_settings               = null;
		}

		if ( null !== $this->orig_country ) {
			update_option( 'woocommerce_default_country', $this->orig_country );
			$this->orig_country = null;
		}

		if ( $this->product_id > 0 ) {
			wp_delete_post( $this->product_id, true );
			$this->product_id = 0;
		}

		parent::tearDown();
	}
}
