<?php
/**
 * Class Rest_API\SDK\Exception_Converter file.
 *
 * @package PostNLWooCommerce\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\SDK;

use Postnl\Sdk\Exception\AbstractHttpSdkException;
use Postnl\Sdk\Exception\Auth\AuthExceptionInterface;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\Retry\RetryableExceptionInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Exception_Converter
 *
 * Translates a V4 SDK exception into a plain \Exception carrying a human-readable
 * getMessage(), so it is consumed exactly like the errors
 * Rest_API\Base::check_response_error() throws. Callers only read getMessage(),
 * so the V4 path can be dropped in behind Order\Base and the frontend handlers
 * with no error-handling changes; getCode() additionally carries the HTTP status,
 * which the legacy path leaves at 0.
 *
 * The original SDK exception is preserved as the previous exception so the full
 * cause chain stays available for logging.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\SDK
 */
class Exception_Converter {

	/**
	 * Convert an SDK exception into the plugin's legacy error shape.
	 *
	 * Mapping (most specific first):
	 *  - Authentication failures (HTTP 401/403 and pre-request credential/OAuth
	 *    failures) collapse to a single "Invalid PostNL API credentials." message.
	 *  - Validation errors (400/422) surface the field-level errors PostNL
	 *    reported so the merchant can correct the request.
	 *  - Transient failures (429, 408, retryable 5xx, network, retries exhausted)
	 *    collapse to "PostNL is temporarily unavailable." since a retry may succeed.
	 *  - Any other HTTP error, including a permanent 5xx such as 501, uses the
	 *    description PostNL returned in the response body.
	 *  - Anything else is an SDK-internal or PHP error whose message describes
	 *    plugin internals rather than the merchant's problem, so it is replaced
	 *    with a generic one. The original stays available via getPrevious().
	 *
	 * When available, the PostNL traceId is appended for support correlation.
	 *
	 * @since 6.0.0
	 *
	 * @param \Throwable $exception SDK exception (or any throwable) to convert.
	 * @return \Exception Plugin-shaped error with a preserved status code.
	 */
	public static function convert( \Throwable $exception ): \Exception {
		if ( $exception instanceof AuthExceptionInterface ) {
			return self::to_error( __( 'Invalid PostNL API credentials.', 'postnl-for-woocommerce' ), $exception );
		}

		if ( $exception instanceof ValidationException ) {
			return self::to_error( self::validation_message( $exception ), $exception );
		}

		if ( self::is_transient( $exception ) ) {
			return self::to_error( __( 'PostNL is temporarily unavailable. Please try again.', 'postnl-for-woocommerce' ), $exception );
		}

		if ( $exception instanceof AbstractHttpSdkException ) {
			// PostNL's own description of the failure, already cleaned by the SDK.
			return self::to_error( $exception->getMessage(), $exception );
		}

		return self::to_error(
			__( 'An unexpected error occurred while contacting PostNL. Check the PostNL logs for details.', 'postnl-for-woocommerce' ),
			$exception
		);
	}

	/**
	 * Whether a retry could plausibly succeed.
	 *
	 * RetryableExceptionInterface only declares the capability, so the predicate
	 * has to be asked rather than the type: ServerException implements it for
	 * every 5xx but reports false for permanent ones such as 501. The SDK's own
	 * retry policy gates on isRetryable() for the same reason.
	 *
	 * When the retry ceiling is hit the SDK rethrows the last underlying failure
	 * rather than a dedicated exhaustion type, so a genuinely transient failure
	 * that exhausted its retries still arrives as a retryable exception here.
	 *
	 * @param \Throwable $exception Original SDK exception being converted.
	 * @return bool
	 */
	private static function is_transient( \Throwable $exception ): bool {
		return $exception instanceof RetryableExceptionInterface && $exception->isRetryable();
	}

	/**
	 * Build the converted error, appending the traceId and preserving both the
	 * status code and the original exception as the cause.
	 *
	 * @param string     $message   Human-readable, already-translated message.
	 * @param \Throwable $exception Original SDK exception being converted.
	 * @return \Exception
	 */
	private static function to_error( string $message, \Throwable $exception ): \Exception {
		return new \Exception(
			$message . self::trace_suffix( $exception ),
			self::status_code( $exception ),
			$exception
		);
	}

	/**
	 * Flatten a ValidationException's field errors into "field: message" pairs.
	 *
	 * Falls back to the exception's own (already-cleaned) message when PostNL
	 * returned a 400/422 without any structured field errors.
	 *
	 * @param ValidationException $exception Validation exception to describe.
	 * @return string
	 */
	private static function validation_message( ValidationException $exception ): string {
		$parts = array();

		foreach ( $exception->getFieldErrors() as $field_error ) {
			// FieldError::$field is nullable (error formats without a field reference); default it for display.
			$parts[] = sprintf( '%1$s: %2$s', $field_error->field ?? __( 'request', 'postnl-for-woocommerce' ), $field_error->message );
		}

		if ( empty( $parts ) ) {
			return $exception->getMessage();
		}

		return implode( '; ', $parts );
	}

	/**
	 * The PostNL correlation suffix, present only on HTTP exceptions that carry a traceId.
	 *
	 * @param \Throwable $exception Original SDK exception being converted.
	 * @return string Empty string when no traceId is available.
	 */
	private static function trace_suffix( \Throwable $exception ): string {
		if ( $exception instanceof AbstractHttpSdkException ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
			$trace_id = $exception->problemDetails->traceId;

			if ( null !== $trace_id && '' !== $trace_id ) {
				return sprintf( ' (traceId: %s)', $trace_id );
			}
		}

		return '';
	}

	/**
	 * The status code to preserve on the converted error.
	 *
	 * HTTP exceptions report their status via getCode(); pre-request failures
	 * (auth, transport) report 0, which is preserved as-is.
	 *
	 * @param \Throwable $exception Original SDK exception being converted.
	 * @return int
	 */
	private static function status_code( \Throwable $exception ): int {
		return (int) $exception->getCode();
	}
}
