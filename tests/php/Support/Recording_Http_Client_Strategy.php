<?php
/**
 * HTTP discovery strategy that hands the SDK a recording PSR-18 client.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

use Http\Discovery\Strategy\DiscoveryStrategy;
use Psr\Http\Client\ClientInterface;

/**
 * HTTP discovery strategy that hands the SDK a recording PSR-18 client.
 *
 * The checkout builds its own Service_Factory and SDK client, so a test cannot
 * pass a fake client in. The SDK falls back to PSR-18 discovery when it is given
 * no client, which is where this strategy answers.
 */
class Recording_Http_Client_Strategy implements DiscoveryStrategy {

	/**
	 * Client returned to the SDK, or null when the strategy is inactive.
	 *
	 * @var Recording_Http_Client|null
	 */
	public static ?Recording_Http_Client $client = null;

	/**
	 * Offer the recording client for a PSR-18 client lookup.
	 *
	 * @param string $type Interface the discovery is looking for.
	 * @return array<int, array{class: callable}>
	 */
	public static function getCandidates( $type ) {
		if ( ClientInterface::class !== $type || null === self::$client ) {
			return array();
		}

		return array(
			array(
				'class' => static function () {
					return self::$client;
				},
			),
		);
	}
}
