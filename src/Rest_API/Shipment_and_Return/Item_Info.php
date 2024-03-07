<?php
/**
 * Class Rest_API\Shipping\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipping_and_Return;

use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Product\Single;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */
class Item_Info extends Base_Info {

	protected function parse_args() {
	}

	public function convert_data_to_args( $post_data ) {
	}

}
