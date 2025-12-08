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

use PostNLWooCommerce\Utils;
use WC_Admin_Meta_Boxes;
use WC_Product;

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
	protected $service = POSTNL_SERVICE_NAME;

	/**
	 * Letterbox field name.
	 *
	 * @var letterbox_parcel
	 */
	const LETTERBOX_PARCEL = '_postnl_letterbox_parcel';

	/**
	 * Letterbox field name.
	 *
	 * @var max_qty_per_letterbox
	 */
	const MAX_QTY_PER_LETTERBOX = '_postnl_max_qty_per_letterbox';

	/**
	 * Origin field name.
	 *
	 * @var origin_field
	 */
	const ORIGIN_FIELD = '_postnl_country_origin';

	/**
	 * HS Tariff Code field name.
	 *
	 * @var hs_code_field
	 */
	const HS_CODE_FIELD = '_postnl_hs_tariff_code';

	/**
	 * Adults Only (18+) field.
	 *
	 * @var adults_only
	 */
	const ADULTS_ONLY_FIELD = '_postnl_adult_product';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Mapping the product field.
	 *
	 * @param String $service Service name for the product fields.
	 *
	 * @return array
	 */
	public static function product_field_maps( $service ) {
		return array(
			array(
				'id'          => self::ADULTS_ONLY_FIELD,
				'type'        => 'checkbox',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'Mark as 18+ (Adults Only) (%s)', 'postnl-for-woocommerce' ), $service ),
				'description' => esc_html__( 'Enable this for products intended only for adults (18+).', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
			),
			array(
				'id'          => self::LETTERBOX_PARCEL,
				'type'        => 'checkbox',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'Enable Letterbox Parcel (%s)', 'postnl-for-woocommerce' ), $service ),
				'description' => esc_html__( 'When this setting is enabled the PostNL plug-in automatically determines whether a shipment fits through a letterbox. This choice can be overridden when creating a shipment manually via the Label & Tracking menu. This only works for orders with destination Netherlands.', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
			),
			array(
				'id'                => self::MAX_QTY_PER_LETTERBOX,
				'type'              => 'number',
				// translators: %s will be replaced by service name.
				'label'             => sprintf( esc_html__( 'Maximum amount per letterbox parcel (%s)', 'postnl-for-woocommerce' ), $service ),
				'description'       => esc_html__( 'Please fill in how many times this product fits in a letterbox parcel. A letterbox parcel may weigh a maximum of 2 kilograms and has the following maximum dimensions: 38x26.5x3.2 cm', 'postnl-for-woocommerce' ),
				'desc_tip'          => 'true',
				'placeholder'       => esc_html__( 'Enter max quantity', 'postnl-for-woocommerce' ),
				'custom_attributes' => array(
					'min' => 1,
				),
			),
			array(
				'id'          => self::ORIGIN_FIELD,
				'type'        => 'select',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'Country of Origin (%s)', 'postnl-for-woocommerce' ), $service ),
				'description' => esc_html__( 'Country of Origin.', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
				'options'     => array_merge(
					array( '0' => esc_html__( '- select country -', 'postnl-for-woocommerce' ) ),
					WC()->countries->get_countries(),
				),
			),
			array(
				'id'          => self::HS_CODE_FIELD,
				'type'        => 'text',
				// translators: %s will be replaced by service name.
				'label'       => sprintf( esc_html__( 'HS Tariff Code (%s)', 'postnl-for-woocommerce' ), $service ),
				'description' => esc_html__( 'HS Tariff Code is a number assigned to every possible commodity that can be imported or exported from any country.', 'postnl-for-woocommerce' ),
				'desc_tip'    => 'true',
				'placeholder' => esc_html__( 'HS Code', 'postnl-for-woocommerce' ),
			),

		);
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'woocommerce_product_options_shipping', array( $this, 'additional_product_shipping_options' ), 8 );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_additional_product_parent_options' ) );
		add_action( 'woocommerce_variation_options_pricing', array( $this, 'additional_product_variation_shipping_options' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_additional_product_variation_options' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_product_edit_script' ) );
		add_action( 'woocommerce_before_product_object_save', array( $this, 'validate_conflicting_options' ), 100, 1 );
	}

	/**
	 * Enqueue JS file in product single page.
	 */
	public function enqueue_admin_product_edit_script() {
		if ( ! is_admin() ) {
			return;
		}

		wp_enqueue_script(
			'admin-product-single',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/admin-product-single.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);
	}

	/**
	 * Add the meta box for shipment info on the product page.
	 *
	 * @access public
	 */
	public function additional_product_shipping_options() {
		$fields = self::product_field_maps( $this->service );
		Utils::fields_generator( $fields );
	}

	/**
	 * Add the meta box for shipment info on the product variation.
	 *
	 * @param int   $loop Iteration of the product variations.
	 * @param Array $variation_data Variation data.
	 * @param Array $variation Variation object.
	 *
	 * @access public
	 */
	public function additional_product_variation_shipping_options( $loop, $variation_data, $variation ) {
		if ( empty( $variation->ID ) ) {
			return;
		}

		$product = wc_get_product( $variation->ID );

		if ( empty( $product ) ) {
			return;
		}

		$fields           = self::product_field_maps( $this->service );
		$variation_fields = array();

		foreach ( $fields as $field ) {
			$field['value']     = $product->get_meta( $field['id'] );
			$field['id']        = $field['id'] . '[' . $loop . ']';
			$variation_fields[] = $field;
		}

		Utils::fields_generator( $variation_fields );
	}

	/**
	 * Saving meta box in product admin page.
	 *
	 * @param int $product_id Product post ID.
	 * @param int $i          Iteration of product variations.
	 */
	public function save_additional_product_shipping_options( $product_id, $i = '' ) {
		$product = wc_get_product( $product_id );

		if ( empty( $product ) ) {
			\WC_Admin_Meta_Boxes::add_error( esc_html__( 'Product ID does not exists!', 'postnl-for-woocommerce' ) );
			return;
		}

		$fields = self::product_field_maps( $this->service );

		foreach ( $fields as $field ) {
			if ( ! isset( $_POST[ $field['id'] ] ) ) {
				$product->delete_meta_data( $field['id'] );
				continue;
			}

			if ( empty( $i ) && ! is_array( $_POST[ $field['id'] ] ) && 0 === $product->get_parent_id() ) {
				$product->update_meta_data( $field['id'], sanitize_text_field( wp_unslash( $_POST[ $field['id'] ] ) ) );
			} elseif ( ! empty( $i ) && 0 !== $product->get_parent_id() ) {
				$field_value = ! empty( $_POST[ $field['id'] ][ $i ] ) ? $_POST[ $field['id'] ][ $i ] : '';
				$product->update_meta_data( $field['id'], sanitize_text_field( wp_unslash( $field_value ) ) );
			}
		}

		$product->save();
	}

	/**
	 * Saving meta box in product admin page.
	 *
	 * @param int $product_id Product post ID.
	 */
	public function save_additional_product_parent_options( $product_id ) {
		$this->save_additional_product_shipping_options( $product_id );
	}

	/**
	 * Saving meta box in product admin page.
	 *
	 * @param int $product_id Product post ID.
	 * @param int $i Iteration of product variations.
	 */
	public function save_additional_product_variation_options( $product_id, $i ) {
		$this->save_additional_product_shipping_options( $product_id, $i );
	}

	/**
	 * Prevent a product from being marked as both "18+" and "Letterbox Parcel".
	 *
	 * @param \WC_Product $product Current product object.
	 */
	public static function validate_conflicting_options( WC_Product $product ) {
		// Read the current meta values.
		$is_adult     = Utils::is_adults_only_product( $product );
		$is_letterbox = Utils::is_letterbox_parcel_product( $product );

		// If both check-boxes are turned on, remove letterbox metadata.
		if ( $is_adult && $is_letterbox ) {
			$product->delete_meta_data( self::LETTERBOX_PARCEL );
			$product->save_meta_data();

			// Show a notice in the admin screen.
			WC_Admin_Meta_Boxes::add_error(
				__( '“18+” and “Letterbox Parcel” cannot be enabled together. Letterbox has been disabled automatically.', 'postnl-for-woocommerce' )
			);
		}
	}
}
