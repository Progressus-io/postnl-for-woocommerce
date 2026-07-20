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
use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Shipping\Item_Info;
use PostNLWooCommerce\Utils;

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
	 * @testdox An explicit admin letterbox_48 selection persists so a fresh order load resolves to 2948, not the 24h default.
	 */
	public function test_admin_letterbox_48_selection_persists_and_resolves(): void {
		// A 24h merchant default means a dropped/unsaved selection would resolve to
		// letterbox (2928); the explicit 48h choice must override it.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();
		$order_id          = $order->get_id();

		// Mirror the admin save path (Order\Base::save_meta_value): normalize the
		// explicit selection and persist the variant before the label engine, which
		// re-reads the order via wc_get_order(), is constructed.
		$selection = Utils::normalize_letterbox_options( array( 'letterbox_48' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', $selection['type'] );
		$order->save_meta_data();

		$this->assertSame(
			'letterbox_48',
			$this->resolve_letterbox_type( array( 'order_details' => array( 'order_id' => $order_id ) ) ),
			'The explicit 48h admin selection must survive a fresh order load and resolve to 2948.'
		);
	}

	/**
	 * @testdox On reload, a stored 48h choice swaps the generic letterbox option for letterbox_48 so the meta box pre-selects 48h.
	 */
	public function test_get_shipping_options_preselects_48h_from_stored_variant(): void {
		// A 24h merchant default proves the swap is driven by the stored variant,
		// not the default: without it get_shipping_options() would pre-select 24h.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$handler = $this->make_order_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox_48' );
		$order->save();

		$options = $handler->get_shipping_options( wc_get_order( $order->get_id() ) );

		$this->assertSame( 'yes', $options['letterbox_48'] ?? '', 'A stored 48h choice must pre-select the 48h option on reload.' );
		$this->assertArrayNotHasKey( 'letterbox', $options, 'The generic 24h letterbox must be swapped out for the 48h variant.' );
	}

	/**
	 * @testdox On reload, a stored 24h choice leaves the generic letterbox option in place and never surfaces letterbox_48.
	 */
	public function test_get_shipping_options_keeps_24h_from_stored_variant(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$handler = $this->make_order_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox' );
		$order->save();

		$options = $handler->get_shipping_options( wc_get_order( $order->get_id() ) );

		$this->assertSame( 'yes', $options['letterbox'] ?? '', 'A stored 24h choice must keep the generic letterbox option selected.' );
		$this->assertArrayNotHasKey( 'letterbox_48', $options, 'A 24h choice must never surface the 48h variant.' );
	}

	/**
	 * @testdox After a 48h selection, the reloaded meta box pre-selects only the 48h checkbox, not both.
	 */
	public function test_add_meta_box_value_shows_only_48h_after_48h_selection(): void {
		// A 24h merchant default proves the display is driven by the stored 48h
		// variant, not the default: without the swap both checkboxes surface.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$handler = $this->make_single_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		// Mirror the persisted state after a 48h save: the backend collapses onto
		// the generic 'letterbox' feature and the variant is recorded separately.
		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox_48' );
		$order->save();

		$fields = $handler->add_meta_box_value( wc_get_order( $order->get_id() ) );

		$this->assertNotSame(
			'yes',
			$this->field( $fields, 'postnl_letterbox' )['value'],
			'The 24h checkbox must not be pre-selected for a 48h order.'
		);
		$this->assertSame(
			'yes',
			$this->field( $fields, 'postnl_letterbox_48' )['value'],
			'The 48h checkbox must be pre-selected for a 48h order.'
		);
	}

	/**
	 * @testdox A 24h selection still pre-selects only the 24h checkbox on reload.
	 */
	public function test_add_meta_box_value_keeps_24h_after_24h_selection(): void {
		// A 48h merchant default proves the display is driven by the stored 24h
		// variant, not the default.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox_48';

		$handler = $this->make_single_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox' );
		$order->save();

		$fields = $handler->add_meta_box_value( wc_get_order( $order->get_id() ) );

		$this->assertSame(
			'yes',
			$this->field( $fields, 'postnl_letterbox' )['value'],
			'The 24h checkbox must stay pre-selected for a 24h order.'
		);
		$this->assertNotSame(
			'yes',
			$this->field( $fields, 'postnl_letterbox_48' )['value'],
			'The 48h checkbox must not surface for a 24h order.'
		);
	}

	/**
	 * @testdox Once a label exists, the pre-selected 48h checkbox is locked (disabled).
	 */
	public function test_add_meta_box_value_disables_48h_checkbox_when_label_exists(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$handler = $this->make_single_handler( true );

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox_48' );
		$order->save();

		$fields = $handler->add_meta_box_value( wc_get_order( $order->get_id() ) );

		$this->assertSame(
			'disabled',
			$this->field( $fields, 'postnl_letterbox_48' )['custom_attributes']['disabled'] ?? '',
			'The pre-selected 48h checkbox must be locked once a label exists.'
		);
	}

	/**
	 * @testdox A 48h letterbox order reports the 48h label as its delivery type instead of "Standard Shipment".
	 */
	public function test_get_delivery_type_returns_48h_label_for_letterbox_order(): void {
		// A 24h merchant default proves the label is driven by the stored 48h
		// variant, not the default.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox';

		$handler = $this->make_order_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox_48' );
		$order->save();

		$this->assertSame(
			Utils::get_letterbox_label_48h(),
			$handler->get_delivery_type( wc_get_order( $order->get_id() ) ),
			'A 48h letterbox order must report the 48h label, not the generic "Standard Shipment".'
		);
	}

	/**
	 * @testdox A 24h letterbox order reports the 24h label as its delivery type.
	 */
	public function test_get_delivery_type_returns_24h_label_for_letterbox_order(): void {
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox_48';

		$handler = $this->make_order_handler();

		$order = new \WC_Order();
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'letterbox' => 'yes' ) );
		$order->update_meta_data( '_postnl_letterbox_type', 'letterbox' );
		$order->save();

		$this->assertSame(
			Utils::get_letterbox_label_24h(),
			$handler->get_delivery_type( wc_get_order( $order->get_id() ) ),
			'A 24h letterbox order must report the 24h label.'
		);
	}

	/**
	 * @testdox A saved non-letterbox selection wins over a letterbox merchant default, so no letterbox label leaks onto the order.
	 */
	public function test_get_delivery_type_prefers_saved_non_letterbox_choice_over_letterbox_default(): void {
		// Force the store to default to Letterbox 48 and make the order auto
		// letterbox eligible, so with no explicit choice the delivery type would
		// resolve to the 48h label. The saved non-letterbox selection must take
		// precedence over that default and never surface a letterbox label.
		Settings::get_instance()->settings['default_automatic_letterboxparcel_product'] = 'letterbox_48';

		$handler = $this->make_order_handler();

		$order = new \WC_Order();
		$order->set_shipping_country( 'NL' );
		$order->update_meta_data( '_postnl_letterbox', true );
		$order->save();
		$this->order_ids[] = $order->get_id();

		$handler->seed_backend_options( $order, array( 'delivery_day' => 'yes' ) );

		$delivery_type = $handler->get_delivery_type( wc_get_order( $order->get_id() ) );

		$this->assertNotSame(
			Utils::get_letterbox_label_24h(),
			$delivery_type,
			'A saved non-letterbox selection must not surface the 24h letterbox label.'
		);
		$this->assertNotSame(
			Utils::get_letterbox_label_48h(),
			$delivery_type,
			'The letterbox merchant default must not override the saved non-letterbox selection.'
		);
	}

	/**
	 * A minimal concrete Order\Base whose only dependencies are the settings
	 * instance and the meta name. The constructor is overridden so the real one
	 * does not register admin hooks, which would leak into sibling tests.
	 *
	 * @return Order_Base
	 */
	private function make_order_handler(): Order_Base {
		return new class() extends Order_Base {
			public function __construct() {
				$this->settings  = Settings::get_instance();
				$this->meta_name = '_' . $this->prefix . 'order_metadata';
			}
			public function init_hooks() {}
			public function seed_backend_options( \WC_Order $order, array $backend ): void {
				$order->update_meta_data( $this->meta_name, array( 'backend' => $backend ) );
				$order->save();
			}
		};
	}

	/**
	 * A minimal concrete Order\Single whose label-file state is controllable and
	 * whose constructor does not register the real admin hooks.
	 *
	 * @param bool $has_label Whether have_label_file() should report a saved label.
	 * @return Single
	 */
	private function make_single_handler( bool $has_label = false ): Single {
		return new class( $has_label ) extends Single {
			/**
			 * @var bool
			 */
			private $fake_has_label;

			public function __construct( bool $has_label ) {
				$this->fake_has_label = $has_label;
				$this->settings       = Settings::get_instance();
				$this->meta_name      = '_' . $this->prefix . 'order_metadata';
			}
			public function init_hooks() {}
			public function have_label_file( $order ) {
				return $this->fake_has_label;
			}
			public function seed_backend_options( \WC_Order $order, array $backend ): void {
				$order->update_meta_data( $this->meta_name, array( 'backend' => $backend ) );
				$order->save();
			}
		};
	}

	/**
	 * Return the meta-box field definition with the given prefixed id.
	 *
	 * @param array  $fields Field definitions from add_meta_box_value().
	 * @param string $id     Prefixed field id to find.
	 * @return array
	 */
	private function field( array $fields, string $id ): array {
		foreach ( $fields as $field ) {
			if ( $field['id'] === $id ) {
				return $field;
			}
		}

		$this->fail( sprintf( 'Field %s not found in the meta box.', $id ) );
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
