<?php
/**
 * Class Rest_API/Checkout file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Main;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Base
 *
 * @package PostNLWooCommerce\Rest_API
 */
class Base {
	/**
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * PostnL API URL.
	 *
	 * @var string
	 */
	public $api_url;

	/**
	 * PostnL Logger.
	 *
	 * @var Logger
	 */
	public $logger;

	/**
	 * WC Cart data.
	 *
	 * @var Array
	 */
	public $post_data;

	/**
	 * PostnL API key value from the settings.
	 *
	 * @var string
	 */
	public $api_key;

	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint;

	/**
	 * Sandbox mode or not.
	 *
	 * @var boolean
	 */
	public $is_sandbox;

	/**
	 * Class constructor.
	 *
	 * @param array $post_data WC Cart post data.
	 */
	public function __construct( $post_data ) {
		$this->settings = Settings::get_instance();
		$this->logger   = Main::get_logger();

		$this->set_post_data( $post_data );
		$this->check_api_mode();
		$this->set_api_url();
	}

	/**
	 * Method to process external data to be accepted post data variable within class.
	 *
	 * @param array $post_data External post data.
	 */
	public function set_post_data( $post_data ) {
		$this->post_data = $post_data;
	}

	/**
	 * Check the API mode from the settings.
	 */
	public function check_api_mode() {
		$this->is_sandbox = $this->settings->is_sandbox();
	}

	/**
	 * Set API Environment value.
	 */
	public function set_api_url() {
		$this->api_url  = ( true === $this->is_sandbox ) ? POSTNL_WC_SANDBOX_API_URL : POSTNL_WC_PROD_API_URL;
		$this->api_url .= $this->endpoint;
	}

	/**
	 * Get API Environment value.
	 *
	 * @return String
	 */
	public function get_api_url() {
		if ( empty( $this->api_url ) ) {
			$this->set_api_url();
		}

		return $this->api_url;
	}

	/**
	 * Set API key value.
	 */
	public function set_api_key() {
		$this->api_key = ( true === $this->is_sandbox ) ? $this->settings->get_api_key() : $this->settings->get_api_key();
	}

	/**
	 * Get API Key value.
	 *
	 * @return String
	 */
	public function get_api_key() {
		if ( empty( $this->api_key ) ) {
			$this->set_api_key();
		}

		return $this->api_key;
	}

	/**
	 * Get headers args for REST request.
	 *
	 * @return String
	 */
	public function get_headers_args() {
		return array(
			'apikey'       => $this->get_api_key(),
			'accept'       => 'application/json',
			'Content-Type' => 'application/json',
		);
	}

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array();
	}

	/**
	 * Send API request to PostNL Rest API.
	 *
	 * @throws \Exception Throw error if response has WP_Error.
	 */
	public function send_request() {
		$api_url      = esc_url( $this->get_api_url() );
		$request_args = array(
			'method'  => 'POST',
			'headers' => $this->get_headers_args(),
			'body'    => wp_json_encode( $this->compose_body_request() ),
		);

		$this->logger->write( sprintf( 'Begin send request to %1$s', $api_url ) );
		$this->logger->write( 'API Request:' );
		$this->logger->write( print_r( $request_args, true ) );

		for ( $i = 1; $i <= 5; $i++ ) {
			$response = wp_remote_request( $api_url, $request_args );

			if ( ! is_wp_error( $response ) ) {
				$this->logger->write( sprintf( 'Get response after %1$d attempts.', $i ) );

				break;
			}
		}

		if ( is_wp_error( $response ) ) {
			$this->logger->write( 'Get WP Error response:' );
			$this->logger->write( print_r( $response, true ) );

			throw new \Exception( $response->get_error_message() );
		}

		$body_response   = wp_remote_retrieve_body( $response );
		$header_response = wp_remote_retrieve_headers( $response );

		$this->logger->write( 'API Successful response:' );
		$this->logger->write( print_r( $header_response, true ) );
		$this->logger->write( print_r( $body_response, true ) );

		return $body_response;
	}
}
