<?php
/**
 * Plugin Name: PostNL for WooCommerce
 * Plugin URI: https://github.com/Progressus-io/postnl-for-woocommerce/
 * Description: WooCommerce integration for PostNL
 * Author: PostNL
 * Author URI: https://postnl.post/
 * Version: 1.0.0
 * WC requires at least: 2.6.14
 * WC tested up to: 6.6.0
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

require_once 'vendor/autoload.php';

Main::instance();
