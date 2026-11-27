<?php
/**
 * Integration tests that keep WooCommerce Local pickup away from PostNL.
 *
 * Local pickup orders must get no PostNL delivery options, no PostNL product,
 * no label and no Label & Tracking box, on both checkouts and in bulk, even
 * when a merchant selected Local pickup under the PostNL shipping methods.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Order\Bulk;
use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Product\Single as Product_Single;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Utils;

/**
 * Exercises every entry point that decides whether an order is a PostNL order.
 */
class LocalPickupDisconnectTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backup of the store options this test overrides.
	 *
	 * @var array
	 */
	private $orig_options = array();

	/**
	 * Orders and products created during the test.
	 *
	 * @var int[]
	 */
	private $post_ids = array();

	/**
	 * Number of HTTP requests sent to the PostNL API during the test.
	 *
	 * @var int
	 */
	private $api_calls = 0;

	/**
	 * Backup of the session and customer state the checkout handlers write.
	 *
	 * @var array
	 */
	private $orig_checkout = array();

	/**
	 * Prepare an NL store that lists Local pickup under the PostNL shipping methods.
	 */
	protected function setUp(): void {
		parent::setUp();

		$store_options = array(
			'woocommerce_default_country' => 'NL',
			'woocommerce_store_address'   => 'Hoofdstraat 1',
			'woocommerce_store_city'      => 'Amsterdam',
			'woocommerce_store_postcode'  => '1011 AB',
		);

		foreach ( $store_options as $option => $value ) {
			$this->orig_options[ $option ] = get_option( $option );
			update_option( $option, $value );
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['api_keys']                   = 'test-key';
		$settings->settings['environment_mode']           = 'sandbox';
		$settings->settings['cut_off_time']               = '18:00';
		$settings->settings['enable_delivery_days']       = 'yes';
		$settings->settings['enable_pickup_points']       = 'yes';
		$settings->settings['validate_nl_address']        = 'no';
		$settings->settings['reorder_nl_address']         = 'no';
		$settings->settings['supported_shipping_methods'] = array( 'flat_rate', 'local_pickup', 'pickup_location' );

		$this->orig_checkout = array(
			'chosen'       => WC()->session->get( 'chosen_shipping_methods' ),
			'country'      => WC()->customer->get_shipping_country(),
			'postcode'     => WC()->customer->get_shipping_postcode(),
			'address_1'    => WC()->customer->get_shipping_address_1(),
			'address_2'    => WC()->customer->get_shipping_address_2(),
			'city'         => WC()->customer->get_shipping_city(),
			'house_number' => WC()->customer->get_meta( '_wc_shipping/postnl/house_number' ),
		);

		wc_clear_notices();
		add_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10, 3 );
	}

	/**
	 * Restore global state and remove created posts.
	 */
	protected function tearDown(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_postnl_api' ), 10 );
		remove_filter( 'wp_doing_ajax', '__return_true' );
		remove_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );
		unset( $_REQUEST['post_data'], $_POST['nonce'], $_POST['data'] );

		WC()->session->set( 'chosen_shipping_methods', $this->orig_checkout['chosen'] );
		WC()->session->__unset( POSTNL_SETTINGS_ID . '_invalid_address_marker' );
		WC()->customer->set_shipping_country( $this->orig_checkout['country'] );
		WC()->customer->set_shipping_postcode( $this->orig_checkout['postcode'] );
		WC()->customer->set_shipping_address_1( $this->orig_checkout['address_1'] );
		WC()->customer->set_shipping_address_2( $this->orig_checkout['address_2'] );
		WC()->customer->set_shipping_city( $this->orig_checkout['city'] );
		WC()->customer->update_meta_data( '_wc_shipping/postnl/house_number', $this->orig_checkout['house_number'] );
		WC()->customer->save();

		foreach ( $this->post_ids as $id ) {
			$order = wc_get_order( $id );

			if ( $order ) {
				$order->delete( true );
			} else {
				wp_delete_post( $id, true );
			}
		}

		Settings::get_instance()->settings = $this->orig_settings;
		foreach ( $this->orig_options as $option => $value ) {
			update_option( $option, $value );
		}
		wc_clear_notices();

		parent::tearDown();
	}

	/**
	 * Answer the postcode check with no address and every other PostNL API request with an error.
	 *
	 * @param false|array $preempt Short-circuit value.
	 * @param array       $args    Request arguments.
	 * @param string      $url     Request URL.
	 *
	 * @return false|array
	 */
	public function fake_postnl_api( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'postnl.nl' ) ) {
			return $preempt;
		}

		++$this->api_calls;

		if ( false !== strpos( $url, 'postalcodecheck' ) ) {
			return array(
				'headers'  => array(),
				'body'     => '[]',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( array( 'Errors' => array( array( 'Description' => 'Faked by the test' ) ) ) ),
			'response' => array(
				'code'    => 400,
				'message' => 'Bad Request',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * Replace the AJAX wp_die() so the test keeps running.
	 *
	 * @return callable
	 */
	public function ajax_die_handler() {
		return static function () {
			throw new \Error( 'wp_die' );
		};
	}

	/**
	 * Create an NL order with the given shipping lines and a letterbox-eligible product.
	 *
	 * @param string ...$method_ids Shipping method ID of each shipping line.
	 *
	 * @return \WC_Order
	 */
	private function make_order( string ...$method_ids ): \WC_Order {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Letterbox item' );
		$product->set_regular_price( '10' );
		$product->set_weight( '0.1' );
		$product->update_meta_data( Product_Single::LETTERBOX_PARCEL, 'yes' );
		$product->update_meta_data( Product_Single::MAX_QTY_PER_LETTERBOX, 5 );
		$product->save();
		$this->post_ids[] = $product->get_id();

		$order = new \WC_Order();
		$order->set_shipping_country( 'NL' );
		$order->set_shipping_postcode( '6591 BA' );
		$order->set_shipping_address_1( 'Hoofdstraat 1' );
		$order->set_shipping_city( 'Gennep' );
		$order->add_product( $product, 1 );

		foreach ( $method_ids as $method_id ) {
			$item = new \WC_Order_Item_Shipping();
			$item->set_method_id( $method_id );
			$item->set_method_title( $method_id );
			$order->add_item( $item );
		}
		$order->save();

		$this->post_ids[] = $order->get_id();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * Build an order handler without registering its admin hooks.
	 *
	 * @param string $class Bulk or Single.
	 *
	 * @return Bulk|Single
	 */
	private function make_handler( string $class ) {
		if ( Bulk::class === $class ) {
			return new class() extends Bulk {
				/**
				 * Skip hook registration.
				 */
				public function init_hooks() {}
			};
		}

		return new class() extends Single {
			/**
			 * Skip hook registration.
			 */
			public function init_hooks() {}
		};
	}

	/**
	 * Build the blocks handler with the settings singleton.
	 *
	 * @return Extend_Block_Core
	 */
	private function make_blocks_handler(): Extend_Block_Core {
		$handler  = ( new \ReflectionClass( Extend_Block_Core::class ) )->newInstanceWithoutConstructor();
		$settings = new \ReflectionProperty( Extend_Block_Core::class, 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( $handler, Settings::get_instance() );

		return $handler;
	}

	/**
	 * @testdox Local pickup is never a supported PostNL method nor offered in the Shipping Methods setting.
	 */
	public function test_local_pickup_is_never_supported(): void {
		WC()->shipping()->load_shipping_methods();
		$settings = Settings::get_instance();

		$this->assertNotContains( 'local_pickup', $settings->get_supported_shipping_methods() );
		$this->assertNotContains( 'pickup_location', $settings->get_supported_shipping_methods() );
		$this->assertContains( 'flat_rate', $settings->get_supported_shipping_methods() );
		$this->assertArrayNotHasKey( 'local_pickup', $settings->get_shipping_methods() );
		$this->assertArrayNotHasKey( 'pickup_location', $settings->get_shipping_methods() );
		$this->assertArrayHasKey( 'flat_rate', $settings->get_shipping_methods() );
	}

	/**
	 * @testdox Classic checkout does not look up PostNL delivery options for Local pickup.
	 */
	public function test_classic_checkout_skips_local_pickup(): void {
		$_REQUEST['post_data'] = http_build_query(
			array(
				'billing_country'   => 'NL',
				'billing_postcode'  => '6591 BA',
				'billing_address_1' => 'Hoofdstraat 1',
				'billing_city'      => 'Gennep',
				'shipping_method'   => array( 'local_pickup:3' ),
			)
		);

		ob_start();
		( new Container( false ) )->postnl_fields();
		$html = ob_get_clean();

		$this->assertSame( 0, $this->api_calls );
		$this->assertSame( '', trim( $html ) );
	}

	/**
	 * @testdox Blocks endpoint hides the PostNL options when Local pickup is the chosen rate.
	 */
	public function test_blocks_endpoint_hides_options_for_local_pickup(): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'pickup_location:0' ) );
		add_filter( 'wp_doing_ajax', '__return_true' );
		add_filter( 'wp_die_ajax_handler', array( $this, 'ajax_die_handler' ) );

		$_POST['nonce'] = wp_create_nonce( 'postnl_delivery_day_nonce' );
		$_POST['data']  = array(
			'ship_to_different_address' => '1',
			'shipping_country'          => 'NL',
			'shipping_postcode'         => '6591 BA',
			'shipping_address_1'        => 'Hoofdstraat',
			'shipping_house_number'     => '1',
			'shipping_city'             => 'Gennep',
		);

		ob_start();
		try {
			$this->make_blocks_handler()->handle_set_checkout_post_data();
		} catch ( \Error $e ) {
			$this->assertSame( 'wp_die', $e->getMessage() );
		} finally {
			$response = json_decode( (string) ob_get_clean(), true );
		}

		$this->assertSame( 0, $this->api_calls );
		$this->assertTrue( $response['success'] );
		$this->assertFalse( $response['data']['show_container'] );
	}

	/**
	 * Rate chosen at checkout and whether the invalid address error must block it.
	 *
	 * @return array
	 */
	public static function invalid_address_marker_provider(): array {
		return array(
			'Local pickup' => array( 'pickup_location:0', false ),
			'PostNL rate'  => array( 'flat_rate:1', true ),
		);
	}

	/**
	 * @testdox A rejected NL delivery address blocks only PostNL rates at checkout.
	 *
	 * @dataProvider invalid_address_marker_provider
	 *
	 * @param string $rate         Chosen shipping rate.
	 * @param bool   $expect_error Whether the cart must report the invalid address.
	 */
	public function test_invalid_address_marker_blocks_only_postnl_rates( string $rate, bool $expect_error ): void {
		WC()->session->set( 'chosen_shipping_methods', array( $rate ) );
		WC()->session->set( POSTNL_SETTINGS_ID . '_invalid_address_marker', true );
		WC()->customer->set_shipping_country( 'NL' );

		$errors = new \WP_Error();
		$this->make_blocks_handler()->postnl_validate_address_in_cart( $errors, WC()->cart );

		$this->assertSame( $expect_error, $errors->has_errors() );
	}

	/**
	 * Rates chosen after PostNL rejected the address and whether the order must stay blocked.
	 *
	 * @return array
	 */
	public static function rejected_address_provider(): array {
		return array(
			'Local pickup'                => array( array( 'pickup_location:0' ), false ),
			'PostNL rate'                 => array( array( 'flat_rate:1' ), true ),
			'Local pickup and PostNL rate' => array( array( 'pickup_location:0', 'flat_rate:1' ), true ),
		);
	}

	/**
	 * @testdox Blocks checkout places a Local pickup order with an address PostNL rejected and still blocks a PostNL order.
	 *
	 * @dataProvider rejected_address_provider
	 *
	 * @param array $rates        Chosen shipping rate of each package.
	 * @param bool  $expect_error Whether the cart must report the invalid address.
	 */
	public function test_blocks_rejected_address_blocks_only_postnl_orders( array $rates, bool $expect_error ): void {
		WC()->session->set( 'chosen_shipping_methods', array( 'flat_rate:1' ) );
		WC()->customer->set_shipping_country( 'NL' );

		( new Container( false ) )->validated_address(
			array(
				'shipping_country'      => 'NL',
				'shipping_postcode'     => '6591 BA',
				'shipping_house_number' => '999',
			)
		);

		$this->assertSame( 1, $this->api_calls );
		$this->assertTrue( WC()->session->get( POSTNL_SETTINGS_ID . '_invalid_address_marker' ) );

		WC()->session->set( 'chosen_shipping_methods', $rates );

		$errors = new \WP_Error();
		$this->make_blocks_handler()->postnl_validate_address_in_cart( $errors, WC()->cart );

		$this->assertSame( $expect_error, $errors->has_errors() );
		$this->assertSame( $expect_error, (bool) WC()->session->get( POSTNL_SETTINGS_ID . '_invalid_address_marker', false ) );
	}

	/**
	 * Classic checkout method and whether PostNL must check the address.
	 *
	 * @return array
	 */
	public static function classic_rejected_address_provider(): array {
		return array(
			'Local pickup' => array( 'local_pickup:3', false ),
			'PostNL rate'  => array( 'flat_rate:1', true ),
		);
	}

	/**
	 * @testdox Classic checkout checks an address PostNL rejects only for a PostNL method.
	 *
	 * @dataProvider classic_rejected_address_provider
	 *
	 * @param string $method         Posted shipping method.
	 * @param bool   $expect_checked Whether the address check must run and warn.
	 */
	public function test_classic_rejected_address_is_checked_only_for_postnl_methods( string $method, bool $expect_checked ): void {
		$settings                                  = Settings::get_instance();
		$settings->settings['validate_nl_address'] = 'yes';
		$settings->settings['reorder_nl_address']  = 'yes';

		WC()->session->set( 'chosen_shipping_methods', array( $method ) );
		WC()->session->set( POSTNL_SETTINGS_ID . '_invalid_address_marker', true );
		WC()->customer->set_shipping_country( 'NL' );

		$_REQUEST['post_data'] = http_build_query(
			array(
				'billing_country'      => 'NL',
				'billing_postcode'     => '6591 BA',
				'billing_house_number' => '999',
				'billing_address_1'    => 'Hoofdstraat',
				'billing_city'         => 'Gennep',
				'shipping_method'      => array( $method ),
			)
		);

		ob_start();
		( new Container( false ) )->postnl_fields();
		ob_end_clean();

		$this->assertSame( $expect_checked, $this->api_calls > 0 );
		$this->assertSame( $expect_checked, wc_has_notice( 'This is not a valid address!', 'notice' ) );
		$this->assertCount( 0, wc_get_notices( 'error' ) );
		$this->assertNull( WC()->session->get( POSTNL_SETTINGS_ID . '_invalid_address_marker' ) );
	}

	/**
	 * @testdox Blocks checkout does not save a PostNL delivery day or pickup point on a Local pickup order.
	 */
	public function test_blocks_order_save_skips_local_pickup(): void {
		$order   = $this->make_order( 'pickup_location' );
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'extensions',
			array(
				'postnl' => array(
					'houseNumber'     => '12',
					'deliveryDay'     => '2026-11-23_08:30-21:30_0',
					'deliveryDayDate' => '2026-11-23',
					'deliveryDayFrom' => '08:30',
					'deliveryDayTo'   => '21:30',
					'deliveryDayType' => 'Daytime',
					'dropoffPoints'   => '',
				),
			)
		);

		$this->make_blocks_handler()->save_postnl_checkout_fields( $order, $request );

		$saved = wc_get_order( $order->get_id() );
		$this->assertEmpty( $saved->get_meta( '_postnl_order_metadata' ) );
		$this->assertEmpty( $saved->get_meta( '_postnl_letterbox_type' ) );
		$this->assertTrue( $saved->meta_exists( '_shipping_house_number' ), 'The address part of the request is still saved.' );
	}

	/**
	 * @testdox Blocks checkout still saves the delivery day on a PostNL order.
	 */
	public function test_blocks_order_save_keeps_postnl_orders(): void {
		$order   = $this->make_order( 'flat_rate' );
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'extensions',
			array(
				'postnl' => array(
					'deliveryDay'     => '2026-11-23_08:30-21:30_0',
					'deliveryDayDate' => '2026-11-23',
				),
			)
		);

		$this->make_blocks_handler()->save_postnl_checkout_fields( $order, $request );

		$meta = wc_get_order( $order->get_id() )->get_meta( '_postnl_order_metadata' );
		$this->assertSame( '2026-11-23', $meta['frontend']['delivery_day_date'] );
	}

	/**
	 * @testdox A Local pickup order gets no PostNL product, not from the default product and not from automatic letterbox.
	 */
	public function test_local_pickup_order_has_no_postnl_product(): void {
		$order   = $this->make_order( 'local_pickup' );
		$handler = $this->make_handler( Single::class );

		$this->assertSame( array(), $handler->get_shipping_options( $order ) );
		$this->assertFalse( Utils::is_order_eligible_auto_letterbox( $order ) );
		$this->assertTrue( Utils::is_order_eligible_auto_letterbox( $this->make_order( 'flat_rate' ) ) );
	}

	/**
	 * @testdox The Label & Tracking box is not added to a Local pickup order.
	 */
	public function test_label_box_is_not_added_for_local_pickup(): void {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/template.php';

		$handler = $this->make_handler( Single::class );
		$screen  = Utils::get_order_screen_id();

		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$handler->add_meta_box( $screen, $this->make_order( 'local_pickup' ) );
		$this->assertFalse( $this->has_label_box() );

		$handler->add_meta_box( $screen, $this->make_order( 'flat_rate' ) );
		$this->assertTrue( $this->has_label_box() );

		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	/**
	 * Whether the Label & Tracking box is registered on any screen.
	 *
	 * Without an admin screen WordPress files boxes under a placeholder screen,
	 * so every screen is searched.
	 *
	 * @return bool
	 */
	private function has_label_box(): bool {
		global $wp_meta_boxes;

		foreach ( (array) $wp_meta_boxes as $contexts ) {
			foreach ( $contexts as $priorities ) {
				foreach ( $priorities as $boxes ) {
					if ( ! empty( $boxes['woocommerce-shipment-postnl-label'] ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * @testdox Bulk "PostNL Create Label" refuses a Local pickup order without calling PostNL.
	 */
	public function test_bulk_create_label_refuses_local_pickup(): void {
		$order  = $this->make_order( 'local_pickup' );
		$result = $this->make_handler( Bulk::class )->generate_label_and_notes( $order->get_id(), array() );

		$this->assertSame( 0, $this->api_calls );
		$this->assertSame( 'error', $result['message']['type'] );
		$this->assertStringContainsString( 'Local pickup', $result['message']['message'] );
		$this->assertArrayNotHasKey( 'labels_data', $result );
	}

	/**
	 * Give an order a label record, as a label created on an earlier version would.
	 *
	 * @param \WC_Order $order Order to label.
	 *
	 * @return \WC_Order The reloaded order.
	 */
	private function add_existing_label( \WC_Order $order ): \WC_Order {
		$order->update_meta_data(
			'_postnl_order_metadata',
			array(
				'backend' => array( 'signature_on_delivery' => 'yes' ),
				'labels'  => array(
					'label' => array(
						'type'     => 'label',
						'barcode'  => '3SDEVC000000001',
						'filepath' => '/tmp/postnl-test-label.pdf',
					),
				),
			)
		);
		$order->save();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * @testdox A Local pickup order labelled before the change keeps its label, its box and its Print Label action.
	 */
	public function test_existing_label_on_local_pickup_order_stays_reachable(): void {
		global $wp_meta_boxes;

		require_once ABSPATH . 'wp-admin/includes/template.php';

		$order  = $this->add_existing_label( $this->make_order( 'local_pickup' ) );
		$single = $this->make_handler( Single::class );

		$saved = $single->save_meta_value( $order->get_id(), array() );
		$this->assertSame( '3SDEVC000000001', $saved['labels']['label']['barcode'] );
		$this->assertSame( 0, $this->api_calls );

		$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		try {
			$single->add_meta_box( Utils::get_order_screen_id(), $order );
			$this->assertTrue( $this->has_label_box() );
		} finally {
			$wp_meta_boxes = array(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$actions = $this->make_handler( Bulk::class )->add_create_label_actions_button( array(), $order );
		$this->assertSame( 'postnl-action-download-label', $actions['postnl-label']['action'] );
	}

	/**
	 * @testdox A Local pickup order without a label gets no Create Label action in the orders list.
	 */
	public function test_local_pickup_order_without_label_has_no_create_action(): void {
		$bulk = $this->make_handler( Bulk::class );

		$this->assertSame( array(), $bulk->add_create_label_actions_button( array(), $this->make_order( 'local_pickup' ) ) );
		$this->assertSame(
			'postnl-action-create-label',
			$bulk->add_create_label_actions_button( array(), $this->make_order( 'flat_rate' ) )['postnl-label']['action']
		);
	}

	/**
	 * @testdox Bulk "Change Shipping Options" skips a Local pickup order and still updates a PostNL order.
	 */
	public function test_bulk_change_shipping_options_skips_local_pickup(): void {
		$pickup = $this->make_order( 'local_pickup' );
		$postnl = $this->make_order( 'flat_rate' );

		$_REQUEST['postnl_shipping_zone']               = 'NL';
		$_REQUEST['postnl_default_shipping_options_nl'] = 'signature_on_delivery';

		try {
			$this->make_handler( Bulk::class )->bulk_action_change_shipping_options( '', 'postnl-change-shipping-options', array( $pickup->get_id(), $postnl->get_id() ) );
		} finally {
			unset( $_REQUEST['postnl_shipping_zone'], $_REQUEST['postnl_default_shipping_options_nl'] );
			delete_option( '_postnl_bulk_action_confirmation' );
		}

		$this->assertEmpty( wc_get_order( $pickup->get_id() )->get_meta( '_postnl_order_metadata' ) );
		$this->assertSame( 'yes', wc_get_order( $postnl->get_id() )->get_meta( '_postnl_order_metadata' )['backend']['signature_on_delivery'] ?? null );
	}

	/**
	 * @testdox An order with one PostNL line and one Local pickup line stays a PostNL order; an order without shipping lines is not Local pickup.
	 */
	public function test_mixed_shipment_stays_postnl(): void {
		$mixed = $this->make_order( 'local_pickup', 'flat_rate' );

		$this->assertFalse( Utils::is_local_pickup_order( $mixed ) );
		$this->assertNotSame( array(), $this->make_handler( Single::class )->get_shipping_options( $mixed ) );

		$no_lines = new \WC_Order();
		$no_lines->save();
		$this->post_ids[] = $no_lines->get_id();

		$this->assertFalse( Utils::is_local_pickup_order( $no_lines ) );
	}

	/**
	 * @testdox The classic checkout saves no PostNL delivery day on a Local pickup order, and still does on a PostNL order.
	 */
	public function test_classic_order_save_skips_local_pickup(): void {
		$posted = array(
			'postnl_delivery_day'      => '2026-11-23_08:30-21:30_0',
			'postnl_delivery_day_date' => '2026-11-23',
		);

		$pickup = $this->make_order( 'local_pickup' );
		$postnl = $this->make_order( 'flat_rate' );

		// Called on a handler built without hooks: the plugin registers the classic save
		// hooks only when the checkout page is not a blocks checkout.
		$classic = new class() extends Delivery_Day {
			/**
			 * Skip hook registration.
			 */
			public function init_hooks() {}
		};

		foreach ( array( $pickup, $postnl ) as $order ) {
			$classic->save_data( $order->get_id(), $posted );
			$classic->save_letterbox_data( $order->get_id(), $posted );
			$classic->save_default_data( $order->get_id(), $posted );
		}

		$this->assertEmpty( wc_get_order( $pickup->get_id() )->get_meta( '_postnl_order_metadata' ) );
		$this->assertEmpty( wc_get_order( $pickup->get_id() )->get_meta( '_postnl_letterbox' ) );
		$this->assertSame( '2026-11-23', wc_get_order( $postnl->get_id() )->get_meta( '_postnl_order_metadata' )['frontend']['delivery_day_date'] ?? null );
	}
}
