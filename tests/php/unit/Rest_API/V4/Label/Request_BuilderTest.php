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
		$this->assertSame( 'Jan', $payload['receiver']['contact']['firstName'], 'The given name must not land in lastName.' );
		$this->assertSame( 'Jansen', $payload['receiver']['contact']['lastName'], 'The family name must not land in firstName.' );
		$this->assertSame( 'consumer', $payload['receiver']['type'] );

		$this->assertSame( 'pdf', $payload['labelSettings']['outputType'] );
		$this->assertSame( 200, $payload['labelSettings']['resolution'] );

		$this->assertSame( '3SDEVC1234567', $payload['items'][0]['barcode'] );
		$this->assertSame( 'ORDER-1001', $payload['items'][0]['customerReferences']['shipmentReference'] );
		$this->assertSame( 2000, $payload['items'][0]['dimensions']['weight'], 'Weight must be sent in grams.' );
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
	 * @testdox build() upper-cases the country code and carries a non-NL destination through.
	 */
	public function test_receiver_country_is_upper_cased_and_carried_through(): void {
		$fields                        = $this->domestic_fields();
		$fields['receiver']['country'] = 'be';

		$payload = $this->payload( $fields );

		$this->assertSame( 'BE', $payload['receiver']['address']['countryIso'], 'A lower-case destination country must be normalized, not dropped to the NL default.' );
		$this->assertSame( 'NL', $payload['sender']['address']['countryIso'], 'The sender country must be resolved independently of the receiver.' );
	}

	/**
	 * @testdox build() falls back to NL when the country code is not a known ISO code.
	 */
	public function test_unknown_country_falls_back_to_nl(): void {
		$fields                        = $this->domestic_fields();
		$fields['receiver']['country'] = 'XX';

		$payload = $this->payload( $fields );

		$this->assertSame( 'NL', $payload['receiver']['address']['countryIso'] );
	}

	/**
	 * @testdox build() carries a non-parcel shipment type through to the payload.
	 */
	public function test_shipment_type_is_carried_through(): void {
		$fields                  = $this->domestic_fields();
		$fields['shipment_type'] = 'letterbox';

		$payload = $this->payload( $fields );

		$this->assertSame( 'letterbox', $payload['shipmentType'], 'A non-parcel shipment type must survive the build, not collapse to the parcel default.' );
	}

	/**
	 * @testdox build() carries the requested label output type and resolution through to the payload.
	 * @dataProvider label_settings_provider
	 *
	 * @param string $output_type          Requested output type.
	 * @param int    $resolution           Requested resolution.
	 * @param string $expected_output_type Output type expected on the wire.
	 * @param int    $expected_resolution  Resolution expected on the wire.
	 */
	public function test_label_settings_are_carried_through( string $output_type, int $resolution, string $expected_output_type, int $expected_resolution ): void {
		$fields          = $this->domestic_fields();
		$fields['label'] = array(
			'output_type' => $output_type,
			'resolution'  => $resolution,
		);

		$payload = $this->payload( $fields );

		$this->assertSame( $expected_output_type, $payload['labelSettings']['outputType'] );
		$this->assertSame( $expected_resolution, $payload['labelSettings']['resolution'] );
	}

	/**
	 * Data provider for non-default label settings.
	 *
	 * Every case deliberately differs from the pdf/200 fallback, so a resolver
	 * short-circuited to its default cannot pass.
	 *
	 * @return array
	 */
	public static function label_settings_provider(): array {
		return array(
			'ZPL at 600 dpi'                  => array( 'zpl', 600, 'zpl', 600 ),
			'JPG at 300 dpi'                  => array( 'jpg', 300, 'jpg', 300 ),
			'upper-case output is lowercased' => array( 'ZPL', 600, 'zpl', 600 ),
		);
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
