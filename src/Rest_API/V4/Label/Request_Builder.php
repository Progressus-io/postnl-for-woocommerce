<?php
/**
 * Class Rest_API\V4\Label\Request_Builder file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Label;

use Postnl\Sdk\Enums\Payload\Country;
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
 * Scope: single domestic parcel, single collo, optional delivery Services
 * (insurance, signature/delivery-code confirmation, stated-address-only,
 * return-when-not-home and their combinations). The customer number/code are
 * injected into the sender by the SDK client
 * (ClientBuilder::withCustomerCredentials), so they are deliberately absent
 * here. CollectionLocation and MessageID are V1-only and never emitted.
 *
 * @since   5.9.9
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
	 *                                  labelconfirm auto-issue one.
	 *     @type array  $label         Label output: output_type (pdf|zpl|jpg|gif|png)
	 *                                  and resolution (200|300|600).
	 *     @type array  $services      Optional resolved service flags: deliveryConfirmation
	 *                                  ('signature'|'deliverycode'), insuredValue (float),
	 *                                  statedAddressOnly (bool), returnWhenNotHome (bool),
	 *                                  minimalAgeCheck ('16+'|'18+').
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

		$item = new ShippingItem(
			barcode: self::maybe_null( (string) ( $fields['barcode'] ?? '' ) ),
			customerReferences: new CustomerReferences(
				shipmentReference: self::maybe_null( (string) ( $fields['reference'] ?? '' ) )
			),
			dimensions: new Dimensions(
				weightGr: max( 1, (int) ( $fields['weight_gr'] ?? 0 ) )
			)
		);

		return new ShipmentDeliveryRequest(
			sender: $sender,
			receiver: $receiver,
			labelSettings: $label_settings,
			shipmentType: self::shipment_type( (string) ( $fields['shipment_type'] ?? 'parcel' ) ),
			services: self::services( $fields['services'] ?? array() ),
			items: array( $item )
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
		$confirmation = isset( $flags['deliveryConfirmation'] )
			? DeliveryConfirmation::tryFrom( (string) $flags['deliveryConfirmation'] )
			: null;
		$age_check    = isset( $flags['minimalAgeCheck'] )
			? MinimalAgeCheck::tryFrom( (string) $flags['minimalAgeCheck'] )
			: null;
		$insured      = isset( $flags['insuredValue'] ) ? (float) $flags['insuredValue'] : null;
		$stated_only  = ! empty( $flags['statedAddressOnly'] ) ? true : null;
		$return_home  = ! empty( $flags['returnWhenNotHome'] ) ? true : null;

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
}
