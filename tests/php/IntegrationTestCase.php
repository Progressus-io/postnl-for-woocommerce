<?php
/**
 * Base class for integration tests.
 */

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
class IntegrationTestCase extends TestCase {}
