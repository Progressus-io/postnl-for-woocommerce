<?php
/**
 * Bootstrap for integration tests.
 *
 * Must be run inside the wp-env tests-cli container where WordPress is
 * installed at /var/www/html. Override WP_ROOT_FOLDER env var to point
 * to a different WordPress root if needed.
 *
 * The Composer autoloader (including autoload-dev) is loaded by the
 * PostNL plugin when WordPress activates it, so test namespaces are
 * available without an explicit require here.
 */

$wp_root = getenv( 'WP_ROOT_FOLDER' ) ?: '/var/www/html';

if ( ! file_exists( $wp_root . '/wp-load.php' ) ) {
	throw new \RuntimeException(
		"WordPress not found at: {$wp_root}\n" .
		"Run integration tests via: npm run test:php:integration\n" .
		"Or set the WP_ROOT_FOLDER env var to a valid WordPress root."
	);
}

$_SERVER['HTTP_HOST']   = 'localhost';
$_SERVER['REQUEST_URI'] = '/';

require_once $wp_root . '/wp-load.php';
