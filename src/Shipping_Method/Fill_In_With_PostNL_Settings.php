<?php
/**
 * Class Shipping_Method/Fill_In_With_PostNL_Settings file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

use WC_Admin_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fill_In_With_PostNL_Settings
 * Handles the 'Fill in with PostNL' WooCommerce shipping settings tab.
 */
class Fill_In_With_PostNL_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_get_sections_shipping', array( $this, 'add_settings_section' ) );
		add_filter( 'woocommerce_get_settings_shipping', array( $this, 'add_settings_fields' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		add_filter( 'woocommerce_admin_settings_sanitize_option', array( $this, 'maybe_prevent_saving_invalid_data' ), 10, 3 );
	}

	/**
	 * Add new section to the WooCommerce Shipping settings.
	 *
	 * @param array $sections Existing WooCommerce shipping sections.
	 *
	 * @return array
	 */
	public function add_settings_section( array $sections ): array {
		$sections['fill-in-with-postnl'] = esc_html__( 'Fill in with PostNL', 'postnl-for-woocommerce' );
		return $sections;
	}

	/**
	 * Add settings fields to the custom section.
	 *
	 * @param array  $settings Existing settings.
	 * @param string $current_section Current section being rendered.
	 *
	 * @return array
	 */
	public function add_settings_fields( $settings, $current_section ): array {
		if ( 'fill-in-with-postnl' !== $current_section ) {
			return $settings;
		}

		$info_block = '<p>' . esc_html__( 'With this functionality your customers can easily and automatically fill in their shipping address via their PostNL account. This functionality is only available for consumers with a Dutch shipping address. Follow the next steps in order to use Fill in with:', 'postnl-for-woocommerce' ) . '</p>';

		$info_block .= '<ol>';
		$info_block .= '<li>' . sprintf(
			// translators: %s is an external url for postnl portal.
			esc_html__( 'Log in at the business portal (%s) with your business account.', 'postnl-for-woocommerce' ),
			esc_url( 'https://dil-business-portal.postnl.nl/home' )
		) . '</li>';
		$info_block .= '<li>' . esc_html__( 'Select "Fill in with PostNL".', 'postnl-for-woocommerce' ) . '</li>';
		$info_block .= '<li>' . esc_html__( 'Update the Redirect URL and choose your application.', 'postnl-for-woocommerce' ) . '</li>';
		$info_block .= '<li>' . esc_html__( 'You will receive your Client ID via email.', 'postnl-for-woocommerce' ) . '</li>';
		$info_block .= '</ol>';

		$settings = array(
			array(
				'title' => esc_html__( 'Fill in with PostNL', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'desc'  => $info_block,
				'id'    => 'postnl_fill_in_with_title',
			),
			array(
				'title'   => esc_html__( 'Enable', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'Enable Fill in with PostNL functionality.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_enable_fill_in_with',
				'default' => 'no',
				'type'    => 'checkbox',
			),
			array(
				'title' => esc_html__( 'Client ID', 'postnl-for-woocommerce' ),
				'desc'  => esc_html__( 'Enter your PostNL Client ID from the Digital Business Portal.', 'postnl-for-woocommerce' ),
				'id'    => 'postnl_fill_in_with_client_id',
				'type'  => 'text',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_fill_in_with_section_end',
			),

			// Cart Button section.
			array(
				'title' => esc_html__( 'Cart Button', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_cart_button_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in cart page', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'Automatically render checkout button in cart page', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_cart_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Cart page button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_cart_button_placement',
				'type'    => 'select',
				'default' => 'before_checkout',
				'options' => array(
					'before_checkout' => esc_html__( 'Before Checkout', 'postnl-for-woocommerce' ),
					'after_checkout'  => esc_html__( 'After Checkout', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_cart_button_section_end',
			),

			// Checkout Page section.
			array(
				'title' => esc_html__( 'Checkout Page', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_checkout_page_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in checkout page', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'Automatically render button in checkout page', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_checkout_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Checkout page button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_checkout_button_placement',
				'type'    => 'select',
				'default' => 'before_customer_details',
				'options' => array(
					'before_customer_details' => esc_html__( 'Before Customer Details', 'postnl-for-woocommerce' ),
					'after_customer_details'  => esc_html__( 'After Customer Details', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_checkout_section_end',
			),

			// Minicart section.
			array(
				'title' => esc_html__( 'Minicart', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_minicart_title',
			),
			array(
				'title'   => esc_html__( 'Automatically render button in Minicart', 'postnl-for-woocommerce' ),
				'desc'    => esc_html__( 'Automatically render button in Minicart', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_minicart_auto_render_button',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => esc_html__( 'Minicart button placement', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_minicart_button_placement',
				'type'    => 'select',
				'default' => 'before_buttons',
				'options' => array(
					'before_buttons' => esc_html__( 'Before Buttons', 'postnl-for-woocommerce' ),
					'after_buttons'  => esc_html__( 'After Buttons', 'postnl-for-woocommerce' ),
				),
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_minicart_section_end',
			),

			// Custom css section.
			array(
				'title' => esc_html__( 'Custom CSS', 'postnl-for-woocommerce' ),
				'type'  => 'title',
				'id'    => 'postnl_custom_css_title',
			),
			array(
				'title'    => esc_html__( 'Custom CSS', 'postnl-for-woocommerce' ),
				'desc_tip' => esc_html__( 'Add your custom styles for the PostNL button. This will be printed in the site header.', 'postnl-for-woocommerce' ),
				'id'       => 'postnl_custom_css',
				'type'     => 'textarea',
				'css'      => 'min-height:200px; font-family: monospace;',
				'class'    => 'postnl-css-editor',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'postnl_custom_css_section_end',
			),
			array(
				'desc'    => esc_html__( 'I understand that the PostNL address fields are activated (= separate field for zipcode, housnumber, housenumber extension and street) to let consumers fill in their shipping address with the PostNL app.', 'postnl-for-woocommerce' ),
				'id'      => 'postnl_consent_checkbox',
				'type'    => 'checkbox',
				'default' => 'no',
			),
		);

		return $settings;
	}

	/**
	 * Enqueue admin scripts.
	 * This method is used to load the CodeMirror editor for custom CSS in the settings page.
	 *
	 * @param string $hook The current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_admin_scripts( $hook ): void {
		if ( 'woocommerce_page_wc-settings' !== $hook || ! isset( $_GET['section'] ) || 'fill-in-with-postnl' !== $_GET['section'] ) {
			return;
		}

		// Load WP's built-in CodeMirror.
		wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		// Init script.
		wp_add_inline_script(
			'wp-theme-plugin-editor',
			<<<JS
			jQuery( function( $ ) {
				wp.codeEditor.initialize( $('.postnl-css-editor'), {
					codemirror: {
						mode: 'css',
						lineNumbers: true,
						indentUnit: 2,
						viewportMargin: Infinity,
					}
				});
			});
		JS
		);
	}

	/**
	 * Prevent saving if required conditions aren't met.
	 *
	 * @param mixed  $value The value to be saved.
	 * @param array  $option Option details.
	 * @param string $raw_value Raw (unsanitized) value.
	 *
	 * @return mixed
	 */
	public function maybe_prevent_saving_invalid_data( $value, $option, $raw_value ) {
		static $validation_done = false;

		// Run only for settings prefixed with 'postnl_'.
		if ( false === strpos( $option['id'], 'postnl_' ) ) {
			return $value;
		}

		// Only run once per settings save.
		if ( $validation_done ) {
			return $value;
		}

		$validation_done = true;

		// Validation only needed if 'Fill in with PostNL' is enabled.
		$is_enabled = isset( $_POST['postnl_enable_fill_in_with'] );
		if ( ! $is_enabled ) {
			return $value;
		}

		// Get checkbox values.
		$cart_checked     = isset( $_POST['postnl_cart_auto_render_button'] );
		$checkout_checked = isset( $_POST['postnl_checkout_auto_render_button'] );
		$minicart_checked = isset( $_POST['postnl_minicart_auto_render_button'] );

		// If all are disabled, add error.
		if ( ! $cart_checked && ! $checkout_checked && ! $minicart_checked ) {
			WC_Admin_Settings::add_error(
				esc_html__( 'You must enable at least one "Automatically render button" option in Cart, Checkout, or Minicart.', 'postnl-for-woocommerce' )
			);

			return get_option( $option['id'], $option['default'] ?? '' );
		}

		return $value;
	}
}
