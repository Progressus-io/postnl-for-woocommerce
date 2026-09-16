<?php
/**
 * Integration tests for the new API key settings behaviour that a unit test
 * cannot reach:
 *
 *  - Item 1: after a save, the settings the screen reads must reflect what was
 *    just written. A unit test on the status mapping passes while the screen is
 *    still wrong, because the bug is the Settings singleton keeping its pre-save
 *    cache; only a real save through process_admin_options() exercises it.
 *  - The legacy "Old API Key" field is shown only when a key is already stored.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Guards the post-save singleton refresh and the legacy-key field gating.
 */
class NewApiKeySettingsTest extends IntegrationTestCase {

	/**
	 * Backups restored on teardown.
	 *
	 * @var mixed
	 */
	private $orig_country;

	/**
	 * @var mixed
	 */
	private $orig_option;

	/**
	 * @var array<string, mixed>|null
	 */
	private $orig_settings = null;

	protected function setUp(): void {
		parent::setUp();

		$this->orig_country  = get_option( 'woocommerce_default_country' );
		$this->orig_option   = get_option( 'woocommerce_postnl_settings' );
		$this->orig_settings = Settings::get_instance()->settings;

		update_option( 'woocommerce_default_country', 'NL' );
	}

	protected function tearDown(): void {
		unset(
			$_POST['woocommerce_postnl_environment_mode'],
			$_POST['woocommerce_postnl_api_keys'],
			$_POST['woocommerce_postnl_api_keys_new']
		);

		if ( false === $this->orig_option ) {
			delete_option( 'woocommerce_postnl_settings' );
		} else {
			update_option( 'woocommerce_postnl_settings', $this->orig_option );
		}

		update_option( 'woocommerce_default_country', $this->orig_country );

		if ( null !== $this->orig_settings ) {
			Settings::get_instance()->settings = $this->orig_settings;
		}

		parent::tearDown();
	}

	/**
	 * Item 1: the value read back after a save is the saved one, not the value the
	 * singleton cached before the save.
	 *
	 * @testdox process_admin_options refreshes the Settings singleton so the post-save screen is current.
	 */
	public function test_process_admin_options_refreshes_the_settings_singleton(): void {
		// Start from a clean stored state and prime the singleton so it holds the
		// pre-save (empty) new-key value in memory.
		update_option( 'woocommerce_postnl_settings', array() );
		$singleton = Settings::get_instance();
		$singleton->init_settings();

		$this->assertSame(
			'',
			$singleton->get_country_option( 'api_keys_new', '' ),
			'Precondition: the primed singleton holds no new key yet.'
		);

		// Simulate the admin saving the form. The new key equals the old key so the
		// save-time validation short-circuits before any live PostNL call.
		$_POST['woocommerce_postnl_environment_mode'] = 'production';
		$_POST['woocommerce_postnl_api_keys']         = 'PRIMEKEY-123';
		$_POST['woocommerce_postnl_api_keys_new']     = 'PRIMEKEY-123';

		( new PostNL() )->process_admin_options();

		$this->assertSame(
			'PRIMEKEY-123',
			Settings::get_instance()->get_country_option( 'api_keys_new', '' ),
			'After the save the singleton must read the value just written, not the stale primed one.'
		);
	}

	/**
	 * The legacy "Old API Key" field is unset until a key is stored, so a fresh
	 * install shows only the new "API Key" field.
	 *
	 * @testdox The legacy Old API Key field is gated on a stored value.
	 */
	public function test_legacy_api_key_field_is_gated_on_a_stored_value(): void {
		$settings = Settings::get_instance();

		$settings->settings              = is_array( $settings->settings ) ? $settings->settings : array();
		$settings->settings['api_keys']  = '';
		$fields                          = $settings->get_setting_fields();

		$this->assertArrayNotHasKey( 'api_keys', $fields, 'The Old API Key field is hidden when none is stored.' );
		$this->assertArrayHasKey( 'api_keys_new', $fields, 'The new API Key field is always shown.' );

		$settings->settings['api_keys'] = 'OLDKEY-123';
		$fields                         = $settings->get_setting_fields();

		$this->assertArrayHasKey( 'api_keys', $fields, 'The Old API Key field appears once a key is stored.' );
	}
}
