<?php
/**
 * Integration tests for the request the V4 label service sends for a real order.
 *
 * Each test drives V4\Label\Service::create() with a WooCommerce order against a
 * fake transport and asserts on the JSON body, so it proves the order's data
 * survives Item_Info parsing, eligibility, the mapper and the request builder.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Rest_API\V4\Label\Service as V4_Label_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Tests\Support\Client_Factory_Settings;
use PostNLWooCommerce\Tests\Support\Failing_Http_Client;
use PostNLWooCommerce\Tests\Support\Spy_Label_Client_Factory;
use Psr\Log\NullLogger;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Label\Service
 */
class V4LabelRequestTest extends IntegrationTestCase {

	/**
	 * Backups of the WC options changed by setUp(), restored on teardown.
	 *
	 * @var array<string, mixed>
	 */
	private $orig_options = array();

	/**
	 * IDs of orders and products created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Return-label setting before setUp() changed it; null when it was unset.
	 *
	 * @var string|null
	 */
	private $orig_return_setting = null;

	/**
	 * Configure a complete NL store address; Item_Info rejects an unconfigured store.
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

		// A return label in the box keeps a BE order on the legacy path.
		$settings                  = Settings::get_instance();
		$this->orig_return_setting = $settings->settings['return_shipment_and_labels'] ?? null;

		$settings->settings['return_shipment_and_labels'] = 'none';
	}

	/**
	 * Restore the store options and remove the fixtures created by the test.
	 */
	protected function tearDown(): void {
		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		if ( null === $this->orig_return_setting ) {
			unset( Settings::get_instance()->settings['return_shipment_and_labels'] );
		} else {
			Settings::get_instance()->settings['return_shipment_and_labels'] = $this->orig_return_setting;
		}

		parent::tearDown();
	}

	/**
	 * @testdox A pickup order placed through V4 checkout, which stores no pickup time, sends its location code as the deliveryLocation.
	 */
	public function test_pickup_order_sends_the_delivery_location(): void {
		$payload = $this->request_payload(
			'NL',
			array(),
			array(
				'dropoff_points'                   => '176227',
				'dropoff_points_address_company'   => 'Primera',
				'dropoff_points_address_address_1' => 'Kerkstraat',
				'dropoff_points_address_address_2' => '1',
				'dropoff_points_address_city'      => 'Amsterdam',
				'dropoff_points_address_postcode'  => '1017GA',
				'dropoff_points_address_country'   => 'NL',
				'dropoff_points_date'              => '06-10-2026',
				'dropoff_points_time'              => '',
			)
		);

		$this->assertSame( array( 'pickupLocationId' => '176227' ), $payload['deliveryLocation'] );
		$this->assertSame( '1234AB', $payload['receiver']['address']['postalCode'], 'The receiver must stay the customer.' );
		$this->assertArrayNotHasKey( 'handoverDate', $payload );
	}

	/**
	 * @testdox A letterbox order sends the letterbox shipment type with the duration of its variant.
	 * @dataProvider letterbox_provider
	 *
	 * @param string $variant  Stored _postnl_letterbox_type.
	 * @param string $duration Expected deliveryWindow duration.
	 */
	public function test_letterbox_order_sends_its_duration( string $variant, string $duration ): void {
		$payload = $this->request_payload( 'NL', array( 'letterbox' => 'yes' ), array(), array( '_postnl_letterbox_type' => $variant ) );

		$this->assertSame( 'letterbox', $payload['shipmentType'] );
		$this->assertSame( array( 'duration' => $duration ), $payload['services']['deliveryWindow'] );
	}

	/**
	 * Letterbox variants and the duration each must send.
	 *
	 * @return array
	 */
	public static function letterbox_provider(): array {
		return array(
			'24h (2928)' => array( 'letterbox', '24hours' ),
			'48h (2948)' => array( 'letterbox_48', 'non24hours' ),
		);
	}

	/**
	 * @testdox An insured NL to BE parcel with a signature is sent on V4 to the Belgian address.
	 */
	public function test_nl_to_be_parcel_is_sent_on_v4(): void {
		$payload = $this->request_payload(
			'BE',
			array(
				'insured_shipping'      => 'yes',
				'signature_on_delivery' => 'yes',
			)
		);

		$this->assertSame( 'BE', $payload['receiver']['address']['countryIso'] );
		$this->assertSame( 'signature', $payload['services']['deliveryConfirmation'] );
		$this->assertSame( 10.0, (float) $payload['services']['insuredValue'], 'The insured amount is the order item subtotal.' );
	}

	/**
	 * Run create() for a new order and return the decoded request body.
	 *
	 * The fake transport answers 401, so create() throws after sending. A null
	 * request means the order fell back to the legacy pipeline instead.
	 *
	 * @param string $country  Shipping country.
	 * @param array  $backend  Backend option flags ('yes' strings).
	 * @param array  $frontend Frontend (checkout) selection.
	 * @param array  $meta     Extra order meta.
	 * @return array
	 */
	private function request_payload( string $country, array $backend, array $frontend = array(), array $meta = array() ): array {
		$http    = new Failing_Http_Client();
		$service = new V4_Label_Service(
			new Spy_Label_Client_Factory( new Client_Factory_Settings(), $http ),
			'v4-integration-key',
			new NullLogger()
		);

		try {
			$service->create( $this->make_post_data( $country, $backend, $frontend, $meta ) );
		} catch ( \Exception $exception ) {
			$this->assertStringContainsString( 'trace-abc', $exception->getMessage(), 'create() must fail with the canned 401, not earlier: ' . $exception->getMessage() );
		}

		$this->assertNotNull( $http->last_request, 'The V4 request must reach the transport; null means the order fell back to legacy.' );

		return json_decode( (string) $http->last_request->getBody(), true );
	}

	/**
	 * Build an order with one physical product and the post-data shape
	 * Order\Base::save_meta_value() hands to the label service.
	 *
	 * @param string $country  Shipping country.
	 * @param array  $backend  Backend option flags.
	 * @param array  $frontend Frontend selection.
	 * @param array  $meta     Extra order meta.
	 * @return array
	 */
	private function make_post_data( string $country, array $backend, array $frontend, array $meta ): array {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( '10' );
		$product->set_weight( '0.5' );
		$product->save();
		$this->post_ids[] = $product->get_id();

		$order = new \WC_Order();
		$order->set_billing_email( 'buyer@example.com' );
		$order->set_shipping_first_name( 'Jan' );
		$order->set_shipping_last_name( 'Jansen' );
		$order->set_shipping_address_1( 'Main Street' );
		$order->set_shipping_city( 'Amsterdam' );
		$order->set_shipping_postcode( '1234AB' );
		$order->set_shipping_country( $country );
		$order->update_meta_data( '_shipping_house_number', '9' );

		foreach ( $meta as $key => $value ) {
			$order->update_meta_data( $key, $value );
		}

		$order->add_product( $product, 1 );
		$order->save();
		$this->post_ids[] = $order->get_id();

		return array(
			'order'                   => $order,
			'saved_data'              => array(
				'backend'  => array( 'delivery_type' => 'Standard' ) + $backend,
				'frontend' => $frontend,
			),
			'main_barcode'            => '3SDEVC0000001',
			'barcodes'                => array( '3SDEVC0000001' ),
			'return_barcode'          => '',
			'shipping_return_barcode' => '',
			'is_return_activated'     => false,
		);
	}
}
