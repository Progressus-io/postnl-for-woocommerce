<?php
/**
 * Product editor handler class.
 *
 * @package PostNLWooCommerce\Product
 */

namespace PostNLWooCommerce\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Automattic\WooCommerce\Admin\BlockTemplates\BlockInterface;

/**
 * Product editor handler.
 */
class Product_Editor {

	/**
	 * Class constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_block_template_area_product-form_after_add_block_product-shipping-dimensions', array( $this, 'add_shipping_blocks' ) );
		add_action( 'woocommerce_block_template_area_product-form_after_add_block_product-variation-shipping-dimensions', array( $this, 'add_shipping_blocks' ) );
	}

	/**
	 * Add custom blocks to the product editor shipping section.
	 *
	 * @param BlockInterface $shipping_dimensions_block The shipping dimensions block.
	 */
	public function add_shipping_blocks( BlockInterface $shipping_dimensions_block ) {
		if ( ! method_exists( $shipping_dimensions_block, 'get_parent' ) ) {
			return;
		}

		$parent = $shipping_dimensions_block->get_parent();

		// Add Letterbox Parcel Checkbox Block.
		$parent->add_block(
			array(
				'id'         => 'postnl-letterbox-parcel',
				'blockName'  => 'woocommerce/product-checkbox-field',
				'attributes' => array(
					'title'          => __( 'PostNL extra settings', 'postnl-for-woocommerce' ),
					'label'          => __( 'Enable Letterbox Parcel', 'postnl-for-woocommerce' ),
					'property'       => 'meta_data.' . Single::LETTERBOX_PARCEL,
					'tooltip'        => __( 'When enabled, PostNL plugin automatically determines whether a shipment fits through a letterbox.', 'postnl-for-woocommerce' ),
					'checkedValue'   => 'yes',
					'uncheckedValue' => '',
				),
			)
		);

		// Add Maximum Quantity per Letterbox Number Block.
		$parent->add_block(
			array(
				'id'         => 'postnl-max-qty-per-letterbox',
				'blockName'  => 'woocommerce/product-number-field',
				'attributes' => array(
					'label'       => __( 'Maximum Quantity per Letterbox Parcel', 'postnl-for-woocommerce' ),
					'property'    => 'meta_data.' . Single::MAX_QTY_PER_LETTERBOX,
					'placeholder' => __( 'Enter max quantity', 'postnl-for-woocommerce' ),
					'min'         => 1,
					'tooltip'     => __( 'Specify how many times this product fits in a letterbox parcel.', 'postnl-for-woocommerce' ),
				),
			)
		);

		// Add Country of Origin Select Block.
		$parent->add_block(
			array(
				'id'         => 'postnl-country-origin',
				'blockName'  => 'woocommerce/product-select-field',
				'attributes' => array(
					'label'    => __( 'Country of Origin', 'postnl-for-woocommerce' ),
					'property' => 'meta_data.' . Single::ORIGIN_FIELD,
					'options'  => array_merge(
						array(
							array(
								'value' => '0',
								'label' => __( '- select country -', 'postnl-for-woocommerce' ),
							),
						),
						array_map(
							function ( $key, $value ) {
								return array(
									'value' => $key,
									'label' => $value,
								);
							},
							array_keys( WC()->countries->get_countries() ),
							WC()->countries->get_countries()
						)
					),
					'tooltip'  => __( 'Select the country of origin for this product.', 'postnl-for-woocommerce' ),
				),
			)
		);

		// Add HS Tariff Code Text Block.
		$parent->add_block(
			array(
				'id'         => 'postnl-hs-code',
				'blockName'  => 'woocommerce/product-text-field',
				'attributes' => array(
					'label'       => __( 'HS Tariff Code', 'postnl-for-woocommerce' ),
					'property'    => 'meta_data.' . Single::HS_CODE_FIELD,
					'placeholder' => __( 'HS Code', 'postnl-for-woocommerce' ),
					'tooltip'     => __( 'HS Tariff Code for international shipping.', 'postnl-for-woocommerce' ),
				),
			)
		);

		// Add 18+ Adults Only Checkbox Block.
		$parent->add_block(
			array(
				'id'         => 'postnl-adults-only-checkbox',
				'blockName'  => 'woocommerce/product-checkbox-field',
				'attributes' => array(
					'title'          => esc_html__( 'Adult Product Settings', 'postnl-for-woocommerce' ),
					'label'          => esc_html__( 'Mark as 18+ (Adults Only)', 'postnl-for-woocommerce' ),
					'property'       => 'meta_data' . Single::ADULTS_ONLY_FIELD,
					'tooltip'        => esc_html__( 'Enable this for products intended only for adults (18+).', 'postnl-for-woocommerce' ),
					'checkedValue'   => 'yes',
					'uncheckedValue' => '',
				),
			)
		);
	}
}
