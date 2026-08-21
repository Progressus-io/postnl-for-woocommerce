<?php
/**
 * Unit tests for the "New API Key" logic on Shipping_Method\Settings: the
 * NewKey header value, the effective-key selection, and the validated-key hash
 * binding that gates the save-time key switch.
 *
 * @package PostNLWooCommerce\Tests\Shipping_Method
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Shipping_Method;

use Brain\Monkey\Functions;
use Mockery;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Shipping_Method\Settings
 */
class SettingsNewApiKeyTest extends UnitTestCase {

	/**
	 * The System Under Test.
	 *
	 * @var Settings&\Mockery\MockInterface
	 */
	private $sut;

	protected function setUp(): void {
		parent::setUp();

		if ( ! defined( 'POSTNL_SETTINGS_ID' ) ) {
			define( 'POSTNL_SETTINGS_ID', 'postnl' );
		}

		// Partial mock so the real decision methods run while the low-level key
		// accessors are stubbed per test.
		$this->sut = Mockery::mock( Settings::class )->makePartial();
	}

	/**
	 * @testdox get_new_key_header_value reports No, Same or Yes from the entered key.
	 * @dataProvider new_key_header_provider
	 */
	public function test_get_new_key_header_value( string $new_key, string $original, string $expected ): void {
		$this->sut->shouldReceive( 'get_api_key_new' )->andReturn( $new_key );
		$this->sut->shouldReceive( 'get_api_key' )->andReturn( $original );

		$this->assertSame(
			$expected,
			$this->sut->get_new_key_header_value(),
			"Expected '{$expected}' for new='{$new_key}', original='{$original}'."
		);
	}

	/**
	 * @return array<string, array{string, string, string}>
	 */
	public static function new_key_header_provider(): array {
		return array(
			'empty new key'           => array( '', 'ORIGINAL', 'No' ),
			'new key equals original' => array( 'SAMEKEY', 'SAMEKEY', 'Same' ),
			'distinct new key'        => array( 'NEWKEY', 'ORIGINAL', 'Yes' ),
		);
	}

	/**
	 * Pins the deliberate behaviour (still unconfirmed with PostNL) that a
	 * distinct key reports "Yes" for adoption tracking before the save-time
	 * validation has run. If product confirms "Yes" must mean "validated", this
	 * is the expectation to flip.
	 *
	 * @testdox A distinct new key reports Yes even when it has not been validated.
	 */
	public function test_distinct_new_key_reports_yes_without_validation(): void {
		$this->sut->shouldReceive( 'get_api_key_new' )->andReturn( 'NEWKEY' );
		$this->sut->shouldReceive( 'get_api_key' )->andReturn( 'ORIGINAL' );

		$this->assertSame( 'Yes', $this->sut->get_new_key_header_value() );
	}

	/**
	 * @testdox get_effective_api_key returns the new key only when it is distinct and validated.
	 * @dataProvider effective_key_provider
	 */
	public function test_get_effective_api_key( string $original, string $new_key, bool $validated, string $expected ): void {
		$this->sut->shouldReceive( 'get_api_key' )->andReturn( $original );
		$this->sut->shouldReceive( 'get_api_key_new' )->andReturn( $new_key );
		$this->sut->shouldReceive( 'is_api_key_new_validated' )->andReturn( $validated );

		$this->assertSame(
			$expected,
			$this->sut->get_effective_api_key(),
			"Expected '{$expected}' (new='{$new_key}', validated=" . ( $validated ? 'true' : 'false' ) . ').'
		);
	}

	/**
	 * @return array<string, array{string, string, bool, string}>
	 */
	public static function effective_key_provider(): array {
		return array(
			'empty new key falls back to original'       => array( 'ORIGINAL', '', false, 'ORIGINAL' ),
			'new key identical to original'              => array( 'ORIGINAL', 'ORIGINAL', false, 'ORIGINAL' ),
			'distinct but unvalidated keeps original'    => array( 'ORIGINAL', 'NEWKEY', false, 'ORIGINAL' ),
			'distinct and validated switches to new key' => array( 'ORIGINAL', 'NEWKEY', true, 'NEWKEY' ),
		);
	}

	/**
	 * @testdox is_api_key_new_validated_value returns false for an empty key.
	 */
	public function test_validated_value_false_for_empty_key(): void {
		$this->assertFalse( $this->sut->is_api_key_new_validated_value( '' ) );
	}

	/**
	 * @testdox is_api_key_new_validated_value returns false when no hash is stored.
	 */
	public function test_validated_value_false_when_no_hash_stored(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$this->assertFalse( $this->sut->is_api_key_new_validated_value( 'NEWKEY' ) );
	}

	/**
	 * @testdox is_api_key_new_validated_value validates only the exact key the stored hash was bound to.
	 */
	public function test_validated_value_matches_only_the_hashed_key(): void {
		Functions\when( 'get_option' )->justReturn( hash( 'sha256', 'NEWKEY' ) );

		$this->assertTrue(
			$this->sut->is_api_key_new_validated_value( 'NEWKEY' ),
			'The key whose hash is stored must validate.'
		);
		$this->assertFalse(
			$this->sut->is_api_key_new_validated_value( 'DIFFERENT' ),
			'A different key must not validate against the stored hash.'
		);
	}

	/**
	 * Guards the stale-read fix: the validated state must be bound to the exact
	 * key value passed in, not re-read from the settings object's cache.
	 *
	 * @testdox set_api_key_new_validated stores the SHA-256 of the explicit key value.
	 */
	public function test_set_validated_hashes_the_explicit_key(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION, hash( 'sha256', 'NEWKEY' ) )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( true, 'NEWKEY' );
	}

	/**
	 * @testdox set_api_key_new_validated clears the stored hash when validation fails.
	 */
	public function test_set_validated_false_deletes_the_hash(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( false );
	}

	/**
	 * @testdox set_api_key_new_validated clears the hash when asked to validate an empty key.
	 */
	public function test_set_validated_true_with_empty_key_deletes_the_hash(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( true, '' );
	}
}
