<?php
/**
 * Integration regression tests for the Legacy service recursion guard.
 *
 * Order\Base's public label methods (create_label(), maybe_create_letterbox(),
 * maybe_create_return_label()) now resolve their service through Service_Factory.
 * The Legacy wrappers extend Order\Base and therefore inherit those same public
 * methods, so each wrapper's create() must call the protected *_pipeline()
 * variant instead.
 *
 * If a wrapper ever calls the public method, every hop builds a fresh factory and
 * a fresh wrapper, so the call recurses until PHP exhausts memory. That is an
 * uncatchable fatal on the live label path rather than a handled error, and the
 * trap is inviting because create_label() is the obvious-looking method while
 * create_label_pipeline() is the odd one.
 *
 * These tests pin the invariant directly: create() must reach the pipeline
 * without ever consulting the factory. Each probe subclass counts service_factory()
 * calls, so a wrapper that starts routing through the factory fails here instead
 * of taking a merchant's label screen down.
 *
 * The probes also hand back a factory pre-seeded with an inert stub rather than a
 * real one. That deliberately breaks the loop at its first hop: without it a
 * regression would recurse until PHP exhausted memory, and an uncatchable fatal
 * would abort the whole suite instead of reporting which invariant broke.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Return_Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Label_Service;
use PostNLWooCommerce\Rest_API\Legacy\Letterbox_Service;
use PostNLWooCommerce\Rest_API\Legacy\Return_Label_Service;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Guards Legacy\{Label,Letterbox,Return_Label}_Service::create() against routing
 * back through Service_Factory.
 */
class ServiceFactoryRecursionTest extends IntegrationTestCase {

	/**
	 * Orders created by the test, deleted on teardown.
	 *
	 * @var \WC_Order[]
	 */
	private $orders = array();

	/**
	 * Products created by the test, deleted on teardown.
	 *
	 * @var \WC_Product[]
	 */
	private $products = array();

	/**
	 * Remove any orders and products the test created.
	 */
	public function tearDown(): void {
		foreach ( $this->orders as $order ) {
			$order->delete( true );
		}
		foreach ( $this->products as $product ) {
			$product->delete( true );
		}
		$this->orders   = array();
		$this->products = array();

		parent::tearDown();
	}

	/**
	 * Create a Dutch order carrying one line item.
	 *
	 * The line item matters: the shipping Item_Info walks order_details['contents']
	 * while building the request, so an order with no items would emit notices that
	 * have nothing to do with what these tests assert. These tests never reach the
	 * PostNL API; they only need the pipeline to be entered, not to succeed.
	 *
	 * @return \WC_Order
	 */
	private function make_order(): \WC_Order {
		$product = new \WC_Product_Simple();
		$product->set_name( 'PostNL recursion guard fixture' );
		$product->set_regular_price( '10.00' );
		$product->set_weight( '1' );
		$product->save();

		$this->products[] = $product;

		$order = wc_create_order();
		$order->add_product( $product, 1 );
		$order->set_shipping_first_name( 'Jan' );
		$order->set_shipping_last_name( 'de Vries' );
		$order->set_shipping_address_1( 'Siriusdreef' );
		$order->set_shipping_address_2( '42' );
		$order->set_shipping_postcode( '2132WT' );
		$order->set_shipping_city( 'Hoofddorp' );
		$order->set_shipping_country( 'NL' );
		$order->calculate_totals();
		$order->save();

		$this->orders[] = $order;

		return $order;
	}

	/**
	 * Build a factory whose label flows resolve to inert stubs.
	 *
	 * Returned by the probes in place of a real factory so that a regressed
	 * wrapper resolves to a stub that returns immediately, instead of building
	 * another real wrapper and recursing into a fatal.
	 *
	 * @return Service_Factory
	 */
	private function inert_factory(): Service_Factory {
		$label_stub = new class() implements Label_Service_Interface {
			/**
			 * @param array $post_data Post data.
			 * @return array
			 */
			public function create( array $post_data ): array {
				return array();
			}
		};

		$return_stub = new class() implements Return_Label_Service_Interface {
			/**
			 * @param array $post_data Post data.
			 * @return array
			 */
			public function create( array $post_data ): array {
				return array();
			}

			/**
			 * @param int $order_id Order ID.
			 * @return array
			 */
			public function activate( int $order_id ): array {
				return array();
			}
		};

		$factory = new Service_Factory( null );
		$factory->set_legacy_service( 'label', $label_stub );
		$factory->set_legacy_service( 'letterbox', $label_stub );
		$factory->set_legacy_service( 'return_label', $return_stub );

		return $factory;
	}

	/**
	 * Build the post data shape the label pipelines expect.
	 *
	 * @param array $backend Backend saved-data flags for this call.
	 * @return array
	 */
	private function make_post_data( array $backend = array() ): array {
		return array(
			'order'        => $this->make_order(),
			'saved_data'   => array(
				'backend' => array_merge(
					array(
						'num_labels'          => 1,
						'letterbox'           => 'no',
						'create_return_label' => 'no',
					),
					$backend
				),
			),
			'main_barcode' => '3SABC1234567',
			'barcodes'     => array( '3SABC1234567' ),
		);
	}

	/**
	 * @testdox Label_Service::create() reaches the pipeline without consulting the factory
	 *
	 * create_label_pipeline() has no short-circuit, so it validates the order and
	 * throws before any HTTP call. Catching that exception is what proves the
	 * pipeline was entered directly; the assertion is that the factory was never
	 * touched on the way.
	 */
	public function test_label_service_create_does_not_route_through_the_factory(): void {
		$probe = new class() extends Label_Service {
			/**
			 * Number of times the factory was consulted.
			 *
			 * @var int
			 */
			public $factory_calls = 0;

			/**
			 * Inert factory supplied by the test.
			 *
			 * @var Service_Factory|null
			 */
			public $inert = null;

			/**
			 * Count factory access and hand back the inert factory, so a regression
			 * surfaces as a failed assertion rather than a runaway recursion.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				++$this->factory_calls;
				return $this->inert;
			}
		};

		$probe->inert = $this->inert_factory();

		try {
			$probe->create( $this->make_post_data() );
		} catch ( \Exception $e ) {
			// Expected: the pipeline validates the order and throws before reaching
			// the network. Reaching here at all means no runaway recursion.
			$this->assertNotEmpty( $e->getMessage() );
		}

		$this->assertSame(
			0,
			$probe->factory_calls,
			'Label_Service::create() must call create_label_pipeline(), not the factory-routed create_label().'
		);
	}

	/**
	 * @testdox Letterbox_Service::create() reaches the pipeline without consulting the factory
	 *
	 * With the letterbox flag off, the pipeline short-circuits to an empty array,
	 * so this exercises the delegation without touching the network at all.
	 */
	public function test_letterbox_service_create_does_not_route_through_the_factory(): void {
		$probe = new class() extends Letterbox_Service {
			/**
			 * Number of times the factory was consulted.
			 *
			 * @var int
			 */
			public $factory_calls = 0;

			/**
			 * Inert factory supplied by the test.
			 *
			 * @var Service_Factory|null
			 */
			public $inert = null;

			/**
			 * Count factory access and hand back the inert factory, so a regression
			 * surfaces as a failed assertion rather than a runaway recursion.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				++$this->factory_calls;
				return $this->inert;
			}
		};

		$probe->inert = $this->inert_factory();

		$result = $probe->create( $this->make_post_data( array( 'letterbox' => 'no' ) ) );

		$this->assertSame( array(), $result, 'The letterbox guard should short-circuit to an empty array.' );
		$this->assertSame(
			0,
			$probe->factory_calls,
			'Letterbox_Service::create() must call maybe_create_letterbox_pipeline(), not the factory-routed maybe_create_letterbox().'
		);
	}

	/**
	 * @testdox Return_Label_Service::create() reaches the pipeline without consulting the factory
	 *
	 * With the return-label flag off, the pipeline short-circuits to an empty
	 * array, so this exercises the delegation without touching the network.
	 */
	public function test_return_label_service_create_does_not_route_through_the_factory(): void {
		$probe = new class() extends Return_Label_Service {
			/**
			 * Number of times the factory was consulted.
			 *
			 * @var int
			 */
			public $factory_calls = 0;

			/**
			 * Inert factory supplied by the test.
			 *
			 * @var Service_Factory|null
			 */
			public $inert = null;

			/**
			 * Count factory access and hand back the inert factory, so a regression
			 * surfaces as a failed assertion rather than a runaway recursion.
			 *
			 * @return Service_Factory
			 */
			protected function service_factory(): Service_Factory {
				++$this->factory_calls;
				return $this->inert;
			}
		};

		$probe->inert = $this->inert_factory();

		$result = $probe->create( $this->make_post_data( array( 'create_return_label' => 'no' ) ) );

		$this->assertSame( array(), $result, 'The return-label guard should short-circuit to an empty array.' );
		$this->assertSame(
			0,
			$probe->factory_calls,
			'Return_Label_Service::create() must call maybe_create_return_label_pipeline(), not the factory-routed maybe_create_return_label().'
		);
	}
}
