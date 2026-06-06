<?php
/**
 * Class Rest_API\Legacy\Letterbox_Service file.
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
 * Class Letterbox_Service
 *
 * Legacy service wrapper for letterbox parcel labels.  Implements
 * Label_Service_Interface by delegating to Order\Base::maybe_create_letterbox()
 * which runs the full Letterbox pipeline (API request → put_label_content →
 * maybe_merge_labels with type 'letterbox').
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Letterbox_Service extends Order_Base implements Label_Service_Interface {

	/**
	 * No WP hooks are registered by this service class.
	 *
	 * Required by Order\Base but intentionally a no-op here.
	 */
	public function init_hooks() {
		// Intentionally empty — this service does not register WordPress hooks.
	}

	/**
	 * Create a PostNL letterbox label and return the normalized label record.
	 *
	 * Delegates to Order\Base::maybe_create_letterbox(), which uses the
	 * Letterbox\Client pipeline and returns the normalized labels array ready
	 * to be stored as _postnl_order_metadata['labels'].
	 *
	 * Callers must ensure $post_data['saved_data']['backend']['letterbox'] === 'yes'
	 * so the pipeline guard inside maybe_create_letterbox() does not short-circuit.
	 *
	 * @param array $post_data Context needed to build and send the letterbox request.
	 *
	 * @return array Normalized label record keyed by 'letterbox'.
	 *
	 * @throws \Exception If the API request fails or the label array is empty.
	 */
	public function create( array $post_data ): array {
		return $this->maybe_create_letterbox_pipeline( $post_data );
	}
}
