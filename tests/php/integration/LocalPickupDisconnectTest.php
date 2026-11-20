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

		WC()->session->set( 'chosen_shipping_methods', array() );

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
	 * Answer every PostNL API request with an error so nothing is ever created.
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
	 * Create an NL order with one shipping line and a letterbox-eligible product.
	 *
	 * @param string $method_id Shipping method ID of the shipping line.
	 *
	 * @return \WC_Order
	 */
	private function make_order( string $method_id ): \WC_Order {
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

		$item = new \WC_Order_Item_Shipping();
		$item->set_method_id( $method_id );
		$item->set_method_title( $method_id );
		$order->add_item( $item );
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
		$settings = Settings::get_instance();

		$this->assertNotContains( 'local_pickup', $settings->get_supported_shipping_methods() );
		$this->assertNotContains( 'pickup_location', $settings->get_supported_shipping_methods() );
		$this->assertContains( 'flat_rate', $settings->get_supported_shipping_methods() );
		$this->assertArrayNotHasKey( 'local_pickup', $settings->get_shipping_methods() );
		$this->assertArrayNotHasKey( 'pickup_location', $settings->get_shipping_methods() );
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
	 * @testdox Blocks checkout does not save a PostNL delivery day or pickup point on a Local pickup order.
	 */
	public function test_blocks_order_save_skips_local_pickup(): void {
		$order   = $this->make_order( 'pickup_location' );
		$request = new \WP_REST_Request( 'POST', '/wc/store/v1/checkout' );
		$request->set_param(
			'extensions',
			array(
				'postnl' => array(
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

		$this->assertEmpty( wc_get_order( $order->get_id() )->get_meta( '_postnl_order_metadata' ) );
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
}
