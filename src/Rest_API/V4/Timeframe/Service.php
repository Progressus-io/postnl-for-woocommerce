<?php
/**
 * Class Rest_API\V4\Timeframe\Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Timeframe
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Timeframe;

use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\DeliveryWindowService;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Timeframes\V4\Request\MultipleServicesTimeframeRequest;
use Postnl\Sdk\Service\Timeframes\V4\Response\TimeframeMultipleServicesCollection;
use Postnl\Sdk\Transport\Cache\CachingPlugin;
use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
use PostNLWooCommerce\Rest_API\SDK\Cache_Adapter;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service
 *
 * V4 SDK-backed delivery-day (timeframe) lookup. Mirrors the timeframe half of
 * Legacy\Checkout\{Client,Item_Info}: it builds the request from the checkout
 * address plus shipping settings, calls the SDK timeframes() endpoint, and maps
 * the response back into the exact DeliveryOptions shape Frontend\Container
 * consumes (see Frontend\Container::get_checkout_data() and get_default_value()),
 * so callers cannot tell V4 from the legacy /shipment/v1/checkout path.
 *
 * Timeframe responses are the same on every checkout pageload for a given
 * address, so the request is routed through the SDK CachingPlugin (backed by
 * Cache_Adapter / WP transients) with only /timeframe/ allowlisted.
 *
 * @since   5.9.6
 * @package PostNLWooCommerce\Rest_API\V4\Timeframe
 */
class Service implements Timeframe_Service_Interface {

	/**
	 * Maximum number of look-ahead days the V4 timeframe endpoint accepts.
	 */
	private const V4_MAX_DELIVERY_DAYS = 14;

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
	 * Look-ahead days used by the plugin today; overridable per instance.
	 */
	public const DEFAULT_DELIVERY_DAYS = 10;

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
	 * Number of look-ahead days to request, clamped to the V4 maximum.
	 *
	 * @var int
	 */
	private $number_of_days;

	/**
	 * Service constructor.
	 *
	 * @param Client_Factory $client_factory SDK client factory.
	 * @param object         $settings       Plugin settings instance.
	 * @param int            $number_of_days Look-ahead days (default 10, capped at the V4 max of 14).
	 */
	public function __construct( Client_Factory $client_factory, object $settings, int $number_of_days = self::DEFAULT_DELIVERY_DAYS ) {
		$this->client_factory = $client_factory;
		$this->settings       = $settings;
		$this->number_of_days = $this->clamp_days( $number_of_days );
	}

	/**
	 * Retrieve available delivery-day timeframes for a checkout address.
	 *
	 * @param array $post_data Checkout POST data (shipping_* address fields).
	 *
	 * @return array {
	 *     @type array $DeliveryOptions Legacy-shaped delivery options; see
	 *                                  Timeframe_Service_Interface.
	 * }
	 *
	 * @throws \Exception Converted SDK error when the request fails.
	 */
	public function get_delivery_options( array $post_data ): array {
		try {
			$request  = $this->build_request( $post_data );
			$client   = $this->build_client();
			$response = $client->timeframes()->forMultipleServices( $request );

			return array( 'DeliveryOptions' => $this->map_response( $response->timeframes() ) );
		} catch ( \Throwable $exception ) {
			// Exception_Converter returns a plugin-shaped \Exception with an already-escaped message.
			$error = Exception_Converter::convert( $exception );
			throw $error;
		}
	}

	/**
	 * Build the SDK request from the checkout address and shipping settings.
	 *
	 * Mirrors Legacy\Checkout\Item_Info: delivery days are always requested
	 * (Base_Info hardcodes delivery_days_enabled), evening is added when enabled,
	 * and morning stays a daytime sub-window (V4 has no separate morning service).
	 *
	 * @param array $post_data Checkout POST data.
	 *
	 * @return MultipleServicesTimeframeRequest
	 */
	protected function build_request( array $post_data ): MultipleServicesTimeframeRequest {
		return new MultipleServicesTimeframeRequest(
			handoverDate: $this->get_handover_date(),
			receiverAddress: $this->build_receiver_address( $post_data ),
			services: $this->build_services(),
			shipmentType: ShipmentType::Parcel,
			numberOfDays: $this->number_of_days,
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
	 * Delivery-window services to request.
	 *
	 * @return DeliveryWindowService[]
	 */
	protected function build_services(): array {
		$services = array( DeliveryWindowService::Daytime );

		if ( $this->is_evening_enabled() ) {
			$services[] = DeliveryWindowService::Evening;
		}

		return $services;
	}

	/**
	 * Build the SDK client with the timeframe caching plugin attached.
	 *
	 * The CachingPlugin only caches responses whose URI contains '/timeframe/',
	 * and its key prefix ('timeframe') keeps the Cache_Adapter allowlist happy so
	 * both gates agree on what may be cached.
	 *
	 * @return \Postnl\Sdk\Client\PostnlClientInterface
	 */
	protected function build_client() {
		$v4_key = $this->get_v4_key();

		$caching_plugin = CachingPlugin::create(
			cache: new Cache_Adapter( $v4_key ),
			ttl: $this->cache_ttl(),
			allowedEndpoints: array( '/timeframe/' ),
			keyPrefix: 'timeframe'
		);

		return $this->client_factory->build_with_plugins( $v4_key, (bool) $this->settings->is_sandbox(), $caching_plugin );
	}

	/**
	 * Map the SDK timeframe collection into the legacy DeliveryOptions shape.
	 *
	 * Available timeframes are grouped by delivery date; each window carries a
	 * single legacy option code ('Daytime', 'Evening', or '08:00-12:00').
	 *
	 * @param TimeframeMultipleServicesCollection $collection SDK timeframe collection.
	 *
	 * @return array<int, array{DeliveryDate: string, Timeframe: array<int, array{From: string, To: string, Options: string[]}>}>
	 */
	protected function map_response( TimeframeMultipleServicesCollection $collection ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO properties are camelCase.
		$by_date = array();

		foreach ( $collection->filterAvailable()->all() as $timeframe ) {
			if ( null === $timeframe->deliveryDate || null === $timeframe->timeFrame ) {
				continue;
			}

			$date = $timeframe->deliveryDate;

			if ( ! isset( $by_date[ $date ] ) ) {
				$by_date[ $date ] = array(
					'DeliveryDate' => $date,
					'Timeframe'    => array(),
				);
			}

			$by_date[ $date ]['Timeframe'][] = array(
				'From'    => (string) $timeframe->timeFrame->from,
				'To'      => (string) $timeframe->timeFrame->until,
				'Options' => array( $this->map_option( $timeframe ) ),
			);
		}

		return array_values( $by_date );
	}

	/**
	 * Translate a V4 timeframe into the legacy option code the checkout expects.
	 *
	 * '08:00-12:00' is only emitted when the merchant enabled morning delivery,
	 * so a disabled morning window collapses to a plain 'Daytime' option instead
	 * of introducing a non-standard fee tab that the legacy path would not show.
	 *
	 * @param \Postnl\Sdk\ResponseData\V4\TimeFrame $timeframe SDK timeframe entry.
	 *
	 * @return string
	 */
	protected function map_option( $timeframe ): string {
		$service = is_string( $timeframe->service ) ? strtolower( $timeframe->service ) : '';
		$slot    = $timeframe->timeFrame;

		if ( DeliveryWindowService::Evening->value === $service || ( null !== $slot && $slot->isEvening() ) ) {
			return 'Evening';
		}

		if ( $this->is_morning_enabled() && null !== $slot && $slot->isMorning() ) {
			return '08:00-12:00';
		}

		return 'Daytime';
	}
	// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase

	/**
	 * Handover date (ISO 8601 date) the SDK computes delivery days from.
	 *
	 * The legacy checkout call sends OrderDate, ShippingDuration, and per-day
	 * CutOffTimes and lets PostNL walk the calendar to the first shippable day;
	 * the V4 request only accepts the resulting handoverDate, so that walk
	 * happens here: an order placed after the cut-off time hands over a day
	 * later, each transit day beyond the first adds a preparation day, and the
	 * handover then lands on the next enabled drop-off day.
	 *
	 * @return string
	 */
	protected function get_handover_date(): string {
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
	 * TTL, in seconds, for cached timeframe responses.
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
		 * @param int $ttl Default 600 seconds.
		 */
		$ttl = (int) apply_filters( 'postnl_v4_cache_ttl', 600 );

		return $ttl > 0 ? $ttl : 600;
	}

	/**
	 * Whether evening delivery is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_evening_enabled(): bool {
		return method_exists( $this->settings, 'is_evening_delivery_enabled' )
			&& (bool) $this->settings->is_evening_delivery_enabled();
	}

	/**
	 * Whether morning delivery is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_morning_enabled(): bool {
		return method_exists( $this->settings, 'is_morning_delivery_enabled' )
			&& (bool) $this->settings->is_morning_delivery_enabled();
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
	 * Clamp the requested look-ahead days to the range the V4 endpoint accepts.
	 *
	 * @param int $days Requested number of days.
	 *
	 * @return int
	 */
	private function clamp_days( int $days ): int {
		if ( $days < 1 ) {
			return 1;
		}

		return min( $days, self::V4_MAX_DELIVERY_DAYS );
	}
}
