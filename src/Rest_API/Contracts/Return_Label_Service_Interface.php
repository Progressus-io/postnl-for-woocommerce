<?php
/**
 * Interface Return_Label_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for return label generation and shipment-return activation services.
 *
 * Covers two distinct operations that share the concept of a "return":
 *
 * 1. create(): Generates a return label PDF and returns the normalized label
 *    record (same shape as Label_Service_Interface::create()) to be stored
 *    in _postnl_order_metadata['labels']['return-label'].
 *
 * 2. activate(): Activates the return function on an existing shipment via
 *    /parcels/v1/shipment/activatereturn (Shipment_and_Return\Client).  Called
 *    from Order\Single::postnl_activate_return_function() when the admin clicks
 *    "Activate Return" in the order meta box.
 *
 * Both the Legacy (V1) and any future V4 transport must implement this interface.
 * Callers in Order\Base and Order\Single never need to know which transport answered.
 */
interface Return_Label_Service_Interface {

	/**
	 * Create a PostNL return label for an order and return the normalized
	 * label record ready to be stored in _postnl_order_metadata['labels'].
	 *
	 * The return shape is derived from Order\Base::maybe_create_return_label(),
	 * specifically the value it returns after the full pipeline:
	 *   1. PostNL API call (/v1/shipment, POST, confirm=true) with swapped
	 *      addresses so the shipment flows back to the store (Return_Label\Client
	 *      extends Shipping\Client and overrides get_customer_address()).
	 *   2. Validation of barcode + label content.
	 *   3. Base64 decode + file write to POSTNL_UPLOADS_DIR.
	 *   4. Merge of A6 files into A4 when applicable
	 *      (via put_label_content() + maybe_merge_labels() with type 'return-label').
	 *
	 * The resulting array is stored in $saved_data['labels'] by the caller:
	 *   $saved_data['labels'] = $return_label_service->create( $post_data );
	 *
	 * Only called when 'create_return_label' === 'yes' in the backend options AND
	 * the shipment is eligible (NL/BE destination, not certain product codes).
	 *
	 * @param array $post_data {
	 *     Context needed to build and send the return label request.
	 *     Shape is identical to Label_Service_Interface::create() $post_data.
	 *
	 *     @type \WC_Order $order         Required. WooCommerce order object.
	 *     @type array     $saved_data {
	 *         Required. Order-level saved data.
	 *         @type array $backend  Admin-selected option flags.
	 *     }
	 *     @type string    $main_barcode  Required. Primary barcode string.
	 *     @type string[]  $barcodes      Required. All barcodes (index 0 = main).
	 * }
	 *
	 * @return array {
	 *     Normalized label record keyed by 'return-label'.  Same shape as
	 *     Label_Service_Interface::create().  Stored verbatim as
	 *     _postnl_order_metadata['labels'] by the caller.
	 *
	 *     @type array $return_label {
	 *         The actual array key is the string 'return-label' (the parent_label_type
	 *         passed to put_label_content()).
	 *
	 *         @type string   $type         'return-label'.
	 *         @type string   $barcode      The main barcode string of the return shipment.
	 *         @type int      $created_at   Unix timestamp at the moment of label creation.
	 *         @type string   $filepath     Absolute path to the final return label file
	 *                                      on disk.  Used by download_label() via
	 *                                      get_download_label_url( $id, 'return-label' ).
	 *         @type string[] $merged_files Paths to individual files merged into
	 *                                      $filepath.  Present whenever labels were
	 *                                      merged, i.e. any case other than a single
	 *                                      A6 label (covers A4 and multi-collo A6).
	 *     }
	 * }
	 *
	 * @throws \Exception If the API request fails, the barcode is missing, the
	 *                    label content is empty, or the resulting label array is empty.
	 */
	public function create( array $post_data ): array;

	/**
	 * Activate the return function on an existing outbound shipment.
	 *
	 * Derived from Order\Single::postnl_activate_return_function(), which:
	 *   1. Constructs Shipment_and_Return\Item_Info from $order_id.  The Item_Info
	 *      reads the barcode from _postnl_order_metadata['labels']['label']['barcode'].
	 *   2. Constructs Shipment_and_Return\Client with that Item_Info.
	 *   3. Calls send_request().
	 *   4. Checks $response['successFulBarcodes'] to confirm activation.
	 *   5. On success, stores 'yes' in _postnl_return_activated order meta.
	 *
	 * The current PostNL endpoint is /parcels/v1/shipment/activatereturn (POST).
	 *
	 * @param int $order_id WooCommerce order ID.  The barcode is read from the
	 *                      order's _postnl_order_metadata (via
	 *                      Shipment_and_Return\Item_Info::get_barcode()).
	 *
	 * @return array {
	 *     JSON-decoded PostNL /parcels/v1/shipment/activatereturn response body.
	 *
	 *     @type string[] $successFulBarcodes Barcodes for which return was activated.
	 *                                        Non-empty on success.
	 *     @type array    $errorsPerBarcode {
	 *         Present when one or more barcodes failed activation.
	 *         @type array $item {
	 *             @type string $barcode Barcode that failed.
	 *             @type array  $errors  Indexed array of error records.
	 *                 @type array $error {
	 *                     @type string $description Human-readable error description,
	 *                                               shown in the admin notice.
	 *                 }
	 *         }
	 *     }
	 * }
	 *
	 * @throws \Exception If the API request fails at transport level.
	 */
	public function activate( int $order_id ): array;
}
