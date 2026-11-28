<?php
/**
 * Integration tests for the country option lookup cache and the one-time
 * registration of the PostNL shipping method hooks.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Guards that the cache and the hook guard change no setting value or field.
 */
class SettingsCacheHooksTest extends IntegrationTestCase {

	const FIELDS_HOOK = 'woocommerce_shipping_instance_form_fields_postnl';

	const SAVE_HOOK = 'woocommerce_update_options_shipping_postnl';

	/**
	 * Store country before the test.
	 *
	 * @var mixed
	 */
	private $orig_country;

	/**
	 * Stored plugin settings before the test.
	 *
	 * @var mixed
	 */
	private $orig_option;

	/**
	 * Number of times the settings field list was built.
	 *
	 * @var int
	 */
	private $builds = 0;

	/**
	 * Back up the options the tests overwrite and start counting field list builds.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->orig_country = get_option( 'woocommerce_default_country' );
		$this->orig_option  = get_option( 'woocommerce_postnl_settings' );

		add_filter( 'gettext', array( $this, 'count_field_list_builds' ), 10, 3 );
	}

	/**
	 * Restore the options and the settings singleton.
	 */
	protected function tearDown(): void {
		remove_filter( 'gettext', array( $this, 'count_field_list_builds' ), 10 );
		unset(
			$_POST['woocommerce_postnl_environment_mode'],
			$_POST['woocommerce_postnl_api_keys'],
			$_POST['woocommerce_postnl_api_keys_new'],
			$_POST['woocommerce_postnl_customer_num']
		);

		if ( false === $this->orig_option ) {
			delete_option( 'woocommerce_postnl_settings' );
		} else {
			update_option( 'woocommerce_postnl_settings', $this->orig_option );
		}

		update_option( 'woocommerce_default_country', $this->orig_country );
		Settings::get_instance()->init_settings();

		parent::tearDown();
	}

	/**
	 * Count one build each time the first field title of the list is translated.
	 *
	 * @param string $translation Translated text.
	 * @param string $text        Text to translate.
	 * @param string $domain      Text domain.
	 *
	 * @return string
	 */
	public function count_field_list_builds( $translation, $text, $domain ) {
		if ( 'Manual' === $text && 'postnl-for-woocommerce' === $domain ) {
			++$this->builds;
		}

		return $translation;
	}

	/**
	 * Get the PostNL objects that have a callback on a hook.
	 *
	 * @param string $hook Hook name.
	 *
	 * @return PostNL[]
	 */
	private function postnl_callbacks( string $hook ): array {
		global $wp_filter;

		$owners = array();

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return $owners;
		}

		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				if ( is_array( $callback['function'] ) && $callback['function'][0] instanceof PostNL ) {
					$owners[] = $callback['function'][0];
				}
			}
		}

		return $owners;
	}

	/**
	 * Remove every PostNL callback and forget which objects registered them.
	 */
	private function reset_hooks(): void {
		$methods = array(
			self::FIELDS_HOOK       => 'instance_form_fields',
			self::SAVE_HOOK         => 'process_admin_options',
			'admin_enqueue_scripts' => 'enqueue_shipping_method_assets',
		);

		foreach ( $methods as $hook => $method ) {
			foreach ( $this->postnl_callbacks( $hook ) as $owner ) {
				remove_filter( $hook, array( $owner, $method ), 10 );
			}
		}

		foreach ( array( 'shared_hooks_owner', 'save_hook_owner' ) as $name ) {
			$property = new \ReflectionProperty( PostNL::class, $name );
			$property->setAccessible( true );
			$property->setValue( null, null );
		}
	}

	/**
	 * Store the plugin settings and reload the singleton from them.
	 *
	 * @param string $country  Store country.
	 * @param array  $settings Plugin settings to store.
	 *
	 * @return Settings
	 */
	private function store_settings( string $country, array $settings ): Settings {
		update_option( 'woocommerce_default_country', $country );
		update_option( 'woocommerce_postnl_settings', $settings );

		$singleton = Settings::get_instance();
		$singleton->init_settings();

		return $singleton;
	}

	/**
	 * @testdox Each hook has one PostNL callback after several shipping method objects are created.
	 */
	public function test_hooks_are_registered_once(): void {
		$this->reset_hooks();

		new PostNL();
		foreach ( array( 1, 2, 3, 4, 5 ) as $instance_id ) {
			new PostNL( $instance_id );
		}
		new PostNL();

		$this->assertCount( 1, $this->postnl_callbacks( self::FIELDS_HOOK ) );
		$this->assertCount( 1, $this->postnl_callbacks( self::SAVE_HOOK ) );
		$this->assertCount( 1, $this->postnl_callbacks( 'admin_enqueue_scripts' ) );
	}

	/**
	 * @testdox The save action belongs to the settings level object when a zone instance is created first.
	 */
	public function test_save_action_is_registered_by_the_settings_level_object(): void {
		$this->reset_hooks();

		new PostNL( 3 );

		$this->assertCount( 1, $this->postnl_callbacks( self::FIELDS_HOOK ) );
		$this->assertCount( 0, $this->postnl_callbacks( self::SAVE_HOOK ) );

		new PostNL();
		new PostNL( 4 );

		$owners = $this->postnl_callbacks( self::SAVE_HOOK );

		$this->assertCount( 1, $owners );
		$this->assertSame( 0, $owners[0]->instance_id );
		$this->assertCount( 1, $this->postnl_callbacks( self::FIELDS_HOOK ) );
	}

	/**
	 * @testdox The hooks are registered again after they were removed.
	 */
	public function test_hooks_are_registered_again_after_removal(): void {
		$this->reset_hooks();
		new PostNL();

		foreach ( $this->postnl_callbacks( self::FIELDS_HOOK ) as $owner ) {
			remove_filter( self::FIELDS_HOOK, array( $owner, 'instance_form_fields' ), 10 );
		}
		foreach ( $this->postnl_callbacks( self::SAVE_HOOK ) as $owner ) {
			remove_action( self::SAVE_HOOK, array( $owner, 'process_admin_options' ), 10 );
		}

		new PostNL();

		$this->assertCount( 1, $this->postnl_callbacks( self::FIELDS_HOOK ) );
		$this->assertCount( 1, $this->postnl_callbacks( self::SAVE_HOOK ) );
	}

	/**
	 * @testdox Every zone instance gets the same instance fields as a single shipping method object.
	 */
	public function test_instance_fields_are_the_same_for_every_instance(): void {
		$this->reset_hooks();

		$expected = $this->instance_fields( 1 );

		$this->assertSame( POSTNL_SERVICE_NAME, $expected['title']['default'] );
		$this->assertArrayHasKey( 'minimum_for_free_shipping', $expected );

		foreach ( array( 2, 3, 4 ) as $instance_id ) {
			$this->assertSame( $expected, $this->instance_fields( $instance_id ) );
		}
	}

	/**
	 * Get the instance fields of a new zone instance, with each callback reduced to its method name.
	 *
	 * @param int $instance_id Instance ID.
	 *
	 * @return array
	 */
	private function instance_fields( int $instance_id ): array {
		$fields = ( new PostNL( $instance_id ) )->get_instance_form_fields();

		foreach ( $fields as $key => $field ) {
			foreach ( $field as $name => $value ) {
				if ( is_array( $value ) && isset( $value[0] ) && is_object( $value[0] ) ) {
					$fields[ $key ][ $name ] = get_class( $value[0] ) . '::' . $value[1];
				}
			}
		}

		return $fields;
	}

	/**
	 * Store country and stored legacy keys to compare the lookup under.
	 *
	 * @return array
	 */
	public static function lookup_provider(): array {
		return array(
			'NL with legacy keys'    => array( 'NL', 'OLDKEY-1', 'OLDSANDBOX-1' ),
			'NL without legacy keys' => array( 'NL', '', '' ),
			'BE with legacy keys'    => array( 'BE', 'OLDKEY-1', 'OLDSANDBOX-1' ),
			'BE without legacy keys' => array( 'BE', '', '' ),
		);
	}

	/**
	 * @testdox get_country_option returns the same value as the uncached lookup for every field.
	 *
	 * @dataProvider lookup_provider
	 *
	 * @param string $country     Store country.
	 * @param string $api_key     Stored legacy production key.
	 * @param string $sandbox_key Stored legacy sandbox key.
	 */
	public function test_country_option_matches_the_uncached_lookup( string $country, string $api_key, string $sandbox_key ): void {
		$settings = $this->store_settings(
			$country,
			array(
				'api_keys'                   => $api_key,
				'api_keys_sandbox'           => $sandbox_key,
				'api_keys_new'               => 'NEWKEY-1',
				'environment_mode'           => 'sandbox',
				'customer_num'               => '11223344',
				'number_delivery_days'       => '5',
				'default_shipping_options'   => 'signature_on_delivery',
				'return_address_or_reply_no' => 'antwoordnummer',
			)
		);

		$fields  = array_keys( $settings->filter_setting_fields( 'NL' ) + $settings->filter_setting_fields( 'BE' ) );
		$fields  = array_merge( $fields, array( 'api_keys', 'api_keys_sandbox', 'not_a_field' ) );
		$allowed = array_keys( $settings->filter_setting_fields( $country, false ) );

		foreach ( $fields as $field ) {
			$expected = in_array( $field, $allowed, true ) ? $settings->get_option( $field, 'fallback' ) : '';

			$this->assertSame( $expected, $settings->get_country_option( $field, 'fallback' ), $field );
			$this->assertSame( $expected, $settings->get_country_option( $field, 'fallback' ), $field . ' (second read)' );
		}
	}

	/**
	 * @testdox Repeated get_country_option calls build the field list once.
	 */
	public function test_repeated_lookups_build_the_field_list_once(): void {
		$settings = $this->store_settings( 'NL', array( 'api_keys' => 'OLDKEY-1' ) );

		$this->builds = 0;

		foreach ( array( 'api_keys', 'customer_num', 'cut_off_time', 'api_keys', 'not_a_field' ) as $field ) {
			$settings->get_country_option( $field );
		}

		$this->assertSame( 1, $this->builds );
	}

	/**
	 * @testdox init_settings empties the cached field keys.
	 */
	public function test_init_settings_empties_the_cache(): void {
		$settings = $this->store_settings( 'NL', array( 'api_keys' => 'OLDKEY-1' ) );
		$settings->get_country_option( 'api_keys' );

		$cache = new \ReflectionProperty( Settings::class, 'country_field_keys' );
		$cache->setAccessible( true );

		$this->assertCount( 1, $cache->getValue( $settings ) );

		$settings->init_settings();

		$this->assertSame( array(), $cache->getValue( $settings ) );
	}

	/**
	 * @testdox A settings save is read back in the same request.
	 */
	public function test_saved_values_are_returned_after_a_save(): void {
		$settings = $this->store_settings( 'NL', array() );

		$this->assertSame( '', $settings->get_country_option( 'api_keys_new', '' ) );
		$this->assertSame( '', $settings->get_country_option( 'customer_num', '' ) );

		$_POST['woocommerce_postnl_environment_mode'] = 'production';
		$_POST['woocommerce_postnl_api_keys']         = 'SAVEDKEY-1';
		$_POST['woocommerce_postnl_api_keys_new']     = 'SAVEDKEY-1';
		$_POST['woocommerce_postnl_customer_num']     = '44556677';

		( new PostNL() )->process_admin_options();

		$this->assertSame( 'SAVEDKEY-1', Settings::get_instance()->get_country_option( 'api_keys_new', '' ) );
		$this->assertSame( '44556677', Settings::get_instance()->get_country_option( 'customer_num', '' ) );
	}

	/**
	 * @testdox A value stored outside the form is returned after init_settings, and the field list follows a country change.
	 */
	public function test_stored_values_are_returned_after_init_settings(): void {
		$settings = $this->store_settings( 'NL', array( 'customer_num' => '111' ) );

		$this->assertSame( '111', $settings->get_country_option( 'customer_num', '' ) );

		update_option( 'woocommerce_postnl_settings', array( 'customer_num' => '222' ) );

		$this->assertSame( '111', $settings->get_country_option( 'customer_num', '' ) );

		$settings->init_settings();

		$this->assertSame( '222', $settings->get_country_option( 'customer_num', '' ) );

		$nl_only = array_diff(
			array_keys( $settings->filter_setting_fields( 'NL', false ) ),
			array_keys( $settings->filter_setting_fields( 'BE', false ) )
		);
		$field   = (string) current( $nl_only );

		$this->assertNotSame( '', $field );
		$this->assertSame( $settings->get_option( $field, 'fallback' ), $settings->get_country_option( $field, 'fallback' ) );

		update_option( 'woocommerce_default_country', 'BE' );

		$this->assertSame( '', $settings->get_country_option( $field, 'fallback' ) );
	}
}
