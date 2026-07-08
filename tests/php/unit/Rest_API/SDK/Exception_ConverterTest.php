<?php
/**
 * Unit tests for Rest_API\SDK\Exception_Converter.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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
use Postnl\Sdk\Exception\Retry\RetryExhaustedException;
use Postnl\Sdk\Exception\RuntimeSdkException;
use Postnl\Sdk\Exception\SchemaMismatchException;
use Postnl\Sdk\Exception\Server\ServerException;
use Postnl\Sdk\Exception\Transport\TransportException;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Exception_Converter
 */
class Exception_ConverterTest extends UnitTestCase {

	protected function setUp(): void {
		parent::setUp();
		// The converter's own fixed strings run through esc_html__(); pass them through verbatim.
		Functions\when( 'esc_html__' )->returnArg( 1 );
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

		$this->assertSame( 'Invalid PostNL API credentials', $error->getMessage() );
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

		$this->assertSame( 'Invalid PostNL API credentials', $error->getMessage() );
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
	 * @testdox A validation error with no field errors falls back to the ProblemDetails message
	 */
	public function test_validation_exception_without_field_errors_uses_problem_message(): void {
		$sdk = new ValidationException(
			'Bad Request',
			HttpStatus::BadRequest,
			400,
			$this->request(),
			$this->response( 400 ),
			$this->problem( detail: 'The request body is malformed' )
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

		$this->assertSame( 'PostNL temporarily unavailable, please try again.', $error->getMessage() );
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

		$this->assertSame( 'PostNL temporarily unavailable, please try again.', $error->getMessage() );
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
			'PostNL temporarily unavailable, please try again. (traceId: trace-503)',
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

		$this->assertSame( 'PostNL temporarily unavailable, please try again.', $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
	}

	/**
	 * @testdox An exhausted retry chain maps to the temporarily-unavailable message
	 */
	public function test_retry_exhausted_exception_maps_to_temporary_message(): void {
		$sdk = new RetryExhaustedException( $this->request(), 3, $this->response( 503 ) );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'PostNL temporarily unavailable, please try again.', $error->getMessage() );
		$this->assertSame( 503, $error->getCode() );
	}

	// ── Generic HTTP + non-HTTP ────────────────────────────────────────────────

	/**
	 * @testdox A generic 4xx client error uses the ProblemDetails message and preserves the status
	 */
	public function test_generic_client_exception_uses_problem_message(): void {
		$sdk = new ClientException(
			'Not Found',
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
	 * @testdox A schema mismatch passes the SDK message through unchanged
	 */
	public function test_schema_mismatch_exception_passes_message_through(): void {
		$sdk = SchemaMismatchException::missingField( 'Barcode', 'code' );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( $sdk->getMessage(), $error->getMessage() );
		$this->assertSame( 0, $error->getCode() );
		$this->assertSame( $sdk, $error->getPrevious() );
	}

	/**
	 * @testdox A non-HTTP SDK runtime error passes its own message and code through
	 */
	public function test_runtime_sdk_exception_passes_message_through(): void {
		$sdk = RuntimeSdkException::create( 'internal failure', 500 );

		$error = Exception_Converter::convert( $sdk );

		$this->assertSame( 'SDK: internal failure', $error->getMessage() );
		$this->assertSame( 500, $error->getCode() );
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
}
