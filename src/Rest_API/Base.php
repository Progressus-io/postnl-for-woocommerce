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
	 * PostnL API Method.
	 *
	 * @var string
	 */
	public $method = 'POST';

	/**
	 * PostnL Logger.
	 *
	 * @var Logger
	 */
	public $logger;

	/**
	 * API arguments.
	 *
	 * @var Array
	 */
	public $api_args;

	/**
	 * Item Info.
	 *
	 * @var Array
	 */
	public $item_info;

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
	 * @param Item_Info $item_info Set of Item_Info.
	 */
	public function __construct( $item_info ) {
		$this->settings = Settings::get_instance();
		$this->logger   = Main::get_logger();

		$this->set_item_info( $item_info );
		$this->check_api_mode();
		$this->set_api_url();
	}

	/**
	 * Method to set API arguments as a class property.
	 *
	 * @param array $api_args Set of API arguments.
	 */
	public function set_api_args( $api_args ) {
		$this->api_args = $api_args;
	}

	/**
	 * Method to set API args to an item info.
	 *
	 * @param array $api_args Set of API arguments.
	 */
	public function set_item_info( $api_args ) {
		$this->item_info = $api_args;
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

		if ( ! empty( $this->compose_url_params() ) && is_array( $this->compose_url_params() ) ) {
			$this->api_url = add_query_arg(
				$this->compose_url_params(),
				$this->api_url
			);
		}
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
		$this->api_key = ( true === $this->is_sandbox ) ? $this->settings->get_api_key_sandbox() : $this->settings->get_api_key();
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
	 * Get basic headers args for REST request.
	 *
	 * @return Array
	 */
	public function get_basic_headers_args() {
		return array(
			'apikey'       => $this->get_api_key(),
			'accept'       => 'application/json',
			'Content-Type' => 'application/json',
			'SourceSystem' => '35',
		);
	}

	/**
	 * Get headers args for REST request.
	 * We can manipulate this in child class if some class has different needs for API headers.
	 *
	 * @return Array
	 */
	public function get_headers_args() {
		return $this->get_basic_headers_args();
	}

	/**
	 * Function for composing API parameter in the URL for GET request.
	 */
	public function compose_url_params() {
		return array();
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
		$api_url      = $this->get_api_url();
		$request_args = array(
			'method'  => $this->method,
			'headers' => $this->get_headers_args(),
		);

		if ( ! empty( $this->compose_body_request() ) && is_array( $this->compose_body_request() ) ) {
			$request_args['body'] = wp_json_encode( $this->compose_body_request() );
		}

		$this->logger->write( sprintf( 'Begin send request to %1$s', $api_url ) );
		$this->logger->write( 'API Request:' );
		$this->logger->write( $request_args );

		for ( $i = 1; $i <= 5; $i++ ) {
			$response = wp_remote_request( $api_url, $request_args );

			if ( ! is_wp_error( $response ) ) {
				$this->logger->write( sprintf( 'Get response after %1$d attempts.', $i ) );

				break;
			}
		}

		if ( is_wp_error( $response ) ) {
			$this->logger->write( 'Get WP Error response:' );
			$this->logger->write( $response );

			throw new \Exception( $response->get_error_message() );
		}

		$body_response   = wp_remote_retrieve_body( $response );
		$header_response = wp_remote_retrieve_headers( $response );

		$this->logger->write( 'API Successful response:' );
		$this->logger->write( $header_response );
		$this->logger->write( $body_response );

		$this->check_response_error( $body_response );

		return json_decode( $body_response, true );
	}

	/**
	 * Check if the response value has error or not.
	 *
	 * @param Mixed $response response value from the API call.
	 *
	 * @throws \Exception Error when response has error.
	 */
	public function check_response_error( $response ) {

		if ( ! is_array( $response ) ) {
			$response = json_decode( $response, true );
		}

		if ( ! empty( $response['fault'] ) ) {
			$error_text = ! empty( $response['fault']['faultstring'] ) ? $response['fault']['faultstring'] : esc_html__( 'Unknown error!', 'postnl-for-woocommerce' );
			throw new \Exception( $error_text );
		}

		if ( ! empty( $response['Errors'] ) ) {
			$first_error = array_shift( $response['Errors'] );
			$error_text  = ! empty( $first_error['Description'] ) ? $first_error['Description'] : '';

			if ( empty( $error_text ) ) {
				$error_text = ! empty( $first_error['ErrorMsg'] ) ? $first_error['ErrorMsg'] : esc_html__( 'Unknown error!', 'postnl-for-woocommerce' );
			}

			throw new \Exception( $error_text );
		}

		if ( ! empty( $response['Error'] ) ) {
			$first_error = $response['Error'];
			$error_text  = ! empty( $first_error['ErrorMessage'] ) ? $first_error['ErrorMessage'] : esc_html__( 'Unknown error!', 'postnl-for-woocommerce' );

			throw new \Exception( $error_text );
		}
	}
}
