<?php
/**
 * Integration tests for the Fill in With PostNL button styling controls.
 *
 * Three distinct concerns are guarded here:
 *
 *  1. Clamping: the width and corner-radius fields carry an HTML "max"
 *     attribute, which is a client-side hint and nothing more. The clamp inside
 *     maybe_prevent_saving_invalid_data() is the only thing that bounds the
 *     stored value. It has to floor a negative rather than reflect it into
 *     range: absint() turns -500 into 500, which then clamps to a 100% width
 *     instead of the intended minimum of 1.
 *
 *  2. Preview cardinality: Fill_In_With_PostNL_Settings is constructed once per
 *     consumer (Main, the frontend renderer, the OAuth handler and the blocks
 *     integration). woocommerce_admin_field_postnl_preview is an action rather
 *     than a filter, so a callback bound to $this renders the preview once per
 *     instance and emits duplicate "postnl-button-preview" DOM ids. Binding the
 *     callback statically collapses the registrations to one.
 *
 *  3. Dynamic CSS: add_custom_css() must emit one width rule per button context
 *     plus the shared corner radius, so each context can be sized independently
 *     from the settings screen.
 *
 * Note that Main::__construct() returns early unless the WooCommerce base
 * country is NL or BE, so the plugin's hooks are not registered under the test
 * bootstrap. These tests therefore drive the classes directly rather than
 * relying on the ambient hook state.
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Integration;

use PostNLWooCommerce\Tests\IntegrationTestCase;
use PostNLWooCommerce\Frontend\Fill_In_With_Postnl;
use PostNLWooCommerce\Shipping_Method\Fill_In_With_PostNL_Settings;

/**
 * Covers the server-side clamp, the preview renderer and the generated CSS.
 */
class FillInWithButtonStylingTest extends IntegrationTestCase {

	/**
	 * Hook the settings screen uses to render the preview row.
	 */
	private const PREVIEW_HOOK = 'woocommerce_admin_field_postnl_preview';

	/**
	 * Number of times the plugin constructs Fill_In_With_PostNL_Settings per request.
	 */
	private const INSTANTIATIONS_PER_REQUEST = 4;

	/**
	 * Sentinel marking an option that did not exist before the test ran.
	 */
	private const ABSENT = '__absent__';

	/**
	 * Every option this suite reads or writes.
	 */
	private const TOUCHED_OPTIONS = array(
		'postnl_enable_fill_in_with',
		'postnl_fill_in_with_client_id',
		'postnl_cart_button_width',
		'postnl_checkout_button_width',
		'postnl_minicart_button_width',
		'postnl_button_border_radius',
		'postnl_button_border',
		'postnl_button_alignment',
		'postnl_button_background_color',
		'postnl_button_hover_background_color',
		'postnl_custom_css',
	);

	/**
	 * Snapshot of the touched options, keyed by option name.
	 *
	 * @var array
	 */
	private $option_snapshot = array();

	/**
	 * Capture the original option values so each test starts from a known state.
	 */
	protected function setUp(): void {
		parent::setUp();

		foreach ( self::TOUCHED_OPTIONS as $option ) {
			$this->option_snapshot[ $option ] = get_option( $option, self::ABSENT );
		}
	}

	/**
	 * Restore every option this suite touched and drop the generated stylesheet.
	 */
	protected function tearDown(): void {
		foreach ( $this->option_snapshot as $option => $value ) {
			if ( self::ABSENT === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}

		wp_dequeue_style( 'postnl-custom-css' );
		wp_deregister_style( 'postnl-custom-css' );

		// The preview hook belongs solely to this plugin, so clearing it cannot
		// disturb WordPress or WooCommerce callbacks.
		remove_all_actions( self::PREVIEW_HOOK );

		parent::tearDown();
	}

	/**
	 * @testdox A numeric range option is clamped to its bounds when saved.
	 *
	 * @dataProvider clamp_cases
	 *
	 * @param string $option_id Option being saved.
	 * @param string $raw       Value as it arrives from the settings form.
	 * @param int    $expected  Value that must reach the database.
	 */
	public function test_numeric_range_option_is_clamped_on_save( string $option_id, string $raw, int $expected ): void {
		$settings = new Fill_In_With_PostNL_Settings();

		$actual = $settings->maybe_prevent_saving_invalid_data(
			$raw,
			array(
				'id'      => $option_id,
				'default' => '100',
			),
			$raw
		);

		$this->assertSame( $expected, $actual );
	}

	/**
	 * Boundary, out-of-range and junk inputs for each clamped option.
	 *
	 * The negative cases are the regression guard: absint() reflects them into
	 * range rather than flooring them.
	 *
	 * @return array
	 */
	public static function clamp_cases(): array {
		return array(
			'width above max'         => array( 'postnl_cart_button_width', '999', 100 ),
			'width at max'            => array( 'postnl_cart_button_width', '100', 100 ),
			'width in range'          => array( 'postnl_cart_button_width', '50', 50 ),
			'width at min'            => array( 'postnl_cart_button_width', '1', 1 ),
			'width below min'         => array( 'postnl_cart_button_width', '0', 1 ),
			'width negative floors'   => array( 'postnl_cart_button_width', '-5', 1 ),
			'width large negative'    => array( 'postnl_cart_button_width', '-500', 1 ),
			'width non numeric'       => array( 'postnl_cart_button_width', 'abc', 1 ),
			'width empty'             => array( 'postnl_cart_button_width', '', 1 ),
			'checkout width negative' => array( 'postnl_checkout_button_width', '-1', 1 ),
			'checkout width in range' => array( 'postnl_checkout_button_width', '80', 80 ),
			'minicart width at max'   => array( 'postnl_minicart_button_width', '100', 100 ),
			'radius above max'        => array( 'postnl_button_border_radius', '999', 50 ),
			'radius at max'           => array( 'postnl_button_border_radius', '50', 50 ),
			'radius in range'         => array( 'postnl_button_border_radius', '12', 12 ),
			'radius at min'           => array( 'postnl_button_border_radius', '0', 0 ),
			'radius negative floors'  => array( 'postnl_button_border_radius', '-5', 0 ),
			'radius large negative'   => array( 'postnl_button_border_radius', '-500', 0 ),
		);
	}

	/**
	 * @testdox An option outside the clamped set passes through untouched.
	 */
	public function test_unclamped_postnl_option_is_returned_unchanged(): void {
		$settings = new Fill_In_With_PostNL_Settings();

		$actual = $settings->maybe_prevent_saving_invalid_data(
			'1px solid #000000',
			array(
				'id'      => 'postnl_button_border',
				'default' => 'none',
			),
			'1px solid #000000'
		);

		$this->assertSame( '1px solid #000000', $actual );
	}

	/**
	 * @testdox The settings class registers the preview renderer.
	 */
	public function test_constructor_registers_the_preview_renderer(): void {
		remove_all_actions( self::PREVIEW_HOOK );

		new Fill_In_With_PostNL_Settings();

		$this->assertNotFalse( has_action( self::PREVIEW_HOOK ), 'The preview field type must have a renderer bound to it.' );
	}

	/**
	 * @testdox The preview renders once even when the settings class is constructed repeatedly.
	 */
	public function test_button_preview_renders_exactly_once(): void {
		remove_all_actions( self::PREVIEW_HOOK );

		for ( $i = 0; $i < self::INSTANTIATIONS_PER_REQUEST; $i++ ) {
			new Fill_In_With_PostNL_Settings();
		}

		ob_start();
		do_action(
			self::PREVIEW_HOOK,
			array(
				'type' => 'postnl_preview',
				'id'   => 'postnl_button_preview',
			)
		);
		$html = ob_get_clean();

		$this->assertStringContainsString( 'id="postnl-button-preview"', $html, 'The preview field should render.' );

		$this->assertSame(
			1,
			substr_count( $html, 'id="postnl-button-preview"' ),
			'The preview must render once no matter how many times the settings class is constructed. A duplicate id means the hook was bound to $this instead of the class.'
		);

		$this->assertSame( 1, substr_count( $html, '<tr' ), 'The preview should emit a single table row.' );
	}

	/**
	 * @testdox Each button context gets its own width rule plus the shared radius.
	 */
	public function test_add_custom_css_emits_per_context_widths_and_radius(): void {
		update_option( 'postnl_enable_fill_in_with', 'yes' );
		update_option( 'postnl_fill_in_with_client_id', 'test-client-id' );
		update_option( 'postnl_cart_button_width', '60' );
		update_option( 'postnl_checkout_button_width', '80' );
		update_option( 'postnl_minicart_button_width', '100' );
		update_option( 'postnl_button_border_radius', '12' );

		$css = $this->generate_inline_css();

		$this->assertStringContainsString( '.postnl-button-in-cart #postnl-login-button { width: 60%; }', $css );
		$this->assertStringContainsString( '.postnl-button-in-checkout #postnl-login-button { width: 80%; }', $css );
		$this->assertStringContainsString( '.postnl-button-in-minicart #postnl-login-button { width: 100%; }', $css );
		$this->assertStringContainsString( 'border-radius: 12px;', $css );
	}

	/**
	 * @testdox With no alignment saved the button defaults to centered so reduced widths do not hug the left edge.
	 */
	public function test_add_custom_css_defaults_to_centered_alignment(): void {
		update_option( 'postnl_enable_fill_in_with', 'yes' );
		update_option( 'postnl_fill_in_with_client_id', 'test-client-id' );
		delete_option( 'postnl_button_alignment' );

		$css = $this->generate_inline_css();

		$this->assertStringContainsString( 'margin-left: auto; margin-right: auto;', $css );
	}

	/**
	 * @testdox A zero corner radius is emitted rather than falling back to the default.
	 */
	public function test_add_custom_css_emits_a_zero_radius(): void {
		update_option( 'postnl_enable_fill_in_with', 'yes' );
		update_option( 'postnl_fill_in_with_client_id', 'test-client-id' );
		update_option( 'postnl_button_border_radius', '0' );

		$this->assertStringContainsString( 'border-radius: 0px;', $this->generate_inline_css() );
	}

	/**
	 * @testdox No stylesheet is generated while the feature is disabled.
	 */
	public function test_add_custom_css_is_skipped_when_disabled(): void {
		update_option( 'postnl_enable_fill_in_with', 'no' );

		$this->assertSame( '', $this->generate_inline_css() );
	}

	/**
	 * Run add_custom_css() and return whatever inline CSS it registered.
	 *
	 * Constructing the frontend class is the only route to add_custom_css(). Its
	 * constructor binds hooks, which is harmless here because the request is torn
	 * down after the suite.
	 *
	 * @return string
	 */
	private function generate_inline_css(): string {
		$frontend = new Fill_In_With_Postnl();
		$frontend->add_custom_css();

		$inline = wp_styles()->get_data( 'postnl-custom-css', 'after' );

		if ( is_array( $inline ) ) {
			return implode( '', $inline );
		}

		return (string) $inline;
	}
}
