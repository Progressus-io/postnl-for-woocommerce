<?php
/**
 * Class Product\Single file.
 *
 * @package PostNLWooCommerce\Product
 */

namespace PostNLWooCommerce\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Single
 *
 * @package PostNLWooCommerce\Product
 */
class Single {
	/**
	 * Saved shipping settings.
	 *
	 * @var shipping_settings
	 */
	protected $shipping_settings = array();

	/**
	 * Current service.
	 *
	 * @var service
	 */
	protected $service = 'PostNL';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'additional_product_shipping_options' ), 8 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_additional_product_shipping_options' ) );
	}

	/**
	 * Add the meta box for shipment info on the product page.
	 *
	 * @access public
	 */
	public function additional_product_shipping_options() {
		$countries = WC()->countries->get_countries();

		woocommerce_wp_select(
			array(
				'id'          => '_postnl_country_origin',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'Country of Origin (%s)', 'postnl-for-woocommerce' ), $this->service ),
				'description' => esc_html__( 'Country of Origin.', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
				'options'     => array_merge(
					array( '0' => esc_html__( '- select country -', 'postnl-for-woocommerce' ) ),
					$countries
				),
			),
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_postnl_hs_tariff_code',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'HS Tariff Code (%s)', 'postnl-for-woocommerce' ), $this->service ),
				'description' => esc_html__( 'HS Tariff Code is a number assigned to every possible commodity that can be imported or exported from any country.', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
				'placeholder' => 'HS Code',
			)
		);
	}

	/**
	 * Saving meta box in product admin page.
	 *
	 * @param int $product_id Product post ID.
	 */
	public function save_additional_product_shipping_options( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			\WC_Admin_Meta_Boxes::add_error( esc_html__( 'Product ID does not exists!', 'postnl-for-woocommerce' ) );
			return;
		}

		// Country of origin.
		if ( isset( $_POST['_postnl_country_origin'] ) ) {
			$product->update_meta_data( '_postnl_country_origin', sanitize_text_field( $_POST['_postnl_country_origin'] ) );
		}

		// HS code value.
		if ( isset( $_POST['_postnl_hs_tariff_code'] ) ) {
			$product->update_meta_data( '_postnl_hs_tariff_code', sanitize_text_field( $_POST['_postnl_hs_tariff_code'] ) );
		}

		$product->save();
	}
}
