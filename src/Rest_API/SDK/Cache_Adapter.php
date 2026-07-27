<?php
/**
 * Class Rest_API\SDK\Cache_Adapter file.
 *
 * @package PostNLWooCommerce\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\SDK;

use DateInterval;
use Postnl\Sdk\Cache\Adapter\AbstractCacheAdapter;
use Psr\Log\LoggerInterface;
use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Adapter
 *
 * WordPress-transient-backed cache for the V4 SDK, implementing the SDK's
 * CacheAdapterInterface (PSR-16 plus isAvailable()). It is purpose-built for
 * the per-checkout-pageload timeframe/locations responses: only keys whose
 * prefix is on the allowlist are stored, and anything else bypasses.
 *
 * The prefix is a label the caller chooses via the CachingPlugin keyPrefix, not
 * the request URI. Deciding which endpoints may be cached is CachingPlugin's
 * job, through its own allowedEndpoints list.
 *
 * Transient keys are namespaced with a hash of the V4 API key so two stores on
 * shared hosting are extremely unlikely to read each other's cached responses.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\SDK
 */
class Cache_Adapter extends AbstractCacheAdapter {

	/**
	 * Default time-to-live, in seconds, before a cached response expires.
	 * Public so consumers of the postnl_v4_cache_ttl filter (e.g. the V4
	 * Timeframe service) share one source of truth for the default.
	 *
	 * @since 6.0.0
	 * @var int
	 */
	public const DEFAULT_TTL = 600;

	/**
	 * Cacheable key prefix for the timeframe flow. Pass as the CachingPlugin
	 * keyPrefix so the plugin's generated keys clear this adapter's allowlist.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	public const PREFIX_TIMEFRAME = 'timeframe';

	/**
	 * Cacheable key prefix for the pickup-locations flow.
	 *
	 * @since 6.0.0
	 * @var string
	 */
	public const PREFIX_LOCATIONS = 'locations';

	/**
	 * Raw-key prefixes whose responses may be cached. Anything else bypasses.
	 */
	private const ALLOWED_PREFIXES = array( self::PREFIX_TIMEFRAME, self::PREFIX_LOCATIONS );

	/**
	 * Whether a non-allowlisted key has already been reported for this instance.
	 *
	 * @var bool
	 */
	private $bypass_logged = false;

	/**
	 * Cache_Adapter constructor.
	 *
	 * The TTL filter is applied here, so a filter registered after the adapter
	 * is built does not affect it.
	 *
	 * @param string               $v4_key PostNL V4 API key, hashed into the key namespace.
	 * @param LoggerInterface|null $logger Optional PSR-3 logger for cache errors.
	 * @param callable|null        $clock  Optional clock override for tests.
	 */
	public function __construct( string $v4_key, ?LoggerInterface $logger = null, ?callable $clock = null ) {
		/**
		 * Filters the TTL, in seconds, for cached V4 timeframe/locations responses.
		 *
		 * @since 6.0.0
		 *
		 * @param int $ttl Default 600 seconds.
		 */
		$ttl = (int) apply_filters( 'postnl_v4_cache_ttl', self::DEFAULT_TTL );

		$prefix = 'postnl_v4_' . substr( sha1( $v4_key ), 0, 8 ) . '_';

		parent::__construct( $prefix, $ttl > 0 ? $ttl : self::DEFAULT_TTL, $logger, $clock );
	}

	/**
	 * Fetch a cached value, or $default on a miss or non-cacheable key.
	 *
	 * A stored boolean false cannot be told apart from a miss and yields
	 * $default; the cached payloads are timeframe/locations arrays, so this
	 * edge does not arise on the wired path.
	 *
	 * @param string $key     Cache key.
	 * @param mixed  $default Value returned when nothing is cached.
	 * @return mixed
	 * @throws \Postnl\Sdk\Cache\Exceptions\InvalidCacheArgumentException When the key contains a reserved character.
	 */
	public function get( string $key, mixed $default = null ): mixed { // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound -- name is fixed by the PSR-16 CacheInterface.
		$this->validateKey( $key );

		if ( ! $this->is_cacheable( $key ) ) {
			return $default;
		}

		$value = get_transient( $this->transient_name( $key ) );

		return false === $value ? $default : $value;
	}

	/**
	 * Whether a non-expired value is cached for the key.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 * @throws \Postnl\Sdk\Cache\Exceptions\InvalidCacheArgumentException When the key contains a reserved character.
	 */
	public function has( string $key ): bool {
		$this->validateKey( $key );

		return $this->is_cacheable( $key ) && false !== get_transient( $this->transient_name( $key ) );
	}

	/**
	 * Store a value. Non-allowlisted keys are not cached and return false.
	 *
	 * WordPress falls back to update_option(), which reports false when a live
	 * entry is rewritten with an identical value, so a false return does not
	 * always mean the write failed.
	 *
	 * @param string                $key   Cache key.
	 * @param mixed                 $value Value to cache.
	 * @param DateInterval|int|null $ttl   Lifetime; null/0/negative use the default.
	 * @return bool
	 * @throws \Postnl\Sdk\Cache\Exceptions\InvalidCacheArgumentException When the key contains a reserved character.
	 */
	public function set( string $key, mixed $value, DateInterval|int|null $ttl = null ): bool {
		$this->validateKey( $key );

		if ( ! $this->is_cacheable( $key ) ) {
			return false;
		}

		$seconds = $this->normalizeTtlSeconds( $ttl );

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- name is fixed by the SDK's AbstractCacheAdapter.
		$default_seconds = $this->defaultTtl;

		// A zero or negative DateInterval normalizes to 0, which set_transient() reads as
		// "never expires", so fall back to the default rather than cache permanently.
		return set_transient( $this->transient_name( $key ), $value, $seconds > 0 ? $seconds : $default_seconds );
	}

	/**
	 * Delete a cached value.
	 *
	 * An entry that was never stored makes delete_transient() report false,
	 * which PSR-16 reserves for genuine errors, so an absent key is reported
	 * as a successful delete.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 * @throws \Postnl\Sdk\Cache\Exceptions\InvalidCacheArgumentException When the key contains a reserved character.
	 */
	public function delete( string $key ): bool {
		$this->validateKey( $key );

		delete_transient( $this->transient_name( $key ) );

		return true;
	}

	/**
	 * Remove every transient in this adapter's namespace.
	 *
	 * There is no WordPress API to delete transients by prefix, so the options
	 * table is queried to find this namespace's transients, then each is removed
	 * via delete_transient(). That clears both the value and timeout rows and
	 * invalidates the option cache, whereas a raw DELETE would leave stale cache
	 * entries readable within the same request.
	 *
	 * Returns false whenever the namespace cannot be enumerated, rather than
	 * reporting a success that cleared nothing: under a persistent object cache
	 * transients never reach the options table, and a failed lookup query gives
	 * back the same empty result as a namespace with nothing in it.
	 *
	 * @return bool
	 */
	public function clear(): bool {
		global $wpdb;

		if ( wp_using_ext_object_cache() ) {
			return false;
		}

		if ( ! isset( $wpdb ) ) {
			return false;
		}

		$like  = $wpdb->esc_like( '_transient_' . $this->prefix ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		if ( ! empty( $wpdb->last_error ) ) {
			$this->logError( 'clear', new RuntimeException( (string) $wpdb->last_error ) );

			return false;
		}

		foreach ( (array) $names as $option_name ) {
			// A row that expired between the lookup and here is already gone, and
			// delete_transient() cannot tell that apart from a failure, so its
			// return value carries no signal worth acting on.
			delete_transient( substr( (string) $option_name, strlen( '_transient_' ) ) );
		}

		return true;
	}

	/**
	 * Whether the transient API is loaded.
	 *
	 * @return bool
	 */
	public function isAvailable(): bool {
		return function_exists( 'get_transient' );
	}

	/**
	 * Whether the key belongs to an allowlisted, cacheable endpoint.
	 *
	 * @param string $key Raw (un-hashed) cache key.
	 * @return bool
	 */
	private function is_cacheable( string $key ): bool {
		/**
		 * Filters the raw-key prefixes whose V4 responses may be cached.
		 *
		 * @since 6.0.0
		 *
		 * @param string[] $prefixes Default: timeframe and locations.
		 */
		$allowed = (array) apply_filters( 'postnl_v4_cache_allowed_prefixes', self::ALLOWED_PREFIXES );

		foreach ( $allowed as $prefix ) {
			// Cast before the emptiness check: null and false both survive a
			// strict comparison against '' but cast to it, and an empty needle
			// makes str_starts_with() match every key.
			$prefix = (string) $prefix;

			if ( '' !== $prefix && str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		$this->log_bypass( $key, $allowed );

		return false;
	}

	/**
	 * Warn once per instance that a key fell outside the allowlist.
	 *
	 * A CachingPlugin built without a matching keyPrefix caches nothing at all,
	 * which is otherwise indistinguishable from a cold cache. Reporting it once
	 * surfaces the mis-wiring without flooding the log on every request.
	 *
	 * @param string  $key     Rejected cache key.
	 * @param mixed[] $allowed Allowlisted prefixes in effect.
	 * @return void
	 */
	private function log_bypass( string $key, array $allowed ): void {
		if ( $this->bypass_logged || null === $this->logger ) {
			return;
		}

		$this->bypass_logged = true;

		$this->logger->warning(
			sprintf(
				'PostNL V4 cache bypassed: key "%s" matches no allowed prefix (%s). Check the CachingPlugin keyPrefix.',
				substr( $key, 0, 24 ),
				implode( ', ', array_map( 'strval', $allowed ) )
			)
		);
	}

	/**
	 * Build a length-safe, namespaced transient name for a key.
	 *
	 * The raw key is hashed so the option name stays well under WordPress's
	 * 172-character limit, while the plain namespace prefix is preserved so
	 * clear() can match it.
	 *
	 * @param string $key Raw cache key.
	 * @return string
	 */
	private function transient_name( string $key ): string {
		return $this->prefix . md5( $key );
	}
}
