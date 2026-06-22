<?php
/**
 * Integration tests for letterbox product-type persistence and resolution.
 *
 * Two distinct concerns are guarded here:
 *
 *  1. Persistence (regression): on the classic (shortcode) checkout the
 *     customer's 24h/48h choice is read from the selected shipping method and
 *     written to the order as _postnl_letterbox_type. The save callback runs on
 *     the woocommerce_checkout_update_order_meta hook, which fires AFTER the
 *     order has already been saved and which passes only an order id. A callback
 *     that mutates its own freshly loaded order instance therefore has to save
 *     that instance itself: every sibling callback in the chain does. When it
 *     does not, the choice is silently dropped and the label later ships the
 *     24h product (2928) even when the buyer selected and paid for 48h (2948).
 *
 *  2. Resolution: Item_Info::get_letterbox_type() turns the stored choice and
 *     the merchant default into the concrete variant token that maps to the
 *     product code (letterbox -> 2928, letterbox_48 -> 2948).
 *
 * The persistence test is expected to fail until save_letterbox_type() persists
 * the order; the resolution tests document the currently correct behaviour.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Frontend\Base;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Shipping\Item_Info;

/**
 * Covers the classic-checkout persistence path and the variant resolver.
 */
class LetterboxTypeTest extends IntegrationTestCase {

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array|null
	 */
	private $orig_settings = null;

	/**
	 * Backup of the woocommerce_default_country option.
	 *
	 * @var mixed
	 */
	private $orig_country = null;

	/**
	 * IDs of orders created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $order_ids = array();

	/**
	 * Put the store in NL and treat flat_rate as a PostNL-linked method so the
	 * shipping line items built below are recognised by the save callback.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['supported_shipping_methods']               = array( 'flat_rate' );
		$settings->settings['default_automatic_letterboxparcel_product'] = 'customer_decide';
	}

	/**
	 * @testdox Classic checkout persists the selected letterbox variant to the order so the 48h product code survives a reload.
	 */
	public function test_save_letterbox_type_persists_choice_to_order(): void {
		$order    = $this->make_order_with_letterbox_shipping( 'letterbox_48' );
		$order_id = $order->get_id();

		// Precondition: the resolved meta is not present before the callback runs.
		$this->assertSame(
			'',
			$order->get_meta( '_postnl_letterbox_type' ),
			'The order must not carry _postnl_letterbox_type before save_letterbox_type() runs.'
		);

		$this->make_frontend_handler()->save_letterbox_type( $order_id, array() );

		// Re-read from the data store. The value must have been written, not left
		// on the discarded order instance loaded inside the callback.
		$reloaded = wc_get_order( $order_id );

		$this->assertSame(
			'letterbox_48',
			$reloaded->get_meta( '_postnl_letterbox_type' ),
			'save_letterbox_type() must persist the choice so Item_Info::get_letterbox_type() ships 2948 rather than the 2928 fallback.'
		);
	}

	/**
	 * @testdox A stored 48h choice wins over a 24h merchant default.
	 */
	public function test_get_letterbox_type_prefers_stored_48h_choice(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$order_id = $this->make_order_with_stored_type( 'letterbox_48' );

		$this->assertSame(
			'letterbox_48',
			$this->resolve_letterbox_type( array( 'order_details' => array( 'order_id' => $order_id ) ) )
		);
	}

	/**
	 * @testdox A stored 24h choice wins over a 48h merchant default.
	 */
	public function test_get_letterbox_type_prefers_stored_24h_choice(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox_48';

		$order_id = $this->make_order_with_stored_type( 'letterbox' );

		$this->assertSame(
			'letterbox',
			$this->resolve_letterbox_type( array( 'order_details' => array( 'order_id' => $order_id ) ) )
		);
	}

	/**
	 * @testdox With no stored choice, a forced 48h default resolves to the 48h variant.
	 */
	public function test_get_letterbox_type_uses_forced_48h_default(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox_48';

		$this->assertSame( 'letterbox_48', $this->resolve_letterbox_type( array() ) );
	}

	/**
	 * @testdox With no stored choice, a forced 24h default resolves to the 24h variant.
	 */
	public function test_get_letterbox_type_uses_forced_24h_default(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$this->assertSame( 'letterbox', $this->resolve_letterbox_type( array() ) );
	}

	/**
	 * @testdox Customer-decide with no recorded choice falls back to the 24h variant.
	 */
	public function test_get_letterbox_type_customer_decide_defaults_to_24h(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'customer_decide';

		// No order is passed, which exercises the customer_decide fallback without
		// depending on the order-only logging branch.
		$this->assertSame( 'letterbox', $this->resolve_letterbox_type( array() ) );
	}

	/**
	 * Build an order with a PostNL-linked shipping line item that carries the
	 * given rate-level letterbox_type meta (as the canonical rate would).
	 *
	 * @param string $letterbox_type Variant token to attach, or '' for none.
	 * @return \WC_Order
	 */
	private function make_order_with_letterbox_shipping( string $letterbox_type ): \WC_Order {
		$order = new \WC_Order();

		$item = new \WC_Order_Item_Shipping();
		$item->set_method_title( 'Flat rate' );
		$item->set_method_id( 'flat_rate' );

		if ( '' !== $letterbox_type ) {
			$item->add_meta_data( 'letterbox_type', $letterbox_type, true );
		}

		$order->add_item( $item );
		$order->save();

		$this->order_ids[] = $order->get_id();

		return $order;
	}

	/**
	 * Build an order that already has the resolved _postnl_letterbox_type meta.
	 *
	 * @param string $letterbox_type Variant token to store.
	 * @return int Order ID.
	 */
	private function make_order_with_stored_type( string $letterbox_type ): int {
		$order = new \WC_Order();
		$order->update_meta_data( '_postnl_letterbox_type', $letterbox_type );
		$order->save();

		$this->order_ids[] = $order->get_id();

		return $order->get_id();
	}

	/**
	 * A minimal concrete Frontend\Base whose only dependency is the settings
	 * instance. The constructor is overridden so the real one does not register
	 * the global checkout hooks, which would leak into sibling tests.
	 *
	 * @return Base
	 */
	private function make_frontend_handler(): Base {
		return new class() extends Base {
			public function __construct() {
				$this->settings = Settings::get_instance();
			}
			public function set_primary_field_name() {}
			public function set_template_file() {}
			public function get_fields() {
				return array();
			}
			public function is_enabled() {
				return true;
			}
			public function add_checkout_tab( $tabs, $response ) {
				return $tabs;
			}
			public function get_content_data( $response, $post_data ) {
				return array();
			}
			public function validate_fields( $data, $posted_data ) {
				return $data;
			}
		};
	}

	/**
	 * Invoke the protected Item_Info::get_letterbox_type() with the given API
	 * args, bypassing the heavy constructor.
	 *
	 * @param array $api_args API args to inject.
	 * @return string Resolved variant token.
	 */
	private function resolve_letterbox_type( array $api_args ): string {
		$item_info = ( new \ReflectionClass( Item_Info::class ) )->newInstanceWithoutConstructor();

		$settings_prop = new \ReflectionProperty( Item_Info::class, 'settings' );
		$settings_prop->setAccessible( true );
		$settings_prop->setValue( $item_info, Settings::get_instance() );

		$args_prop = new \ReflectionProperty( Item_Info::class, 'api_args' );
		$args_prop->setAccessible( true );
		$args_prop->setValue( $item_info, $api_args );

		$method = new \ReflectionMethod( Item_Info::class, 'get_letterbox_type' );
		$method->setAccessible( true );

		return (string) $method->invoke( $item_info );
	}

	/**
	 * Restore global state mutated by the tests.
	 */
	protected function tearDown(): void {
		foreach ( $this->order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof \WC_Order ) {
				$order->delete( true );
			}
		}
		$this->order_ids = array();

		if ( null !== $this->orig_settings ) {
			Settings::get_instance()->settings = $this->orig_settings;
			$this->orig_settings               = null;
		}

		if ( null !== $this->orig_country ) {
			update_option( 'woocommerce_default_country', $this->orig_country );
			$this->orig_country = null;
		}

		parent::tearDown();
	}
}
