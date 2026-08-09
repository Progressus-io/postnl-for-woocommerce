<?php
/**
 * Class Rest_API\V4\Label\Request_Builder file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Label;

use Postnl\Sdk\Enums\Payload\AssociatedDocumentType;
use Postnl\Sdk\Enums\Payload\Bundle;
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\Currency;
use Postnl\Sdk\Enums\Payload\DeliveryConfirmation;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\LabelResolution;
use Postnl\Sdk\Enums\Payload\MinimalAgeCheck;
use Postnl\Sdk\Enums\Payload\ReceiverType;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\CustomerReferences;
use Postnl\Sdk\RequestData\V4\Dimensions;
use Postnl\Sdk\RequestData\V4\InternationalShipment\AssociatedDocument;
use Postnl\Sdk\RequestData\V4\InternationalShipment\Content;
use Postnl\Sdk\RequestData\V4\InternationalShipment\Customs;
use Postnl\Sdk\RequestData\V4\InternationalShipment\InternationalShipmentData;
use Postnl\Sdk\RequestData\V4\LabelSettings;
use Postnl\Sdk\RequestData\V4\Services;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use Postnl\Sdk\ResponseData\V4\ShippingItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Request_Builder
 *
 * Pure translator from a flat, already-parsed field array into a V4
 * ShipmentDeliveryRequest DTO for the /shipment/delivery/v4/labelconfirm
 * endpoint. It performs no WooCommerce or settings access, so the DTO shape
 * can be asserted in isolation.
 *
 * Scope: single parcel, single collo, optional delivery Services
 * (insurance, signature/delivery-code confirmation, stated-address-only,
 * return-when-not-home and their combinations) for domestic shipments, plus
 * EU/ROW international shipments carrying an InternationalShipmentData block
 * (service bundle + customs declaration). The customer number/code are
 * injected into the sender by the SDK client
 * (ClientBuilder::withCustomerCredentials), so they are deliberately absent
 * here. CollectionLocation and MessageID are V1-only and never emitted.
 *
 * @since   6.0.0
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */
class Request_Builder {

	/**
	 * Build the ShipmentDeliveryRequest from parsed order fields.
	 *
	 * @param array $fields {
	 *     Flat, pre-parsed values sourced from the legacy Shipping\Item_Info.
	 *
	 *     @type array  $sender        Store address: company, street, house_number,
	 *                                  house_number_ext, postcode, city, country.
	 *     @type array  $receiver      Recipient: company, first_name, last_name, street,
	 *                                  house_number, house_number_ext, postcode, city,
	 *                                  country, email, phone.
	 *     @type string $shipment_type V4 ShipmentType value, e.g. 'parcel'.
	 *     @type int    $weight_gr     Total shipment weight in grams.
	 *     @type string $reference     Merchant shipment reference (order number).
	 *     @type string $barcode       Pre-issued barcode to confirm; empty to let
	 *                                  labelconfirm auto-issue one. Ignored when
	 *                                  $barcodes is provided.
	 *     @type array  $barcodes      Pre-issued barcodes, one per collo, for a
	 *                                  multi-collo shipment. Falls back to a single
	 *                                  item built from $barcode when absent.
	 *     @type int    $num_labels    Collo count (1-10). Governs the item count only
	 *                                  when neither $barcodes nor $barcode carries a
	 *                                  pre-issued barcode; the barcodes win otherwise.
	 *     @type array  $label         Label output: output_type (pdf|zpl|jpg|gif|png)
	 *                                  and resolution (200|300|600).
	 *     @type array  $services      Optional resolved service flags: deliveryConfirmation
	 *                                  ('signature'|'deliverycode'), insuredValue (float),
	 *                                  statedAddressOnly (bool), returnWhenNotHome (bool).
	 *                                  minimalAgeCheck ('16+'|'18+') is accepted but no
	 *                                  V4_Mapper row emits it yet — every id_check
	 *                                  combination is still Legacy-only, so ID Check
	 *                                  orders do not reach V4 at all.
	 *     @type array  $international  Optional EU/ROW data: bundle ('track_trace'|'insured'|
	 *                                  'insured_plus') and customs (currency, transaction_code,
	 *                                  associated_document{type,number}, sender_identification,
	 *                                  receiver_identification, content[]{description, quantity,
	 *                                  weight, value, country_of_origin, hs_code}).
	 * }
	 * @return ShipmentDeliveryRequest
	 */
	public static function build( array $fields ): ShipmentDeliveryRequest {
		$sender_fields   = $fields['sender'] ?? array();
		$receiver_fields = $fields['receiver'] ?? array();
		$label_fields    = $fields['label'] ?? array();

		$sender = ShipmentParty::asSender(
			address: self::address( $sender_fields )
		);

		$receiver = ShipmentParty::asReceiver(
			address: self::address( $receiver_fields ),
			contact: self::contact( $receiver_fields ),
			receiverType: ReceiverType::Consumer
		);

		$label_settings = new LabelSettings(
			outputType: self::output_type( (string) ( $label_fields['output_type'] ?? 'pdf' ) ),
			resolution: self::resolution( (int) ( $label_fields['resolution'] ?? 200 ) )
		);

		return new ShipmentDeliveryRequest(
			sender: $sender,
			receiver: $receiver,
			labelSettings: $label_settings,
			shipmentType: self::shipment_type( (string) ( $fields['shipment_type'] ?? 'parcel' ) ),
			services: self::services( $fields['services'] ?? array() ),
			internationalShipmentData: self::international( $fields['international'] ?? array() ),
			items: self::items( $fields )
		);
	}

	/**
	 * Build one ShippingItem per collo.
	 *
	 * A multi-collo shipment sends one item per barcode in a single request; the
	 * SDK derives itemCount from the item count. Each collo carries the same
	 * shipment reference and weight, matching the legacy per-shipment payload.
	 *
	 * When no barcode is pre-issued the count comes from num_labels instead, so an
	 * order that lets labelconfirm issue its barcodes is not collapsed into one
	 * collo. That labelconfirm auto-issues a barcode per barcode-less item is an
	 * API assumption the SDK docs do not cover (flip-checklist Q15c); if it is
	 * unsupported the request fails loudly, which is the point — the silent
	 * single-collo collapse is what this replaces.
	 *
	 * @param array $fields Builder input keyed as documented on build().
	 * @return ShippingItem[]
	 */
	private static function items( array $fields ): array {
		$barcodes = array_values( array_filter( (array) ( $fields['barcodes'] ?? array() ), 'is_scalar' ) );

		if ( empty( $barcodes ) ) {
			$single      = (string) ( $fields['barcode'] ?? '' );
			$collo_count = ( '' === $single ) ? max( 1, (int) ( $fields['num_labels'] ?? 1 ) ) : 1;
			$barcodes    = array_fill( 0, $collo_count, $single );
		}

		$reference = self::maybe_null( (string) ( $fields['reference'] ?? '' ) );
		$weight_gr = max( 1, (int) ( $fields['weight_gr'] ?? 0 ) );

		$items = array();
		foreach ( $barcodes as $barcode ) {
			$items[] = new ShippingItem(
				barcode: self::maybe_null( (string) $barcode ),
				customerReferences: new CustomerReferences(
					shipmentReference: $reference
				),
				dimensions: new Dimensions(
					weightGr: $weight_gr
				)
			);
		}

		return $items;
	}

	/**
	 * Build the InternationalShipmentData block for an EU/ROW shipment.
	 *
	 * Returns null for a domestic shipment (no international data supplied), so
	 * the request omits the block entirely. The service bundle is carried here —
	 * not on Services, which has no bundle field — and the customs declaration is
	 * attached when the shipment carries content items.
	 *
	 * @param array $data International data keyed as documented on build().
	 * @return InternationalShipmentData|null
	 */
	private static function international( array $data ): ?InternationalShipmentData {
		if ( empty( $data ) ) {
			return null;
		}

		$bundle  = Bundle::tryFrom( (string) ( $data['bundle'] ?? '' ) );
		$customs = self::customs( $data['customs'] ?? array() );

		if ( null === $bundle && null === $customs ) {
			return null;
		}

		return new InternationalShipmentData(
			customs: $customs,
			bundle: $bundle
		);
	}

	/**
	 * Build the Customs declaration from the shipment's customs fields.
	 *
	 * Returns null when there are no content items to declare, so a shipment
	 * that needs no customs block omits it. Mirrors the legacy Customs block:
	 * transactionCode 11 with an invoice associatedDocument, the order currency,
	 * a trusted-shipper senderIdentification when the merchant supplied one, and
	 * one Content entry per order line.
	 *
	 * @param array $data Customs fields keyed as documented on build().
	 * @return Customs|null
	 */
	private static function customs( array $data ): ?Customs {
		$content = self::customs_content( $data['content'] ?? array() );

		if ( empty( $content ) ) {
			return null;
		}

		return new Customs(
			content: $content,
			transactionCode: self::maybe_null( (string) ( $data['transaction_code'] ?? '' ) ),
			currency: Currency::tryFrom( strtoupper( (string) ( $data['currency'] ?? '' ) ) ),
			associatedDocument: self::associated_document( $data['associated_document'] ?? array() ),
			senderIdentification: self::maybe_null( (string) ( $data['sender_identification'] ?? '' ) ),
			receiverIdentification: self::maybe_null( (string) ( $data['receiver_identification'] ?? '' ) )
		);
	}

	/**
	 * Translate the per-line customs items into Content DTOs.
	 *
	 * @param array $items Customs content items keyed as documented on build().
	 * @return Content[]
	 */
	private static function customs_content( array $items ): array {
		$content = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}

			$content[] = new Content(
				description: self::maybe_null( self::truncate( (string) ( $item['description'] ?? '' ), 35 ) ),
				quantity: max( 1, (int) ( $item['quantity'] ?? 1 ) ),
				weight: max( 1, (int) ( $item['weight'] ?? 0 ) ),
				value: (float) ( $item['value'] ?? 0 ),
				countryOfOrigin: Country::tryFrom( strtoupper( (string) ( $item['country_of_origin'] ?? '' ) ) ),
				hsTariffNumber: self::maybe_null( (string) ( $item['hs_code'] ?? '' ) )
			);
		}

		return $content;
	}

	/**
	 * Build the customs associatedDocument, mandatory for transactionCode 11/21/32.
	 *
	 * @param array $data Associated-document fields: type and number.
	 * @return AssociatedDocument|null
	 */
	private static function associated_document( array $data ): ?AssociatedDocument {
		if ( empty( $data ) ) {
			return null;
		}

		$type   = AssociatedDocumentType::tryFrom( (string) ( $data['type'] ?? '' ) );
		$number = self::maybe_null( (string) ( $data['number'] ?? '' ) );

		if ( null === $type && null === $number ) {
			return null;
		}

		return new AssociatedDocument(
			type: $type,
			number: $number
		);
	}

	/**
	 * Translate resolved service flags into a V4 Services DTO.
	 *
	 * Returns null when no recognised service is present, so the request omits
	 * the Services block entirely for a plain parcel.
	 *
	 * @param array $flags Resolved service flags keyed as documented on build().
	 * @return Services|null
	 */
	private static function services( array $flags ): ?Services {
		$confirmation = DeliveryConfirmation::tryFrom( (string) ( $flags['deliveryConfirmation'] ?? '' ) );
		$age_check    = MinimalAgeCheck::tryFrom( (string) ( $flags['minimalAgeCheck'] ?? '' ) );
		// isset (not ! empty) so a legitimately zero insured value is still sent, matching
		// the legacy Amounts block, which emits Value 0 rather than omitting the block.
		$insured     = isset( $flags['insuredValue'] ) ? (float) $flags['insuredValue'] : null;
		$stated_only = ! empty( $flags['statedAddressOnly'] ) ? true : null;
		$return_home = ! empty( $flags['returnWhenNotHome'] ) ? true : null;

		if ( null === $confirmation && null === $age_check && null === $insured
			&& null === $stated_only && null === $return_home ) {
			return null;
		}

		return new Services(
			statedAddressOnly: $stated_only,
			returnWhenNotHome: $return_home,
			minimalAgeCheck: $age_check,
			deliveryConfirmation: $confirmation,
			insuredValue: $insured
		);
	}

	/**
	 * Translate an address field array into a V4 Address DTO.
	 *
	 * @param array $fields Address fields keyed as documented on build().
	 * @return Address
	 */
	private static function address( array $fields ): Address {
		return new Address(
			countryIso: self::country( (string) ( $fields['country'] ?? '' ) ),
			houseNumber: self::maybe_null( (string) ( $fields['house_number'] ?? '' ) ),
			postalCode: self::maybe_null( (string) ( $fields['postcode'] ?? '' ) ),
			companyName: self::maybe_null( (string) ( $fields['company'] ?? '' ) ),
			street: self::maybe_null( (string) ( $fields['street'] ?? '' ) ),
			houseNumberAddition: self::maybe_null( (string) ( $fields['house_number_ext'] ?? '' ) ),
			city: self::maybe_null( (string) ( $fields['city'] ?? '' ) )
		);
	}

	/**
	 * Translate recipient contact fields into a V4 Contact DTO.
	 *
	 * @param array $fields Receiver fields keyed as documented on build().
	 * @return Contact
	 */
	private static function contact( array $fields ): Contact {
		return new Contact(
			email: self::maybe_null( (string) ( $fields['email'] ?? '' ) ),
			firstName: self::maybe_null( (string) ( $fields['first_name'] ?? '' ) ),
			lastName: self::maybe_null( (string) ( $fields['last_name'] ?? '' ) ),
			mobileNumber: self::maybe_null( (string) ( $fields['phone'] ?? '' ) ),
			companyName: self::maybe_null( (string) ( $fields['company'] ?? '' ) )
		);
	}

	/**
	 * Resolve a country code into the SDK Country enum, defaulting to NL.
	 *
	 * @param string $code Two-letter ISO country code.
	 * @return Country
	 */
	private static function country( string $code ): Country {
		return Country::tryFrom( strtoupper( $code ) ) ?? Country::NL;
	}

	/**
	 * Resolve a shipment-type string into the SDK ShipmentType enum.
	 *
	 * @param string $type ShipmentType value, e.g. 'parcel'.
	 * @return ShipmentType
	 */
	private static function shipment_type( string $type ): ShipmentType {
		return ShipmentType::tryFrom( $type ) ?? ShipmentType::Parcel;
	}

	/**
	 * Resolve a label output-type string into the SDK LabelOutputType enum.
	 *
	 * @param string $output_type One of pdf|zpl|jpg|gif|png.
	 * @return LabelOutputType
	 */
	private static function output_type( string $output_type ): LabelOutputType {
		return LabelOutputType::tryFrom( strtolower( $output_type ) ) ?? LabelOutputType::PDF;
	}

	/**
	 * Resolve a resolution integer into the SDK LabelResolution enum.
	 *
	 * @param int $resolution One of 200|300|600.
	 * @return LabelResolution
	 */
	private static function resolution( int $resolution ): LabelResolution {
		return LabelResolution::tryFrom( $resolution ) ?? LabelResolution::DPI_200;
	}

	/**
	 * Map a legacy combined printer-type string to discrete V4 label settings.
	 *
	 * The legacy setting stores values such as 'GraphicFile|PDF' or
	 * 'Zebra|Generic ZPL II 600 dpi'; V4 wants a separate output type and
	 * resolution. PDF carries no dpi and falls back to 200.
	 *
	 * @param string $printer_type Legacy combined printer-type string.
	 * @return array{output_type:string,resolution:int}
	 */
	public static function printer_type_to_label_settings( string $printer_type ): array {
		$output_type = 'pdf';
		foreach ( array( 'zpl', 'jpg', 'gif', 'png', 'pdf' ) as $candidate ) {
			if ( false !== stripos( $printer_type, $candidate ) ) {
				$output_type = $candidate;
				break;
			}
		}

		$resolution = 200;
		if ( preg_match( '/(\d{3})\s*dpi/i', $printer_type, $matches ) ) {
			$resolution = (int) $matches[1];
		}

		return array(
			'output_type' => $output_type,
			'resolution'  => $resolution,
		);
	}

	/**
	 * Return null for an empty string so the DTO omits the field entirely.
	 *
	 * @param string $value Candidate value.
	 * @return string|null
	 */
	private static function maybe_null( string $value ): ?string {
		return '' === $value ? null : $value;
	}

	/**
	 * Truncate a string to a maximum length, respecting multibyte characters.
	 *
	 * The customs Content description is capped at 35 characters by PostNL.
	 *
	 * @param string $value  Candidate value.
	 * @param int    $length Maximum length.
	 * @return string
	 */
	private static function truncate( string $value, int $length ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $value, 0, $length )
			: substr( $value, 0, $length );
	}
}
