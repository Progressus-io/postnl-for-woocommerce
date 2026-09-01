<?php
/**
 * Integration tests for the filter/action carry-forward audit (task 25).
 *
 * Guards that the public extension points third parties rely on keep firing once
 * a flow runs on the V4 SDK path, with the same parameter shape as the legacy
 * path. Every test drives the real V4\Label\Service::create() against a fake
 * transport, so a passing test proves the production label path fires the hook
 * and that the hook's result reaches the request — not merely that a helper
 * works when called by hand. The full matrix lives in
 * docs/postnl-v4-migration/approach-2/filter-carry-forward-matrix.md.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Logger;
use PostNLWooCommerce\Rest_API\Legacy\Shipping\Client as Legacy_Shipping_Client;
use PostNLWooCommerce\Rest_API\SDK\Logger_Adapter;
use PostNLWooCommerce\Rest_API\V4\Label\Service as V4_Label_Service;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Tests\Support\Client_Factory_Settings;
use PostNLWooCommerce\Tests\Support\Failing_Http_Client;
use PostNLWooCommerce\Tests\Support\Spy_Label_Client_Factory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Drives the V4 label service's create() end to end (up to the transport) and
 * asserts the legacy filters fire from it with their documented arguments.
 */
class FilterCarryForwardTest extends IntegrationTestCase {

	/**
	 * Backups of the WC options changed by setUp(), restored on teardown.
	 *
	 * @var array<string, mixed>
	 */
	private $orig_options = array();

	/**
	 * IDs of orders created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $order_ids = array();

	/**
	 * IDs of products created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $product_ids = array();

	/**
	 * Configure a complete NL store address so the shipper resolves to a valid
	 * domestic origin — Item_Info validation rejects an unconfigured store — and
	 * pin the weight unit so the expected gram value is deterministic.
	 */
	protected function setUp(): void {
		parent::setUp();

		$store = array(
			'woocommerce_default_country' => 'NL',
			'woocommerce_store_address'   => 'Siriusdreef',
			'woocommerce_store_city'      => 'Hoofddorp',
			'woocommerce_store_postcode'  => '2132WT',
			'woocommerce_weight_unit'     => 'kg',
		);

		foreach ( $store as $option => $value ) {
			$this->orig_options[ $option ] = get_option( $option );
			update_option( $option, $value );
		}
	}

	/**
	 * Restore the store options and remove the fixtures created by the test.
	 */
	protected function tearDown(): void {
		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( $this->order_ids as $order_id ) {
			wp_delete_post( $order_id, true );
		}

		foreach ( $this->product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		parent::tearDown();
	}

	/**
	 * @testdox postnl_shipment_addresses fires once from create() with ( array, Shipping\Client ) and the rewrite reaches the wire.
	 */
	public function test_shipment_addresses_filter_fires_from_create_and_reaches_the_request(): void {
		$captured = array();
		$rewrite  = function ( $addresses, $client = null ) use ( &$captured ) {
			$captured[] = array( $addresses, $client );

			foreach ( $addresses as $index => $address ) {
				if ( '01' === ( $address['AddressType'] ?? '' ) ) {
					$addresses[ $index ]['Street'] = 'Rewritten Street';
				}
			}

			return $addresses;
		};

		list( $service, $http ) = $this->make_service();

		add_filter( 'postnl_shipment_addresses', $rewrite, 10, 2 );

		try {
			$body = $this->run_create( $service, $http, $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_shipment_addresses', $rewrite, 10 );
		}

		$this->assertCount( 1, $captured, 'postnl_shipment_addresses must fire exactly once from the V4 label path.' );

		list( $addresses, $client ) = $captured[0];

		$this->assertIsArray( $addresses, 'The first argument must be the legacy-shaped addresses array.' );
		$this->assertInstanceOf(
			Legacy_Shipping_Client::class,
			$client,
			'The second argument must be a Shipping\Client, matching the legacy shape third parties hook.'
		);

		$recipient = $this->recipient_entry( $addresses );
		$this->assertNotNull( $recipient, 'The addresses array must carry the recipient (AddressType 01) entry.' );
		$this->assertSame( 'Main Street', $recipient['Street'], 'The filter must receive the unmodified order address.' );

		$this->assertStringContainsString( 'Rewritten Street', $body, 'A third-party address rewrite must reach the V4 request, not be fired and ignored.' );
		$this->assertStringNotContainsString( 'Main Street', $body, 'The original street must be replaced, not sent alongside the rewrite.' );
	}

	/**
	 * @testdox postnl_order_weight fires from create() with ( float, WC_Order ) and its return value reaches the wire.
	 */
	public function test_order_weight_filter_fires_from_create_and_reaches_the_request(): void {
		$captured = array();
		$override = function ( $weight, $order = null ) use ( &$captured ) {
			$captured[] = array( $weight, $order );

			return 2500.0;
		};

		list( $service, $http ) = $this->make_service();

		add_filter( 'postnl_order_weight', $override, 10, 2 );

		try {
			$body = $this->run_create( $service, $http, $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_order_weight', $override, 10 );
		}

		$this->assertNotEmpty( $captured, 'postnl_order_weight must fire while create() parses the order.' );

		list( $weight, $order ) = end( $captured );

		$this->assertIsFloat( $weight, 'The first argument must be a float, matching the documented legacy shape.' );
		$this->assertSame( 1500.0, $weight, 'The weight must be the order total in grams (1 x 1.5 kg).' );
		$this->assertInstanceOf( \WC_Order::class, $order, 'The second argument must be the WC_Order, matching the legacy shape.' );

		$this->assertStringContainsString( '"weight":2500', $body, 'The filtered weight must be the one sent to PostNL.' );
	}

	/**
	 * @testdox postnl_logger_write_message fires for a line the V4 label service logs.
	 */
	public function test_logger_write_message_filter_fires_from_the_v4_service(): void {
		$captured = array();
		$spy      = function ( $message ) use ( &$captured ) {
			$captured[] = $message;

			return $message;
		};

		list( $service, $http ) = $this->make_service( new Logger_Adapter( new Logger( true ) ) );

		add_filter( 'postnl_logger_write_message', $spy, 10, 1 );

		try {
			// The fake transport answers 401, so create() logs the failure through
			// the adapter — the same route every V4 service's log line takes.
			$this->run_create( $service, $http, $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_logger_write_message', $spy, 10 );
		}

		$v4_lines = array_filter(
			$captured,
			static function ( $message ) {
				return is_string( $message ) && 0 === strpos( $message, '[postnl-v4]' );
			}
		);

		$this->assertNotEmpty( $v4_lines, 'postnl_logger_write_message must see the lines the V4 service writes.' );
		$this->assertStringContainsString(
			'V4 label creation failed',
			implode( "\n", $v4_lines ),
			'The filtered line must be the finished message, matching the ( string $message ) legacy shape.'
		);
	}

	/**
	 * @testdox postnl_order_meta_box_fields fires from the V4 label service with ( array, string ).
	 */
	public function test_meta_box_fields_filter_fires_from_the_v4_service(): void {
		$captured = array();
		$spy      = function ( $fields, $context = null ) use ( &$captured ) {
			$captured[] = array( $fields, $context );

			return $fields;
		};

		list( $service ) = $this->make_service();
		$post_data       = $this->make_post_data();

		add_filter( 'postnl_order_meta_box_fields', $spy, 10, 2 );

		try {
			$fields = $service->meta_box_fields( $post_data['order'], 'save' );
		} finally {
			remove_filter( 'postnl_order_meta_box_fields', $spy, 10 );
		}

		$this->assertCount( 1, $captured, 'postnl_order_meta_box_fields must fire once per meta_box_fields() call.' );
		$this->assertIsArray( $captured[0][0], 'The first argument must be the fields array.' );
		$this->assertSame( 'save', $captured[0][1], 'The second argument must be the context string the caller passed.' );
		$this->assertSame( $captured[0][0], $fields, 'The filtered fields must be what the service returns.' );
	}

	/**
	 * @testdox A postnl_shipment_addresses callback that returns null fails create() with a named \Exception, not a TypeError.
	 */
	public function test_non_array_addresses_return_fails_with_a_named_exception(): void {
		list( $service, $http ) = $this->make_service();

		add_filter( 'postnl_shipment_addresses', '__return_null', 10 );

		$caught = null;
		try {
			$service->create( $this->make_post_data() );
		} catch ( \Exception $exception ) {
			$caught = $exception;
		} finally {
			remove_filter( 'postnl_shipment_addresses', '__return_null', 10 );
		}

		$this->assertNotNull( $caught, 'create() must fail when the filter returns null.' );
		$this->assertSame( \Exception::class, get_class( $caught ), 'The failure must be the plain \Exception the AJAX handlers catch and display.' );
		$this->assertStringContainsString( 'postnl_shipment_addresses', $caught->getMessage(), 'The message must name the filter so the merchant knows which plugin to look at.' );
		$this->assertNull( $http->last_request, 'Nothing must be sent to PostNL when the address list is invalid.' );
	}

	/**
	 * @testdox A postnl_shipment_addresses callback that sets a nested value fails create() with an \Exception naming the field.
	 */
	public function test_non_scalar_address_field_fails_with_a_named_exception(): void {
		$nest = function ( $addresses ) {
			$addresses[0]['Street'] = array( 'Nested', 'Street' );

			return $addresses;
		};

		list( $service, $http ) = $this->make_service();

		add_filter( 'postnl_shipment_addresses', $nest, 10 );

		$caught = null;
		try {
			$service->create( $this->make_post_data() );
		} catch ( \Exception $exception ) {
			$caught = $exception;
		} finally {
			remove_filter( 'postnl_shipment_addresses', $nest, 10 );
		}

		$this->assertNotNull( $caught, 'create() must fail when a recipient field is not a scalar.' );
		$this->assertSame( \Exception::class, get_class( $caught ) );
		$this->assertStringContainsString( 'Street', $caught->getMessage(), 'The message must name the offending field.' );
		$this->assertNull( $http->last_request, 'Nothing must be sent to PostNL when a recipient field is invalid.' );
	}

	/**
	 * Find the recipient (AddressType 01) entry in a legacy addresses array.
	 *
	 * @param array $addresses Legacy-shaped addresses array.
	 * @return array|null
	 */
	private function recipient_entry( array $addresses ): ?array {
		foreach ( $addresses as $address ) {
			if ( '01' === ( $address['AddressType'] ?? '' ) ) {
				return $address;
			}
		}

		return null;
	}

	/**
	 * Build the real V4 label service against a fake transport.
	 *
	 * The service is constructed exactly as Service_Factory constructs it; only the
	 * HTTP client inside the SDK is replaced, so create() runs its real eligibility,
	 * mapper, filter and request-builder steps and the built request is captured.
	 *
	 * @param LoggerInterface|null $logger PSR-3 logger; NullLogger unless a test asserts on logging.
	 * @return array{0: V4_Label_Service, 1: Failing_Http_Client}
	 */
	private function make_service( ?LoggerInterface $logger = null ): array {
		$http    = new Failing_Http_Client();
		$factory = new Spy_Label_Client_Factory( new Client_Factory_Settings(), $http );
		$service = new V4_Label_Service( $factory, 'v4-integration-key', $logger ?? new NullLogger() );

		return array( $service, $http );
	}

	/**
	 * Run the real create() and return the JSON body that reached the transport.
	 *
	 * The fake transport answers 401, so create() throws the converted error after
	 * the request has been sent; that is expected. What is not tolerated is create()
	 * never reaching the transport: that means eligibility routed the order down the
	 * legacy pipeline, where the same filters fire from legacy code and would make
	 * every assertion pass for the wrong reason.
	 *
	 * @param V4_Label_Service   $service   Service under test.
	 * @param Failing_Http_Client $http     Its fake transport.
	 * @param array               $post_data Label post data.
	 * @return string Request body.
	 */
	private function run_create( V4_Label_Service $service, Failing_Http_Client $http, array $post_data ): string {
		$caught = null;
		try {
			$service->create( $post_data );
		} catch ( \Exception $exception ) {
			$caught = $exception;
		}

		$this->assertNotNull( $caught, 'The fake transport answers 401, so create() must throw.' );
		$this->assertStringContainsString( 'trace-abc', $caught->getMessage(), 'create() must fail with the canned 401, not with something earlier in the path: ' . $caught->getMessage() );
		$this->assertNotNull( $http->last_request, 'The V4 request must reach the transport; null here means the order fell back to the legacy pipeline.' );

		return (string) $http->last_request->getBody();
	}

	/**
	 * Build a domestic NL order with one physical product and the post-data shape
	 * Order\Base::save_meta_value() hands to the label service.
	 *
	 * @return array
	 */
	private function make_post_data(): array {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( '10' );
		$product->set_weight( '1.5' );
		$product->save();
		$this->product_ids[] = $product->get_id();

		$order = new \WC_Order();
		$order->set_billing_email( 'buyer@example.com' );
		$order->set_billing_phone( '0612345678' );
		$order->set_shipping_first_name( 'Jan' );
		$order->set_shipping_last_name( 'Jansen' );
		$order->set_shipping_company( 'Buyer BV' );
		$order->set_shipping_address_1( 'Main Street' );
		$order->set_shipping_city( 'Amsterdam' );
		$order->set_shipping_postcode( '1234AB' );
		$order->set_shipping_country( 'NL' );
		$order->update_meta_data( '_shipping_house_number', '9' );
		$order->add_product( $product, 1 );
		$order->save();
		$this->order_ids[] = $order->get_id();

		return array(
			'order'                   => $order,
			'saved_data'              => array(
				'backend'  => array( 'delivery_type' => 'Standard' ),
				'frontend' => array(),
			),
			'main_barcode'            => '3SDEVC0000001',
			'barcodes'                => array( '3SDEVC0000001' ),
			'return_barcode'          => '',
			'shipping_return_barcode' => '',
			'is_return_activated'     => false,
		);
	}
}
