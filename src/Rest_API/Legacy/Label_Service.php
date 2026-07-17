<?php
/**
 * Class Rest_API\Legacy\Label_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Rest_API\Contracts\Label_Service_Interface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Label_Service
 *
 * Legacy service wrapper for outbound shipping labels.  Implements
 * Label_Service_Interface by delegating to the existing Order\Base pipeline
 * (create_label → put_label_content → maybe_merge_labels) so no label-
 * generation logic is duplicated.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Label_Service extends Order_Base implements Label_Service_Interface {

	/**
	 * No WP hooks are registered by this service class.
	 *
	 * Required by Order\Base but intentionally a no-op here.
	 */
	public function init_hooks() {
		// Intentionally empty — this service does not register WordPress hooks.
	}

	/**
	 * Create a PostNL shipping label and return the normalized label record.
	 *
	 * Delegates entirely to Order\Base::create_label(), which runs the full
	 * pipeline: API request → check_label_and_barcode → put_label_content →
	 * maybe_merge_labels.  The returned array is ready to be stored as
	 * _postnl_order_metadata['labels'].
	 *
	 * @param array $post_data Context needed to build and send the label request.
	 *
	 * @return array Normalized label record keyed by label-type string.
	 *
	 * @throws \Exception If the API request fails or the label array is empty.
	 */
	public function create( array $post_data ): array {
		return $this->create_label( $post_data );
	}
}
