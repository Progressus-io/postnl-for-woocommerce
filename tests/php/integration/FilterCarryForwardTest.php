<?php
/**
 * Integration tests for the filter/action carry-forward audit (task 25).
 *
 * Guards that the public extension points third parties rely on keep firing once
 * a flow runs on the V4 SDK path, with the same parameter shape as the legacy
 * path. Only postnl_shipment_addresses needed new wiring in the V4 label service;
 * postnl_order_weight carries forward automatically because the V4 service reuses
 * the shared Shipping\Item_Info. The full matrix lives in
 * docs/postnl-v4-migration/approach-2/filter-carry-forward-matrix.md.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Rest_API\Legacy\Shipping\Client as Legacy_Shipping_Client;
use PostNLWooCommerce\Rest_API\Shipping;
use PostNLWooCommerce\Rest_API\V4\Label\Service as V4_Label_Service;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises the V4 label service's request-construction seam and asserts the
 * legacy filters fire from it with their documented arguments.
 */
class FilterCarryForwardTest extends IntegrationTestCase {

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
	 * IDs of products created during a test, removed on teardown.
	 *
	 * @var int[]
	 */
	private $product_ids = array();

	/**
	 * Put the store in NL so the shipper resolves to a domestic origin.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );
	}

	/**
	 * Restore the store country and remove the fixtures created by the test.
	 */
	protected function tearDown(): void {
		update_option( 'woocommerce_default_country', $this->orig_country );

		foreach ( $this->order_ids as $order_id ) {
			wp_delete_post( $order_id, true );
		}

		foreach ( $this->product_ids as $product_id ) {
			wp_delete_post( $product_id, true );
		}

		parent::tearDown();
	}

	/**
	 * @testdox postnl_order_weight fires from the V4 label path with ( float, WC_Order ).
	 */
	public function test_order_weight_filter_fires_from_v4_path(): void {
		$captured = array();
		$spy      = function ( $weight, $order = null ) use ( &$captured ) {
			$captured[] = array( $weight, $order );

			return $weight;
		};

		add_filter( 'postnl_order_weight', $spy, 10, 2 );

		try {
			$this->make_service()->expose_filter_addresses( $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_order_weight', $spy, 10 );
		}

		$this->assertNotEmpty( $captured, 'postnl_order_weight must fire while the V4 service parses the order.' );

		$last = end( $captured );
		$this->assertIsNumeric( $last[0], 'The first argument must be the numeric total weight.' );
		$this->assertInstanceOf( \WC_Order::class, $last[1], 'The second argument must be the WC_Order, matching the legacy shape.' );
	}

	/**
	 * @testdox postnl_shipment_addresses fires from the V4 label path with ( array, Shipping\Client ).
	 */
	public function test_shipment_addresses_filter_fires_from_v4_path(): void {
		$captured = array();
		$spy      = function ( $addresses, $client = null ) use ( &$captured ) {
			$captured[] = array( $addresses, $client );

			return $addresses;
		};

		add_filter( 'postnl_shipment_addresses', $spy, 10, 2 );

		try {
			$this->make_service()->expose_filter_addresses( $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_shipment_addresses', $spy, 10 );
		}

		$this->assertCount( 1, $captured, 'postnl_shipment_addresses must fire exactly once from the V4 label path.' );

		list( $addresses, $client ) = $captured[0];

		$this->assertIsArray( $addresses, 'The first argument must be the legacy-shaped addresses array.' );
		$this->assertInstanceOf(
			Legacy_Shipping_Client::class,
			$client,
			'The second argument must be a Shipping\Client, matching the legacy shape third parties hook.'
		);

		$recipient = $this->recipient_entry( $addresses );
		$this->assertNotNull( $recipient, 'The addresses array must carry the recipient (AddressType 01) entry.' );
		$this->assertSame( 'Amsterdam', $recipient['City'], 'The recipient entry must reflect the order shipping address.' );
	}

	/**
	 * @testdox A postnl_shipment_addresses modification is honoured by the V4 label path.
	 */
	public function test_shipment_addresses_modification_is_returned(): void {
		$rewrite = function ( $addresses ) {
			foreach ( $addresses as $index => $address ) {
				if ( '01' === ( $address['AddressType'] ?? '' ) ) {
					$addresses[ $index ]['Street'] = 'Rewritten Street';
				}
			}

			return $addresses;
		};

		add_filter( 'postnl_shipment_addresses', $rewrite, 10, 2 );

		try {
			$addresses = $this->make_service()->expose_filter_addresses( $this->make_post_data() );
		} finally {
			remove_filter( 'postnl_shipment_addresses', $rewrite, 10 );
		}

		$recipient = $this->recipient_entry( $addresses );
		$this->assertNotNull( $recipient );
		$this->assertSame(
			'Rewritten Street',
			$recipient['Street'],
			'A third-party address rewrite must reach the V4 request, not be fired and ignored.'
		);
	}

	/**
	 * Find the recipient (AddressType 01) entry in a legacy addresses array.
	 *
	 * @param array $addresses Legacy-shaped addresses array.
	 * @return array|null
	 */
	private function recipient_entry( array $addresses ): ?array {
		foreach ( $addresses as $address ) {
			if ( '01' === ( $address['AddressType'] ?? '' ) ) {
				return $address;
			}
		}

		return null;
	}

	/**
	 * A V4 label service that exposes the request-construction seam create() runs.
	 *
	 * expose_filter_addresses() mirrors the first steps of create(): it builds the
	 * same Shipping\Item_Info (firing postnl_order_weight) and calls the same
	 * protected filter_shipment_addresses() (firing postnl_shipment_addresses),
	 * without the eligibility, mapper and SDK machinery those steps sit in front
	 * of. init_hooks() is a no-op, so instantiating the service registers nothing.
	 *
	 * @return V4_Label_Service
	 */
	private function make_service(): V4_Label_Service {
		return new class() extends V4_Label_Service {
			public function expose_filter_addresses( array $post_data ): array {
				return $this->filter_shipment_addresses( new Shipping\Item_Info( $post_data ) );
			}
		};
	}

	/**
	 * Build a domestic NL order with one physical product and the post-data shape
	 * Order\Base::save_meta_value() hands to the label service.
	 *
	 * @return array
	 */
	private function make_post_data(): array {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Widget' );
		$product->set_regular_price( '10' );
		$product->set_weight( '1.5' );
		$product->save();
		$this->product_ids[] = $product->get_id();

		$order = new \WC_Order();
		$order->set_shipping_first_name( 'Jan' );
		$order->set_shipping_last_name( 'Jansen' );
		$order->set_shipping_company( 'Buyer BV' );
		$order->set_shipping_address_1( 'Main Street' );
		$order->set_shipping_city( 'Amsterdam' );
		$order->set_shipping_postcode( '1234AB' );
		$order->set_shipping_country( 'NL' );
		$order->update_meta_data( '_shipping_house_number', '9' );
		$order->add_product( $product, 1 );
		$order->save();
		$this->order_ids[] = $order->get_id();

		return array(
			'order'                   => $order,
			'saved_data'              => array(
				'backend'  => array( 'delivery_type' => 'Standard' ),
				'frontend' => array(),
			),
			'main_barcode'            => '3SDEVC0000001',
			'barcodes'                => array( '3SDEVC0000001' ),
			'return_barcode'          => '',
			'shipping_return_barcode' => '',
			'is_return_activated'     => false,
		);
	}
}
