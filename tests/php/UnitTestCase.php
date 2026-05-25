<?php
/**
 * Base class for unit tests.
 */

namespace PostNLWooCommerce\Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests extend this class.
 *
 * Brain\Monkey is initialised/torn down automatically so each test starts
 * with a clean set of WP function stubs and Mockery expectations.
 */
class UnitTestCase extends TestCase {

	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
