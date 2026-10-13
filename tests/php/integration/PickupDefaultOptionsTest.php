<?php
/**
 * Integration tests for the pickup default shipping options.
 *
 * The "Default Shipping Pickup" setting is an NL pickup setting. The bulk Create
 * Label action copies every pre-selected option into the label request, so a
 * Belgian pickup order that inherited it (ID Check by default) was sent to the
 * legacy API, while the meta box showed no options for the same order.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Covers Order\Base::get_shipping_options() for pickup orders.
 */
class PickupDefaultOptionsTest extends IntegrationTestCase {

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

	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['default_shipping_options_pickup'] = 'id_check';
		$settings->settings['default_shipping_options_be']     = 'insured_shipping';
	}

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

	/**
	 * @testdox An NL pickup order pre-selects the pickup default.
	 */
	public function test_nl_pickup_order_uses_the_pickup_default(): void {
		$options = $this->make_order_handler()->get_shipping_options( $this->make_pickup_order( 'NL' ) );

		$this->assertSame( array( 'id_check' => 'yes' ), $options );
	}

	/**
	 * @testdox A BE pickup order pre-selects nothing, neither the pickup nor the BE default.
	 */
	public function test_be_pickup_order_has_no_default_options(): void {
		$options = $this->make_order_handler()->get_shipping_options( $this->make_pickup_order( 'BE' ) );

		$this->assertSame( array(), $options );
	}

	/**
	 * An order to the given country with a pickup point chosen at checkout.
	 *
	 * @param string $country Shipping country.
	 * @return \WC_Order
	 */
	private function make_pickup_order( string $country ): \WC_Order {
		$order = new \WC_Order();
		$order->set_shipping_country( $country );
		$order->update_meta_data(
			'_postnl_order_metadata',
			array( 'frontend' => array( 'dropoff_points' => 'PNPNL-01-176227' ) )
		);
		$order->save();

		$this->order_ids[] = $order->get_id();

		return wc_get_order( $order->get_id() );
	}

	/**
	 * A minimal concrete Order\Base that does not register the admin hooks.
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
		};
	}
}
