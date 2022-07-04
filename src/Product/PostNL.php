<?php
/**
 * Class Order\PostNL file.
 *
 * @package PostNLWooCommerce\Product
 */

namespace PostNLWooCommerce\Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class AbstractProduct
 *
 * @package PostNLWooCommerce\Product
 */
class PostNL extends AbstractProduct {

	/**
	 * Add the meta box for shipment info on the product page for child class.
	 *
	 * @access public
	 */
	public function additional_product_settings() {

	}

	/**
	 * Saving meta box in product admin page for child class.
	 *
	 * @param int $product_id Product Post ID.
	 * @access public
	 */
	public function save_additional_product_settings( $product_id ) {

	}
}
