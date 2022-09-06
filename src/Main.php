<?php
/**
 * Class Main file.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Main
 *
 * @package PostNLWooCommerce
 */
class Main {
	/**
	 * Version of this plugin.
	 *
	 * @var _version
	 */
	private $version = '1.0.0';

	/**
	 * The ID of this plugin settings.
	 *
	 * @var settings_id
	 */
	public $settings_id = 'postnl';

	/**
	 * Shipping Product.
	 *
	 * @var PostNLWooCommerce\Product\PostNL
	 */
	public $shipping_product = null;

	/**
	 * Shipping Order.
	 *
	 * @var PostNLWooCommerce\Order\Single
	 */
	public $shipping_order = null;

	/**
	 * Shipping Order Bulk.
	 *
	 * @var PostNLWooCommerce\Order\Bulk
	 */
	public $shipping_order_bulk = null;

	/**
	 * Shipping Settings.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	public $shipping_settings = null;

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
		add_action( 'init', array( $this, 'load_plugin' ), 1 );
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

		$this->define( 'POSTNL_WC_SANDBOX_API_URL', esc_url( 'https://api-sandbox.postnl.nl' ) );
		$this->define( 'POSTNL_WC_PROD_API_URL', esc_url( 'https://api.postnl.nl' ) );

		$this->define( 'POSTNL_WC_VERSION', $this->version );
		$this->define( 'POSTNL_SETTINGS_ID', $this->settings_id );
		$this->define( 'POSTNL_WC_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/' );
		$this->define( 'POSTNL_UPLOADS_DIR', $upload_dir['basedir'] . '/postnl/' );
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin() {
		// Throw an admin error informing the user this plugin needs WooCommerce to function.
		add_action( 'admin_notices', array( $this, 'notice_wc_required' ) );

		// Throw an admin error informing the user this plugin needs country settings to be NL and BE.
		add_action( 'admin_notices', array( $this, 'notice_nl_be_required' ) );

		if ( class_exists( 'WooCommerce' ) && in_array( Utils::get_base_country(), Utils::get_available_country(), true ) ) {
			$this->define_constants();
			$this->init_hooks();
		}
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->get_shipping_order();
		$this->get_shipping_order_bulk();
		$this->get_shipping_product();
		$this->get_frontend();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 5 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

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
	 * Add PostNL Shipping Method to WooCommerce.
	 *
	 * @param array<WC_Shipping_Method> $shipping_methods Array of existing WC shipping methods.
	 *
	 * @return array<WC_Shipping_Method>
	 */
	public function add_shipping_method( $shipping_methods ) {
		$shipping_methods[ $this->settings_id ] = new Shipping_Method\PostNL();
		return $shipping_methods;
	}

	/**
	 * Get order single class.
	 *
	 * @return Order\Single
	 */
	public function get_shipping_order() {
		if ( empty( $this->shipping_order ) ) {
			$this->shipping_order = new Order\Single();
		}

		return $this->shipping_order;
	}

	/**
	 * Get order bulk class.
	 *
	 * @return Order\Bulk
	 */
	public function get_shipping_order_bulk() {
		if ( empty( $this->shipping_order_bulk ) ) {
			$this->shipping_order_bulk = new Order\Bulk();
		}

		return $this->shipping_order_bulk;
	}

	/**
	 * Get product class.
	 *
	 * @return Product\Single
	 */
	public function get_shipping_product() {
		if ( empty( $this->shipping_product ) ) {
			$this->shipping_product = new Product\Single();
		}

		return $this->shipping_product;
	}

	/**
	 * Get frontend class.
	 */
	public function get_frontend() {
		new Frontend\Container();
		new Frontend\Delivery_Day();
		new Frontend\Dropoff_Points();
	}

	/**
	 * Get settings class.
	 *
	 * @return Shipping_Method\Settings
	 */
	public function get_shipping_settings() {
		if ( empty( $this->shipping_settings ) ) {
			$this->shipping_settings = new Shipping_Method\Settings();
		}

		return $this->shipping_settings;
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
		if ( ! class_exists( 'WooCommerce' ) ) {
			?>
			<div class="error">
				<p><?php esc_html_e( 'PostNL plugin requires WooCommerce to be installed and activated!', 'postnl-for-woocommerce' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Admin error notifying user that Country must be using Netherlands or Belgium.
	 */
	public function notice_nl_be_required() {
		if ( ! in_array( Utils::get_base_country(), Utils::get_available_country(), true ) ) {
			?>
			<div class="error">
				<p><?php esc_html_e( 'PostNL plugin requires store country to be Netherlands (NL) or Belgium (BE)!', 'postnl-for-woocommerce' ); ?></p>
			</div>
			<?php
		}
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

	/**
	 * Get logger object.
	 */
	public static function get_logger() {
		$settings = Shipping_Method\Settings::get_instance();
		return new Logger( $settings->is_logging_enabled() );
	}
}
