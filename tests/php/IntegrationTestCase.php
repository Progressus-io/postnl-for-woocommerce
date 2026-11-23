<?php
/**
 * Base class for integration tests.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration tests extend this class.
 *
 * WordPress and WooCommerce are already loaded by the bootstrap before
 * PHPUnit runs the test suite, so no additional setup is needed here.
 * Add shared helpers for database reset, fixture loading, etc. as the
 * suite grows.
 */
class IntegrationTestCase extends TestCase {

	/**
	 * Delete the delivery options failure log throttle, which outlives a test because the database is not reset.
	 */
	protected function clear_lookup_failure_throttle(): void {
		global $wpdb;

		$names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_postnl_lookup_failed_' ) . '%'
			)
		);

		foreach ( $names as $name ) {
			delete_transient( substr( $name, strlen( '_transient_' ) ) );
		}
	}
}
