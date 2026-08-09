<?php
/**
 * Unit tests for Rest_API\V4\Returns\Service.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Returns;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Client\ClientBuilder;
use PostNLWooCommerce\Rest_API\Legacy\Return_Label\Item_Info;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Returns\Request_Builder;
use PostNLWooCommerce\Rest_API\V4\Returns\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Returns\Service
 */
class ServiceTest extends UnitTestCase {

	/**
	 * API key the Service is constructed with in these tests.
	 */
	private const V4_KEY = 'v4-returns-secret';

	protected function setUp(): void {
		parent::setUp();
		$this->seed_settings_singleton();

		// Exception_Converter translates its messages; surface them verbatim in failures.
		Functions\when( '__' )->returnArg( 1 );
	}

	protected function tearDown(): void {
		$this->reset_settings_singleton();
		parent::tearDown();
	}

	/**
	 * Replace the Settings singleton Order\Base hands the service with a double
	 * overriding exactly the getters it reads. Every unstubbed parent method is left
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
	 * @param ClientInterface   $http   Fake HTTP client.
	 * @param AbstractLogger|null $logger Optional spy logger.
	 * @return Testable_Returns_Service
	 */
	private function service( ClientInterface $http, ?AbstractLogger $logger = null ): Testable_Returns_Service {
		return new Testable_Returns_Service(
			new Spy_Returns_Client_Factory( new Returns_Client_Factory_Settings(), $http ),
			self::V4_KEY,
			$logger ?? new NullLogger()
		);
	}

	/**
	 * Call a private method on the service.
	 *
	 * Reached by reflection rather than by widening the method to protected. These
	 * helpers have no collaborators, so promoting them would buy nothing and would
	 * enlarge the class's public shape purely for the tests.
	 *
	 * @param Service $service Service under test.
	 * @param string  $method  Method name.
	 * @param mixed   ...$args Arguments.
	 * @return mixed
	 */
	private function call_private( Service $service, string $method, ...$args ) {
		$reflected = new \ReflectionMethod( Service::class, $method );
		$reflected->setAccessible( true );

		return $reflected->invoke( $service, ...$args );
	}

	/**
	 * A parsed return item info for a Dutch customer of a Dutch store.
	 *
	 * @param string $customer_country Customer (return sender) country.
	 * @param string $printer_type     Merchant's combined printer-type string.
	 * @return Item_Info
	 */
	private function item_info( string $customer_country = 'NL', string $printer_type = 'GraphicFile|JPG 600 dpi' ): Item_Info {
		return new Fake_Return_Item_Info( $customer_country, $printer_type );
	}

	// ── Eligibility ──────────────────────────────────────────────────────────

	/**
	 * @testdox A Dutch customer is eligible for the V4 retailPrint return
	 */
	public function test_dutch_customer_is_eligible(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$this->assertTrue( $this->call_private( $service, 'is_eligible', $this->item_info( 'NL' ) ) );
	}

	/**
	 * @testdox A Belgian customer is not eligible, even though the store is Dutch
	 *
	 * The print method follows the country the parcel is handed in from, which is
	 * the customer's. The SDK states it on LabelPrintMethod: sending from BE allows
	 * only consumerPrint, and consumerPrint is not implemented. Reading the store
	 * country instead would always say yes, because the plugin refuses to run unless
	 * the store is in NL or BE — and Mapping::option_available_list() does offer
	 * create_return_label for NL to BE, so this case is reachable.
	 */
	public function test_belgian_customer_is_not_eligible(): void {
		$service   = $this->service( new Failing_Returns_Http_Client() );
		$item_info = $this->item_info( 'BE' );

		// The store stays Dutch, so only the customer's country can decide this.
		$this->assertSame( 'NL', $item_info->shipper['country'] );
		$this->assertFalse( $this->call_private( $service, 'is_eligible', $item_info ) );
	}

	// ── create() guard order ─────────────────────────────────────────────────

	/**
	 * @testdox create() returns the legacy pipeline result without building Item_Info when the box is unticked
	 *
	 * Item_Info extends Shipping\Item_Info, which lists main_barcode as required with
	 * no default and throws "Barcode is empty!" when it is absent. This post data has
	 * no main_barcode, so building it before the checkbox check would turn an
	 * ordinary save into an error about barcodes.
	 */
	public function test_create_without_the_checkbox_never_builds_item_info(): void {
		$http    = new Failing_Returns_Http_Client();
		$service = $this->service( $http );

		$result = $service->create(
			array(
				'saved_data' => array( 'backend' => array( 'create_return_label' => 'no' ) ),
			)
		);

		$this->assertSame( array( 'legacy-pipeline' => true ), $result );
		$this->assertSame( 1, $service->pipeline_calls );
		$this->assertNull( $http->last_request, 'The SDK must not be called when no return label was requested.' );
	}

	/**
	 * @testdox create() falls back to the legacy pipeline when the key is missing entirely
	 */
	public function test_create_falls_back_when_the_flag_is_absent(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$this->assertSame( array( 'legacy-pipeline' => true ), $service->create( array( 'saved_data' => array( 'backend' => array() ) ) ) );
		$this->assertSame( 1, $service->pipeline_calls );
	}

	// ── Field extraction ─────────────────────────────────────────────────────

	/**
	 * @testdox extract_fields() carries the return barcode, order number and weight
	 *
	 * The three numbers are kept distinct in the fixture so reading the wrong key
	 * cannot pass by coincidence.
	 */
	public function test_extract_fields_carries_the_order_values(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$fields = $this->call_private(
			$service,
			'extract_fields',
			$this->item_info(),
			array( 'return_barcode' => '3SDEVCRET77' )
		);

		$this->assertSame( '3SDEVCRET77', $fields['barcode'] );
		$this->assertSame( 'ORDER-4242', $fields['reference'] );
		$this->assertSame( 1500, $fields['weight_gr'] );
		$this->assertSame( 'retailPrint', $fields['print_method'] );
	}

	/**
	 * @testdox extract_fields() maps the customer to the sender and the return address to the receiver
	 *
	 * A return travels customer to merchant, so the outbound recipient becomes the
	 * return sender.
	 */
	public function test_extract_fields_swaps_the_parties(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$fields = $this->call_private( $service, 'extract_fields', $this->item_info(), array() );

		$this->assertSame( 'Kalverstraat', $fields['sender']['street'] );
		$this->assertSame( 'buyer@example.com', $fields['sender']['email'] );
		$this->assertSame( 'Antwoordnummer', $fields['receiver']['street'] );
		$this->assertSame( '12345', $fields['receiver']['house_number'] );
	}

	/**
	 * @testdox extract_fields() carries both halves of the printer setting
	 *
	 * The merchant's setting is one combined string holding the format and the dpi.
	 * Keeping only the format left every return label at the SDK's 200 dpi default,
	 * silently downgrading a merchant configured for 600.
	 */
	public function test_extract_fields_carries_output_type_and_resolution(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$fields = $this->call_private(
			$service,
			'extract_fields',
			$this->item_info( 'NL', 'Zebra|Generic ZPL II 300 dpi' ),
			array()
		);

		$this->assertSame( 'zpl', $fields['label']['output_type'] );
		$this->assertSame( 300, $fields['label']['resolution'] );
	}

	/**
	 * @testdox extract_fields() falls back to 200 dpi for a printer setting carrying no dpi
	 */
	public function test_extract_fields_defaults_the_resolution_for_pdf(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );

		$fields = $this->call_private( $service, 'extract_fields', $this->item_info( 'NL', 'GraphicFile|PDF' ), array() );

		$this->assertSame( 'pdf', $fields['label']['output_type'] );
		$this->assertSame( 200, $fields['label']['resolution'] );
	}

	// ── Client construction ──────────────────────────────────────────────────

	/**
	 * @testdox The SDK client authenticates with the injected key, not one re-read from settings
	 *
	 * The key is resolved and validated by Service_Factory before it decides V4 may
	 * run at all. Re-deriving it inside the service duplicated that decision behind a
	 * method_exists() guard that fell back to an empty key.
	 */
	public function test_client_authenticates_with_the_injected_key(): void {
		$http    = new Failing_Returns_Http_Client();
		$service = $this->service( $http );
		$fields  = $this->call_private( $service, 'extract_fields', $this->item_info(), array() );

		try {
			$service->expose_generate_return( Request_Builder::build( $fields ), $fields );
		} catch ( \Exception $error ) {
			unset( $error );
		}

		$this->assertNotNull( $http->last_request, 'The request must reach the transport.' );
		$this->assertSame( self::V4_KEY, $http->last_request->getHeaderLine( 'apiKey' ) );
	}

	// ── Error handling and logging ───────────────────────────────────────────

	/**
	 * @testdox An SDK failure surfaces as the converted, merchant-facing error
	 */
	public function test_sdk_failure_surfaces_as_the_converted_error(): void {
		$service = $this->service( new Failing_Returns_Http_Client() );
		$fields  = $this->call_private( $service, 'extract_fields', $this->item_info(), array() );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid PostNL API credentials. (traceId: trace-ret)' );

		$service->expose_generate_return( Request_Builder::build( $fields ), $fields );
	}

	/**
	 * @testdox A failed return call is logged at error level with the original SDK cause
	 *
	 * Exception_Converter replaces the SDK message with a merchant-safe one and keeps
	 * the original only as the previous exception, which nothing else reads. One of
	 * its variants tells the merchant to check these very logs, so without this line
	 * the message would point at a file where nothing had been written.
	 */
	public function test_failed_return_call_is_logged_with_the_original_cause(): void {
		$logger  = new Spy_Returns_Logger();
		$service = $this->service( new Failing_Returns_Http_Client(), $logger );
		$fields  = $this->call_private( $service, 'extract_fields', $this->item_info(), array() );

		try {
			$service->expose_generate_return( Request_Builder::build( $fields ), $fields );
		} catch ( \Exception $error ) {
			unset( $error );
		}

		$this->assertCount( 1, $logger->records, 'Exactly one entry must be written for a failed call.' );
		$this->assertSame( 'error', $logger->records[0]['level'] );
		$this->assertStringContainsString( 'ORDER-4242', $logger->records[0]['message'], 'The order number makes the entry traceable.' );
		$this->assertStringContainsString( 'apiKey header missing or invalid', $logger->records[0]['message'], 'The original SDK detail must survive.' );
	}

	/**
	 * @testdox A successful return call writes nothing to the log
	 */
	public function test_successful_return_call_is_not_logged(): void {
		$logger  = new Spy_Returns_Logger();
		$service = $this->service( new Succeeding_Returns_Http_Client(), $logger );
		$fields  = $this->call_private( $service, 'extract_fields', $this->item_info(), array() );

		$service->expose_generate_return( Request_Builder::build( $fields ), $fields );

		$this->assertSame( array(), $logger->records );
	}
}

/**
 * Service exposing the protected transport method and standing in for the legacy
 * pipeline, which needs a WooCommerce order.
 */
class Testable_Returns_Service extends Service {

	/**
	 * How many times the legacy fallback ran.
	 *
	 * @var int
	 */
	public int $pipeline_calls = 0;

	/**
	 * Record the fallback instead of running the WooCommerce-bound pipeline.
	 *
	 * @param array $post_data Order post data.
	 * @return array
	 */
	protected function maybe_create_return_label_pipeline( $post_data ) {
		unset( $post_data );
		++$this->pipeline_calls;

		return array( 'legacy-pipeline' => true );
	}

	/**
	 * Reach the protected transport method.
	 *
	 * @param \Postnl\Sdk\Service\ReturnShipment\V4\Request\ReturnShipmentRequest $request Built request.
	 * @param array                                                              $fields  Flattened fields.
	 * @return \Postnl\Sdk\Service\ReturnShipment\V4\Response\GenerateReturnResponseInterface
	 */
	public function expose_generate_return( $request, array $fields ) {
		return $this->generate_return( $request, $fields );
	}
}

/**
 * Return item info with the parsed properties set directly, skipping the
 * WooCommerce-bound parent constructor.
 */
class Fake_Return_Item_Info extends Item_Info {

	/**
	 * @param string $customer_country Customer (return sender) country.
	 * @param string $printer_type     Merchant's combined printer-type string.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Deliberately skips the WooCommerce-bound parent constructor.
	public function __construct( string $customer_country = 'NL', string $printer_type = 'GraphicFile|JPG 600 dpi' ) {
		// The store is always Dutch here, so only the customer's country can decide
		// eligibility. The plugin refuses to run on a store outside NL or BE.
		$this->shipper  = array( 'country' => 'NL' );
		$this->receiver = array(
			'company'      => '',
			'first_name'   => 'Jan',
			'last_name'    => 'Jansen',
			'address_1'    => 'Kalverstraat',
			'house_number' => '9',
			'address_2'    => 'A',
			'postcode'     => '1234AB',
			'city'         => 'Amsterdam',
			'country'      => $customer_country,
		);
		$this->customer = array(
			'return_company'     => 'My Shop',
			'return_address_1'   => 'Antwoordnummer',
			'return_address_2'   => '12345',
			'return_address_zip' => '1000AA',
			'return_address_city' => 'Amsterdam',
		);
		$this->shipment = array(
			'email'        => 'buyer@example.com',
			'phone'        => '0612345678',
			'order_number' => 'ORDER-4242',
			'total_weight' => 1500,
			'printer_type' => $printer_type,
		);
	}
}

/**
 * Minimal settings stand-in for Client_Factory, which only reads the customer
 * credentials off the object it is handed.
 */
class Returns_Client_Factory_Settings {

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
class Spy_Returns_Client_Factory extends Client_Factory {

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
class Failing_Returns_Http_Client implements ClientInterface {

	/**
	 * The most recent outgoing request, captured for header assertions.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Return the canned error response.
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
					'title'   => 'Unauthorized',
					'detail'  => 'apiKey header missing or invalid',
					'traceId' => 'trace-ret',
				)
			)
		);
	}
}

/**
 * PSR-18 client answering with a minimal successful return/generate body.
 */
class Succeeding_Returns_Http_Client implements ClientInterface {

	/**
	 * The most recent outgoing request.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Return the canned success response.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return new Response(
			200,
			array( 'Content-Type' => 'application/json' ),
			(string) json_encode(
				array(
					'items' => array(
						array(
							'barcode' => '3SDEVCRET77',
							'labels'  => array(
								array(
									'label'      => base64_encode( 'JPG-BYTES' ),
									'outputType' => 'jpg',
									'labelType'  => 'Return Label',
								),
							),
						),
					),
				)
			)
		);
	}
}

/**
 * PSR-3 logger that records every write for assertion.
 */
class Spy_Returns_Logger extends AbstractLogger {

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
