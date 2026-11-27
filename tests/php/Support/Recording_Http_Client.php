<?php
/**
 * PSR-18 client that records each request and answers with a canned response.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * PSR-18 client that records each request and answers with a canned response.
 *
 * No request reaches the network, so a test can assert on what the SDK would
 * have sent to PostNL.
 */
class Recording_Http_Client implements ClientInterface {

	/**
	 * Decoded JSON bodies of the requests sent, in order.
	 *
	 * @var array<int, array>
	 */
	public array $bodies = array();

	/**
	 * Response body returned for every request.
	 *
	 * @var string
	 */
	private string $response_body;

	/**
	 * Constructor.
	 *
	 * @param string $response_body JSON body returned for every request.
	 */
	public function __construct( string $response_body ) {
		$this->response_body = $response_body;
	}

	/**
	 * Record the request and return the canned response.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->bodies[] = (array) json_decode( (string) $request->getBody(), true );

		return new Response( 200, array( 'Content-Type' => 'application/json' ), $this->response_body );
	}
}
