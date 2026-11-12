<?php
/**
 * PSR-18 client that sends through Guzzle and keeps the last request.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

use GuzzleHttp\Client;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Real HTTP client for the sandbox suite that records what was sent.
 */
class Recording_Http_Client implements ClientInterface {

	/**
	 * Last request sent.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Send the request and remember it.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return ( new Client( array( 'http_errors' => false ) ) )->send( $request );
	}
}
