<?php
/**
 * Class Rest_API\Return_Label\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */

namespace PostNLWooCommerce\Rest_API\Return_Label;

use PostNLWooCommerce\Rest_API\Shipping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Return_Label
 */
class Item_Info extends Shipping\Item_Info {
	/**
	 * Get product code from api args.
	 *
	 * @return String.
	 */
	public function get_product_code() {
		return '2285';
	}

	/**
	 * Get product options from api args.
	 *
	 * @return String.
	 */
	public function get_product_options() {
		return array(
			'characteristic' => '152',
			'option'         => '025',
		);
	}
}
