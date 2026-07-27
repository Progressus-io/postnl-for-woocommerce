<?php
/**
 * Unit tests for Rest_API\SDK\Cache_Adapter.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Mockery;
use Postnl\Sdk\Cache\Exceptions\InvalidCacheArgumentException;
use PostNLWooCommerce\Rest_API\SDK\Cache_Adapter;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Cache_Adapter
 */
class Cache_AdapterTest extends UnitTestCase {

	/**
	 * In-memory stand-in for the WP transient store, keyed to a value and the
	 * timestamp it expires at (0 meaning no expiry).
	 *
	 * @var array<string, array{mixed, int}>
	 */
	private array $store = array();

	/**
	 * Current time for the fake store, so expiry can be driven without sleeping.
	 *
	 * @var int
	 */
	private int $now = 1000000;

	/**
	 * The $wpdb global as it stood before a test replaced it.
	 *
	 * @var mixed
	 */
	private $previous_wpdb = null;

	/**
	 * Wire get/set/delete_transient to the in-memory store so round-trips work.
	 *
	 * The store honours the TTL it is handed: WordPress drops an expired
	 * transient on read, so a stub that ignores the TTL cannot exercise the
	 * expire half of the adapter's contract.
	 */
	private function with_transient_store(): void {
		$this->store = array();

		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl = 0 ) {
				$this->store[ $key ] = array( $value, 0 < $ttl ? $this->now + (int) $ttl : 0 );
				return true;
			}
		);
		Functions\when( 'get_transient' )->alias(
			function ( $key ) {
				if ( ! array_key_exists( $key, $this->store ) ) {
					return false;
				}

				list( $value, $expires_at ) = $this->store[ $key ];

				if ( 0 !== $expires_at && $this->now >= $expires_at ) {
					unset( $this->store[ $key ] );
					return false;
				}

				return $value;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			function ( $key ) {
				$existed = isset( $this->store[ $key ] );
				unset( $this->store[ $key ] );
				return $existed;
			}
		);
	}

	/**
	 * Move the fake store's clock forward.
	 *
	 * @param int $seconds Seconds to advance.
	 */
	private function advance_time( int $seconds ): void {
		$this->now += $seconds;
	}

	/**
	 * Restore the $wpdb global after every test.
	 *
	 * The clear() tests replace it with a mock or null. Restoring inside each
	 * test body would be skipped when an assertion throws, leaking the mock
	 * into whatever runs next in the same process.
	 */
	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->previous_wpdb;

		parent::tearDown();
	}

	/**
	 * Swap in a stand-in for the $wpdb global for the duration of a test.
	 *
	 * @param mixed $wpdb Replacement value.
	 */
	private function with_wpdb( $wpdb ): void {
		$this->previous_wpdb = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb']     = $wpdb;
	}

	/**
	 * @testdox An allowlisted key round-trips through the transient store
	 */
	public function test_allowlisted_key_round_trips(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertTrue( $adapter->set( 'timeframe_abc', array( 'slots' => 3 ) ) );
		$this->assertSame( array( 'slots' => 3 ), $adapter->get( 'timeframe_abc' ) );
		$this->assertTrue( $adapter->has( 'timeframe_abc' ) );
		$this->assertTrue( $adapter->delete( 'timeframe_abc' ) );
		$this->assertNull( $adapter->get( 'timeframe_abc' ) );
	}

	/**
	 * @testdox Deleting a key that was never cached reports success
	 */
	public function test_deleting_an_absent_key_reports_success(): void {
		$this->with_transient_store();

		// PSR-16 reserves a false return for an error, and there is nothing
		// wrong with deleting something that is not there.
		$this->assertTrue( ( new Cache_Adapter( 'tenant-key' ) )->delete( 'timeframe_never_stored' ) );
	}

	/**
	 * @testdox The locations prefix is also cacheable
	 */
	public function test_locations_prefix_is_cacheable(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertTrue( $adapter->set( 'locations_xyz', 'data' ) );
		$this->assertSame( 'data', $adapter->get( 'locations_xyz' ) );
	}

	/**
	 * @testdox A missing entry returns the supplied default
	 */
	public function test_missing_entry_returns_default(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertNull( $adapter->get( 'timeframe_gone' ) );
		$this->assertSame( 'fallback', $adapter->get( 'timeframe_gone', 'fallback' ) );
		$this->assertFalse( $adapter->has( 'timeframe_gone' ) );
	}

	/**
	 * @testdox An entry is readable until its TTL elapses and gone afterwards
	 */
	public function test_entry_expires_once_its_ttl_elapses(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$adapter->set( 'timeframe_abc', array( 'slots' => 3 ), 30 );

		$this->advance_time( 29 );
		$this->assertSame( array( 'slots' => 3 ), $adapter->get( 'timeframe_abc' ) );
		$this->assertTrue( $adapter->has( 'timeframe_abc' ) );

		$this->advance_time( 2 );
		$this->assertNull( $adapter->get( 'timeframe_abc' ) );
		$this->assertSame( 'fallback', $adapter->get( 'timeframe_abc', 'fallback' ) );
		$this->assertFalse( $adapter->has( 'timeframe_abc' ) );
	}

	/**
	 * @testdox An entry stored with the default TTL survives to 600 seconds
	 */
	public function test_entry_stored_with_the_default_ttl_expires_at_600_seconds(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$adapter->set( 'timeframe_abc', 'v' );

		$this->advance_time( Cache_Adapter::DEFAULT_TTL - 1 );
		$this->assertSame( 'v', $adapter->get( 'timeframe_abc' ) );

		$this->advance_time( 1 );
		$this->assertNull( $adapter->get( 'timeframe_abc' ) );
	}

	/**
	 * @testdox A non-allowlisted key is not written and reports a bypass
	 */
	public function test_non_allowlisted_key_bypasses_set(): void {
		Functions\expect( 'set_transient' )->never();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertFalse( $adapter->set( 'shipment_label_1', 'pdf' ) );
	}

	/**
	 * @testdox A non-allowlisted key never hits the store on get and returns default
	 */
	public function test_non_allowlisted_key_bypasses_get(): void {
		Functions\expect( 'get_transient' )->never();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertSame( 'default', $adapter->get( 'shipment_label_1', 'default' ) );
		$this->assertFalse( $adapter->has( 'shipment_label_1' ) );
	}

	/**
	 * @testdox The TTL is filterable via postnl_v4_cache_ttl
	 */
	public function test_ttl_is_filterable(): void {
		Filters\expectApplied( 'postnl_v4_cache_ttl' )->once()->andReturn( 99 );

		$captured_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl ) use ( &$captured_ttl ) {
				$captured_ttl = $ttl;
				return true;
			}
		);

		$adapter = new Cache_Adapter( 'tenant-key' );
		$adapter->set( 'timeframe_abc', 'v' );

		$this->assertSame( 99, $captured_ttl );
	}

	/**
	 * @testdox A non-positive filtered TTL falls back to the 600s default
	 */
	public function test_invalid_filtered_ttl_falls_back_to_default(): void {
		Filters\expectApplied( 'postnl_v4_cache_ttl' )->andReturn( 0 );

		$captured_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl ) use ( &$captured_ttl ) {
				$captured_ttl = $ttl;
				return true;
			}
		);

		$adapter = new Cache_Adapter( 'tenant-key' );
		$adapter->set( 'timeframe_abc', 'v' );

		$this->assertSame( 600, $captured_ttl );
	}

	/**
	 * @testdox Two adapters with different V4 keys use different transient names
	 */
	public function test_keys_are_namespaced_per_tenant(): void {
		$names = array();
		Functions\when( 'set_transient' )->alias(
			function ( $key ) use ( &$names ) {
				$names[] = $key;
				return true;
			}
		);

		( new Cache_Adapter( 'tenant-a' ) )->set( 'timeframe_abc', 'v' );
		( new Cache_Adapter( 'tenant-b' ) )->set( 'timeframe_abc', 'v' );

		$this->assertCount( 2, $names );
		$this->assertNotSame( $names[0], $names[1] );
	}

	/**
	 * @testdox An invalid key throws the SDK's InvalidCacheArgumentException
	 */
	public function test_invalid_key_throws(): void {
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->expectException( InvalidCacheArgumentException::class );
		$adapter->get( 'timeframe/bad' );
	}

	/**
	 * @testdox isAvailable() is true in a WordPress runtime
	 */
	public function test_is_available(): void {
		Functions\when( 'get_transient' )->justReturn( false );

		$this->assertTrue( ( new Cache_Adapter( 'tenant-key' ) )->isAvailable() );
	}

	/**
	 * @testdox clear() finds namespaced transients and removes each via delete_transient()
	 */
	public function test_clear_deletes_namespaced_transients(): void {
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );

		$captured_like    = null;
		$wpdb             = Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( fn( $s ) => $s );
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $sql, $like ) use ( &$captured_like ) {
				$captured_like = $like;
				return $sql;
			}
		);
		$wpdb->shouldReceive( 'get_col' )->once()->andReturn(
			array( '_transient_postnl_v4_abc_one', '_transient_postnl_v4_abc_two' )
		);
		$this->with_wpdb( $wpdb );

		$deleted = array();
		Functions\when( 'delete_transient' )->alias(
			function ( $key ) use ( &$deleted ) {
				$deleted[] = $key;
				return true;
			}
		);

		$result = ( new Cache_Adapter( 'tenant-key' ) )->clear();

		$this->assertTrue( $result );
		$this->assertStringStartsWith( '_transient_postnl_v4_', $captured_like );
		// The '_transient_' prefix is stripped so delete_transient() gets the bare key.
		$this->assertSame( array( 'postnl_v4_abc_one', 'postnl_v4_abc_two' ), $deleted );
	}

	/**
	 * @testdox clear() succeeds even when a row expires between the lookup and the delete
	 */
	public function test_clear_succeeds_when_a_row_vanishes_before_deletion(): void {
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );

		$wpdb             = Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = '';
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( fn( $s ) => $s );
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( fn( $sql ) => $sql );
		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array( '_transient_postnl_v4_abc_gone' ) );
		$this->with_wpdb( $wpdb );

		// The row expired in between, so delete_transient() reports false for a
		// transient that is already absent.
		Functions\when( 'delete_transient' )->justReturn( false );

		$this->assertTrue( ( new Cache_Adapter( 'tenant-key' ) )->clear() );
	}

	/**
	 * @testdox clear() returns false when $wpdb is unavailable
	 */
	public function test_clear_returns_false_when_wpdb_unavailable(): void {
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );
		$this->with_wpdb( null );

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key' ) )->clear() );
	}

	/**
	 * @testdox clear() reports failure under a persistent object cache instead of a success that cleared nothing
	 */
	public function test_clear_returns_false_under_persistent_object_cache(): void {
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( true );

		// Transients never reach the options table, so the namespace is not
		// enumerable and no query may be attempted.
		$wpdb = Mockery::mock();
		$wpdb->shouldNotReceive( 'get_col' );
		$this->with_wpdb( $wpdb );

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key' ) )->clear() );
	}

	/**
	 * @testdox clear() reports failure and logs when the lookup query errors
	 */
	public function test_clear_returns_false_and_logs_on_query_error(): void {
		Functions\when( 'wp_using_ext_object_cache' )->justReturn( false );

		$wpdb             = Mockery::mock();
		$wpdb->options    = 'wp_options';
		$wpdb->last_error = 'Table wp_options does not exist';
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( fn( $s ) => $s );
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing( fn( $sql ) => $sql );
		// An errored get_col() yields an empty set, which is indistinguishable
		// from "nothing cached" without consulting last_error.
		$wpdb->shouldReceive( 'get_col' )->once()->andReturn( array() );
		$this->with_wpdb( $wpdb );

		Functions\expect( 'delete_transient' )->never();

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'error' )->once()->with( Mockery::pattern( '/Table wp_options does not exist/' ) );

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key', $logger ) )->clear() );
	}

	/**
	 * @testdox A DateInterval TTL is normalized to seconds before storage
	 */
	public function test_date_interval_ttl_is_normalized_to_seconds(): void {
		$captured_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl ) use ( &$captured_ttl ) {
				$captured_ttl = $ttl;
				return true;
			}
		);

		( new Cache_Adapter( 'tenant-key' ) )->set( 'timeframe_abc', 'v', new \DateInterval( 'PT30S' ) );

		$this->assertSame( 30, $captured_ttl );
	}

	/**
	 * A zero or negative DateInterval normalizes to 0 seconds. WordPress reads
	 * set_transient( $key, $value, 0 ) as "no expiration" and would cache the
	 * response permanently, while the SDK's own adapters treat the same 0 as
	 * already expired. The non-positive TTL must fall back to the default rather
	 * than reach set_transient() as 0.
	 *
	 * @dataProvider non_positive_interval_provider
	 * @testdox A non-positive DateInterval TTL falls back to the default instead of caching forever
	 *
	 * @param \DateInterval $ttl Interval under test.
	 */
	public function test_non_positive_date_interval_ttl_does_not_cache_forever( \DateInterval $ttl ): void {
		$captured_ttl = null;
		Functions\when( 'set_transient' )->alias(
			function ( $key, $value, $ttl ) use ( &$captured_ttl ) {
				$captured_ttl = $ttl;
				return true;
			}
		);

		( new Cache_Adapter( 'tenant-key' ) )->set( 'timeframe_abc', 'v', $ttl );

		$this->assertSame(
			600,
			$captured_ttl,
			'A non-positive TTL must not reach set_transient() as 0, which WordPress stores permanently.'
		);
	}

	/**
	 * Yields the zero and negative intervals that normalize to 0 seconds.
	 *
	 * @return array<string, array{\DateInterval}>
	 */
	public static function non_positive_interval_provider(): array {
		$negative         = new \DateInterval( 'PT10M' );
		$negative->invert = 1;

		return array(
			'zero interval'     => array( new \DateInterval( 'PT0S' ) ),
			'negative interval' => array( $negative ),
		);
	}

	/**
	 * @testdox The SDK's default keyPrefix is not cacheable, so an unwired CachingPlugin caches nothing
	 */
	public function test_sdk_default_key_prefix_is_not_cacheable(): void {
		Functions\expect( 'set_transient' )->never();

		$sdk_default_key = 'sdk_postnl_http_' . hash( 'sha256', 'get|https://api.postnl.nl/v4/timeframe/|' );

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key' ) )->set( $sdk_default_key, 'v' ) );
	}

	/**
	 * @testdox A key built from the exported prefix constants is cacheable
	 */
	public function test_exported_prefix_constants_are_cacheable(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$timeframe = Cache_Adapter::PREFIX_TIMEFRAME . hash( 'sha256', 'timeframe-request' );
		$locations = Cache_Adapter::PREFIX_LOCATIONS . hash( 'sha256', 'locations-request' );

		$this->assertTrue( $adapter->set( $timeframe, 'a' ) );
		$this->assertTrue( $adapter->set( $locations, 'b' ) );
	}

	/**
	 * @testdox A realistic SDK cache key round-trips a cached response
	 */
	public function test_realistic_sdk_key_round_trips_a_cached_response(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		// Key and payload shaped as CachingPlugin builds and stores them.
		$key      = Cache_Adapter::PREFIX_TIMEFRAME . hash( 'sha256', 'post|https://api.postnl.nl/v4/timeframe/calculate|{"cd":"1234"}' );
		$response = array(
			'body'       => '{"Timeframes":[]}',
			'statusCode' => 200,
			'headers'    => array( 'Content-Type' => array( 'application/json' ) ),
		);

		$this->assertTrue( $adapter->set( $key, $response ) );
		$this->assertSame( $response, $adapter->get( $key ) );
		$this->assertTrue( $adapter->has( $key ) );
		$this->assertTrue( $adapter->delete( $key ) );
		$this->assertNull( $adapter->get( $key ) );
	}

	/**
	 * The raw key is hashed into the transient name so the option_name stays
	 * within WordPress's limit. option_name is VARCHAR(191) and transients
	 * carry a companion _transient_timeout_ row, whose 19-character prefix
	 * leaves 172 for the name itself. Dropping the hash in favour of the raw
	 * key would silently truncate long keys and miss on every subsequent read.
	 *
	 * @testdox The transient name is a fixed length however long the key is
	 */
	public function test_transient_name_length_is_bounded_regardless_of_key_length(): void {
		$names = array();
		Functions\when( 'set_transient' )->alias(
			function ( $key ) use ( &$names ) {
				$names[] = $key;
				return true;
			}
		);

		$adapter = new Cache_Adapter( 'tenant-key' );
		$adapter->set( Cache_Adapter::PREFIX_TIMEFRAME . 'a', 'v' );
		$adapter->set( Cache_Adapter::PREFIX_TIMEFRAME . str_repeat( 'a', 5000 ), 'v' );

		$this->assertCount( 2, $names );
		$this->assertSame( strlen( $names[0] ), strlen( $names[1] ) );

		foreach ( $names as $name ) {
			$this->assertLessThanOrEqual( 172, strlen( $name ) );
		}
	}

	/**
	 * @testdox A bypassed key is reported to the logger once per instance
	 */
	public function test_bypass_is_logged_once_per_instance(): void {
		$logger = Mockery::mock( LoggerInterface::class );
		$logger->shouldReceive( 'warning' )->once()->with( Mockery::pattern( '/cache bypassed/' ) );

		$adapter = new Cache_Adapter( 'tenant-key', $logger );

		// Three rejected calls, one warning: a mis-wired plugin must not flood the log.
		$adapter->set( 'shipment_label_1', 'v' );
		$adapter->get( 'shipment_label_1' );
		$adapter->has( 'shipment_label_1' );
	}

	/**
	 * @testdox An allowlisted key is never reported as a bypass
	 */
	public function test_allowlisted_key_is_not_logged_as_bypass(): void {
		$this->with_transient_store();

		$logger = Mockery::mock( LoggerInterface::class );
		$logger->shouldNotReceive( 'warning' );

		( new Cache_Adapter( 'tenant-key', $logger ) )->set( Cache_Adapter::PREFIX_TIMEFRAME . 'abc', 'v' );
	}

	/**
	 * @dataProvider empty_like_prefix_provider
	 * @testdox An empty-like prefix in the allowlist does not make every key cacheable
	 *
	 * @param mixed $prefix Value a site filter may inject alongside a real prefix.
	 */
	public function test_empty_like_allowlist_prefix_does_not_match_everything( $prefix ): void {
		Filters\expectApplied( 'postnl_v4_cache_allowed_prefixes' )->andReturn( array( $prefix ) );
		Functions\expect( 'set_transient' )->never();

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key' ) )->set( 'anything_at_all', 'v' ) );
	}

	/**
	 * Values that cast to an empty string. null and false survive a strict
	 * comparison against '', so a cast-last guard lets them through and an
	 * empty needle then matches every key. A missing get_option() returns
	 * false, which makes this reachable from an ordinary site filter.
	 *
	 * @return array<string, array{mixed}>
	 */
	public static function empty_like_prefix_provider(): array {
		return array(
			'empty string' => array( '' ),
			'null'         => array( null ),
			'false'        => array( false ),
		);
	}

	/**
	 * @testdox A real prefix alongside an empty-like one still gates correctly
	 */
	public function test_empty_like_prefix_beside_a_real_one_does_not_leak(): void {
		Filters\expectApplied( 'postnl_v4_cache_allowed_prefixes' )->andReturn(
			array( Cache_Adapter::PREFIX_TIMEFRAME, null )
		);
		Functions\expect( 'set_transient' )->never();

		$this->assertFalse( ( new Cache_Adapter( 'tenant-key' ) )->set( 'shipment_label_1', 'PDF-BYTES' ) );
	}
}
