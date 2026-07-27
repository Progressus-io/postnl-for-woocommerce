<?php
/**
 * Unit tests for Rest_API\SDK\Client_Factory.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Postnl\Sdk\Auth\Method\ApiKeyAuth;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\Exception\InvalidArgumentSdkException;
use Postnl\Sdk\Transport\Retry\RetryConfig;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use ReflectionProperty;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Client_Factory
 */
class Client_FactoryTest extends UnitTestCase {

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Build a minimal settings stub with configurable customer credentials.
	 *
	 * @param mixed $customer_num  PostNL customer number; intentionally untyped to mirror Settings.
	 * @param mixed $customer_code PostNL customer code; intentionally untyped to mirror Settings.
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
	 * @testdox build() sends no HTTP request while constructing the client
	 *
	 * ClientBuilder::make() assembles the transport and middleware stack without
	 * sending any request; requests are deferred until a service accessor
	 * (barcodes(), labelling(), etc.) is invoked on the returned client.
	 *
	 * Asserting only that build() returns a client would not prove this — the
	 * sandbox host is reachable and would answer a stray request with a 401
	 * rather than raising. So a PSR-18 spy is injected and the test fails if the
	 * SDK reaches for the network at all.
	 */
	public function test_build_does_not_perform_network_call(): void {
		$http = new class() implements ClientInterface {
			/**
			 * Requests captured during construction; must remain empty.
			 *
			 * @var array<int, string>
			 */
			public array $requests = array();

			public function sendRequest( RequestInterface $request ): ResponseInterface {
				$this->requests[] = (string) $request->getUri();
				throw new \RuntimeException( 'Unexpected HTTP request during construction: ' . $request->getUri() );
			}
		};

		$factory = new Http_Spy_Client_Factory( $this->make_settings(), $http );
		$client  = $factory->build( 'fake-key-no-network', true );

		$this->assertInstanceOf( PostnlClientInterface::class, $client );
		$this->assertSame(
			array(),
			$http->requests,
			'build() must not send any HTTP request while constructing the SDK client.'
		);
	}

	/**
	 * @testdox An empty API key is rejected by the SDK rather than yielding an unusable client
	 *
	 * Pins the contract documented by build()'s @throws tag. Callers wiring this into
	 * checkout must gate on a non-empty key or surface this exception deliberately.
	 */
	public function test_empty_api_key_throws(): void {
		$factory = new Client_Factory( $this->make_settings() );

		$this->expectException( InvalidArgumentSdkException::class );
		$factory->build( '   ', false );
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
	 * @testdox The V4 API key passed to build() is the key configured on the SDK builder
	 *
	 * Without this assertion every other test still passes when the key argument is
	 * dropped and a literal is hardcoded into Auth::apiKey(), because the memo tests
	 * key off build()'s arguments rather than what actually reaches the builder.
	 */
	public function test_api_key_is_forwarded_to_auth(): void {
		$spy = new Spy_Client_Factory( $this->make_settings() );
		$spy->build( 'fake-v4-key-abc123', false );

		$auth = $this->builder_prop( $spy->captured_builder, 'auth' );

		$this->assertInstanceOf( ApiKeyAuth::class, $auth );
		$this->assertSame( 'fake-v4-key-abc123', $auth->reveal() );
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

		$factory = new Client_Factory( $settings );
		$a       = $factory->build( 'k', false );

		$settings->num = '22222222';

		$b = $factory->build( 'k', false );

		$this->assertNotSame( $a, $b );
	}

	/**
	 * @testdox Customer credentials containing the key delimiter do not collide onto one client
	 *
	 * Joining the raw values with '|' let the delimiter shift the segment boundary, so
	 * ( '1|2', '3' ) and ( '1', '2|3' ) hashed to the same key and the second config was
	 * silently served the first config's client. Customer values are country-scoped, so
	 * they can differ within a single request.
	 */
	public function test_customer_credentials_containing_delimiter_do_not_collide(): void {
		$settings = new class {
			public string $num  = '1|2';
			public string $code = '3';
			public function get_customer_num() {
				return $this->num;
			}
			public function get_customer_code() {
				return $this->code;
			}
		};

		$factory = new Client_Factory( $settings );
		$a       = $factory->build( 'same-key', false );

		// Same joined string, different decomposition.
		$settings->num  = '1';
		$settings->code = '2|3';

		$b = $factory->build( 'same-key', false );

		$this->assertNotSame( $a, $b, 'Distinct customer credentials must not share a memoized client.' );
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
		$path   = ABSPATH . 'src/Rest_API/SDK/Client_Factory.php';
		$source = (string) file_get_contents( $path );

		// Without this the guard rots silently: file_get_contents() on a moved or renamed
		// file yields '', and every assertion below then passes against an empty string.
		$this->assertNotEmpty( $source, "Could not read {$path} — the MessageID guard would pass vacuously." );

		// Case-insensitive, so it also covers 'MessageID' and 'messageid'.
		$this->assertStringNotContainsStringIgnoringCase( 'messageid', $source );
		$this->assertStringNotContainsString( 'message_id', $source );
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

/**
 * Spy subclass that pins a PSR-18 client onto the builder.
 *
 * Lets a test observe whether the SDK reaches for the network while the client
 * is being constructed, without contacting a real host.
 */
class Http_Spy_Client_Factory extends Client_Factory {

	/**
	 * PSR-18 client to hand the SDK.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http;

	/**
	 * Constructor.
	 *
	 * @param object          $settings Settings stub.
	 * @param ClientInterface $http     PSR-18 spy client.
	 */
	public function __construct( object $settings, ClientInterface $http ) {
		parent::__construct( $settings );
		$this->http = $http;
	}

	/**
	 * Attach the spy HTTP client to the configured builder.
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
		return parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )
			->withHttpClient( $this->http );
	}
}
