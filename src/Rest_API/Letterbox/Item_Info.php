<?php
/**
 * Class Rest_API\Letterbox\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Letterbox
 */

namespace PostNLWooCommerce\Rest_API\Letterbox;

use PostNLWooCommerce\Rest_API\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Letterbox
 */
class Item_Info extends Shipping\Item_Info {
	/**
	 * Get product code from api args.
	 *
	 * @return String.
	 */
	public function get_product_code() {
		return '2928';
	}

	/**
	 * Get product options from api args.
	 *
	 * @return String.
	 */
	public function get_product_options() {
		return array();
	}
}
