<?php

namespace PostNLWooCommerce\Checkout_Blocks;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;
use PostNLWooCommerce\Shipping_Method\Fill_In_With_PostNL_Settings;
use PostNLWooCommerce\Utils;
use function PostNLWooCommerce\postnl;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for integrating with WooCommerce Blocks
 */
class Blocks_Integration implements IntegrationInterface {

	/**
	 * Settings class instance.
	 *
	 * @var Fill_In_With_PostNL_Settings
	 */
	protected $fill_in_with_settings;

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
		$this->fill_in_with_settings = new Fill_In_With_PostNL_Settings();
		$this->register_scripts_and_styles();
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
		$this->register_block_script(
			'postnl-fill-in-with-editor',
			'/build/postnl-fill-in-with-editor.js',
			'/build/postnl-fill-in-with-editor.asset.php'
		);

		// Register frontend scripts for blocks
		$this->register_frontend_script(
			'postnl-container-frontend',
			'/build/postnl-container-frontend.js',
			'/build/postnl-container-frontend.asset.php'
		);

		$this->register_frontend_script(
			'postnl-fill-in-with-frontend',
			'/build/postnl-fill-in-with-frontend.js',
			'/build/postnl-fill-in-with-frontend.asset.php'
		);
	}

	/**
	 * Registers the main JS file required to add filters and Slot/Fills.
	 */
	private function register_main_integration() {
		$script_path = '/build/index.js';

		$script_url = POSTNL_WC_PLUGIN_DIR_URL . $script_path;

		$script_asset_path = POSTNL_WC_PLUGIN_DIR_PATH . '/build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
			? require $script_asset_path
			: array(
				'dependencies' => array(),
				'version'      => $this->get_file_version( $script_path ),
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
			'postnl-for-woocommerce',
			POSTNL_WC_PLUGIN_DIR_PATH . '/languages'
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
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => $this->get_file_version( $script_path ),
		);

		wp_register_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_set_script_translations(
			$handle,
			'postnl-for-woocommerce',
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
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array(),
			'version'      => $this->get_file_version( $script_path ),
		);

		wp_enqueue_script(
			$handle,
			$script_url,
			$asset['dependencies'],
			$asset['version'],
			true
		);

		if ( 'postnl-fill-in-with-frontend' === $handle ) {
			$selected_location = $this->fill_in_with_settings->get_button_placement( 'checkout' );
			if ( 'after_customer_details' === $selected_location ) {
				$selected_location = 'woocommerce/checkout-shipping-address-block';
			} else {
				$selected_location = 'woocommerce/checkout-contact-information-block';
			}
			wp_localize_script(
				$handle,
				'postnlSettings',
				array(
					'blockLocation' => get_option( 'postnl_block_location', $selected_location ),
				)
			);
		}

		wp_set_script_translations(
			$handle,
			'postnl-for-woocommerce',
			POSTNL_WC_PLUGIN_DIR_PATH . '/languages'
		);
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
		return array(
			'postnl-delivery-day-integration',
			'postnl-container-frontend',
			'postnl-fill-in-with-frontend',
		);
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array(
			'postnl-delivery-day-integration',
			'postnl-container-editor',
			'postnl-fill-in-with-editor',
		);
	}


	/**
	 * An array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		$letterbox = Utils::is_cart_eligible_auto_letterbox( WC()->cart );
		$settings  = postnl()->get_shipping_settings();

		return array(
			'pluginUrl'                    => POSTNL_WC_PLUGIN_DIR_URL,
			'ajax_url'                     => admin_url( 'admin-ajax.php' ),
			'nonce'                        => wp_create_nonce( 'postnl_delivery_day_nonce' ),
			'letterbox'                    => $letterbox,
			'is_nl_address_enabled'        => $settings->is_reorder_nl_address_enabled(),
			'is_pickup_points_enabled'     => $settings->is_pickup_points_enabled(),
			'fill_in_with_postnl_settings' => array(
				'is_fill_in_with_postnl_enabled' => $this->fill_in_with_settings->is_fill_in_with_postnl_enabled(),
				'redirect_uri'                   => $this->fill_in_with_settings->get_redirect_uri(),
			),
		);
	}
}
