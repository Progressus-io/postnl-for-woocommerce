<?php
/**
 * Bootstrap for unit tests.
 *
 * Defines ABSPATH so plugin files pass the ABSPATH guard, provides minimal
 * WordPress filter stubs so classes that call add_filter/apply_filters can be
 * tested without a WordPress install, then loads the Composer autoloader.
 * Brain\Monkey stubs are available per-test via UnitTestCase for deeper mocking.
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/' );
}

// Minimal WordPress filter registry stubs.
$GLOBALS['postnl_wp_filters'] = array();

if ( ! function_exists( 'add_filter' ) ) {
	/**
	 * Register a callback for a WordPress filter hook.
	 *
	 * @param string   $tag           Hook name.
	 * @param callable $callback      Callback to register.
	 * @param int      $priority      Unused; kept for signature compatibility.
	 * @param int      $accepted_args Unused; kept for signature compatibility.
	 */
	function add_filter( $tag, $callback, $priority = 10, $accepted_args = 1 ) {
		$GLOBALS['postnl_wp_filters'][ $tag ][] = $callback;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	/**
	 * Call all callbacks registered for a filter hook and return the result.
	 *
	 * @param string $tag   Hook name.
	 * @param mixed  $value The value to filter.
	 * @param mixed  ...$args Additional arguments passed to each callback.
	 * @return mixed Filtered value.
	 */
	function apply_filters( $tag, $value, ...$args ) {
		foreach ( $GLOBALS['postnl_wp_filters'][ $tag ] ?? array() as $callback ) {
			$value = call_user_func( $callback, $value, ...$args );
		}
		return $value;
	}
}

if ( ! function_exists( '__return_true' ) ) {
	/** WordPress helper — always returns true. */
	function __return_true() {
		return true;
	}
}

if ( ! function_exists( '__return_false' ) ) {
	/** WordPress helper — always returns false. */
	function __return_false() {
		return false;
	}
}

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
