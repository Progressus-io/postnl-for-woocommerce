<?php
/**
 * Sandbox tests for Rest_API\V4\Timeframe\Service.
 *
 * @package PostNLWooCommerce\Tests\Sandbox
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Sandbox;

use Brain\Monkey\Functions;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Timeframe\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\Support\Sandbox_Test_Case;
use Psr\Log\NullLogger;

/**
 * @coversNothing
 */
class TimeframeSandboxTest extends Sandbox_Test_Case {

	/**
	 * Drop-off on weekdays only, so Monday must not be offered.
	 */
	private const WEEKDAY_DROPOFF = array( 'mon', 'tue', 'wed', 'thu', 'fri' );

	/**
	 * Stub the WordPress functions the timeframe lookup reaches.
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'get_transient' )->justReturn( false );
		Functions\when( 'set_transient' )->justReturn( true );
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		Functions\when( 'current_datetime' )->alias(
			static fn(): \DateTimeImmutable => new \DateTimeImmutable( 'now', new \DateTimeZone( 'Europe/Amsterdam' ) )
		);
	}

	/**
	 * @testdox A morning-enabled lookup returns the Guaranteed before 10 window as the morning option, sorted, without Monday
	 */
	public function test_morning_enabled_lookup(): void {
		$settings = $this->make_settings();
		$service  = new Service( new Client_Factory( $settings ), $settings, $this->api_key, 10, new NullLogger() );

		$this->throttle();
		$options = $service->get_delivery_options(
			array(
				'ship_to_different_address' => '1',
				'shipping_country'          => 'NL',
				'shipping_postcode'         => '2595 AA',
				'shipping_address_1'        => 'Prinses Beatrixlaan',
				'shipping_address_2'        => '23',
				'shipping_city'             => 'Den Haag',
			)
		)['DeliveryOptions'];

		$this->assertNotEmpty( $options, 'The sandbox returned no delivery days.' );

		$morning = array();
		foreach ( $options as $day ) {
			foreach ( $day['Timeframe'] as $window ) {
				if ( array( '08:00-12:00' ) === $window['Options'] ) {
					$morning[] = $window['From'] . '-' . $window['To'];
				}
			}
		}

		$this->assertNotEmpty( $morning, 'No Guaranteed before 10 window was mapped to the morning option.' );
		$this->assertSame( array( '08:00:00-10:00:00' ), array_values( array_unique( $morning ) ) );

		$dates  = array_map( static fn( array $day ): \DateTimeImmutable => new \DateTimeImmutable( $day['DeliveryDate'] ), $options );
		$sorted = $dates;
		sort( $sorted );
		$this->assertEquals( $sorted, $dates, 'Delivery days are not in ascending date order.' );

		foreach ( $dates as $date ) {
			$this->assertNotSame( '1', $date->format( 'N' ), 'Monday ' . $date->format( 'Y-m-d' ) . ' was offered although Sunday is not a drop-off day.' );
		}
	}

	/**
	 * Settings double with morning and evening on and a weekday-only drop-off.
	 *
	 * @return Settings
	 */
	private function make_settings(): Settings {
		return new class( $this->customer_number, $this->customer_code, self::WEEKDAY_DROPOFF ) extends Settings {

			/**
			 * Store the values the getters return.
			 *
			 * @param string   $customer_number Sandbox customer number.
			 * @param string   $customer_code   Sandbox customer code.
			 * @param string[] $dropoff         Enabled drop-off weekday keys.
			 */
			public function __construct(
				private string $customer_number,
				private string $customer_code,
				private array $dropoff
			) {}

			/**
			 * Sandbox customer number.
			 *
			 * @return string
			 */
			public function get_customer_num() {
				return $this->customer_number;
			}

			/**
			 * Sandbox customer code.
			 *
			 * @return string
			 */
			public function get_customer_code() {
				return $this->customer_code;
			}

			/**
			 * Always the sandbox.
			 *
			 * @return bool
			 */
			public function is_sandbox() {
				return true;
			}

			/**
			 * Evening delivery is on.
			 *
			 * @return bool
			 */
			public function is_evening_delivery_enabled() {
				return true;
			}

			/**
			 * Morning delivery is on.
			 *
			 * @return bool
			 */
			public function is_morning_delivery_enabled() {
				return true;
			}

			/**
			 * Cut-off time.
			 *
			 * @return string
			 */
			public function get_cut_off_time() {
				return '23:00';
			}

			/**
			 * Transit time in days.
			 *
			 * @return string
			 */
			public function get_transit_time() {
				return '1';
			}

			/**
			 * Enabled drop-off weekday keys.
			 *
			 * @return string[]
			 */
			public function get_dropoff_days() {
				return $this->dropoff;
			}
		};
	}
}
