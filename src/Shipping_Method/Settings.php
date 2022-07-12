<?php
/**
 * Class Shipping_Method/Settings file.
 *
 * @package PostNLWooCommerce\Shipping_Method
 */

namespace PostNLWooCommerce\Shipping_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings
 *
 * @package PostNLWooCommerce\Shipping_Method
 */
class Settings extends \WC_Settings_API {
	/**
	 * ID of the class extending the settings API. Used in option names.
	 *
	 * @var string
	 */
	public $id = POSTNL_SETTINGS_ID;
}
