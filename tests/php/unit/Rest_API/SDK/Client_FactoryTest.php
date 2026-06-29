<?php
/**
 * Unit tests for Rest_API\SDK\Client_Factory.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\Transport\Retry\RetryConfig;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Tests\UnitTestCase;
use ReflectionProperty;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Client_Factory
 */
class Client_FactoryTest extends UnitTestCase {

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Build a minimal settings stub with configurable customer credentials.
	 *
	 * @param string $customer_num  PostNL customer number.
	 * @param string $customer_code PostNL customer code.
	 * @return object
	 */
	private function make_settings( mixed $customer_num = '12345678', mixed $customer_code = 'PBNL' ): object {
		// Getters are intentionally untyped to mirror the real Settings class, so the
		// suite exercises the (string) cast in Client_Factory::build() rather than masking it.
		return new class( $customer_num, $customer_code ) {
			public function __construct(
				private mixed $num,
				private mixed $code,
			) {}

			public function get_customer_num() {
				return $this->num;
			}

			public function get_customer_code() {
				return $this->code;
			}
		};
	}

	/**
	 * Read a private property value from a ClientBuilder instance.
	 *
	 * PHP 8.1+ allows Reflection to access private members without
	 * setAccessible(), so we omit the deprecated call.
	 *
	 * @param ClientBuilder $builder  Builder instance to inspect.
	 * @param string        $property Private property name.
	 * @return mixed
	 */
	private function builder_prop( ClientBuilder $builder, string $property ): mixed {
		$prop = new ReflectionProperty( ClientBuilder::class, $property );
		return $prop->getValue( $builder );
	}

	// ── Tests ────────────────────────────────────────────────────────────────

	/**
	 * @testdox build() returns an instance of PostnlClientInterface
	 */
	public function test_build_returns_postnl_client_interface(): void {
		$factory = new Client_Factory( $this->make_settings() );
		$this->assertInstanceOf( PostnlClientInterface::class, $factory->build( 'fake-key', false ) );
	}

	/**
	 * @testdox build() does not perform a network call during construction
	 *
	 * ClientBuilder::make() assembles the Guzzle HTTP client and middleware
	 * stack without sending any HTTP request.  Requests are deferred until a
	 * service accessor (barcodes(), labelling(), etc.) is invoked on the
	 * returned client.  If construction attempted a real network call it would
	 * throw a connection error on this obviously-unreachable fake key.
	 */
	public function test_build_does_not_perform_network_call(): void {
		$factory = new Client_Factory( $this->make_settings() );
		// Passes because make() is lazy — no network activity at construction.
		$client = $factory->build( 'fake-key-no-network', true );
		$this->assertInstanceOf( PostnlClientInterface::class, $client );
	}

	/**
	 * @testdox The sandbox flag is forwarded to the SDK builder
	 */
	public function test_sandbox_flag_is_configured(): void {
		$spy_sandbox = new Spy_Client_Factory( $this->make_settings() );
		$spy_sandbox->build( 'k', true );
		$this->assertTrue(
			(bool) $this->builder_prop( $spy_sandbox->captured_builder, 'isSandbox' ),
			'Expected isSandbox=true when build() is called with is_sandbox=true.'
		);

		$spy_prod = new Spy_Client_Factory( $this->make_settings() );
		$spy_prod->build( 'k', false );
		$this->assertFalse(
			(bool) $this->builder_prop( $spy_prod->captured_builder, 'isSandbox' ),
			'Expected isSandbox=false when build() is called with is_sandbox=false.'
		);
	}

	/**
	 * @testdox SourceSystem '35' is passed to the SDK builder
	 */
	public function test_source_system_35_is_configured(): void {
		$spy = new Spy_Client_Factory( $this->make_settings() );
		$spy->build( 'k', false );
		$this->assertSame(
			'35',
			$this->builder_prop( $spy->captured_builder, 'sourceSystem' )
		);
	}

	/**
	 * @testdox API version V4 is explicitly pinned on the SDK builder
	 */
	public function test_api_version_v4_is_pinned(): void {
		$spy = new Spy_Client_Factory( $this->make_settings() );
		$spy->build( 'k', false );
		$this->assertSame(
			\Postnl\Sdk\Enums\Version::V4,
			$this->builder_prop( $spy->captured_builder, 'apiVersion' )
		);
	}

	/**
	 * @testdox Customer number and customer code are forwarded to the SDK builder
	 */
	public function test_customer_credentials_are_configured(): void {
		$spy = new Spy_Client_Factory( $this->make_settings( '99999999', 'POSTNL' ) );
		$spy->build( 'k', false );

		$this->assertSame(
			'99999999',
			$this->builder_prop( $spy->captured_builder, 'customerNumber' )
		);
		$this->assertSame(
			'POSTNL',
			$this->builder_prop( $spy->captured_builder, 'customerCode' )
		);
	}

	/**
	 * @testdox Retry behaviour is configured on the SDK builder
	 */
	public function test_retry_config_is_configured(): void {
		$spy = new Spy_Client_Factory( $this->make_settings() );
		$spy->build( 'k', false );
		$this->assertInstanceOf(
			RetryConfig::class,
			$this->builder_prop( $spy->captured_builder, 'retryConfig' ),
			'Expected build() to configure a RetryConfig via withRetry().'
		);
	}

	/**
	 * @testdox Changed customer credentials bypass the memo, proving they are part of the cache key
	 *
	 * Credentials are read from Settings inside build(), so they cannot vary via build()
	 * arguments. A mutable settings stub is the only way to prove the customer segment of
	 * the cache key is load-bearing: if it were dropped, the second call would return the
	 * memoized first instance.
	 */
	public function test_changed_customer_credentials_bypass_memo(): void {
		$settings = new class {
			public mixed $num = '11111111';
			public function get_customer_num() {
				return $this->num;
			}
			public function get_customer_code() {
				return 'PBNL';
			}
		};

		$factory      = new Client_Factory( $settings );
		$a            = $factory->build( 'k', false );
		$settings->num = '22222222';
		$b            = $factory->build( 'k', false );

		$this->assertNotSame( $a, $b );
	}

	/**
	 * @testdox Non-string customer credentials are cast and do not raise a TypeError
	 *
	 * Legacy or corrupted option data can be a non-string. Without the boundary cast in
	 * build(), strict_types would throw a TypeError at make_builder( string ... ).
	 */
	public function test_non_string_customer_credentials_are_cast(): void {
		$factory = new Client_Factory( $this->make_settings( 12345678, null ) );
		$this->assertInstanceOf( PostnlClientInterface::class, $factory->build( 'k', false ) );
	}

	/**
	 * @testdox Repeated build() calls with identical config return the same memoized instance
	 */
	public function test_same_config_returns_memoized_instance(): void {
		$factory = new Client_Factory( $this->make_settings() );
		$a       = $factory->build( 'key-x', false );
		$b       = $factory->build( 'key-x', false );
		$this->assertSame( $a, $b );
	}

	/**
	 * @testdox A different API key returns a distinct client instance
	 */
	public function test_different_key_returns_different_instance(): void {
		$factory = new Client_Factory( $this->make_settings() );
		$a       = $factory->build( 'key-one', false );
		$b       = $factory->build( 'key-two', false );
		$this->assertNotSame( $a, $b );
	}

	/**
	 * @testdox A different sandbox flag returns a distinct client instance
	 */
	public function test_different_sandbox_flag_returns_different_instance(): void {
		$factory = new Client_Factory( $this->make_settings() );
		$a       = $factory->build( 'key', true );
		$b       = $factory->build( 'key', false );
		$this->assertNotSame( $a, $b );
	}

	/**
	 * @testdox Client_Factory does not configure or reference MessageID
	 *
	 * MessageID is a V1-only field.  V4 has no MessageID concept.
	 * This assertion confirms the source file contains no reference to it.
	 */
	public function test_no_message_id_referenced(): void {
		$source = (string) file_get_contents( ABSPATH . 'src/Rest_API/SDK/Client_Factory.php' );

		$this->assertStringNotContainsString( 'MessageID', $source );
		$this->assertStringNotContainsString( 'message_id', $source );
		$this->assertStringNotContainsStringIgnoringCase( 'messageid', $source );
	}
}

// ── Test seam ────────────────────────────────────────────────────────────────

/**
 * Spy subclass that captures the configured ClientBuilder before make() is called.
 *
 * Allows tests to inspect builder property values via ReflectionProperty without
 * exposing any private state on the production class.
 */
class Spy_Client_Factory extends Client_Factory {

	/**
	 * The last builder created by make_builder().
	 *
	 * @var ClientBuilder|null
	 */
	public ?ClientBuilder $captured_builder = null;

	/**
	 * Intercept builder creation, capture it, then delegate to the parent.
	 *
	 * @param string $v4_key          V4 API key.
	 * @param bool   $is_sandbox      Sandbox flag.
	 * @param string $customer_number PostNL customer number.
	 * @param string $customer_code   PostNL customer code.
	 * @return ClientBuilder
	 */
	protected function make_builder(
		string $v4_key,
		bool $is_sandbox,
		string $customer_number,
		string $customer_code
	): ClientBuilder {
		$builder                = parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code );
		$this->captured_builder = $builder;
		return $builder;
	}
}
