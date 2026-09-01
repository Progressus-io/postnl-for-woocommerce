<?php
/**
 * Unit tests for Rest_API\SDK\Exception_Converter.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Brain\Monkey\Functions;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Cache\Exceptions\CacheException;
use Postnl\Sdk\Enums\AuthFailureReason;
use Postnl\Sdk\Enums\HttpStatus;
use Postnl\Sdk\Enums\TransportFailureReason;
use Postnl\Sdk\Exception\Auth\AuthException;
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\ClientException;
use Postnl\Sdk\Exception\Client\RateLimitException;
use Postnl\Sdk\Exception\Client\TimeoutException;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\Data\FieldError;
use Postnl\Sdk\Exception\Data\ProblemDetails;
use Postnl\Sdk\Exception\ExceptionNormalizer;
use Postnl\Sdk\Exception\HttpSdkException;
use Postnl\Sdk\Exception\InvalidArgumentSdkException;
use Postnl\Sdk\Exception\PayloadMappingException;
use Postnl\Sdk\Exception\PostnlSdkException;
use Postnl\Sdk\Exception\Retry\RetryExhaustedException;
use Postnl\Sdk\Exception\RuntimeSdkException;
use Postnl\Sdk\Exception\SchemaMismatchException;
use Postnl\Sdk\Exception\Server\ServerException;
use Postnl\Sdk\Exception\Transport\TransportException;
use Postnl\Sdk\Exception\UnknownExtensionException;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Exception_Converter
 */
class Exception_ConverterTest extends UnitTestCase {

	/**
	 * The message the converter substitutes when the failure describes plugin internals.
	 */
	private const INTERNAL_ERROR_MESSAGE = 'An unexpected error occurred while contacting PostNL. Check the PostNL logs for details.';

	/**
	 * Pass the converter's own translated strings through verbatim.
	 */
	protected function setUp(): void {
		parent::setUp();
		Functions\when( '__' )->returnArg( 1 );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * A throwaway PSR-7 request for HTTP/transport exception fixtures.
	 *
	 * @return RequestInterface
	 */
	private function request(): RequestInterface {
		return new Request( 'POST', 'https://api.postnl.nl/v4/shipment' );
	}

	/**
	 * A PSR-7 response with the given status for HTTP exception fixtures.
	 *
	 * @param int $status HTTP status code.
	 * @return ResponseInterface
	 */
	private function response( int $status ): ResponseInterface {
		return new Response( $status );
	}

	/**
	 * Build a ProblemDetails DTO with only the fields the converter reads.
	 *
	 * @param string|null      $detail       Human-readable detail message.
	 * @param string|null      $trace_id     PostNL correlation id.
	 * @param list<FieldError> $field_errors Structured validation errors.
	 * @return ProblemDetails
	 */
	private function problem( ?string $detail = null, ?string $trace_id = null, array $field_errors = array() ): ProblemDetails {
		return new ProblemDetails(
			type: null,
			title: null,
			status: null,
			detail: $detail,
			instance: null,
			traceId: $trace_id,
			fieldErrors: $field_errors,
		);
	}

	// ── Authentication ─────────────────────────────────────────────────────────

	/**
	 * @dataProvider auth_status_provider
	 * @testdox HTTP 401/403 becomes the generic invalid-credentials message with the status preserved
	 *
	 * @param int        $status_code Raw HTTP status.
	 * @param HttpStatus $status      Matching status enum.
	 */
	public function test_authentication_exception_maps_to_credentials_message( int $status_code, HttpStatus $status ): void {
		$sdk = new AuthenticationException(
			'Unauthorized',
			$status,
			$status_code,
			$this->request(),
			$this->response( $status_code ),
			$this->problem( detail: 'Token rejected' )
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'Invalid PostNL API credentials.', $error->getMessage() );
		$this->assertSame( $status_code, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious() );
	}

	/**
	 * @return array<string, array{int, HttpStatus}>
	 */
	public static function auth_status_provider(): array {
		return array(
			'401 unauthorized' => array( 401, HttpStatus::Unauthorized ),
			'403 forbidden'    => array( 403, HttpStatus::Forbidden ),
		);
	}

	/**
	 * @testdox A pre-request AuthException also maps to the credentials message with status 0
	 */
	public function test_pre_request_auth_exception_maps_to_credentials_message(): void {
		$sdk = new AuthException( AuthFailureReason::InvalidCredentials, 'OAuth token acquisition failed' );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'Invalid PostNL API credentials.', $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
	}

	// ── Validation ───────────────────────────────────────────────────────────

	/**
	 * @dataProvider validation_status_provider
	 * @testdox Validation errors (400/422) bubble field-level messages with the traceId appended
	 *
	 * @param int        $status_code Raw HTTP status.
	 * @param HttpStatus $status      Matching status enum.
	 */
	public function test_validation_exception_bubbles_field_errors( int $status_code, HttpStatus $status ): void {
		$sdk = new ValidationException(
			'Bad Request',
			$status,
			$status_code,
			$this->request(),
			$this->response( $status_code ),
			$this->problem(
				trace_id: 'trace-val-1',
				field_errors: array(
					new FieldError( 'postalCode', 'Invalid postal code' ),
					new FieldError( 'houseNumber', 'House number is required' ),
				)
			)
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame(
			'postalCode: Invalid postal code; houseNumber: House number is required (traceId: trace-val-1)',
			$error->getMessage()
		);
		$this->assertSame( $status_code, $error->getCode() );
	}

	/**
	 * @return array<string, array{int, HttpStatus}>
	 */
	public static function validation_status_provider(): array {
		return array(
			'400 bad request'         => array( 400, HttpStatus::BadRequest ),
			'422 unprocessable entity' => array( 422, HttpStatus::UnprocessableEntity ),
		);
	}

	/**
	 * @testdox A validation error with no field errors falls back to the exception's own message
	 */
	public function test_validation_exception_without_field_errors_uses_exception_message(): void {
		// Distinct values so the assertion pins which of the two the converter reads.
		$sdk = new ValidationException(
			'The request body is malformed',
			HttpStatus::BadRequest,
			400,
			$this->request(),
			$this->response( 400 ),
			$this->problem( detail: 'A different ProblemDetails narrative' )
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'The request body is malformed', $error->getMessage() );
		$this->assertSame( 400, $error->getCode() );
	}

	// ── Transient failures ─────────────────────────────────────────────────────

	/**
	 * @testdox A 429 rate-limit error maps to the temporarily-unavailable message
	 */
	public function test_rate_limit_exception_maps_to_temporary_message(): void {
		$sdk = new RateLimitException(
			'Too Many Requests',
			HttpStatus::TooManyRequests,
			429,
			$this->request(),
			$this->response( 429 ),
			$this->problem()
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL is temporarily unavailable. Please try again.', $error->getMessage() );
		$this->assertSame( 429, $error->getCode() );
	}

	/**
	 * @testdox A 408 timeout error maps to the temporarily-unavailable message
	 */
	public function test_timeout_exception_maps_to_temporary_message(): void {
		$sdk = new TimeoutException(
			'Request Timeout',
			HttpStatus::RequestTimeout,
			408,
			$this->request(),
			$this->response( 408 ),
			$this->problem()
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL is temporarily unavailable. Please try again.', $error->getMessage() );
		$this->assertSame( 408, $error->getCode() );
	}

	/**
	 * @testdox A 5xx server error maps to the temporarily-unavailable message with its status preserved
	 */
	public function test_server_exception_maps_to_temporary_message(): void {
		$sdk = new ServerException(
			'Service Unavailable',
			HttpStatus::ServiceUnavailable,
			503,
			$this->request(),
			$this->response( 503 ),
			$this->problem( trace_id: 'trace-503' )
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame(
			'PostNL is temporarily unavailable. Please try again. (traceId: trace-503)',
			$error->getMessage()
		);
		$this->assertSame( 503, $error->getCode() );
	}

	/**
	 * @testdox A network transport failure maps to the temporarily-unavailable message with status 0
	 */
	public function test_transport_exception_maps_to_temporary_message(): void {
		$sdk = new TransportException(
			$this->request(),
			'Connection refused',
			null,
			0,
			TransportFailureReason::ConnectionRefused
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL is temporarily unavailable. Please try again.', $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
	}

	/**
	 * @testdox An exhausted retry chain maps to the temporarily-unavailable message
	 */
	public function test_retry_exhausted_exception_maps_to_temporary_message(): void {
		$sdk = new RetryExhaustedException( $this->request(), 3, $this->response( 503 ) );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL is temporarily unavailable. Please try again.', $error->getMessage() );
		$this->assertSame( 503, $error->getCode() );
	}

	// ── Generic HTTP + non-HTTP ────────────────────────────────────────────────

	/**
	 * @testdox A generic 4xx client error uses the SDK message and preserves the status
	 */
	public function test_generic_client_exception_uses_sdk_message(): void {
		// HttpSdkException::fromResponse() seeds the exception message from ProblemDetails, so mirror that here.
		$sdk = new ClientException(
			'Shipment not found',
			HttpStatus::NotFound,
			404,
			$this->request(),
			$this->response( 404 ),
			$this->problem( detail: 'Shipment not found', trace_id: 'trace-404' )
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'Shipment not found (traceId: trace-404)', $error->getMessage() );
		$this->assertSame( 404, $error->getCode() );
	}

	/**
	 * @testdox An HTTP error with an empty body never leaks the raw "Unknown error" fallback
	 */
	public function test_empty_body_http_error_does_not_leak_unknown_error(): void {
		// A real parsed error from an empty 404 body: the SDK cleans "Unknown error" to the status reason.
		$sdk = HttpSdkException::fromResponse( $this->request(), $this->response( 404 ) );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'Not Found', $error->getMessage() );
		$this->assertSame( 404, $error->getCode() );
	}

	/**
	 * @testdox A schema mismatch is not shown to the merchant, who cannot act on an API contract break
	 */
	public function test_schema_mismatch_exception_is_not_shown_to_the_merchant(): void {
		$sdk = SchemaMismatchException::missingField( 'Barcode', 'code' );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( self::INTERNAL_ERROR_MESSAGE, $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious(), 'The contract break must stay available for logging' );
	}

	/**
	 * @testdox A non-HTTP SDK runtime error is replaced but keeps its code
	 */
	public function test_runtime_sdk_exception_is_not_shown_to_the_merchant(): void {
		$sdk = RuntimeSdkException::create( 'internal failure', 500 );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( self::INTERNAL_ERROR_MESSAGE, $error->getMessage() );
		$this->assertSame( 500, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious() );
	}

	/**
	 * @testdox An SDK exception rooted in InvalidArgumentException (not RuntimeException) is replaced too
	 */
	public function test_invalid_argument_sdk_exception_is_not_shown_to_the_merchant(): void {
		$sdk = InvalidArgumentSdkException::create( 'bad argument', 0 );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( self::INTERNAL_ERROR_MESSAGE, $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious() );
	}

	/**
	 * @testdox convert() always returns a plain \Exception, never an SDK type
	 */
	public function test_convert_returns_plain_exception(): void {
		$sdk = new ClientException(
			'Conflict',
			HttpStatus::Conflict,
			409,
			$this->request(),
			$this->response( 409 ),
			$this->problem( detail: 'Conflict' )
		);

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( \Exception::class, get_class( $error ) );
	}

	// ── Non-retryable server errors ────────────────────────────────────────────

	/**
	 * @dataProvider non_retryable_server_status_provider
	 * @testdox A permanent 5xx keeps PostNL's own message instead of inviting a retry that cannot succeed
	 *
	 * @param int        $status_code Raw HTTP status.
	 * @param HttpStatus $status      Matching status enum.
	 * @param string     $detail      PostNL's human-readable explanation.
	 */
	public function test_non_retryable_server_exception_keeps_postnl_message( int $status_code, HttpStatus $status, string $detail ): void {
		$sdk = new ServerException(
			$detail,
			$status,
			$status_code,
			$this->request(),
			$this->response( $status_code ),
			$this->problem( detail: $detail )
		);

		$this->assertFalse( $sdk->isRetryable(), 'Fixture must be a permanent 5xx or this test proves nothing' );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( $detail, $error->getMessage(), 'A permanent failure must not be reported as temporary' );
		$this->assertSame( $status_code, $error->getCode() );
	}

	/**
	 * 5xx codes the SDK classifies as permanent, where retrying can never succeed.
	 *
	 * @return array<string, array{int, HttpStatus, string}>
	 */
	public static function non_retryable_server_status_provider(): array {
		return array(
			'501 not implemented'                 => array( 501, HttpStatus::NotImplemented, 'Barcode service is not implemented for this customer code' ),
			'511 network authentication required' => array( 511, HttpStatus::NetworkAuthenticationRequired, 'Network authentication is required by the proxy' ),
		);
	}

	/**
	 * @dataProvider retryable_server_status_provider
	 * @testdox A genuinely retryable 5xx still maps to the temporarily-unavailable message
	 *
	 * @param int             $status_code Raw HTTP status.
	 * @param HttpStatus|null $status      Matching status enum, or null for a status the SDK does not know.
	 */
	public function test_retryable_server_exception_still_maps_to_temporary_message( int $status_code, ?HttpStatus $status ): void {
		$sdk = new ServerException(
			'Upstream failure',
			$status,
			$status_code,
			$this->request(),
			$this->response( $status_code ),
			$this->problem()
		);

		$this->assertTrue( $sdk->isRetryable(), 'Fixture must be a retryable 5xx or this test proves nothing' );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL is temporarily unavailable. Please try again.', $error->getMessage() );
		$this->assertSame( $status_code, $error->getCode() );
	}

	/**
	 * 5xx codes worth retrying, including an unknown 5xx which the SDK treats as retryable by default.
	 *
	 * @return array<string, array{int, HttpStatus|null}>
	 */
	public static function retryable_server_status_provider(): array {
		return array(
			'500 internal server error' => array( 500, HttpStatus::InternalServerError ),
			'502 bad gateway'           => array( 502, HttpStatus::BadGateway ),
			'504 gateway timeout'       => array( 504, HttpStatus::GatewayTimeout ),
			'599 unknown 5xx'           => array( 599, null ),
		);
	}

	// ── Internal detail must not reach the merchant ────────────────────────────

	/**
	 * @dataProvider internal_throwable_provider
	 * @testdox Internal SDK and PHP errors are not surfaced to the merchant verbatim
	 *
	 * @param \Throwable $sdk      Throwable carrying detail the merchant must never see.
	 * @param string     $fragment Substring that must not appear in the converted message.
	 */
	public function test_internal_throwable_message_is_not_surfaced( \Throwable $sdk, string $fragment ): void {
		$error = Exception_Converter::convert( $sdk );

		$this->assertStringNotContainsString(
			$fragment,
			$error->getMessage(),
			'Internal detail leaked into a merchant-visible error message'
		);
		$this->assertSame( self::INTERNAL_ERROR_MESSAGE, $error->getMessage() );
		$this->assertSame( $sdk, $error->getPrevious(), 'The original throwable must stay available for logging' );
	}

	/**
	 * Throwables whose own message carries credentials, server paths or SDK internals.
	 *
	 * @return array<string, array{\Throwable, string}>
	 */
	public static function internal_throwable_provider(): array {
		return array(
			'malformed request carrying a credentialed URI' => array( self::malformed_request_exception(), 'CustomerCode' ),
			'PHP engine error carrying a server path'       => array( self::php_engine_error(), 'Exception_ConverterTest.php' ),
			'cache failure carrying connection details'     => array(
				CacheException::connectionFailed( 'redis', 'tcp://10.0.0.5:6379 auth failed for user admin' ),
				'10.0.0.5',
			),
			'payload mapping failure naming a class'        => array(
				PayloadMappingException::forClass(
					'PostNLWooCommerce\\Missing\\Shipment_Request',
					new \ReflectionException( 'Class does not exist' )
				),
				'Shipment_Request',
			),
			'unknown extension quoting developer setup'     => array(
				UnknownExtensionException::forId( 'barcode', array( 'labelling' ) ),
				'extensions()',
			),
		);
	}

	/**
	 * A transport timeout that a non-strict PSR-18 adapter reports as a malformed request, which
	 * ExceptionNormalizer turns into a LogicSdkException whose message embeds the full request URI.
	 *
	 * @return \Throwable
	 */
	private static function malformed_request_exception(): \Throwable {
		$request = new Request(
			'GET',
			'https://api.postnl.nl/shipment/v1_1/barcode?CustomerCode=DEVC&CustomerNumber=11223344&Type=3S'
		);

		try {
			ExceptionNormalizer::throwFromException(
				new RequestException( 'cURL error 28: Operation timed out after 30000 ms', $request )
			);
		} catch ( \Throwable $error ) {
			return $error;
		}

		throw new \LogicException( 'Expected the normalizer to reject a malformed request' );
	}

	/**
	 * A TypeError whose message embeds the absolute path of this file.
	 *
	 * @return \Throwable
	 */
	private static function php_engine_error(): \Throwable {
		$typed = static function ( string $value ): string {
			return $value;
		};

		try {
			$typed( null );
		} catch ( \Throwable $error ) {
			return $error;
		}

		throw new \LogicException( 'Expected the closure to reject a null argument' );
	}

	// ── Remaining SDK exception classes ────────────────────────────────────────

	/**
	 * @testdox An unmapped 4xx status passes the SDK's cleaned reason phrase through
	 */
	public function test_unmapped_client_status_passes_reason_phrase_through(): void {
		$sdk = HttpSdkException::fromResponse( $this->request(), $this->response( 402 ) );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'Payment Required', $error->getMessage() );
		$this->assertSame( 402, $error->getCode() );
	}

	/**
	 * @testdox The SDK's base exception is replaced but keeps its code
	 */
	public function test_base_sdk_exception_is_not_shown_to_the_merchant(): void {
		$sdk = new PostnlSdkException( 'base failure', 42 );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( self::INTERNAL_ERROR_MESSAGE, $error->getMessage() );
		$this->assertSame( 42, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious() );
	}
}
