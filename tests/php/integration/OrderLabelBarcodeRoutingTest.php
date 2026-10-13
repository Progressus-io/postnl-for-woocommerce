<?php
/**
 * Integration tests for the barcode request in Order\Base::save_meta_value().
 *
 * A label routed to V4 gets its barcode from the label call, so save_meta_value()
 * must not request one first, and it stores one barcode per collo from the V4
 * service rather than the single barcode left on a merged multi-collo label.
 * A label on the legacy path still requests them up front.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Rest_API\V4\Label\Service as V4_Label_Service;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * Covers the V4 and legacy branches of the label save.
 */
class OrderLabelBarcodeRoutingTest extends IntegrationTestCase {

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
	 * Configure a complete NL store address and turn returns off.
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

		$settings                  = Settings::get_instance();
		$this->orig_return_setting = $settings->settings['return_shipment_and_labels'] ?? null;

		$settings->settings['return_shipment_and_labels'] = 'none';
	}

	/**
	 * Restore the store options and settings, and remove the fixtures.
	 */
	protected function tearDown(): void {
		remove_all_filters( 'postnl_sdk_enable_label' );

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
	 * @testdox A V4 label requests no barcode and stores every collo barcode the V4 service reports.
	 */
	public function test_v4_label_skips_the_barcode_request(): void {
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$barcodes = new Counting_Barcode_Service();
		$label    = new Recording_V4_Label_Service( array( '3SV4COLLO1', '3SV4COLLO2' ) );
		$handler  = $this->make_handler( $barcodes, $label );

		$result = $handler->save_meta_value( $this->make_order(), array( 'postnl_num_labels' => '2' ) );

		$this->assertSame( 0, $barcodes->calls, 'A V4 label must not request a barcode first.' );
		$this->assertSame( '', $label->post_data['main_barcode'] ?? '', 'The V4 service must get no prefetched barcode.' );
		$this->assertSame(
			array( '3SV4COLLO1', '3SV4COLLO2' ),
			array_column( $result['saved_data']['barcodes'], 'value' ),
			'Every collo barcode is stored, not just the one on the merged label.'
		);
	}

	/**
	 * @testdox A V4 label harvests the barcode from the label when the service reports none.
	 */
	public function test_v4_label_falls_back_to_the_label_barcode(): void {
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$barcodes = new Counting_Barcode_Service();
		$handler  = $this->make_handler( $barcodes, new Recording_V4_Label_Service( array() ) );

		$result = $handler->save_meta_value( $this->make_order(), array() );

		$this->assertSame( 0, $barcodes->calls );
		$this->assertSame( array( '3SLABEL0001' ), array_column( $result['saved_data']['barcodes'], 'value' ) );
	}

	/**
	 * @testdox A legacy label requests one barcode per collo before the label.
	 */
	public function test_legacy_label_prefetches_one_barcode_per_collo(): void {
		$barcodes = new Counting_Barcode_Service();
		$label    = new Recording_Legacy_Label_Service();
		$handler  = $this->make_handler( $barcodes, null, $label );

		$result = $handler->save_meta_value( $this->make_order(), array( 'postnl_num_labels' => '2' ) );

		$this->assertSame( 2, $barcodes->calls );
		$this->assertSame( '3SPREFETCH1', $label->post_data['main_barcode'] );
		$this->assertSame(
			array( '3SPREFETCH1', '3SPREFETCH2' ),
			array_column( $result['saved_data']['barcodes'], 'value' )
		);
	}

	/**
	 * An Order\Base whose service factory serves the given test doubles.
	 *
	 * @param Counting_Barcode_Service       $barcodes Legacy barcode service.
	 * @param V4_Label_Service|null          $v4_label V4 label service, injected when given.
	 * @param Label_Service_Interface|null   $legacy   Legacy label service, when given.
	 * @return Order_Base
	 */
	private function make_handler( Counting_Barcode_Service $barcodes, ?V4_Label_Service $v4_label, ?Label_Service_Interface $legacy = null ): Order_Base {
		$key_settings = new class() {
			/**
			 * @return bool
			 */
			public function is_sandbox() {
				return true;
			}

			/**
			 * @return string
			 */
			public function get_api_key() {
				return 'integration-key';
			}

			/**
			 * @return string
			 */
			public function get_api_key_sandbox() {
				return 'integration-key';
			}
		};

		$factory = new Service_Factory( $key_settings );
		$factory->set_legacy_service( 'barcode', $barcodes );

		if ( null !== $v4_label ) {
			$factory->inject_v4_service( 'label', $v4_label );
		}

		if ( null !== $legacy ) {
			$factory->set_legacy_service( 'label', $legacy );
		}

		return new class( $factory ) extends Order_Base {
			/**
			 * Factory serving the test doubles.
			 *
			 * @var Service_Factory
			 */
			private $test_factory;

			/**
			 * Skip the parent constructor, which registers the admin hooks.
			 *
			 * @param Service_Factory $factory Factory serving the test doubles.
			 */
			public function __construct( Service_Factory $factory ) {
				$this->settings     = Settings::get_instance();
				$this->meta_name    = '_' . $this->prefix . 'order_metadata';
				$this->test_factory = $factory;
			}

			/**
			 * No hooks for this test double.
			 */
			public function init_hooks() {}

			/**
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				return $this->test_factory;
			}
		};
	}

	/**
	 * An NL order with one physical product.
	 *
	 * @return int Order ID.
	 */
	private function make_order(): int {
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
		$order->set_shipping_country( 'NL' );
		$order->update_meta_data( '_shipping_house_number', '9' );
		$order->add_product( $product, 1 );
		$order->save();
		$this->post_ids[] = $order->get_id();

		return $order->get_id();
	}
}

/**
 * Legacy barcode service that counts its calls and answers with numbered barcodes.
 */
class Counting_Barcode_Service implements Barcode_Service_Interface {

	/**
	 * Number of barcodes requested.
	 *
	 * @var int
	 */
	public int $calls = 0;

	/**
	 * Answer with the next numbered barcode.
	 *
	 * @param array $post_data Barcode request data.
	 * @return array
	 */
	public function generate( array $post_data ): array {
		++$this->calls;

		return array( 'Barcode' => '3SPREFETCH' . $this->calls );
	}
}

/**
 * V4 label service that records the post data and answers with one merged label.
 */
class Recording_V4_Label_Service extends V4_Label_Service {

	/**
	 * Post data create() received.
	 *
	 * @var array
	 */
	public array $post_data = array();

	/**
	 * Barcodes to report as issued.
	 *
	 * @var string[]
	 */
	private array $reported;

	/**
	 * @param string[] $reported Barcodes to report as issued.
	 */
	public function __construct( array $reported ) {
		parent::__construct( new Client_Factory( Settings::get_instance(), new NullLogger() ), 'integration-key', new NullLogger() );
		$this->reported = $reported;
	}

	/**
	 * Record the post data instead of calling PostNL.
	 *
	 * @param array $post_data Label post data.
	 * @return array
	 */
	public function create( array $post_data ): array {
		$this->post_data = $post_data;

		return array(
			'label' => array(
				'type'    => 'label',
				'barcode' => $this->reported[0] ?? '3SLABEL0001',
			),
		);
	}

	/**
	 * @return string[]
	 */
	public function issued_barcodes(): array {
		return $this->reported;
	}
}

/**
 * Legacy label service that records the post data instead of calling PostNL.
 */
class Recording_Legacy_Label_Service implements Label_Service_Interface {

	/**
	 * Post data create() received.
	 *
	 * @var array
	 */
	public array $post_data = array();

	/**
	 * Record the post data and answer with one label.
	 *
	 * @param array $post_data Label post data.
	 * @return array
	 */
	public function create( array $post_data ): array {
		$this->post_data = $post_data;

		return array(
			'label' => array(
				'type'    => 'label',
				'barcode' => $post_data['main_barcode'],
			),
		);
	}
}
