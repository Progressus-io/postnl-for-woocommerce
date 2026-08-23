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

/*
 * Rest_API\V4\Label\Service writes the labelconfirm documents to POSTNL_UPLOADS_DIR
 * before it records them, so exercising store_labels() needs the constant Main.php
 * normally derives from wp_upload_dir(). A temp directory keeps the write real —
 * the service deliberately skips any record whose file is not on disk afterwards,
 * which a stubbed-out writer could not reproduce — while touching nothing outside
 * the system temp area. Tests that use it clean up after themselves.
 */
if ( ! defined( 'POSTNL_UPLOADS_DIR' ) ) {
	define( 'POSTNL_UPLOADS_DIR', sys_get_temp_dir() . '/postnl-unit-uploads/' );
}

if ( ! class_exists( 'WC_Settings_API' ) ) {
	/**
	 * Minimal stand-in for the WooCommerce settings API base class.
	 */
	class WC_Settings_API {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}

if ( ! class_exists( 'WC_Order' ) ) {
	/**
	 * Minimal stand-in for a WooCommerce order: a data bag exposing exactly the
	 * getters the V4 Smart Returns service reads. Getters are explicit so that a
	 * typo'd or newly-added getter fatals in a test instead of quietly returning
	 * a default.
	 */
	class WC_Order { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

		/**
		 * Fixture values keyed by getter suffix.
		 *
		 * @var array<string, string>
		 */
		private $data;

		/**
		 * @param array<string, string> $data Fixture values.
		 */
		public function __construct( array $data = array() ) {
			$this->data = $data;
		}

		/** @return string */
		public function get_shipping_country() {
			return $this->data['shipping_country'] ?? '';
		}

		/** @return string */
		public function get_shipping_first_name() {
			return $this->data['shipping_first_name'] ?? '';
		}

		/** @return string */
		public function get_shipping_last_name() {
			return $this->data['shipping_last_name'] ?? '';
		}

		/** @return string */
		public function get_billing_email() {
			return $this->data['billing_email'] ?? '';
		}

		/** @return string */
		public function get_billing_phone() {
			return $this->data['billing_phone'] ?? '';
		}

		/** @return string */
		public function get_order_number() {
			return $this->data['order_number'] ?? '';
		}
	}
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
