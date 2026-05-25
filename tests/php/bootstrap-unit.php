<?php
/**
 * Bootstrap for unit tests.
 *
 * Defines ABSPATH so plugin files pass the ABSPATH guard, then registers
 * the Composer autoloader. No WordPress or WooCommerce is loaded here —
 * WP functions are stubbed/mocked per-test via Brain\Monkey.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
