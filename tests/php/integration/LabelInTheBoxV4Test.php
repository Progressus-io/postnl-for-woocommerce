<?php
/**
 * Integration tests for dropping label in the box on V4.
 *
 * PostNL dropped the label in the box return for NL and BE stores on V4 (ClickUp
 * 868jpv52c). A BE store has no return setting, which legacy reads as asking for a
 * return label on every BE>BE and BE>NL shipment, and V4 Eligibility rejects any
 * shipment with a return. So with labels on V4 no in-box return is requested and
 * those shipments are labelled on V4. Without V4 the legacy behaviour is unchanged.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use Postnl\Sdk\Client\ResponseMeta;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\ResponseData\V4\LabelsCollection;
use Postnl\Sdk\ResponseData\V4\WarningsCollection;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseInterface;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItem;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItemsCollection;
use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Order\Bulk;
use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Shipping\Item_Info as Shipping_Item_Info;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Rest_API\V4\Label\Service as V4_Label_Service;
use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use Psr\Log\NullLogger;

/**
 * Covers the label save, the meta box and the settings screen with labels on V4 and on legacy.
 */
class LabelInTheBoxV4Test extends IntegrationTestCase {

	/**
	 * Backups of the WC options changed by a test, restored on teardown.
	 *
	 * @var array<string, mixed>
	 */
	private $orig_options = array();

	/**
	 * Settings array before the test changed it.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * IDs of orders and products created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Back up the settings the tests change.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_settings = Settings::get_instance()->settings;

		$this->orig_options['woocommerce_postnl_settings'] = get_option( 'woocommerce_postnl_settings' );
	}

	/**
	 * Restore the store options and settings, and remove the fixtures.
	 */
	protected function tearDown(): void {
		remove_all_filters( 'postnl_sdk_enable_label' );

		foreach ( $this->orig_options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}

		Settings::get_instance()->settings = $this->orig_settings;

		parent::tearDown();
	}

	// ── V4 on ────────────────────────────────────────────────────────────────

	/**
	 * With labels on V4 a BE store's BE>BE and BE>NL label goes to V4 without a return barcode.
	 *
	 * @testdox With labels on V4 a BE store's BE>BE and BE>NL label goes to V4 without a return barcode.
	 *
	 * @testWith ["BE"]
	 *           ["NL"]
	 *
	 * @param string $destination Shipping country.
	 */
	public function test_be_store_labels_on_v4_without_a_return( string $destination ): void {
		$this->use_store( 'BE' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$barcodes = new In_Box_Counting_Barcode_Service();
		$label    = new In_Box_Recording_V4_Label_Service();
		$handler  = $this->make_handler( true, $barcodes, $label );

		$handler->save_meta_value( $this->make_order( $destination ), array() );

		$this->assertTrue( $label->confirmed, 'The label must be created on V4.' );
		$this->assertFalse( $label->fell_back, 'The label must not fall back to legacy.' );
		$this->assertSame( 0, $barcodes->calls, 'No barcode, and so no return barcode, may be requested.' );
	}

	/**
	 * With labels on V4 an NL store set to Label in the box labels on V4 with no in-box return.
	 *
	 * @testdox With labels on V4 an NL store set to Label in the box labels on V4 with no in-box return.
	 *
	 * @testWith ["NL"]
	 *           ["BE"]
	 *
	 * @param string $destination Shipping country.
	 */
	public function test_nl_store_with_in_box_labels_on_v4_without_a_return( string $destination ): void {
		$this->use_store( 'NL', 'in_box' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$barcodes = new In_Box_Counting_Barcode_Service();
		$label    = new In_Box_Recording_V4_Label_Service();
		$handler  = $this->make_handler( true, $barcodes, $label );

		$handler->save_meta_value(
			$this->make_order( $destination ),
			array( 'postnl_create_return_label' => 'yes' )
		);

		$this->assertTrue( $label->confirmed, 'The label must be created on V4.' );
		$this->assertFalse( $label->fell_back, 'The label must not fall back to legacy.' );
		$this->assertSame( 0, $barcodes->calls );
		$this->assertSame( array(), $this->return_options( $label->post_data ), 'No in-box return option may be sent.' );
	}

	/**
	 * With labels on V4 the Create Return Label field is gone from the meta box, also for the save.
	 *
	 * @testdox With labels on V4 the Create Return Label field is gone from the meta box, also for the save.
	 */
	public function test_meta_box_has_no_create_return_label_on_v4(): void {
		$this->use_store( 'NL', 'in_box' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$handler = $this->make_handler( true, new In_Box_Counting_Barcode_Service() );

		$this->assertNotContains( 'postnl_create_return_label', $this->field_ids( $handler, 'display' ) );
		$this->assertNotContains( 'postnl_create_return_label', $this->field_ids( $handler, 'save' ) );
	}

	/**
	 * With labels on V4 the bulk Create label modal has no Create Return Label field.
	 *
	 * @testdox With labels on V4 the bulk Create label modal has no Create Return Label field.
	 */
	public function test_bulk_modal_has_no_create_return_label_on_v4(): void {
		$this->use_store( 'NL', 'in_box' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$this->assertNotContains( 'postnl_create_return_label', $this->bulk_field_ids( true ) );
	}

	/**
	 * The V4 label service drops label in the box even when the caller passes it as available.
	 *
	 * Order\Base already passes false on V4, so this pins the flag create() sets itself.
	 *
	 * @testdox The V4 label service drops label in the box even when the caller passes it as available.
	 */
	public function test_v4_create_drops_label_in_the_box_passed_by_the_caller(): void {
		$this->use_store( 'NL', 'in_box' );

		$label = new In_Box_Recording_V4_Label_Service();
		$label->create(
			array(
				'order'            => wc_get_order( $this->make_order( 'NL' ) ),
				'saved_data'       => array(
					'backend'  => array(
						'delivery_type'       => 'Standard',
						'create_return_label' => 'yes',
					),
					'frontend' => array(),
				),
				'label_in_the_box' => true,
			)
		);

		$this->assertTrue( $label->confirmed, 'The label must be created on V4.' );
		$this->assertFalse( $label->fell_back, 'An in-box return would send the label back to legacy.' );
	}

	/**
	 * With labels on V4 an NL store's settings hide Label in the box and point to Smart Return.
	 *
	 * @testdox With labels on V4 an NL store's settings hide Label in the box and point to Smart Return.
	 */
	public function test_settings_hide_label_in_the_box_on_v4(): void {
		$this->use_store( 'NL', 'in_box' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$fields = $this->make_shipping_method( true )->get_form_fields();

		$this->assertArrayNotHasKey( 'return_shipment_and_labels', $fields );
		$this->assertSame(
			'Label in the box is not available with the new PostNL APIs. You can still offer returns with Smart Return.',
			$fields['return_settings_title']['description'] ?? ''
		);
		$this->assertArrayHasKey( 'activate_smart_return', $fields, 'Smart Return must stay available.' );
	}

	/**
	 * With labels on V4 a BE store's settings say Label in the box is not available, without pointing to Smart Return.
	 *
	 * Smart Return does not work for BE stores, so the BE note must not suggest it.
	 *
	 * @testdox With labels on V4 a BE store's settings say Label in the box is not available, without pointing to Smart Return.
	 */
	public function test_settings_note_for_be_store_on_v4(): void {
		$this->use_store( 'BE' );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$fields = $this->make_shipping_method( true )->get_form_fields();

		$this->assertSame(
			'Label in the box is not available with the new PostNL APIs.',
			$fields['return_settings_title']['description'] ?? ''
		);
	}

	/**
	 * Without V4 a BE store's settings show no label in the box note.
	 *
	 * @testdox Without V4 a BE store's settings show no label in the box note.
	 */
	public function test_no_settings_note_for_be_store_without_v4(): void {
		$this->use_store( 'BE' );

		$fields = $this->make_shipping_method( false )->get_form_fields();

		$this->assertArrayHasKey( 'return_settings_title', $fields );
		$this->assertArrayNotHasKey( 'description', $fields['return_settings_title'] );
	}

	/**
	 * Saving the settings with labels on V4 keeps the stored Label in the box choice.
	 *
	 * @testdox Saving the settings with labels on V4 keeps the stored Label in the box choice.
	 */
	public function test_saving_settings_on_v4_keeps_the_stored_choice(): void {
		$this->use_store( 'NL', 'in_box' );
		update_option( 'woocommerce_postnl_settings', Settings::get_instance()->settings );
		add_filter( 'postnl_sdk_enable_label', '__return_true' );

		$method = $this->make_shipping_method( true );
		$method->set_post_data( array() );
		$method->save_form();

		$stored = get_option( 'woocommerce_postnl_settings' );

		$this->assertSame( 'in_box', $stored['return_shipment_and_labels'] ?? null );
	}

	// ── V4 off: legacy unchanged ─────────────────────────────────────────────

	/**
	 * Without V4 a BE store still requests a return barcode on BE>BE and BE>NL labels.
	 *
	 * @testdox Without V4 a BE store still requests a return barcode on BE>BE and BE>NL labels.
	 *
	 * @testWith ["BE"]
	 *           ["NL"]
	 *
	 * @param string $destination Shipping country.
	 */
	public function test_legacy_be_store_still_requests_a_return( string $destination ): void {
		$this->use_store( 'BE' );

		$barcodes = new In_Box_Counting_Barcode_Service();
		$legacy   = new In_Box_Recording_Legacy_Label_Service();
		$handler  = $this->make_handler( false, $barcodes, null, $legacy );

		$handler->save_meta_value( $this->make_order( $destination ), array() );

		$this->assertSame( 2, $barcodes->calls, 'One label barcode and one return barcode.' );
		$this->assertSame( '3SINBOX2', $legacy->post_data['return_barcode'] );
		$this->assertTrue( $legacy->post_data['label_in_the_box'] );
	}

	/**
	 * Without V4 an NL store set to Label in the box still gets the in-box return.
	 *
	 * @testdox Without V4 an NL store set to Label in the box still gets the in-box return.
	 */
	public function test_legacy_nl_store_with_in_box_still_gets_the_return(): void {
		$this->use_store( 'NL', 'in_box' );

		$barcodes = new In_Box_Counting_Barcode_Service();
		$legacy   = new In_Box_Recording_Legacy_Label_Service();
		$handler  = $this->make_handler( false, $barcodes, null, $legacy );

		$handler->save_meta_value( $this->make_order( 'NL' ), array( 'postnl_create_return_label' => 'yes' ) );

		$this->assertSame( 2, $barcodes->calls, 'One label barcode and one return barcode.' );
		$this->assertSame( '3SINBOX2', $legacy->post_data['return_barcode'] );
		$this->assertSame(
			array(
				array(
					'characteristic' => '152',
					'option'         => '028',
				),
			),
			$this->return_options( $legacy->post_data )
		);
	}

	/**
	 * Without V4 the meta box and settings still offer Label in the box.
	 *
	 * @testdox Without V4 the meta box and settings still offer Label in the box.
	 */
	public function test_legacy_still_offers_label_in_the_box(): void {
		$this->use_store( 'NL', 'in_box' );

		$handler = $this->make_handler( false, new In_Box_Counting_Barcode_Service() );

		$this->assertContains( 'postnl_create_return_label', $this->field_ids( $handler, 'display' ) );
		$this->assertContains( 'postnl_create_return_label', $this->field_ids( $handler, 'save' ) );
		$this->assertContains( 'postnl_create_return_label', $this->bulk_field_ids( false ) );
		$this->assertArrayHasKey( 'return_shipment_and_labels', $this->make_shipping_method( false )->get_form_fields() );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Configure the store country and address, and the return setting.
	 *
	 * @param string      $country     Store country, NL or BE.
	 * @param string|null $return_type Stored return setting, or null to leave it unset.
	 * @return void
	 */
	private function use_store( string $country, ?string $return_type = null ): void {
		$store = 'BE' === $country
			? array(
				'woocommerce_default_country' => 'BE',
				'woocommerce_store_address'   => 'Wetstraat 16',
				'woocommerce_store_city'      => 'Brussel',
				'woocommerce_store_postcode'  => '1000',
			)
			: array(
				'woocommerce_default_country' => 'NL',
				'woocommerce_store_address'   => 'Siriusdreef',
				'woocommerce_store_city'      => 'Hoofddorp',
				'woocommerce_store_postcode'  => '2132WT',
			);

		$store['woocommerce_weight_unit'] = 'kg';

		foreach ( $store as $option => $value ) {
			if ( ! array_key_exists( $option, $this->orig_options ) ) {
				$this->orig_options[ $option ] = get_option( $option );
			}
			update_option( $option, $value );
		}

		$settings           = Settings::get_instance();
		$settings->settings = is_array( $settings->settings ) ? $settings->settings : array();

		if ( null === $return_type ) {
			unset( $settings->settings['return_shipment_and_labels'] );
		} else {
			$settings->settings['return_shipment_and_labels'] = $return_type;
		}
	}

	/**
	 * A service factory that sees a validated V4 key, or none.
	 *
	 * @param bool $with_key Whether the factory sees a V4 key.
	 * @return Service_Factory
	 */
	private function make_factory( bool $with_key ): Service_Factory {
		return new Service_Factory( $with_key ? new In_Box_Key_Settings() : null );
	}

	/**
	 * An Order\Base whose service factory serves the given test doubles.
	 *
	 * @param bool                            $with_key Whether the factory sees a V4 key.
	 * @param In_Box_Counting_Barcode_Service $barcodes Legacy barcode service.
	 * @param V4_Label_Service|null           $v4_label V4 label service, injected when given.
	 * @param Label_Service_Interface|null    $legacy   Legacy label service, when given.
	 * @return Order_Base
	 */
	private function make_handler( bool $with_key, In_Box_Counting_Barcode_Service $barcodes, ?V4_Label_Service $v4_label = null, ?Label_Service_Interface $legacy = null ): Order_Base {
		$factory = $this->make_factory( $with_key );
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
			 *
			 * @return void
			 */
			public function init_hooks() {}

			/**
			 * Serve the test factory.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				return $this->test_factory;
			}
		};
	}

	/**
	 * A PostNL shipping method whose factory sees a validated V4 key, or none.
	 *
	 * @param bool $with_key Whether the factory sees a V4 key.
	 * @return PostNL
	 */
	private function make_shipping_method( bool $with_key ): PostNL {
		return new class( $with_key ) extends PostNL {
			/**
			 * Whether the factory sees a V4 key. Static because the parent constructor builds the form first.
			 *
			 * @var bool
			 */
			public static $with_key = false;

			/**
			 * Record the key choice before the parent builds the form.
			 *
			 * @param bool $with_key Whether the factory sees a V4 key.
			 */
			public function __construct( bool $with_key ) {
				self::$with_key = $with_key;
				parent::__construct();
			}

			/**
			 * Serve a factory with or without a V4 key.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				return new Service_Factory( self::$with_key ? new In_Box_Key_Settings() : null );
			}

			/**
			 * Run the plain WC_Settings_API save, without the API key validation.
			 *
			 * @return bool
			 */
			public function save_form(): bool {
				return \WC_Settings_API::process_admin_options();
			}
		};
	}

	/**
	 * IDs of the meta box fields for a context.
	 *
	 * @param Order_Base $handler Order handler.
	 * @param string     $context 'display' or 'save'.
	 * @return string[]
	 */
	private function field_ids( Order_Base $handler, string $context ): array {
		// The 'display' context runs Order\Bulk::additional_meta_box(), which the plugin
		// hooks at bootstrap when the stored store country is NL or BE. It calls
		// get_current_screen(), an admin-only function, so load it here: the callback
		// then sees no screen and passes the fields through, whatever the country was.
		if ( ! function_exists( 'get_current_screen' ) ) {
			require_once ABSPATH . 'wp-admin/includes/screen.php';
		}

		return array_column( $handler->meta_box_fields( false, $context ), 'id' );
	}

	/**
	 * IDs of the fields in the bulk Create label modal, with a factory that sees a V4 key or none.
	 *
	 * @param bool $with_key Whether the factory sees a V4 key.
	 * @return string[]
	 */
	private function bulk_field_ids( bool $with_key ): array {
		$bulk = new class( $this->make_factory( $with_key ) ) extends Bulk {
			/**
			 * Factory deciding whether labels run on V4.
			 *
			 * @var Service_Factory
			 */
			private $test_factory;

			/**
			 * Skip the parent constructor, which registers the admin hooks.
			 *
			 * @param Service_Factory $factory Factory deciding whether labels run on V4.
			 */
			public function __construct( Service_Factory $factory ) {
				$this->settings     = Settings::get_instance();
				$this->test_factory = $factory;
			}

			/**
			 * Serve the test factory.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				return $this->test_factory;
			}

			/**
			 * Expose the protected modal field builder.
			 *
			 * @return array[]
			 */
			public function modal_fields(): array {
				return $this->create_label_fields();
			}
		};

		return array_column( $bulk->modal_fields(), 'id' );
	}

	/**
	 * Return options Shipping\Item_Info builds from the post data a label service received.
	 *
	 * @param array $post_data Label post data.
	 * @return array
	 */
	private function return_options( array $post_data ): array {
		$post_data += array(
			'main_barcode' => '',
			'barcodes'     => array(),
		);

		return ( new Shipping_Item_Info( $post_data ) )->shipment['return_options'] ?? array();
	}

	/**
	 * An order with one physical product shipping to the given country.
	 *
	 * @param string $country Shipping country, NL or BE.
	 * @return int Order ID.
	 */
	private function make_order( string $country ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( '10' );
		$product->set_weight( '0.5' );
		$product->save();
		$this->post_ids[] = $product->get_id();

		$order = new \WC_Order();
		$order->set_billing_email( 'buyer@example.com' );
		$order->set_billing_phone( '0612345678' );
		$order->set_shipping_first_name( 'Jan' );
		$order->set_shipping_last_name( 'Jansen' );
		$order->set_shipping_country( $country );

		if ( 'BE' === $country ) {
			$order->set_shipping_address_1( 'Grote Markt' );
			$order->set_shipping_city( 'Antwerpen' );
			$order->set_shipping_postcode( '2000' );
		} else {
			$order->set_shipping_address_1( 'Main Street' );
			$order->set_shipping_city( 'Amsterdam' );
			$order->set_shipping_postcode( '1234AB' );
		}

		$order->update_meta_data( '_shipping_house_number', '9' );
		$order->add_product( $product, 1 );
		$order->save();
		$this->post_ids[] = $order->get_id();

		return $order->get_id();
	}
}

/**
 * Settings stand-in that reports a validated V4 key, as Service_Factory reads it.
 */
class In_Box_Key_Settings {

	/**
	 * Sandbox mode.
	 *
	 * @return bool
	 */
	public function is_sandbox() {
		return true;
	}

	/**
	 * The new API key.
	 *
	 * @return string
	 */
	public function get_api_key_new() {
		return 'integration-key';
	}

	/**
	 * The new API key passed validation.
	 *
	 * @return bool
	 */
	public function is_api_key_new_validated() {
		return true;
	}
}

/**
 * Legacy barcode service that counts its calls and answers with numbered barcodes.
 */
class In_Box_Counting_Barcode_Service implements Barcode_Service_Interface {

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

		return array( 'Barcode' => '3SINBOX' . $this->calls );
	}
}

/**
 * V4 label service that answers the label call itself and records a legacy fallback.
 */
class In_Box_Recording_V4_Label_Service extends V4_Label_Service {

	/**
	 * Whether the V4 label call was made.
	 *
	 * @var bool
	 */
	public bool $confirmed = false;

	/**
	 * Whether the order fell back to the legacy pipeline.
	 *
	 * @var bool
	 */
	public bool $fell_back = false;

	/**
	 * Post data create() received.
	 *
	 * @var array
	 */
	public array $post_data = array();

	/**
	 * Build the service without a real PostNL client.
	 */
	public function __construct() {
		parent::__construct( new Client_Factory( Settings::get_instance(), new NullLogger() ), 'integration-key', new NullLogger() );
	}

	/**
	 * Record the post data exactly as received, then run the real create().
	 *
	 * @param array $post_data Label post data.
	 * @return array
	 */
	public function create( array $post_data ): array {
		$this->post_data = $post_data;

		return parent::create( $post_data );
	}

	/**
	 * Answer the label call with one collo instead of calling PostNL.
	 *
	 * @param ShipmentDeliveryRequest $request Label request.
	 * @param array                   $fields  Request fields.
	 * @return ShipmentDeliveryResponseInterface
	 */
	protected function confirm_label( ShipmentDeliveryRequest $request, array $fields ): ShipmentDeliveryResponseInterface {
		$this->confirmed = true;

		$item = new ShipmentDeliveryResponseItem(
			barcode: '3SV4INBOX1',
			labels: new LabelsCollection(
				array(
					new Label(
						label: base64_encode( 'PDF-BYTES' ),
						outputType: LabelOutputType::PDF,
						labelType: 'Label'
					),
				)
			),
			partnerId: null,
			partnerBarcode: null
		);

		return new class( new ShipmentDeliveryResponseItemsCollection( array( $item ) ) ) implements ShipmentDeliveryResponseInterface {
			/**
			 * Keep the collo items.
			 *
			 * @param ShipmentDeliveryResponseItemsCollection $items Collo items.
			 */
			public function __construct( private ShipmentDeliveryResponseItemsCollection $items ) {}

			/**
			 * The collo items.
			 *
			 * @return ShipmentDeliveryResponseItemsCollection
			 */
			public function items(): ShipmentDeliveryResponseItemsCollection {
				return $this->items;
			}

			/**
			 * Not used by this test.
			 *
			 * @return ResponseMeta
			 *
			 * @throws \LogicException Always, the response meta is not read here.
			 */
			public function meta(): ResponseMeta {
				throw new \LogicException( 'meta() is not exercised by this test.' );
			}

			/**
			 * Not used by this test.
			 *
			 * @return WarningsCollection
			 *
			 * @throws \LogicException Always, the warnings are not read here.
			 */
			public function warnings(): WarningsCollection {
				throw new \LogicException( 'warnings() is not exercised by this test.' );
			}
		};
	}

	/**
	 * Skip the PDF merge and remove the written label files.
	 *
	 * @param array  $labels     Label records built from the response.
	 * @param mixed  $order      Order object.
	 * @param string $barcode    Barcode the merged record is keyed on.
	 * @param string $label_type Parent label type.
	 * @return array
	 */
	public function maybe_merge_labels( $labels, $order, $barcode, $label_type ) {
		foreach ( array_column( $labels, 'filepath' ) as $file ) {
			wp_delete_file( $file );
		}

		return array(
			$label_type => array(
				'type'    => $label_type,
				'barcode' => $barcode,
			),
		);
	}

	/**
	 * Record a legacy fallback instead of requesting barcodes.
	 *
	 * @param array $label_post_data Label post data.
	 * @return array
	 */
	protected function prefetch_label_barcodes( $label_post_data ) {
		$this->fell_back = true;

		$label_post_data['main_barcode'] = '3SFALLBACK1';
		$label_post_data['barcodes']     = array( '3SFALLBACK1' );

		return $label_post_data;
	}

	/**
	 * Record a legacy fallback instead of calling the legacy label API.
	 *
	 * @param array $post_data Label post data.
	 * @return array
	 */
	protected function create_label_pipeline( $post_data ) {
		$this->fell_back = true;

		return array(
			'label' => array(
				'type'    => 'label',
				'barcode' => $post_data['main_barcode'],
			),
		);
	}
}

/**
 * Legacy label service that records the post data instead of calling PostNL.
 */
class In_Box_Recording_Legacy_Label_Service implements Label_Service_Interface {

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
