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

	protected function setUp(): void {
		parent::setUp();

		// Container declares $tab_field = POSTNL_SETTINGS_ID . '_option' as a
		// property default, which is resolved when the object is created.
		if ( ! defined( 'POSTNL_SETTINGS_ID' ) ) {
			define( 'POSTNL_SETTINGS_ID', 'postnl' );
		}
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
	 * Container subclass that skips the WooCommerce-dependent constructor and
	 * exposes the protected aggregation method under test.
	 */
	private function aggregating_container(): Container {
		return new class() extends Container {
			// Skip Settings::get_instance() and hook registration; this instance is
			// only used to exercise the pure aggregation method.
			public function __construct() {} // phpcs:ignore Squiz.Commenting.FunctionComment.Missing, Generic.CodeAnalysis.EmptyStatement.DetectedFunction

			/**
			 * @param Timeframe_Service_Interface       $timeframe Delivery-day service.
			 * @param Pickup_Location_Service_Interface $pickup    Pickup-location service.
			 * @param array                             $post_data Checkout post input.
			 * @return array
			 */
			public function aggregate( $timeframe, $pickup, $post_data ): array {
				return $this->aggregate_delivery_options( $timeframe, $pickup, $post_data );
			}
		};
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

		$result = $this->aggregating_container()->aggregate( $legacy, $legacy, $this->post_data() );

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

		$result = $this->aggregating_container()->aggregate( $timeframe, $pickup, $this->post_data() );

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

		$result = $this->aggregating_container()->aggregate( $timeframe, $pickup, $this->post_data() );

		$this->assertSame( array( array( 'DeliveryDate' => '03-01-2026' ) ), $result['DeliveryOptions'] );
		$this->assertSame( array(), $result['PickupOptions'] );
	}
}
