<?php
/**
 * Base class for the opt-in PostNL sandbox suite.
 *
 * @package PostNLWooCommerce\Tests\Support
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Support;

use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * Reads the sandbox credentials from the environment, skips when they are
 * missing, and spaces calls so the suite stays under the sandbox limit of
 * 15 calls a minute.
 */
abstract class Sandbox_Test_Case extends UnitTestCase {

	/**
	 * Minimum seconds between two sandbox calls (60 / 15 = 4, plus headroom).
	 */
	private const SECONDS_BETWEEN_CALLS = 4.5;

	/**
	 * Microtime of the previous sandbox call in this run.
	 *
	 * @var float
	 */
	private static float $last_call = 0.0;

	/**
	 * Sandbox API key.
	 *
	 * @var string
	 */
	protected string $api_key = '';

	/**
	 * Sandbox customer number.
	 *
	 * @var string
	 */
	protected string $customer_number = '';

	/**
	 * Sandbox customer code.
	 *
	 * @var string
	 */
	protected string $customer_code = '';

	/**
	 * Load the credentials or skip the test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->api_key         = (string) getenv( 'POSTNL_SANDBOX_API_KEY' );
		$this->customer_number = (string) getenv( 'POSTNL_SANDBOX_CUSTOMER_NUMBER' );
		$this->customer_code   = (string) getenv( 'POSTNL_SANDBOX_CUSTOMER_CODE' );

		if ( '' === $this->api_key || '' === $this->customer_number || '' === $this->customer_code ) {
			$this->markTestSkipped( 'Set POSTNL_SANDBOX_API_KEY, POSTNL_SANDBOX_CUSTOMER_NUMBER and POSTNL_SANDBOX_CUSTOMER_CODE to run the sandbox suite.' );
		}
	}

	/**
	 * Wait until the next sandbox call fits within the rate limit.
	 */
	protected function throttle(): void {
		$wait = self::SECONDS_BETWEEN_CALLS - ( microtime( true ) - self::$last_call );

		if ( $wait > 0 ) {
			usleep( (int) ( $wait * 1000000 ) );
		}

		self::$last_call = microtime( true );
	}
}
