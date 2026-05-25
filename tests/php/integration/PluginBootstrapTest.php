<?php
/**
 * Integration smoke test — verifies WordPress, WooCommerce, and the
 * PostNL plugin are all reachable after the bootstrap loads wp-load.php.
 */

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Utils;

class PluginBootstrapTest extends IntegrationTestCase {

	public function test_wordpress_core_functions_available(): void {
		$this->assertTrue( function_exists( 'add_action' ) );
		$this->assertTrue( function_exists( 'get_option' ) );
	}

	public function test_woocommerce_class_exists(): void {
		$this->assertTrue( class_exists( 'WooCommerce' ) );
	}

	public function test_postnl_utils_callable_from_integration_context(): void {
		$this->assertSame( array( 'NL', 'BE' ), Utils::get_available_country() );
		$this->assertSame( array( 'NL' ), Utils::get_available_country_for_letterbox() );
	}
}
