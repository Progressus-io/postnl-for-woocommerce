<?php
/**
 * Interface Label_Service_Interface.
 *
 * @package PostNLWooCommerce\Rest_API\Contracts
 */

namespace PostNLWooCommerce\Rest_API\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contract for outbound shipping label generation services.
 *
 * Covers both standard parcel labels (Shipping\Client, /v1/shipment) and
 * letterbox-parcel labels (Letterbox\Client, same endpoint with a different
 * product code).  Both callers in Order\Base pass the same $post_data shape
 * and the return value is stored directly in _postnl_order_metadata['labels']
 * without further transformation.
 *
 * The interface sits at the service level: it replaces the entire
 * Order\Base::create_label() call chain (API request + file write + merge).
 * Callers in Order\Base assign the return value straight to $saved_data['labels'].
 *
 * Both the Legacy (V1) and any future V4 transport must implement this interface.
 * Callers in Order\Base never need to know which transport answered.
 */
interface Label_Service_Interface {

	/**
	 * Create a PostNL shipping label for an order and return the normalized
	 * label record ready to be stored in _postnl_order_metadata['labels'].
	 *
	 * The return shape is derived from Order\Base::create_label() and
	 * Order\Base::maybe_create_letterbox() — specifically the value those
	 * methods return after the full pipeline:
	 *   1. PostNL API call (/v1/shipment, POST, confirm=true).
	 *   2. Validation of barcode + label content in the response.
	 *   3. Base64 decode + file write of each label to POSTNL_UPLOADS_DIR.
	 *   4. Merge of multiple A6 files into one A4 sheet when label format is A4
	 *      (via Order\Base::put_label_content() + maybe_merge_labels()).
	 *
	 * The resulting array is keyed by the label-type string (e.g. 'label',
	 * 'letterbox') and assigned to $saved_data['labels'] in
	 * Order\Base::save_meta_value():
	 *   $saved_data['labels'] = $label_service->create( $post_data );
	 *
	 * Multi-collo (num_labels > 1) shipments are sent in a single API request
	 * with multiple Shipments entries grouped by GroupType '03'; the merged
	 * result is still a single entry in the returned array.
	 *
	 * @param array $post_data {
	 *     Context needed to build and send the label request.
	 *
	 *     @type \WC_Order $order          Required. WooCommerce order object.
	 *     @type array     $saved_data {
	 *         Required. Order-level saved data.
	 *         @type array  $backend   Admin-selected option flags, e.g.:
	 *                                 num_labels, insured_shipping, letterbox,
	 *                                 id_check, signature_on_delivery, etc.
	 *         @type array  $frontend  Customer-selected checkout options, e.g.
	 *                                 delivery_day_*, dropoff_points_*.
	 *     }
	 *     @type string    $main_barcode            Required. The primary barcode
	 *                                              string for the first shipment.
	 *     @type string[]  $barcodes                Required. All barcodes for
	 *                                              multi-collo; index 0 is main.
	 *     @type string    $return_barcode          Return barcode string.
	 *                                              Empty string when not applicable.
	 *     @type string    $shipping_return_barcode Shipping-return barcode string.
	 *                                              Empty string when not applicable.
	 *     @type bool      $is_return_activated     Whether return function was
	 *                                              previously activated for this order.
	 * }
	 *
	 * @return array {
	 *     Normalized label record keyed by label-type string.  This array is
	 *     stored verbatim as _postnl_order_metadata['labels'] by the caller.
	 *
	 *     @type array $label_type {
	 *         Key is the label-type string, e.g. 'label' or 'letterbox'.
	 *         Matches the parent_label_type passed internally to put_label_content().
	 *
	 *         @type string   $type         Same as the array key, e.g. 'label'.
	 *         @type string   $barcode      The main/parent barcode string, e.g.
	 *                                      '3SXXXXXXXXX'.  Read by get_tracking_link()
	 *                                      and displayed in the orders list column.
	 *         @type int      $created_at   Unix timestamp (current_time('timestamp'))
	 *                                      at the moment of label creation.
	 *         @type string   $filepath     Absolute path to the final label file on
	 *                                      disk (merged A4 PDF or single A6 file).
	 *                                      Used by download_label() and delete_label().
	 *         @type string[] $merged_files Absolute paths of the individual A6 label
	 *                                      files that were merged into $filepath.
	 *                                      Present only when label format is A4 and
	 *                                      more than one source file was merged.
	 *                                      Removed by delete_label() alongside $filepath.
	 *     }
	 * }
	 *
	 * @throws \Exception If the API request fails, the barcode is missing, the
	 *                    label content is empty, or the resulting label array is
	 *                    empty after processing.
	 */
	public function create( array $post_data ): array;
}
