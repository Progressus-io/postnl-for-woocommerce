<?php
/**
 * Unit tests for Rest_API\V4\Pickup_Location\Service.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Pickup_Location
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Pickup_Location;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\PickUpLocationType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\ResponseData\V4\Locations\Location\DayOpeningTimes;
use Postnl\Sdk\ResponseData\V4\Locations\Location\LocationOpeningHours;
use Postnl\Sdk\ResponseData\V4\Locations\Location\PickupLocation;
use Postnl\Sdk\ResponseData\V4\Locations\PickUpLocationsCollection;
use Postnl\Sdk\ResponseData\V4\TimeSlot;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Pickup_Location\Service;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Pickup_Location\Service
 */
class ServiceTest extends UnitTestCase {

	/**
	 * In-memory stand-in for the WP transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = array();

	/**
	 * Build a settings stub exposing only what the Service reads.
	 *
	 * @return object
	 */
	private function make_settings(): object {
		return new class() {
			public function get_customer_code() {
				return 'DEVC';
			}

			public function get_customer_num() {
				return '11223344';
			}

			public function get_v4_api_key() {
				return 'v4-secret';
			}

			public function is_sandbox() {
				return true;
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
			'shipping_postcode'         => '2521 CA',
			'shipping_address_1'        => 'Weimarstraat',
			'shipping_address_2'        => '70',
			'shipping_city'             => 'Den Haag',
		);
	}

	// ── Request building ─────────────────────────────────────────────────────

	/**
	 * @testdox build_request() maps the checkout address and settings onto the SDK request
	 */
	public function test_build_request_maps_address_and_settings(): void {
		$service = new Testable_Pickup_Service( new Client_Factory( $this->make_settings() ), $this->make_settings() );
		$request = $service->expose_build_request( $this->nl_post_data() );

		$this->assertSame( 3, $request->numberOfLocations, 'Defaults to the plugin count of 3.' );
		$this->assertSame( PickUpLocationType::Retail, $request->locationType, 'Only retail pickup points are requested.' );
		$this->assertSame( '2026-07-14', $request->pickupDate );
		$this->assertSame( '11223344', $request->customerNumber );
		$this->assertSame( 'DEVC', $request->customerCode );

		$this->assertSame( Country::NL, $request->receiverAddress->countryIso );
		$this->assertSame( '2521CA', $request->receiverAddress->postalCode, 'Postcode spaces are stripped.' );
		$this->assertSame( '70', $request->receiverAddress->houseNumber );
		$this->assertSame( 'Weimarstraat', $request->receiverAddress->street );
		$this->assertSame( 'Den Haag', $request->receiverAddress->city );
	}

	/**
	 * @testdox The billing address is used when the order does not ship to a different address
	 */
	public function test_build_request_falls_back_to_billing_address(): void {
		$service = new Testable_Pickup_Service( new Client_Factory( $this->make_settings() ), $this->make_settings() );

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
	 * @testdox numberOfLocations defaults to 3 and is clamped to the V4 range [1, 10]
	 */
	public function test_number_of_locations_is_clamped(): void {
		$settings = $this->make_settings();
		$factory  = new Client_Factory( $settings );
		$post     = $this->nl_post_data();

		$default = new Testable_Pickup_Service( $factory, $settings );
		$this->assertSame( 3, $default->expose_build_request( $post )->numberOfLocations );

		$over = new Testable_Pickup_Service( $factory, $settings, 25 );
		$this->assertSame( 10, $over->expose_build_request( $post )->numberOfLocations, 'Capped at the V4 maximum of 10.' );

		$under = new Testable_Pickup_Service( $factory, $settings, 0 );
		$this->assertSame( 1, $under->expose_build_request( $post )->numberOfLocations, 'Floored at 1.' );
	}

	// ── Response mapping ─────────────────────────────────────────────────────

	/**
	 * @testdox map_response() maps the SDK locations into a single legacy PickupOptions group
	 */
	public function test_map_response_produces_legacy_shape(): void {
		$service = new Testable_Pickup_Service( new Client_Factory( $this->make_settings() ), $this->make_settings() );

		$collection = new PickUpLocationsCollection(
			array(
				new PickupLocation(
					pickupLocationId: '176227',
					locationType: 'Retail',
					name: 'Jumbo Den Haag',
					distance: 523,
					address: new Address(
						countryIso: Country::NL,
						houseNumber: '70',
						postalCode: '2521CA',
						street: 'Weimarstraat',
						city: 'Den Haag'
					),
					openingTimes: new LocationOpeningHours(
						openingTimes: array(
							new DayOpeningTimes( day: 'Monday', times: array( new TimeSlot( from: '08:00', until: '21:00' ) ) ),
						)
					)
				),
			)
		);

		$this->assertSame(
			array(
				array(
					'PickupDate' => '2026-07-14',
					'Locations'  => array(
						array(
							'LocationCode' => '176227',
							'Name'         => 'Jumbo Den Haag',
							'Distance'     => '523',
							'Address'      => array(
								'CompanyName' => 'Jumbo Den Haag',
								'Street'      => 'Weimarstraat',
								'HouseNr'     => '70',
								'Zipcode'     => '2521CA',
								'City'        => 'Den Haag',
								'Countrycode' => 'NL',
							),
							'OpeningHours' => array(
								array(
									'Day'   => 'Monday',
									'Times' => array(
										array(
											'From' => '08:00',
											'To'   => '21:00',
										),
									),
								),
							),
						),
					),
				),
			),
			$service->expose_map_response( $collection )
		);
	}

	/**
	 * @testdox An empty locations collection yields an empty PickupOptions array so the tab hides
	 */
	public function test_map_response_empty_collection_is_empty(): void {
		$service = new Testable_Pickup_Service( new Client_Factory( $this->make_settings() ), $this->make_settings() );

		$this->assertSame( array(), $service->expose_map_response( new PickUpLocationsCollection( array() ) ) );
	}

	/**
	 * @testdox A location without opening times or a distance still maps without warnings
	 */
	public function test_map_response_tolerates_missing_optional_fields(): void {
		$service = new Testable_Pickup_Service( new Client_Factory( $this->make_settings() ), $this->make_settings() );

		$collection = new PickUpLocationsCollection(
			array(
				new PickupLocation(
					pickupLocationId: '999',
					name: 'Parcel Point',
					address: new Address( countryIso: Country::NL, postalCode: '1000AA', city: 'Amsterdam', street: 'Damrak' )
				),
			)
		);

		$mapped = $service->expose_map_response( $collection );

		$this->assertSame( '', $mapped[0]['Locations'][0]['Distance'] );
		$this->assertSame( '', $mapped[0]['Locations'][0]['Address']['HouseNr'] );
		$this->assertSame( array(), $mapped[0]['Locations'][0]['OpeningHours'] );
	}

	// ── Pickup date ──────────────────────────────────────────────────────────

	/**
	 * Build a settings stub that also exposes the shipping-day settings the
	 * pickup-date walk reads (cut-off time, transit time, drop-off days).
	 *
	 * @param string   $cut_off Cut-off time (HH:MM).
	 * @param string   $transit Transit time in days, as the setting stores it.
	 * @param string[] $dropoff Enabled drop-off weekday keys.
	 * @return object
	 */
	private function make_shipping_settings( string $cut_off, string $transit, array $dropoff ): object {
		return new class( $cut_off, $transit, $dropoff ) {
			public function __construct(
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

			public function get_v4_api_key() {
				return 'v4-secret';
			}

			public function is_sandbox() {
				return true;
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
	 * Build a pickup-date-exposing service pinned to the given "now".
	 *
	 * @param string $now      Site-timezone datetime, e.g. '2026-07-14 10:00:00'.
	 * @param object $settings Settings stub.
	 * @return Pickup_Date_Service
	 */
	private function make_pickup_date_service( string $now, object $settings ): Pickup_Date_Service {
		$service = new Pickup_Date_Service( new Client_Factory( $settings ), $settings );
		$service->set_now( new \DateTimeImmutable( $now ) );

		return $service;
	}

	/**
	 * @testdox An order after the cut-off time is picked up the next day
	 */
	public function test_pickup_date_after_cutoff_shifts_a_day(): void {
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_pickup_date_service( '2026-07-14 17:00:00', $settings );

		$this->assertSame( '2026-07-15', $service->expose_pickup_date() );
	}

	/**
	 * @testdox Each transit day beyond the first adds a preparation day
	 */
	public function test_pickup_date_adds_transit_days(): void {
		$settings = $this->make_shipping_settings( '16:00', '3', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_pickup_date_service( '2026-07-14 10:00:00', $settings );

		$this->assertSame( '2026-07-16', $service->expose_pickup_date() );
	}

	/**
	 * @testdox The pickup date lands on the next enabled drop-off day
	 */
	public function test_pickup_date_skips_disabled_dropoff_days(): void {
		// Friday 2026-07-17 after the 16:00 cut-off; weekends are not drop-off days.
		$settings = $this->make_shipping_settings( '16:00', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri' ) );
		$service  = $this->make_pickup_date_service( '2026-07-17 17:00:00', $settings );

		$this->assertSame( '2026-07-20', $service->expose_pickup_date() );
	}

	/**
	 * @testdox A malformed cut-off setting falls back to the 23:00 default instead of failing
	 */
	public function test_pickup_date_tolerates_malformed_cutoff(): void {
		$settings = $this->make_shipping_settings( 'not-a-time', '1', array( 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' ) );
		$service  = $this->make_pickup_date_service( '2026-07-14 22:00:00', $settings );

		$this->assertSame( '2026-07-14', $service->expose_pickup_date() );
	}

	// ── Caching ──────────────────────────────────────────────────────────────

	/**
	 * @testdox An identical second lookup is served from cache without a second HTTP call
	 *
	 * Drives the real Service through the SDK CachingPlugin + Cache_Adapter with an
	 * in-memory transient store and a call-counting HTTP client, proving the
	 * /locations/ response is cached across identical requests within a request cycle.
	 */
	public function test_second_identical_call_hits_cache(): void {
		$this->with_transient_store();
		Functions\when( 'current_datetime' )->justReturn( new \DateTimeImmutable( '2026-07-14 10:00:00' ) );

		$body = wp_json_encode(
			array(
				'locations' => array(
					array(
						'pickupLocationId' => '176227',
						'locationType'     => 'Retail',
						'name'             => 'Jumbo Den Haag',
						'distance'         => 523,
						'address'          => array(
							'street'      => 'Weimarstraat',
							'houseNumber' => '70',
							'postalCode'  => '2521CA',
							'city'        => 'Den Haag',
							'countryIso'  => 'NL',
						),
					),
				),
			)
		);

		$http    = new Counting_Http_Client( $body );
		$factory = new Spy_Pickup_Client_Factory( $this->make_settings(), $http );
		$service = new Service( $factory, $this->make_settings() );
		$post    = $this->nl_post_data();

		$first  = $service->get_pickup_locations( $post );
		$second = $service->get_pickup_locations( $post );

		$this->assertSame( 1, $http->count, 'Second identical lookup must be served from cache.' );
		$this->assertSame( $first, $second );
		$this->assertSame( '176227', $first['PickupOptions'][0]['Locations'][0]['LocationCode'] );
		$this->assertSame( 'Jumbo Den Haag', $first['PickupOptions'][0]['Locations'][0]['Address']['CompanyName'] );
	}

	/**
	 * Wire the WP transient functions to an in-memory store for cache round-trips.
	 */
	private function with_transient_store(): void {
		$this->store = array();

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
 * Exposes the Service's protected request/response helpers and pins the pickup date.
 */
class Testable_Pickup_Service extends Service {

	/**
	 * Public wrapper for build_request().
	 *
	 * @param array $post_data Checkout POST data.
	 * @return \Postnl\Sdk\Service\PickupLocations\V4\Request\PickUpNearAddressRequest
	 */
	public function expose_build_request( array $post_data ) {
		return $this->build_request( $post_data );
	}

	/**
	 * Public wrapper for map_response().
	 *
	 * @param PickUpLocationsCollection $collection SDK locations collection.
	 * @return array
	 */
	public function expose_map_response( PickUpLocationsCollection $collection ): array {
		return $this->map_response( $collection );
	}

	/**
	 * Pin the pickup date so request and mapping assertions are deterministic.
	 *
	 * @return string
	 */
	protected function get_pickup_date(): string {
		return '2026-07-14';
	}
}

/**
 * Exposes get_pickup_date() with a pinned clock so the calendar walk is deterministic.
 */
class Pickup_Date_Service extends Service {

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
	 * Public wrapper for get_pickup_date().
	 *
	 * @return string
	 */
	public function expose_pickup_date(): string {
		return $this->get_pickup_date();
	}
}

/**
 * Client_Factory whose SDK builder is wired to a fake HTTP client so no network
 * call is made, while the production caching plugin stack stays intact.
 */
class Spy_Pickup_Client_Factory extends Client_Factory {

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
		return new Response( 200, array( 'Content-Type' => 'application/json' ), $this->body );
	}
}
