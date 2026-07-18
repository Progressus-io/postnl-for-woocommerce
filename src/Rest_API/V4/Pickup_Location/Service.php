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
use Postnl\Sdk\ResponseData\V4\Locations\PickUpLocationsCollection;
use Postnl\Sdk\Service\PickupLocations\V4\Request\PickUpNearAddressRequest;
use Postnl\Sdk\Transport\Cache\CachingPlugin;
use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\SDK\Cache_Adapter;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;

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
 * address, so the request is routed through the SDK CachingPlugin (backed by
 * Cache_Adapter / WP transients) with only /locations/ allowlisted.
 *
 * @since   5.9.6
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
	 * Number of pickup locations the plugin requests today; overridable per instance.
	 */
	public const DEFAULT_LOCATIONS = 3;

	/**
	 * SDK client factory.
	 *
	 * @var Client_Factory
	 */
	private $client_factory;

	/**
	 * Plugin settings instance.
	 *
	 * @var object
	 */
	private $settings;

	/**
	 * Number of locations to request, clamped to the V4 range.
	 *
	 * @var int
	 */
	private $number_of_locations;

	/**
	 * Service constructor.
	 *
	 * @param Client_Factory $client_factory      SDK client factory.
	 * @param object         $settings            Plugin settings instance.
	 * @param int            $number_of_locations Locations to request (default 3, capped at the V4 max of 10).
	 */
	public function __construct( Client_Factory $client_factory, object $settings, int $number_of_locations = self::DEFAULT_LOCATIONS ) {
		$this->client_factory      = $client_factory;
		$this->settings            = $settings;
		$this->number_of_locations = $this->clamp_locations( $number_of_locations );
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
		try {
			$request  = $this->build_request( $post_data );
			$client   = $this->build_client();
			$response = $client->pickupLocations()->nearAddress( $request );

			return array( 'PickupOptions' => $this->map_response( $response->locationsCollection() ) );
		} catch ( \Throwable $exception ) {
			// Exception_Converter returns a plugin-shaped \Exception; its message can
			// carry raw API text (field errors, upstream messages) — escape on output.
			$error = Exception_Converter::convert( $exception );
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
	 * Build the SDK client with the locations caching plugin attached.
	 *
	 * The CachingPlugin only caches responses whose URI contains '/locations/',
	 * and its key prefix ('locations') keeps the Cache_Adapter allowlist happy so
	 * both gates agree on what may be cached.
	 *
	 * @return \Postnl\Sdk\Client\PostnlClientInterface
	 */
	protected function build_client() {
		$v4_key = $this->get_v4_key();

		$caching_plugin = CachingPlugin::create(
			cache: new Cache_Adapter( $v4_key ),
			ttl: $this->cache_ttl(),
			allowedEndpoints: array( '/locations/' ),
			keyPrefix: 'locations'
		);

		return $this->client_factory->build_with_plugins( $v4_key, (bool) $this->settings->is_sandbox(), $caching_plugin );
	}

	/**
	 * Map the SDK locations collection into the legacy PickupOptions shape.
	 *
	 * V4 returns a flat list; the legacy shape groups locations under a pickup
	 * date, so the whole list becomes a single group. An empty collection yields
	 * an empty PickupOptions array, which hides the pickup tab.
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
				'PickupDate' => $this->get_pickup_date(),
				'Locations'  => $locations,
			),
		);
	}

	/**
	 * Map a single SDK PickupLocation into the legacy location shape.
	 *
	 * Name is emitted both at the top level (Pickup_Location_Service_Interface) and
	 * as Address.CompanyName so the classic Frontend\Dropoff_Points reader, which
	 * takes the company from the address block, resolves the same value.
	 *
	 * @param \Postnl\Sdk\ResponseData\V4\Locations\Location\PickupLocation $location SDK location entry.
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
	 * @param \Postnl\Sdk\ResponseData\V4\Locations\Location\LocationOpeningHours|null $opening_hours SDK opening-hours object.
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
	 * Pickup date (ISO 8601) the parcel is expected to reach the location.
	 *
	 * The legacy checkout call sends OrderDate, ShippingDuration, and per-day
	 * CutOffTimes and lets PostNL walk the calendar to the first shippable day;
	 * the V4 near-address request only accepts a single pickupDate, so that walk
	 * happens here: an order placed after the cut-off time hands over a day later,
	 * each transit day beyond the first adds a preparation day, and the handover
	 * then lands on the next enabled drop-off day.
	 *
	 * @return string
	 */
	protected function get_pickup_date(): string {
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

		return $handover->format( 'Y-m-d' );
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
		if ( method_exists( $this->settings, 'get_cut_off_time' ) ) {
			$cut_off = (string) $this->settings->get_cut_off_time();

			if ( 1 === preg_match( '/^(?:[01][0-9]|2[0-4]):[0-5][0-9]$/', $cut_off ) ) {
				return $cut_off;
			}
		}

		return self::DEFAULT_CUT_OFF_TIME;
	}

	/**
	 * Shipping duration in days (the legacy ShippingDuration / transit_time setting), minimum 1.
	 *
	 * @return int
	 */
	private function get_shipping_duration(): int {
		if ( ! method_exists( $this->settings, 'get_transit_time' ) ) {
			return 1;
		}

		return max( 1, (int) $this->settings->get_transit_time() );
	}

	/**
	 * Enabled drop-off weekday keys ('mon' … 'sun'); empty means no restriction.
	 *
	 * @return string[]
	 */
	private function get_dropoff_days(): array {
		if ( ! method_exists( $this->settings, 'get_dropoff_days' ) ) {
			return array();
		}

		$days = $this->settings->get_dropoff_days();

		return is_array( $days ) ? $days : array();
	}

	/**
	 * TTL, in seconds, for cached locations responses.
	 *
	 * Reads the same filter as Cache_Adapter so both agree, and never returns a
	 * value <= 0 since CachingPlugin rejects one.
	 *
	 * @return int
	 */
	protected function cache_ttl(): int {
		/**
		 * Filters the TTL, in seconds, for cached V4 timeframe/locations responses.
		 *
		 * @since 5.9.6
		 *
		 * @param int $ttl Default Cache_Adapter::DEFAULT_TTL (600 seconds).
		 */
		$ttl = (int) apply_filters( 'postnl_v4_cache_ttl', Cache_Adapter::DEFAULT_TTL );

		return $ttl > 0 ? $ttl : Cache_Adapter::DEFAULT_TTL;
	}

	/**
	 * Read the V4 API key from settings, tolerating settings without the getter.
	 *
	 * The V4 key field ships in a separate PR, so Service_Factory only routes here
	 * once it exists; the method_exists guard keeps this class safe in isolation.
	 *
	 * @return string
	 */
	private function get_v4_key(): string {
		if ( ! method_exists( $this->settings, 'get_v4_api_key' ) ) {
			return '';
		}

		$key = $this->settings->get_v4_api_key();

		return is_string( $key ) ? $key : '';
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
