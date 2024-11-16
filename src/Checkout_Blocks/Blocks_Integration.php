<?php

namespace PostNLWooCommerce\Checkout_Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Frontend\Dropoff_Points;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for integrating with WooCommerce Blocks
 */
class Blocks_Integration implements IntegrationInterface {

	/**
	 * The name of the integration.
	 *
	 * @return string
	 */
	public function get_name() {
		return 'postnl-for-woocommerce-blocks';
	}

	/**
	 * When called invokes any initialization/setup for the integration.
	 */
	public function initialize() {
		$this->register_scripts_and_styles();
		$this->register_ajax_actions();
	}

	/**
	 * Registers scripts and styles for both editor and frontend.
	 */
	private function register_scripts_and_styles() {
		// Register main integration script and style
		$this->register_main_integration();

		// Register block editor scripts
		$this->register_block_script(
			'postnl-container-editor',
			'/build/postnl-container.js',
			'/build/postnl-container.asset.php'
		);

		// Register frontend scripts for blocks
		$this->register_frontend_script(
			'postnl-container-frontend',
			'/build/postnl-container-frontend.js',
			'/build/postnl-container-frontend.asset.php'
		);

		// Register block styles
		$this->register_styles();
	}

	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	private function register_main_integration() {
		$script_path = '/build/index.js';
		$style_path  = '/build/style-index.css';

		$script_url = POSTNL_WC_PLUGIN_DIR_URL . $script_path;
		$style_url  = POSTNL_WC_PLUGIN_DIR_URL . $style_path;

		$script_asset_path = POSTNL_WC_PLUGIN_DIR_PATH . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => [],
				'version'      => $this->get_file_version( $script_path ),
			];

		wp_enqueue_style(
			'postnl-delivery-day-integration',
			$style_url,
			[],
			$this->get_file_version( $style_path )
		);

		wp_register_script(
			'postnl-delivery-day-integration',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_set_script_translations(
			'postnl-delivery-day-integration',
			'postnl-for-woocommerce-blocks',
			POSTNL_WC_PLUGIN_DIR_PATH . '/languages'
		);
	}

	/**
	 * Registers block styles for the block editor.
	 */
	private function register_styles() {
		$block_style_path = '/build/style-postnl-delivery-day.css';
		$block_style_url  = POSTNL_WC_PLUGIN_DIR_URL . $block_style_path;

		wp_enqueue_style(
			'postnl-delivery-day',
			$block_style_url,
			[],
			$this->get_file_version( $block_style_path )
		);
	}

	/**
	 * Helper method to register block editor scripts.
	 *
	 * @param string $handle Script handle name.
	 * @param string $script_path Path to the JS file.
	 * @param string $asset_path Path to the asset file.
	 */
	private function register_block_script( $handle, $script_path, $asset_path ) {
		$script_url = POSTNL_WC_PLUGIN_DIR_URL . $script_path;
		$asset_file = POSTNL_WC_PLUGIN_DIR_PATH . $asset_path;
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => $this->get_file_version( $script_path ),
		];

		wp_register_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			$handle,
			'postnl-for-woocommerce-blocks',
			POSTNL_WC_PLUGIN_DIR_PATH . '/languages'
		);
	}

	/**
	 * Helper method to register frontend scripts.
	 *
	 * @param string $handle Script handle name.
	 * @param string $script_path Path to the JS file.
	 * @param string $asset_path Path to the asset file.
	 */
	private function register_frontend_script( $handle, $script_path, $asset_path ) {
		$script_url = POSTNL_WC_PLUGIN_DIR_URL . $script_path;
		$asset_file = POSTNL_WC_PLUGIN_DIR_PATH . $asset_path;
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => [],
			'version'      => $this->get_file_version( $script_path ),
		];

		wp_enqueue_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			$handle,
			'postnl-for-woocommerce-blocks',
			POSTNL_WC_PLUGIN_DIR_PATH . '/languages'
		);
	}

	/**
	 * Registers AJAX actions.
	 */
	private function register_ajax_actions() {
		add_action( 'wp_ajax_postnl_set_checkout_post_data', [ $this, 'handle_set_checkout_post_data' ] );
		add_action( 'wp_ajax_nopriv_postnl_set_checkout_post_data', [ $this, 'handle_set_checkout_post_data' ] );
		add_action( 'wp_ajax_postnl_get_delivery_options', [ $this, 'handle_get_delivery_options' ] );
		add_action( 'wp_ajax_nopriv_postnl_get_delivery_options', [ $this, 'handle_get_delivery_options' ] );
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 *
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		$file_path = POSTNL_WC_PLUGIN_DIR_PATH . $file;
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file_path ) ) {
			return filemtime( $file_path );
		}

		return POSTNL_WC_VERSION;
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [
			'postnl-delivery-day-integration',
			'postnl-container-frontend',
		];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [
			'postnl-delivery-day-integration',
			'postnl-container-editor',
		];
	}


	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$letterbox = Utils::is_eligible_auto_letterbox( WC()->cart );

		return [
			'pluginUrl' => POSTNL_WC_PLUGIN_DIR_URL,
			'ajax_url'  => admin_url( 'admin-ajax.php' ),
			'nonce'     => wp_create_nonce( 'postnl_delivery_day_nonce' ),
			'letterbox' => $letterbox, // Add the letterbox status here
		];
	}

	/**
	 * Handle AJAX request to set checkout post data and return updated delivery options.
	 */
	public function handle_set_checkout_post_data() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
			wp_die();
		}

		// Check if data is provided
		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( [ 'message' => 'No data provided.' ], 400 );
			wp_die();
		}

		// Sanitize data
		$sanitized_data = array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) );

		// Validation
		$settings         = new Settings();
		$shipping_country = isset( $sanitized_data['shipping_country'] ) ? $sanitized_data['shipping_country'] : '';

		if ( 'NL' !== $shipping_country ) {
			// Clear the session data
			WC()->session->__unset( 'postnl_checkout_post_data' );

			// Return empty response to notify frontend to clear options
			wp_send_json_success( [
				'message'          => 'No delivery options available.',
				'delivery_options' => [],
			], 200 );
			wp_die();
		}

		// Create Container instance
		$container = new Container();

		// Validate the address if validation is enabled
		if ( $settings->is_validate_nl_address_enabled() ) {

			// Check if shipping_postcode is provided
			if ( empty( $sanitized_data['shipping_postcode'] ) || empty( $sanitized_data['shipping_house_number'] ) ) {

				// Clear the session data
				WC()->session->__unset( 'postnl_checkout_post_data' );

				// Return empty response to notify frontend to clear options
				wp_send_json_success( [
					'message'          => 'No delivery options available due to missing postcode or house number.',
					'delivery_options' => [],
				], 200 );
				wp_die();
			}

			// Call the validated_address method
			$container->validated_address( $sanitized_data );

			// Get the validated address from the session
			$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );


			// Include the validated address in the response data
			$response_data['validated_address'] = $validated_address;
		}

		// Store data in WooCommerce session
		WC()->session->set( 'postnl_checkout_post_data', $sanitized_data );

		// Fetch updated delivery options
		try {
			$delivery_day     = new Delivery_Day();
			$checkout_data    = $container->get_checkout_data( $sanitized_data );
			$delivery_options = $delivery_day->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );

			$response_data['message']          = 'Data saved successfully.';
			$response_data['delivery_options'] = isset( $delivery_options['delivery_options'] ) ? $delivery_options['delivery_options'] : [];

			wp_send_json_success( $response_data, 200 );
		} catch ( \Exception $e ) {
		}

		wp_die();
	}

	/**
	 * Handle AJAX request to fetch updated delivery options.
	 */
	public function handle_get_delivery_options() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( [ 'message' => 'Invalid nonce' ], 400 );
			wp_die();
		}

		// Retrieve post_data from WooCommerce session
		$order_data = WC()->session->get( 'postnl_checkout_post_data' );


		$settings         = new Settings();
		$shipping_country = isset( $order_data['shipping_country'] ) ? $order_data['shipping_country'] : '';

		if ( empty( $order_data ) || ! is_array( $order_data ) || 'NL' !== $shipping_country ) {

			// Clear the session data
			WC()->session->__unset( 'postnl_checkout_post_data' );

			// Return empty response to notify frontend to clear options
			wp_send_json_success(
				[
					'delivery_options' => [],
					'dropoff_options'  => [],
				], 200 );
			wp_die();
		}
		if ( $settings->is_validate_nl_address_enabled() ) {

			// Check if shipping_postcode is provided
			if ( empty( $order_data['shipping_postcode'] ) || empty( $order_data['shipping_house_number'] ) ) {

				// Clear the session data
				WC()->session->__unset( 'postnl_checkout_post_data' );

				// Return empty response to notify frontend to clear options
				wp_send_json_success(
					[
						'delivery_options' => [],
						'dropoff_options'  => [],
					], 200 );
				wp_die();

			}

		}


		try {
			$container        = new Container();
			$delivery_day     = new Delivery_Day();
			$dropoff          = new Dropoff_Points();
			$checkout_data    = $container->get_checkout_data( $order_data );
			$delivery_options = $delivery_day->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );
			$dropoff_options  = $dropoff->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );

			wp_send_json_success(
				[
					'delivery_options' => isset( $delivery_options['delivery_options'] ) ? $delivery_options['delivery_options'] : [],
					'dropoff_options'  => isset( $dropoff_options['dropoff_options'] ) ? $dropoff_options['dropoff_options'] : [],
				], 200 );
		} catch ( \Exception $e ) {

			wp_send_json_error( [ 'message' => 'Failed to fetch delivery options.' ], 500 );
		}

		wp_die();
	}

}
