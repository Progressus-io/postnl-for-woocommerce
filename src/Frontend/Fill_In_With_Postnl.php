<?php
/**
 * Class Frontend/Fill_In_With_Postnl file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use WP_REST_Response;
use PostNLWooCommerce\Shipping_Method\Fill_In_With_PostNL_Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fill_In_With_Postnl
 * Renders the "Fill in with PostNL" button based on admin settings.
 */
class Fill_In_With_Postnl {

	/**
	 * Settings class instance.
	 *
	 * @var Fill_In_With_PostNL_Settings
	 */
	protected $settings;

	/**
	 * Constructor.
	 * Initializes the button rendering based on admin settings.
	 */
	public function __construct() {
		$this->settings = new Fill_In_With_PostNL_Settings();
		add_shortcode( 'print_fill_in_with_postnl_button', array( $this, 'print_fill_in_button' ) );
		add_action( 'wp_head', array( $this, 'add_custom_css' ) );
		add_filter( 'render_block', array( $this, 'postnl_woocommerce_cart_block_do_actions' ), 9999, 2 );
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_nopriv_get_postnl_user_info', array( $this, 'handle_postnl_user_info' ) );
		add_action( 'wp_ajax_get_postnl_user_info', array( $this, 'handle_postnl_user_info' ) );

		$this->maybe_add_hooks();
	}

	/**
	 * Handle the AJAX request to get PostNL user info.
	 *
	 * This method retrieves user data stored in the WooCommerce session and returns it as a JSON response.
	 *
	 * @return void
	 */
	public function handle_postnl_user_info(): void {
		if ( ! $this->settings->is_fill_in_with_postnl_enabled() ) {
			wp_send_json_error( 'Fill in with PostNL is not enabled or Client ID is missing.' );
		}
		$data = WC()->session->get( 'postnl_user_data' );
		if ( ! $data ) {
			wp_send_json_error( 'No user data' );
		}
		wp_send_json_success( $data );
	}

	/**
	 * Print the "Fill in with PostNL" button.
	 *
	 * This method is used to render the button via a shortcode.
	 *
	 * @return string Rendered button HTML or empty string if not enabled.
	 */
	public function print_fill_in_button(): string {
		if ( ! $this->is_enabled() ) {
			return '';
		}

		ob_start();
		$this->render_button();
		return ob_get_clean();
	}

	/**
	 * Add actions to render the "Fill in with PostNL" button in specific blocks.
	 *
	 * This method is used to add actions to specific blocks in the cart and checkout pages.
	 *
	 * @param string $block_content The content of the block.
	 * @param array  $block         The block data.
	 *
	 * @return string Modified block content with actions added.
	 */
	public function postnl_woocommerce_cart_block_do_actions( $block_content, $block ) {
		$blocks = array(
			'woocommerce/proceed-to-checkout-block',
		);
		if ( in_array( $block['blockName'], $blocks, true ) ) {
			ob_start();
			do_action( 'postnl_before_' . $block['blockName'] );
			echo $block_content;
			do_action( 'postnl_after_' . $block['blockName'] );
			$block_content = ob_get_contents();
			ob_end_clean();
		}
		return $block_content;
	}

	/**
	 * Add custom CSS for the "Fill in with PostNL" button.
	 *
	 * This method is used to add custom styles to the head section of the page.
	 *
	 * @return void
	 */
	public function add_custom_css(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$border                 = sanitize_text_field( get_option( 'postnl_button_border', '1px solid #000000' ) );
		$alignment              = sanitize_text_field( get_option( 'postnl_button_alignment', 'left' ) );
		$custom_css             = wp_strip_all_tags( get_option( 'postnl_custom_css', '' ) );
		$background_color       = sanitize_hex_color( get_option( 'postnl_button_background_color', '#ff6200' ) );
		$hover_background_color = sanitize_hex_color( get_option( 'postnl_button_hover_background_color', '#e55500' ) );

		$css = '';

		// Dynamic CSS for the PostNL button.
		$css .= '#postnl-login-button {';
		$css .= 'background-color: ' . $background_color . ';';
		$css .= 'border: ' . $border . ';';
		if ( 'center' === $alignment ) {
			$css .= 'display: block; margin-left: auto; margin-right: auto;';
		} elseif ( 'right' === $alignment ) {
			$css .= 'display: block; margin-left: auto;';
		} else {
			$css .= 'display: block;';
		}
		$css .= '}';

		// Hover effect.
		$css .= '#postnl-login-button:hover {';
		$css .= 'background-color: ' . $hover_background_color . ';';
		$css .= '}';

		// Append custom CSS from the textarea field.
		if ( ! empty( $custom_css ) ) {
			$css .= wp_strip_all_tags( $custom_css );
		}

		if ( ! empty( $css ) ) {
			echo '<style id="postnl-custom-css">' . wp_kses_post( $css ) . '</style>';
		}
	}

	/**
	 * Conditionally add hooks based on admin settings.
	 *
	 * @return void
	 */
	private function maybe_add_hooks(): void {
		$locations = array(
			'cart_before_checkout'             => array(
				'woocommerce_proceed_to_checkout',
				'postnl_before_woocommerce/proceed-to-checkout-block',
			),
			'cart_after_checkout'              => array(
				'woocommerce_after_cart_totals',
				'postnl_after_woocommerce/proceed-to-checkout-block',
			),
			'checkout_before_customer_details' => array( 'woocommerce_checkout_before_customer_details' ),
			'checkout_after_customer_details'  => array( 'woocommerce_checkout_after_customer_details' ),
			'minicart_before_buttons'          => array( 'woocommerce_widget_shopping_cart_before_buttons', 'woocommerce/mini-cart-footer-block' ),
			'minicart_after_buttons'           => array( 'woocommerce_widget_shopping_cart_after_buttons', 'woocommerce/mini-cart-footer-block' ),
		);

		foreach ( $locations as $key => $hooks ) {
			if ( $this->is_enabled_for( $key ) ) {
				foreach ( (array) $hooks as $hook ) {
					add_action( $hook, array( $this, 'render_button' ), 20 );
				}
			}
		}
	}

	/**
	 * Check if the global feature is enabled and client ID is present.
	 *
	 * @return bool
	 */
	private function is_enabled(): bool {
		return $this->settings->is_fill_in_with_postnl_enabled();
	}

	/**
	 * Check if the button should be rendered for a specific location.
	 *
	 * @param string $location The location to check.
	 *
	 * @return bool True if the button should be rendered, false otherwise.
	 */
	private function is_enabled_for( string $location ): bool {
		$mapping = array(
			'cart_before_checkout'             => array( 'postnl_cart_auto_render_button', 'postnl_cart_button_placement', 'before_checkout' ),
			'cart_after_checkout'              => array( 'postnl_cart_auto_render_button', 'postnl_cart_button_placement', 'after_checkout' ),
			'checkout_before_customer_details' => array( 'postnl_checkout_auto_render_button', 'postnl_checkout_button_placement', 'before_customer_details' ),
			'checkout_after_customer_details'  => array( 'postnl_checkout_auto_render_button', 'postnl_checkout_button_placement', 'after_customer_details' ),
			'minicart_before_buttons'          => array( 'postnl_minicart_auto_render_button', 'postnl_minicart_button_placement', 'before_buttons' ),
			'minicart_after_buttons'           => array( 'postnl_minicart_auto_render_button', 'postnl_minicart_button_placement', 'after_buttons' ),
		);

		if ( ! isset( $mapping[ $location ] ) ) {
			return false;
		}

		[ $auto_render_key, $placement_key, $expected_placement ] = $mapping[ $location ];

		return 'yes' === get_option( $auto_render_key, 'no' )
			&& get_option( $placement_key ) === $expected_placement;
	}

	/**
	 * Register REST API routes.
	 *
	 * @return void
	 */
	public function register_rest_routes(): void {
		register_rest_route(
			'postnl/v1',
			'/get-redirect-uri',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_get_redirect_uri' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle the REST API request to get the redirect URI.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_get_redirect_uri(): WP_REST_Response {

		if ( ! $this->settings->is_fill_in_with_postnl_enabled() ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => esc_html__( 'Fill in with PostNL is not enabled or Client ID is missing.', 'postnl-for-woocommerce' ),
				),
				400
			);
		}

		$code_verifier = bin2hex( random_bytes( 32 ) );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$code_challenge = rtrim( strtr( base64_encode( hash( 'sha256', $code_verifier, true ) ), '+/', '-_' ), '=' );

		if ( null === WC()->session ) {
			if ( function_exists( 'wc_load_cart' ) ) {
				wc_load_cart(); // Force session start
			}
		}
		WC()->session->set( 'postnl_code_verifier', $code_verifier );

		$redirect_uri = $this->settings->get_redirect_uri( $code_challenge );

		return new WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'redirect_uri' => $redirect_uri,
				),
			),
			200
		);
	}

	/**
	 * Enqueue scripts.
	 */
	public function enqueue_scripts(): void {
		$postnl_checkout_params = array(
			'rest_url' => rest_url( 'postnl/v1/get-redirect-uri' ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		);

		wp_enqueue_script(
			'fill-in-with-postnl',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/fill_in_with_postnl.js',
			array( 'jquery' ),
			'1.0',
			true
		);

		wp_localize_script(
			'fill-in-with-postnl',
			'postnlCheckoutParams',
			$postnl_checkout_params
		);
	}

	/**
	 * Render the "Fill in with PostNL" button using a WooCommerce template.
	 *
	 * @return void
	 */
	public function render_button(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		wc_get_template(
			'checkout/postnl-fill-in-with-button.php',
			array(),
			'',
			POSTNL_WC_PLUGIN_DIR_PATH . '/templates/'
		);
	}
}
