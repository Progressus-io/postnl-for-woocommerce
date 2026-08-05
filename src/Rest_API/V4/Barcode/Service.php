<?php
/**
 * Class Rest_API\V4\Barcode\Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Barcode
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Barcode;

use PostNLWooCommerce\Rest_API\Contracts\Barcode_Service_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Service
 *
 * V4 barcode "service" — intentionally a no-op.
 *
 * V4 has no standalone barcode endpoint: the label call auto-issues the barcode and
 * returns it, so there is nothing for generate() to request. Order\Base takes the
 * label-first branch on V4 and never calls generate(); this class exists only so
 * Service_Factory can return a uniform Barcode_Service_Interface.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Barcode
 */
class Service implements Barcode_Service_Interface {

	/**
	 * No-op barcode generation.
	 *
	 * Returns an empty array, which create_barcode() rejects loudly — reaching this
	 * method means the V4 path was mis-wired, not that a barcode is unavailable.
	 *
	 * @param array $post_data Post data (unused on V4).
	 *
	 * @return array Always empty; the barcode is harvested from the label response.
	 *
	 * @since 6.0.0
	 */
	public function generate( array $post_data ): array {
		return array();
	}
}
