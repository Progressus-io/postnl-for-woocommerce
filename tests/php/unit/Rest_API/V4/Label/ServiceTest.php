<?php
/**
 * Unit tests for Rest_API\V4\Label\Service.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Label;

use Brain\Monkey\Functions;
use GuzzleHttp\Psr7\Response;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Label\Request_Builder;
use PostNLWooCommerce\Rest_API\V4\Label\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Label\Service
 */
class ServiceTest extends UnitTestCase {

	/**
	 * API key the Service is constructed with in these tests.
	 */
	private const V4_KEY = 'v4-label-secret';

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
	 * overriding exactly the getters it reads.
	 *
	 * The service extends Order\Base, which resolves its settings through
	 * Settings::get_instance() rather than a constructor parameter, so the double
	 * has to go in through the singleton. Every unstubbed parent method is left
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
	 * A representative happy-path domestic parcel field set, matching the shape
	 * Service::extract_fields() produces and Request_Builder consumes.
	 *
	 * @return array
	 */
	private function domestic_fields(): array {
		return array(
			'sender'        => array(
				'company'          => 'My Shop',
				'street'           => 'Siriusdreef',
				'house_number'     => '42',
				'house_number_ext' => '',
				'postcode'         => '2132WT',
				'city'             => 'Hoofddorp',
				'country'          => 'NL',
			),
			'receiver'      => array(
				'company'          => '',
				'first_name'       => 'Jan',
				'last_name'        => 'Jansen',
				'street'           => 'Kalverstraat',
				'house_number'     => '9',
				'house_number_ext' => 'A',
				'postcode'         => '1234 AB',
				'city'             => 'Amsterdam',
				'country'          => 'NL',
				'email'            => 'buyer@example.com',
				'phone'            => '0612345678',
			),
			'shipment_type' => 'parcel',
			'weight_gr'     => 2000,
			'reference'     => 'ORDER-1001',
			'barcode'       => '3SDEVC1234567',
			'label'         => array(
				'output_type' => 'pdf',
				'resolution'  => 200,
			),
		);
	}

	// ── Client construction ──────────────────────────────────────────────────

	/**
	 * @testdox The SDK client authenticates with the injected key, not one re-read from settings
	 *
	 * The key is resolved and validated by Service_Factory before it decides V4 may
	 * run at all. Re-deriving it inside the service duplicated that decision behind
	 * a fallback that sent an empty key, so this pins the key travelling in through
	 * the constructor and reaching the wire.
	 */
	public function test_client_authenticates_with_the_injected_key(): void {
		$http    = new Failing_Http_Client();
		$factory = new Spy_Label_Client_Factory( new Client_Factory_Settings(), $http );
		$service = new Testable_Label_Service( $factory, self::V4_KEY, new NullLogger() );

		try {
			$service->expose_confirm_label( Request_Builder::build( $this->domestic_fields() ), $this->domestic_fields() );
		} catch ( \Exception $error ) {
			unset( $error );
		}

		$this->assertNotNull( $http->last_request, 'The request must reach the transport.' );
		$this->assertSame( self::V4_KEY, $http->last_request->getHeaderLine( 'apiKey' ) );
	}

	// ── Error handling ───────────────────────────────────────────────────────

	/**
	 * @testdox An SDK failure surfaces as the converted, merchant-facing error
	 */
	public function test_sdk_failure_surfaces_as_the_converted_error(): void {
		$factory = new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() );
		$service = new Testable_Label_Service( $factory, self::V4_KEY, new NullLogger() );

		$this->expectException( \Exception::class );
		$this->expectExceptionMessage( 'Invalid PostNL API credentials. (traceId: trace-abc)' );
		$this->expectExceptionCode( 401 );

		$service->expose_confirm_label( Request_Builder::build( $this->domestic_fields() ), $this->domestic_fields() );
	}

	// ── Logging ──────────────────────────────────────────────────────────────

	/**
	 * @testdox A failed label call is logged at error level with the original SDK cause
	 *
	 * Exception_Converter deliberately replaces the SDK message with a merchant-safe
	 * one and keeps the original only as the previous exception, which nothing else
	 * reads. Legacy label generation logs every request and response through
	 * Rest_API\Base::send_request(); without this line the V4 path would be the only
	 * label flow whose first production failure leaves no trail at all.
	 */
	public function test_failed_label_call_is_logged_with_the_original_cause(): void {
		$logger  = new Spy_Logger();
		$factory = new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() );
		$service = new Testable_Label_Service( $factory, self::V4_KEY, $logger );

		$cause = null;

		try {
			$service->expose_confirm_label( Request_Builder::build( $this->domestic_fields() ), $this->domestic_fields() );
			$this->fail( 'The converted SDK error must propagate.' );
		} catch ( \Exception $error ) {
			$cause = $error->getPrevious();
		}

		$this->assertNotNull( $cause, 'Exception_Converter must preserve the SDK exception as the cause.' );
		$this->assertNotSame( '', $cause->getMessage(), 'A blank cause message would make the assertion below vacuous.' );

		$errors = $this->error_records( $logger );

		$this->assertCount( 1, $errors, 'The failure must be logged exactly once, at error level.' );
		$this->assertStringContainsString(
			$cause->getMessage(),
			$errors[0]['message'],
			'The safe message alone is not actionable; the original SDK message has to reach the log.'
		);
		$this->assertStringContainsString( get_class( $cause ), $errors[0]['message'], 'The cause class identifies where the failure came from.' );
	}

	/**
	 * @testdox The failure log names the order and the destination area, and no more
	 *
	 * The order reference is what makes the line traceable back to a shipment; the
	 * country plus postcode area is what lets support reproduce the lookup. Legacy
	 * send_request() dumps the whole body — name, street, city, email, phone — into
	 * the same WooCommerce log, and this line is written whether or not anyone is
	 * debugging, so none of that may travel with it.
	 */
	public function test_failure_log_is_trimmed_to_order_and_destination_area(): void {
		$logger  = new Spy_Logger();
		$factory = new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() );
		$service = new Testable_Label_Service( $factory, self::V4_KEY, $logger );

		try {
			$service->expose_confirm_label( Request_Builder::build( $this->domestic_fields() ), $this->domestic_fields() );
		} catch ( \Exception $error ) {
			unset( $error );
		}

		$errors = $this->error_records( $logger );
		$this->assertCount( 1, $errors );
		$message = $errors[0]['message'];

		$this->assertStringContainsString( 'ORDER-1001', $message, 'The order reference is what makes the entry traceable.' );
		$this->assertStringContainsString( 'NL 1234', $message, 'Country and postcode area let support reproduce the call.' );

		foreach ( array( 'Jan', 'Jansen', 'Kalverstraat', 'Amsterdam', 'buyer@example.com', '0612345678' ) as $personal ) {
			$this->assertStringNotContainsString( $personal, $message, "Recipient detail '{$personal}' must not reach the log." );
		}

		$this->assertStringNotContainsString( '1234AB', $message, 'Only the postcode area may be logged, not the full postcode.' );
		$this->assertStringNotContainsString( self::V4_KEY, $message, 'The API key must never reach the log.' );
	}

	/**
	 * Error-level records from a spy logger, re-indexed.
	 *
	 * @param Spy_Logger $logger Spy logger to read.
	 * @return array<int, array<string, mixed>>
	 */
	private function error_records( Spy_Logger $logger ): array {
		return array_values(
			array_filter( $logger->records, static fn( array $record ) => LogLevel::ERROR === $record['level'] )
		);
	}
}

/**
 * Exposes the protected send-and-convert seam so the failure path can be driven
 * without building a WooCommerce order and a full Shipping\Item_Info.
 */
class Testable_Label_Service extends Service {

	/**
	 * Public wrapper for confirm_label().
	 *
	 * @param ShipmentDeliveryRequest $request Built labelconfirm request.
	 * @param array                   $fields  Field set the request was built from.
	 * @return \Postnl\Sdk\Service\ShipmentDelivery\V4\Response\LabelConfirmResponseInterface
	 */
	public function expose_confirm_label( ShipmentDeliveryRequest $request, array $fields ) {
		return $this->confirm_label( $request, $fields );
	}
}

/**
 * Minimal settings stand-in for Client_Factory, which only reads the customer
 * credentials off the object it is handed.
 */
class Client_Factory_Settings {

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
class Spy_Label_Client_Factory extends Client_Factory {

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
class Failing_Http_Client implements ClientInterface {

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
					'traceId' => 'trace-abc',
				)
			)
		);
	}
}

/**
 * PSR-3 logger that records every write for assertion.
 */
class Spy_Logger extends AbstractLogger {

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
