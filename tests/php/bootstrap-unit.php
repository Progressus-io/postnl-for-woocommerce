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
