<?php
/**
 * Unit tests for the "New API Key" logic on Shipping_Method\Settings: the
 * NewKey header value, the effective-key selection, the environment-aware
 * new-key resolution, and the validated-key hash binding that gates the
 * save-time key switch.
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

		// The status-copy methods run for real, so give them return-first-arg
		// stand-ins for the escaping and translation helpers.
		Functions\stubs( array( '__', 'esc_html', 'esc_html__', 'esc_url', 'esc_attr' ) );

		// Partial mock so the real decision methods run while the low-level key
		// accessors are stubbed per test.
		$this->sut = Mockery::mock( Settings::class )->makePartial();
	}

	/**
	 * @testdox get_new_key_header_value reports No, Same, Entered or Yes from the entered, distinct and validated state.
	 * @dataProvider new_key_header_provider
	 */
	public function test_get_new_key_header_value( string $new_key, string $original, bool $validated, string $expected ): void {
		$this->sut->shouldReceive( 'get_api_key_new' )->andReturn( $new_key );
		$this->sut->shouldReceive( 'get_original_api_key' )->andReturn( $original );
		$this->sut->shouldReceive( 'is_api_key_new_validated' )->andReturn( $validated );

		$this->assertSame(
			$expected,
			$this->sut->get_new_key_header_value(),
			"Expected '{$expected}' for new='{$new_key}', original='{$original}', validated=" . ( $validated ? 'true' : 'false' ) . '.'
		);
	}

	/**
	 * @return array<string, array{string, string, bool, string}>
	 */
	public static function new_key_header_provider(): array {
		return array(
			'empty new key'                     => array( '', 'ORIGINAL', false, 'No' ),
			'new key equals original'           => array( 'SAMEKEY', 'SAMEKEY', false, 'Same' ),
			'distinct but unvalidated'          => array( 'NEWKEY', 'ORIGINAL', false, 'Entered' ),
			'distinct and validated'            => array( 'NEWKEY', 'ORIGINAL', true, 'Yes' ),
			// With no old key stored, a key can never resolve to "Same" — the case
			// Dustin asked to pin, since "Same" implies comparing against an old key.
			'distinct with no old key'          => array( 'NEWKEY', '', false, 'Entered' ),
			'validated with no old key'         => array( 'NEWKEY', '', true, 'Yes' ),
			'empty with no old key'             => array( '', '', false, 'No' ),
		);
	}

	/**
	 * "Yes" means the key works, so an entered-but-unvalidated key must not report
	 * adoption to PostNL — it reports "Entered" instead, so a merchant whose key
	 * failed our validation is not counted as migrated.
	 *
	 * @testdox A distinct new key reports Entered until it has been validated.
	 */
	public function test_distinct_new_key_reports_entered_without_validation(): void {
		$this->sut->shouldReceive( 'get_api_key_new' )->andReturn( 'NEWKEY' );
		$this->sut->shouldReceive( 'get_original_api_key' )->andReturn( 'ORIGINAL' );
		$this->sut->shouldReceive( 'is_api_key_new_validated' )->andReturn( false );

		$this->assertSame( 'Entered', $this->sut->get_new_key_header_value() );
	}

	/**
	 * @testdox get_api_key_new returns the environment-appropriate new-key field.
	 */
	public function test_get_api_key_new_is_environment_aware(): void {
		$sandbox = Mockery::mock( Settings::class )->makePartial();
		$sandbox->shouldReceive( 'is_sandbox' )->andReturn( true );
		$sandbox->shouldReceive( 'get_api_key_sandbox_new' )->andReturn( 'SANDBOX_NEW' );
		$this->assertSame( 'SANDBOX_NEW', $sandbox->get_api_key_new() );

		$production = Mockery::mock( Settings::class )->makePartial();
		$production->shouldReceive( 'is_sandbox' )->andReturn( false );
		$production->shouldReceive( 'get_country_option' )->with( 'api_keys_new', '' )->andReturn( 'PROD_NEW' );
		$this->assertSame( 'PROD_NEW', $production->get_api_key_new() );
	}

	/**
	 * @testdox get_effective_api_key returns the new key only when it is distinct and validated.
	 * @dataProvider effective_key_provider
	 */
	public function test_get_effective_api_key( string $original, string $new_key, bool $validated, string $expected ): void {
		$this->sut->shouldReceive( 'get_original_api_key' )->andReturn( $original );
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
		$this->assertFalse( $this->sut->is_api_key_new_validated_value( '', false ) );
	}

	/**
	 * @testdox is_api_key_new_validated_value returns false when no hash is stored.
	 */
	public function test_validated_value_false_when_no_hash_stored(): void {
		Functions\when( 'get_option' )->justReturn( '' );

		$this->assertFalse( $this->sut->is_api_key_new_validated_value( 'NEWKEY', false ) );
	}

	/**
	 * @testdox is_api_key_new_validated_value validates only the exact key the stored hash was bound to.
	 */
	public function test_validated_value_matches_only_the_hashed_key(): void {
		Functions\when( 'get_option' )->justReturn( hash( 'sha256', 'NEWKEY' ) );

		$this->assertTrue(
			$this->sut->is_api_key_new_validated_value( 'NEWKEY', false ),
			'The key whose hash is stored must validate.'
		);
		$this->assertFalse(
			$this->sut->is_api_key_new_validated_value( 'DIFFERENT', false ),
			'A different key must not validate against the stored hash.'
		);
	}

	/**
	 * Guards item 5 of the review: the validated flag is environment-scoped, so a
	 * key validated in production is not treated as validated in sandbox.
	 *
	 * @testdox The validated hash is stored per environment.
	 */
	public function test_validated_flag_is_environment_scoped(): void {
		Functions\when( 'get_option' )->alias(
			static function ( $name, $default = '' ) {
				return Settings::NEW_API_KEY_VALIDATED_HASH_OPTION === $name
					? hash( 'sha256', 'PRODKEY' )
					: $default;
			}
		);

		$this->assertTrue(
			$this->sut->is_api_key_new_validated_value( 'PRODKEY', false ),
			'The production key validates against the production hash.'
		);
		$this->assertFalse(
			$this->sut->is_api_key_new_validated_value( 'PRODKEY', true ),
			'The production key must not validate against the (empty) sandbox hash.'
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

		$this->sut->set_api_key_new_validated( true, 'NEWKEY', false );
	}

	/**
	 * @testdox set_api_key_new_validated stores the sandbox key under the sandbox-scoped option.
	 */
	public function test_set_validated_uses_sandbox_option_in_sandbox(): void {
		Functions\expect( 'update_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION . '_sandbox', hash( 'sha256', 'SANDBOXKEY' ) )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( true, 'SANDBOXKEY', true );
	}

	/**
	 * @testdox set_api_key_new_validated clears the stored hash when validation fails.
	 */
	public function test_set_validated_false_deletes_the_hash(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( false, null, false );
	}

	/**
	 * @testdox set_api_key_new_validated clears the hash when asked to validate an empty key.
	 */
	public function test_set_validated_true_with_empty_key_deletes_the_hash(): void {
		Functions\expect( 'delete_option' )
			->once()
			->with( Settings::NEW_API_KEY_VALIDATED_HASH_OPTION )
			->andReturn( true );

		$this->sut->set_api_key_new_validated( true, '', false );
	}

	/**
	 * @testdox build_new_key_status shows green Valid for a saved, validated key.
	 */
	public function test_build_status_saved_validated_is_green_valid(): void {
		$status = $this->sut->build_new_key_status( 'NEWKEY', 'OLDKEY', true, false, true, '' );

		$this->assertSame( 'Yes', $status['header'] );
		$this->assertSame( '#008a20', $status['color'] );
		$this->assertStringContainsStringIgnoringCase( 'working', $status['summary'] );
	}

	/**
	 * The amber "works, not saved yet" state is the new state item 2 asked for: a
	 * key that checks out on blur but has not been saved must not read as green.
	 *
	 * @testdox build_new_key_status shows amber Works-not-saved for a validated but unsaved key.
	 */
	public function test_build_status_validated_but_unsaved_is_amber(): void {
		$status = $this->sut->build_new_key_status( 'NEWKEY', 'OLDKEY', true, false, false, '' );

		$this->assertSame( '#dba617', $status['color'] );
		$this->assertStringContainsStringIgnoringCase( 'not saved', $status['label'] );
	}

	/**
	 * @testdox build_new_key_status shows the Same-as-old amber state.
	 */
	public function test_build_status_same_as_old(): void {
		$status = $this->sut->build_new_key_status( 'SAMEKEY', 'SAMEKEY', false, false, true, '' );

		$this->assertSame( 'Same', $status['header'] );
		$this->assertStringContainsStringIgnoringCase( 'already had', $status['summary'] );
	}

	/**
	 * Item 4: on a fresh install nothing is in use, so the "Not set" copy must not
	 * imply an old key is carrying the plugin.
	 *
	 * @testdox build_new_key_status "Not set" copy does not assume an old key on a fresh install.
	 */
	public function test_build_status_not_set_without_old_key(): void {
		$status = $this->sut->build_new_key_status( '', '', false, false, true, '' );

		$this->assertSame( 'No', $status['header'] );
		$this->assertStringNotContainsStringIgnoringCase( 'old key', $status['description'] );
		$this->assertStringNotContainsStringIgnoringCase( 'switched on', $status['description'] );
		$this->assertStringContainsStringIgnoringCase( 'connect', $status['description'] );
	}

	/**
	 * @testdox build_new_key_status "Not set" copy nudges an existing merchant to switch keys.
	 */
	public function test_build_status_not_set_with_old_key(): void {
		$status = $this->sut->build_new_key_status( '', 'OLDKEY', false, false, true, '' );

		$this->assertSame( 'No', $status['header'] );
		$this->assertStringContainsStringIgnoringCase( 'old key', $status['description'] );
	}

	/**
	 * @testdox build_new_key_status invalid copy names the old key when one is in use.
	 */
	public function test_build_status_invalid_with_old_key(): void {
		$status = $this->sut->build_new_key_status( 'NEWKEY', 'OLDKEY', false, false, true, 'invalid' );

		$this->assertSame( 'Entered', $status['header'] );
		$this->assertStringContainsStringIgnoringCase( 'rejected', $status['summary'] );
		$this->assertStringContainsStringIgnoringCase( 'still using your old key', $status['summary'] );
	}

	/**
	 * Item 4: the same rejection with no old key must admit nothing is connected,
	 * rather than claiming an old key is still in use.
	 *
	 * @testdox build_new_key_status invalid copy admits no working key on a fresh install.
	 */
	public function test_build_status_invalid_without_old_key(): void {
		$status = $this->sut->build_new_key_status( 'NEWKEY', '', false, false, true, 'invalid' );

		$this->assertStringContainsStringIgnoringCase( 'no working key', $status['summary'] );
		$this->assertStringNotContainsStringIgnoringCase( 'old key', $status['summary'] );
	}

	/**
	 * Item 3: an outage must read as "could not reach", not "invalid", so the
	 * entered-key copy varies by reason.
	 *
	 * @testdox build_new_key_status entered copy varies by failure reason.
	 * @dataProvider entered_reason_provider
	 *
	 * @param string $reason   Persisted failure reason.
	 * @param string $needle   Text the summary must contain.
	 */
	public function test_build_status_entered_copy_by_reason( string $reason, string $needle ): void {
		$status = $this->sut->build_new_key_status( 'NEWKEY', 'OLDKEY', false, false, true, $reason );

		$this->assertSame( 'Entered', $status['header'] );
		$this->assertStringContainsStringIgnoringCase( $needle, $status['summary'] );
	}

	/**
	 * @return array<string, array{string, string}>
	 */
	public static function entered_reason_provider(): array {
		return array(
			'invalid key rejected'      => array( 'invalid', 'rejected' ),
			'unreachable host'          => array( 'unreachable', 'could not reach' ),
			'missing customer details'  => array( 'missing', 'not checked' ),
			'rejected on customer data' => array( 'rejected', 'could not process' ),
		);
	}
}
