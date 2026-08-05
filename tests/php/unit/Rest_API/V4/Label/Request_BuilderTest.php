<?php
/**
 * Unit tests for Rest_API\V4\Label\Request_Builder.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Label;

use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\LabelResolution;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use Postnl\Sdk\Support\PayloadMapper;
use PostNLWooCommerce\Rest_API\V4\Label\Request_Builder;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Label\Request_Builder
 */
class Request_BuilderTest extends UnitTestCase {

	/**
	 * A representative happy-path domestic parcel field set.
	 *
	 * @return array
	 */
	private function domestic_fields(): array {
		return array(
			'sender'        => array(
				'company'          => 'My Shop',
				'street'           => 'Siriusdreef',
				'house_number'     => '42',
				'house_number_ext' => '',
				'postcode'         => '2132WT',
				'city'             => 'Hoofddorp',
				'country'          => 'NL',
			),
			'receiver'      => array(
				'company'          => '',
				'first_name'       => 'Jan',
				'last_name'        => 'Jansen',
				'street'           => 'Main Street',
				'house_number'     => '9',
				'house_number_ext' => 'A',
				'postcode'         => '1234AB',
				'city'             => 'Amsterdam',
				'country'          => 'NL',
				'email'            => 'buyer@example.com',
				'phone'            => '0612345678',
			),
			'shipment_type' => 'parcel',
			'weight_gr'     => 2000,
			'reference'     => 'ORDER-1001',
			'barcode'       => '3SDEVC1234567',
			'label'         => array(
				'output_type' => 'pdf',
				'resolution'  => 200,
			),
		);
	}

	/**
	 * Serialize the built request to its wire array.
	 *
	 * @param array $fields Builder input.
	 * @return array
	 */
	private function payload( array $fields ): array {
		$request = Request_Builder::build( $fields );
		$this->assertInstanceOf( ShipmentDeliveryRequest::class, $request );

		return $request->toArray( PayloadMapper::create() );
	}

	/**
	 * @testdox build() produces a domestic parcel payload with sender, receiver, label and one collo.
	 */
	public function test_builds_domestic_parcel_payload(): void {
		$payload = $this->payload( $this->domestic_fields() );

		$this->assertSame( 'parcel', $payload['shipmentType'] );
		$this->assertSame( 1, $payload['itemCount'], 'A single collo must report itemCount 1.' );

		$this->assertSame( 'NL', $payload['sender']['address']['countryIso'] );
		$this->assertSame( '42', $payload['sender']['address']['houseNumber'] );
		$this->assertSame( 'My Shop', $payload['sender']['address']['companyName'] );

		$this->assertSame( 'NL', $payload['receiver']['address']['countryIso'] );
		$this->assertSame( '9', $payload['receiver']['address']['houseNumber'] );
		$this->assertSame( 'A', $payload['receiver']['address']['houseNumberAddition'] );
		$this->assertSame( 'buyer@example.com', $payload['receiver']['contact']['email'] );
		$this->assertSame( 'consumer', $payload['receiver']['type'] );

		$this->assertSame( 'pdf', $payload['labelSettings']['outputType'] );
		$this->assertSame( 200, $payload['labelSettings']['resolution'] );

		$this->assertSame( '3SDEVC1234567', $payload['items'][0]['barcode'] );
		$this->assertSame( 'ORDER-1001', $payload['items'][0]['customerReferences']['shipmentReference'] );
		$this->assertSame( 2000, $payload['items'][0]['dimensions']['weight'], 'Weight must be sent in grams.' );
	}

	/**
	 * @testdox build() emits one item per collo with a shared reference and weight for a multi-collo shipment.
	 */
	public function test_builds_multi_collo_items(): void {
		$fields             = $this->domestic_fields();
		$fields['barcodes'] = array( '3SDEVC1', '3SDEVC2', '3SDEVC3' );

		$payload = $this->payload( $fields );

		$this->assertSame( 3, $payload['itemCount'], 'itemCount must equal the collo count.' );
		$this->assertCount( 3, $payload['items'] );
		$this->assertSame( '3SDEVC1', $payload['items'][0]['barcode'] );
		$this->assertSame( '3SDEVC2', $payload['items'][1]['barcode'] );
		$this->assertSame( '3SDEVC3', $payload['items'][2]['barcode'] );

		foreach ( $payload['items'] as $item ) {
			$this->assertSame( 'ORDER-1001', $item['customerReferences']['shipmentReference'] );
			$this->assertSame( 2000, $item['dimensions']['weight'] );
		}
	}

	/**
	 * @testdox build() falls back to a single item built from barcode when no barcodes array is given.
	 */
	public function test_single_item_fallback_without_barcodes_array(): void {
		$payload = $this->payload( $this->domestic_fields() );

		$this->assertSame( 1, $payload['itemCount'] );
		$this->assertCount( 1, $payload['items'] );
		$this->assertSame( '3SDEVC1234567', $payload['items'][0]['barcode'] );
	}

	/**
	 * @testdox build() never emits the V1-only CollectionLocation, MessageID, Customer or product-code fields.
	 */
	public function test_omits_v1_only_fields(): void {
		$payload = $this->payload( $this->domestic_fields() );
		$json    = (string) json_encode( $payload );

		$this->assertStringNotContainsStringIgnoringCase( 'CollectionLocation', $json );
		$this->assertStringNotContainsStringIgnoringCase( 'MessageID', $json );
		$this->assertStringNotContainsString( 'ProductCodeDelivery', $json );
		$this->assertArrayNotHasKey( 'Customer', $payload );
	}

	/**
	 * @testdox build() omits the item barcode so labelconfirm auto-issues one when none is supplied.
	 */
	public function test_empty_barcode_is_omitted(): void {
		$fields            = $this->domestic_fields();
		$fields['barcode'] = '';

		$payload = $this->payload( $fields );

		$this->assertArrayNotHasKey( 'barcode', $payload['items'][0], 'An empty barcode must be dropped, not sent blank.' );
	}

	/**
	 * @testdox build() clamps a zero or missing weight to a minimum of one gram.
	 */
	public function test_weight_is_clamped_to_minimum(): void {
		$fields              = $this->domestic_fields();
		$fields['weight_gr'] = 0;

		$payload = $this->payload( $fields );

		$this->assertSame( 1, $payload['items'][0]['dimensions']['weight'] );
	}

	/**
	 * @testdox build() falls back to sane defaults for unknown enum values.
	 */
	public function test_unknown_enum_values_fall_back(): void {
		$fields                  = $this->domestic_fields();
		$fields['shipment_type'] = 'nonsense';
		$fields['label']         = array(
			'output_type' => 'bmp',
			'resolution'  => 999,
		);

		$request = Request_Builder::build( $fields );
		$payload = $request->toArray( PayloadMapper::create() );

		$this->assertSame( ShipmentType::Parcel->value, $payload['shipmentType'] );
		$this->assertSame( LabelOutputType::PDF->value, $payload['labelSettings']['outputType'] );
		$this->assertSame( LabelResolution::DPI_200->value, $payload['labelSettings']['resolution'] );
	}

	/**
	 * @testdox build() omits the Services block for a plain parcel with no service flags.
	 */
	public function test_services_omitted_when_empty(): void {
		$payload = $this->payload( $this->domestic_fields() );

		$this->assertArrayNotHasKey( 'services', $payload, 'A plain parcel must not send an empty Services block.' );
	}

	/**
	 * @testdox build() maps a signature + insured + return-when-not-home combination to the Services DTO.
	 */
	public function test_services_combination_is_mapped(): void {
		$fields             = $this->domestic_fields();
		$fields['services'] = array(
			'deliveryConfirmation' => 'signature',
			'insuredValue'         => 49.95,
			'returnWhenNotHome'    => true,
		);

		$services = $this->payload( $fields )['services'];

		$this->assertSame( 'signature', $services['deliveryConfirmation'] );
		$this->assertSame( 49.95, $services['insuredValue'] );
		$this->assertTrue( $services['returnWhenNotHome'] );
		$this->assertArrayNotHasKey( 'statedAddressOnly', $services, 'Unset flags must be omitted, not sent false.' );
	}

	/**
	 * @testdox build() maps individual service flags to their Services fields.
	 * @dataProvider service_flag_provider
	 *
	 * @param array  $services Resolved service flags.
	 * @param string $key      Expected Services payload key.
	 * @param mixed  $expected Expected value at that key.
	 */
	public function test_individual_service_flags( array $services, string $key, $expected ): void {
		$fields             = $this->domestic_fields();
		$fields['services'] = $services;

		$this->assertSame( $expected, $this->payload( $fields )['services'][ $key ] );
	}

	/**
	 * Data provider mapping a single service flag to its expected payload value.
	 *
	 * @return array
	 */
	public static function service_flag_provider(): array {
		return array(
			'signature'          => array( array( 'deliveryConfirmation' => 'signature' ), 'deliveryConfirmation', 'signature' ),
			'delivery code'      => array( array( 'deliveryConfirmation' => 'deliverycode' ), 'deliveryConfirmation', 'deliverycode' ),
			'stated address only' => array( array( 'statedAddressOnly' => true ), 'statedAddressOnly', true ),
			'return when not home' => array( array( 'returnWhenNotHome' => true ), 'returnWhenNotHome', true ),
			'insured value'      => array( array( 'insuredValue' => 12.5 ), 'insuredValue', 12.5 ),
			'age check 18+'      => array( array( 'minimalAgeCheck' => '18+' ), 'minimalAgeCheck', '18+' ),
			'age check 16+'      => array( array( 'minimalAgeCheck' => '16+' ), 'minimalAgeCheck', '16+' ),
		);
	}

	/**
	 * @testdox build() sends a zero insured value rather than omitting it, matching the legacy Amounts block.
	 */
	public function test_zero_insured_value_is_sent(): void {
		$fields             = $this->domestic_fields();
		$fields['services'] = array( 'insuredValue' => 0.0 );

		$this->assertSame( 0.0, $this->payload( $fields )['services']['insuredValue'] );
	}

	/**
	 * @testdox build() drops unrecognised service values rather than sending the Services block.
	 */
	public function test_unrecognised_service_values_omit_block(): void {
		$fields             = $this->domestic_fields();
		$fields['services'] = array(
			'deliveryConfirmation' => 'nonsense',
			'minimalAgeCheck'      => '99+',
		);

		$this->assertArrayNotHasKey( 'services', $this->payload( $fields ), 'Unknown enum values must not force an empty Services block.' );
	}

	/**
	 * @testdox build() omits the internationalShipmentData block for a domestic parcel.
	 */
	public function test_international_block_omitted_for_domestic(): void {
		$this->assertArrayNotHasKey( 'internationalShipmentData', $this->payload( $this->domestic_fields() ) );
	}

	/**
	 * A representative EU international field set with a customs declaration.
	 *
	 * @return array
	 */
	private function international_fields(): array {
		$fields                  = $this->domestic_fields();
		$fields['international']  = array(
			'bundle'  => 'insured',
			'customs' => array(
				'currency'              => 'EUR',
				'transaction_code'      => '11',
				'associated_document'   => array(
					'type'   => 'invoice',
					'number' => 'ORDER-1001',
				),
				'sender_identification' => 'VOEC-12345',
				'content'               => array(
					array(
						'description'       => 'Blue cotton t-shirt',
						'quantity'          => 2,
						'weight'            => 500,
						'value'             => 19.95,
						'country_of_origin' => 'NL',
						'hs_code'           => '610910',
					),
				),
			),
		);

		return $fields;
	}

	/**
	 * @testdox build() attaches the service bundle to internationalShipmentData, never to services.
	 */
	public function test_international_bundle_is_on_international_block(): void {
		$payload = $this->payload( $this->international_fields() );

		$this->assertSame( 'insured', $payload['internationalShipmentData']['bundle'] );
		$this->assertArrayNotHasKey( 'services', $payload, 'An international parcel with no domestic services must not send a Services block; the bundle lives on internationalShipmentData.' );
	}

	/**
	 * @testdox build() maps the customs declaration fields onto the Customs DTO.
	 */
	public function test_international_customs_is_mapped(): void {
		$customs = $this->payload( $this->international_fields() )['internationalShipmentData']['customs'];

		$this->assertSame( '11', $customs['transactionCode'] );
		$this->assertSame( 'EUR', $customs['currency'] );
		$this->assertSame( 'invoice', $customs['associatedDocument']['type'] );
		$this->assertSame( 'ORDER-1001', $customs['associatedDocument']['number'] );
		$this->assertSame( 'VOEC-12345', $customs['senderIdentification'] );
		$this->assertArrayNotHasKey( 'receiverIdentification', $customs, 'An unset receiver id must be omitted.' );

		$item = $customs['content'][0];
		$this->assertSame( 'Blue cotton t-shirt', $item['description'] );
		$this->assertSame( 2, $item['quantity'] );
		$this->assertSame( 500, $item['weight'] );
		$this->assertSame( 19.95, $item['value'] );
		$this->assertSame( 'NL', $item['countryOfOrigin'] );
		$this->assertSame( '610910', $item['hsTariffNumber'] );
	}

	/**
	 * @testdox build() truncates a customs item description to 35 characters.
	 */
	public function test_customs_description_is_truncated(): void {
		$fields = $this->international_fields();
		$fields['international']['customs']['content'][0]['description'] = str_repeat( 'a', 50 );

		$content = $this->payload( $fields )['internationalShipmentData']['customs']['content'][0];

		$this->assertSame( str_repeat( 'a', 35 ), $content['description'] );
	}

	/**
	 * @testdox build() clamps a zero customs item weight and quantity to a minimum of one.
	 */
	public function test_customs_item_weight_and_quantity_are_clamped(): void {
		$fields = $this->international_fields();
		$fields['international']['customs']['content'][0]['weight']   = 0;
		$fields['international']['customs']['content'][0]['quantity'] = 0;

		$content = $this->payload( $fields )['internationalShipmentData']['customs']['content'][0];

		$this->assertSame( 1, $content['weight'] );
		$this->assertSame( 1, $content['quantity'] );
	}

	/**
	 * @testdox build() drops an unrecognised bundle and currency rather than sending them.
	 */
	public function test_unknown_bundle_and_currency_are_omitted(): void {
		$fields                                       = $this->international_fields();
		$fields['international']['bundle']             = 'nonsense';
		$fields['international']['customs']['currency'] = 'XXX';

		$international = $this->payload( $fields )['internationalShipmentData'];

		$this->assertArrayNotHasKey( 'bundle', $international, 'An unknown bundle must not be sent.' );
		$this->assertArrayNotHasKey( 'currency', $international['customs'], 'An unknown currency must not be sent.' );
	}

	/**
	 * @testdox build() omits the Customs block when no content items are declared but keeps the bundle.
	 */
	public function test_customs_omitted_without_content(): void {
		$fields                                      = $this->international_fields();
		$fields['international']['customs']['content'] = array();

		$international = $this->payload( $fields )['internationalShipmentData'];

		$this->assertSame( 'insured', $international['bundle'] );
		$this->assertArrayNotHasKey( 'customs', $international, 'Customs with no content items must be omitted.' );
	}

	/**
	 * @testdox printer_type_to_label_settings() splits legacy combined strings into output type and resolution.
	 * @dataProvider printer_type_provider
	 *
	 * @param string $printer_type Legacy combined printer-type string.
	 * @param string $output_type  Expected output type.
	 * @param int    $resolution   Expected resolution.
	 */
	public function test_printer_type_mapping( string $printer_type, string $output_type, int $resolution ): void {
		$result = Request_Builder::printer_type_to_label_settings( $printer_type );

		$this->assertSame( $output_type, $result['output_type'] );
		$this->assertSame( $resolution, $result['resolution'] );
	}

	/**
	 * Data provider for printer-type mapping.
	 *
	 * @return array
	 */
	public static function printer_type_provider(): array {
		return array(
			'PDF (no dpi, defaults to 200)' => array( 'GraphicFile|PDF', 'pdf', 200 ),
			'ZPL 600 dpi'                   => array( 'Zebra|Generic ZPL II 600 dpi', 'zpl', 600 ),
			'JPG 300 dpi'                   => array( 'GraphicFile|JPG 300 dpi', 'jpg', 300 ),
			'GIF 200 dpi'                   => array( 'GraphicFile|GIF 200 dpi', 'gif', 200 ),
			'Empty string defaults to PDF'  => array( '', 'pdf', 200 ),
		);
	}
}
