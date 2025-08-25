<?php
/**
 * Class Frontend/Fill_In_With_Postnl_Handler file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

defined( 'ABSPATH' ) || exit;

use PostNLWooCommerce\Main;
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
	 * PostnL Logger.
	 *
	 * @var Logger
	 */
	protected $logger;
	/**
	 * Session variable key for user data.
	 *
	 * @var string
	 */
	private static string $session_user_data_key = 'user_data';
	/**
	 * Session variable key for verifier.
	 *
	 * @var string
	 */
	private static string $session_verifier_key = 'code_verifier';

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->logger   = Main::get_logger();
		$this->settings = new Fill_In_With_PostNL_Settings();
		add_action( 'wp_ajax_nopriv_get_postnl_user_info', array( $this, 'handle_postnl_user_info' ) );
		add_action( 'wp_ajax_get_postnl_user_info', array( $this, 'handle_postnl_user_info' ) );
		add_action( 'template_redirect', array( $this, 'maybe_handle_oauth_callback' ) );
	}

	/**
	 * Handle the AJAX request to get PostNL user info.
	 *
	 * This method retrieves user data stored in the WooCommerce session and returns it as a JSON response.
	 *
	 * @return void
	 */
	public function handle_postnl_user_info(): void {
		// Check for nonce verification if needed.
		if ( isset( $_REQUEST['nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['nonce'] ) ), 'postnl_user_info' ) ) {
			wp_send_json_error( esc_html__( 'Invalid nonce.', 'postnl-for-woocommerce' ) );
		}
		if ( ! $this->settings->is_fill_in_with_postnl_enabled() ) {
			wp_send_json_error( esc_html__( 'Fill in with PostNL is not enabled or Client ID is missing.', 'postnl-for-woocommerce' ) );
		}

		$data = WC()->session->get( POSTNL_SETTINGS_ID . self::$session_user_data_key );
		if ( ! $data ) {
			wp_send_json_error( esc_html__( 'No user data', 'postnl-for-woocommerce' ) );
		}
		wp_send_json_success( $data );
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
			$redirect_url = wc_get_checkout_url() . '?callback=postnl&code=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['code'] ) ) );
			if ( isset( $_GET['state'] ) ) {
				$redirect_url .= '&state=' . rawurlencode( sanitize_text_field( wp_unslash( $_GET['state'] ) ) );
			}
			wp_safe_redirect( $redirect_url );
			exit;
		}

		$code     = sanitize_text_field( wp_unslash( $_GET['code'] ) );
		$verifier = get_transient( 'postnl_' . self::$session_verifier_key );

		if ( ! $verifier ) {
			$this->logger->write( 'Login session expired. Please try again.' );
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
					'redirect_uri'  => home_url( '/checkout/default/details/?callback=postnl' ),
					'client_id'     => $this->settings->get_client_id(),
					'code_verifier' => $verifier,
					'scope'         => 'base',
				),
			)
		);

		if ( is_wp_error( $token_response ) ) {
			$this->logger->write( sprintf( 'PostNL Token Request Error: %1$s', $token_response->get_error_message() ) );
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
			$this->logger->write( 'PostNL: Access token not found in response' );
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
			$this->logger->write( sprintf( 'PostNL User Info Error: %1$s', $user_info_response->get_error_message() ) );
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
			$this->logger->write( 'Incomplete user data received from PostNL.' );
			wc_add_notice( esc_html__( 'Incomplete user data.', 'postnl-for-woocommerce' ), 'error' );
			return;
		}

		$person          = $user_data['person'];
		$primary_address = $user_data['primaryAddress'];

		$country_code = $this->get_country_code_by_name( $primary_address['countryName'] ?? '' ) ?? 'NL';

		$prepared_user_data = array(
			'person'         => array(
				'givenName'  => $person['givenName'] ?? '',
				'familyName' => $person['familyName'] ?? '',
				'email'      => $person['email'] ?? '',
			),
			'primaryAddress' => array(
				'streetName'          => $primary_address['streetName'] ?? '',
				'houseNumber'         => $primary_address['houseNumber'] ?? '',
				'houseNumberAddition' => $primary_address['houseNumberAddition'] ?? '',
				'postalCode'          => $primary_address['postalCode'] ?? '',
				'cityName'            => $primary_address['cityName'] ?? '',
				'countryName'         => $country_code,
			),
		);

		WC()->session->set( POSTNL_SETTINGS_ID . self::$session_user_data_key, $prepared_user_data );

		// Redirect to clean URL (remove callback and code).
		wp_safe_redirect( remove_query_arg( array( 'code', 'state' ) ) );
		exit;
	}

	/**
	 * Get country code by country name.
	 *
	 * @param string $name Country name.
	 *
	 * @return string|null Country code or null if not found.
	 */
	private function get_country_code_by_name( string $name ): ?string {
		$countries = WC()->countries->get_countries();
		$name      = strtolower( $name );

		foreach ( $countries as $code => $country_name ) {
			if ( strtolower( $country_name ) === $name ) {
				return $code;
			}
		}

		return null;
	}
}
