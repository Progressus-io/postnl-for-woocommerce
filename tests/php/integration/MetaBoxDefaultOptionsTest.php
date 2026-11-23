<?php
/**
 * Integration tests for the default shipping product the Label & Tracking meta box pre-selects.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Covers the settings default reaching the single order meta box for an order with no saved selection.
 */
class MetaBoxDefaultOptionsTest extends IntegrationTestCase {

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
	 * Put the store in NL.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$this->orig_settings = Settings::get_instance()->settings;
	}

	/**
	 * Default settings per destination and the meta box options each must pre-select.
	 *
	 * @return array
	 */
	public static function default_settings_provider(): array {
		return array(
			'BE packets'                        => array( 'BE', 'packets', array( 'packets' ) ),
			'BE packets + track and trace'      => array( 'BE', 'packets|track_and_trace', array( 'packets', 'track_and_trace' ) ),
			'BE packets + t&t + insured'        => array( 'BE', 'packets|track_and_trace|insured_shipping', array( 'packets', 'track_and_trace', 'insured_shipping' ) ),
			'BE mailbox packet'                 => array( 'BE', 'mailboxpacket', array( 'mailboxpacket' ) ),
			'BE mailbox packet + t&t'           => array( 'BE', 'mailboxpacket|track_and_trace', array( 'mailboxpacket', 'track_and_trace' ) ),
			'BE standard belgium + insured'     => array( 'BE', 'standard_belgium|insured_shipping', array( 'insured_shipping' ) ),
			'EU eu parcel + t&t + insured plus' => array( 'DE', 'eu_parcel|track_and_trace|insured_plus', array( 'track_and_trace', 'insured_plus' ) ),
		);
	}

	/**
	 * Pre-select the default shipping product on the single order meta box.
	 *
	 * @testdox add_meta_box_value() pre-selects the default shipping product from the settings for an order with no saved selection.
	 *
	 * @dataProvider default_settings_provider
	 *
	 * @param string $country         Shipping country of the order.
	 * @param string $default_setting Pipe-separated default shipping option from the settings.
	 * @param array  $expected        Options that must be pre-selected.
	 */
	public function test_add_meta_box_value_preselects_default_shipping_product( string $country, string $default_setting, array $expected ): void {
		$zone = 'BE' === $country ? 'be' : 'eu';

		Settings::get_instance()->settings[ 'default_shipping_options_' . $zone ] = $default_setting;

		$order = new \WC_Order();
		$order->set_shipping_country( $country );
		$order->save();
		$this->order_ids[] = $order->get_id();

		$values = array();
		foreach ( $this->make_single_handler()->add_meta_box_value( wc_get_order( $order->get_id() ) ) as $field ) {
			$values[ $field['id'] ] = $field['value'] ?? null;
		}

		foreach ( array( 'packets', 'mailboxpacket', 'track_and_trace', 'insured_shipping', 'insured_plus' ) as $option ) {
			$this->assertArrayHasKey( 'postnl_' . $option, $values );
			$this->assertSame(
				in_array( $option, $expected, true ) ? 'yes' : '',
				$values[ 'postnl_' . $option ],
				$option . ' must match the ' . $default_setting . ' default'
			);
		}
	}

	/**
	 * Build an Order\Single that does not register the real admin hooks.
	 *
	 * @return Single
	 */
	private function make_single_handler(): Single {
		return new class() extends Single {

			/**
			 * Load the settings and meta key without registering hooks.
			 */
			public function __construct() {
				$this->settings  = Settings::get_instance();
				$this->meta_name = '_' . $this->prefix . 'order_metadata';
			}

			/**
			 * Register no hooks.
			 */
			public function init_hooks() {}
		};
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
