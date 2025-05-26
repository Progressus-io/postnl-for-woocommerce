<?php
/**
 * Class Frontend/Fill_In_With_Postnl file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

defined( 'ABSPATH' ) || exit;

/**
 * Class Fill_In_With_Postnl
 * Renders the "Fill in with PostNL" button based on admin settings.
 */
class Fill_In_With_Postnl {

	/**
	 * Constructor.
	 * Initializes the button rendering based on admin settings.
	 */
	public function __construct() {
		$this->maybe_add_hooks();
	}

	/**
	 * Conditionally add hooks based on admin settings.
	 *
	 * Return void
	 */
	private function maybe_add_hooks(): void {
		$locations = array(
			'cart_before_checkout'             => 'woocommerce_proceed_to_checkout',
			'cart_after_checkout'              => 'woocommerce_after_cart_totals',
			'checkout_before_customer_details' => 'woocommerce_checkout_before_customer_details',
			'checkout_after_customer_details'  => 'woocommerce_checkout_after_customer_details',
			'minicart_before_buttons'          => 'woocommerce_widget_shopping_cart_before_buttons',
			'minicart_after_buttons'           => 'woocommerce_widget_shopping_cart_after_buttons',
		);

		foreach ( $locations as $key => $hook ) {
			if ( $this->is_enabled_for( $key ) ) {
				add_action( $hook, array( $this, 'render_button' ), 20 );
			}
		}
	}

	/**
	 * Check if the global feature is enabled and client ID is present.
	 *
	 * Return bool
	 */
	private function is_enabled(): bool {
		$is_enabled = 'yes' === get_option( 'postnl_enable_fill_in_with', 'no' );
		$client_id  = sanitize_text_field( get_option( 'postnl_fill_in_with_client_id' ) );

		return $is_enabled && ! empty( $client_id );
	}

	/**
	 * Helper to check if button rendering is enabled for a specific position.
	 *
	 * @param string $location The location to check.
	 *
	 * Return bool
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
	 * Render the "Fill in with PostNL" button using a WooCommerce template.
	 *
	 * Return void
	 */
	public function render_button(): void {
		if ( ! $this->is_enabled() ) {
			return;
		}

		$client_id     = sanitize_text_field( get_option( 'postnl_fill_in_with_client_id' ) );
		$redirect_base = 'https://dil-login.postnl.nl/oauth2/login_options/';
		$callback_url  = home_url( '/checkout/default/details/?callback=postnl' );
		$redirect_uri  = esc_url( $redirect_base . '?client_id=' . $client_id . '&redirect_uri=' . rawurlencode( $callback_url ) . '&response_type=code&scope=base&code_challenge=&code_challenge_method=S256' );

		wc_get_template(
			'checkout/postnl-fill-in-with-button.php',
			array(
				'redirect_uri' => $redirect_uri,
			),
			'',
			POSTNL_WC_PLUGIN_DIR_PATH . '/templates/'
		);
	}
}
