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

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Cache_Adapter
 */
class Cache_AdapterTest extends UnitTestCase {

	/**
	 * In-memory stand-in for the WP transient store.
	 *
	 * @var array<string, mixed>
	 */
	private array $store = array();

	/**
	 * Wire get/set/delete_transient to the in-memory store so round-trips work.
	 */
	private function with_transient_store(): void {
		$this->store = array();

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
				$existed = isset( $this->store[ $key ] );
				unset( $this->store[ $key ] );
				return $existed;
			}
		);
	}

	// ── Tests ────────────────────────────────────────────────────────────────

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
	 * @testdox The locations prefix is also cacheable
	 */
	public function test_locations_prefix_is_cacheable(): void {
		$this->with_transient_store();
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertTrue( $adapter->set( 'locations_xyz', 'data' ) );
		$this->assertSame( 'data', $adapter->get( 'locations_xyz' ) );
	}

	/**
	 * @testdox An expired or missing entry returns the supplied default
	 */
	public function test_missing_entry_returns_default(): void {
		Functions\when( 'get_transient' )->justReturn( false );
		$adapter = new Cache_Adapter( 'tenant-key' );

		$this->assertNull( $adapter->get( 'timeframe_gone' ) );
		$this->assertSame( 'fallback', $adapter->get( 'timeframe_gone', 'fallback' ) );
		$this->assertFalse( $adapter->has( 'timeframe_gone' ) );
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
	 * @testdox clear() deletes only this adapter's namespaced transients
	 */
	public function test_clear_scopes_deletion_to_namespace(): void {
		global $wpdb;
		$previous = $wpdb ?? null;

		$wpdb          = Mockery::mock();
		$wpdb->options = 'wp_options';
		$wpdb->shouldReceive( 'esc_like' )->andReturnUsing( fn( $s ) => $s );
		$captured = array();
		$wpdb->shouldReceive( 'prepare' )->andReturnUsing(
			function ( $sql, ...$args ) use ( &$captured ) {
				$captured = $args;
				return $sql;
			}
		);
		$wpdb->shouldReceive( 'query' )->once()->andReturn( 2 );

		$result = ( new Cache_Adapter( 'tenant-key' ) )->clear();

		$this->assertTrue( $result );
		$this->assertStringStartsWith( '_transient_postnl_v4_', $captured[0] );
		$this->assertStringStartsWith( '_transient_timeout_postnl_v4_', $captured[1] );

		$wpdb = $previous;
	}
}
