<?php
/**
 * Class Rest_API\V4\Returns\Request_Builder file.
 *
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Rest_API\V4\Returns;

use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\LabelPrintMethod;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\CustomerReferences;
use Postnl\Sdk\RequestData\V4\Dimensions;
use Postnl\Sdk\RequestData\V4\LabelSettings;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\ResponseData\V4\ShippingItem;
use Postnl\Sdk\Service\ReturnShipment\V4\Request\ReturnShipmentRequest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Request_Builder
 *
 * Pure translator from a flat, already-parsed field array into a V4
 * ReturnShipmentRequest DTO for the /shipment/delivery/v4/return/generate
 * endpoint. It performs no WooCommerce or settings access, so the DTO shape can
 * be asserted in isolation.
 *
 * Scope: the retail-print (retailPrint) NL flow. PostNL confirmed (2026-05-21)
 * the three legacy return mechanisms collapse onto this one endpoint,
 * differentiated by printMethod and the receiver address:
 *
 * - printMethod is retailPrint for NL-origin returns (consumerPrint is a
 *   BE-origin follow-on, left unimplemented).
 * - The sender is the consumer returning the item; the receiver is the merchant,
 *   whose address encodes reply-number (Antwoordnummer) vs return-to-home.
 * - returnPeriod is omitted (the V4 default is 35 days and PostNL do not want it
 *   differentiated) and there are no additional services for return products, so
 *   no ReturnOptions block is emitted.
 *
 * The merchant's customerNumber/customerCode are injected into the receiver by
 * the SDK client (ClientBuilder::withCustomerCredentials), so they are
 * deliberately absent here.
 *
 * @since   5.9.10
 * @package PostNLWooCommerce\Rest_API\V4\Returns
 */
class Request_Builder {

	/**
	 * Build the ReturnShipmentRequest from parsed order/settings fields.
	 *
	 * @param array $fields {
	 *     Flat, pre-parsed values.
	 *
	 *     @type array  $sender       Consumer returning the item: company, first_name,
	 *                                last_name, street, house_number, house_number_ext,
	 *                                postcode, city, country, email, phone.
	 *     @type array  $receiver     Merchant return address (reply-number or home):
	 *                                company, street, house_number, house_number_ext,
	 *                                postcode, city, country.
	 *     @type string $print_method LabelPrintMethod value, e.g. 'retailPrint'.
	 *     @type array  $label        Label output: output_type (pdf|zpl|jpg|gif|png).
	 *     @type string $barcode      Pre-issued return barcode to confirm; empty to let
	 *                                the endpoint auto-issue one.
	 *     @type string $reference    Merchant shipment reference (order number).
	 *     @type int    $weight_gr    Total shipment weight in grams.
	 * }
	 * @return ReturnShipmentRequest
	 */
	public static function build( array $fields ): ReturnShipmentRequest {
		$sender_fields   = $fields['sender'] ?? array();
		$receiver_fields = $fields['receiver'] ?? array();
		$label_fields    = $fields['label'] ?? array();

		$sender = ShipmentParty::asReturnSender(
			address: self::address( $sender_fields ),
			contact: self::contact( $sender_fields )
		);

		$receiver = ShipmentParty::asReturnReceiver(
			address: self::address( $receiver_fields )
		);

		$label_settings = new LabelSettings(
			outputType: self::output_type( (string) ( $label_fields['output_type'] ?? 'pdf' ) ),
			printMethod: self::print_method( (string) ( $fields['print_method'] ?? 'retailPrint' ) )
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

		return new ReturnShipmentRequest(
			sender: $sender,
			receiver: $receiver,
			labelSettings: $label_settings,
			shipmentType: ShipmentType::Parcel,
			items: array( $item )
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
	 * Translate consumer contact fields into a V4 Contact DTO.
	 *
	 * @param array $fields Sender fields keyed as documented on build().
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
	 * Resolve a print-method string into the SDK LabelPrintMethod enum.
	 *
	 * Defaults to retailPrint — the only method valid for the NL-origin flow.
	 *
	 * @param string $print_method LabelPrintMethod value, e.g. 'retailPrint'.
	 * @return LabelPrintMethod
	 */
	private static function print_method( string $print_method ): LabelPrintMethod {
		return LabelPrintMethod::tryFrom( $print_method ) ?? LabelPrintMethod::Retail;
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
	 * Return null for an empty string so the DTO omits the field entirely.
	 *
	 * @param string $value Candidate value.
	 * @return string|null
	 */
	private static function maybe_null( string $value ): ?string {
		return '' === $value ? null : $value;
	}
}
