<?php
/**
 * Unit tests for Rest_API\V4\Returns\Smart_Returns_Service::map_fields().
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Returns;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Client\ClientBuilder;
use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Item_Info;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Returns\Smart_Returns_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Returns\Smart_Returns_Service
 */
class Smart_Returns_ServiceTest extends UnitTestCase {

	/**
	 * API key the service is constructed with in these tests.
	 */
	private const V4_KEY = 'v4-smart-returns-secret';

	protected function setUp(): void {
		parent::setUp();
		$this->seed_settings_singleton();

		// The service's thrown messages are translated; surface them verbatim.
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'esc_html__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		$this->reset_settings_singleton();
		parent::tearDown();
	}

	/**
	 * Replace the Settings singleton build_client() reads is_sandbox() from with a
	 * double overriding exactly that getter. Every unstubbed parent method is left
	 * alone on purpose: reaching one fatals rather than quietly returning stub data.
	 *
	 * @return void
	 */
	private function seed_settings_singleton(): void {
		$double = new class() extends Settings {
			// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Skips the real constructor, which needs WooCommerce.
			public function __construct() {}

			public function is_sandbox() {
				return true;
			}
		};

		$property = new \ReflectionProperty( Settings::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, $double );
	}

	/**
	 * Clear the seeded singleton so it cannot leak into another test file.
	 *
	 * @return void
	 */
	private function reset_settings_singleton(): void {
		$property = new \ReflectionProperty( Settings::class, 'instance' );
		$property->setAccessible( true );
		$property->setValue( null, null );
	}

	/**
	 * Build a service wired to a fake transport, so nothing reaches the network.
	 *
	 * @param ClientInterface     $http   Fake HTTP client.
	 * @param AbstractLogger|null $logger Optional spy logger.
	 * @return Testable_Smart_Returns_Service
	 */
	private function service( ClientInterface $http, ?AbstractLogger $logger = null ): Testable_Smart_Returns_Service {
		return new Testable_Smart_Returns_Service(
			new Smart_Returns_Spy_Client_Factory( new Smart_Returns_Client_Factory_Settings(), $http ),
			self::V4_KEY,
			$logger ?? new NullLogger()
		);
	}

	/**
	 * A Dutch order carrying the consumer contact generate() reads.
	 *
	 * @param string $country Shipping country.
	 * @return \WC_Order
	 */
	private function order( string $country = 'NL' ): \WC_Order {
		return new \WC_Order(
			array(
				'shipping_country'    => $country,
				'shipping_first_name' => 'Jan',
				'shipping_last_name'  => 'Jansen',
				'billing_email'       => 'buyer@example.com',
				'billing_phone'       => '0612345678',
				'order_number'        => 'ORDER-1001',
			)
		);
	}

	/**
	 * A response item for the succeeding fake client.
	 *
	 * @param array       $labels  Label entries (label/outputType/labelType).
	 * @param string|null $barcode Item barcode, or null to omit the field.
	 * @return array
	 */
	private static function response_item( array $labels, ?string $barcode = '3SDEVCRET77' ): array {
		$item = array( 'labels' => $labels );

		if ( null !== $barcode ) {
			$item['barcode'] = $barcode;
		}

		return $item;
	}

	/**
	 * A label entry for the succeeding fake client.
	 *
	 * @param string $content     Raw (pre-encoding) label bytes.
	 * @param string $output_type SDK output type string.
	 * @param string $label_type  labelType string as PostNL would return it.
	 * @return array
	 */
	private static function label( string $content, string $output_type = 'png', string $label_type = 'Printcode' ): array {
		return array(
			'label'      => base64_encode( $content ),
			'outputType' => $output_type,
			'labelType'  => $label_type,
		);
	}

	/**
	 * The consumer address + return-to-home receiver as the legacy
	 * Smart_Returns\Item_Info::$customer array exposes it.
	 *
	 * @return array
	 */
	private function customer(): array {
		return array(
			'company'                    => '',
			'address_1'                  => 'Main Street',
			'address_2'                  => 'B',
			'house_number'               => '9',
			'city'                       => 'Amsterdam',
			'state'                      => '',
			'country'                    => 'NL',
			'postcode'                   => '1234AB',
			'return_address_1'           => 'Siriusdreef',
			'return_address_2'           => '42',
			'return_address_house_noext' => '',
			'return_address_city'        => 'Hoofddorp',
			'return_address_zip'         => '2132WT',
			'return_customer_code'       => 'DEVC',
		);
	}

	/**
	 * The merchant store info as Smart_Returns\Item_Info::$store exposes it.
	 *
	 * @return array
	 */
	private function store(): array {
		return array(
			'company' => 'My Shop',
			'country' => 'NL',
			'email'   => 'shop@example.com',
		);
	}

	/**
	 * The consumer contact read from the order.
	 *
	 * @return array
	 */
	private function consumer(): array {
		return array(
			'first_name' => 'Jan',
			'last_name'  => 'Jansen',
			'email'      => 'buyer@example.com',
			'phone'      => '0612345678',
		);
	}

	/**
	 * @testdox map_fields() always requests retailPrint with a PNG output for inline display.
	 */
	public function test_forces_retail_print_png(): void {
		$fields = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' );

		$this->assertSame( 'retailPrint', $fields['print_method'] );
		$this->assertSame( 'png', $fields['label']['output_type'] );
		$this->assertSame( '', $fields['barcode'], 'No barcode is pre-issued; return/generate auto-issues one.' );
		$this->assertSame( 'ORDER-1001', $fields['reference'] );
	}

	/**
	 * @testdox map_fields() maps the consumer (order recipient) as the return sender.
	 */
	public function test_sender_is_the_consumer(): void {
		$sender = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' )['sender'];

		$this->assertSame( 'Main Street', $sender['street'] );
		$this->assertSame( '9', $sender['house_number'] );
		$this->assertSame( 'B', $sender['house_number_ext'] );
		$this->assertSame( '1234AB', $sender['postcode'] );
		$this->assertSame( 'Amsterdam', $sender['city'] );
		$this->assertSame( 'NL', $sender['country'] );
		$this->assertSame( 'Jan', $sender['first_name'] );
		$this->assertSame( 'Jansen', $sender['last_name'] );
		$this->assertSame( 'buyer@example.com', $sender['email'] );
		$this->assertSame( '0612345678', $sender['phone'] );
	}

	/**
	 * @testdox map_fields() maps a return-to-home receiver from the merchant return address.
	 */
	public function test_receiver_is_the_return_to_home_address(): void {
		$receiver = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' )['receiver'];

		$this->assertSame( 'My Shop', $receiver['company'] );
		$this->assertSame( 'Siriusdreef', $receiver['street'] );
		$this->assertSame( '42', $receiver['house_number'] );
		$this->assertSame( '2132WT', $receiver['postcode'] );
		$this->assertSame( 'Hoofddorp', $receiver['city'] );
		$this->assertSame( 'NL', $receiver['country'] );
	}

	/**
	 * @testdox map_fields() encodes a reply-number (Antwoordnummer) receiver from the return address.
	 */
	public function test_receiver_encodes_reply_number(): void {
		$customer                     = $this->customer();
		$customer['return_address_1'] = 'Antwoordnummer';
		$customer['return_address_2'] = '12345';
		$customer['return_address_city'] = 'Amsterdam';
		$customer['return_address_zip']  = '1000AA';

		$receiver = Smart_Returns_Service::map_fields( $customer, $this->store(), $this->consumer(), 'ORDER-1001' )['receiver'];

		$this->assertSame( 'Antwoordnummer', $receiver['street'] );
		$this->assertSame( '12345', $receiver['house_number'] );
		$this->assertSame( '1000AA', $receiver['postcode'] );
	}

	/**
	 * @testdox map_fields() takes the receiver country from the store, not a constant.
	 *
	 * The store fixture must NOT be NL here: 'NL' equals the `?? 'NL'` fallback,
	 * so an NL fixture cannot tell $store['country'] apart from a hardcoded 'NL'
	 * (mutation-verified before this test existed).
	 */
	public function test_receiver_country_follows_the_store(): void {
		$store            = $this->store();
		$store['country'] = 'BE';

		$receiver = Smart_Returns_Service::map_fields( $this->customer(), $store, $this->consumer(), 'ORDER-1001' )['receiver'];

		$this->assertSame( 'BE', $receiver['country'] );
	}

	/**
	 * @testdox map_fields() sends no weight; the builder clamps 0 up to the 1-gram minimum.
	 */
	public function test_weight_is_left_to_the_builder_minimum(): void {
		$fields = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' );

		$this->assertSame( 0, $fields['weight_gr'], 'The legacy V2.2 request carried no weight; parity is the builder minimum, not a real weight.' );
	}

	// -------------------------------------------------------------------------
	// generate() — routing, response handling, logging
	// -------------------------------------------------------------------------

	/**
	 * @testdox generate() routes a Dutch order to V4 and returns the normalized printcode array.
	 */
	public function test_generate_routes_a_dutch_order_to_v4(): void {
		$http    = new Smart_Returns_Succeeding_Http_Client(
			array( self::response_item( array( self::label( 'PNG-BYTES' ) ) ) )
		);
		$service = $this->service( $http );

		$result = $service->generate( $this->order() );

		$this->assertSame( 'v4', $result['api_version'] );
		$this->assertSame( '3SDEVCRET77', $result['barcode'] );
		$this->assertSame( 'PNG-BYTES', $result['content'] );
		$this->assertSame( 'png', $result['output_type'] );
		$this->assertSame( 'image/png', $result['mime'] );
		$this->assertNotNull( $http->last_request, 'The V4 endpoint must actually be called.' );
		$this->assertSame( 0, $service->legacy_calls, 'A Dutch order must not run the legacy pipeline.' );
	}

	/**
	 * @testdox generate() falls back to the legacy pipeline for a non-Dutch order.
	 */
	public function test_generate_falls_back_for_a_non_dutch_order(): void {
		$http    = new Smart_Returns_Succeeding_Http_Client( array() );
		$service = $this->service( $http );

		$result = $service->generate( $this->order( 'BE' ) );

		$this->assertSame( array( 'legacy-pipeline' => true ), $result );
		$this->assertSame( 1, $service->legacy_calls );
		$this->assertNull( $http->last_request, 'A non-Dutch order must never reach the V4 endpoint.' );
	}

	/**
	 * @testdox A jpg printcode is returned with the matching jpeg MIME type.
	 */
	public function test_jpg_printcode_maps_to_the_jpeg_mime(): void {
		$service = $this->service(
			new Smart_Returns_Succeeding_Http_Client(
				array( self::response_item( array( self::label( 'JPG-BYTES', 'jpg' ) ) ) )
			)
		);

		$result = $service->generate( $this->order() );

		$this->assertSame( 'jpg', $result['output_type'] );
		$this->assertSame( 'image/jpeg', $result['mime'] );
	}

	/**
	 * @testdox A pdf-only response is rejected loudly instead of being mailed to the consumer.
	 */
	public function test_pdf_only_response_is_rejected_and_logged(): void {
		$logger  = new Smart_Returns_Spy_Logger();
		$service = $this->service(
			new Smart_Returns_Succeeding_Http_Client(
				array( self::response_item( array( self::label( '%PDF-1.4', 'pdf', 'Return Label' ) ) ) )
			),
			$logger
		);

		try {
			$service->generate( $this->order() );
			$this->fail( 'A pdf-only response must throw.' );
		} catch ( \Exception $exception ) {
			$this->assertStringContainsString( 'format that cannot be shown', $exception->getMessage() );
		}

		$this->assertCount( 1, $logger->records );
		$this->assertSame( 'error', $logger->records[0]['level'] );
		$this->assertStringContainsString( 'pdf', $logger->records[0]['message'] );
		$this->assertStringContainsString( 'ORDER-1001', $logger->records[0]['message'] );
	}

	/**
	 * @testdox More than one usable label logs a warning naming the label types; the first one is used.
	 */
	public function test_multiple_usable_labels_warn_and_the_first_wins(): void {
		$logger  = new Smart_Returns_Spy_Logger();
		$service = $this->service(
			new Smart_Returns_Succeeding_Http_Client(
				array(
					self::response_item(
						array(
							self::label( 'PRINTCODE-BYTES', 'png', 'Printcode' ),
							self::label( 'LABEL-BYTES', 'png', 'Return Label' ),
						)
					),
				)
			),
			$logger
		);

		$result = $service->generate( $this->order() );

		$this->assertSame( 'PRINTCODE-BYTES', $result['content'] );
		$this->assertCount( 1, $logger->records );
		$this->assertSame( 'warning', $logger->records[0]['level'] );
		$this->assertStringContainsString( 'Printcode', $logger->records[0]['message'] );
		$this->assertStringContainsString( 'Return Label', $logger->records[0]['message'] );
	}

	/**
	 * @testdox A response without a barcode number logs a warning and returns an empty barcode.
	 */
	public function test_missing_barcode_warns(): void {
		$logger  = new Smart_Returns_Spy_Logger();
		$service = $this->service(
			new Smart_Returns_Succeeding_Http_Client(
				array( self::response_item( array( self::label( 'PNG-BYTES' ) ), null ) )
			),
			$logger
		);

		$result = $service->generate( $this->order() );

		$this->assertSame( '', $result['barcode'] );
		$this->assertCount( 1, $logger->records );
		$this->assertSame( 'warning', $logger->records[0]['level'] );
		$this->assertStringContainsString( 'no barcode', $logger->records[0]['message'] );
	}

	/**
	 * @testdox An empty response throws the no-shipment error.
	 */
	public function test_no_items_throws(): void {
		$service = $this->service( new Smart_Returns_Succeeding_Http_Client( array() ) );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'No shipment was returned' );

		$service->generate( $this->order() );
	}

	/**
	 * @testdox A failed V4 call is logged with the order number and the original cause.
	 */
	public function test_failed_call_is_logged_with_the_order_number(): void {
		$logger  = new Smart_Returns_Spy_Logger();
		$service = $this->service( new Smart_Returns_Failing_Http_Client(), $logger );

		try {
			$service->generate( $this->order() );
			$this->fail( 'A failing transport must throw.' );
		} catch ( \Exception $exception ) {
			// The converted, merchant-facing error is what escapes.
			$this->assertNotSame( '', $exception->getMessage() );
		}

		$this->assertCount( 1, $logger->records );
		$this->assertSame( 'error', $logger->records[0]['level'] );
		$this->assertStringContainsString( 'ORDER-1001', $logger->records[0]['message'] );
		$this->assertStringContainsString( 'cause', $logger->records[0]['message'] );
	}

	/**
	 * @testdox A successful V4 call writes nothing to the log.
	 */
	public function test_successful_call_is_not_logged(): void {
		$logger  = new Smart_Returns_Spy_Logger();
		$service = $this->service(
			new Smart_Returns_Succeeding_Http_Client(
				array( self::response_item( array( self::label( 'PNG-BYTES' ) ) ) )
			),
			$logger
		);

		$service->generate( $this->order() );

		$this->assertSame( array(), $logger->records );
	}
}

/**
 * Service under test with the WooCommerce-bound collaborators replaced: the
 * legacy fallback is recorded instead of run, and the legacy Item_Info is built
 * without touching WordPress.
 */
class Testable_Smart_Returns_Service extends Smart_Returns_Service {

	/**
	 * How many times the legacy fallback ran.
	 *
	 * @var int
	 */
	public $legacy_calls = 0;

	/**
	 * Record the fallback instead of running the WooCommerce-bound legacy client.
	 *
	 * @return Smart_Returns_Service_Interface
	 */
	protected function legacy_service(): Smart_Returns_Service_Interface {
		++$this->legacy_calls;

		return new class() implements Smart_Returns_Service_Interface {
			/**
			 * @param \WC_Order $order Order object.
			 * @return array
			 */
			public function generate( \WC_Order $order ): array {
				unset( $order );

				return array( 'legacy-pipeline' => true );
			}
		};
	}

	/**
	 * Build the parsed item info without the WooCommerce-bound parent constructor.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 * @return Item_Info
	 */
	protected function item_info( \WC_Order $order ): Item_Info {
		unset( $order );

		return new Fake_Smart_Returns_Item_Info();
	}
}

/**
 * Smart Returns item info with the parsed properties set directly, skipping the
 * WooCommerce-bound parent constructor.
 */
class Fake_Smart_Returns_Item_Info extends Item_Info {

	// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Deliberately skips the WooCommerce-bound parent constructor.
	public function __construct() {
		$this->customer = array(
			'company'                    => '',
			'address_1'                  => 'Main Street',
			'address_2'                  => 'B',
			'house_number'               => '9',
			'city'                       => 'Amsterdam',
			'country'                    => 'NL',
			'postcode'                   => '1234AB',
			'return_address_1'           => 'Siriusdreef',
			'return_address_2'           => '42',
			'return_address_house_noext' => '',
			'return_address_city'        => 'Hoofddorp',
			'return_address_zip'         => '2132WT',
		);
		$this->store    = array(
			'company' => 'My Shop',
			'country' => 'NL',
			'email'   => 'shop@example.com',
		);
	}
}

/**
 * Minimal settings stand-in for Client_Factory, which only reads the customer
 * credentials off the object it is handed.
 */
class Smart_Returns_Client_Factory_Settings {

	/**
	 * @return string
	 */
	public function get_customer_num() {
		return '11223344';
	}

	/**
	 * @return string
	 */
	public function get_customer_code() {
		return 'DEVC';
	}
}

/**
 * Client_Factory whose SDK builder is wired to a fake HTTP client so no network
 * call is made, while the production client configuration stays intact.
 */
class Smart_Returns_Spy_Client_Factory extends Client_Factory {

	/**
	 * Fake HTTP client injected into every built SDK client.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http_client;

	/**
	 * Constructor.
	 *
	 * @param object          $settings    Settings stub.
	 * @param ClientInterface $http_client Fake HTTP client.
	 */
	public function __construct( object $settings, ClientInterface $http_client ) {
		parent::__construct( $settings );
		$this->http_client = $http_client;
	}

	/**
	 * Attach the fake HTTP client to the configured builder.
	 *
	 * @param string $v4_key          V4 API key.
	 * @param bool   $is_sandbox      Sandbox flag.
	 * @param string $customer_number PostNL customer number.
	 * @param string $customer_code   PostNL customer code.
	 * @return ClientBuilder
	 */
	protected function make_builder( string $v4_key, bool $is_sandbox, string $customer_number, string $customer_code ): ClientBuilder {
		return parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )
			->withHttpClient( $this->http_client );
	}
}

/**
 * PSR-18 client that always answers with a PostNL problem+json error.
 *
 * 401 is chosen because the SDK's retry policy treats it as permanent, so the
 * failure surfaces on the first attempt with no backoff sleeps in the test.
 */
class Smart_Returns_Failing_Http_Client implements ClientInterface {

	/**
	 * The most recent outgoing request.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Answer every request with a 401 problem document.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return new Response(
			401,
			array( 'Content-Type' => 'application/problem+json' ),
			(string) json_encode(
				array(
					'type'   => 'about:blank',
					'title'  => 'Unauthorized',
					'status' => 401,
					'detail' => 'Invalid apikey',
				)
			)
		);
	}
}

/**
 * PSR-18 client that answers with a configurable return/generate success body.
 */
class Smart_Returns_Succeeding_Http_Client implements ClientInterface {

	/**
	 * The most recent outgoing request.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Response items payload.
	 *
	 * @var array
	 */
	private array $items;

	/**
	 * @param array $items Items array as PostNL would return it.
	 */
	public function __construct( array $items ) {
		$this->items = $items;
	}

	/**
	 * Answer every request with the canned success body.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return new Response(
			200,
			array( 'Content-Type' => 'application/json' ),
			(string) json_encode( array( 'items' => $this->items ) )
		);
	}
}

/**
 * PSR-3 logger that records every write for assertion.
 */
class Smart_Returns_Spy_Logger extends AbstractLogger {

	/**
	 * Recorded log calls, each as array{level: mixed, message: string, context: array}.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public array $records = array();

	/**
	 * Record the call.
	 *
	 * @param mixed              $level   PSR-3 level.
	 * @param string|\Stringable $message Log message.
	 * @param array              $context Context values.
	 * @return void
	 */
	public function log( $level, string|\Stringable $message, array $context = array() ): void {
		$this->records[] = array(
			'level'   => $level,
			'message' => (string) $message,
			'context' => $context,
		);
	}
}
