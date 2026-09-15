<?php
/**
 * Unit tests for Rest_API\Barcode\Key_Validator: the response classifier that
 * decides on the HTTP status code before the body, and the reason → sentence
 * map shared by the save path and the on-blur endpoint.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\Barcode
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\Barcode;

use Brain\Monkey\Functions;
use PostNLWooCommerce\Rest_API\Barcode\Key_Validator;
use PostNLWooCommerce\Tests\UnitTestCase;
use ReflectionMethod;

/**
 * @covers \PostNLWooCommerce\Rest_API\Barcode\Key_Validator
 */
class Key_ValidatorTest extends UnitTestCase {

	/**
	 * Invoke the protected static classifier directly with a stubbed response.
	 *
	 * @param bool                     $is_wp_error Whether the response is a transport error.
	 * @param int                      $code        HTTP status code to report.
	 * @param array<string,mixed>|null $body        Decoded body to report, or null for a non-JSON body.
	 *
	 * @return string
	 */
	private function classify( bool $is_wp_error, int $code, $body ): string {
		Functions\when( 'is_wp_error' )->justReturn( $is_wp_error );
		Functions\when( 'wp_remote_retrieve_response_code' )->justReturn( $code );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- test double, WP not loaded.
		Functions\when( 'wp_remote_retrieve_body' )->justReturn( null === $body ? '' : (string) json_encode( $body ) );

		$method = new ReflectionMethod( Key_Validator::class, 'classify' );
		$method->setAccessible( true );

		// The response payload itself is irrelevant: the stubs above drive the
		// status code and body the classifier reads.
		return (string) $method->invoke( null, array( 'stub' => true ) );
	}

	/**
	 * @testdox classify maps each response shape to the right reason, status code first.
	 * @dataProvider classify_provider
	 *
	 * @param bool                     $is_wp_error Transport failure flag.
	 * @param int                      $code        HTTP status code.
	 * @param array<string,mixed>|null $body        Decoded body.
	 * @param string                   $expected    Expected reason.
	 */
	public function test_classify( bool $is_wp_error, int $code, $body, string $expected ): void {
		$this->assertSame( $expected, $this->classify( $is_wp_error, $code, $body ) );
	}

	/**
	 * The matrix from item 3 of the review. The rows that matter most are the
	 * non-2xx responses carrying an Apigee fault: those used to read as "invalid"
	 * because the body was inspected before the status code.
	 *
	 * @return array<string, array{bool, int, array<string,mixed>|null, string}>
	 */
	public static function classify_provider(): array {
		$fault    = array( 'fault' => array( 'faultstring' => 'Quota exceeded' ) );
		$errors   = array( 'Errors' => array( array( 'Description' => 'Bad key' ) ) );
		$barcode  = array( 'Barcode' => '3SABCD1234567' );

		return array(
			'transport failure'                 => array( true, 0, null, Key_Validator::REASON_UNREACHABLE ),
			'401 unauthorized'                  => array( false, 401, $fault, Key_Validator::REASON_INVALID ),
			'403 forbidden'                     => array( false, 403, null, Key_Validator::REASON_INVALID ),
			'200 with fault body'               => array( false, 200, $fault, Key_Validator::REASON_INVALID ),
			'200 with Errors body'              => array( false, 200, $errors, Key_Validator::REASON_INVALID ),
			'200 with barcode'                  => array( false, 200, $barcode, Key_Validator::REASON_VALID ),
			'200 with no barcode'               => array( false, 200, array( 'foo' => 'bar' ), Key_Validator::REASON_UNREACHABLE ),
			'429 quota with fault body'         => array( false, 429, $fault, Key_Validator::REASON_UNREACHABLE ),
			'503 unavailable with fault body'   => array( false, 503, $fault, Key_Validator::REASON_UNREACHABLE ),
			'500 server error'                  => array( false, 500, null, Key_Validator::REASON_UNREACHABLE ),
			'400 bad request'                   => array( false, 400, null, Key_Validator::REASON_REJECTED ),
			'404 not found'                     => array( false, 404, null, Key_Validator::REASON_REJECTED ),
		);
	}

	/**
	 * @testdox reason_message returns the mandated invalid string and a distinct sentence per reason.
	 */
	public function test_reason_message_is_distinct_per_reason(): void {
		Functions\when( '__' )->returnArg();

		$this->assertSame(
			'The newly entered API key is invalid. Please check the key and enter it again.',
			Key_Validator::reason_message( Key_Validator::REASON_INVALID ),
			'The invalid copy is fixed by the PostNL requirement and must not drift.'
		);

		$missing     = Key_Validator::reason_message( Key_Validator::REASON_MISSING );
		$unreachable = Key_Validator::reason_message( Key_Validator::REASON_UNREACHABLE );
		$rejected    = Key_Validator::reason_message( Key_Validator::REASON_REJECTED );

		$messages = array( $missing, $unreachable, $rejected );
		$this->assertCount( 3, array_unique( $messages ), 'Each reason must read differently so an outage is not called invalid.' );

		// An outage must not tell the merchant to fill in details they already have.
		$this->assertStringContainsStringIgnoringCase( 'reach', $unreachable );
		$this->assertStringContainsStringIgnoringCase( 'customer', $missing );
	}
}
