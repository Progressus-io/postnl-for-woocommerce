<?php
/**
 * Unit tests for Rest_API\SDK\Logger_Adapter.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\SDK
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\SDK;

use Brain\Monkey\Functions;
use Mockery;
use PostNLWooCommerce\Logger;
use PostNLWooCommerce\Rest_API\SDK\Logger_Adapter;
use PostNLWooCommerce\Tests\UnitTestCase;
use Psr\Log\LogLevel;

/**
 * @covers \PostNLWooCommerce\Rest_API\SDK\Logger_Adapter
 */
class Logger_AdapterTest extends UnitTestCase {

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Stub wc_get_logger() to return a Mockery WC logger spy.
	 *
	 * @return Mockery\MockInterface The fake WC logger, so tests can set
	 *                               expectations on its log() method.
	 */
	private function fake_wc_logger(): Mockery\MockInterface {
		$wc_logger = Mockery::mock();
		Functions\when( 'wc_get_logger' )->justReturn( $wc_logger );
		return $wc_logger;
	}

	/**
	 * Build an adapter whose enable-logging gate is on or off.
	 *
	 * @param bool $enabled Whether the plugin logger reports logging enabled.
	 * @return Logger_Adapter
	 */
	private function make_adapter( bool $enabled = true ): Logger_Adapter {
		return new Logger_Adapter( new Logger( $enabled ) );
	}

	// ── Tests ────────────────────────────────────────────────────────────────

	/**
	 * @testdox A logged message reaches the WC logger tagged [postnl-v4] on the PostNL source
	 */
	public function test_message_is_tagged_and_routed_to_wc_logger(): void {
		$wc_logger = $this->fake_wc_logger();
		$wc_logger->shouldReceive( 'log' )
			->once()
			->with( 'error', '[postnl-v4] boom', array( 'source' => 'PostNLWooCommerce' ) );

		$this->make_adapter()->error( 'boom' );
	}

	/**
	 * Every PSR-3 level is passed through to WC unchanged (PSR-3 levels are
	 * identical to WC_Log_Levels).
	 *
	 * @dataProvider psr3_levels_provider
	 * @testdox PSR-3 level is preserved when forwarded to the WC logger
	 *
	 * @param string $level PSR-3 level under test.
	 */
	public function test_each_psr3_level_is_preserved( string $level ): void {
		$wc_logger = $this->fake_wc_logger();
		$wc_logger->shouldReceive( 'log' )
			->once()
			->with( $level, '[postnl-v4] msg', array( 'source' => 'PostNLWooCommerce' ) );

		$this->make_adapter()->log( $level, 'msg' );
	}

	/**
	 * Yields one case per valid PSR-3 level.
	 *
	 * @return array<string, array{string}>
	 */
	public static function psr3_levels_provider(): array {
		return array(
			'emergency' => array( LogLevel::EMERGENCY ),
			'alert'     => array( LogLevel::ALERT ),
			'critical'  => array( LogLevel::CRITICAL ),
			'error'     => array( LogLevel::ERROR ),
			'warning'   => array( LogLevel::WARNING ),
			'notice'    => array( LogLevel::NOTICE ),
			'info'      => array( LogLevel::INFO ),
			'debug'     => array( LogLevel::DEBUG ),
		);
	}

	/**
	 * @testdox {placeholder} tokens are interpolated from context per the PSR-3 spec
	 */
	public function test_context_placeholders_are_interpolated(): void {
		$wc_logger = $this->fake_wc_logger();
		$wc_logger->shouldReceive( 'log' )
			->once()
			->with( 'info', '[postnl-v4] Label 3SABC created for order 42', array( 'source' => 'PostNLWooCommerce' ) );

		$this->make_adapter()->info(
			'Label {barcode} created for order {order_id}',
			array(
				'barcode'  => '3SABC',
				'order_id' => 42,
			)
		);
	}

	/**
	 * @testdox Context values that cannot be stringified leave their placeholder intact
	 */
	public function test_non_stringable_context_leaves_placeholder_intact(): void {
		$wc_logger = $this->fake_wc_logger();
		$wc_logger->shouldReceive( 'log' )
			->once()
			->with( 'debug', '[postnl-v4] payload {data}', array( 'source' => 'PostNLWooCommerce' ) );

		$this->make_adapter()->debug( 'payload {data}', array( 'data' => array( 'nested' => true ) ) );
	}

	/**
	 * @testdox An unrecognised level falls back to notice so WC_Logger accepts it
	 */
	public function test_unknown_level_falls_back_to_notice(): void {
		$wc_logger = $this->fake_wc_logger();
		$wc_logger->shouldReceive( 'log' )
			->once()
			->with( 'notice', '[postnl-v4] mystery', array( 'source' => 'PostNLWooCommerce' ) );

		$this->make_adapter()->log( 'not-a-level', 'mystery' );
	}

	/**
	 * @testdox Nothing is written when the plugin logging toggle is off
	 */
	public function test_disabled_logger_writes_nothing(): void {
		Functions\expect( 'wc_get_logger' )->never();

		$this->make_adapter( false )->error( 'should not be written' );
	}
}
