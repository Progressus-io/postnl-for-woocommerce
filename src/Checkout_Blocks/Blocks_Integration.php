<?php

namespace PostNLWooCommerce\Checkout_Blocks;

use \Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Frontend\Dropoff_Points;
use PostNLWooCommerce\Frontend\Container;

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
		$this->register_postnl_for_wocommerce_editor_scripts();
		$this->register_postnl_for_wocommerce_editor_styles();
		$this->register_main_integration();

		// Register AJAX actions
		add_action( 'wp_ajax_postnl_set_checkout_post_data', array( $this, 'handle_set_checkout_post_data' ) );
		add_action( 'wp_ajax_nopriv_postnl_set_checkout_post_data', array( $this, 'handle_set_checkout_post_data' ) );
		add_action( 'wp_ajax_postnl_get_delivery_options', array( $this, 'handle_get_delivery_options' ) );
		add_action( 'wp_ajax_nopriv_postnl_get_delivery_options', array( $this, 'handle_get_delivery_options' ) );

	}

	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	private function register_main_integration() {
		$script_path = '/build/index.js';
		$style_path  = '/build/style-index.css';

		$script_url = POSTNL_WC_PLUGIN_DIR_URL . $script_path;
		$style_url  = POSTNL_WC_PLUGIN_DIR_URL . $style_path;

		$script_asset_path = dirname( __FILE__ ) . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: [
				'dependencies' => array(),
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
			'postnl-for-woocommerce-blocks',
			'postnl-for-woocommerce-blocks',
			dirname( __FILE__ ) . '/languages'
		);
	}

	/**
	 * Registers block editor and frontend scripts.
	 */
	public function register_postnl_for_wocommerce_editor_scripts() {
		// Register postnl-delivery-day block
		$this->register_block_script(
			'postnl-delivery-day-editor',
			'/build/postnl-delivery-day.js',
			'/build/postnl-delivery-day.asset.php'
		);
		$this->register_block_script(
			'postnl-container-editor',
			'/build/postnl-container.js',
			'/build/postnl-container.asset.php'
		);
		// Register postnl-dropoff-points block
		$this->register_block_script(
			'postnl-dropoff-points-editor',
			'/build/postnl-dropoff-points.js',
			'/build/postnl-dropoff-points.asset.php'
		);

		// Register postnl-billing-address block
		$this->register_block_script(
			'postnl-billing-address-editor',
			'/build/postnl-billing-address.js',
			'/build/postnl-billing-address.asset.php'
		);

		// Register postnl-shipping-address block
		$this->register_block_script(
			'postnl-shipping-address-editor',
			'/build/postnl-shipping-address.js',
			'/build/postnl-shipping-address.asset.php'
		);

		// Register frontend scripts for all blocks
		$this->register_frontend_script(
			'postnl-delivery-day-frontend',
			'/build/postnl-delivery-day-frontend.js',
			'/build/postnl-delivery-day-frontend.asset.php'
		);


		$this->register_frontend_script(
			'postnl-container-frontend',
			'/build/postnl-container-frontend.js',
			'/build/postnl-container-frontend.asset.php'
		);

		$this->register_frontend_script(
			'postnl-dropoff-points-frontend',
			'/build/postnl-dropoff-points-frontend.js',
			'/build/postnl-dropoff-points-frontend.asset.php'
		);

		$this->register_frontend_script(
			'postnl-billing-address-frontend',
			'/build/postnl-billing-address-frontend.js',
			'/build/postnl-billing-address-frontend.asset.php'
		);

		$this->register_frontend_script(
			'postnl-shipping-address-frontend',
			'/build/postnl-shipping-address-frontend.js',
			'/build/postnl-shipping-address-frontend.asset.php'
		);
	}

	/**
	 * Registers block styles for the block editor.
	 */
	public function register_postnl_for_wocommerce_editor_styles() {
		$block_style_path = '/build/style-postnl-delivery-day.css';
		$block_style_url  = POSTNL_WC_PLUGIN_DIR_URL . $block_style_path;

		wp_enqueue_style(
			'postnl-delivery-day',
			$block_style_url,
			[],
			$this->get_file_version( $block_style_path )
		);

		$extra_style_path = '/build/style-postnl-dropoff-points.css';
		$extra_style_url  = POSTNL_WC_PLUGIN_DIR_URL . $extra_style_path;

		wp_enqueue_style(
			'postnl-dropoff-points',
			$extra_style_url,
			[],
			$this->get_file_version( $extra_style_path )
		);
		$this->localize_scripts();
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
		$asset_file = dirname( __FILE__ ) . $asset_path;
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => array(),
			'version'      => $this->get_file_version( $script_path ),
		];

		wp_register_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'postnl-for-woocommerce-blocks', dirname( __FILE__ ) . '/languages' );
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
		$asset_file = dirname( __FILE__ ) . $asset_path;
		$asset      = file_exists( $asset_file ) ? require $asset_file : [
			'dependencies' => array(),
			'version'      => $this->get_file_version( $script_path ),
		];

		wp_enqueue_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations( $handle, 'postnl-for-woocommerce-blocks', dirname( __FILE__ ) . '/languages' );
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param string $file Local path to the file.
	 *
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}

		return POSTNL_WC_VERSION;
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return [ 'postnl-delivery-day-integration', 'postnl-delivery-day-frontend' ];
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return [ 'postnl-delivery-day-integration', 'postnl-delivery-day-editor' ];
	}

	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$data = [
			'postnl-delivery-day-active' => true,
		];

		return $data;
	}


	public function localize_scripts() {
		try {
			// Prepare variables for use.
			$plugin_url = POSTNL_WC_PLUGIN_DIR_URL;
			// Instantiate Delivery_Day to get delivery options based on post_data


			if (!empty($session_data) && is_array($session_data)) {
				$order_data = $session_data;
			}



		} catch (Exception $e) {
			$plugin_url = POSTNL_WC_PLUGIN_DIR_URL;
		}

		// Prepare the data to be localized into JavaScript.
		$localize_data = array(
			'pluginUrl'       => $plugin_url,
			'ajax_url'        => admin_url('admin-ajax.php'),
			'nonce'           => wp_create_nonce('postnl_delivery_day_nonce'),
		);


		// Localize scripts for frontend
		wp_localize_script(
			'postnl-delivery-day-frontend',
			'postnl_ajax_object',
			$localize_data
		);

		// Optionally, localize for editor scripts if needed
		wp_localize_script(
			'postnl-delivery-day-editor',
			'postnl_ajax_object',
			$localize_data
		);
	}


	/**
	 * Handle AJAX request to set checkout post data and return updated delivery options.
	 */
	public function handle_set_checkout_post_data() {
		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
			wp_die();
		}

		// Check if data is provided
		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( array( 'message' => 'No data provided.' ), 400 );
			wp_die();
		}

		// Sanitize data
		$sanitized_data = array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) );

		// Store data in WooCommerce session
		WC()->session->set( 'postnl_checkout_post_data', $sanitized_data );

		// Fetch updated delivery options
		try {
			$con = new Container();
			$dd  = new Delivery_Day();
			$checkout_data = $con->get_checkout_data( $sanitized_data );
			$delivery_options = $dd->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );

			wp_send_json_success( array(
				'message'          => 'Data saved successfully.',
				'delivery_options' => $delivery_options['DeliveryOptions'],
			), 200 );
		} catch ( Exception $e ) {
			error_log( 'Error fetching delivery options: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to fetch delivery options.' ), 500 );
		}

		wp_die();
	}

	/**
	 * Handle AJAX request to fetch updated delivery options.
	 */
	public function handle_get_delivery_options() {

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
			wp_die();
		}

		// Retrieve post_data from WooCommerce session
		$order_data = WC()->session->get( 'postnl_checkout_post_data' );

		if ( empty( $order_data ) || ! is_array( $order_data ) ) {
			wp_send_json_error( array( 'message' => 'No checkout data found.' ), 400 );
			wp_die();
		}

		try {
			$con              = new Container();
			$dd               = new Delivery_Day();
			$dropoff          = new Dropoff_Points();
			$checkout_data    = $con->get_checkout_data( $order_data );
			$delivery_options = $dd->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );
			$dropoff_options  = $dropoff->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );
			wp_send_json_success(
				array(
					'delivery_options' => isset( $delivery_options['delivery_options'] ) ? $delivery_options['delivery_options'] : [],
					'dropoff_options'  => isset( $dropoff_options['dropoff_options'] ) ? $dropoff_options['dropoff_options'] : [],
				), 200 );
		} catch ( Exception $e ) {
			error_log( 'Error fetching delivery options: ' . $e->getMessage() );
			wp_send_json_error( array( 'message' => 'Failed to fetch delivery options.' ), 500 );
		}

		wp_die();
	}



}
