<?php
/**
 * Regression tests for the "wrong account address" report on the
 * "Invullen met PostNL" flow (a BE account showing the previously used NL
 * account's address).
 *
 * The autofill payload is cached in the WooCommerce session under
 * POSTNL_SETTINGS_ID . 'user_data'. A new login must drop the previously cached
 * account before processing, otherwise a failed or abandoned attempt leaves the
 * old account's address behind for the checkout autofill to replay.
 *
 * @package PostNLWooCommerce\Tests\Frontend
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Frontend;

use Brain\Monkey\Functions;
use Mockery;
use PostNLWooCommerce\Frontend\Fill_In_With_Postnl_Handler;
use PostNLWooCommerce\Tests\UnitTestCase;
use ReflectionClass;

/**
 * Minimal in-memory stand-in for the WooCommerce session, supporting the
 * get/set/__unset surface the handler touches.
 */
class Fake_WC_Session {

	/**
	 * Stored session values keyed by name.
	 *
	 * @var array<string, mixed>
	 */
	public array $store = array();

	/**
	 * Get a value from the session.
	 *
	 * @param string $key Session key.
	 * @return mixed
	 */
	public function get( string $key ) {
		return $this->store[ $key ] ?? null;
	}

	/**
	 * Set a value in the session.
	 *
	 * @param string $key   Session key.
	 * @param mixed  $value Value to store.
	 * @return void
	 */
	public function set( string $key, $value ): void {
		$this->store[ $key ] = $value;
	}

	/**
	 * Remove a value from the session.
	 *
	 * @param string $key Session key.
	 * @return void
	 */
	public function __unset( string $key ): void {
		unset( $this->store[ $key ] );
	}
}

/**
 * Thrown in place of wp_send_json_success() so the test can capture the payload
 * the handler would have returned to the browser without actually dying.
 */
class Json_Success_Captured extends \Exception {

	/**
	 * Captured payload.
	 *
	 * @var mixed
	 */
	public $payload;

	/**
	 * Constructor.
	 *
	 * @param mixed $payload The payload passed to wp_send_json_success().
	 */
	public function __construct( $payload ) {
		parent::__construct( 'json_success' );
		$this->payload = $payload;
	}
}

/**
 * Thrown in place of wp_send_json_error() so the test can assert the handler
 * reported an error rather than serving cached data.
 */
class Json_Error_Captured extends \Exception {

	/**
	 * Captured payload.
	 *
	 * @var mixed
	 */
	public $payload;

	/**
	 * Constructor.
	 *
	 * @param mixed $payload The payload passed to wp_send_json_error().
	 */
	public function __construct( $payload ) {
		parent::__construct( 'json_error' );
		$this->payload = $payload;
	}
}

/**
 * Covers the session-cache invalidation that prevents a previous PostNL
 * account's address from leaking into a later login.
 *
 * @covers \PostNLWooCommerce\Frontend\Fill_In_With_Postnl_Handler
 */
class Fill_In_With_Postnl_Handler_SessionCacheTest extends UnitTestCase {

	/**
	 * The session key the handler reads and writes.
	 */
	private const USER_DATA_KEY = 'postnl_user_data';

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POSTNL_SETTINGS_ID' ) ) {
			define( 'POSTNL_SETTINGS_ID', 'postnl_' );
		}
	}

	protected function tearDown(): void {
		unset( $_GET['callback'], $_GET['code'], $_REQUEST['nonce'] );
		parent::tearDown();
	}

	/**
	 * Build a handler without running the constructor (which would register
	 * WordPress hooks) and inject a fake logger plus an enabled settings stub.
	 *
	 * @param Fake_WC_Session $session Session double exposed via WC()->session.
	 * @return Fill_In_With_Postnl_Handler
	 */
	private function make_handler( Fake_WC_Session $session ): Fill_In_With_Postnl_Handler {
		$reflection = new ReflectionClass( Fill_In_With_Postnl_Handler::class );
		$handler    = $reflection->newInstanceWithoutConstructor();

		$logger = Mockery::mock();
		$logger->shouldReceive( 'write' )->andReturnNull();

		$settings = Mockery::mock();
		$settings->shouldReceive( 'is_fill_in_with_postnl_enabled' )->andReturnTrue();

		$logger_property = $reflection->getProperty( 'logger' );
		$logger_property->setAccessible( true );
		$logger_property->setValue( $handler, $logger );

		$settings_property = $reflection->getProperty( 'settings' );
		$settings_property->setAccessible( true );
		$settings_property->setValue( $handler, $settings );

		$wc          = new \stdClass();
		$wc->session = $session;
		Functions\when( 'WC' )->justReturn( $wc );

		return $handler;
	}

	/**
	 * Drive maybe_handle_oauth_callback() through a login return whose PKCE
	 * verifier has expired, mirroring the failed BE attempts reported on
	 * staging. The callback bails out before exchanging the code.
	 *
	 * @param Fill_In_With_Postnl_Handler $handler Handler under test.
	 * @return void
	 */
	private function run_failed_callback( Fill_In_With_Postnl_Handler $handler ): void {
		$_GET['callback'] = 'postnl';
		$_GET['code']     = 'be-auth-code';

		Functions\when( 'is_checkout' )->justReturn( true );
		Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Functions\when( 'wp_unslash' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
		Functions\when( 'wc_add_notice' )->justReturn( null );
		Functions\when( 'get_transient' )->justReturn( false ); // Expired verifier -> early return.

		$handler->maybe_handle_oauth_callback();
	}

	/**
	 * Sample address payloads for two different PostNL accounts.
	 *
	 * @param string $country Two-letter country code (NL or BE).
	 * @return array<string, mixed>
	 */
	private function account_data( string $country ): array {
		$accounts = array(
			'NL' => array(
				'person'         => array(
					'givenName'  => 'Jan',
					'familyName' => 'de Vries',
					'email'      => 'jan@example.nl',
				),
				'primaryAddress' => array(
					'streetName'  => 'Kalverstraat',
					'houseNumber' => '1',
					'postalCode'  => '1012NX',
					'cityName'    => 'Amsterdam',
					'countryName' => 'NL',
				),
			),
			'BE' => array(
				'person'         => array(
					'givenName'  => 'Marie',
					'familyName' => 'Janssens',
					'email'      => 'marie@example.be',
				),
				'primaryAddress' => array(
					'streetName'  => 'Meir',
					'houseNumber' => '2',
					'postalCode'  => '2000',
					'cityName'    => 'Antwerpen',
					'countryName' => 'BE',
				),
			),
		);

		return $accounts[ $country ];
	}

	/**
	 * @testdox A new login callback drops the previously cached account before processing.
	 */
	public function test_login_callback_clears_previously_cached_account(): void {
		$session = new Fake_WC_Session();

		// An NL account logged in earlier -> its data is cached.
		$session->set( self::USER_DATA_KEY, $this->account_data( 'NL' ) );

		$handler = $this->make_handler( $session );

		// A BE login is attempted but the callback fails before it can store data.
		$this->run_failed_callback( $handler );

		$this->assertNull(
			$session->get( self::USER_DATA_KEY ),
			'A new login attempt must drop the previous account data before processing.'
		);
	}

	/**
	 * The headline regression: an NL account logs in, then a BE login fails
	 * (mirrors the "Mismatching redirect URI" / expired verifier conditions on
	 * staging). The checkout autofill must NOT serve the stale NL address.
	 *
	 * @testdox After a failed BE login the checkout autofill no longer serves the stale NL address.
	 */
	public function test_failed_be_login_does_not_serve_stale_nl_address(): void {
		$session = new Fake_WC_Session();
		$session->set( self::USER_DATA_KEY, $this->account_data( 'NL' ) );

		$handler = $this->make_handler( $session );

		$this->run_failed_callback( $handler );

		// The checkout autofill request runs next.
		Functions\when( 'wp_send_json_success' )->alias(
			function ( $payload ) {
				throw new Json_Success_Captured( $payload );
			}
		);
		Functions\when( 'wp_send_json_error' )->alias(
			function ( $payload ) {
				throw new Json_Error_Captured( $payload );
			}
		);

		try {
			$handler->handle_postnl_user_info();
			$this->fail( 'Expected a JSON response to be sent.' );
		} catch ( Json_Success_Captured $captured ) {
			$this->fail( 'Stale account data was served to the checkout instead of an error.' );
		} catch ( Json_Error_Captured $captured ) {
			// Expected: nothing is cached, so the handler reports no user data.
			$this->assertTrue( true );
		}
	}
}
