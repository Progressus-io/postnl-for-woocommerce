<?php
/**
 * Integration tests for the incomplete postcode delivery options guard.
 *
 * Covers issue #364: the checkout refreshes while the customer is still typing
 * the postcode, the partial postcode reached the PostNL API, and the API error
 * became a classic checkout error notice or an HTTP 500 on the blocks endpoint.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises the classic and blocks delivery options entry points.
 */
class IncompletePostcodeLookupTest extends IntegrationTestCase {

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
	 * Whether the faked PostNL API answers with an error.
	 *
	 * @var bool
	 */
	private $api_fails = false;

	/**
	 * Prepare an NL store, a cart session and a faked PostNL API.
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

		wc_clear_notices();
		add_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10, 3 );
	}

	/**
	 * Restore global state.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10 );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
		unset( $_REQUEST['post_data'], $_POST['nonce'], $_POST['data'] );

		Settings::get_instance()->settings = $this->orig_settings;
		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}
		wc_clear_notices();

		parent::tearDown();
	}

	/**
	 * Answer PostNL API requests without reaching the network.
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

		$body = $this->api_fails
			? array( 'Errors' => array( array( 'Description' => 'The provided postcode format is invalid' ) ) )
			: array(
				'DeliveryOptions' => array(),
				'PickupOptions'   => array(),
			);

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $this->api_fails ? 400 : 200,
				'message' => $this->api_fails ? 'Bad Request' : 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Replace the AJAX wp_die() so the test keeps running.
	 *
	 * An \Error is thrown because the handler catches \Exception, and a real
	 * wp_die() ends the request instead of reaching that catch.
	 *
	 * @return callable
	 */
	public function ajax_die_handler() {
		return static function () {
			throw new \Error( 'wp_die' );
		};
	}

	/**
	 * Run the classic checkout callback for an order review refresh.
	 *
	 * @param string $country  Billing country.
	 * @param string $postcode Billing postcode.
	 */
	private function run_classic( string $country, string $postcode ): void {
		$_REQUEST['post_data'] = http_build_query(
			array(
				'billing_first_name' => 'Jan',
				'billing_last_name'  => 'Jansen',
				'billing_country'    => $country,
				'billing_postcode'   => $postcode,
				'billing_address_1'  => 'Hoofdstraat 1',
				'billing_city'       => 'Teststad',
				'shipping_method'    => array( 'flat_rate:1' ),
			)
		);

		ob_start();
		( new Container( false ) )->postnl_fields();
		ob_end_clean();
	}

	/**
	 * Call the blocks AJAX endpoint and return its decoded JSON response.
	 *
	 * @param string $country  Shipping country.
	 * @param string $postcode Shipping postcode.
	 *
	 * @return array
	 */
	private function run_blocks( string $country, string $postcode ): array {
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );

		$_POST['nonce'] = wp_create_nonce( 'postnl_delivery_day_nonce' );
		$_POST['data']  = array(
			'ship_to_different_address' => '1',
			'shipping_first_name'       => 'Jan',
			'shipping_last_name'        => 'Jansen',
			'shipping_country'          => $country,
			'shipping_postcode'         => $postcode,
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
	 * Partial postcodes per country.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function incomplete_postcodes(): array {
		return array(
			'NL digits only' => array( 'NL', '6591' ),
			'NL one letter'  => array( 'NL', '6591 B' ),
			'BE 3 digits'    => array( 'BE', '206' ),
		);
	}

	/**
	 * Complete postcodes per country.
	 *
	 * @return array<string, array{0:string,1:string}>
	 */
	public static function complete_postcodes(): array {
		return array(
			'NL with space'    => array( 'NL', '6591 BA' ),
			'NL without space' => array( 'NL', '6591BA' ),
			'BE'               => array( 'BE', '2060' ),
		);
	}

	/**
	 * @testdox Classic checkout skips the lookup for a partial postcode.
	 *
	 * @dataProvider incomplete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_classic_skips_lookup_for_incomplete_postcode( string $country, string $postcode ): void {
		$this->run_classic( $country, $postcode );

		$this->assertSame( 0, $this->api_calls );
		$this->assertSame( 0, wc_notice_count( 'error' ) );
	}

	/**
	 * @testdox Classic checkout runs the lookup for a complete postcode.
	 *
	 * @dataProvider complete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_classic_runs_lookup_for_complete_postcode( string $country, string $postcode ): void {
		$this->run_classic( $country, $postcode );

		$this->assertGreaterThan( 0, $this->api_calls );
	}

	/**
	 * @testdox Classic checkout does not turn a PostNL API error into an error notice.
	 */
	public function test_classic_api_error_adds_no_notice(): void {
		$this->api_fails = true;

		$this->run_classic( 'NL', '6591 BA' );

		$this->assertGreaterThan( 0, $this->api_calls );
		$this->assertSame( 0, wc_notice_count( 'error' ) );
	}

	/**
	 * @testdox Blocks endpoint skips the lookup for a partial postcode.
	 *
	 * @dataProvider incomplete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_blocks_skips_lookup_for_incomplete_postcode( string $country, string $postcode ): void {
		$response = $this->run_blocks( $country, $postcode );

		$this->assertSame( 0, $this->api_calls );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['show_container'] );
	}

	/**
	 * @testdox Blocks endpoint runs the lookup for a complete postcode.
	 *
	 * @dataProvider complete_postcodes
	 *
	 * @param string $country  Country code.
	 * @param string $postcode Postcode.
	 */
	public function test_blocks_runs_lookup_for_complete_postcode( string $country, string $postcode ): void {
		$response = $this->run_blocks( $country, $postcode );

		$this->assertGreaterThan( 0, $this->api_calls );
		$this->assertTrue( $response['success'] );
	}

	/**
	 * @testdox Blocks endpoint answers with a valid response instead of a 500 when the PostNL API errors.
	 */
	public function test_blocks_api_error_returns_valid_response(): void {
		$this->api_fails = true;

		$response = $this->run_blocks( 'NL', '6591 BA' );

		$this->assertGreaterThan( 0, $this->api_calls );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['show_container'] );
		$this->assertSame( array(), $response['data']['delivery_options'] );
	}
}
