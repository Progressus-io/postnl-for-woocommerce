<?php
/**
 * Class Rest_API\Legacy\Return_Label_Service file.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */

namespace PostNLWooCommerce\Rest_API\Legacy;

use PostNLWooCommerce\Order\Base as Order_Base;
use PostNLWooCommerce\Rest_API\Contracts\Return_Label_Service_Interface;
use PostNLWooCommerce\Rest_API\Legacy\Shipment_and_Return\Client as Shipment_and_Return_Client;
use PostNLWooCommerce\Rest_API\Legacy\Shipment_and_Return\Item_Info as Shipment_and_Return_Item_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Return_Label_Service
 *
 * Legacy service wrapper implementing Return_Label_Service_Interface.
 *
 * - create(): delegates to Order\Base::maybe_create_return_label() so the full
 *   pipeline (Return_Label\Client → put_label_content → maybe_merge_labels with
 *   type 'return-label') is used without duplicating any logic.
 *
 * - activate(): wraps Shipment_and_Return\Client which calls the PostNL
 *   /parcels/v1/shipment/activatereturn endpoint.  This mirrors what
 *   Order\Single::postnl_activate_return_function() does today.
 *
 * @package PostNLWooCommerce\Rest_API\Legacy
 */
class Return_Label_Service extends Order_Base implements Return_Label_Service_Interface {

	/**
	 * No WP hooks are registered by this service class.
	 *
	 * Required by Order\Base but intentionally a no-op here.
	 */
	public function init_hooks() {
		// Intentionally empty — this service does not register WordPress hooks.
	}

	/**
	 * Create a PostNL return label and return the normalized label record.
	 *
	 * Delegates to Order\Base::maybe_create_return_label(), which uses the
	 * Return_Label\Client pipeline and returns the normalized labels array ready
	 * to be stored as _postnl_order_metadata['labels'].
	 *
	 * Callers must ensure $post_data['saved_data']['backend']['create_return_label'] === 'yes'
	 * so the pipeline guard inside maybe_create_return_label() does not short-circuit.
	 *
	 * @param array $post_data Context needed to build and send the return label request.
	 *
	 * @return array Normalized label record keyed by 'return-label'.
	 *
	 * @throws \Exception If the API request fails or the label array is empty.
	 */
	public function create( array $post_data ): array {
		return $this->maybe_create_return_label( $post_data );
	}

	/**
	 * Activate the return function on an existing outbound shipment.
	 *
	 * Mirrors Order\Single::postnl_activate_return_function() by constructing
	 * Shipment_and_Return\Item_Info with the order ID (which reads the barcode
	 * from order meta), then calling send_request() on the client.
	 *
	 * @param int $order_id WooCommerce order ID.
	 *
	 * @return array JSON-decoded PostNL activatereturn response body.
	 *
	 * @throws \Exception If the API request fails at transport level.
	 */
	public function activate( int $order_id ): array {
		$item_info = new Shipment_and_Return_Item_Info( $order_id );
		$client    = new Shipment_and_Return_Client( $item_info );
		return $client->send_request();
	}
}
