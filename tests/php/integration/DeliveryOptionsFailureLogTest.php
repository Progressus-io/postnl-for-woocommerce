<?php
/**
 * Integration tests for logging failed delivery options lookups.
 *
 * PostNL answers an invalid API key with HTTP 401 and a body that only carries
 * a message, and a gateway outage with an HTML or empty 5xx. Both must reach the
 * WooCommerce log, once per checkout type and error within the throttle window.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises the failure log through the classic and blocks delivery options entry points.
 */
class DeliveryOptionsFailureLogTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backup of the store options this test overrides.
	 *
	 * @var array
	 */
	private $orig_options = array();

	/**
	 * Number of HTTP requests sent to the PostNL API during the test.
	 *
	 * @var int
	 */
	private $api_calls = 0;

	/**
	 * Faked PostNL response: status, reason and raw body, or a network error text.
	 *
	 * @var array{code?:int, message?:string, body?:string, error?:string}
	 */
	private $api_response = array();

	/**
	 * Messages written to the WooCommerce log during the test.
	 *
	 * @var array<int, array{level:string, message:string, source:string}>
	 */
	private $logged = array();

	/**
	 * Class of the log handler whose filter pass is recorded.
	 *
	 * @var string|null
	 */
	private $handler_class = null;

	/**
	 * Prepare an NL store, a cart session and a PostNL API that rejects the key.
	 */
	protected function setUp(): void {
		parent::setUp();

		$store_options = array(
			'woocommerce_default_country' => 'NL',
			'woocommerce_store_address'   => 'Hoofdstraat 1',
			'woocommerce_store_city'      => 'Amsterdam',
			'woocommerce_store_postcode'  => '1011 AB',
		);

		foreach ( $store_options as $option => $value ) {
			$this->orig_options[ $option ] = get_option( $option );
			update_option( $option, $value );
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['api_keys']                   = 'test-key';
		$settings->settings['environment_mode']           = 'sandbox';
		$settings->settings['cut_off_time']               = '18:00';
		$settings->settings['enable_delivery_days']       = 'yes';
		$settings->settings['enable_pickup_points']       = 'yes';
		$settings->settings['validate_nl_address']        = 'no';
		$settings->settings['reorder_nl_address']         = 'no';
		$settings->settings['supported_shipping_methods'] = array( 'flat_rate' );
		$settings->settings['enable_logging']             = 'no';

		$this->api_response = array(
			'code'    => 401,
			'message' => 'Unauthorized',
			'body'    => '{"message":"Unauthorized","http_status_code":401}',
		);

		wc_clear_notices();
		$this->clear_lookup_failure_throttle();
		add_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10, 3 );
		add_filter( 'woocommerce_logger_log_message', array( $this, 'capture_log' ), 10, 4 );
	}

	/**
	 * Restore global state.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10 );
		remove_filter( 'woocommerce_logger_log_message', array( $this, 'capture_log' ), 10 );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
		unset( $_REQUEST['post_data'], $_POST['nonce'], $_POST['data'] );

		$this->clear_lookup_failure_throttle();
		Settings::get_instance()->settings = $this->orig_settings;
		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}
		wc_clear_notices();

		parent::tearDown();
	}

	/**
	 * Answer PostNL API requests with the faked response, without reaching the network.
	 *
	 * @param false|array $preempt Short-circuit value.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 *
	 * @return false|array
	 */
	public function fake_postnl_api( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'postnl.nl' ) ) {
			return $preempt;
		}

		++$this->api_calls;

		if ( isset( $this->api_response['error'] ) ) {
			return new \WP_Error( 'http_request_failed', $this->api_response['error'] );
		}

		return array(
			'headers'  => array(),
			'body'     => $this->api_response['body'],
			'response' => array(
				'code'    => $this->api_response['code'],
				'message' => $this->api_response['message'],
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Record WooCommerce log entries and stop them reaching the log handlers.
	 *
	 * WC_Logger applies this filter once per handler, so only the first handler's pass is recorded.
	 * Repeated identical entries stay separate, which the throttle tests rely on.
	 *
	 * @param string      $message Log message.
	 * @param string      $level   Log level.
	 * @param array       $context Log context.
	 * @param object|null $handler Log handler.
	 *
	 * @return null
	 */
	public function capture_log( $message, $level, $context, $handler = null ) {
		$handler_class = is_object( $handler ) ? get_class( $handler ) : '';

		if ( null === $this->handler_class ) {
			$this->handler_class = $handler_class;
		}

		if ( $handler_class === $this->handler_class ) {
			$this->logged[] = array(
				'level'   => $level,
				'message' => $message,
				'source'  => $context['source'] ?? '',
			);
		}

		return null;
	}

	/**
	 * Return the logged delivery options failures.
	 *
	 * @return array<int, array{level:string, message:string, source:string}>
	 */
	private function logged_failures(): array {
		return array_values(
			array_filter(
				$this->logged,
				static function ( $entry ) {
					return false !== strpos( $entry['message'], 'PostNL delivery options lookup failed' );
				}
			)
		);
	}

	/**
	 * Replace the AJAX wp_die() so the test keeps running.
	 *
	 * @return callable
	 */
	public function ajax_die_handler() {
		return static function () {
			throw new \Error( 'wp_die' );
		};
	}

	/**
	 * Run the classic checkout callback for an order review refresh with a complete NL address.
	 */
	private function run_classic(): void {
		$_REQUEST['post_data'] = http_build_query(
			array(
				'billing_first_name' => 'Jan',
				'billing_last_name'  => 'Jansen',
				'billing_country'    => 'NL',
				'billing_postcode'   => '6591 BA',
				'billing_address_1'  => 'Hoofdstraat 1',
				'billing_city'       => 'Teststad',
				'shipping_method'    => array( 'flat_rate:1' ),
			)
		);

		WC()->customer->set_billing_country( 'NL' );
		WC()->customer->set_billing_postcode( '6591 BA' );
		WC()->customer->set_shipping_country( 'NL' );
		WC()->customer->set_shipping_postcode( '6591 BA' );

		ob_start();
		( new Container( false ) )->postnl_fields();
		ob_end_clean();
	}

	/**
	 * Call the blocks AJAX endpoint with a complete NL address and return its decoded JSON response.
	 *
	 * @return array
	 */
	private function run_blocks(): array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );

		$_POST['nonce'] = wp_create_nonce( 'postnl_delivery_day_nonce' );
		$_POST['data']  = array(
			'ship_to_different_address' => '1',
			'shipping_first_name'       => 'Jan',
			'shipping_last_name'        => 'Jansen',
			'shipping_country'          => 'NL',
			'shipping_postcode'         => '6591 BA',
			'shipping_address_1'        => 'Hoofdstraat',
			'shipping_house_number'     => '1',
			'shipping_city'             => 'Teststad',
		);

		$handler  = ( new \ReflectionClass( Extend_Block_Core::class ) )->newInstanceWithoutConstructor();
		$settings = new \ReflectionProperty( Extend_Block_Core::class, 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( $handler, Settings::get_instance() );

		ob_start();
		try {
			$handler->handle_set_checkout_post_data();
		} catch ( \Error $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		} finally {
			$output = ob_get_clean();
		}

		$response = json_decode( $output, true );
		$this->assertIsArray( $response, 'The blocks endpoint must answer with JSON.' );

		return $response;
	}

	/**
	 * @testdox Classic checkout logs a 401 for an invalid API key with its status while plugin logging is off.
	 */
	public function test_classic_logs_invalid_key_with_plugin_logging_off(): void {
		$this->run_classic();

		$failures = $this->logged_failures();

		$this->assertFalse( Settings::get_instance()->is_logging_enabled() );
		$this->assertSame( 1, $this->api_calls );
		$this->assertCount( 1, $failures );
		$this->assertSame( 'error', $failures[0]['level'] );
		$this->assertSame( 'PostNLWooCommerce', $failures[0]['source'] );
		$this->assertStringContainsString( 'on the classic checkout (code 401): Unauthorized', $failures[0]['message'] );
		$this->assertSame( 0, wc_notice_count( 'error' ) );
	}

	/**
	 * @testdox Blocks endpoint logs a 401 for an invalid API key with its status while plugin logging is off.
	 */
	public function test_blocks_logs_invalid_key_with_plugin_logging_off(): void {
		$response = $this->run_blocks();

		$failures = $this->logged_failures();

		$this->assertFalse( Settings::get_instance()->is_logging_enabled() );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['show_container'] );
		$this->assertCount( 1, $failures );
		$this->assertSame( 'error', $failures[0]['level'] );
		$this->assertStringContainsString( 'on the blocks checkout (code 401): Unauthorized', $failures[0]['message'] );
	}

	/**
	 * Gateway errors without a JSON message.
	 *
	 * @return array<string, array{0:int,1:string,2:string}>
	 */
	public static function gateway_errors(): array {
		return array(
			'HTML 503'  => array( 503, 'Service Unavailable', '<html><body><h1>503 Service Temporarily Unavailable</h1></body></html>' ),
			'empty 502' => array( 502, 'Bad Gateway', '' ),
		);
	}

	/**
	 * @testdox Classic checkout logs a gateway error with the HTTP reason and status.
	 *
	 * @dataProvider gateway_errors
	 *
	 * @param int    $code    HTTP status.
	 * @param string $reason  HTTP reason.
	 * @param string $body    Response body.
	 */
	public function test_classic_logs_gateway_error( int $code, string $reason, string $body ): void {
		$this->api_response = array(
			'code'    => $code,
			'message' => $reason,
			'body'    => $body,
		);

		$this->run_classic();

		$failures = $this->logged_failures();

		$this->assertCount( 1, $failures );
		$this->assertStringContainsString( '(code ' . $code . '): ' . $reason, $failures[0]['message'] );
	}

	/**
	 * @testdox The postcode check throws on a 401 with the status as the exception code.
	 */
	public function test_postcode_check_throws_on_invalid_key(): void {
		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Unauthorized' );
		$this->expectExceptionCode( 401 );

		( new Postcode_Check_Service() )->check(
			array(
				'shipping_country'      => 'NL',
				'shipping_postcode'     => '6591 BA',
				'shipping_house_number' => '1',
				'shipping_address_2'    => '',
			)
		);
	}

	/**
	 * @testdox Classic checkout logs a repeated failure once inside the throttle window.
	 */
	public function test_classic_logs_repeated_failure_once(): void {
		$this->run_classic();
		$this->run_classic();
		$this->run_classic();

		$this->assertSame( 3, $this->api_calls );
		$this->assertCount( 1, $this->logged_failures() );
	}

	/**
	 * @testdox Blocks endpoint logs a repeated failure once inside the throttle window.
	 */
	public function test_blocks_logs_repeated_failure_once(): void {
		$this->run_blocks();
		$this->run_blocks();
		$this->run_blocks();

		$this->assertSame( 3, $this->api_calls );
		$this->assertCount( 1, $this->logged_failures() );
	}

	/**
	 * @testdox A network error that only differs in its timing is logged once.
	 */
	public function test_network_error_timing_does_not_bypass_throttle(): void {
		$this->api_response = array( 'error' => 'cURL error 28: Connection timed out after 5 milliseconds' );
		$this->run_blocks();

		$this->api_response = array( 'error' => 'cURL error 28: Connection timed out after 7 milliseconds' );
		$this->run_blocks();

		$failures = $this->logged_failures();

		$this->assertCount( 1, $failures );
		$this->assertStringContainsString( '(code 0): cURL error 28', $failures[0]['message'] );
	}

	/**
	 * @testdox The throttle is kept per checkout type and per error.
	 */
	public function test_throttle_is_per_checkout_and_error(): void {
		$this->run_classic();
		$this->run_blocks();

		$this->api_response = array(
			'code'    => 503,
			'message' => 'Service Unavailable',
			'body'    => '',
		);
		$this->run_blocks();
		$this->run_blocks();

		$messages = array_column( $this->logged_failures(), 'message' );

		$this->assertCount( 3, $messages );
		$this->assertStringContainsString( 'on the classic checkout (code 401)', $messages[0] );
		$this->assertStringContainsString( 'on the blocks checkout (code 401)', $messages[1] );
		$this->assertStringContainsString( 'on the blocks checkout (code 503)', $messages[2] );
	}
}
