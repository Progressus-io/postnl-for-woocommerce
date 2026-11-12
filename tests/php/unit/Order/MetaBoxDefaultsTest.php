<?php
/**
 * Unit tests for the default values the Label & Tracking meta box pre-selects.
 *
 * @package PostNLWooCommerce\Tests\Order
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Order;

use Brain\Monkey\Functions;
use PostNLWooCommerce\Order\Base;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Tests\UnitTestCase;
use PostNLWooCommerce\Utils;
use ReflectionClass;
use ReflectionProperty;

/**
 * Covers the default values meta_box_fields() gives the shipping option checkboxes.
 *
 * @covers \PostNLWooCommerce\Order\Base::meta_box_fields
 */
class MetaBoxDefaultsTest extends UnitTestCase {

	/**
	 * Stub the WordPress functions meta_box_fields() calls.
	 */
	protected function setUp(): void {
		parent::setUp();

		Functions\stubTranslationFunctions();
		Functions\when( 'wp_create_nonce' )->justReturn( 'nonce' );
		Functions\when( 'apply_filters' )->returnArg( 2 );
	}

	/**
	 * Build the meta box fields for an order whose resolved default is the given setting value.
	 *
	 * @param string $default_setting Pipe-separated default shipping option from the settings.
	 *
	 * @return array Field values keyed by option name.
	 */
	private function field_values( string $default_setting ): array {
		$sut                  = ( new ReflectionClass( Meta_Box_Defaults_Base::class ) )->newInstanceWithoutConstructor();
		$sut->default_options = Utils::prepare_shipping_options( $default_setting );

		$settings = new ReflectionProperty( Base::class, 'settings' );
		$settings->setAccessible( true );
		$settings->setValue( $sut, ( new ReflectionClass( Meta_Box_Defaults_Settings::class ) )->newInstanceWithoutConstructor() );

		$values = array();
		foreach ( $sut->meta_box_fields( new \WC_Order() ) as $field ) {
			$values[ str_replace( POSTNL_SETTINGS_ID . '_', '', $field['id'] ) ] = $field['value'] ?? null;
		}

		return $values;
	}

	/**
	 * Default settings and the meta box options each must pre-select.
	 *
	 * @return array
	 */
	public static function default_settings_provider(): array {
		return array(
			'packets'                      => array( 'packets', array( 'packets' ) ),
			'packets + track and trace'    => array( 'packets|track_and_trace', array( 'packets', 'track_and_trace' ) ),
			'packets + t&t + insured'      => array( 'packets|track_and_trace|insured_shipping', array( 'packets', 'track_and_trace', 'insured_shipping' ) ),
			'packets + t&t + insured plus' => array( 'packets|track_and_trace|insured_plus', array( 'packets', 'track_and_trace', 'insured_plus' ) ),
			'mailbox packet'               => array( 'mailboxpacket', array( 'mailboxpacket' ) ),
			'mailbox packet + t&t'         => array( 'mailboxpacket|track_and_trace', array( 'mailboxpacket', 'track_and_trace' ) ),
			'eu parcel + t&t + ins plus'   => array( 'eu_parcel|track_and_trace|insured_plus', array( 'track_and_trace', 'insured_plus' ) ),
			'standard belgium + insured'   => array( 'standard_belgium|insured_shipping', array( 'insured_shipping' ) ),
		);
	}

	/**
	 * Pre-select the options of each default shipping product.
	 *
	 * @testdox meta_box_fields() pre-selects exactly the default options that have a meta box checkbox.
	 *
	 * @dataProvider default_settings_provider
	 *
	 * @param string $default_setting Pipe-separated default shipping option from the settings.
	 * @param array  $expected        Options that must be pre-selected.
	 */
	public function test_preselects_default_shipping_product( string $default_setting, array $expected ): void {
		$values = $this->field_values( $default_setting );

		foreach ( array( 'packets', 'mailboxpacket', 'track_and_trace', 'insured_shipping', 'insured_plus' ) as $option ) {
			$this->assertArrayHasKey( $option, $values );
			$this->assertSame(
				in_array( $option, $expected, true ) ? 'yes' : '',
				$values[ $option ],
				$option . ' must match the ' . $default_setting . ' default'
			);
		}
	}
}

/**
 * Order\Base double that returns fixed default options instead of resolving them from an order.
 */
class Meta_Box_Defaults_Base extends Base { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Default options returned by get_shipping_options().
	 *
	 * @var array
	 */
	public $default_options = array();

	/**
	 * Required by Order\Base; nothing to hook in a unit test.
	 */
	public function init_hooks() {}

	/**
	 * Return the fixed default options.
	 *
	 * @param \WC_Order $order Order object.
	 *
	 * @return array
	 */
	public function get_shipping_options( $order ) {
		return $this->default_options;
	}
}

/**
 * Settings double exposing only the getters meta_box_fields() reads.
 */
class Meta_Box_Defaults_Settings extends Settings { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Return the return label setting.
	 *
	 * @return string
	 */
	public function get_return_shipment_and_labels() {
		return 'none';
	}

	/**
	 * Return the label format setting.
	 *
	 * @return string
	 */
	public function get_label_format() {
		return 'A6';
	}
}
