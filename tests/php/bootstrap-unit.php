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

if ( ! class_exists( 'WC_Settings_API' ) ) {
	/**
	 * Minimal stand-in for the WooCommerce settings API base class.
	 */
	class WC_Settings_API {} // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
