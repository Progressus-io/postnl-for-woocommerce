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
use Postnl\Sdk\Service\Timeframes\Response\MultipleServicesTimeframeCollection;
use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
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
 * V4 SDK-backed delivery-day (timeframe) lookup. Mirrors the timeframe half of
 * Legacy\Checkout\{Client,Item_Info}: it builds the request from the checkout
 * address plus shipping settings, calls the SDK timeframes() endpoint, and maps
 * the response back into the exact DeliveryOptions shape Frontend\Container
 * consumes (see Frontend\Container::get_checkout_data() and get_default_value()),
 * so callers cannot tell V4 from the legacy /shipment/v1/checkout path.
 *
 * Timeframe responses are the same on every checkout pageload for a given
 * address, so the mapped result is cached at the service level (Cache_Adapter /
 * WP transients), keyed by the address and the settings that shape the mapping.
 * The SDK's HTTP-layer CachingPlugin was removed in v3.0.0, so caching lives
 * here on the one read-only operation that is safe to cache.
 *
 * The PSR-3 logger is required, not optional: it is where a failed lookup's real
 * cause survives (Exception_Converter hands the merchant a safe message and keeps
 * the SDK's own only as the previous exception), and it is what Cache_Adapter
 * reports a mis-wired cache through. Wiring passes a Logger_Adapter built on
 * Main::get_logger(), so V4 entries land in the same WooCommerce log as the
 * legacy path and honour the same "enable logging" setting.
 *
 * @since   6.0.0
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
	 * Number of look-ahead days to request, clamped to the V4 maximum.
	 *
	 * @var int
	 */
	private $number_of_days;

	/**
	 * PSR-3 logger the failure path and the cache adapter report through.
	 *
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * Service constructor.
	 *
	 * The API key, the look-ahead day count and the logger are all required
	 * rather than defaulted: the key has no getter on Settings to fall back to, a
	 * defaulted day count would silently ignore the merchant's configured value
	 * if a caller forgot to pass Settings::get_number_delivery_days(), and a
	 * defaulted NullLogger would let a caller wire the service up with logging
	 * silently switched off — the exact gap this parameter closes.
	 *
	 * The key is marked SensitiveParameter so PHP redacts it from stack traces,
	 * matching Client_Factory::build().
	 *
	 * @since 6.0.0 Added the required $logger parameter.
	 *
	 * @param Client_Factory  $client_factory SDK client factory.
	 * @param Settings        $settings       Plugin settings instance.
	 * @param string          $v4_key         PostNL V4 API key.
	 * @param int             $number_of_days Look-ahead days, capped at the V4 max of 14 and floored at 1.
	 * @param LoggerInterface $logger         PSR-3 logger; wiring passes a Logger_Adapter.
	 */
	public function __construct(
		Client_Factory $client_factory,
		Settings $settings,
		#[\SensitiveParameter]
		string $v4_key,
		int $number_of_days,
		LoggerInterface $logger
	) {
		$this->client_factory = $client_factory;
		$this->settings       = $settings;
		$this->v4_key         = $v4_key;
		$this->number_of_days = $this->clamp_days( $number_of_days );
		$this->logger         = $logger;
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
		// A merchant who disabled every drop-off day never hands parcels over; the
		// legacy path marks all days unavailable so PostNL returns nothing — mirror
		// that with an empty result instead of asking for undeliverable days.
		if ( $this->all_dropoff_days_disabled() ) {
			return array( 'DeliveryOptions' => array() );
		}

		$cache     = new Cache_Adapter( $this->v4_key, $this->logger );
		$cache_key = $this->cache_key( $post_data );

		$cached = $cache->get( $cache_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		try {
			$request  = $this->build_request( $post_data );
			$client   = $this->build_client();
			$response = $client->timeframes()->forMultipleServices( $request );

			$result = array( 'DeliveryOptions' => $this->map_response( $response->timeframes() ) );

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
					'V4 timeframe lookup failed for destination "%1$s": %2$s (cause: %3$s: %4$s)',
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
	 * @return string e.g. 'NL 1234'; empty when the payload carried no address.
	 */
	private function describe_destination( array $post_data ): string {
		$post_data = Address_Utils::set_post_data_address( $post_data );

		$country  = isset( $post_data['shipping_country'] ) ? (string) $post_data['shipping_country'] : '';
		$postcode = isset( $post_data['shipping_postcode'] ) ? str_replace( ' ', '', (string) $post_data['shipping_postcode'] ) : '';

		return trim( $country . ' ' . substr( $postcode, 0, 4 ) );
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
	 * Build the SDK client for the timeframe lookup.
	 *
	 * Caching is handled at the service level (see get_delivery_options), not in
	 * the transport, so the client needs no cache plugin.
	 *
	 * @return \Postnl\Sdk\Client\PostnlClientInterface
	 */
	protected function build_client() {
		return $this->client_factory->build( $this->v4_key, (bool) $this->settings->is_sandbox() );
	}

	/**
	 * Cache key for a checkout address's mapped delivery options.
	 *
	 * Covers everything that changes the result: the receiver address, the
	 * handover date, the requested services and day count, and the settings the
	 * mapping reads (drop-off days, evening/morning toggles). A settings change
	 * therefore yields a new key — a miss that recomputes immediately — rather
	 * than serving a stale mapping. Prefixed with PREFIX_TIMEFRAME so it clears
	 * Cache_Adapter's allowlist.
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
			$this->get_handover_date()->format( 'Y-m-d' ),
			(string) $this->number_of_days,
			implode( ',', array_map( static fn( DeliveryWindowService $service ): string => $service->value, $this->build_services() ) ),
			implode( ',', $this->get_dropoff_days() ),
			$this->is_evening_enabled() ? 'e1' : 'e0',
			$this->is_morning_enabled() ? 'm1' : 'm0',
			(string) $this->settings->get_customer_code(),
			(string) $this->settings->get_customer_num(),
		);

		return Cache_Adapter::PREFIX_TIMEFRAME . '_' . md5( implode( '|', $parts ) );
	}

	/**
	 * Map the SDK timeframe collection into the legacy DeliveryOptions shape.
	 *
	 * Available timeframes are grouped by delivery date; each window carries a
	 * single legacy option code ('Daytime', 'Evening', or '08:00-12:00').
	 *
	 * Windows map_option() reports as not offered are dropped, and a date left
	 * without any window is dropped with them: an entry with an empty Timeframe
	 * array would render a delivery date heading above an empty radio group.
	 *
	 * Delivery dates the merchant cannot reach from an enabled drop-off day are
	 * dropped here too — see is_reachable_delivery_date(). The mapped result is
	 * what gets cached (see get_delivery_options), and the drop-off days that
	 * drive this filtering are part of the cache key, so a settings change
	 * recomputes rather than serving a stale mapping.
	 *
	 * @param MultipleServicesTimeframeCollection $collection SDK timeframe collection.
	 *
	 * @return array<int, array{DeliveryDate: string, Timeframe: array<int, array{From: string, To: string, Options: string[]}>}>
	 */
	protected function map_response( MultipleServicesTimeframeCollection $collection ): array {
		// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO properties are camelCase.
		$by_date      = array();
		$dropoff_days = $this->get_dropoff_days();

		foreach ( $collection->filterAvailable()->all() as $timeframe ) {
			if ( null === $timeframe->deliveryDate || null === $timeframe->timeFrame ) {
				continue;
			}

			if ( ! $this->is_reachable_delivery_date( $timeframe->deliveryDate, $dropoff_days ) ) {
				continue;
			}

			$option = $this->map_option( $timeframe );

			// Skipped before the date entry is created, so a date whose every window
			// was dropped never enters the result in the first place.
			if ( null === $option ) {
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
				'Options' => array( $option ),
			);
		}

		return array_values( $by_date );
	}

	/**
	 * Translate a V4 timeframe into the legacy option code the checkout expects.
	 *
	 * Evening and morning windows are only offered when the merchant enabled that
	 * delivery option; otherwise the window is dropped rather than relabelled to
	 * 'Daytime'. Relabelling would put a window on the checkout that the legacy
	 * path never showed: a second, identical 'Daytime' radio which — being
	 * fee-free and first in the list — becomes the preselected default that gets
	 * saved on the order (Frontend\Container::get_default_value()).
	 *
	 * The classification is deliberately separate from the toggles: PostNL returns
	 * late windows under the 'daytime' service and TimeSlot::isEvening() only
	 * checks from >= 17:00, so a 17:00-21:00 daytime window is an evening window
	 * here and must disappear when evening delivery is off. A genuinely plain
	 * window (e.g. 09:00-18:00) stays 'Daytime' whatever the toggles say.
	 *
	 * @param \Postnl\Sdk\Service\Timeframes\Response\Timeframe $timeframe SDK timeframe entry.
	 *
	 * @return string|null Legacy option code, or null when the window is not offered.
	 */
	protected function map_option( $timeframe ): ?string {
		$service = is_string( $timeframe->service ) ? strtolower( $timeframe->service ) : '';
		$slot    = $timeframe->timeFrame;

		if ( DeliveryWindowService::Evening->value === $service || ( null !== $slot && $slot->isEvening() ) ) {
			return $this->is_evening_enabled() ? 'Evening' : null;
		}

		if ( null !== $slot && $slot->isMorning() ) {
			return $this->is_morning_enabled() ? '08:00-12:00' : null;
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
	 * The SDK request accepts a DateTimeInterface and formats it as yyyy-MM-dd
	 * from the object's own timezone, so the date is built in the site timezone
	 * (via now()) and returned as-is rather than pre-formatted to a string.
	 *
	 * @return \DateTimeImmutable
	 */
	protected function get_handover_date(): \DateTimeImmutable {
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

		return $handover;
	}

	/**
	 * Whether a delivery date can be reached from an enabled drop-off day.
	 *
	 * The legacy checkout call sent one CutOffTimes entry per excluded drop-off
	 * day (Legacy\Checkout\Client::get_cutoff_times()) and PostNL withheld every
	 * delivery date whose handover fell on one. The V4 request has no equivalent
	 * field — it carries a single handoverDate — so get_handover_date() can only
	 * place the *first* handover on a valid day; without this filter every later
	 * date PostNL returns ignores drop-off days entirely, and a merchant shipping
	 * Mondays and Thursdays would offer a Wednesday delivery that needs a Tuesday
	 * handover they never do.
	 *
	 * The handover a date implies comes from get_handover_date()'s own model: an
	 * order with transit time t hands over at order + (t - 1) preparation days and
	 * the Transit Time setting promises delivery at order + t, so PostNL delivers
	 * the day after handover and date D is reachable only when D - 1 day is an
	 * enabled drop-off day.
	 *
	 * With every day enabled this is a no-op; with none enabled
	 * get_delivery_options() has already short-circuited before mapping.
	 *
	 * @param string   $date         Delivery date in the d-m-Y format PostNL returns, e.g. '14-07-2026'.
	 * @param string[] $dropoff_days Enabled drop-off weekday keys.
	 *
	 * @return bool
	 */
	private function is_reachable_delivery_date( string $date, array $dropoff_days ): bool {
		$delivery = \DateTimeImmutable::createFromFormat( '!d-m-Y', $date );

		// An unreadable date is kept rather than filtered away: if PostNL ever
		// returns another format, dropping every date would leave the customer an
		// empty delivery-day widget with nothing to explain it.
		if ( false === $delivery ) {
			return true;
		}

		$handover = $delivery->modify( '-1 day' );

		return in_array( self::WEEKDAYS[ (int) $handover->format( 'N' ) ], $dropoff_days, true );
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
	 * Enabled drop-off weekday keys ('mon' … 'sun').
	 *
	 * @return string[]
	 */
	private function get_dropoff_days(): array {
		return $this->settings->get_dropoff_days();
	}

	/**
	 * Whether the merchant disabled every drop-off day.
	 *
	 * @return bool
	 */
	private function all_dropoff_days_disabled(): bool {
		return array() === $this->get_dropoff_days();
	}

	/**
	 * TTL, in seconds, for cached timeframe responses.
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
	 * Whether evening delivery is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_evening_enabled(): bool {
		return (bool) $this->settings->is_evening_delivery_enabled();
	}

	/**
	 * Whether morning delivery is enabled in settings.
	 *
	 * @return bool
	 */
	private function is_morning_enabled(): bool {
		return (bool) $this->settings->is_morning_delivery_enabled();
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
