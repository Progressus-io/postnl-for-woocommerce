<?php
/**
 * Class Rest_API\V4\Barcode\Service file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Barcode
 */

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
 * PostNL confirmed (2026-05-21) that V4 has no standalone barcode endpoint: the
 * label call (/shipment/delivery/v4/labelconfirm) auto-issues the barcode based
 * on shipment type and destination and returns it in the label response. So the
 * prefetch that the Legacy transport performs against GET /shipment/v1_1/barcode
 * does not exist on V4; there is nothing for generate() to request.
 *
 * On the V4 path, Order\Base reverses the ordering (label first, then reads the
 * barcode out of the label response) and never calls generate(). This class
 * exists only so Service_Factory can return a uniform Barcode_Service_Interface
 * for the barcode flow; generate() returns an empty array as a safe fallback if
 * it is ever reached.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Barcode
 */
class Service implements Barcode_Service_Interface {

	/**
	 * No-op barcode generation.
	 *
	 * V4 issues the barcode from the label response, so no request is made here.
	 *
	 * @param array $post_data Post data (unused on V4).
	 *
	 * @return array Empty array — the barcode is harvested from the label response.
	 */
	public function generate( array $post_data ): array {
		return array();
	}
}
