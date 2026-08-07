<?php
/**
 * Bootstrap for unit tests.
 *
 * Defines ABSPATH so plugin files pass the ABSPATH guard, then loads the
 * Composer autoloader. WordPress function stubs (add_filter, apply_filters,
 * __return_true, __return_false, etc.) are provided per-test by Brain\Monkey
 * via UnitTestCase — no global stubs needed here.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
}

/*
 * Shipping_Method\Settings evaluates `public $id = POSTNL_SETTINGS_ID;` at
 * class-definition time and extends \WC_Settings_API, so simply autoloading it
 * needs both. Main.php defines the constant as its $settings_id ('postnl');
 * WooCommerce is not loaded in unit tests, so the parent class is stubbed here.
 *
 * The stub is deliberately empty: any test double that extends Settings and
 * reaches a real parent method (get_option(), get_country_option(), …) fatals
 * loudly instead of silently returning stub data. That is the point — a double
 * must override every getter the code under test reads.
 */
if ( ! defined( 'POSTNL_SETTINGS_ID' ) ) {
	define( 'POSTNL_SETTINGS_ID', 'postnl' );
}

/*
 * Order\Base evaluates `protected $service = POSTNL_SERVICE_NAME;` at class-definition
 * time, so any test that instantiates an Order\Base subclass (e.g. the V4 label service
 * built by Service_Factory) needs the constant. Main.php defines it as its $service_name.
 */
if ( ! defined( 'POSTNL_SERVICE_NAME' ) ) {
	define( 'POSTNL_SERVICE_NAME', 'PostNL' );
}

if ( ! class_exists( 'WC_Settings_API' ) ) {
	/**
	 * Minimal stand-in for the WooCommerce settings API base class.
	 */
	class WC_Settings_API {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

/**
 * Minimal stand-in for the WooCommerce settings base class so plugin classes
 * that extend it (e.g. Shipping_Method\Settings) can be loaded under unit
 * tests. Only the surface the unit suite exercises is implemented: the in-memory
 * $settings store and the get_option() accessor that reads from it.
 */
if ( ! class_exists( 'WC_Settings_API' ) ) {
	class WC_Settings_API {

		/**
		 * In-memory settings values.
		 *
		 * @var array<string, mixed>
		 */
		public $settings = array();

		/**
		 * Read a stored setting, falling back to a default.
		 *
		 * @param string $key         Setting key.
		 * @param mixed  $empty_value Value returned when the key is unset.
		 * @return mixed
		 */
		public function get_option( $key, $empty_value = null ) {
			return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $empty_value;
		}
	}
}
