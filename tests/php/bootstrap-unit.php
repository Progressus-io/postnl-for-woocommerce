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
