<?php
/**
 * Class Main file.
 *
 * @package Progressus\PostNLWooCommerce
 */

namespace Progressus\PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Main
 *
 * @package Progressus\PostNLWooCommerce
 */
class Main {
	/**
	 * Version of this plugin.
	 *
	 * @var _version
	 */
	private $version = '1.0.0';

	/**
	 * Instance to call certain functions globally within the plugin
	 *
	 * @var _instance
	 */
	protected static $instance = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_plugin' ), 0 );
	}

	/**
	 * Main PostNL for WooCommerce.
	 *
	 * Ensures only one instance is loaded or can be loaded.
	 *
	 * @static
	 * @return self Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Define WC Constants.
	 */
	private function define_constants() {
		$upload_dir = wp_upload_dir();

		// Path related defines.
		$this->define( 'POSTNL_WC_PLUGIN_FILE', POSTNL_WC_PLUGIN_FILE );
		$this->define( 'POSTNL_WC_PLUGIN_BASENAME', plugin_basename( POSTNL_WC_PLUGIN_FILE ) );
		$this->define( 'POSTNL_WC_PLUGIN_DIR_PATH', untrailingslashit( plugin_dir_path( POSTNL_WC_PLUGIN_FILE ) ) );
		$this->define( 'POSTNL_WC_PLUGIN_DIR_URL', untrailingslashit( plugins_url( '/', POSTNL_WC_PLUGIN_FILE ) ) );

		$this->define( 'POSTNL_WC_VERSION', $this->version );

		$this->define( 'POSTNL_WC_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/' );
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin() {
		// Checks if WooCommerce is installed.
		if ( class_exists( 'WooCommerce' ) ) {

			$this->define_constants();
			$this->init_hooks();
		} else {
			// Throw an admin error informing the user this plugin needs WooCommerce to function.
			add_action( 'admin_notices', array( $this, 'notice_wc_required' ) );
		}

	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		new Order();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 1 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );

		// Locate woocommerce template.
		add_filter( 'woocommerce_locate_template', array( $this, 'woocommerce_locate_template' ), 20, 3 );
	}

	/**
	 * Localisation.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'postnl-for-woocommerce', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Load or enqueue the css file.
	 */
	public function enqueue_styles() {

	}

	/**
	 * Enqueue all scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_script( 'mc-customization-frontend', POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/frontend.js', array( 'jquery' ), '1.0.0', true );
	}

	/**
	 * Add PostNL Shipping Method to WooCommerce.
	 *
	 * @param array( WC_Shipping_Method ) $shipping_methods Array of existing WC shipping methods.
	 *
	 * @return array( WC_Shipping_Method )
	 */
	public function add_shipping_method( $shipping_methods ) {
		$shipping_methods['pr_postnl'] = new Method\PostNL();
		return $shipping_methods;
	}

	/**
	 * Define constant if not already set.
	 *
	 * @param  string      $name Name of constant variable.
	 * @param  string|bool $value Value of constant variable.
	 */
	public function define( $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value );
		}
	}

	/**
	 * Admin error notifying user that WC is required.
	 */
	public function notice_wc_required() {
		?>
		<div class="error">
			<p><?php esc_html_e( 'PostNL plugin requires WooCommerce to be installed and activated!', 'postnl-for-woocommerce' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Manipulate the WooCommerce template file location.
	 *
	 * @param string $template      Template filename before manipulated.
	 * @param string $template_name Template filename to be manipulated.
	 * @param string $template_path Template new path.
	 *
	 * @return String
	 */
	public function woocommerce_locate_template( $template, $template_name, $template_path ) {

		global $woocommerce;

		$_template = $template;

		if ( ! $template_path ) {
			$template_path = $woocommerce->template_url;
		}

		$plugin_path = untrailingslashit( POSTNL_WC_PLUGIN_DIR_PATH ) . '/templates/';

		// Look within passed path within the theme - this is priority.
		$template = locate_template(
			array(
				$template_path . $template_name,
				$template_name,
			)
		);

		// Modification: Get the template from this plugin, if it exists.
		if ( ! $template && file_exists( $plugin_path . $template_name ) ) {
			$template = $plugin_path . $template_name;
		}

		// Use default template.

		if ( ! $template ) {
			$template = $_template;
		}

		// Return what we found.
		return $template;
	}
}
