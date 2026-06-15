<?php
/**
 * Integration tests for letterbox shipping-rate injection.
 *
 * Guards against a regression where the blocks AJAX handler constructed a
 * second Frontend\Container, re-registering the woocommerce_package_rates
 * filters so inject_letterbox_rates_for_all_methods ran twice in one request
 * and duplicated the 24h / 48h options.
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
	 * @testdox customer_decide collapses every linked method into ONE 24h/48h pair, keeps Free Shipping + non-linked carriers, drops linked rates.
	 */
	public function test_customer_decide_collapses_linked_methods_to_single_pair(): void {
		$this->make_cart_letterbox_eligible();

		$container = new Container( false );

		// Example zone: two linked Flat Rate instances, the PostNL method, a Free Shipping
		// method, and a non-linked carrier (DHL).
		$rates = array(
			'flat_rate:3'    => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
			'flat_rate:4'    => new \WC_Shipping_Rate( 'flat_rate:4', 'Flat rate', 8.0, array(), 'flat_rate', 4 ),
			'postnl:5'       => new \WC_Shipping_Rate( 'postnl:5', 'PostNL', 12.0, array(), 'postnl', 5 ),
			'free_shipping:6' => new \WC_Shipping_Rate( 'free_shipping:6', 'Free shipping', 0.0, array(), 'free_shipping', 6 ),
			'dhl:7'          => new \WC_Shipping_Rate( 'dhl:7', 'DHL', 9.0, array(), 'dhl', 7 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		// Exactly one 24h + one 48h canonical option, derived from the PostNL rate.
		$this->assertArrayHasKey( 'postnl:5:letterbox', $out );
		$this->assertArrayHasKey( 'postnl:5:letterbox_48', $out );

		$letterbox_keys = array_filter(
			array_keys( $out ),
			static fn( $k ) => false !== strpos( $k, ':letterbox' )
		);
		$this->assertCount( 2, $letterbox_keys, 'Exactly one 24h and one 48h option must exist — no per-method duplicates.' );

		// Every individual PostNL-linked rate is gone.
		$this->assertArrayNotHasKey( 'flat_rate:3', $out );
		$this->assertArrayNotHasKey( 'flat_rate:4', $out );
		$this->assertArrayNotHasKey( 'postnl:5', $out );

		// Non-linked carrier survives untouched.
		$this->assertArrayHasKey( 'dhl:7', $out );
		$this->assertSame( 9.0, (float) $out['dhl:7']->get_cost() );

		// Free Shipping survives and is never collapsed.
		$this->assertArrayHasKey( 'free_shipping:6', $out );

		// Canonical rates carry the correct letterbox_type meta.
		$this->assertSame( 'letterbox', $out['postnl:5:letterbox']->get_meta_data()['letterbox_type'] );
		$this->assertSame( 'letterbox_48', $out['postnl:5:letterbox_48']->get_meta_data()['letterbox_type'] );
	}

	/**
	 * @testdox A non-linked Free Shipping method must NOT zero the canonical letterbox options.
	 */
	public function test_non_linked_free_shipping_does_not_zero_letterbox(): void {
		$this->make_cart_letterbox_eligible();
		// Only flat_rate is linked to PostNL; free_shipping is a standalone option.
		Settings::get_instance()->settings['supported_shipping_methods'] = array( 'flat_rate' );

		$container = new Container( false );

		$rates = array(
			'flat_rate:3'     => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
			'postnl:5'        => new \WC_Shipping_Rate( 'postnl:5', 'PostNL', 12.0, array(), 'postnl', 5 ),
			'free_shipping:6' => new \WC_Shipping_Rate( 'free_shipping:6', 'Free shipping', 0.0, array(), 'free_shipping', 6 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		// The standalone Free Shipping option survives untouched.
		$this->assertArrayHasKey( 'free_shipping:6', $out );
		$this->assertSame( 0.0, (float) $out['free_shipping:6']->get_cost() );

		// The canonical letterbox options keep the linked carrier price (cheapest
		// linked cost = 10.0) instead of being forced to 0 by the unrelated Free
		// Shipping method.
		$this->assertSame(
			10.0,
			(float) $out['postnl:5:letterbox_48']->get_cost(),
			'A non-linked Free Shipping method must not zero the 48h letterbox option.'
		);
		$this->assertSame(
			10.0,
			(float) $out['postnl:5:letterbox']->get_cost(),
			'A non-linked Free Shipping method must not zero the 24h letterbox option.'
		);
	}

	/**
	 * @testdox A PostNL-linked Free Shipping method DOES waive the canonical letterbox cost.
	 */
	public function test_linked_free_shipping_zeroes_letterbox(): void {
		$this->make_cart_letterbox_eligible();
		// Free Shipping is explicitly linked to PostNL — it should waive the letterbox cost.
		Settings::get_instance()->settings['supported_shipping_methods'] = array( 'flat_rate', 'free_shipping' );

		$container = new Container( false );

		$rates = array(
			'flat_rate:3'     => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
			'postnl:5'        => new \WC_Shipping_Rate( 'postnl:5', 'PostNL', 12.0, array(), 'postnl', 5 ),
			'free_shipping:6' => new \WC_Shipping_Rate( 'free_shipping:6', 'Free shipping', 0.0, array(), 'free_shipping', 6 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		// Free Shipping still survives as its own option.
		$this->assertArrayHasKey( 'free_shipping:6', $out );

		// Because Free Shipping is linked, both canonical options are waived to 0.
		$this->assertSame(
			0.0,
			(float) $out['postnl:5:letterbox_48']->get_cost(),
			'A linked Free Shipping method must waive the 48h letterbox option.'
		);
		$this->assertSame(
			0.0,
			(float) $out['postnl:5:letterbox']->get_cost(),
			'A linked Free Shipping method must waive the 24h letterbox option.'
		);
	}

	/**
	 * @testdox A forced letterbox setting yields exactly one canonical option.
	 *
	 * @dataProvider forced_letterbox_settings
	 *
	 * @param string $setting     Forced product setting (letterbox|letterbox_48).
	 * @param string $suffix      Expected canonical rate-id suffix.
	 * @param string $expect_type Expected letterbox_type meta value.
	 */
	public function test_forced_setting_yields_single_option( string $setting, string $suffix, string $expect_type ): void {
		$this->make_cart_letterbox_eligible();
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = $setting;

		$container = new Container( false );

		$rates = array(
			'flat_rate:3'    => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
			'postnl:5'       => new \WC_Shipping_Rate( 'postnl:5', 'PostNL', 12.0, array(), 'postnl', 5 ),
			'free_shipping:6' => new \WC_Shipping_Rate( 'free_shipping:6', 'Free shipping', 0.0, array(), 'free_shipping', 6 ),
			'dhl:7'          => new \WC_Shipping_Rate( 'dhl:7', 'DHL', 9.0, array(), 'dhl', 7 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		$letterbox_keys = array_values(
			array_filter(
				array_keys( $out ),
				static fn( $k ) => false !== strpos( $k, ':letterbox' )
			)
		);

		$this->assertCount( 1, $letterbox_keys, 'A forced setting must yield exactly one canonical option.' );
		$this->assertSame( 'postnl:5' . $suffix, $letterbox_keys[0] );
		$this->assertSame( $expect_type, $out[ $letterbox_keys[0] ]->get_meta_data()['letterbox_type'] );

		// Linked methods gone; Free Shipping and non-linked carrier survive.
		$this->assertArrayNotHasKey( 'flat_rate:3', $out );
		$this->assertArrayNotHasKey( 'postnl:5', $out );
		$this->assertArrayHasKey( 'free_shipping:6', $out );
		$this->assertArrayHasKey( 'dhl:7', $out );
	}

	/**
	 * Forced-setting scenarios.
	 *
	 * @return array<string, array{0:string,1:string,2:string}>
	 */
	public static function forced_letterbox_settings(): array {
		return array(
			'24h forced' => array( 'letterbox', ':letterbox', 'letterbox' ),
			'48h forced' => array( 'letterbox_48', ':letterbox_48', 'letterbox_48' ),
		);
	}

	/**
	 * @testdox With no PostNL method instance in the zone, the canonical pair is still produced from the linked carrier.
	 */
	public function test_collapse_without_postnl_method_uses_linked_carrier(): void {
		$this->make_cart_letterbox_eligible();

		$container = new Container( false );

		// Only a linked Flat Rate and a non-linked carrier — no PostNL method present.
		$rates = array(
			'flat_rate:3' => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
			'dhl:7'       => new \WC_Shipping_Rate( 'dhl:7', 'DHL', 9.0, array(), 'dhl', 7 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		// Canonical pair synthesized from the linked Flat Rate; no zero-option outcome.
		$this->assertArrayHasKey( 'flat_rate:3:letterbox', $out );
		$this->assertArrayHasKey( 'flat_rate:3:letterbox_48', $out );
		$this->assertArrayNotHasKey( 'flat_rate:3', $out );

		// No free shipping here, so the base cost falls back to the linked carrier cost.
		$this->assertSame( 10.0, (float) $out['flat_rate:3:letterbox_48']->get_cost() );

		// Non-linked carrier survives.
		$this->assertArrayHasKey( 'dhl:7', $out );
	}

	/**
	 * @testdox The 24h surcharge is added to the 24h variant only; the 48h variant stays at base cost.
	 */
	public function test_customer_decide_applies_24h_surcharge_to_24h_variant_only(): void {
		$this->make_cart_letterbox_eligible();

		// A non-zero 24h fee with no base letterbox_fee: the base cost falls back
		// to the cheapest linked carrier cost (10.0), and only the 24h variant
		// carries the surcharge on top.
		Settings::get_instance()->settings['letterbox_24_fee'] = '1.50';
		Settings::get_instance()->settings['letterbox_fee']    = '';

		$container = new Container( false );

		$rates = array(
			'flat_rate:3' => new \WC_Shipping_Rate( 'flat_rate:3', 'Flat rate', 10.0, array(), 'flat_rate', 3 ),
		);

		$out = $container->inject_letterbox_rates_for_all_methods( $rates, array() );

		$this->assertSame(
			11.5,
			(float) $out['flat_rate:3:letterbox']->get_cost(),
			'24h variant must be base cost plus the configured 24h surcharge.'
		);
		$this->assertSame(
			10.0,
			(float) $out['flat_rate:3:letterbox_48']->get_cost(),
			'48h variant must remain at base cost with no surcharge.'
		);
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
