<?php
/**
 * Client_Factory wired to a fake HTTP client.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

use Postnl\Sdk\Client\ClientBuilder;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use Psr\Http\Client\ClientInterface;

/**
 * Client_Factory whose SDK builder is wired to a fake HTTP client so no network
 * call is made, while the production client configuration stays intact.
 *
 * Shared by the unit suite (V4\Label\ServiceTest) and the integration suite
 * (FilterCarryForwardTest), which drives the real V4 label Service::create()
 * against it and asserts on the captured request.
 */
class Spy_Label_Client_Factory extends Client_Factory {

	/**
	 * Fake HTTP client injected into every built SDK client.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http_client;

	/**
	 * Constructor.
	 *
	 * @param object          $settings    Settings stub.
	 * @param ClientInterface $http_client Fake HTTP client.
	 */
	public function __construct( object $settings, ClientInterface $http_client ) {
		parent::__construct( $settings );
		$this->http_client = $http_client;
	}

	/**
	 * Attach the fake HTTP client to the configured builder.
	 *
	 * @param string $v4_key          V4 API key.
	 * @param bool   $is_sandbox      Sandbox flag.
	 * @param string $customer_number PostNL customer number.
	 * @param string $customer_code   PostNL customer code.
	 * @return ClientBuilder
	 */
	protected function make_builder( string $v4_key, bool $is_sandbox, string $customer_number, string $customer_code ): ClientBuilder {
		return parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )
			->withHttpClient( $this->http_client );
	}
}
