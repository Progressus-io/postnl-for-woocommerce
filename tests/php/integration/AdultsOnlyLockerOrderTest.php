<?php
/**
 * Integration tests for placing an order with a parcel locker and an 18+ cart.
 *
 * The pickup lookup leaves lockers out for an 18+ cart, but a locker chosen
 * earlier can still arrive with the order: the blocks checkout restores it from
 * sessionStorage, and a classic checkout page opened before the 18+ product was
 * added still posts it. The order must not keep a parcel locker either way.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Product\Single;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Covers the order-time side of the 18+ cart rule, for the blocks and the classic checkout.
 */
class AdultsOnlyLockerOrderTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backups of the options changed by setUp().
	 *
	 * @var array<string, mixed>
	 */
	private $orig_options = array();

	/**
	 * Backup of the $_POST superglobal.
	 *
	 * @var array
	 */
	private $orig_post = array();

	/**
	 * Backup of the $_REQUEST superglobal.
	 *
	 * @var array
	 */
	private $orig_request = array();

	/**
	 * IDs of the products and orders created during a test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Shipping zone created for the test.
	 *
	 * @var \WC_Shipping_Zone|null
	 */
	private $zone;

	/**
	 * Set up an NL store with pickup points, a flat rate and bank transfer.
	 */
	protected function setUp(): void {
		parent::setUp();

		$options = array(
			'woocommerce_default_country'       => 'NL',
			'woocommerce_bacs_settings'         => array(
				'enabled' => 'yes',
				'title'   => 'Bank transfer',
			),
			'woocommerce_enable_guest_checkout' => 'yes',
		);

		foreach ( $options as $option => $value ) {
			$this->orig_options[ $option ] = get_option( $option, null );
			update_option( $option, $value );
		}

		$this->zone = new \WC_Shipping_Zone();
		$this->zone->set_zone_name( 'Adults only locker order NL' );
		$this->zone->add_location( 'NL', 'country' );
		$this->zone->save();
		$this->zone->add_shipping_method( 'flat_rate' );

		WC()->payment_gateways()->init();
		WC()->shipping()->load_shipping_methods();

		if ( null === WC()->cart ) {
			wc_load_cart();
		}
		WC()->cart->empty_cart();

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['environment_mode']           = 'sandbox';
		$settings->settings['enable_pickup_points']       = 'yes';
		$settings->settings['validate_nl_address']        = 'no';
		$settings->settings['reorder_nl_address']         = 'no';
		$settings->settings['supported_shipping_methods'] = array( 'flat_rate' );

		$this->orig_post    = $_POST;
		$this->orig_request = $_REQUEST;

		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );
		// The order emails have nowhere to go in the test container.
		add_filter( 'pre_wp_mail', '__return_false' );
	}

	/**
	 * Restore the global state changed in setUp() and by the checkouts.
	 */
	protected function tearDown(): void {
		remove_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );
		remove_filter( 'pre_wp_mail', '__return_false' );
		remove_filter( 'wp_doing_ajax', '__return_true' );

		$_POST    = $this->orig_post;
		$_REQUEST = $this->orig_request;

		WC()->cart->empty_cart();
		foreach ( $this->post_ids as $post_id ) {
			$order = wc_get_order( $post_id );
			if ( $order ) {
				$order->delete( true );
				continue;
			}
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		if ( $this->zone ) {
			$this->zone->delete();
		}

		Settings::get_instance()->settings = $this->orig_settings;

		foreach ( $this->orig_options as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		$this->orig_options = array();

		wc_clear_notices();

		parent::tearDown();
	}

	/**
	 * Replace wp_die() so the classic checkout's JSON response doesn't end the run.
	 *
	 * @return callable
	 */
	public function get_die_handler(): callable {
		return static function () {
			throw new \Error( 'wp_die' );
		};
	}

	/**
	 * Put a shippable 18+ product in the cart.
	 */
	private function add_adults_only_product_to_cart(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Whisky' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->update_meta_data( Single::ADULTS_ONLY_FIELD, 'yes' );
		$this->post_ids[] = $product->save();

		$this->assertNotFalse( WC()->cart->add_to_cart( $product->get_id() ), 'The product must be in the cart.' );
	}

	/**
	 * The parcel locker chosen before the 18+ product was added, as the dropoff block sends it.
	 *
	 * @return array<string, string>
	 */
	private function locker_extension_data(): array {
		return array(
			'deliveryDay'                 => '',
			'deliveryDayDate'             => '',
			'deliveryDayFrom'             => '',
			'deliveryDayTo'               => '',
			'deliveryDayPrice'            => '',
			'deliveryDayType'             => '',
			'dropoffPoints'               => '-409465',
			'dropoffPointsAddressCompany' => 'Pakketautomaat',
			'dropoffPointsAddress1'       => 'Weena',
			'dropoffPointsAddress2'       => '691',
			'dropoffPointsCity'           => 'ROTTERDAM',
			'dropoffPointsPostcode'       => '3013AM',
			'dropoffPointsCountry'        => 'NL',
			'dropoffPointsPartnerID'      => '',
			'dropoffPointsDate'           => '27-11-2026',
			'dropoffPointsTime'           => '',
			'dropoffPointsType'           => 'ParcelMachine',
		);
	}

	/**
	 * The customer's address.
	 *
	 * @return array<string, string>
	 */
	private function address(): array {
		return array(
			'first_name' => 'Jan',
			'last_name'  => 'Jansen',
			'address_1'  => 'Siriusdreef 3',
			'city'       => 'Hoofddorp',
			'postcode'   => '2132WT',
			'country'    => 'NL',
			'email'      => 'jan@example.com',
			'phone'      => '0612345678',
		);
	}

	/**
	 * The pickup type saved on an order, or null when the order has no pickup point.
	 *
	 * @param int $order_id Order ID.
	 *
	 * @return string|null
	 */
	private function saved_pickup_type( int $order_id ): ?string {
		$order = wc_get_order( $order_id );
		$this->assertInstanceOf( \WC_Order::class, $order );

		$meta = $order->get_meta( '_postnl_order_metadata' );

		return is_array( $meta ) ? ( $meta['frontend']['dropoff_points_type'] ?? null ) : null;
	}

	/**
	 * @testdox A blocks checkout order with an 18+ product doesn't keep a parcel locker chosen earlier.
	 */
	public function test_blocks_order_with_adults_only_product_keeps_no_parcel_locker(): void {
		$this->add_adults_only_product_to_cart();

		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'billing_address'  => $this->address(),
					'shipping_address' => $this->address(),
					'payment_method'   => 'bacs',
					'extensions'       => array( 'postnl' => $this->locker_extension_data() ),
				)
			)
		);

		$response = rest_do_request( $request );
		$data     = $response->get_data();

		if ( ! empty( $data['order_id'] ) ) {
			$this->post_ids[] = (int) $data['order_id'];
		}

		if ( 200 !== $response->get_status() ) {
			$this->assertGreaterThanOrEqual( 400, $response->get_status(), 'The checkout must refuse the locker, not fail in another way: ' . wp_json_encode( $data ) );
			return;
		}

		$this->assertNotSame( 'ParcelMachine', $this->saved_pickup_type( (int) $data['order_id'] ), 'A parcel locker cannot take an 18+ parcel.' );
	}

	/**
	 * @testdox A classic checkout order with an 18+ product doesn't keep a parcel locker posted from a page opened earlier.
	 */
	public function test_classic_order_with_adults_only_product_keeps_no_parcel_locker(): void {
		$this->add_adults_only_product_to_cart();

		$address = $this->address();
		$_POST   = array(
			'billing_first_name'                       => $address['first_name'],
			'billing_last_name'                        => $address['last_name'],
			'billing_address_1'                        => $address['address_1'],
			'billing_city'                             => $address['city'],
			'billing_postcode'                         => $address['postcode'],
			'billing_country'                          => $address['country'],
			'billing_email'                            => $address['email'],
			'billing_phone'                            => $address['phone'],
			'shipping_method'                          => array( 'flat_rate:' . $this->flat_rate_instance_id() ),
			'payment_method'                           => 'bacs',
			'woocommerce-process-checkout-nonce'       => wp_create_nonce( 'woocommerce-process_checkout' ),
			'postnl_option'                            => 'dropoff_points',
			'postnl_dropoff_points'                    => '-409465',
			'postnl_dropoff_points_type'               => 'ParcelMachine',
			'postnl_dropoff_points_address_company'    => 'Pakketautomaat',
			'postnl_dropoff_points_address_address_1'  => 'Weena',
			'postnl_dropoff_points_address_address_2'  => '691',
			'postnl_dropoff_points_address_city'       => 'ROTTERDAM',
			'postnl_dropoff_points_address_postcode'   => '3013AM',
			'postnl_dropoff_points_address_country'    => 'NL',
			'postnl_dropoff_points_date'               => '27-11-2026',
		);
		$_REQUEST = $_POST;

		add_filter( 'wp_doing_ajax', '__return_true' );

		ob_start();
		try {
			WC()->checkout()->process_checkout();
		} catch ( \Error $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		} finally {
			$output = (string) ob_get_clean();
		}

		$result = json_decode( $output, true );
		$this->assertIsArray( $result, 'The classic checkout must answer with JSON: ' . $output );

		if ( 'success' !== ( $result['result'] ?? '' ) ) {
			$this->assertSame( 'failure', $result['result'] ?? '' );
			return;
		}

		$this->assertMatchesRegularExpression( '/order-received\/(\d+)/', (string) $result['redirect'] );
		preg_match( '/order-received\/(\d+)/', (string) $result['redirect'], $matches );
		$this->post_ids[] = (int) $matches[1];

		$this->assertNotSame( 'ParcelMachine', $this->saved_pickup_type( (int) $matches[1] ), 'A parcel locker cannot take an 18+ parcel.' );
	}

	/**
	 * The flat rate's instance ID in the test zone.
	 *
	 * @return int
	 */
	private function flat_rate_instance_id(): int {
		foreach ( $this->zone->get_shipping_methods() as $method ) {
			return (int) $method->get_instance_id();
		}

		$this->fail( 'The test zone has no flat rate.' );
	}
}
