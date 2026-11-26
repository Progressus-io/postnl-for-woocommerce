<?php
/**
 * Integration tests for the location types the V4 pickup lookup requests for a real cart.
 *
 * A parcel locker cannot check an ID, so a cart with an 18+ product is offered
 * retail points only. Each test runs a checkout entry point with a WooCommerce
 * cart and asserts on the locationTypes the SDK would send to PostNL.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use Http\Discovery\Psr18ClientDiscovery;
use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Product\Single;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Tests\Support\Recording_Http_Client;
use PostNLWooCommerce\Tests\Support\Recording_Http_Client_Strategy;

/**
 * Covers the 18+ cart rule through the classic and the blocks checkout.
 */
class AdultsOnlyCartPickupLocationsTest extends IntegrationTestCase {

	/**
	 * V4 sandbox API key the store is configured with.
	 */
	private const V4_KEY = 'v4-test-key';

	/**
	 * Option holding the hash of the validated V4 sandbox key.
	 */
	private const VALIDATED_OPTION = Settings::NEW_API_KEY_VALIDATED_HASH_OPTION . '_sandbox';

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
	 * HTTP discovery strategies before the recording strategy was added.
	 *
	 * @var array
	 */
	private $orig_strategies = array();

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
	 * IDs of the products created during a test.
	 *
	 * @var int[]
	 */
	private $product_ids = array();

	/**
	 * Client that records the V4 pickup lookup.
	 *
	 * @var Recording_Http_Client
	 */
	private $http;

	/**
	 * Set up an NL store with the V4 pickup lookup on and the legacy delivery day lookup faked.
	 */
	protected function setUp(): void {
		parent::setUp();

		$options = array(
			'woocommerce_default_country' => 'NL',
			'woocommerce_store_address'   => 'Prinses Beatrixlaan 23',
			'woocommerce_store_city'      => 'Den Haag',
			'woocommerce_store_postcode'  => '2595AK',
			self::VALIDATED_OPTION        => hash( 'sha256', self::V4_KEY ),
		);

		foreach ( $options as $option => $value ) {
			$this->orig_options[ $option ] = get_option( $option, null );
			update_option( $option, $value );
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}
		WC()->cart->empty_cart();

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		// Sandbox, so a request that escaped the fakes could never reach production.
		$settings->settings['environment_mode']                = 'sandbox';
		$settings->settings['api_keys_sandbox']                = 'legacy-test-key';
		$settings->settings['api_keys_sandbox_new']            = self::V4_KEY;
		$settings->settings['customer_code']                   = 'DEVC';
		$settings->settings['customer_num']                    = '11223344';
		$settings->settings['enable_delivery_days']            = 'yes';
		$settings->settings['enable_pickup_points']            = 'yes';
		$settings->settings['validate_nl_address']             = 'no';
		$settings->settings['reorder_nl_address']              = 'no';
		$settings->settings['cut_off_time']                    = '18:00';
		$settings->settings['transit_time']                    = '1';
		$settings->settings['supported_shipping_methods']      = array( 'flat_rate' );
		$settings->settings['default_shipping_options_pickup'] = '';

		// Only the pickup lookup runs on V4. Delivery days stay on the legacy
		// transport, which goes through the WordPress HTTP API.
		add_filter( 'postnl_sdk_enable_pickup_location', '__return_true' );
		add_filter( 'pre_http_request', array( $this, 'fake_legacy_api' ), 10, 3 );

		// An empty result is not cached, so every lookup reaches the client.
		$this->http                             = new Recording_Http_Client( '{"locations":[]}' );
		Recording_Http_Client_Strategy::$client = $this->http;
		$this->orig_strategies                  = (array) Psr18ClientDiscovery::getStrategies();
		Psr18ClientDiscovery::prependStrategy( Recording_Http_Client_Strategy::class );

		$this->orig_post    = $_POST;
		$this->orig_request = $_REQUEST;

		add_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );
	}

	/**
	 * Restore the global state changed in setUp() and by the checkout entry points.
	 */
	protected function tearDown(): void {
		remove_filter( 'postnl_sdk_enable_pickup_location', '__return_true' );
		remove_filter( 'pre_http_request', array( $this, 'fake_legacy_api' ), 10 );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'get_die_handler' ) );

		Psr18ClientDiscovery::setStrategies( $this->orig_strategies );
		Recording_Http_Client_Strategy::$client = null;

		$_POST    = $this->orig_post;
		$_REQUEST = $this->orig_request;

		WC()->cart->empty_cart();
		foreach ( $this->product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}
		$this->product_ids = array();

		Settings::get_instance()->settings = $this->orig_settings;

		foreach ( $this->orig_options as $option => $value ) {
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
		$this->orig_options = array();

		if ( null !== WC()->session ) {
			WC()->session->__unset( 'postnl_checkout_post_data' );
		}
		wc_clear_notices();

		parent::tearDown();
	}

	/**
	 * Answer the legacy delivery day lookup with an empty result, without reaching the network.
	 *
	 * @param false|array $preempt Short-circuit value.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 *
	 * @return false|array
	 */
	public function fake_legacy_api( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'postnl.nl' ) ) {
			return $preempt;
		}

		return array(
			'headers'  => array(),
			'body'     => '{"DeliveryOptions":[],"PickupOptions":[]}',
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Replace wp_die() during the blocks handler call so the test keeps running.
	 *
	 * @return callable
	 */
	public function get_die_handler(): callable {
		// An \Error, because the handler wraps its success responses in catch ( \Exception ).
		return static function () {
			throw new \Error( 'wp_die' );
		};
	}

	/**
	 * Put one shippable product in the cart.
	 *
	 * @param bool $adults_only Whether the product is marked as 18+.
	 */
	private function add_product_to_cart( bool $adults_only ): void {
		$product = new \WC_Product_Simple();
		$product->set_name( $adults_only ? 'Whisky' : 'Mug' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );

		if ( $adults_only ) {
			$product->update_meta_data( Single::ADULTS_ONLY_FIELD, 'yes' );
		}

		$this->product_ids[] = $product->save();

		$this->assertNotFalse( WC()->cart->add_to_cart( $product->get_id() ), 'The product must be in the cart.' );
	}

	/**
	 * Put one variation of a variable product in the cart.
	 *
	 * @param bool $parent_adults_only    Whether the parent product is marked as 18+.
	 * @param bool $variation_adults_only Whether the variation itself is marked as 18+.
	 */
	private function add_variation_to_cart( bool $parent_adults_only, bool $variation_adults_only ): void {
		$attribute = new \WC_Product_Attribute();
		$attribute->set_name( 'Size' );
		$attribute->set_options( array( 'Large' ) );
		$attribute->set_variation( true );

		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Wine' );
		$parent->set_status( 'publish' );
		$parent->set_attributes( array( $attribute ) );
		if ( $parent_adults_only ) {
			$parent->update_meta_data( Single::ADULTS_ONLY_FIELD, 'yes' );
		}
		$this->product_ids[] = $parent->save();

		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_attributes( array( 'size' => 'Large' ) );
		$variation->set_regular_price( '12' );
		$variation->set_status( 'publish' );
		if ( $variation_adults_only ) {
			$variation->update_meta_data( Single::ADULTS_ONLY_FIELD, 'yes' );
		}
		$this->product_ids[] = $variation->save();
		\WC_Product_Variable::sync( $parent->get_id() );

		$this->assertNotFalse(
			WC()->cart->add_to_cart( $parent->get_id(), 1, $variation->get_id(), array( 'attribute_size' => 'Large' ) ),
			'The variation must be in the cart.'
		);
	}

	/**
	 * Put a virtual product in the cart, which never ships.
	 *
	 * @param bool $adults_only Whether the product is marked as 18+.
	 */
	private function add_virtual_product_to_cart( bool $adults_only ): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Tasting voucher' );
		$product->set_regular_price( '10' );
		$product->set_status( 'publish' );
		$product->set_virtual( true );
		if ( $adults_only ) {
			$product->update_meta_data( Single::ADULTS_ONLY_FIELD, 'yes' );
		}
		$this->product_ids[] = $product->save();

		$this->assertNotFalse( WC()->cart->add_to_cart( $product->get_id() ), 'The product must be in the cart.' );
	}

	/**
	 * Shipping address fields for a destination country.
	 *
	 * @param string $country Destination country, NL or BE.
	 *
	 * @return array<string, string>
	 */
	private function address( string $country ): array {
		if ( 'BE' === $country ) {
			return array(
				'country'   => 'BE',
				'postcode'  => '1000',
				'address_1' => 'Rue Neuve 10',
				'city'      => 'Brussels',
			);
		}

		return array(
			'country'   => 'NL',
			'postcode'  => '2132WT',
			'address_1' => 'Siriusdreef 3',
			'city'      => 'Hoofddorp',
		);
	}

	/**
	 * Run the classic checkout callback for an order review refresh.
	 *
	 * @param string $country Destination country.
	 */
	private function run_classic( string $country ): void {
		$address = $this->address( $country );

		$_REQUEST['post_data'] = http_build_query(
			array(
				'billing_first_name' => 'Jan',
				'billing_last_name'  => 'Jansen',
				'billing_country'    => $address['country'],
				'billing_postcode'   => $address['postcode'],
				'billing_address_1'  => $address['address_1'],
				'billing_city'       => $address['city'],
				'shipping_method'    => array( 'flat_rate:1' ),
			)
		);

		WC()->customer->set_billing_country( $address['country'] );
		WC()->customer->set_billing_postcode( $address['postcode'] );
		WC()->customer->set_shipping_country( $address['country'] );
		WC()->customer->set_shipping_postcode( $address['postcode'] );

		ob_start();
		try {
			( new Container( false ) )->postnl_fields();
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Call the blocks checkout AJAX endpoint.
	 *
	 * @param string $country Destination country.
	 */
	private function run_blocks( string $country ): void {
		$address = $this->address( $country );

		add_filter( 'wp_doing_ajax', '__return_true' );

		$_POST = array(
			'nonce' => wp_create_nonce( 'postnl_delivery_day_nonce' ),
			'data'  => array(
				// The blocks checkout always sends this; without it the shipping fields are copied from billing.
				'ship_to_different_address' => '1',
				'shipping_country'          => $address['country'],
				'shipping_postcode'         => $address['postcode'],
				'shipping_address_1'        => $address['address_1'],
				'shipping_city'             => $address['city'],
				'shipping_method'           => 'flat_rate',
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
		} catch ( \Error $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		} finally {
			ob_end_clean();
		}
	}

	/**
	 * Run one checkout entry point and return the locationTypes of its pickup lookup.
	 *
	 * @param string $checkout Checkout type, classic or blocks.
	 * @param string $country  Destination country.
	 *
	 * @return string[]
	 */
	private function looked_up_location_types( string $checkout, string $country ): array {
		if ( 'classic' === $checkout ) {
			$this->run_classic( $country );
		} else {
			$this->run_blocks( $country );
		}

		$this->assertSame( array(), wc_get_notices( 'error' ), 'The lookup must not fail.' );
		$this->assertCount( 1, $this->http->bodies, 'The checkout must send one V4 pickup lookup.' );
		$this->assertSame( $country, $this->http->bodies[0]['receiverAddress']['countryIso'] );

		return $this->http->bodies[0]['locationTypes'];
	}

	/**
	 * Checkout types and destinations the lookup runs for.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function checkout_and_country_provider(): array {
		return array(
			'classic checkout, NL address' => array( 'classic', 'NL' ),
			'classic checkout, BE address' => array( 'classic', 'BE' ),
			'blocks checkout, NL address'  => array( 'blocks', 'NL' ),
			'blocks checkout, BE address'  => array( 'blocks', 'BE' ),
		);
	}

	/**
	 * @testdox A cart with an 18+ product is offered retail points only.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_adults_only_cart_gets_retail_points_only( string $checkout, string $country ): void {
		$this->add_product_to_cart( true );

		$this->assertSame( array( 'retail' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * @testdox A cart mixing an 18+ product with a normal one is offered retail points only.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_mixed_cart_gets_retail_points_only( string $checkout, string $country ): void {
		$this->add_product_to_cart( false );
		$this->add_product_to_cart( true );

		$this->assertSame( array( 'retail' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * @testdox A normal cart is still offered retail points and parcel lockers.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_normal_cart_gets_retail_points_and_parcel_lockers( string $checkout, string $country ): void {
		$this->add_product_to_cart( false );

		$this->assertSame( array( 'retail', 'parcel_locker' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * @testdox A cart with a variation marked as 18+ is offered retail points only, even when its parent isn't marked.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_adults_only_variation_gets_retail_points_only( string $checkout, string $country ): void {
		$this->add_variation_to_cart( false, true );

		$this->assertSame( array( 'retail' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * @testdox A variation of a product marked as 18+ is offered retail points only.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_variation_of_adults_only_product_gets_retail_points_only( string $checkout, string $country ): void {
		$this->add_variation_to_cart( true, false );

		$this->assertSame( array( 'retail' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * @testdox A virtual 18+ product next to a shippable normal product keeps parcel lockers, since only the normal product ships.
	 * @dataProvider checkout_and_country_provider
	 *
	 * @param string $checkout Checkout type.
	 * @param string $country  Destination country.
	 */
	public function test_virtual_adults_only_product_keeps_parcel_lockers( string $checkout, string $country ): void {
		$this->add_virtual_product_to_cart( true );
		$this->add_product_to_cart( false );

		$this->assertSame( array( 'retail', 'parcel_locker' ), $this->looked_up_location_types( $checkout, $country ) );
	}

	/**
	 * Pickup defaults that leave parcel lockers out for an NL address.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function locker_excluded_default_provider(): array {
		return array(
			'classic checkout, insured shipping default' => array( 'classic', 'insured_shipping' ),
			'classic checkout, ID check default'         => array( 'classic', 'id_check' ),
			'blocks checkout, insured shipping default'  => array( 'blocks', 'insured_shipping' ),
			'blocks checkout, ID check default'          => array( 'blocks', 'id_check' ),
		);
	}

	/**
	 * @testdox A normal cart still follows an insured or ID Check pickup default for an NL address.
	 * @dataProvider locker_excluded_default_provider
	 *
	 * @param string $checkout       Checkout type.
	 * @param string $pickup_default "Default Shipping Pickup" value.
	 */
	public function test_normal_cart_follows_the_pickup_default( string $checkout, string $pickup_default ): void {
		Settings::get_instance()->settings['default_shipping_options_pickup'] = $pickup_default;

		$this->add_product_to_cart( false );

		$this->assertSame( array( 'retail' ), $this->looked_up_location_types( $checkout, 'NL' ) );
	}
}
