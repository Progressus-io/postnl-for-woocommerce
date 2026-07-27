<?php
/**
 * Integration smoke test — verifies WordPress, WooCommerce, and the
 * PostNL plugin are all reachable after the bootstrap loads wp-load.php.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Utils;

/**
 * Confirms the integration bootstrap delivers a fully wired WP + WC environment.
 */
class PluginBootstrapTest extends IntegrationTestCase {

	/**
	 * @testdox Should expose WordPress core functions inside the integration context.
	 */
	public function test_wordpress_core_functions_available(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'get_option' ) );
	}

	/**
	 * @testdox Should have the WooCommerce class loaded.
	 */
	public function test_woocommerce_class_exists(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
	}

	/**
	 * @testdox Should have run the PostNL plugin's bootstrap so POSTNL_WC_VERSION is defined.
	 */
	public function test_postnl_plugin_is_bootstrapped(): void {
		$this->assertTrue( defined( 'POSTNL_WC_VERSION' ) );
	}

	/**
	 * @testdox Should let PostNL Utils helpers be called from the integration context.
	 */
	public function test_postnl_utils_callable_from_integration_context(): void {
		$this->assertSame( array( 'NL', 'BE' ), Utils::get_available_country() );
		$this->assertSame( array( 'NL' ), Utils::get_available_country_for_letterbox() );
	}
}
