<?php
/**
 * Integration tests for the default of the "Default Shipping Pickup" setting.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Covers the pickup default on a fresh install and on a store that already saved a value.
 */
class DefaultPickupShippingOptionTest extends IntegrationTestCase {

	/**
	 * Setting key of the "Default Shipping Pickup" field.
	 */
	const FIELD = 'default_shipping_options_pickup';

	/**
	 * Backup of the stored plugin settings option, false when it did not exist.
	 *
	 * @var mixed
	 */
	private $orig_option = null;

	/**
	 * Backup of the woocommerce_default_country option.
	 *
	 * @var mixed
	 */
	private $orig_country = null;

	/**
	 * Put the store in NL, the only country the pickup default applies to.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		update_option( 'woocommerce_default_country', 'NL' );

		$this->orig_option = get_option( Settings::get_instance()->get_option_key() );
	}

	/**
	 * Resolve Standard Shipping when the store never saved the plugin settings.
	 *
	 * @testdox A fresh install with no saved value resolves the pickup default to Standard Shipping.
	 */
	public function test_fresh_install_resolves_pickup_default_to_standard_shipping(): void {
		$settings = Settings::get_instance();

		delete_option( $settings->get_option_key() );
		$settings->init_settings();

		$this->assertSame( '', $settings->get_setting_fields()[ self::FIELD ]['default'] );
		$this->assertSame( '', $settings->get_country_option( self::FIELD ) );
		$this->assertArrayNotHasKey( 'id_check', $settings->get_default_shipping_options( 'pickup' ) );
	}

	/**
	 * Preselect Standard Shipping on the settings screen, which reads the field default.
	 *
	 * @testdox A fresh install preselects Standard Shipping on the settings screen.
	 */
	public function test_fresh_install_settings_screen_preselects_standard_shipping(): void {
		delete_option( Settings::get_instance()->get_option_key() );

		$method = new PostNL();
		$html   = $method->generate_settings_html( $method->get_form_fields(), false );

		$this->assertSame( '', $method->get_option( self::FIELD ) );
		$this->assertMatchesRegularExpression( '/<option value=""\s+selected=\'selected\'/', $html );
	}

	/**
	 * Store Standard Shipping when the merchant saves the settings screen as rendered.
	 *
	 * @testdox Saving an untouched settings screen on a fresh install stores Standard Shipping.
	 */
	public function test_fresh_install_untouched_save_stores_standard_shipping(): void {
		$settings = Settings::get_instance();
		delete_option( $settings->get_option_key() );

		$method = new PostNL();
		$key    = $method->get_field_key( self::FIELD );

		$_POST[ $key ] = $method->get_option( self::FIELD );
		$method->process_admin_options();
		unset( $_POST[ $key ] );

		$stored = get_option( $settings->get_option_key() );
		$this->assertSame( '', $stored[ self::FIELD ] );
	}

	/**
	 * Fall back to Standard Shipping when saved settings predate the field.
	 *
	 * @testdox Saved settings without the pickup key resolve to Standard Shipping everywhere.
	 */
	public function test_saved_settings_without_the_key_resolve_to_standard_shipping(): void {
		$settings = Settings::get_instance();

		update_option( $settings->get_option_key(), array( 'enable_logging' => 'no' ) );
		$settings->init_settings();

		$this->assertSame( '', $settings->get_country_option( self::FIELD ) );
		$this->assertSame( '', ( new PostNL() )->get_option( self::FIELD ) );
		$this->assertArrayNotHasKey( self::FIELD, get_option( $settings->get_option_key() ), 'The stored option must not be rewritten' );
	}

	/**
	 * Saved values and the options each must still resolve to.
	 *
	 * @return array
	 */
	public static function saved_value_provider(): array {
		return array(
			'id check'           => array( 'id_check', array( 'id_check' ) ),
			'id check + insured' => array( 'id_check|insured_shipping', array( 'id_check', 'insured_shipping' ) ),
			'insured'            => array( 'insured_shipping', array( 'insured_shipping' ) ),
		);
	}

	/**
	 * Keep the value a store already saved.
	 *
	 * @testdox A store with a saved pickup value keeps it.
	 *
	 * @dataProvider saved_value_provider
	 *
	 * @param string $saved    Value stored in the plugin settings option.
	 * @param array  $expected Options the saved value must resolve to.
	 */
	public function test_saved_pickup_value_is_kept( string $saved, array $expected ): void {
		$settings = Settings::get_instance();

		update_option( $settings->get_option_key(), array( self::FIELD => $saved ) );
		$settings->init_settings();

		$this->assertSame( $saved, $settings->get_country_option( self::FIELD ) );
		$this->assertSame( array_fill_keys( $expected, 'yes' ), $settings->get_default_shipping_options( 'pickup' ) );

		$stored = get_option( $settings->get_option_key() );
		$this->assertSame( $saved, $stored[ self::FIELD ], 'The stored option must not be rewritten' );
		$this->assertSame( $saved, ( new PostNL() )->get_option( self::FIELD ), 'The settings screen must show the saved value' );
	}

	/**
	 * Restore global state mutated by the tests.
	 */
	protected function tearDown(): void {
		$settings = Settings::get_instance();

		if ( false === $this->orig_option ) {
			delete_option( $settings->get_option_key() );
		} elseif ( null !== $this->orig_option ) {
			update_option( $settings->get_option_key(), $this->orig_option );
		}
		$this->orig_option = null;
		$settings->init_settings();

		if ( null !== $this->orig_country ) {
			update_option( 'woocommerce_default_country', $this->orig_country );
			$this->orig_country = null;
		}

		parent::tearDown();
	}
}
