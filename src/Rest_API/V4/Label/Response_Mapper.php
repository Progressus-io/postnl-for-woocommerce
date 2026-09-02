<?php
/**
 * Class Rest_API\V4\Label\Response_Mapper file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Label;

use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseInterface;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Response_Mapper
 *
 * Pure reader for the labelconfirm response. Extracts the auto-issued barcode
 * and the label document(s) without any WooCommerce or filesystem access, so
 * barcode capture can be asserted in isolation. File writing and merging stay
 * in the WooCommerce-bound service.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */
class Response_Mapper {

	/**
	 * Return the first shipment item from the response, or null when empty.
	 *
	 * A single domestic parcel yields exactly one shipment item.
	 *
	 * @param ShipmentDeliveryResponseInterface $response Response from labelconfirm.
	 * @return ShipmentDeliveryResponseItem|null
	 */
	public static function first_shipment_item( ShipmentDeliveryResponseInterface $response ): ?ShipmentDeliveryResponseItem {
		$items = $response->items();

		return $items->isEmpty() ? null : $items->first();
	}

	/**
	 * Return every shipment item from the response, in order.
	 *
	 * A multi-collo shipment yields one item per collo, each with its own barcode
	 * and label document(s).
	 *
	 * @param ShipmentDeliveryResponseInterface $response Response from labelconfirm.
	 * @return ShipmentDeliveryResponseItem[]
	 */
	public static function all_shipment_items( ShipmentDeliveryResponseInterface $response ): array {
		return array_values( $response->items()->all() );
	}

	/**
	 * Return the barcode issued for a shipment item.
	 *
	 * The labelconfirm endpoint auto-issues the barcode and echoes it back on
	 * the item; the fallback is used only when the response omits it (e.g. a
	 * barcode was pre-supplied on the request).
	 *
	 * @param ShipmentDeliveryResponseItem $item     Shipment item from the response.
	 * @param string                       $fallback Barcode to use when none is returned.
	 * @return string
	 */
	public static function get_barcode( ShipmentDeliveryResponseItem $item, string $fallback = '' ): string {
		if ( null !== $item->barcode && '' !== $item->barcode ) {
			return $item->barcode;
		}

		return $fallback;
	}

	/**
	 * Return the international partner barcode issued for a shipment item.
	 *
	 * For EU/ROW shipments the labelconfirm response echoes the delivering
	 * partner's barcode and id; both are empty for a domestic shipment.
	 *
	 * @param ShipmentDeliveryResponseItem $item Shipment item from the response.
	 * @return string
	 */
	public static function get_partner_barcode( ShipmentDeliveryResponseItem $item ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		return null !== $item->partnerBarcode ? $item->partnerBarcode : '';
	}

	/**
	 * Return the international partner id issued for a shipment item.
	 *
	 * @param ShipmentDeliveryResponseItem $item Shipment item from the response.
	 * @return string
	 */
	public static function get_partner_id( ShipmentDeliveryResponseItem $item ): string {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Third-party SDK DTO property.
		return null !== $item->partnerId ? $item->partnerId : '';
	}

	/**
	 * Return the non-empty Label objects attached to a shipment item.
	 *
	 * @param ShipmentDeliveryResponseItem $item Shipment item from the response.
	 * @return Label[]
	 */
	public static function get_labels( ShipmentDeliveryResponseItem $item ): array {
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
	 * Build a normalized label record matching the shape stored in
	 * _postnl_order_metadata['labels'] by the legacy path, tagged as V4.
	 *
	 * The partner barcode/id are added only for international shipments, where the
	 * labelconfirm response returns them; they are omitted for domestic labels.
	 *
	 * @param string $type            Label type slug, e.g. 'label'.
	 * @param string $barcode         Barcode for this label.
	 * @param string $filepath        Absolute path to the written label file.
	 * @param string $partner_barcode Optional international partner barcode.
	 * @param string $partner_id      Optional international partner id.
	 * @return array<string,mixed>
	 */
	public static function to_label_record( string $type, string $barcode, string $filepath, string $partner_barcode = '', string $partner_id = '' ): array {
		$record = array(
			'type'        => $type,
			'barcode'     => $barcode,
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested -- Mirrors the legacy label record's created_at timestamp stored in order meta.
			'created_at'  => current_time( 'timestamp' ),
			'filepath'    => $filepath,
			'api_version' => 'v4',
		);

		if ( '' !== $partner_barcode ) {
			$record['partner_barcode'] = $partner_barcode;
		}

		if ( '' !== $partner_id ) {
			$record['partner_id'] = $partner_id;
		}

		return $record;
	}
}
