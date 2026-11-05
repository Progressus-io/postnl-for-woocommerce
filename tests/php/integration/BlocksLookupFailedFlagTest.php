<?php
/**
 * Integration tests for the lookup_failed flag on the blocks delivery options AJAX response.
 *
 * The blocks checkout answers a failed PostNL lookup with a 200, so the client can
 * only tell a failure from an empty result by this flag. Without it the retry
 * budget is reset instead of spent, and a failed lookup is never retried.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Covers the failure and empty-result responses of handle_set_checkout_post_data().
 */
class BlocksLookupFailedFlagTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * Backup of the woocommerce_default_country option.
	 *
	 * @var mixed
	 */
	private $orig_country = null;

	/**
	 * Backup of the $_POST superglobal.
	 *
	 * @var array
	 */
	private $orig_post = array();

	/**
	 * Set up an NL store on the legacy transport with address validation off.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;
		// No new API key keeps every flow on the legacy transport, whose requests go
		// through the WordPress HTTP API and can be answered by pre_http_request.
		$settings->settings['api_keys_new']         = '';
		$settings->settings['api_keys_sandbox_new'] = '';
		$settings->settings['validate_nl_address']  = 'no';

		$this->orig_post = $_POST;

		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );
	}

	/**
	 * Restore the global state changed in setUp() and by the handler.
	 */
	protected function tearDown(): void {
		remove_all_filters( 'pre_http_request' );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );

		$_POST = $this->orig_post;

		if ( null !== $this->orig_settings ) {
			Settings::get_instance()->settings = $this->orig_settings;
			$this->orig_settings               = null;
		}

		if ( null !== $this->orig_country ) {
			update_option( 'woocommerce_default_country', $this->orig_country );
			$this->orig_country = null;
		}

		if ( null !== WC()->session ) {
			WC()->session->__unset( 'postnl_checkout_post_data' );
		}

		parent::tearDown();
	}

	/**
	 * @testdox A lookup that fails is answered with lookup_failed set and the container hidden.
	 */
	public function test_failed_lookup_is_flagged(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
			}
		);

		$response = $this->call_handler();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Failed to fetch delivery options.', $response['data']['message'] );
		$this->assertTrue( $response['data']['lookup_failed'] ?? false, 'A failed lookup must carry lookup_failed so the client spends its retry budget.' );
		$this->assertFalse( $response['data']['show_container'] );
	}

	/**
	 * @testdox A lookup that succeeds with no options is not flagged as failed.
	 */
	public function test_empty_result_is_not_flagged(): void {
		add_filter(
			'pre_http_request',
			static function () {
				return array(
					'headers'  => array(),
					'body'     => '{}',
					'response' => array(
						'code'    => 200,
						'message' => 'OK',
					),
					'cookies'  => array(),
				);
			}
		);

		$response = $this->call_handler();

		$this->assertTrue( $response['success'] );
		$this->assertSame( 'No delivery or dropoff options available.', $response['data']['message'] );
		$this->assertArrayNotHasKey( 'lookup_failed', $response['data'], 'An empty result is not a failure and must reset the retry budget.' );
		$this->assertFalse( $response['data']['show_container'] );
	}

	/**
	 * Replace wp_die() during the handler call so the test keeps running.
	 *
	 * @return callable
	 */
	public function get_die_handler(): callable {
		return static function () {
			throw new \RuntimeException( 'wp_die' );
		};
	}

	/**
	 * Run the AJAX handler for an NL address and decode its JSON response.
	 *
	 * @return array
	 */
	private function call_handler(): array {
		$_POST = array(
			'nonce' => wp_create_nonce( 'postnl_delivery_day_nonce' ),
			'data'  => array(
				'shipping_country'          => 'NL',
				'shipping_postcode'         => '2132WT',
				'shipping_house_number'     => '3',
				'shipping_address_1'        => 'Siriusdreef',
				'shipping_city'             => 'Hoofddorp',
				'shipping_method'           => 'postnl',
				// The blocks checkout always sends this; without it the shipping fields are copied from billing.
				'ship_to_different_address' => '1',
			),
		);

		// The constructor registers WooCommerce hooks that would leak into other tests.
		$reflection = new \ReflectionClass( Extend_Block_Core::class );
		$handler    = $reflection->newInstanceWithoutConstructor();
		$property   = $reflection->getProperty( 'settings' );
		$property->setAccessible( true );
		$property->setValue( $handler, Settings::get_instance() );

		ob_start();
		try {
			$handler->handle_set_checkout_post_data();
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		}
		$output = (string) ob_get_clean();

		$response = json_decode( $output, true );
		$this->assertIsArray( $response, 'The handler must answer with JSON: ' . $output );

		return $response;
	}
}
