<?php
/**
 * Unit tests for Rest_API\V4\Timeframe\Service.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Timeframe
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Timeframe;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\DeliveryWindowService;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\ResponseData\V4\TimeFrame;
use Postnl\Sdk\ResponseData\V4\TimeSlot;
use Postnl\Sdk\Service\Timeframes\V4\Response\TimeframeMultipleServicesCollection;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Timeframe\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Timeframe\Service
 */
class ServiceTest extends UnitTestCase {

	/**
	 * API key the Service is constructed with in these tests.
	 */
	private const V4_KEY = 'v4-secret';

	/**
	 * Look-ahead days the Service is constructed with unless a test varies it.
	 */
	private const DAYS = 10;

	/**
	 * Every weekday enabled for drop-off.
	 */
	private const ALL_DROPOFF_DAYS = array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' );

	/**
	 * In-memory stand-in for the WP transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = array();

	/**
	 * Build a settings double overriding exactly the getters the Service reads.
	 *
	 * It extends the real Settings so it satisfies the Service's typed parameter;
	 * every parent method is left unstubbed on purpose, so reaching one fatals
	 * rather than quietly returning stub data.
	 *
	 * @param bool     $evening Whether evening delivery is enabled.
	 * @param bool     $morning Whether morning delivery is enabled.
	 * @param string   $cut_off Cut-off time (HH:MM).
	 * @param string   $transit Transit time in days, as the setting stores it.
	 * @param string[] $dropoff Enabled drop-off weekday keys.
	 * @return Settings
	 */
	private function make_settings(
		bool $evening = true,
		bool $morning = false,
		string $cut_off = '23:00',
		string $transit = '1',
		array $dropoff = self::ALL_DROPOFF_DAYS
	): Settings {
		return new class( $evening, $morning, $cut_off, $transit, $dropoff ) extends Settings {
			public function __construct(
				private bool $evening,
				private bool $morning,
				private string $cut_off,
				private string $transit,
				private array $dropoff,
			) {}

			public function get_customer_code() {
				return 'DEVC';
			}

			public function get_customer_num() {
				return '11223344';
			}

			public function is_sandbox() {
				return true;
			}

			public function is_evening_delivery_enabled() {
				return $this->evening;
			}

			public function is_morning_delivery_enabled() {
				return $this->morning;
			}

			public function get_cut_off_time() {
				return $this->cut_off;
			}

			public function get_transit_time() {
				return $this->transit;
			}

			public function get_dropoff_days() {
				return $this->dropoff;
			}
		};
	}

	/**
	 * A NL checkout POST payload.
	 *
	 * @return array<string, string>
	 */
	private function nl_post_data(): array {
		return array(
			'ship_to_different_address' => '1',
			'shipping_country'          => 'NL',
			'shipping_postcode'         => '1234 AB',
			'shipping_address_1'        => 'Main Street',
			'shipping_address_2'        => '10',
			'shipping_city'             => 'Amsterdam',
		);
	}

	// ── Request building ─────────────────────────────────────────────────────

	/**
	 * @testdox build_request() maps the checkout address and settings onto the SDK request
	 */
	public function test_build_request_maps_address_and_settings(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings() ), $this->make_settings(), self::V4_KEY, self::DAYS );
		$request = $service->expose_build_request( $this->nl_post_data() );

		$this->assertSame( '2026-07-14', $request->handoverDate );
		$this->assertSame( ShipmentType::Parcel, $request->shipmentType );
		$this->assertSame( '11223344', $request->customerNumber );
		$this->assertSame( 'DEVC', $request->customerCode );

		$this->assertSame( Country::NL, $request->receiverAddress->countryIso );
		$this->assertSame( '1234AB', $request->receiverAddress->postalCode, 'Postcode spaces are stripped.' );
		$this->assertSame( '10', $request->receiverAddress->houseNumber );
		$this->assertSame( 'Main Street', $request->receiverAddress->street );
		$this->assertSame( 'Amsterdam', $request->receiverAddress->city );
	}

	/**
	 * @testdox The billing address is used when the order does not ship to a different address
	 */
	public function test_build_request_falls_back_to_billing_address(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings() ), $this->make_settings(), self::V4_KEY, self::DAYS );

		$address = $service->expose_build_request(
			array(
				'billing_country'   => 'NL',
				'billing_postcode'  => '2500 CD',
				'billing_address_1' => 'Church Road',
				'billing_address_2' => '42',
				'billing_city'      => 'Den Haag',
			)
		)->receiverAddress;

		$this->assertSame( Country::NL, $address->countryIso );
		$this->assertSame( '2500CD', $address->postalCode );
		$this->assertSame( '42', $address->houseNumber );
		$this->assertSame( 'Church Road', $address->street );
		$this->assertSame( 'Den Haag', $address->city );
	}

	/**
	 * @testdox Evening is requested only when the setting is enabled
	 */
	public function test_build_request_services_follow_evening_setting(): void {
		$with_evening = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true ) ), $this->make_settings( true ), self::V4_KEY, self::DAYS );
		$this->assertSame(
			array( DeliveryWindowService::Daytime, DeliveryWindowService::Evening ),
			$with_evening->expose_build_request( $this->nl_post_data() )->services
		);

		$no_evening = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false ) ), $this->make_settings( false ), self::V4_KEY, self::DAYS );
		$this->assertSame(
			array( DeliveryWindowService::Daytime ),
			$no_evening->expose_build_request( $this->nl_post_data() )->services
		);
	}

	/**
	 * @testdox The configured numberOfDays is passed through and clamped to the V4 range [1, 14]
	 */
	public function test_number_of_days_is_clamped(): void {
		$settings = $this->make_settings();
		$factory  = new Client_Factory( $settings );
		$post     = $this->nl_post_data();

		// 5 is neither the clamp floor nor the ceiling, so it only survives if the
		// merchant's configured value is actually carried through to the request.
		$configured = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 5 );
		$this->assertSame( 5, $configured->expose_build_request( $post )->numberOfDays, 'The configured value is used as-is.' );

		$over = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 20 );
		$this->assertSame( 14, $over->expose_build_request( $post )->numberOfDays, 'Capped at the V4 maximum of 14.' );

		$under = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 0 );
		$this->assertSame( 1, $under->expose_build_request( $post )->numberOfDays, 'Floored at 1.' );
	}

	// ── Response mapping ─────────────────────────────────────────────────────

	/**
	 * @testdox map_response() groups available timeframes by date into the legacy shape
	 */
	public function test_map_response_produces_legacy_shape(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true, true ) ), $this->make_settings( true, true ), self::V4_KEY, self::DAYS );

		$collection = new TimeframeMultipleServicesCollection(
			array(
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '18:00:00', until: '22:00:00' ), availability: true, service: 'evening' ),
				new TimeFrame( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: false, service: 'daytime' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'DeliveryDate' => '14-07-2026',
					'Timeframe'    => array(
						array(
							'From'    => '08:00:00',
							'To'      => '12:00:00',
							'Options' => array( '08:00-12:00' ),
						),
						array(
							'From'    => '18:00:00',
							'To'      => '22:00:00',
							'Options' => array( 'Evening' ),
						),
					),
				),
			),
			$service->expose_map_response( $collection )
		);
	}

	/**
	 * @testdox An evening window is dropped, not relabelled, when evening delivery is disabled
	 *
	 * PostNL returns late windows under the 'daytime' service and TimeSlot::isEvening()
	 * only checks from >= 17:00, so an 18:00-22:00 daytime window would otherwise be
	 * labelled Evening — an extra fee tab a merchant with evening delivery off never offers.
	 */
	public function test_evening_window_is_dropped_when_disabled(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false, false ) ), $this->make_settings( false, false ), self::V4_KEY, self::DAYS );

		$collection = new TimeframeMultipleServicesCollection(
			array(
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '18:00:00', until: '22:00:00' ), availability: true, service: 'daytime' ),
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'DeliveryDate' => '14-07-2026',
					'Timeframe'    => array(
						array(
							'From'    => '09:00:00',
							'To'      => '18:00:00',
							'Options' => array( 'Daytime' ),
						),
					),
				),
			),
			$service->expose_map_response( $collection ),
			'Only the plain daytime window survives; a plain window stays Daytime whatever the toggles say.'
		);
	}

	/**
	 * @testdox A morning window is dropped, not relabelled, when morning delivery is disabled
	 *
	 * Relabelling it to Daytime would render two identical Daytime radios and, being
	 * fee-free and first in the list, make the morning window the preselected default
	 * that lands on the label (Frontend\Container::get_default_value()).
	 */
	public function test_morning_window_is_dropped_when_disabled(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true, false ) ), $this->make_settings( true, false ), self::V4_KEY, self::DAYS );

		$collection = new TimeframeMultipleServicesCollection(
			array(
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'DeliveryDate' => '14-07-2026',
					'Timeframe'    => array(
						array(
							'From'    => '09:00:00',
							'To'      => '18:00:00',
							'Options' => array( 'Daytime' ),
						),
					),
				),
			),
			$service->expose_map_response( $collection )
		);
	}

	/**
	 * @testdox A date left with no windows after dropping disabled ones disappears entirely
	 *
	 * An entry with an empty Timeframe array would render a radio group with no
	 * options under a delivery date heading.
	 */
	public function test_date_with_only_disabled_windows_is_omitted(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false, false ) ), $this->make_settings( false, false ), self::V4_KEY, self::DAYS );

		$collection = new TimeframeMultipleServicesCollection(
			array(
				new TimeFrame( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new TimeFrame( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
			)
		);

		$this->assertSame(
			array(
				array(
					'DeliveryDate' => '15-07-2026',
					'Timeframe'    => array(
						array(
							'From'    => '09:00:00',
							'To'      => '18:00:00',
							'Options' => array( 'Daytime' ),
						),
					),
				),
			),
			$service->expose_map_response( $collection ),
			'14-07-2026 has no offerable window left, so it must not appear at all.'
		);
	}

	// ── Handover date ────────────────────────────────────────────────────────

	/**
	 * Build a settings double varying only the shipping-day settings the
	 * handover-date walk reads (cut-off time, transit time, drop-off days).
	 *
	 * @param string   $cut_off Cut-off time (HH:MM).
	 * @param string   $transit Transit time in days, as the setting stores it.
	 * @param string[] $dropoff Enabled drop-off weekday keys.
	 * @return Settings
	 */
	private function make_shipping_settings( string $cut_off, string $transit, array $dropoff ): Settings {
		return $this->make_settings( cut_off: $cut_off, transit: $transit, dropoff: $dropoff );
	}

	/**
	 * Build a handover-exposing service pinned to the given "now".
	 *
	 * @param string   $now      Site-timezone datetime, e.g. '2026-07-14 10:00:00'.
	 * @param Settings $settings Settings double.
	 * @return Handover_Timeframe_Service
	 */
	private function make_handover_service( string $now, Settings $settings ): Handover_Timeframe_Service {
		$service = new Handover_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS );
		$service->set_now( new \DateTimeImmutable( $now ) );

		return $service;
	}

	/**
	 * @testdox An order before the cut-off time hands over the same day
	 */
	public function test_handover_before_cutoff_is_same_day(): void {
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 10:00:00', $settings );

		$this->assertSame( '2026-07-14', $service->expose_handover_date() );
	}

	/**
	 * @testdox An order after the cut-off time hands over the next day
	 */
	public function test_handover_after_cutoff_shifts_a_day(): void {
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 17:00:00', $settings );

		$this->assertSame( '2026-07-15', $service->expose_handover_date() );
	}

	/**
	 * @testdox Each transit day beyond the first adds a preparation day
	 */
	public function test_handover_adds_transit_days(): void {
		$settings = $this->make_shipping_settings( '16:00', '3', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 10:00:00', $settings );

		$this->assertSame( '2026-07-16', $service->expose_handover_date() );
	}

	/**
	 * @testdox The handover lands on the next enabled drop-off day
	 */
	public function test_handover_skips_disabled_dropoff_days(): void {
		// Friday 2026-07-17 after the 16:00 cut-off; weekends are not drop-off days.
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri' ) );
		$service  = $this->make_handover_service( '2026-07-17 17:00:00', $settings );

		$this->assertSame( '2026-07-20', $service->expose_handover_date() );
	}

	/**
	 * @testdox A malformed cut-off setting falls back to the 23:00 default instead of failing
	 */
	public function test_handover_tolerates_malformed_cutoff(): void {
		$settings = $this->make_shipping_settings( 'not-a-time', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 22:00:00', $settings );

		$this->assertSame( '2026-07-14', $service->expose_handover_date() );
	}

	/**
	 * @testdox An order placed during the exact cut-off minute still hands over the same day
	 */
	public function test_handover_at_exact_cutoff_minute_is_same_day(): void {
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 16:00:59', $settings );

		$this->assertSame( '2026-07-14', $service->expose_handover_date() );
	}

	/**
	 * @testdox A 24:00 cut-off behaves as end-of-day: no order ever shifts to the next day
	 */
	public function test_handover_24_00_cutoff_never_shifts(): void {
		$settings = $this->make_shipping_settings( '24:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 23:59:00', $settings );

		$this->assertSame( '2026-07-14', $service->expose_handover_date() );
	}

	/**
	 * @testdox All drop-off days disabled short-circuits to an empty DeliveryOptions result
	 */
	public function test_all_dropoff_days_disabled_returns_empty_options(): void {
		$settings = $this->make_shipping_settings( '16:00', '1', array() );
		$service  = new Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS );

		$this->assertSame(
			array( 'DeliveryOptions' => array() ),
			$service->get_delivery_options( $this->nl_post_data() ),
			'No SDK request must be made when the merchant never hands over parcels.'
		);
	}

	// ── Caching ──────────────────────────────────────────────────────────────

	/**
	 * @testdox An identical second lookup is served from cache without a second HTTP call
	 *
	 * Drives the real Service through the SDK CachingPlugin + Cache_Adapter with an
	 * in-memory transient store and a call-counting HTTP client, proving the
	 * /timeframe/ response is cached across identical requests within a request cycle.
	 */
	public function test_second_identical_call_hits_cache(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$http    = new Counting_Http_Client( $this->timeframe_response_body() );
		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), $http );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS );
		$post    = $this->nl_post_data();

		$first  = $service->get_delivery_options( $post );
		$second = $service->get_delivery_options( $post );

		$this->assertSame( 1, $http->count, 'Second identical lookup must be served from cache.' );
		$this->assertSame( $first, $second );
		$this->assertSame( '14-07-2026', $first['DeliveryOptions'][0]['DeliveryDate'] );
		$this->assertSame( array( 'Daytime' ), $first['DeliveryOptions'][0]['Timeframe'][0]['Options'] );
	}

	// ── Authentication ───────────────────────────────────────────────────────

	/**
	 * @testdox The outgoing request carries the configured V4 API key
	 *
	 * The key is not discoverable from the settings object — production Settings has
	 * no V4-key getter — so it must be handed to the Service explicitly. Asserting on
	 * the header the SDK actually puts on the wire is the only check that fails when
	 * the key silently resolves to an empty string.
	 */
	public function test_request_carries_the_configured_api_key(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$http    = new Counting_Http_Client( $this->timeframe_response_body() );
		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), $http );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS );

		$service->get_delivery_options( $this->nl_post_data() );

		$this->assertNotNull( $http->last_request, 'The SDK must have sent a request.' );
		$this->assertSame(
			'v4-secret',
			$http->last_request->getHeaderLine( 'apiKey' ),
			'The SDK authenticates with the apiKey header; any other value means the client is not using the configured key.'
		);
	}

	/**
	 * Canned V4 timeframe response body with a single available daytime window.
	 *
	 * @return string
	 */
	private function timeframe_response_body(): string {
		return wp_json_encode(
			array(
				'deliveryDates' => array(
					array(
						'deliveryDate' => '14-07-2026',
						'services'     => array(
							array(
								'service'      => 'daytime',
								'availability' => true,
								'timeFrame'    => array(
									'from'  => '09:00:00',
									'until' => '18:00:00',
								),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Wire the WP transient functions to an in-memory store for cache round-trips.
	 */
	private function with_transient_store(): void {
		$this->store = array();

		// Exception_Converter translates its messages; surface them verbatim in failures.
		Functions\when( '__' )->returnArg( 1 );

		// Cache_Adapter reads its TTL and allowlist through filters; pass the default value through.
		Functions\when( 'apply_filters' )->alias( fn( $tag, $value = null ) => $value );
		Functions\when( 'wp_json_encode' )->alias( fn( $data ) => json_encode( $data ) );
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value ) {
				$this->store[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			fn( $key ) => array_key_exists( $key, $this->store ) ? $this->store[ $key ] : false
		);
		Functions\when( 'delete_transient' )->alias(
			function ( $key ) {
				unset( $this->store[ $key ] );
				return true;
			}
		);
	}
}

// ── Test seams ─────────────────────────────────────────────────────────────

/**
 * Exposes the Service's protected request/response helpers and pins the handover date.
 */
class Testable_Timeframe_Service extends Service {

	/**
	 * Public wrapper for build_request().
	 *
	 * @param array $post_data Checkout POST data.
	 * @return \Postnl\Sdk\Service\Timeframes\V4\Request\MultipleServicesTimeframeRequest
	 */
	public function expose_build_request( array $post_data ) {
		return $this->build_request( $post_data );
	}

	/**
	 * Public wrapper for map_response().
	 *
	 * @param TimeframeMultipleServicesCollection $collection SDK timeframe collection.
	 * @return array
	 */
	public function expose_map_response( TimeframeMultipleServicesCollection $collection ): array {
		return $this->map_response( $collection );
	}

	/**
	 * Pin the handover date so request assertions are deterministic.
	 *
	 * @return string
	 */
	protected function get_handover_date(): string {
		return '2026-07-14';
	}
}

/**
 * Exposes get_handover_date() with a pinned clock so the calendar walk is deterministic.
 */
class Handover_Timeframe_Service extends Service {

	/**
	 * Pinned "now" returned by the clock seam.
	 *
	 * @var \DateTimeImmutable
	 */
	private \DateTimeImmutable $fixed_now;

	/**
	 * Pin the clock.
	 *
	 * @param \DateTimeImmutable $now Datetime to treat as the current time.
	 */
	public function set_now( \DateTimeImmutable $now ): void {
		$this->fixed_now = $now;
	}

	/**
	 * The pinned clock.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function now(): \DateTimeImmutable {
		return $this->fixed_now;
	}

	/**
	 * Public wrapper for get_handover_date().
	 *
	 * @return string
	 */
	public function expose_handover_date(): string {
		return $this->get_handover_date();
	}
}

/**
 * Client_Factory whose SDK builder is wired to a fake HTTP client so no network
 * call is made, while the production caching plugin stack stays intact.
 */
class Spy_Timeframe_Client_Factory extends Client_Factory {

	/**
	 * Fake HTTP client injected into every built SDK client.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http_client;

	/**
	 * Constructor.
	 *
	 * @param object          $settings    Settings stub.
	 * @param ClientInterface $http_client Fake HTTP client.
	 */
	public function __construct( object $settings, ClientInterface $http_client ) {
		parent::__construct( $settings );
		$this->http_client = $http_client;
	}

	/**
	 * Attach the fake HTTP client to the configured builder.
	 *
	 * @param string $v4_key          V4 API key.
	 * @param bool   $is_sandbox      Sandbox flag.
	 * @param string $customer_number PostNL customer number.
	 * @param string $customer_code   PostNL customer code.
	 * @return ClientBuilder
	 */
	protected function make_builder( string $v4_key, bool $is_sandbox, string $customer_number, string $customer_code ): ClientBuilder {
		return parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )
			->withHttpClient( $this->http_client );
	}
}

/**
 * PSR-18 client that counts sends and always returns the same canned 200 response.
 */
class Counting_Http_Client implements ClientInterface {

	/**
	 * Number of requests sent.
	 *
	 * @var int
	 */
	public int $count = 0;

	/**
	 * The most recent outgoing request, captured for header assertions.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Canned JSON response body.
	 *
	 * @var string
	 */
	private string $body;

	/**
	 * Constructor.
	 *
	 * @param string $body Canned JSON response body.
	 */
	public function __construct( string $body ) {
		$this->body = $body;
	}

	/**
	 * Count the call and return the canned response.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		++$this->count;
		$this->last_request = $request;
		return new Response( 200, array( 'Content-Type' => 'application/json' ), $this->body );
	}
}
