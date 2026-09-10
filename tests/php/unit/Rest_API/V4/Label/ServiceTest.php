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
use Postnl\Sdk\Client\ResponseMeta;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\ResponseData\V4\LabelsCollection;
use Postnl\Sdk\ResponseData\V4\WarningsCollection;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseInterface;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItem;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItemsCollection;
use PostNLWooCommerce\Rest_API\Legacy\Shipping;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Label\Request_Builder;
use PostNLWooCommerce\Rest_API\V4\Label\Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
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
		$this->clear_uploads_dir();
		$this->reset_settings_singleton();
		parent::tearDown();
	}

	/**
	 * Remove every label file a test wrote, so a filename reused by the next test
	 * is written afresh rather than short-circuited by write_label_file()'s
	 * "already on disk" guard.
	 *
	 * @return void
	 */
	private function clear_uploads_dir(): void {
		foreach ( glob( POSTNL_UPLOADS_DIR . '*' ) ?: array() as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file );
			}
		}
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
	// ── Insured amount ───────────────────────────────────────────────────────

	/**
	 * @testdox The insured amount sent to PostNL is the order item subtotal
	 *
	 * This is the only number in the flow that becomes the declared value of the
	 * shipment, and the wiring from shipment['subtotal'] through resolve_services()
	 * was previously asserted nowhere -- replacing it with 0, or with any other key
	 * on the shipment array, kept the whole suite green. The legacy Amounts block
	 * uses WC_Order::get_subtotal() (Legacy/Shipping/Client.php), so anything that
	 * adds tax or shipping here is a silent divergence from V1 that only shows up
	 * when a merchant claims on a lost parcel.
	 *
	 * The fixture keeps subtotal, weight and zero as three distinct numbers so
	 * reading the wrong key cannot coincidentally pass.
	 */
	public function test_insured_amount_is_the_order_item_subtotal(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$item_info = new Fake_Shipping_Item_Info(
			array(
				'subtotal'     => 42.00,
				'total_weight' => 1500,
				'order_number' => 'ORDER-1001',
				'printer_type' => 'GraphicFile|PDF',
				'currency'     => 'EUR',
			)
		);

		$fields = $this->extract_fields(
			$service,
			$item_info,
			array(
				'shipmentType' => 'parcel',
				'services'     => array(
					'deliveryConfirmation' => 'signature',
					'insuredValue'         => '<order_total>',
				),
			),
			array( 'main_barcode' => '3SDEVC1234567' )
		);

		$this->assertSame(
			42.00,
			$fields['services']['insuredValue'],
			'The insured amount must be the item subtotal, resolved from the mapper placeholder.'
		);
		$this->assertSame(
			'signature',
			$fields['services']['deliveryConfirmation'],
			'Other service flags must pass through the placeholder substitution unchanged.'
		);
	}

	/**
	 * @testdox A parcel with no mapped services sends no service flags at all
	 */
	public function test_parcel_without_mapped_services_sends_none(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$fields = $this->extract_fields(
			$service,
			new Fake_Shipping_Item_Info( array( 'subtotal' => 42.00 ) ),
			array( 'shipmentType' => 'parcel', 'services' => array() ),
			array()
		);

		$this->assertSame( array(), $fields['services'] );
	}

	/**
	 * @testdox The collo count reaches the builder even when no barcodes are supplied
	 *
	 * The harvest path calls create() with neither barcodes[] nor main_barcode, so
	 * num_labels is the only thing left that tells Request_Builder how many items to
	 * emit. The fixture uses a count other than the schema default of 1 so the
	 * assertion cannot pass on the fallback.
	 */
	public function test_extract_fields_carries_the_collo_count(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$fields = $this->extract_fields(
			$service,
			new Fake_Shipping_Item_Info( array(), array(), array( 'num_labels' => 3 ) ),
			array( 'shipmentType' => 'parcel', 'services' => array() ),
			array()
		);

		$this->assertSame( 3, $fields['num_labels'] );
		$this->assertSame( array(), $fields['barcodes'], 'The harvest path supplies no pre-issued barcodes.' );
		$this->assertSame( '', $fields['barcode'] );
	}

	/**
	 * @testdox Pre-issued barcodes beyond the parsed collo count are dropped
	 *
	 * The num_labels sanitizer clamps the merchant's entry to at most 10, and the V1
	 * request loop iterates that clamped value, but Order\Base::maybe_create_multi_barcodes()
	 * pre-issues barcodes straight off the raw backend value -- the meta-box number
	 * field has a min and no max. Without the slice a merchant who types 15 would ship
	 * 10 colli on V1 and 15 on V4, off an eligibility signal that only ever saw 10.
	 * The fixture supplies more barcodes than the ceiling so the count cannot match
	 * by accident.
	 */
	public function test_extract_fields_clamps_pre_issued_barcodes_to_the_collo_count(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$prefetched = array();
		for ( $i = 1; $i <= 12; $i++ ) {
			$prefetched[] = sprintf( '3SDEVC%02d', $i );
		}

		$fields = $this->extract_fields(
			$service,
			new Fake_Shipping_Item_Info( array(), array(), array( 'num_labels' => 10 ) ),
			array( 'shipmentType' => 'parcel', 'services' => array() ),
			array( 'barcodes' => $prefetched )
		);

		$this->assertCount( 10, $fields['barcodes'], 'The wire collo count must stop at the sanitizer ceiling legacy applies.' );
		$this->assertSame( array_slice( $prefetched, 0, 10 ), $fields['barcodes'], 'The surviving barcodes are the first ten, in order.' );

		$below_ceiling = $this->extract_fields(
			$service,
			new Fake_Shipping_Item_Info( array(), array(), array( 'num_labels' => 3 ) ),
			array( 'shipmentType' => 'parcel', 'services' => array() ),
			array( 'barcodes' => $prefetched )
		);

		$this->assertSame(
			array_slice( $prefetched, 0, 3 ),
			$below_ceiling['barcodes'],
			'The cut follows the parsed collo count, not the sanitizer ceiling.'
		);
	}

	// ── Storing the labelconfirm response ────────────────────────────────────

	/**
	 * @testdox The merged label is keyed on the response barcode when none was pre-issued
	 *
	 * On the harvest path save_meta_value() calls create() with neither barcodes[]
	 * nor main_barcode, so the fallback list is a single empty string. Keying the
	 * merge on it stamps an empty barcode onto the record maybe_merge_labels()
	 * rebuilds -- which is every path except exactly one label in A6 -- and
	 * harvest_barcodes_or_fail() then deletes the labels just written and aborts
	 * the whole save. The merge key has to come from the barcode the response
	 * issued instead.
	 */
	public function test_merge_is_keyed_on_the_response_barcode_when_none_was_preissued(): void {
		$service = $this->merge_spy_service();

		$this->store_labels(
			$service,
			$this->label_response( $this->shipment_item( '3SRESP1' ), $this->shipment_item( '3SRESP2' ) ),
			array( '' )
		);

		$this->assertSame(
			'3SRESP1',
			$service->merge_barcode,
			'The merge must be keyed on the first response barcode; an empty key fails the barcode harvest.'
		);
	}

	/**
	 * @testdox A pre-issued parent barcode still wins as the merge key
	 *
	 * Legacy prefetches the barcodes and passes them in; that value is what the
	 * persisted barcodes[] and the tracking URL are built from, so the response
	 * barcode must not displace it.
	 */
	public function test_preissued_parent_barcode_remains_the_merge_key(): void {
		$service = $this->merge_spy_service();

		$this->store_labels(
			$service,
			$this->label_response( $this->shipment_item( '3SRESP1' ), $this->shipment_item( '3SRESP2' ) ),
			array( '3SMAIN', '3SB2' )
		);

		$this->assertSame( '3SMAIN', $service->merge_barcode, 'The pre-issued parent barcode must survive untouched.' );
	}

	/**
	 * @testdox Every collo in the response reaches the merge
	 *
	 * A multi-collo labelconfirm answers with one shipment item per collo, each
	 * carrying its own label. Storing only the first would silently ship a single
	 * sheet for a three-parcel order.
	 */
	public function test_every_response_collo_reaches_the_merge(): void {
		$service = $this->merge_spy_service();

		$this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1' ),
				$this->shipment_item( '3SRESP2' ),
				$this->shipment_item( '3SRESP3' )
			),
			array( '' )
		);

		$this->assertCount( 3, $service->merge_records, 'Every collo contributes a label record to the merge.' );
		$this->assertSame(
			array( '3SRESP1', '3SRESP2', '3SRESP3' ),
			array_column( $service->merge_records, 'barcode' ),
			'Each record keeps its own collo barcode.'
		);
	}

	/**
	 * @testdox Each collo falls back to its own pre-issued barcode, not the parent's
	 *
	 * When the response omits the barcodes, the pre-issued list is the only thing
	 * that tells the collos apart. Collapsing them onto the parent barcode would
	 * persist one barcode three times and lose two of the three tracking numbers.
	 */
	public function test_each_collo_falls_back_to_its_own_preissued_barcode(): void {
		$service = $this->merge_spy_service();

		$this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( null ),
				$this->shipment_item( null ),
				$this->shipment_item( null )
			),
			array( '3SMAIN', '3SB2', '3SB3' )
		);

		$this->assertSame(
			array( '3SMAIN', '3SB2', '3SB3' ),
			array_column( $service->merge_records, 'barcode' ),
			'Each collo record must carry the barcode pre-issued for its own index.'
		);
	}

	// ── Collo count mismatch between the request and the response ────────────

	/**
	 * @testdox Fewer response items than pre-issued barcodes is logged as a warning
	 *
	 * Order\Base::save_meta_value() persists every prefetched barcode to order meta
	 * regardless of what the label call answered, so a short response leaves the
	 * merchant with three tracking numbers and a two-label sheet, and the third
	 * barcode never confirmed. Storing what came back is still the right move --
	 * partial labels beat none -- but it must not happen in silence.
	 */
	public function test_fewer_response_items_than_preissued_barcodes_is_logged(): void {
		$logger  = new Spy_Logger();
		$service = $this->merge_spy_service( $logger );

		$this->store_labels(
			$service,
			$this->label_response( $this->shipment_item( '3SRESP1' ), $this->shipment_item( '3SRESP2' ) ),
			array( '3SMAIN', '3SB2', '3SB3' )
		);

		$warnings = $this->warning_records( $logger );

		$this->assertCount( 1, $warnings, 'A collo count mismatch is worth exactly one warning.' );
		$this->assertStringContainsString( '3', $warnings[0]['message'], 'The warning has to carry the count that was expected.' );
		$this->assertStringContainsString( '2', $warnings[0]['message'], 'The warning has to carry the count that came back.' );
		$this->assertStringContainsString( 'ORDER-5150', $warnings[0]['message'], 'The order reference is what makes the entry traceable.' );
	}

	/**
	 * @testdox More response items than pre-issued barcodes is logged as a warning
	 *
	 * The surplus direction is just as wrong and just as silent: the extra collo's
	 * label is written and merged, but no barcode for it is ever persisted, so the
	 * sheet carries a parcel the order meta does not know about.
	 */
	public function test_more_response_items_than_preissued_barcodes_is_logged(): void {
		$logger  = new Spy_Logger();
		$service = $this->merge_spy_service( $logger );

		$this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1' ),
				$this->shipment_item( '3SRESP2' ),
				$this->shipment_item( '3SRESP3' )
			),
			array( '3SMAIN', '3SB2' )
		);

		$this->assertCount( 1, $this->warning_records( $logger ), 'A surplus of response items is a mismatch too.' );
	}

	/**
	 * @testdox Matching collo counts log nothing
	 */
	public function test_matching_collo_counts_log_nothing(): void {
		$logger  = new Spy_Logger();
		$service = $this->merge_spy_service( $logger );

		$this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1' ),
				$this->shipment_item( '3SRESP2' ),
				$this->shipment_item( '3SRESP3' )
			),
			array( '3SMAIN', '3SB2', '3SB3' )
		);

		$this->assertSame( array(), $this->warning_records( $logger ), 'One response item per pre-issued barcode is the healthy case.' );
	}

	/**
	 * @testdox The harvest path, which pre-issues no barcodes, is never a mismatch
	 *
	 * create() hands store_labels() a single empty string when nothing was
	 * pre-issued, and the label call then issues one barcode per collo. A
	 * three-item response against that one-element list is correct, so comparing
	 * the raw list length would warn on every multi-collo harvest and train
	 * merchants to ignore the log.
	 */
	public function test_the_harvest_path_is_never_a_collo_count_mismatch(): void {
		$logger  = new Spy_Logger();
		$service = $this->merge_spy_service( $logger );

		$this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1' ),
				$this->shipment_item( '3SRESP2' ),
				$this->shipment_item( '3SRESP3' )
			),
			array( '' )
		);

		$this->assertSame( array(), $this->warning_records( $logger ), 'No barcode was pre-issued, so there is no count to disagree with.' );
	}

	/**
	 * @testdox The merged record carries the parent collo's partner references
	 *
	 * item_label_records() captures each collo's own partner barcode and id, but
	 * maybe_merge_labels() rebuilds one record from the whole set and keeps only
	 * type/barcode/created_at/filepath/merged_files -- so exactly one collo's refs
	 * can survive, and it has to be the parent's, the one the merged sheet is keyed
	 * on. The fixture gives every collo distinct refs, so wiring a later collo's
	 * values by mistake cannot coincidentally pass.
	 */
	public function test_the_merged_record_carries_the_parent_collos_partner_references(): void {
		$service = $this->merge_spy_service();

		$labels = $this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1', 'CE111111111NL', 'PARTNER-1' ),
				$this->shipment_item( '3SRESP2', 'CE222222222NL', 'PARTNER-2' )
			),
			array( '3SMAIN', '3SB2' )
		);

		$this->assertSame( 'CE111111111NL', $labels['label']['partner_barcode'], 'The merged sheet must carry the parent collo partner barcode.' );
		$this->assertSame( 'PARTNER-1', $labels['label']['partner_id'], 'The merged sheet must carry the parent collo partner id.' );
	}

	/**
	 * @testdox Every collo's partner references are kept on the merged record
	 *
	 * The flat partner_barcode/partner_id keys can hold one collo's refs, so the
	 * merged record additionally lists every collo's own refs under
	 * partner_references. Distinct fixtures per collo, so dropping a collo or
	 * wiring the wrong one cannot coincidentally pass.
	 */
	public function test_every_collos_partner_references_are_kept_on_the_merged_record(): void {
		$service = $this->merge_spy_service();

		$labels = $this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1', 'CE111111111NL', 'PARTNER-1' ),
				$this->shipment_item( '3SRESP2', 'CE222222222NL', 'PARTNER-2' )
			),
			array( '3SMAIN', '3SB2' )
		);

		$this->assertSame(
			array(
				array(
					'barcode'         => '3SRESP1',
					'partner_barcode' => 'CE111111111NL',
					'partner_id'      => 'PARTNER-1',
				),
				array(
					'barcode'         => '3SRESP2',
					'partner_barcode' => 'CE222222222NL',
					'partner_id'      => 'PARTNER-2',
				),
			),
			$labels['label']['partner_references'],
			'The merged record must list every collo with its own partner references.'
		);
	}

	/**
	 * @testdox A shipment with no partner data gains no partner_references key
	 *
	 * Matches the flat keys' rule: a domestic record carries no partner keys at
	 * all, so it must not sprout an empty partner_references list either.
	 */
	public function test_a_shipment_with_no_partner_data_gains_no_partner_references_key(): void {
		$service = $this->merge_spy_service();

		$labels = $this->store_labels(
			$service,
			$this->label_response(
				$this->shipment_item( '3SRESP1' ),
				$this->shipment_item( '3SRESP2' )
			),
			array( '3SMAIN', '3SB2' )
		);

		$this->assertArrayNotHasKey( 'partner_references', $labels['label'] );
	}

	/**
	 * A Service whose merge step is spied on, with the transport stubbed out and
	 * the WordPress helpers store_labels() reaches replaced.
	 *
	 * @param LoggerInterface|null $logger Logger to inject; defaults to a silent one.
	 * @return Merge_Spy_Label_Service
	 */
	private function merge_spy_service( ?LoggerInterface $logger = null ): Merge_Spy_Label_Service {
		$this->seed_label_write_stubs();

		return new Merge_Spy_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			$logger ?? new NullLogger()
		);
	}

	/**
	 * Stub the WordPress helpers the label write path calls.
	 *
	 * The directory creation and the file write stay real: store_labels() drops any
	 * record whose file is missing afterwards, so a no-op writer would silently
	 * turn every assertion below into a check on an empty array.
	 *
	 * @return void
	 */
	private function seed_label_write_stubs(): void {
		Functions\when( 'trailingslashit' )->alias( static fn( $path ) => rtrim( (string) $path, '/\\' ) . '/' );
		Functions\when( 'wp_mkdir_p' )->alias( static fn( $dir ) => is_dir( $dir ) || mkdir( $dir, 0777, true ) );
		Functions\when( 'sanitize_title' )->alias( static fn( $title ) => strtolower( str_replace( ' ', '-', (string) $title ) ) );
		Functions\when( 'current_time' )->justReturn( 1700000000 );
	}

	/**
	 * One collo of a labelconfirm response, carrying a single PDF label.
	 *
	 * @param string|null $barcode         Barcode the response issued for this collo, or null when it omits one.
	 * @param string|null $partner_barcode International partner barcode for this collo, or null for a domestic one.
	 * @param string|null $partner_id      International partner id for this collo, or null for a domestic one.
	 * @return ShipmentDeliveryResponseItem
	 */
	private function shipment_item( ?string $barcode, ?string $partner_barcode = null, ?string $partner_id = null ): ShipmentDeliveryResponseItem {
		$label = new Label(
			label: base64_encode( 'PDF-BYTES-' . (string) $barcode ),
			outputType: LabelOutputType::PDF,
			labelType: 'Label'
		);

		return new ShipmentDeliveryResponseItem(
			barcode: $barcode,
			labels: new LabelsCollection( array( $label ) ),
			partnerId: $partner_id,
			partnerBarcode: $partner_barcode
		);
	}

	/**
	 * Wrap shipment items in a stub labelconfirm response exposing items().
	 *
	 * @param ShipmentDeliveryResponseItem ...$items Collo items to expose.
	 * @return ShipmentDeliveryResponseInterface
	 */
	private function label_response( ShipmentDeliveryResponseItem ...$items ): ShipmentDeliveryResponseInterface {
		$collection = new ShipmentDeliveryResponseItemsCollection( $items );

		return new class( $collection ) implements ShipmentDeliveryResponseInterface {
			public function __construct( private ShipmentDeliveryResponseItemsCollection $items ) {}

			public function items(): ShipmentDeliveryResponseItemsCollection {
				return $this->items;
			}

			public function meta(): ResponseMeta {
				throw new \LogicException( 'meta() is not exercised by these tests.' );
			}

			public function warnings(): WarningsCollection {
				throw new \LogicException( 'warnings() is not exercised by these tests.' );
			}
		};
	}

	/**
	 * Call Service::store_labels(), which is private, for the same reason as
	 * extract_fields() below: nothing outside the class calls it.
	 *
	 * @param Service                       $service   Service under test.
	 * @param ShipmentDeliveryResponseInterface $response  Stub labelconfirm response.
	 * @param array                         $fallbacks Pre-issued barcodes per collo.
	 * @return array
	 */
	private function store_labels( Service $service, ShipmentDeliveryResponseInterface $response, array $fallbacks ): array {
		$method = new \ReflectionMethod( Service::class, 'store_labels' );
		$method->setAccessible( true );

		return $method->invoke( $service, $response, new Fake_Order( 5150 ), $fallbacks );
	}

	// ── Partner references after the merge ───────────────────────────────────

	/**
	 * @testdox Partner references survive the label merge that rebuilds the record
	 *
	 * Order\Base::maybe_merge_labels() returns the mapper's record untouched only
	 * when there is exactly one label and the format is A6. Every other path -- and
	 * a ROW response always takes one, since it returns four documents -- discards
	 * the record and builds a fresh one holding type/barcode/created_at/filepath/
	 * merged_files. The fixture below is exactly that rebuilt shape, so the partner
	 * barcode and id captured off the labelconfirm response reach order meta only
	 * if they are re-attached afterwards.
	 *
	 * The values are deliberately distinct from the barcode so reading the wrong
	 * key cannot coincidentally pass.
	 */
	public function test_partner_references_survive_the_label_merge(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$labels = $this->finalize_label_records( $service, $this->merged_label_records(), 'CE123456789NL', 'PARTNER-77' );

		$this->assertArrayHasKey( 'label', $labels );
		$this->assertSame( 'v4', $labels['label']['api_version'], 'The rebuilt record must still be tagged as V4.' );
		$this->assertSame( 'CE123456789NL', $labels['label']['partner_barcode'], 'The partner barcode must survive the merge.' );
		$this->assertSame( 'PARTNER-77', $labels['label']['partner_id'], 'The partner id must survive the merge.' );
	}

	/**
	 * @testdox A record with no partner references gains no empty partner keys
	 *
	 * Response_Mapper::to_label_record() omits both keys when the response carries
	 * no partner data, so a domestic label record must not sprout them here either.
	 */
	public function test_absent_partner_references_add_no_partner_keys(): void {
		$service = new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			new NullLogger()
		);

		$labels = $this->finalize_label_records( $service, $this->merged_label_records(), '', '' );

		$this->assertSame( 'v4', $labels['label']['api_version'], 'The V4 tag is unconditional.' );
		$this->assertArrayNotHasKey( 'partner_barcode', $labels['label'] );
		$this->assertArrayNotHasKey( 'partner_id', $labels['label'] );
	}

	// ── Unmappable international enum values ─────────────────────────────────

	/**
	 * @testdox A customs currency the SDK enum does not carry is logged as a warning
	 *
	 * Request_Builder drops the currency silently when Currency::tryFrom misses, and
	 * the SDK enum carries only thirteen currencies -- a store selling in HUF would
	 * otherwise send a customs declaration with no currency at all and never find out.
	 * The value is still handed to the builder unchanged: the builder owns the
	 * omission, this class only reports it.
	 */
	public function test_unsupported_customs_currency_is_logged(): void {
		$logger  = new Spy_Logger();
		$service = $this->service_with_logger( $logger );

		$fields = $this->extract_fields(
			$service,
			$this->international_item_info( 'HUF', 'nl' ),
			$this->international_mapped(),
			array()
		);

		$warnings = $this->warning_records( $logger );

		$this->assertCount( 1, $warnings, 'Exactly one value fails to map, so exactly one warning is due.' );
		$this->assertStringContainsString( 'currency', $warnings[0]['message'], 'The warning has to name the field that will be dropped.' );
		$this->assertStringContainsString( 'HUF', $warnings[0]['message'], 'The raw value is what the merchant has to change.' );
		$this->assertStringContainsString( 'ORDER-1001', $warnings[0]['message'], 'The order reference is what makes the entry traceable.' );

		$this->assertSame(
			'HUF',
			$fields['international']['customs']['currency'],
			'Service only reports the miss; dropping the field stays the builder decision.'
		);
	}

	/**
	 * @testdox A customs country of origin the SDK enum does not carry is logged as a warning
	 *
	 * Country::tryFrom takes the two-letter ISO code; a full country name saved on a
	 * product ships a customs line with no origin, which is exactly the field customs
	 * authorities hold a parcel over.
	 */
	public function test_unknown_customs_country_of_origin_is_logged(): void {
		$logger  = new Spy_Logger();
		$service = $this->service_with_logger( $logger );

		$this->extract_fields(
			$service,
			$this->international_item_info( 'eur', 'Netherlands' ),
			$this->international_mapped(),
			array()
		);

		$warnings = $this->warning_records( $logger );

		$this->assertCount( 1, $warnings, 'Only the country of origin fails to map here.' );
		$this->assertStringContainsString( 'country of origin', $warnings[0]['message'], 'The warning has to name the field that will be dropped.' );
		$this->assertStringContainsString( 'Netherlands', $warnings[0]['message'], 'The raw value is what the merchant has to change.' );
		$this->assertStringContainsString( 'ORDER-1001', $warnings[0]['message'], 'The order reference is what makes the entry traceable.' );
	}

	/**
	 * @testdox Values the builder maps after upper-casing produce no warning
	 *
	 * The check has to mirror Request_Builder's own normalization, not merely compare
	 * against the enum: the builder upper-cases the currency and the country of origin
	 * before tryFrom, so a lowercase 'eur' reaches PostNL perfectly well and warning
	 * about it would train merchants to ignore the log.
	 */
	public function test_values_the_builder_normalizes_produce_no_warning(): void {
		$logger  = new Spy_Logger();
		$service = $this->service_with_logger( $logger );

		$this->extract_fields(
			$service,
			$this->international_item_info( 'eur', 'nl' ),
			$this->international_mapped(),
			array()
		);

		$this->assertSame( array(), $this->warning_records( $logger ), 'A value the builder maps fine must not be reported as unmappable.' );
	}

	/**
	 * A Service wired to the given logger, with the transport stubbed out.
	 *
	 * @param Spy_Logger $logger Logger to inject.
	 * @return Testable_Label_Service
	 */
	private function service_with_logger( Spy_Logger $logger ): Testable_Label_Service {
		return new Testable_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), new Failing_Http_Client() ),
			self::V4_KEY,
			$logger
		);
	}

	/**
	 * A V4_Mapper result for an EU/ROW parcel, i.e. one carrying a service bundle.
	 *
	 * @return array
	 */
	private function international_mapped(): array {
		return array(
			'shipmentType'              => 'parcel',
			'services'                  => array(),
			'internationalShipmentData' => array( 'bundle' => 'insured' ),
		);
	}

	/**
	 * An international order with one customs line, parameterised on the two values
	 * whose enum lookup can miss.
	 *
	 * @param string $currency Order currency as WooCommerce stores it.
	 * @param string $origin   Country of origin as the product meta stores it.
	 * @return Fake_Shipping_Item_Info
	 */
	private function international_item_info( string $currency, string $origin ): Fake_Shipping_Item_Info {
		return new Fake_Shipping_Item_Info(
			array(
				'subtotal'     => 42.00,
				'total_weight' => 1500,
				'order_id'     => '5150',
				'order_number' => 'ORDER-1001',
				'currency'     => $currency,
			),
			array(
				array(
					'description' => 'Blue cotton t-shirt',
					'qty'         => 2,
					'weight'      => 500,
					'value'       => 19.95,
					'origin'      => $origin,
					'hs_code'     => '610910',
				),
			)
		);
	}

	/**
	 * Warning-level records from a spy logger, re-indexed.
	 *
	 * @param Spy_Logger $logger Spy logger to read.
	 * @return array<int, array<string, mixed>>
	 */
	private function warning_records( Spy_Logger $logger ): array {
		return array_values(
			array_filter( $logger->records, static fn( array $record ) => LogLevel::WARNING === $record['level'] )
		);
	}

	/**
	 * A label set in the shape Order\Base::maybe_merge_labels() rebuilds, i.e.
	 * without the api_version and partner keys the mapper had put on the record.
	 *
	 * @return array
	 */
	private function merged_label_records(): array {
		return array(
			'label' => array(
				'type'         => 'label',
				'barcode'      => '3SDEVC1234567',
				'created_at'   => 1700000000,
				'filepath'     => '/uploads/postnl/merged.pdf',
				'merged_files' => array( '/uploads/postnl/cn23.pdf', '/uploads/postnl/label.pdf' ),
			),
		);
	}

	/**
	 * Call Service::finalize_label_records(), which is private.
	 *
	 * Reflection rather than promoting the method, for the same reason as
	 * extract_fields() below: nothing outside the class calls it.
	 *
	 * @param Service $service         Service under test.
	 * @param array   $labels          Merged label records.
	 * @param string  $partner_barcode Partner barcode from the labelconfirm response.
	 * @param string  $partner_id      Partner id from the labelconfirm response.
	 * @return array
	 */
	private function finalize_label_records( Service $service, array $labels, string $partner_barcode, string $partner_id ): array {
		$method = new \ReflectionMethod( Service::class, 'finalize_label_records' );
		$method->setAccessible( true );

		return $method->invoke( $service, $labels, $partner_barcode, $partner_id );
	}

	/**
	 * Call Service::extract_fields(), which is private.
	 *
	 * Reflection rather than promoting the method to protected: the visibility on
	 * the production class should describe what collaborators may call, not what a
	 * test wants to look at. extract_fields() is a pure translation with no
	 * collaborators, so nothing outside the class has a reason to reach it.
	 *
	 * @param Service            $service   Service under test.
	 * @param Shipping\Item_Info $item_info Parsed legacy item info.
	 * @param array              $mapped    V4_Mapper::map() result.
	 * @param array              $post_data Label post data.
	 * @return array
	 */
	private function extract_fields( Service $service, Shipping\Item_Info $item_info, array $mapped, array $post_data ): array {
		$method = new \ReflectionMethod( Service::class, 'extract_fields' );
		$method->setAccessible( true );

		return $method->invoke( $service, $item_info, $mapped, $post_data );
	}

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
	 * @return \Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseInterface
	 */
	public function expose_confirm_label( ShipmentDeliveryRequest $request, array $fields ) {
		return $this->confirm_label( $request, $fields );
	}

}

/**
 * Records what store_labels() hands the merge step, and answers with the exact
 * shape Order\Base::maybe_merge_labels() rebuilds for a multi-record set: type,
 * barcode, created_at, filepath and merged_files, and nothing else.
 *
 * The real merge is not exercised here on purpose -- it reads the label format
 * off the settings, resolves the shipping zone off a WooCommerce order and drives
 * the PDF merger. What this fix is about is the barcode the merge is keyed on,
 * which is decided before any of that.
 */
class Merge_Spy_Label_Service extends Service {

	/**
	 * Barcode the merge was keyed on.
	 *
	 * @var string|null
	 */
	public ?string $merge_barcode = null;

	/**
	 * Label records handed to the merge.
	 *
	 * @var array
	 */
	public array $merge_records = array();

	/**
	 * Record the merge inputs and return the rebuilt record shape.
	 *
	 * @param array    $labels     Label records built from the response.
	 * @param mixed    $order      Order object.
	 * @param string   $barcode    Barcode the merged record is keyed on.
	 * @param string   $label_type Parent label type.
	 * @return array
	 */
	public function maybe_merge_labels( $labels, $order, $barcode, $label_type ) {
		$this->merge_barcode = (string) $barcode;
		$this->merge_records = (array) $labels;

		return array(
			$label_type => array(
				'type'         => $label_type,
				'barcode'      => $barcode,
				'created_at'   => 1700000000,
				'filepath'     => POSTNL_UPLOADS_DIR . 'merged.pdf',
				'merged_files' => array_column( $this->merge_records, 'filepath' ),
			),
		);
	}
}

/**
 * Order stand-in for the label write path, which asks for the order id to build
 * the label filename and for the order number to make a log line traceable.
 */
class Fake_Order {

	/**
	 * Constructor.
	 *
	 * @param int $id Order id.
	 */
	public function __construct( private int $id ) {}

	/**
	 * @return int
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function get_order_number() {
		return 'ORDER-' . $this->id;
	}
}

/**
 * Item_Info stand-in that skips the real constructor, which needs a WooCommerce
 * order. Only the public arrays extract_fields() reads are populated.
 */
class Fake_Shipping_Item_Info extends Shipping\Item_Info {

	/**
	 * @param array $shipment     Parsed shipment data.
	 * @param array $contents     Parsed order line items, as the customs block reads them.
	 * @param array $backend_data Parsed merchant meta-box choices, e.g. num_labels.
	 */
	// phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found -- Deliberately skips the WooCommerce-bound parent constructor.
	public function __construct( array $shipment, array $contents = array(), array $backend_data = array() ) {
		$this->shipment     = $shipment;
		$this->contents     = $contents;
		$this->shipper      = array( 'country' => 'NL' );
		$this->receiver     = array( 'country' => 'NL' );
		$this->backend_data = $backend_data;
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
