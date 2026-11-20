<?php
/**
 * Integration tests for the number of delivery days sent to PostNL.
 *
 * The "Number of Delivery Days" setting is only shown to NL stores, so a BE
 * store has no saved value. Legacy then sent Days 0 and V4 clamped to one day.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Rest_API\Legacy\Checkout\Client;
use PostNLWooCommerce\Rest_API\Legacy\Checkout\Item_Info;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Rest_API\V4\Timeframe\Service as V4_Timeframe_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Checks the Days value of the legacy request and numberOfDays of the V4 request.
 */
class NumberDeliveryDaysTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backup of the store options this test overrides.
	 *
	 * @var array
	 */
	private $orig_options = array();

	/**
	 * Back up settings and store options.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_settings = Settings::get_instance()->settings;

		foreach ( array( 'woocommerce_default_country', 'woocommerce_store_address', 'woocommerce_store_city', 'woocommerce_store_postcode' ) as $option ) {
			$this->orig_options[ $option ] = get_option( $option );
		}
	}

	/**
	 * Restore settings, store options and the V4 flag.
	 */
	protected function tearDown(): void {
		remove_filter( 'postnl_sdk_enable_timeframe', '__return_true' );
		Settings::get_instance()->set_api_key_new_validated( false );
		Settings::get_instance()->settings = $this->orig_settings;

		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}

		parent::tearDown();
	}

	/**
	 * Set the store country and the saved delivery days value.
	 *
	 * @param string      $country Store country.
	 * @param string|null $days    Saved value, or null when the merchant never saved one.
	 */
	private function use_store( string $country, ?string $days ): void {
		update_option( 'woocommerce_default_country', $country );
		update_option( 'woocommerce_store_address', 'NL' === $country ? 'Hoofdstraat 1' : 'Meir 1' );
		update_option( 'woocommerce_store_city', 'NL' === $country ? 'Amsterdam' : 'Antwerpen' );
		update_option( 'woocommerce_store_postcode', 'NL' === $country ? '1011 AB' : '2000' );

		$settings                           = Settings::get_instance();
		$settings->settings['cut_off_time'] = '18:00';

		unset( $settings->settings['number_delivery_days'] );

		if ( null !== $days ) {
			$settings->settings['number_delivery_days'] = $days;
		}
	}

	/**
	 * Checkout post data for a receiver in the store's country.
	 *
	 * @param string $country Receiver country.
	 *
	 * @return array
	 */
	private function post_data( string $country ): array {
		return array(
			'ship_to_different_address' => '1',
			'shipping_country'          => $country,
			'shipping_postcode'         => 'NL' === $country ? '6591 BA' : '2060',
			'shipping_address_1'        => 'Teststraat',
			'shipping_house_number'     => '1',
			'shipping_city'             => 'Teststad',
		);
	}

	/**
	 * Return the Days value of the legacy checkout request.
	 *
	 * @param string $country Receiver country.
	 *
	 * @return int
	 */
	private function legacy_days( string $country ): int {
		$client = new Client( new Item_Info( $this->post_data( $country ) ) );

		return $client->compose_body_request()['Days'];
	}

	/**
	 * Return the day count the V4 timeframe service sends as numberOfDays.
	 *
	 * Main's V4 build_request() predates the SDK 3 request types, so the value
	 * is read where Service_Factory hands it to the service; build_request()
	 * passes it to numberOfDays unchanged.
	 *
	 * @return int
	 */
	private function v4_days(): int {
		if ( ! interface_exists( '\Psr\Log\LoggerInterface' ) || ! class_exists( '\Postnl\Sdk\Client\ClientBuilder' ) ) {
			$this->markTestSkipped( 'The PostNL SDK is not installed on this branch.' );
		}

		$settings                                       = Settings::get_instance();
		$settings->settings['environment_mode']         = 'sandbox';
		$settings->settings['api_keys_sandbox_new']     = 'test-v4-key';
		$settings->set_api_key_new_validated( true, 'test-v4-key' );
		add_filter( 'postnl_sdk_enable_timeframe', '__return_true' );

		$service = ( new Service_Factory( $settings ) )->timeframe_service();
		$this->assertInstanceOf( V4_Timeframe_Service::class, $service );

		$days = new \ReflectionProperty( $service, 'number_of_days' );
		$days->setAccessible( true );

		return $days->getValue( $service );
	}

	/**
	 * @testdox A BE store without a saved value sends Days 10 on the legacy checkout request.
	 */
	public function test_be_store_legacy_request_uses_default_days(): void {
		$this->use_store( 'BE', null );

		$this->assertSame( 10, $this->legacy_days( 'BE' ) );
	}

	/**
	 * @testdox A BE store without a saved value sends numberOfDays 10 on the V4 timeframe request.
	 */
	public function test_be_store_v4_request_uses_default_days(): void {
		$this->use_store( 'BE', null );

		$this->assertSame( 10, $this->v4_days() );
	}

	/**
	 * @testdox An NL store still sends its saved number of delivery days.
	 */
	public function test_nl_store_keeps_saved_days(): void {
		$this->use_store( 'NL', '5' );

		$this->assertSame( 5, $this->legacy_days( 'NL' ) );
		$this->assertSame( 5, $this->v4_days() );
	}

	/**
	 * @testdox An NL store without a saved value still sends the default of 10.
	 */
	public function test_nl_store_without_saved_value_uses_default_days(): void {
		$this->use_store( 'NL', null );

		$this->assertSame( 10, $this->legacy_days( 'NL' ) );
	}
}
