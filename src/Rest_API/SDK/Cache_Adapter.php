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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cache_Adapter
 *
 * WordPress-transient-backed cache for the V4 SDK, implementing the SDK's
 * CacheAdapterInterface (PSR-16 plus isAvailable()). It is purpose-built for
 * the per-checkout-pageload timeframe/locations responses: only keys on the
 * allowlist are stored, so any other endpoint transparently bypasses the cache.
 *
 * Transient keys are namespaced with a hash of the V4 API key so two stores on
 * shared hosting are extremely unlikely to read each other's cached responses.
 *
 * @since   5.9.6
 * @package PostNLWooCommerce\Rest_API\SDK
 */
class Cache_Adapter extends AbstractCacheAdapter {

	/**
	 * Default time-to-live, in seconds, before a cached response expires.
	 * Public so consumers of the postnl_v4_cache_ttl filter (e.g. the V4
	 * Timeframe service) share one source of truth for the default.
	 */
	public const DEFAULT_TTL = 600;

	/**
	 * Raw-key prefixes whose responses may be cached. Anything else bypasses.
	 */
	private const ALLOWED_PREFIXES = array( 'timeframe', 'locations' );

	/**
	 * Cache_Adapter constructor.
	 *
	 * @param string               $v4_key PostNL V4 API key, hashed into the key namespace.
	 * @param LoggerInterface|null $logger Optional PSR-3 logger for cache errors.
	 * @param callable|null        $clock  Optional clock override for tests.
	 */
	public function __construct( string $v4_key = '', ?LoggerInterface $logger = null, ?callable $clock = null ) {
		/**
		 * Filters the TTL, in seconds, for cached V4 timeframe/locations responses.
		 *
		 * @since 5.9.6
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
	 */
	public function has( string $key ): bool {
		$this->validateKey( $key );

		return $this->is_cacheable( $key ) && false !== get_transient( $this->transient_name( $key ) );
	}

	/**
	 * Store a value. Non-allowlisted keys are not cached and return false.
	 *
	 * @param string                $key   Cache key.
	 * @param mixed                 $value Value to cache.
	 * @param DateInterval|int|null $ttl   Lifetime; null/0/negative use the default.
	 * @return bool
	 */
	public function set( string $key, mixed $value, DateInterval|int|null $ttl = null ): bool {
		$this->validateKey( $key );

		if ( ! $this->is_cacheable( $key ) ) {
			return false;
		}

		return set_transient( $this->transient_name( $key ), $value, $this->normalizeTtlSeconds( $ttl ) );
	}

	/**
	 * Delete a cached value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public function delete( string $key ): bool {
		$this->validateKey( $key );

		return delete_transient( $this->transient_name( $key ) );
	}

	/**
	 * Remove every transient in this adapter's namespace.
	 *
	 * There is no WordPress API to delete transients by prefix, so the options
	 * table is queried to find this namespace's transients, then each is removed
	 * via delete_transient() — which clears both the value and timeout rows and
	 * invalidates the option cache (a raw DELETE would leave stale cache entries
	 * readable within the same request). Object-cache-backed transients are not
	 * enumerable by prefix and so are left to expire on their own TTL.
	 *
	 * @return bool
	 */
	public function clear(): bool {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return false;
		}

		$like  = $wpdb->esc_like( '_transient_' . $this->prefix ) . '%';
		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		$success = true;
		foreach ( (array) $names as $option_name ) {
			$transient = substr( (string) $option_name, strlen( '_transient_' ) );
			if ( ! delete_transient( $transient ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * The transient backend is always available in a WordPress runtime.
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
		 * @since 5.9.6
		 *
		 * @param string[] $prefixes Default: timeframe and locations.
		 */
		$allowed = (array) apply_filters( 'postnl_v4_cache_allowed_prefixes', self::ALLOWED_PREFIXES );

		foreach ( $allowed as $prefix ) {
			if ( '' !== $prefix && str_starts_with( $key, (string) $prefix ) ) {
				return true;
			}
		}

		return false;
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
