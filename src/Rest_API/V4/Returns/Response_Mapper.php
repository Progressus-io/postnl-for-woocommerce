<?php
/**
 * Class Rest_API\V4\Returns\Response_Mapper file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Returns;

use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\ResponseData\V4\ReturnShippingItem;
use Postnl\Sdk\Service\ReturnShipment\V4\Response\GenerateReturnResponseInterface;

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
	 * @param GenerateReturnResponseInterface $response Response from return/generate.
	 * @return ReturnShippingItem|null
	 */
	public static function first_return_item( GenerateReturnResponseInterface $response ): ?ReturnShippingItem {
		$items = $response->items();

		return $items->isEmpty() ? null : $items->first();
	}

	/**
	 * Return the barcode issued for a return item.
	 *
	 * Prefers the item barcode, then the returnBarcode, then the supplied
	 * fallback (used only when the response omits both).
	 *
	 * @param ReturnShippingItem $item     Return item from the response.
	 * @param string             $fallback Barcode to use when none is returned.
	 * @return string
	 */
	public static function get_barcode( ReturnShippingItem $item, string $fallback = '' ): string {
		if ( null !== $item->barcode && '' !== $item->barcode ) {
			return $item->barcode;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		if ( null !== $item->returnBarcode && '' !== $item->returnBarcode ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
			return $item->returnBarcode;
		}

		return $fallback;
	}

	/**
	 * Return the international partner barcode issued for a return item.
	 *
	 * @param ReturnShippingItem $item Return item from the response.
	 * @return string
	 */
	public static function get_partner_barcode( ReturnShippingItem $item ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		return null !== $item->partnerBarcode ? $item->partnerBarcode : '';
	}

	/**
	 * Return the international partner id issued for a return item.
	 *
	 * @param ReturnShippingItem $item Return item from the response.
	 * @return string
	 */
	public static function get_partner_id( ReturnShippingItem $item ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		return null !== $item->partnerId ? $item->partnerId : '';
	}

	/**
	 * Return the non-empty Label objects attached to a return item.
	 *
	 * @param ReturnShippingItem $item Return item from the response.
	 * @return Label[]
	 */
	public static function get_labels( ReturnShippingItem $item ): array {
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
