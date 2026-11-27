<?php
/**
 * Integration tests for Smart Returns from and to Belgium on V4.
 *
 * The Smart Return button is offered for BE shipping addresses once Smart
 * Returns run on V4. Once they do, a Belgian store's settings hide the reply
 * number choice and point to Smart Return; until then the form is unchanged.
 *
 * @package PostNLWooCommerce\Tests\Integration
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Order\Single;
use PostNLWooCommerce\Rest_API\Legacy\Smart_Returns\Item_Info;
use PostNLWooCommerce\Rest_API\Service_Factory;
use PostNLWooCommerce\Shipping_Method\PostNL;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\IntegrationTestCase;

/**
 * Exercises the order screen button, the factory predicate and the BE settings form.
 */
class SmartReturnsBelgiumTest extends IntegrationTestCase {

	/**
	 * V4 key the tests validate.
	 */
	private const V4_KEY = 'test-v4-key';

	/**
	 * Option holding the hash of the validated sandbox V4 key.
	 */
	private const SANDBOX_HASH_OPTION = Settings::NEW_API_KEY_VALIDATED_HASH_OPTION . '_sandbox';

	/**
	 * Backup of the settings singleton's loaded option array.
	 *
	 * @var array
	 */
	private $orig_settings = array();

	/**
	 * Backup of the store options this test overrides.
	 *
	 * @var array
	 */
	private $orig_options = array();

	/**
	 * Orders created during the test.
	 *
	 * @var int[]
	 */
	private $order_ids = array();

	/**
	 * Enable Smart Return with a validated sandbox V4 key, flows off.
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( array( 'woocommerce_default_country', 'woocommerce_store_address', 'woocommerce_store_city', 'woocommerce_store_postcode', self::SANDBOX_HASH_OPTION ) as $option ) {
			$this->orig_options[ $option ] = get_option( $option );
		}

		$settings            = Settings::get_instance();
		$this->orig_settings = $settings->settings;

		$settings->settings['environment_mode']      = 'sandbox';
		$settings->settings['api_keys_sandbox_new']  = self::V4_KEY;
		$settings->settings['activate_smart_return'] = 'yes';
		$settings->set_api_key_new_validated( true, self::V4_KEY );
	}

	/**
	 * Restore settings, store options, flags and orders.
	 */
	protected function tearDown(): void {
		remove_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );
		remove_filter( 'postnl_sdk_enable_label', '__return_true' );

		foreach ( $this->order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$order->delete( true );
			}
		}

		Settings::get_instance()->settings = $this->orig_settings;

		foreach ( $this->orig_options as $option => $value ) {
			if ( false === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		parent::tearDown();
	}

	/**
	 * Skip a test that needs V4 routing when the PostNL SDK is not installed.
	 */
	private function require_sdk(): void {
		if ( ! class_exists( \Postnl\Sdk\Client\ClientBuilder::class ) ) {
			$this->markTestSkipped( 'The PostNL SDK is not installed, so no flow can run on V4.' );
		}
	}

	/**
	 * Create an order shipped to the given country.
	 *
	 * @param string $country Shipping country.
	 *
	 * @return \WC_Order
	 */
	private function make_order( string $country ): \WC_Order {
		$order = new \WC_Order();
		$order->set_shipping_country( $country );
		$order->save();
		$this->order_ids[] = $order->get_id();

		return $order;
	}

	/**
	 * Render the Smart Return button area for an order shipped to the given country.
	 *
	 * @param string $country Shipping country.
	 *
	 * @return string
	 */
	private function button_html( string $country ): string {
		$single = new class() extends Single {
			/**
			 * Skip hook registration.
			 */
			public function init_hooks() {}
		};

		ob_start();
		$single->send_smart_return_email_html( $this->make_order( $country ) );

		return (string) ob_get_clean();
	}

	/**
	 * Build the PostNL settings form for a store in the given country.
	 *
	 * The shipping method registers admin hooks when it is built; they are
	 * removed again so no instance outlives the test.
	 *
	 * @param string $country Store country.
	 *
	 * @return array
	 */
	private function form_fields( string $country ): array {
		update_option( 'woocommerce_default_country', $country );

		$method = new PostNL();
		$method->init_form_fields();
		$fields = $method->form_fields;

		remove_filter( 'woocommerce_shipping_instance_form_fields_' . $method->id, array( $method, 'instance_form_fields' ), 10 );
		remove_action( 'woocommerce_update_options_shipping_' . $method->id, array( $method, 'process_admin_options' ) );
		remove_action( 'admin_enqueue_scripts', array( $method, 'enqueue_shipping_method_assets' ) );

		return $fields;
	}

	/**
	 * @testdox The Smart Return button shows for a BE address only when Smart Returns run on V4.
	 */
	public function test_button_for_be_address_follows_the_v4_flow(): void {
		$this->assertStringNotContainsString( 'button-send-smart-return', $this->button_html( 'BE' ) );

		$this->require_sdk();
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'BE' ) );
	}

	/**
	 * @testdox The Smart Return button keeps showing for NL addresses and never for other countries.
	 */
	public function test_button_for_nl_and_other_addresses(): void {
		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'NL' ) );

		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertStringContainsString( 'button-send-smart-return', $this->button_html( 'NL' ) );
		$this->assertStringNotContainsString( 'button-send-smart-return', $this->button_html( 'DE' ) );
	}

	/**
	 * @testdox With Smart Return off, the BE button stays hidden even when Smart Returns run on V4.
	 */
	public function test_be_button_needs_smart_return_enabled(): void {
		$this->require_sdk();
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );
		Settings::get_instance()->settings['activate_smart_return'] = 'no';

		$this->assertStringNotContainsString( 'button-send-smart-return', $this->button_html( 'BE' ) );
	}

	/**
	 * @testdox Saving a BE store's settings with Smart Returns on V4 keeps the hidden reply number values.
	 */
	public function test_saving_be_settings_on_v4_keeps_hidden_values(): void {
		$this->require_sdk();
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );
		update_option( 'woocommerce_default_country', 'BE' );

		$option = 'woocommerce_' . POSTNL_SETTINGS_ID . '_settings';
		$stored = get_option( $option );
		update_option(
			$option,
			array_merge(
				is_array( $stored ) ? $stored : array(),
				array(
					'return_address_or_reply_no' => 'no',
					'freepost_zip'               => '9999ZZ',
					'freepost_city'              => 'Freepost City',
				)
			)
		);

		$method = new class() extends PostNL {
			/**
			 * Run only the WooCommerce settings save, without the key validation call.
			 */
			public function save_form(): void {
				\WC_Shipping_Method::process_admin_options();
			}
		};
		remove_filter( 'woocommerce_shipping_instance_form_fields_' . $method->id, array( $method, 'instance_form_fields' ), 10 );
		remove_action( 'woocommerce_update_options_shipping_' . $method->id, array( $method, 'process_admin_options' ) );
		remove_action( 'admin_enqueue_scripts', array( $method, 'enqueue_shipping_method_assets' ) );

		$this->assertArrayNotHasKey( 'freepost_zip', $method->get_form_fields() );

		$_POST = array( $method->get_field_key( 'return_address_street' ) => 'Meir' );
		$method->save_form();
		$_POST = array();

		$saved = get_option( $option );

		if ( false === $stored ) {
			delete_option( $option );
		} else {
			update_option( $option, $stored );
		}

		$this->assertSame( 'Meir', $saved['return_address_street'] );
		$this->assertSame( 'no', $saved['return_address_or_reply_no'] );
		$this->assertSame( '9999ZZ', $saved['freepost_zip'] );
		$this->assertSame( 'Freepost City', $saved['freepost_city'] );
	}

	/**
	 * @testdox uses_v4_for() reports the per-flow V4 routing.
	 */
	public function test_uses_v4_for_reports_the_flow(): void {
		$factory = new Service_Factory( Settings::get_instance() );

		$this->assertFalse( $factory->uses_v4_for( 'smart_returns' ) );

		$this->require_sdk();
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$this->assertTrue( $factory->uses_v4_for( 'smart_returns' ) );
		$this->assertFalse( $factory->uses_v4_for( 'label' ) );
	}

	/**
	 * @testdox A BE store keeps "Return to home address" and the freepost fields until Smart Returns run on V4.
	 */
	public function test_be_store_keeps_reply_number_choice_until_v4(): void {
		$be = $this->form_fields( 'BE' );

		$this->assertArrayHasKey( 'return_address_or_reply_no', $be );
		$this->assertArrayHasKey( 'freepost_zip', $be );
		$this->assertArrayHasKey( 'freepost_city', $be );
		$this->assertArrayHasKey( 'return_address_street', $be );
	}

	/**
	 * @testdox With Smart Returns on V4 a BE store's settings hide the reply number choice; an NL store keeps it.
	 */
	public function test_be_store_hides_reply_number_choice_on_v4(): void {
		$this->require_sdk();
		add_filter( 'postnl_sdk_enable_smart_returns', '__return_true' );

		$be = $this->form_fields( 'BE' );

		$this->assertArrayNotHasKey( 'return_address_or_reply_no', $be );
		$this->assertArrayNotHasKey( 'freepost_zip', $be );
		$this->assertArrayNotHasKey( 'freepost_city', $be );
		$this->assertArrayHasKey( 'return_address_street', $be );
		$this->assertArrayHasKey( 'return_address_zip', $be );
		$this->assertArrayHasKey( 'return_address_city', $be );

		$nl = $this->form_fields( 'NL' );

		$this->assertArrayHasKey( 'return_address_or_reply_no', $nl );
		$this->assertArrayHasKey( 'freepost_zip', $nl );
		$this->assertArrayHasKey( 'return_replynumber', $nl );
	}

	/**
	 * Flow flags and the Return Settings note a BE store gets for them.
	 *
	 * @return array<string, array{0:string[],1:string|null}>
	 */
	public static function be_notes(): array {
		$label = 'Label in the box is not available with the new PostNL APIs.';
		$smart = 'You can offer returns with Smart Return. Returns are sent to your return address below.';

		return array(
			'labels on V4'              => array( array( 'label' ), $label ),
			'Smart Returns on V4'       => array( array( 'smart_returns' ), $smart ),
			'both on V4'                => array( array( 'label', 'smart_returns' ), $label . ' ' . $smart ),
		);
	}

	/**
	 * @testdox A BE store's settings note follows each V4 flow: label in the box gone, and the pointer to Smart Return.
	 *
	 * @dataProvider be_notes
	 *
	 * @param string[] $flows Flows switched to V4.
	 * @param string   $note  Expected Return Settings description.
	 */
	public function test_be_store_note_follows_the_v4_flows( array $flows, string $note ): void {
		$this->assertArrayNotHasKey( 'description', $this->form_fields( 'BE' )['return_settings_title'] );

		$this->require_sdk();
		foreach ( $flows as $flow ) {
			add_filter( 'postnl_sdk_enable_' . $flow, '__return_true' );
		}

		$this->assertSame( $note, $this->form_fields( 'BE' )['return_settings_title']['description'] );
	}

	/**
	 * @testdox The Smart Returns item info names a Belgian store as BE, which routes it to the home return address.
	 */
	public function test_item_info_reports_a_belgian_store(): void {
		update_option( 'woocommerce_default_country', 'BE' );
		update_option( 'woocommerce_store_address', 'Meir 1' );
		update_option( 'woocommerce_store_city', 'Antwerpen' );
		update_option( 'woocommerce_store_postcode', '2000' );

		$order = $this->make_order( 'NL' );
		$order->set_shipping_address_1( 'Hoofdstraat 1' );
		$order->set_shipping_postcode( '6591BA' );
		$order->set_shipping_city( 'Gennep' );
		$order->save();

		$this->assertSame( 'BE', ( new Item_Info( $order ) )->store['country'] );
	}
}
