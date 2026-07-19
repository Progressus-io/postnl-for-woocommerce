<?php
/**
 * Unit tests for Frontend\Container checkout aggregation.
 *
 * @package PostNLWooCommerce\Tests\Frontend
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Frontend;

use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Frontend\Container::aggregate_delivery_options
 */
class ContainerCheckoutAggregationTest extends UnitTestCase {

	/**
	 * Invoke the protected static aggregation method under test.
	 *
	 * Reflection is used instead of instantiating Container so the test never
	 * touches the WooCommerce-dependent constructor or the POSTNL_SETTINGS_ID
	 * constant (defining it here would leak into every later test in the run).
	 *
	 * @param Timeframe_Service_Interface       $timeframe Delivery-day service.
	 * @param Pickup_Location_Service_Interface $pickup    Pickup-location service.
	 * @param array                             $post_data Checkout post input.
	 * @return array
	 */
	private function aggregate( $timeframe, $pickup, array $post_data ): array {
		$method = new \ReflectionMethod( Container::class, 'aggregate_delivery_options' );
		$method->setAccessible( true );

		return $method->invoke( null, $timeframe, $pickup, $post_data );
	}

	/**
	 * A checkout POST payload; contents are irrelevant to the aggregation itself.
	 *
	 * @return array<string, string>
	 */
	private function post_data(): array {
		return array(
			'shipping_country'  => 'NL',
			'shipping_postcode' => '1234AB',
		);
	}

	/**
	 * Legacy resolves both flows to one shared service whose single response already
	 * carries both halves, so the pickup lookup must not fire a second time.
	 */
	public function test_shared_legacy_instance_issues_a_single_call(): void {
		$legacy = new class() implements Timeframe_Service_Interface, Pickup_Location_Service_Interface {
			public int $delivery_calls = 0;
			public int $pickup_calls   = 0;

			public function get_delivery_options( array $post_data ): array {
				++$this->delivery_calls;
				return array(
					'DeliveryOptions' => array( array( 'DeliveryDate' => '01-01-2026' ) ),
					'PickupOptions'   => array( array( 'PickupDate' => '01-01-2026' ) ),
				);
			}

			public function get_pickup_locations( array $post_data ): array {
				++$this->pickup_calls;
				return array();
			}
		};

		$result = $this->aggregate( $legacy, $legacy, $this->post_data() );

		$this->assertSame( 1, $legacy->delivery_calls, 'Delivery options should be fetched exactly once.' );
		$this->assertSame( 0, $legacy->pickup_calls, 'The shared legacy response must not trigger a second pickup call.' );
		$this->assertArrayHasKey( 'DeliveryOptions', $result );
		$this->assertArrayHasKey( 'PickupOptions', $result );
		$this->assertSame( array( array( 'PickupDate' => '01-01-2026' ) ), $result['PickupOptions'] );
	}

	/**
	 * V4 splits the endpoint in two, so both halves must be queried and merged into
	 * the same combined shape the front end already renders.
	 */
	public function test_distinct_v4_services_compose_both_halves(): void {
		$timeframe = new class() implements Timeframe_Service_Interface {
			public int $calls = 0;

			public function get_delivery_options( array $post_data ): array {
				++$this->calls;
				return array( 'DeliveryOptions' => array( array( 'DeliveryDate' => '02-01-2026' ) ) );
			}
		};

		$pickup = new class() implements Pickup_Location_Service_Interface {
			public int $calls = 0;

			public function get_pickup_locations( array $post_data ): array {
				++$this->calls;
				return array( 'PickupOptions' => array( array( 'PickupDate' => '02-01-2026' ) ) );
			}
		};

		$result = $this->aggregate( $timeframe, $pickup, $this->post_data() );

		$this->assertSame( 1, $timeframe->calls );
		$this->assertSame( 1, $pickup->calls );
		$this->assertSame( array( array( 'DeliveryDate' => '02-01-2026' ) ), $result['DeliveryOptions'] );
		$this->assertSame( array( array( 'PickupDate' => '02-01-2026' ) ), $result['PickupOptions'] );
	}

	/**
	 * A V4 pickup response missing its key degrades to an empty pickup list — which
	 * the front end renders as a hidden pickup tab — without dropping delivery days.
	 */
	public function test_missing_pickup_options_key_yields_empty_pickup_options(): void {
		$timeframe = new class() implements Timeframe_Service_Interface {
			public function get_delivery_options( array $post_data ): array {
				return array( 'DeliveryOptions' => array( array( 'DeliveryDate' => '03-01-2026' ) ) );
			}
		};

		$pickup = new class() implements Pickup_Location_Service_Interface {
			public function get_pickup_locations( array $post_data ): array {
				return array();
			}
		};

		$result = $this->aggregate( $timeframe, $pickup, $this->post_data() );

		$this->assertSame( array( array( 'DeliveryDate' => '03-01-2026' ) ), $result['DeliveryOptions'] );
		$this->assertSame( array(), $result['PickupOptions'] );
	}
}
