<?php
/**
 * Unit tests for Service_Factory.
 *
 * @package PostNLWooCommerce\Tests\Rest_API
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API;

use Brain\Monkey\Filters;
use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Pickup_Location_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Postcode_Check_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Return_Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Smart_Returns_Service_Interface;
use PostNLWooCommerce\Rest_API\Contracts\Timeframe_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Barcode_Service as Legacy_Barcode_Service;
use PostNLWooCommerce\Rest_API\Legacy\Checkout_Service as Legacy_Checkout_Service;
use PostNLWooCommerce\Rest_API\Legacy\Postcode_Check_Service as Legacy_Postcode_Check_Service;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns_Service as Legacy_Smart_Returns_Service;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\Service_Factory
 */
class Service_FactoryTest extends UnitTestCase {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Return an anonymous settings object that exposes get_v4_api_key().
	 * method_exists() returns true for it, satisfying the has_v4_key() check.
	 *
	 * @param string $key API key to return (default non-empty).
	 * @return object
	 */
	private function settings_with_key( string $key = 'test-v4-key' ): object {
		return new class( $key ) {
			/** @var string */
			private $api_key;
			/** @param string $k Key value. */
			public function __construct( string $k ) {
				$this->api_key = $k;
			}
			/** @return string */
			public function get_v4_api_key(): string {
				return $this->api_key;
			}
		};
	}

	/**
	 * Return an anonymous settings object that does NOT have get_v4_api_key().
	 * Simulates the Settings class before the V4 key field PR is merged.
	 *
	 * @return object
	 */
	private function settings_without_v4_getter(): object {
		return new class {};
	}

	/**
	 * Return a minimal Label_Service_Interface stub.
	 * Used to pre-seed flows whose Legacy services extend Order\Base and therefore
	 * require WooCommerce constants and Settings::get_instance() in their constructors.
	 *
	 * @return Label_Service_Interface
	 */
	private function make_label_stub(): Label_Service_Interface {
		return new class implements Label_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function create( array $post_data ): array {
				return array();
			}
		};
	}

	/**
	 * Return a minimal Return_Label_Service_Interface stub.
	 *
	 * @return Return_Label_Service_Interface
	 */
	private function make_return_label_stub(): Return_Label_Service_Interface {
		return new class implements Return_Label_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function create( array $post_data ): array {
				return array();
			}
			/** @param int $order_id Order ID. @return array */
			public function activate( int $order_id ): array {
				return array();
			}
		};
	}

	/**
	 * Create a Service_Factory with label/letterbox/return_label pre-seeded so the
	 * Order\Base constructor is never invoked during unit tests.
	 *
	 * @param object|null $settings Settings object to pass to the factory.
	 * @return array{ 0: Service_Factory, 1: Label_Service_Interface, 2: Label_Service_Interface, 3: Return_Label_Service_Interface }
	 */
	private function make_factory_with_wc_stubs( $settings = null ): array {
		$factory         = new Service_Factory( $settings );
		$label_stub      = $this->make_label_stub();
		$letterbox_stub  = $this->make_label_stub();
		$return_stub     = $this->make_return_label_stub();

		$factory->set_legacy_service( 'label',        $label_stub );
		$factory->set_legacy_service( 'letterbox',    $letterbox_stub );
		$factory->set_legacy_service( 'return_label', $return_stub );

		return array( $factory, $label_stub, $letterbox_stub, $return_stub );
	}

	// -------------------------------------------------------------------------
	// Scenario 1 — No V4 key: every factory method returns the expected Legacy service
	// -------------------------------------------------------------------------

	/** @testdox No V4 key: barcode_service() returns Legacy_Barcode_Service */
	public function test_no_v4_key_barcode_returns_legacy(): void {
		$factory = new Service_Factory( null );
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	/** @testdox No V4 key: timeframe_service() returns Legacy_Checkout_Service */
	public function test_no_v4_key_timeframe_returns_legacy(): void {
		$factory = new Service_Factory( null );
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->timeframe_service() );
	}

	/** @testdox No V4 key: pickup_location_service() returns Legacy_Checkout_Service */
	public function test_no_v4_key_pickup_location_returns_legacy(): void {
		$factory = new Service_Factory( null );
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->pickup_location_service() );
	}

	/**
	 * @testdox No V4 key: label_service() returns the pre-seeded legacy stub
	 *
	 * Legacy_Label_Service extends Order\Base which calls Settings::get_instance() and
	 * references plugin constants in its constructor.  A pre-seeded stub is used here
	 * to avoid WooCommerce dependencies in unit tests.
	 */
	public function test_no_v4_key_label_returns_legacy(): void {
		[ $factory, $label_stub ] = $this->make_factory_with_wc_stubs();
		$this->assertSame( $label_stub, $factory->label_service() );
	}

	/** @testdox No V4 key: letterbox_service() returns the pre-seeded legacy stub */
	public function test_no_v4_key_letterbox_returns_legacy(): void {
		[ $factory, , $letterbox_stub ] = $this->make_factory_with_wc_stubs();
		$this->assertSame( $letterbox_stub, $factory->letterbox_service() );
	}

	/** @testdox No V4 key: return_label_service() returns the pre-seeded legacy stub */
	public function test_no_v4_key_return_label_returns_legacy(): void {
		[ $factory, , , $return_stub ] = $this->make_factory_with_wc_stubs();
		$this->assertSame( $return_stub, $factory->return_label_service() );
	}

	/** @testdox No V4 key: postcode_check_service() returns Legacy_Postcode_Check_Service */
	public function test_no_v4_key_postcode_check_returns_legacy(): void {
		$factory = new Service_Factory( null );
		$this->assertInstanceOf( Legacy_Postcode_Check_Service::class, $factory->postcode_check_service() );
	}

	/** @testdox No V4 key: smart_returns_service() returns Legacy_Smart_Returns_Service */
	public function test_no_v4_key_smart_returns_returns_legacy(): void {
		$factory = new Service_Factory( null );
		$this->assertInstanceOf( Legacy_Smart_Returns_Service::class, $factory->smart_returns_service() );
	}

	// -------------------------------------------------------------------------
	// Scenario 2 — Settings object present but get_v4_api_key() not yet defined
	// -------------------------------------------------------------------------

	/**
	 * @testdox Settings without get_v4_api_key(): barcode_service() still returns Legacy
	 *
	 * Covers the case where the V4 key settings field PR has not been merged yet.
	 */
	public function test_settings_without_getter_barcode_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_without_v4_getter() );
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	/** @testdox Settings without get_v4_api_key(): postcode_check_service() still returns Legacy */
	public function test_settings_without_getter_postcode_check_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_without_v4_getter() );
		$this->assertInstanceOf( Legacy_Postcode_Check_Service::class, $factory->postcode_check_service() );
	}

	// -------------------------------------------------------------------------
	// Scenario 3 — V4 key present but feature flag disabled → Legacy
	// -------------------------------------------------------------------------

	/**
	 * @testdox V4 key present, flag off: barcode_service() returns Legacy
	 *
	 * Brain\Monkey stubs apply_filters() to return its second argument (false) by
	 * default, so Router::sdk_enabled_for() returns false for all flows unless an
	 * explicit filter expectation overrides it.
	 */
	public function test_key_present_flag_off_barcode_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	/** @testdox V4 key present, flag off: timeframe_service() returns Legacy */
	public function test_key_present_flag_off_timeframe_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->timeframe_service() );
	}

	/** @testdox V4 key present, flag off: postcode_check_service() returns Legacy */
	public function test_key_present_flag_off_postcode_check_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertInstanceOf( Legacy_Postcode_Check_Service::class, $factory->postcode_check_service() );
	}

	/** @testdox Empty V4 key string: barcode_service() returns Legacy */
	public function test_empty_v4_key_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_with_key( '' ) );
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	/** @testdox Whitespace-only V4 key: barcode_service() returns Legacy */
	public function test_whitespace_v4_key_returns_legacy(): void {
		$factory = new Service_Factory( $this->settings_with_key( '   ' ) );
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	// -------------------------------------------------------------------------
	// Scenario 4 — V4 key + flag enabled + stub injected → returns V4 stub
	// -------------------------------------------------------------------------

	/**
	 * @dataProvider v4_stubs_provider
	 * @testdox V4 key + flag + stub: returns stub for flow
	 *
	 * @param string $flow    Flow identifier passed to inject_v4_service().
	 * @param string $method  Factory method to call (e.g. 'barcode_service').
	 * @param object $stub    Stub implementing the flow's interface.
	 */
	public function test_v4_key_flag_stub_returns_v4_stub( string $flow, string $method, object $stub ): void {
		Filters\expectApplied( "postnl_sdk_enable_{$flow}" )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		$factory->inject_v4_service( $flow, $stub );

		$this->assertSame( $stub, $factory->$method() );
	}

	/**
	 * Provides one case per V4-eligible flow.
	 *
	 * Anonymous class instances are created once here; PHPUnit caches the provider
	 * result, so assertSame() in the test correctly checks object identity.
	 *
	 * @return array<string, array{ string, string, object }>
	 */
	public static function v4_stubs_provider(): array {
		$barcode_stub = new class implements Barcode_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function generate( array $post_data ): array {
				return array();
			}
		};

		$timeframe_stub = new class implements Timeframe_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function get_delivery_options( array $post_data ): array {
				return array();
			}
		};

		$pickup_stub = new class implements Pickup_Location_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function get_pickup_locations( array $post_data ): array {
				return array();
			}
		};

		$label_stub = new class implements Label_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function create( array $post_data ): array {
				return array();
			}
		};

		$letterbox_stub = new class implements Label_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function create( array $post_data ): array {
				return array();
			}
		};

		$return_stub = new class implements Return_Label_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function create( array $post_data ): array {
				return array();
			}
			/** @param int $order_id Order ID. @return array */
			public function activate( int $order_id ): array {
				return array();
			}
		};

		$smart_returns_stub = new class implements Smart_Returns_Service_Interface {
			/**
			 * @param \WC_Order $order Order object.
			 * @return array
			 */
			public function generate( \WC_Order $order ): array {
				return array();
			}
		};

		return array(
			'barcode'         => array( 'barcode',         'barcode_service',         $barcode_stub ),
			'timeframe'       => array( 'timeframe',       'timeframe_service',       $timeframe_stub ),
			'pickup_location' => array( 'pickup_location', 'pickup_location_service', $pickup_stub ),
			'label'           => array( 'label',           'label_service',           $label_stub ),
			'letterbox'       => array( 'letterbox',       'letterbox_service',       $letterbox_stub ),
			'return_label'    => array( 'return_label',    'return_label_service',    $return_stub ),
			'smart_returns'   => array( 'smart_returns',   'smart_returns_service',   $smart_returns_stub ),
		);
	}

	// -------------------------------------------------------------------------
	// Scenario 5 — Enabling one flow must not bleed into others
	// -------------------------------------------------------------------------

	/**
	 * @testdox Enabling barcode does not affect label, timeframe, pickup_location, or smart_returns
	 */
	public function test_enabling_barcode_does_not_affect_other_flows(): void {
		Filters\expectApplied( 'postnl_sdk_enable_barcode' )->andReturn( true );

		$barcode_stub = new class implements Barcode_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function generate( array $post_data ): array {
				return array();
			}
		};

		$factory = new Service_Factory( $this->settings_with_key() );
		$factory->inject_v4_service( 'barcode', $barcode_stub );

		// Barcode must return the V4 stub.
		$this->assertSame( $barcode_stub, $factory->barcode_service() );

		// All other non-WC flows must still return their Legacy services.
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->timeframe_service() );
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->pickup_location_service() );
		$this->assertInstanceOf( Legacy_Smart_Returns_Service::class, $factory->smart_returns_service() );
		$this->assertInstanceOf( Legacy_Postcode_Check_Service::class, $factory->postcode_check_service() );
	}

	// -------------------------------------------------------------------------
	// Scenario 6 — postcode_check always remains Legacy
	// -------------------------------------------------------------------------

	/**
	 * @testdox postcode_check_service() always returns Legacy — ignores any injected V4 stub
	 *
	 * postcode_check is intentionally absent from Router::SUPPORTED_FLOWS, so the
	 * factory has no V4 branch for it regardless of key or flags.
	 */
	public function test_postcode_check_always_legacy_ignores_v4_injection(): void {
		$stub = new class implements Postcode_Check_Service_Interface {
			/** @param array $post_data Post data. @return array */
			public function check( array $post_data ): array {
				return array();
			}
		};

		$factory = new Service_Factory( $this->settings_with_key() );
		// Injecting under 'postcode_check' must have no effect.
		$factory->inject_v4_service( 'postcode_check', $stub );

		$result = $factory->postcode_check_service();

		$this->assertNotSame( $stub, $result );
		$this->assertInstanceOf( Legacy_Postcode_Check_Service::class, $result );
	}

	// -------------------------------------------------------------------------
	// Scenario 7 — No V4 service registered → Legacy even with key + flag
	// -------------------------------------------------------------------------

	/**
	 * @testdox V4 key + flag but no stub: barcode_service() returns Legacy
	 */
	public function test_key_and_flag_but_no_stub_barcode_returns_legacy(): void {
		Filters\expectApplied( 'postnl_sdk_enable_barcode' )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		// Deliberately no inject_v4_service() call.
		$this->assertInstanceOf( Legacy_Barcode_Service::class, $factory->barcode_service() );
	}

	/**
	 * @testdox V4 key + flag but no stub: timeframe_service() returns Legacy
	 */
	public function test_key_and_flag_but_no_stub_timeframe_returns_legacy(): void {
		Filters\expectApplied( 'postnl_sdk_enable_timeframe' )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertInstanceOf( Legacy_Checkout_Service::class, $factory->timeframe_service() );
	}

	/**
	 * @testdox V4 key + flag but no stub: smart_returns_service() returns Legacy
	 */
	public function test_key_and_flag_but_no_stub_smart_returns_returns_legacy(): void {
		Filters\expectApplied( 'postnl_sdk_enable_smart_returns' )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertInstanceOf( Legacy_Smart_Returns_Service::class, $factory->smart_returns_service() );
	}

	// -------------------------------------------------------------------------
	// Scenario 8 — barcode_from_label(): gates the Order\Base reorder
	// -------------------------------------------------------------------------

	/** @testdox barcode_from_label() is false with no V4 key */
	public function test_barcode_from_label_false_without_key(): void {
		$factory = new Service_Factory( null );
		$this->assertFalse( $factory->barcode_from_label() );
	}

	/** @testdox barcode_from_label() is false with a key but the label flag off */
	public function test_barcode_from_label_false_when_label_flag_off(): void {
		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertFalse( $factory->barcode_from_label() );
	}

	/**
	 * @testdox barcode_from_label() is false with key + label flag on but no V4 label service
	 *
	 * The safety gate: the reorder must stay dormant until a V4 label service exists,
	 * so a Legacy label service is never asked to build a request without a prefetched
	 * barcode.
	 */
	public function test_barcode_from_label_false_without_v4_label_service(): void {
		Filters\expectApplied( 'postnl_sdk_enable_label' )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		$this->assertFalse( $factory->barcode_from_label() );
	}

	/** @testdox barcode_from_label() is true with key + label flag on + a V4 label service */
	public function test_barcode_from_label_true_with_v4_label_service(): void {
		Filters\expectApplied( 'postnl_sdk_enable_label' )->andReturn( true );

		$factory = new Service_Factory( $this->settings_with_key() );
		$factory->inject_v4_service( 'label', $this->make_label_stub() );

		$this->assertTrue( $factory->barcode_from_label() );
	}

	// -------------------------------------------------------------------------
	// Memoisation — shared checkout service instance
	// -------------------------------------------------------------------------

	/**
	 * @testdox timeframe_service() and pickup_location_service() return the same Legacy_Checkout_Service instance
	 */
	public function test_timeframe_and_pickup_location_share_checkout_instance(): void {
		$factory = new Service_Factory( null );

		$timeframe = $factory->timeframe_service();
		$pickup    = $factory->pickup_location_service();

		$this->assertSame( $timeframe, $pickup );
	}

	/**
	 * @testdox Each lazily-built Legacy service getter memoizes — repeated calls return the same instance
	 *
	 * Covers the stateless flows the factory constructs directly. The Order\Base-extending
	 * flows (label/letterbox/return_label) are pre-seeded stubs in unit context (see scenarios
	 * above), so their memoization is exercised there rather than here.
	 */
	public function test_lazy_service_getters_are_memoized(): void {
		$factory = new Service_Factory( null );

		$getters = array(
			'barcode_service',
			'smart_returns_service',
			'postcode_check_service',
			'timeframe_service',
			'pickup_location_service',
		);

		foreach ( $getters as $getter ) {
			$this->assertSame(
				$factory->$getter(),
				$factory->$getter(),
				"{$getter}() must return the same memoized instance on repeated calls."
			);
		}
	}
}
