<?php
/**
 * Class Rest_API\SDK\Client_Factory file.
 *
 * @package PostNLWooCommerce\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\SDK;

use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Client\PostnlClientInterface;
use Postnl\Sdk\Enums\Version;
use Postnl\Sdk\Transport\Retry\RetryConfig;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client_Factory
 *
 * Builds a configured PostNL V4 SDK client from the installed SDK.
 * Clients are memoized per (key-hash, sandbox, customer) combination so
 * checkout does not reconstruct the SDK client on every request within a
 * single PHP request cycle.
 *
 * @since   5.9.9
 * @package PostNLWooCommerce\Rest_API\SDK
 */
class Client_Factory {

	/**
	 * SourceSystem identifier reserved for this plugin.
	 * PostNL confirmed on 2026-05-21 that SourceSystem 35 is reused for V4.
	 */
	private const SOURCE_SYSTEM = '35';

	/**
	 * Plugin settings instance.
	 *
	 * @var object
	 */
	private $settings;

	/**
	 * Memoized SDK client instances keyed by configuration hash.
	 *
	 * Keys use sha1(v4_key)|sandbox|customer_number|customer_code so the raw
	 * API key is never stored in memory as a visible array key.
	 *
	 * @var array<string, PostnlClientInterface>
	 */
	private $memo = array();

	/**
	 * Client_Factory constructor.
	 *
	 * @param object $settings Plugin settings instance.
	 */
	public function __construct( object $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Return a configured SDK client for the given V4 API key and environment.
	 *
	 * Repeated calls with the same arguments return the same memoized instance.
	 *
	 * The raw V4 key is hashed before it is used as the cache key, so it is not
	 * exposed by dumping the memo. The key parameter is additionally marked
	 * SensitiveParameter so PHP redacts it from exception stack traces and
	 * backtraces; the SDK applies the same protection at its own boundary and
	 * redacts the key from logs and var_dump output.
	 *
	 * @param string $v4_key     PostNL V4 API key. Must not be empty.
	 * @param bool   $is_sandbox Whether to target the sandbox environment.
	 * @return PostnlClientInterface
	 * @throws \Postnl\Sdk\Exception\InvalidArgumentSdkException When $v4_key is empty or whitespace-only.
	 */
	public function build(
		#[\SensitiveParameter]
		string $v4_key,
		bool $is_sandbox
	): PostnlClientInterface {
		// Cast at the boundary: the Settings getters are untyped and may return non-string option data,
		// which would TypeError against make_builder( string ... ) under strict_types.
		$customer_number = (string) $this->settings->get_customer_num();
		$customer_code   = (string) $this->settings->get_customer_code();

		// Any future with*() value sourced from settings or request data must be added to this key,
		// or memoization will return a client configured for a different combination.
		//
		// Every variable segment is hashed to a fixed width before being joined. Concatenating the
		// raw values would let a '|' inside one of them shift the boundary, so that ( '1|2', '3' )
		// and ( '1', '2|3' ) produced the same key and collided onto one client. The customer
		// values are country-scoped settings, so they can legitimately differ within a request.
		$cache_key = implode(
			'|',
			array(
				sha1( $v4_key ),
				$is_sandbox ? '1' : '0',
				sha1( $customer_number ),
				sha1( $customer_code ),
			)
		);

		if ( ! isset( $this->memo[ $cache_key ] ) ) {
			$this->memo[ $cache_key ] = $this->make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )->make();
		}

		return $this->memo[ $cache_key ];
	}

	/**
	 * Create and configure a ClientBuilder ready to call make() on.
	 *
	 * Extracted as a protected method so test subclasses can intercept builder
	 * construction and inspect its configuration via reflection without
	 * triggering a real network call.
	 *
	 * @param string $v4_key          PostNL V4 API key.
	 * @param bool   $is_sandbox      Route requests to the sandbox environment.
	 * @param string $customer_number PostNL customer number from settings.
	 * @param string $customer_code   PostNL customer code from settings.
	 * @return ClientBuilder
	 */
	protected function make_builder(
		#[\SensitiveParameter]
		string $v4_key,
		bool $is_sandbox,
		string $customer_number,
		string $customer_code
	): ClientBuilder {
		$builder = new ClientBuilder();

		return $builder
			->withApiVersion( Version::V4 )
			->withAuth( Auth::apiKey( $v4_key ) )
			->withSandbox( $is_sandbox )
			->withSourceSystem( self::SOURCE_SYSTEM )
			->withCustomerCredentials( $customer_number, $customer_code )
			->withRetry( RetryConfig::exponentialBackoff() );
	}
}
