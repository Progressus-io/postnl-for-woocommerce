<?php
/**
 * Plugin Name: PostNL for WooCommerce
 * Plugin URI: https://github.com/Progressus-io/postnl-for-woocommerce/
 * Description: This plugin enables you to confirm PostNL shipments and print shipping labels promptly. Customers can choose their delivery time and location.
 * Author: PostNL
 * Author URI: https://postnl.post/
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 5.7.3
 * Requires Plugins: woocommerce
 * Requires PHP: 7.4
 * Requires at least: 6.6
 * Tested up to: 6.8
 * WC requires at least: 9.6
 * WC tested up to: 9.8
 * Text Domain: postnl-for-woocommerce
 * Domain Path: /languages/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @package PostNLWooCommerce
 */

namespace PostNLWooCommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'POSTNL_WC_PLUGIN_FILE' ) ) {
	define( 'POSTNL_WC_PLUGIN_FILE', __FILE__ );
}

if ( ! defined( 'POSTNL_WC_PLUGIN_BASENAME' ) ) {
	define( 'POSTNL_WC_PLUGIN_BASENAME', plugin_basename( POSTNL_WC_PLUGIN_FILE ) );
}

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';

/**
 * Main PostNL for WooCommerce.
 *
 * @return Main instance.
 */
function postnl() {
	return Main::instance();
}
add_action( 'plugins_loaded', 'PostNLWooCommerce\postnl' );
