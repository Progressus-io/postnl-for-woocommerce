<?php
/**
 * Class Rest_API\V4\Pickup_Location\Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Pickup_Location
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Pickup_Location;

use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\PickUpLocationType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\PickupLocations\Response\PickUpLocationsCollection;
use Postnl\Sdk\Service\PickupLocations\V4\Request\PickUpNearAddressRequest;
use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\SDK\Cache_Adapter;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Shipping_Method\Settings;
use Psr\Log\LoggerInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service
 *
 * V4 SDK-backed pickup-location (dropoff-point) lookup. Mirrors the locations
 * half of Legacy\Checkout\{Client,Item_Info}: it builds the request from the
 * checkout address plus shipping settings, calls the SDK pickupLocations()
 * near-address endpoint, and maps the response back into the PickupOptions shape
 * Frontend\Container consumes (see Pickup_Location_Service_Interface), so callers
 * cannot tell V4 from the legacy /shipment/v1/checkout path.
 *
 * V4 near-address returns a flat list of locations rather than the legacy
 * per-date PickupOptions groups, so the whole list is emitted as a single group
 * keyed on the computed pickup date.
 *
 * V4 provides no PartnerID or PickupTime for a location; those legacy-only fields
 * are reconciled where the two checkout halves are aggregated (task 17), not here.
 *
 * Locations responses are the same on every checkout pageload for a given
 * address, so the mapped result is cached at the service level (Cache_Adapter /
 * WP transients), keyed by the address and the settings that shape the pickup
 * date. The SDK's HTTP-layer CachingPlugin was removed in v3.0.0, so caching
 * lives here on the one read-only operation that is safe to cache.
 *
 * The PSR-3 logger is required, not optional: it is where a failed lookup's real
 * cause survives (Exception_Converter hands the merchant a safe message and keeps
 * the SDK's own only as the previous exception), and it is what Cache_Adapter
 * reports a mis-wired cache through. Wiring passes a Logger_Adapter built on
 * Main::get_logger(), so V4 entries land in the same WooCommerce log as the
 * legacy path and honour the same "enable logging" setting.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Pickup_Location
 */
class Service implements Pickup_Location_Service_Interface {

	/**
	 * Maximum number of locations the V4 near-address endpoint accepts.
	 */
	private const V4_MAX_LOCATIONS = 10;

	/**
	 * Cut-off time used when the merchant has not configured one, matching the
	 * Legacy\Checkout\Item_Info default.
	 */
	private const DEFAULT_CUT_OFF_TIME = '23:00';

	/**
	 * Weekday keys by ISO-8601 day number, as Settings::get_dropoff_days() returns them.
	 */
	private const WEEKDAYS = array(
		1 => 'mon',
		2 => 'tue',
		3 => 'wed',
		4 => 'thu',
		5 => 'fri',
		6 => 'sat',
		7 => 'sun',
	);

	/**
	 * SDK client factory.
	 *
	 * @var Client_Factory
	 */
	private $client_factory;

	/**
	 * Plugin settings instance.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * PostNL V4 API key used to authenticate SDK requests.
	 *
	 * @var string
	 */
	private $v4_key;

	/**
	 * Number of locations to request, clamped to the V4 range.
	 *
	 * @var int
	 */
	private $number_of_locations;

	/**
	 * PSR-3 logger the failure path and the cache adapter report through.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Memoised pickup date so the request and the mapped group always agree and
	 * the calendar walk runs once per lookup.
	 *
	 * @var string|null
	 */
	private $pickup_date = null;

	/**
	 * Service constructor.
	 *
	 * The API key, the location count and the logger are all required rather than
	 * defaulted: the key has no getter on Settings to fall back to, a defaulted
	 * count would silently ignore the merchant's configured value if a caller
	 * forgot to pass Settings::get_number_pickup_points(), and a defaulted
	 * NullLogger would let a caller wire the service up with logging silently
	 * switched off — the exact gap this parameter closes.
	 *
	 * The key is marked SensitiveParameter so PHP redacts it from stack traces,
	 * matching Client_Factory::build().
	 *
	 * @param Client_Factory  $client_factory      SDK client factory.
	 * @param Settings        $settings            Plugin settings instance.
	 * @param string          $v4_key              PostNL V4 API key.
	 * @param int             $number_of_locations Locations to request, capped at the V4 max of 10 and floored at 1.
	 * @param LoggerInterface $logger              PSR-3 logger; wiring passes a Logger_Adapter.
	 */
	public function __construct(
		Client_Factory $client_factory,
		Settings $settings,
		#[\SensitiveParameter]
		string $v4_key,
		int $number_of_locations,
		LoggerInterface $logger
	) {
		$this->client_factory      = $client_factory;
		$this->settings            = $settings;
		$this->v4_key              = $v4_key;
		$this->number_of_locations = $this->clamp_locations( $number_of_locations );
		$this->logger              = $logger;
	}

	/**
	 * Retrieve available PostNL pickup locations for a checkout address.
	 *
	 * @param array $post_data Checkout POST data (shipping_* address fields).
	 *
	 * @return array {
	 *     @type array $PickupOptions Legacy-shaped pickup options; see
	 *                                Pickup_Location_Service_Interface.
	 * }
	 *
	 * @throws \Exception Converted SDK error when the request fails.
	 */
	public function get_pickup_locations( array $post_data ): array {
		$cache     = new Cache_Adapter( $this->v4_key, $this->logger );
		$cache_key = $this->cache_key( $post_data );

		$cached = $cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$request  = $this->build_request( $post_data );
			$client   = $this->build_client();
			$response = $client->pickupLocations()->nearAddress( $request );

			$result = array( 'PickupOptions' => $this->map_response( $response->locations() ) );

			$cache->set( $cache_key, $result, $this->cache_ttl() );

			return $result;
		} catch ( \Throwable $exception ) {
			// Exception_Converter returns a plugin-shaped \Exception; its message can
			// carry raw API text (field errors, upstream messages) — escape on output.
			$error = Exception_Converter::convert( $exception );

			// The converted message is deliberately merchant-safe, and one of its
			// variants tells the reader to check these very logs, so the original SDK
			// failure has to be written here — nothing else reads getPrevious().
			$this->logger->error(
				sprintf(
					'V4 pickup-location lookup failed for destination "%1$s": %2$s (cause: %3$s: %4$s)',
					$this->describe_destination( $post_data ),
					$error->getMessage(),
					get_class( $exception ),
					$exception->getMessage()
				)
			);

			throw $error;
		}
	}

	/**
	 * Build the SDK request from the checkout address and shipping settings.
	 *
	 * Only retail pickup points are requested, matching the legacy checkout, which
	 * never surfaced parcel lockers.
	 *
	 * @param array $post_data Checkout POST data.
	 *
	 * @return PickUpNearAddressRequest
	 */
	protected function build_request( array $post_data ): PickUpNearAddressRequest {
		return new PickUpNearAddressRequest(
			numberOfLocations: $this->number_of_locations,
			receiverAddress: $this->build_receiver_address( $post_data ),
			locationType: PickUpLocationType::Retail,
			pickupDate: $this->get_pickup_date(),
			customerCode: (string) $this->settings->get_customer_code(),
			customerNumber: (string) $this->settings->get_customer_num()
		);
	}

	/**
	 * Build the receiver Address from the shipping_* POST fields.
	 *
	 * Matches Legacy\Checkout\Item_Info::convert_data_to_args(): the raw POST data
	 * is first run through Address_Utils::set_post_data_address() to resolve the
	 * billing→shipping fallback and house-number extraction, then address_1 is the
	 * street and address_2 the house number.
	 *
	 * @param array $post_data Checkout POST data.
	 *
	 * @return Address
	 */
	protected function build_receiver_address( array $post_data ): Address {
		$post_data = Address_Utils::set_post_data_address( $post_data );

		$country  = isset( $post_data['shipping_country'] ) ? (string) $post_data['shipping_country'] : '';
		$postcode = isset( $post_data['shipping_postcode'] ) ? str_replace( ' ', '', (string) $post_data['shipping_postcode'] ) : '';

		return new Address(
			countryIso: Country::fromValue( $country ),
			houseNumber: isset( $post_data['shipping_address_2'] ) ? (string) $post_data['shipping_address_2'] : '',
			postalCode: $postcode,
			street: isset( $post_data['shipping_address_1'] ) ? (string) $post_data['shipping_address_1'] : '',
			city: isset( $post_data['shipping_city'] ) ? (string) $post_data['shipping_city'] : ''
		);
	}

	/**
	 * Short, log-safe description of the destination a failed lookup was for.
	 *
	 * Legacy Rest_API\Base::send_request() writes the entire request body — street,
	 * house number and city included — to the same WooCommerce log. This keeps only
	 * what identifies which lookup failed and lets support reproduce it: the country
	 * and the postcode's leading area digits. The parts that would pin the entry to a
	 * household are dropped, since an error line is written whether or not anyone is
	 * debugging, while the SDK's own request logging (which does carry the address,
	 * PII-redacted) is what a merchant turns on deliberately.
	 *
	 * @param array $post_data Checkout POST data.
	 *
	 * @return string e.g. 'NL 2521'; empty when the payload carried no address.
	 */
	private function describe_destination( array $post_data ): string {
		$post_data = Address_Utils::set_post_data_address( $post_data );

		$country  = isset( $post_data['shipping_country'] ) ? (string) $post_data['shipping_country'] : '';
		$postcode = isset( $post_data['shipping_postcode'] ) ? str_replace( ' ', '', (string) $post_data['shipping_postcode'] ) : '';

		return trim( $country . ' ' . substr( $postcode, 0, 4 ) );
	}

	/**
	 * Build the SDK client for the pickup-location lookup.
	 *
	 * Caching is handled at the service level (see get_pickup_locations), not in
	 * the transport, so the client needs no cache plugin.
	 *
	 * @return \Postnl\Sdk\Client\PostnlClientInterface
	 */
	protected function build_client() {
		return $this->client_factory->build( $this->v4_key, (bool) $this->settings->is_sandbox() );
	}

	/**
	 * Cache key for a checkout address's mapped pickup options.
	 *
	 * Covers everything that changes the result: the receiver address, the number
	 * of locations requested, and the settings that drive the pickup date (cut-off
	 * time, transit time, drop-off days). A settings change therefore yields a new
	 * key — a miss that recomputes immediately — rather than serving a stale list.
	 * Prefixed with PREFIX_LOCATIONS so it clears Cache_Adapter's allowlist.
	 *
	 * @param array $post_data Checkout POST data.
	 *
	 * @return string
	 */
	protected function cache_key( array $post_data ): string {
		$post_data = Address_Utils::set_post_data_address( $post_data );

		$parts = array(
			(string) ( $post_data['shipping_country'] ?? '' ),
			str_replace( ' ', '', (string) ( $post_data['shipping_postcode'] ?? '' ) ),
			(string) ( $post_data['shipping_address_2'] ?? '' ),
			(string) ( $post_data['shipping_address_1'] ?? '' ),
			(string) ( $post_data['shipping_city'] ?? '' ),
			(string) $this->number_of_locations,
			$this->get_pickup_date(),
			(string) $this->settings->get_customer_code(),
			(string) $this->settings->get_customer_num(),
		);

		return Cache_Adapter::PREFIX_LOCATIONS . '_' . md5( implode( '|', $parts ) );
	}

	/**
	 * Map the SDK locations collection into the legacy PickupOptions shape.
	 *
	 * V4 returns a flat list; the legacy shape groups locations under a pickup
	 * date, so the whole list becomes a single group. An empty collection yields
	 * an empty PickupOptions array, which hides the pickup tab.
	 *
	 * The group's PickupDate is the customer-facing one and is emitted as d-m-Y,
	 * not the ISO date the request carries: Frontend\Dropoff_Points reads it into
	 * the pickup radio label verbatim, and it is stored on the order as
	 * dropoff_points_date for the admin order box and the confirmation email —
	 * every date the plugin shows is d-m-Y. Both formats come from the one
	 * memoised pickup date, so the request and the label can never disagree.
	 *
	 * @param PickUpLocationsCollection $collection SDK locations collection.
	 *
	 * @return array<int, array{PickupDate: string, Locations: array<int, array>}>
	 */
	protected function map_response( PickUpLocationsCollection $collection ): array {
		$locations = array();

		foreach ( $collection->all() as $location ) {
			$locations[] = $this->map_location( $location );
		}

		if ( empty( $locations ) ) {
			return array();
		}

		return array(
			array(
				'PickupDate' => $this->get_display_pickup_date(),
				'Locations'  => $locations,
			),
		);
	}

	/**
	 * The pickup date in the d-m-Y format the plugin shows customers.
	 *
	 * Derived from get_pickup_date() rather than computed separately so the date
	 * on the request and the date on the radio label stay the same day.
	 *
	 * get_pickup_date() is a protected seam, so a subclass can hand this a string
	 * that is not ISO; createFromFormat() then returns false and false->format()
	 * would fatal the whole checkout lookup. Such a date is echoed unchanged
	 * instead — the same choice Timeframe\Service makes for a delivery date it
	 * cannot parse.
	 *
	 * @return string
	 */
	private function get_display_pickup_date(): string {
		$iso = $this->get_pickup_date();

		$date = \DateTimeImmutable::createFromFormat( '!Y-m-d', $iso );

		return false === $date ? $iso : $date->format( 'd-m-Y' );
	}

	/**
	 * Map a single SDK PickupLocation into the legacy location shape.
	 *
	 * Name is emitted both at the top level (Pickup_Location_Service_Interface) and
	 * as Address.CompanyName so the classic Frontend\Dropoff_Points reader, which
	 * takes the company from the address block, resolves the same value.
	 *
	 * @param \Postnl\Sdk\Service\PickupLocations\Response\Location\PickupLocation $location SDK location entry.
	 *
	 * @return array
	 */
	protected function map_location( $location ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO properties are camelCase.
		$address = $location->address;

		return array(
			'LocationCode' => (string) $location->pickupLocationId,
			'Name'         => (string) $location->name,
			'Distance'     => null === $location->distance ? '' : (string) $location->distance,
			'Address'      => array(
				'CompanyName' => (string) $location->name,
				'Street'      => null !== $address ? (string) $address->street : '',
				'HouseNr'     => null !== $address ? (string) $address->houseNumber : '',
				'Zipcode'     => null !== $address ? (string) $address->postalCode : '',
				'City'        => null !== $address ? (string) $address->city : '',
				'Countrycode' => ( null !== $address && null !== $address->countryIso ) ? $address->countryIso->value : '',
			),
			'OpeningHours' => $this->map_opening_hours( $location->openingTimes ),
		);
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Flatten the SDK opening-times object into a day => [ { From, To } ] array.
	 *
	 * @param \Postnl\Sdk\Service\PickupLocations\Response\Location\LocationOpeningHours|null $opening_hours SDK opening-hours object.
	 *
	 * @return array<int, array{Day: string, Times: array<int, array{From: string, To: string}>}>
	 */
	protected function map_opening_hours( $opening_hours ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO properties are camelCase.
		if ( null === $opening_hours || null === $opening_hours->openingTimes ) {
			return array();
		}

		$days = array();

		foreach ( $opening_hours->openingTimes as $day_times ) {
			$times = array();

			foreach ( (array) $day_times->times as $slot ) {
				$times[] = array(
					'From' => (string) $slot->from,
					'To'   => (string) $slot->until,
				);
			}

			$days[] = array(
				'Day'   => (string) $day_times->day,
				'Times' => $times,
			);
		}

		return $days;
		// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
	}

	/**
	 * Pickup date (ISO 8601): the date the parcel reaches the pickup location.
	 *
	 * The SDK documents pickupDate as "the date on which the parcel needs to be
	 * delivered at the PickUpLocation" — an arrival date, not a handover date.
	 *
	 * The legacy checkout call sends OrderDate, ShippingDuration, and per-day
	 * CutOffTimes and lets PostNL walk the calendar to the first shippable day;
	 * the V4 near-address request accepts neither, so the walk happens here, on
	 * the same model V4\Timeframe\Service::get_handover_date() uses: an order
	 * placed after the cut-off time hands over a day later, each transit day
	 * beyond the first adds a preparation day, and the handover then lands on the
	 * next enabled drop-off day. Arrival is one network day after that handover —
	 * the Transit Time setting promises delivery at order + t and the merchant
	 * hands over at order + (t - 1), so the last day is PostNL's.
	 *
	 * That final day is plain calendar arithmetic, deliberately not re-walked onto
	 * a drop-off day: drop-off days say when the merchant hands parcels to PostNL,
	 * not which days PostNL delivers on.
	 *
	 * @return string
	 */
	protected function get_pickup_date(): string {
		if ( null !== $this->pickup_date ) {
			return $this->pickup_date;
		}

		$now      = $this->now();
		$handover = $now;

		if ( $now->format( 'H:i' ) > $this->get_cut_off_time() ) {
			$handover = $handover->modify( '+1 day' );
		}

		$extra_days = $this->get_shipping_duration() - 1;
		if ( $extra_days > 0 ) {
			$handover = $handover->modify( '+' . $extra_days . ' days' );
		}

		$dropoff_days = $this->get_dropoff_days();
		if ( ! empty( $dropoff_days ) ) {
			$attempts = 0;
			while ( $attempts < 6 && ! in_array( self::WEEKDAYS[ (int) $handover->format( 'N' ) ], $dropoff_days, true ) ) {
				$handover = $handover->modify( '+1 day' );
				++$attempts;
			}
		}

		$this->pickup_date = $handover->modify( '+1 day' )->format( 'Y-m-d' );

		return $this->pickup_date;
	}

	/**
	 * Current site-timezone datetime; a seam for deterministic tests.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function now(): \DateTimeImmutable {
		return current_datetime();
	}

	/**
	 * Cut-off time (HH:MM) after which an order hands over the next day.
	 *
	 * Falls back to the Legacy\Checkout\Item_Info default when the setting is
	 * missing or malformed, instead of failing the checkout lookup.
	 *
	 * @return string
	 */
	private function get_cut_off_time(): string {
		$cut_off = (string) $this->settings->get_cut_off_time();

		if ( 1 === preg_match( '/^(?:[01][0-9]|2[0-4]):[0-5][0-9]$/', $cut_off ) ) {
			return $cut_off;
		}

		return self::DEFAULT_CUT_OFF_TIME;
	}

	/**
	 * Shipping duration in days (the legacy ShippingDuration / transit_time setting), minimum 1.
	 *
	 * @return int
	 */
	private function get_shipping_duration(): int {
		return max( 1, (int) $this->settings->get_transit_time() );
	}

	/**
	 * Enabled drop-off weekday keys ('mon' … 'sun'); empty means no restriction.
	 *
	 * @return string[]
	 */
	private function get_dropoff_days(): array {
		return $this->settings->get_dropoff_days();
	}

	/**
	 * TTL, in seconds, for cached locations responses.
	 *
	 * Reads the same filter as Cache_Adapter so both agree, and never returns a
	 * value <= 0.
	 *
	 * @return int
	 */
	protected function cache_ttl(): int {
		/**
		 * Filters the TTL, in seconds, for cached V4 timeframe/locations responses.
		 *
		 * @since 6.0.0
		 *
		 * @param int $ttl Default Cache_Adapter::DEFAULT_TTL (600 seconds).
		 */
		$ttl = (int) apply_filters( 'postnl_v4_cache_ttl', Cache_Adapter::DEFAULT_TTL );

		return $ttl > 0 ? $ttl : Cache_Adapter::DEFAULT_TTL;
	}

	/**
	 * Clamp the requested location count to the range the V4 endpoint accepts.
	 *
	 * @param int $locations Requested number of locations.
	 *
	 * @return int
	 */
	private function clamp_locations( int $locations ): int {
		if ( $locations < 1 ) {
			return 1;
		}

		return min( $locations, self::V4_MAX_LOCATIONS );
	}
}
