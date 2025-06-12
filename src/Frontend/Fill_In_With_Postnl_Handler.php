<?php
/**
 * Class Frontend/Fill_In_With_Postnl_Handler file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

defined( 'ABSPATH' ) || exit;

use PostNLWooCommerce\Shipping_Method\Fill_In_With_PostNL_Settings;

/**
 * Class Fill_In_With_Postnl_Handler
 * Handles the OAuth callback for PostNL and stores user data in WooCommerce session.
 */
class Fill_In_With_Postnl_Handler {

	/**
	 * Settings class instance.
	 *
	 * @var Fill_In_With_PostNL_Settings
	 */
	protected $settings;

	/**
	 * Logger instance.
	 *
	 * @var \WC_Logger
	 */
	protected $logger;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger   = wc_get_logger();
		$this->settings = new Fill_In_With_PostNL_Settings();
		add_action( 'template_redirect', array( $this, 'maybe_handle_oauth_callback' ) );
	}

	/**
	 * Handle PostNL OAuth callback and store user info in Woo session.
	 *
	 * @return void
	 */
	public function maybe_handle_oauth_callback(): void {
		if (
			! isset( $_GET['callback'], $_GET['code'] ) ||
			'postnl' !== $_GET['callback']
		) {
			return;
		}

		// Redirect to checkout page if user lands on non-checkout URL.
		if ( ! is_checkout() ) {
			$redirect_url = wc_get_checkout_url() . '?callback=postnl&code=' . rawurlencode( wp_unslash( $_GET['code'] ) );
			if ( isset( $_GET['state'] ) ) {
				$redirect_url .= '&state=' . rawurlencode( wp_unslash( $_GET['state'] ) );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$code     = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$verifier = isset( $_SESSION['postnl_code_verifier'] ) ? sanitize_text_field( wp_unslash( $_SESSION['postnl_code_verifier'] ) ) : null;

		if ( ! $verifier ) {
			$this->logger->error( 'Login session expired. Please try again.', array( 'source' => 'postnl-for-woocommerce' ) );
			wc_add_notice( esc_html__( 'Login session expired. Please try again.', 'postnl-for-woocommerce' ), 'error' );
			return;
		}

		$token_response = wp_remote_post(
			'https://dil-login.postnl.nl/oauth2/token/',
			array(
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
				'body'    => array(
					'grant_type'    => 'authorization_code',
					'code'          => $code,
					'redirect_uri'  => site_url( '/checkout/?callback=postnl' ),
					'client_id'     => $this->settings->get_client_id(),
					'code_verifier' => $verifier,
					'scope'         => 'base',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			$this->logger->error( 'PostNL Token Request Error: ' . $token_response->get_error_message(), array( 'source' => 'postnl-for-woocommerce' ) );
			wc_add_notice(
				sprintf(
					/* translators: %s is the error message from PostNL */
					esc_html__( 'PostNL Token Request Error: %s', 'postnl-for-woocommerce' ),
					$token_response->get_error_message()
				),
				'error'
			);
			return;
		}

		$body         = json_decode( wp_remote_retrieve_body( $token_response ), true );
		$access_token = $body['access_token'] ?? null;

		if ( ! $access_token ) {
			$this->logger->error( 'PostNL: Access token not found in response', array( 'source' => 'postnl-for-woocommerce' ) );
			wc_add_notice( esc_html__( 'PostNL: Access token not found', 'postnl-for-woocommerce' ), 'error' );
			return;
		}

		$user_info_response = wp_remote_get(
			'https://dil-login.postnl.nl/api/user_info/',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $access_token,
					'Accept'        => 'application/json',
				),
			)
		);

		if ( is_wp_error( $user_info_response ) ) {
			$this->logger->error( 'PostNL User Info Error: ' . $user_info_response->get_error_message(), array( 'source' => 'postnl-for-woocommerce' ) );
			wc_add_notice(
				sprintf(
					/* translators: %s is the error message from PostNL */
					esc_html__( 'PostNL User Info Error: %s', 'postnl-for-woocommerce' ),
					$user_info_response->get_error_message()
				),
				'error'
			);
			return;
		}

		$user_data = json_decode( wp_remote_retrieve_body( $user_info_response ), true );

		if (
			empty( $user_data ) ||
			empty( $user_data['person'] ) ||
			empty( $user_data['primaryAddress'] )
		) {
			wc_add_notice( esc_html__( 'Incomplete user data.', 'postnl-for-woocommerce' ), 'error' );
			return;
		}

		WC()->session->set( 'postnl_user_data', $user_data );

		// Redirect to clean URL (remove callback and code).
		wp_safe_redirect( remove_query_arg( array( 'callback', 'code', 'state' ) ) );
		exit;
	}
}
