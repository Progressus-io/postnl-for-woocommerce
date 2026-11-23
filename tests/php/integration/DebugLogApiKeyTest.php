<?php
/**
 * Integration test for the API key in the plugin's debug log.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * The debug log masks the API key while the request still carries it.
 */
class DebugLogApiKeyTest extends IntegrationTestCase {

	/**
	 * API key used by the test.
	 */
	const API_KEY = 'debuglogtestkey0123456789abcdef';

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Headers of the requests sent to the PostNL API.
	 *
	 * @var array<int, array>
	 */
	private $sent_headers = array();

	/**
	 * Messages passed to the plugin logger, as printed to the log.
	 *
	 * @var string[]
	 */
	private $written = array();

	/**
	 * Turn plugin logging on and fake the PostNL API.
	 */
	protected function setUp(): void {
		parent::setUp();

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['api_keys']         = self::API_KEY;
		$settings->settings['api_keys_sandbox'] = self::API_KEY;
		$settings->settings['environment_mode'] = 'sandbox';
		$settings->settings['enable_logging']   = 'yes';

		add_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10, 3 );
		add_filter( 'postnl_logger_write_message', array( $this, 'capture_write' ), 10, 1 );
		add_filter( 'woocommerce_logger_log_message', '__return_null' );
	}

	/**
	 * Restore global state.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10 );
		remove_filter( 'postnl_logger_write_message', array( $this, 'capture_write' ), 10 );
		remove_filter( 'woocommerce_logger_log_message', '__return_null' );

		Settings::get_instance()->settings = $this->orig_settings;

		parent::tearDown();
	}

	/**
	 * Record the request headers and answer with an empty postcode check result.
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

		$this->sent_headers[] = $args['headers'];

		return array(
			'headers'  => array(),
			'body'     => '[]',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Record what the plugin logger writes.
	 *
	 * @param mixed $message Message to write.
	 *
	 * @return mixed
	 */
	public function capture_write( $message ) {
		$this->written[] = is_scalar( $message ) ? (string) $message : print_r( $message, true ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r

		return $message;
	}

	/**
	 * @testdox The debug log masks the API key and the request still sends it.
	 */
	public function test_debug_log_masks_api_key(): void {
		( new Postcode_Check_Service() )->check(
			array(
				'shipping_postcode'     => '6591 BA',
				'shipping_house_number' => '1',
			)
		);

		$log = implode( "\n", $this->written );

		$this->assertCount( 1, $this->sent_headers );
		$this->assertSame( self::API_KEY, $this->sent_headers[0]['apikey'] );
		$this->assertStringContainsString( '[apikey] => [redacted]', $log );
		$this->assertStringNotContainsString( self::API_KEY, $log );
	}
}
