<?php
/**
 * PSR-18 client that always answers with a PostNL problem+json error.
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
 * PSR-18 client that always answers with a PostNL problem+json error.
 *
 * 401 is chosen because the SDK's retry policy treats it as permanent, so the
 * failure surfaces on the first attempt with no backoff sleeps in the test. The
 * outgoing request is captured first, so a test can assert on what reached the
 * wire even though the call then fails.
 */
class Failing_Http_Client implements ClientInterface {

	/**
	 * The most recent outgoing request, captured for header and body assertions.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Return the canned error response.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return new Response(
			401,
			array( 'Content-Type' => 'application/problem+json' ),
			(string) json_encode(
				array(
					'title'   => 'Unauthorized',
					'detail'  => 'apiKey header missing or invalid',
					'traceId' => 'trace-abc',
				)
			)
		);
	}
}
