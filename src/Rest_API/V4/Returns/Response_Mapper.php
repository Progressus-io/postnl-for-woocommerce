<?php
/**
 * Class Rest_API\V4\Returns\Response_Mapper file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Returns;

use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\Service\ReturnShipment\Response\ReturnShipmentResponseInterface;
use Postnl\Sdk\Service\ReturnShipment\Response\ReturnShipmentResponseItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Response_Mapper
 *
 * Pure reader for the return/generate response. Extracts the issued return
 * barcode and the label document(s) without any WooCommerce or filesystem
 * access, so barcode capture can be asserted in isolation. File writing and
 * merging stay in the WooCommerce-bound service.
 *
 * @since   5.9.10
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */
class Response_Mapper {

	/**
	 * Return the first return item from the response, or null when empty.
	 *
	 * @param ReturnShipmentResponseInterface $response Response from return/generate.
	 * @return ReturnShipmentResponseItem|null
	 */
	public static function first_return_item( ReturnShipmentResponseInterface $response ): ?ReturnShipmentResponseItem {
		$items = $response->items();

		return $items->isEmpty() ? null : $items->first();
	}

	/**
	 * Return the barcode issued for a return item.
	 *
	 * Prefers the item barcode, then the supplied fallback (used only when the
	 * response omits it). The return API's ResponseItem no longer carries a
	 * separate returnBarcode (removed from the schema in SDK v2.0.0); the return
	 * barcode is the item barcode.
	 *
	 * @param ReturnShipmentResponseItem $item     Return item from the response.
	 * @param string                     $fallback Barcode to use when none is returned.
	 * @return string
	 */
	public static function get_barcode( ReturnShipmentResponseItem $item, string $fallback = '' ): string {
		if ( null !== $item->barcode && '' !== $item->barcode ) {
			return $item->barcode;
		}

		return $fallback;
	}

	/**
	 * Return the non-empty Label objects attached to a return item.
	 *
	 * @param ReturnShipmentResponseItem $item Return item from the response.
	 * @return Label[]
	 */
	public static function get_labels( ReturnShipmentResponseItem $item ): array {
		if ( null === $item->labels ) {
			return array();
		}

		return array_values(
			array_filter(
				$item->labels->all(),
				static function ( Label $label ): bool {
					return ! $label->isEmpty();
				}
			)
		);
	}

	/**
	 * Decode a label's base64 document content.
	 *
	 * @param Label $label Label object from the response.
	 * @return string Raw (decoded) label bytes, or empty string when absent.
	 */
	public static function decode_content( Label $label ): string {
		if ( null === $label->label || '' === $label->label ) {
			return '';
		}

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Decoding a PostNL label document returned base64-encoded by the SDK.
		$decoded = base64_decode( $label->label, true );

		return false === $decoded ? '' : $decoded;
	}

	/**
	 * Build a normalized return-label record matching the shape stored in
	 * _postnl_order_metadata['labels']['return-label'] by the legacy path,
	 * tagged as V4.
	 *
	 * @param string $barcode  Barcode for this return label.
	 * @param string $filepath Absolute path to the written label file.
	 * @return array{type:string,barcode:string,created_at:int,filepath:string,api_version:string}
	 */
	public static function to_label_record( string $barcode, string $filepath ): array {
		return array(
			'type'        => 'return-label',
			'barcode'     => $barcode,
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Mirrors the legacy label record's created_at timestamp stored in order meta.
			'created_at'  => current_time( 'timestamp' ),
			'filepath'    => $filepath,
			'api_version' => 'v4',
		);
	}
}
