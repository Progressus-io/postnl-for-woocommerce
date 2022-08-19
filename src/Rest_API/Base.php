<?php
/**
 * Class Rest_API/Checkout file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

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
		$this->settings  = Settings::get_instance();
		$this->post_data = Utils::set_post_data_address( $post_data );

		$this->check_api_mode();
		$this->set_api_url();
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
}
