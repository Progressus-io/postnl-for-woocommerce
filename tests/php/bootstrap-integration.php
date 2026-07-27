<?php
/**
 * Bootstrap for integration tests.
 *
 * Must be run inside the wp-env tests-cli container where WordPress is
 * installed at /var/www/html. Override WP_ROOT_FOLDER env var to point
 * to a different WordPress root if needed.
 *
 * The plugin loads `vendor/autoload.php` from its own bootstrap. Test
 * namespaces from `autoload-dev` resolve through that same autoloader,
 * but only because `composer install` ran without `--no-dev` before
 * wp-env started — CI and the `build:dev` script both do this.
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
