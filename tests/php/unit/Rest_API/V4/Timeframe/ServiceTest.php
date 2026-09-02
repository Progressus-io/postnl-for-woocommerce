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
use Postnl\Sdk\ResponseData\V4\TimeSlot;
use Postnl\Sdk\Service\Timeframes\Response\MultipleServicesTimeframeCollection;
use Postnl\Sdk\Service\Timeframes\Response\Timeframe;
use PostNLWooCommerce\Rest_API\SDK\Cache_Adapter;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Timeframe\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

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
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings() ), $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );
		$request = $service->expose_build_request( $this->nl_post_data() );

		$this->assertSame( '2026-07-14', $request->handoverDate->format( 'Y-m-d' ) );
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
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings() ), $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );

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
		$with_evening = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true ) ), $this->make_settings( true ), self::V4_KEY, self::DAYS, new NullLogger() );
		$this->assertSame(
			array( DeliveryWindowService::Daytime, DeliveryWindowService::Evening ),
			$with_evening->expose_build_request( $this->nl_post_data() )->services
		);

		$no_evening = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false ) ), $this->make_settings( false ), self::V4_KEY, self::DAYS, new NullLogger() );
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
		$configured = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 5, new NullLogger() );
		$this->assertSame( 5, $configured->expose_build_request( $post )->numberOfDays, 'The configured value is used as-is.' );

		$over = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 20, new NullLogger() );
		$this->assertSame( 14, $over->expose_build_request( $post )->numberOfDays, 'Capped at the V4 maximum of 14.' );

		$under = new Testable_Timeframe_Service( $factory, $settings, self::V4_KEY, 0, new NullLogger() );
		$this->assertSame( 1, $under->expose_build_request( $post )->numberOfDays, 'Floored at 1.' );
	}

	// ── Response mapping ─────────────────────────────────────────────────────

	/**
	 * @testdox map_response() groups available timeframes by date into the legacy shape
	 */
	public function test_map_response_produces_legacy_shape(): void {
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true, true ) ), $this->make_settings( true, true ), self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '18:00:00', until: '22:00:00' ), availability: true, service: 'evening' ),
				new Timeframe( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: false, service: 'daytime' ),
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
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false, false ) ), $this->make_settings( false, false ), self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '18:00:00', until: '22:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
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
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( true, false ) ), $this->make_settings( true, false ), self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
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
		$service = new Testable_Timeframe_Service( new Client_Factory( $this->make_settings( false, false ) ), $this->make_settings( false, false ), self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '08:00:00', until: '12:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
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

	/**
	 * One available daytime window on Tuesday 14-07, Wednesday 15-07 and Friday 17-07 2026.
	 *
	 * Their handover days (the day before each) are Monday, Tuesday and Thursday.
	 *
	 * @return MultipleServicesTimeframeCollection
	 */
	private function three_daytime_dates(): MultipleServicesTimeframeCollection {
		return new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '17-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
			)
		);
	}

	/**
	 * @testdox Delivery dates whose handover falls on a disabled drop-off day are dropped
	 *
	 * The V4 request carries a single handoverDate and no excluded-days field, so
	 * unlike the legacy call — which sent one CutOffTimes entry per excluded day and
	 * let PostNL hide the unreachable dates — every date after the first ignores
	 * drop-off days unless the mapping filters them. A merchant shipping Mon+Thu
	 * only would otherwise be offering a Wednesday delivery that needs a Tuesday
	 * handover they never do.
	 */
	public function test_delivery_dates_unreachable_from_a_dropoff_day_are_dropped(): void {
		$settings = $this->make_settings( dropoff: array( 'mon', 'thu' ) );
		$service  = new Testable_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

		$this->assertSame(
			array( '14-07-2026', '17-07-2026' ),
			array_column( $service->expose_map_response( $this->three_daytime_dates() ), 'DeliveryDate' ),
			'Wednesday 15-07 needs a Tuesday handover, which is not a drop-off day.'
		);
	}

	/**
	 * @testdox With every drop-off day enabled the delivery-date filter is a no-op
	 */
	public function test_all_dropoff_days_enabled_keeps_every_delivery_date(): void {
		$settings = $this->make_settings( dropoff: self::ALL_DROPOFF_DAYS );
		$service  = new Testable_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

		$this->assertSame(
			array( '14-07-2026', '15-07-2026', '17-07-2026' ),
			array_column( $service->expose_map_response( $this->three_daytime_dates() ), 'DeliveryDate' )
		);
	}

	/**
	 * @testdox A delivery date in an unreadable format is kept rather than filtered away
	 *
	 * If PostNL ever returned something other than d-m-Y, treating unparseable dates
	 * as unreachable would silently drop every option and leave the customer with an
	 * empty delivery-day widget and no indication why.
	 */
	public function test_unparseable_delivery_date_is_kept(): void {
		$settings = $this->make_settings( dropoff: array( 'mon' ) );
		$service  = new Testable_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: 'not-a-date', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
			)
		);

		$this->assertSame(
			array( 'not-a-date' ),
			array_column( $service->expose_map_response( $collection ), 'DeliveryDate' )
		);
	}

	/**
	 * @testdox Entries without a delivery date or without a window are skipped
	 *
	 * Both fields are nullable on the SDK DTO, and a null date reaches a string
	 * parameter while a null window is dereferenced for its From/To — so an
	 * unguarded entry does not merely map badly, it fatals the checkout lookup.
	 */
	public function test_entries_missing_a_date_or_a_window_are_skipped(): void {
		$settings = $this->make_settings();
		$service  = new Testable_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

		$collection = new MultipleServicesTimeframeCollection(
			array(
				new Timeframe( deliveryDate: null, timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '14-07-2026', timeFrame: null, availability: true, service: 'daytime' ),
				new Timeframe( deliveryDate: '15-07-2026', timeFrame: new TimeSlot( from: '09:00:00', until: '18:00:00' ), availability: true, service: 'daytime' ),
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
			'Only the complete entry survives; the incomplete ones must not reach the mapper.'
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
		$service = new Handover_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );
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
	 *
	 * The fixture is '16:0' rather than something obviously non-numeric on purpose:
	 * the cut-off comparison is a string compare, so 'not-a-time' sorts above every
	 * clock string and the handover stays put whether the validation runs or not.
	 * '16:0' sorts below '22:00', so dropping the validation shifts the handover a
	 * day and this test notices.
	 */
	public function test_handover_tolerates_malformed_cutoff(): void {
		$settings = $this->make_shipping_settings( '16:0', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-14 22:00:00', $settings );

		$this->assertSame( '2026-07-14', $service->expose_handover_date() );
	}

	/**
	 * @testdox The drop-off walk reaches a day a full week away
	 *
	 * A merchant handing over only on Sundays needs six steps from a Monday order.
	 * Every other handover test lands within a step or two, so a walk that gave up
	 * early would still pass them and quietly return an unshippable Monday here.
	 */
	public function test_handover_walks_a_full_week_to_the_only_dropoff_day(): void {
		// Monday 2026-07-13, before the cut-off, so the walk starts on the order day.
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'sun' ) );
		$service  = $this->make_handover_service( '2026-07-13 10:00:00', $settings );

		$this->assertSame( '2026-07-19', $service->expose_handover_date(), 'The following Sunday is six days out.' );
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
		$service  = new Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

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
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );
		$post    = $this->nl_post_data();

		$first  = $service->get_delivery_options( $post );
		$second = $service->get_delivery_options( $post );

		$this->assertSame( 1, $http->count, 'Second identical lookup must be served from cache.' );
		$this->assertSame( $first, $second );
		$this->assertSame( '14-07-2026', $first['DeliveryOptions'][0]['DeliveryDate'] );

		// 'Evening', not 'Daytime': Daytime is map_option()'s fallback, so asserting it
		// would pass even if the cached body were never parsed or mapped at all.
		$this->assertSame( array( 'Evening' ), $first['DeliveryOptions'][0]['Timeframe'][0]['Options'] );
	}

	/**
	 * @testdox A lookup for a different address is not served the previous address's response
	 *
	 * The cached payload is keyed on the request, so a cache that ignored keys
	 * entirely would still satisfy the hit test above while handing every customer
	 * the first customer's delivery days.
	 */
	public function test_a_different_address_misses_the_cache(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$http    = new Counting_Http_Client( $this->timeframe_response_body() );
		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), $http );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );

		$service->get_delivery_options( $this->nl_post_data() );
		$service->get_delivery_options( array_merge( $this->nl_post_data(), array( 'shipping_postcode' => '5678 CD' ) ) );

		$this->assertSame( 2, $http->count, 'A different postcode is a different lookup and must reach PostNL.' );
	}

	/**
	 * @testdox A filtered TTL of zero or less falls back to the default instead of reaching CachingPlugin
	 *
	 * CachingPlugin throws on a non-positive TTL, so passing a filtered zero through
	 * would turn a stray filter into a fatal checkout lookup.
	 */
	public function test_cache_ttl_falls_back_to_the_default_on_a_non_positive_filter_value(): void {
		Functions\when( 'apply_filters' )->alias(
			fn( $tag, $value = null ) => 'postnl_v4_cache_ttl' === $tag ? 0 : $value
		);

		$settings = $this->make_settings();
		$service  = new Testable_Timeframe_Service( new Client_Factory( $settings ), $settings, self::V4_KEY, self::DAYS, new NullLogger() );

		$this->assertSame( Cache_Adapter::DEFAULT_TTL, $service->expose_cache_ttl() );
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
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );

		$service->get_delivery_options( $this->nl_post_data() );

		$this->assertNotNull( $http->last_request, 'The SDK must have sent a request.' );
		$this->assertSame(
			'v4-secret',
			$http->last_request->getHeaderLine( 'apiKey' ),
			'The SDK authenticates with the apiKey header; any other value means the client is not using the configured key.'
		);
	}

	// ── Error handling ───────────────────────────────────────────────────────

	/**
	 * @testdox An SDK failure surfaces as the converted, merchant-facing error
	 *
	 * Callers of the timeframe service handle errors the way they handle
	 * Rest_API\Base::check_response_error() ones — they read getMessage() and show
	 * it. Letting a raw SDK exception through would put SDK-internal wording in
	 * front of the merchant and drop the HTTP status the converter preserves.
	 */
	public function test_sdk_failure_surfaces_as_the_converted_error(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), new Failing_Http_Client() );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, new NullLogger() );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid PostNL API credentials. (traceId: trace-abc)' );
		$this->expectExceptionCode( 401 );

		$service->get_delivery_options( $this->nl_post_data() );
	}

	// ── Logging ──────────────────────────────────────────────────────────────

	/**
	 * @testdox A failed lookup is logged at error level with the original SDK cause
	 *
	 * Exception_Converter deliberately replaces the SDK message with a merchant-safe
	 * one and keeps the original only as the previous exception, which nothing else
	 * reads — and one of those safe messages tells the merchant to "check the PostNL
	 * logs for details". Without this log line those logs are empty.
	 */
	public function test_failed_lookup_is_logged_with_the_original_cause(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$logger  = new Spy_Logger();
		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), new Failing_Http_Client() );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, $logger );

		try {
			$service->get_delivery_options( $this->nl_post_data() );
			$this->fail( 'The converted SDK error must propagate.' );
		} catch ( \Exception $error ) {
			$cause = $error->getPrevious();
		}

		$this->assertNotNull( $cause, 'Exception_Converter must preserve the SDK exception as the cause.' );
		$this->assertNotSame( '', $cause->getMessage(), 'A blank cause message would make the assertion below vacuous.' );

		$errors = array_values(
			array_filter( $logger->records, static fn( array $record ) => LogLevel::ERROR === $record['level'] )
		);

		$this->assertCount( 1, $errors, 'The failure must be logged exactly once, at error level.' );
		$this->assertStringContainsString(
			$cause->getMessage(),
			$errors[0]['message'],
			'The safe message alone is not actionable; the original SDK message has to reach the log.'
		);
		$this->assertStringContainsString( get_class( $cause ), $errors[0]['message'], 'The cause class identifies where the failure came from.' );
	}

	/**
	 * @testdox A cache that silently stores nothing is reported through the logger
	 *
	 * Cache_Adapter warns once when a key clears no allowlisted prefix, which is the
	 * only signal that a mis-wired CachingPlugin keyPrefix has turned caching off —
	 * it is otherwise indistinguishable from a permanently cold cache. The warning
	 * can only fire if the Service hands the adapter its logger.
	 */
	public function test_cache_bypass_is_reported_through_the_logger(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		// Empty the cacheable-prefix allowlist so every key bypasses, standing in for
		// a keyPrefix that no longer matches.
		Functions\when( 'apply_filters' )->alias(
			fn( $tag, $value = null ) => 'postnl_v4_cache_allowed_prefixes' === $tag ? array() : $value
		);

		$logger  = new Spy_Logger();
		$factory = new Spy_Timeframe_Client_Factory( $this->make_settings(), new Counting_Http_Client( $this->timeframe_response_body() ) );
		$service = new Service( $factory, $this->make_settings(), self::V4_KEY, self::DAYS, $logger );

		$service->get_delivery_options( $this->nl_post_data() );

		$warnings = array_values(
			array_filter( $logger->records, static fn( array $record ) => LogLevel::WARNING === $record['level'] )
		);

		$this->assertCount( 1, $warnings, 'Cache_Adapter must be able to report the bypass, and only once.' );
		$this->assertStringContainsString( 'cache bypassed', $warnings[0]['message'] );
	}

	/**
	 * Canned V4 timeframe response body with a single available evening window.
	 *
	 * An evening window rather than a daytime one so tests can assert on an option
	 * code the mapper has to derive: 'Daytime' is map_option()'s fallback and would
	 * come out right even if the response never made it through the mapper.
	 *
	 * Tuesday 14-07-2026 implies a Monday handover, which is an enabled drop-off day
	 * under the default settings double, so the delivery-date filter keeps it.
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
								'service'      => 'evening',
								'availability' => true,
								'timeFrame'    => array(
									'from'  => '18:00:00',
									'until' => '22:00:00',
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
	 * @param MultipleServicesTimeframeCollection $collection SDK timeframe collection.
	 * @return array
	 */
	public function expose_map_response( MultipleServicesTimeframeCollection $collection ): array {
		return $this->map_response( $collection );
	}

	/**
	 * Public wrapper for cache_ttl().
	 *
	 * @return int
	 */
	public function expose_cache_ttl(): int {
		return $this->cache_ttl();
	}

	/**
	 * Pin the handover date so request assertions are deterministic.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function get_handover_date(): \DateTimeImmutable {
		return new \DateTimeImmutable( '2026-07-14' );
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
		return $this->get_handover_date()->format( 'Y-m-d' );
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

/**
 * PSR-18 client that always answers with a PostNL problem+json error.
 *
 * 401 is chosen because the SDK's retry policy treats it as permanent, so the
 * failure surfaces on the first attempt with no backoff sleeps in the test.
 */
class Failing_Http_Client implements ClientInterface {

	/**
	 * Return the canned error response.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		return new Response(
			401,
			array( 'Content-Type' => 'application/problem+json' ),
			(string) json_encode(
				array(
					'title'   => 'Unauthorized',
					'detail'  => 'apiKey header missing or invalid',
					'traceId' => 'trace-abc',
				)
			)
		);
	}
}

/**
 * PSR-3 logger that records every write for assertion.
 */
class Spy_Logger extends AbstractLogger {

	/**
	 * Recorded log calls, each as array{level: mixed, message: string, context: array}.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $records = array();

	/**
	 * Record the call.
	 *
	 * @param mixed              $level   PSR-3 level.
	 * @param string|\Stringable $message Log message.
	 * @param array              $context Context values.
	 * @return void
	 */
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array(
			'level'   => $level,
			'message' => (string) $message,
			'context' => $context,
		);
	}
}
