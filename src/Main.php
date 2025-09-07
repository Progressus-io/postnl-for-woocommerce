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

use PostNLWooCommerce\Checkout_Blocks\Blocks_Integration;
use PostNLWooCommerce\Checkout_Blocks\Extend_Block_Core;
use PostNLWooCommerce\Checkout_Blocks\Extend_Store_Endpoint;

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
	private $version = '5.7.3';

	/**
	 * The ID of this plugin settings.
	 *
	 * @var settings_id
	 */
	public $settings_id = 'postnl';

	/**
	 * The name of this plugin service.
	 *
	 * @var service_name
	 */
	public $service_name = 'PostNL';

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
	 * Orders List
	 *
	 * @var PostNLWooCommerce\Order\OrdersList
	 */
	public $orders_list = null;

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
	 * Product Editor.
	 *
	 * @var PostNLWooCommerce\Product\Product_Editor
	 */
	public $product_editor = null;

	/**
	 * Construct the plugin.
	 */
	public function __construct() {
		$this->define_constants();
		// Throw an admin error informing the user this plugin needs WooCommerce to function.
		add_action( 'admin_notices', array( $this, 'notice_wc_required' ) );

		if ( ! class_exists( 'WooCommerce' ) ) {
			return;
		}

		// Declare WooCommerce features compatibility.
		add_action( 'before_woocommerce_init', array( $this, 'declare_wc_hpos_compatibility' ) );
		add_action( 'before_woocommerce_init', array( $this, 'declare_product_editor_compatibility' ) );

		// Throw an admin error informing the user this plugin needs country settings to be NL and BE.
		add_action( 'admin_notices', array( $this, 'notice_nl_be_required' ) );

		// Throw an admin error informing the user this plugin needs currency settings to be EUR, USD, GBP, CNY.
		add_action( 'admin_notices', array( $this, 'notice_currency_required' ) );

		if ( ! Utils::use_available_currency() || ! Utils::use_available_country() ) {
			return;
		}

		add_action( 'init', array( $this, 'load_plugin' ), 1 );
		// Register the block category.
		add_action( 'block_categories_all', array( $this, 'register_postnl_block_category' ), 10, 2 );
		add_filter( 'woocommerce_shipping_methods', array( $this, 'add_shipping_method' ) );

		$this->register_plugin_links();

	}

	/**
	 * Declare WooCommerce HPOS feature compatibility.
	 */
	public function declare_wc_hpos_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', POSTNL_WC_PLUGIN_BASENAME, true );
		}
	}

	/**
	 * Declare Product Editor compatibility.
	 */
	public function declare_product_editor_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'product_block_editor', POSTNL_WC_PLUGIN_BASENAME, true );
		}
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
		$this->define( 'POSTNL_WC_PLUGIN_DIR_PATH', untrailingslashit( plugin_dir_path( POSTNL_WC_PLUGIN_FILE ) ) );
		$this->define( 'POSTNL_WC_PLUGIN_DIR_URL', untrailingslashit( plugins_url( '/', POSTNL_WC_PLUGIN_FILE ) ) );

		$this->define( 'POSTNL_WC_SANDBOX_API_URL', esc_url( 'https://api-sandbox.postnl.nl' ) );
		$this->define( 'POSTNL_WC_PROD_API_URL', esc_url( 'https://api.postnl.nl' ) );

		$this->define( 'POSTNL_WC_VERSION', $this->version );
		$this->define( 'POSTNL_SETTINGS_ID', $this->settings_id );
		$this->define( 'POSTNL_SERVICE_NAME', $this->service_name );
		$this->define( 'POSTNL_WC_LOG_DIR', $upload_dir['basedir'] . '/wc-logs/' );
		$this->define( 'POSTNL_UPLOADS_DIR', $upload_dir['basedir'] . '/postnl/' );
	}

	/**
	 * Determine which plugin to load.
	 */
	public function load_plugin() {
		$this->init_hooks();
		$this->checkout_blocks();
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		$this->get_shipping_order();
		$this->get_shipping_order_bulk();
		$this->get_orders_list();
		$this->get_shipping_product();
		$this->get_frontend();
		$this->get_product_editor();
	}

	/**
	 * Collection of hooks.
	 */
	public function init_hooks() {
		add_action( 'init', array( $this, 'init' ), 5 );
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Locate woocommerce template.
		add_filter( 'woocommerce_locate_template', array( $this, 'woocommerce_locate_template' ), 20, 3 );

		add_filter( 'woocommerce_email_classes', array( $this, 'add_wc_smart_return_email' ) );

		add_action( 'admin_notices', array( 'PostNLWooCommerce\Admin\Survey', 'maybe_render_notice' ) );
		add_action( 'add_meta_boxes', array( 'PostNLWooCommerce\Admin\Survey', 'maybe_add_meta_box' ), 10, 2 );

	}

	/**
	 * Add the smart return email class.
	 *
	 * @param array $email_classes Array of existing WC emails.
	 *
	 * @return array $email_classes
	 */
	public function add_wc_smart_return_email( $email_classes ) {
		// Add the smart return email to the list of email classes.
		$email_classes['WC_Smart_Return_Email'] = new Emails\WC_Email_Smart_Return();

		return $email_classes;
	}

	/**
	 * Localisation.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'postnl-for-woocommerce', false, untrailingslashit( dirname( POSTNL_WC_PLUGIN_BASENAME ) ) . '/languages' );
	}

	/**
	 * Add PostNL Shipping Method to WooCommerce.
	 *
	 * @param array<WC_Shipping_Method> $shipping_methods Array of existing WC shipping methods.
	 *
	 * @return array<WC_Shipping_Method>
	 */
	public function add_shipping_method( $shipping_methods ) {
		$shipping_methods[ $this->settings_id ] = 'PostNLWooCommerce\Shipping_Method\PostNL';
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
	 * Get product editor class.
	 *
	 * @return Product\Product_Editor
	 */
	public function get_product_editor() {
		if ( empty( $this->product_editor ) ) {
			$this->product_editor = new Product\Product_Editor();
		}

		return $this->product_editor;
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
	 * Get order bulk class.
	 *
	 * @return Order\OrdersList
	 */
	public function get_orders_list() {
		if ( empty( $this->orders_list ) ) {
			$this->shipping_order_bulk = new Order\OrdersList();
		}

		return $this->orders_list;
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
		new Frontend\Checkout_Fields();
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
		if ( ! Utils::use_available_country() ) {
			?>
			<div class="error">
				<p><?php esc_html_e( 'PostNL plugin requires store country to be Netherlands (NL) or Belgium (BE)!', 'postnl-for-woocommerce' ); ?></p>
			</div>
			<?php
		}
	}

	/**
	 * Admin error notifying user that currency must be using EUR, GBP, USD, and CNY.
	 */
	public function notice_currency_required() {
		if ( ! Utils::use_available_currency() ) {
			?>
			<div class="error">
				<p><?php esc_html_e( 'PostNL plugin requires store currency to be EUR, USD, GBP or CNY!', 'postnl-for-woocommerce' ); ?></p>
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
	public function checkout_blocks() {

		// Initialize classes that depend on WooCommerce
		new Extend_Block_Core();
		Extend_Store_Endpoint::init();
		// Register the blocks integration
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new Blocks_Integration() );
			}
		);
	}
	/**
	 * Registers the slug as a block category with WordPress.
	 *
	 * @param array $categories Existing categories.
	 * @return array Modified categories.
	 */
	public function register_postnl_block_category( $categories ) {
		return array_merge(
			$categories,
			array(
				array(
					'slug'  => 'postnl',
					'title' => __( 'Postnl Checkout Blocks', 'postnl-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Register plugin action/meta links (admin only).
	 *
	 * @return void
	 */
	public function register_plugin_links() {
		if ( ! is_admin() ) {
			return;
		}

		add_filter( 'plugin_row_meta', array( $this, 'add_row_meta' ), 10, 2 );
		add_filter( 'plugin_action_links_' . POSTNL_WC_PLUGIN_BASENAME, array( $this, 'add_action_links' ), 10, 1 );
	}

	/**
	 * Add row meta links.
	 *
	 * @param string[] $links Existing links.
	 * @param string $file Plugin file name.
	 *
	 * @return string[]
	 */
	public function add_row_meta( array $links, string $file ): array {
		if ( $file === POSTNL_WC_PLUGIN_BASENAME ) {
			$links[] = sprintf(
				'<a href="%s" target="_blank" rel="noopener">%s</a>',
				esc_url( 'https://wordpress.org/support/plugin/woo-postnl/reviews/#new-post' ),
				esc_html__( 'Leave a review', 'postnl-for-woocommerce' )
			);
		}

		return $links;
	}

	/**
	 * Add action links.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( array $links ): array {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'admin.php?page=wc-settings&tab=shipping&section=postnl' ) ),
				esc_html__( 'Settings', 'postnl-for-woocommerce' )
			)
		);

		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener">%s</a>',
			esc_url( 'https://wordpress.org/support/plugin/woo-postnl/reviews/#new-post' ),
			esc_html__( 'Leave a review', 'postnl-for-woocommerce' )
		);

		return $links;
	}

	/**
	 * Get logger object.
	 */
	public static function get_logger() {
		$settings = Shipping_Method\Settings::get_instance();
		return new Logger( $settings->is_logging_enabled() );
	}
}
