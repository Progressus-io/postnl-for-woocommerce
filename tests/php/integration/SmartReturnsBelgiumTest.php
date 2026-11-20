<?php
/**
 * Integration tests for Smart Returns from and to Belgium on V4.
 *
 * The Smart Return button is offered for BE shipping addresses once Smart
 * Returns run on V4, and a Belgian store's settings hide the reply number
 * choice and point to Smart Return.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises the order screen button, the factory predicate and the BE settings form.
 */
class SmartReturnsBelgiumTest extends IntegrationTestCase {

	/**
	 * V4 key the tests validate.
	 */
	private const V4_KEY = 'test-v4-key';

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
	 * Orders created during the test.
	 *
	 * @var int[]
	 */
	private $order_ids = array();

	/**
	 * Enable Smart Return with a validated sandbox V4 key, flows off.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['environment_mode']      = 'sandbox';
		$settings->settings['api_keys_sandbox_new']  = self::V4_KEY;
		$settings->settings['activate_smart_return'] = 'yes';
		$settings->set_api_key_new_validated( true, self::V4_KEY );
	}

	/**
	 * Restore settings, store country, flags and orders.
	 */
	protected function tearDown(): void {
		remove_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );
		remove_filter( 'postnl_sdk_enable_label', '__return_true' );

		foreach ( $this->order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$order->delete( true );
			}
		}

		Settings::get_instance()->set_api_key_new_validated( false );
		Settings::get_instance()->settings = $this->orig_settings;
		update_option( 'woocommerce_default_country', $this->orig_country );

		parent::tearDown();
	}

	/**
	 * Render the Smart Return button area for an order shipped to the given country.
	 *
	 * @param string $country Shipping country.
	 *
	 * @return string
	 */
	private function button_html( string $country ): string {
		$order = new \WC_Order();
		$order->set_shipping_country( $country );
		$order->save();
		$this->order_ids[] = $order->get_id();

		$single = new class() extends Single {
			/**
			 * Skip hook registration.
			 */
			public function init_hooks() {}
		};

		ob_start();
		$single->send_smart_return_email_html( $order );

		return (string) ob_get_clean();
	}

	/**
	 * Build the PostNL settings form for a store in the given country.
	 *
	 * @param string $country Store country.
	 *
	 * @return array
	 */
	private function form_fields( string $country ): array {
		update_option( 'woocommerce_default_country', $country );

		$method = new PostNL();
		$method->init_form_fields();

		return $method->form_fields;
	}

	/**
	 * @testdox The Smart Return button shows for a BE address only when Smart Returns run on V4.
	 */
	public function test_button_for_be_address_follows_the_v4_flow(): void {
		$this->assertStringNotContainsString( 'button-send-smart-return', $this->button_html( 'BE' ) );

		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'BE' ) );
	}

	/**
	 * @testdox The Smart Return button keeps showing for NL addresses and never for other countries.
	 */
	public function test_button_for_nl_and_other_addresses(): void {
		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'NL' ) );

		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'NL' ) );
		$this->assertStringNotContainsString( 'button-send-smart-return', $this->button_html( 'DE' ) );
	}

	/**
	 * @testdox uses_v4_for() reports the per-flow V4 routing.
	 */
	public function test_uses_v4_for_reports_the_flow(): void {
		$factory = new Service_Factory( Settings::get_instance() );

		$this->assertFalse( $factory->uses_v4_for( 'smart_returns' ) );

		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertTrue( $factory->uses_v4_for( 'smart_returns' ) );
		$this->assertFalse( $factory->uses_v4_for( 'label' ) );
	}

	/**
	 * @testdox A BE store's settings hide "Return to home address" and the freepost fields; an NL store keeps them.
	 */
	public function test_be_store_hides_reply_number_choice(): void {
		$be = $this->form_fields( 'BE' );

		$this->assertArrayNotHasKey( 'return_address_or_reply_no', $be );
		$this->assertArrayNotHasKey( 'freepost_zip', $be );
		$this->assertArrayNotHasKey( 'freepost_city', $be );
		$this->assertArrayHasKey( 'return_address_street', $be );

		$nl = $this->form_fields( 'NL' );

		$this->assertArrayHasKey( 'return_address_or_reply_no', $nl );
		$this->assertArrayHasKey( 'freepost_zip', $nl );
		$this->assertArrayHasKey( 'return_replynumber', $nl );
	}

	/**
	 * @testdox A BE store's settings note says label in the box is gone on V4 and points to Smart Return.
	 */
	public function test_be_store_note_points_to_smart_return(): void {
		$this->assertArrayNotHasKey( 'description', $this->form_fields( 'BE' )['return_settings_title'] );

		add_filter( 'postnl_sdk_enable_label', '__return_true' );
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertSame(
			'Label in the box is not available with the new PostNL APIs. You can offer returns with Smart Return. Returns are sent to your return address below.',
			$this->form_fields( 'BE' )['return_settings_title']['description']
		);
	}
}
