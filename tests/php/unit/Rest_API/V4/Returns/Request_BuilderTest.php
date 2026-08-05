<?php
/**
 * Unit tests for Rest_API\V4\Returns\Request_Builder.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Returns;

use Postnl\Sdk\Enums\Payload\LabelPrintMethod;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\Service\ReturnShipment\V4\Request\ReturnShipmentRequest;
use Postnl\Sdk\Support\PayloadMapper;
use PostNLWooCommerce\Rest_API\V4\Returns\Request_Builder;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Returns\Request_Builder
 */
class Request_BuilderTest extends UnitTestCase {

	/**
	 * A representative NL retailPrint return field set with a return-to-home receiver.
	 *
	 * @return array
	 */
	private function return_fields(): array {
		return array(
			'sender'       => array(
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
			'receiver'     => array(
				'company'          => 'My Shop',
				'street'           => 'Siriusdreef',
				'house_number'     => '42',
				'house_number_ext' => '',
				'postcode'         => '2132WT',
				'city'             => 'Hoofddorp',
				'country'          => 'NL',
			),
			'print_method' => 'retailPrint',
			'barcode'      => '3SDEVCRET123',
			'reference'    => 'ORDER-1001',
			'weight_gr'    => 1500,
			'label'        => array(
				'output_type' => 'jpg',
			),
		);
	}

	/**
	 * A reply-number (Antwoordnummer) receiver field set.
	 *
	 * @return array
	 */
	private function reply_number_fields(): array {
		$fields             = $this->return_fields();
		$fields['receiver'] = array(
			'company'          => 'My Shop',
			'street'           => 'Antwoordnummer',
			'house_number'     => '12345',
			'house_number_ext' => '',
			'postcode'         => '1000AA',
			'city'             => 'Amsterdam',
			'country'          => 'NL',
		);

		return $fields;
	}

	/**
	 * Serialize the built request to its wire array.
	 *
	 * @param array $fields Builder input.
	 * @return array
	 */
	private function payload( array $fields ): array {
		$request = Request_Builder::build( $fields );
		$this->assertInstanceOf( ReturnShipmentRequest::class, $request );

		return $request->toArray( PayloadMapper::create() );
	}

	/**
	 * @testdox build() produces a retailPrint parcel return with return sender and receiver.
	 */
	public function test_builds_retail_print_return(): void {
		$payload = $this->payload( $this->return_fields() );

		$this->assertSame( 'parcel', $payload['shipmentType'] );
		$this->assertSame( LabelPrintMethod::Retail->value, $payload['labelSettings']['printMethod'] );
		$this->assertSame( 'jpg', $payload['labelSettings']['outputType'] );

		// Sender is the consumer returning the item, with contact for track & trace.
		$this->assertSame( 'NL', $payload['sender']['address']['countryIso'] );
		$this->assertSame( '9', $payload['sender']['address']['houseNumber'] );
		$this->assertSame( 'buyer@example.com', $payload['sender']['contact']['email'] );

		// Receiver is the merchant return address.
		$this->assertSame( 'My Shop', $payload['receiver']['address']['companyName'] );
		$this->assertSame( '42', $payload['receiver']['address']['houseNumber'] );

		$this->assertSame( '3SDEVCRET123', $payload['items'][0]['barcode'] );
		$this->assertSame( 'ORDER-1001', $payload['items'][0]['customerReferences']['shipmentReference'] );
		$this->assertSame( 1500, $payload['items'][0]['dimensions']['weight'], 'Weight must be sent in grams.' );
	}

	/**
	 * @testdox build() defaults the print method to retailPrint for an unknown value.
	 */
	public function test_print_method_defaults_to_retail(): void {
		$fields                 = $this->return_fields();
		$fields['print_method'] = 'nonsense';

		$this->assertSame( LabelPrintMethod::Retail->value, $this->payload( $fields )['labelSettings']['printMethod'] );
	}

	/**
	 * @testdox build() omits the returnOptions block, so no returnPeriod or service flags are sent.
	 */
	public function test_return_options_omitted(): void {
		$payload = $this->payload( $this->return_fields() );

		$this->assertArrayNotHasKey( 'returnOptions', $payload, 'returnPeriod is omitted (V4 default 35 days) and there are no return services.' );
		$json = (string) json_encode( $payload );
		$this->assertStringNotContainsStringIgnoringCase( 'returnPeriod', $json );
	}

	/**
	 * @testdox build() never injects the merchant customer number/code (the SDK client adds them).
	 */
	public function test_receiver_omits_customer_credentials(): void {
		$payload = $this->payload( $this->return_fields() );

		$this->assertArrayNotHasKey( 'customerNumber', $payload['receiver'] );
		$this->assertArrayNotHasKey( 'customerCode', $payload['receiver'] );
	}

	/**
	 * @testdox build() encodes a reply-number (Antwoordnummer) receiver as a valid address.
	 */
	public function test_reply_number_addressing_is_valid(): void {
		$address = $this->payload( $this->reply_number_fields() )['receiver']['address'];

		$this->assertSame( 'Antwoordnummer', $address['street'] );
		$this->assertSame( '12345', $address['houseNumber'] );
		$this->assertSame( '1000AA', $address['postalCode'] );
		$this->assertSame( 'Amsterdam', $address['city'] );
	}

	/**
	 * @testdox build() encodes a return-to-home receiver as a valid address.
	 */
	public function test_return_to_home_addressing_is_valid(): void {
		$address = $this->payload( $this->return_fields() )['receiver']['address'];

		$this->assertSame( 'Siriusdreef', $address['street'] );
		$this->assertSame( '42', $address['houseNumber'] );
		$this->assertSame( '2132WT', $address['postalCode'] );
		$this->assertSame( 'Hoofddorp', $address['city'] );
	}

	/**
	 * @testdox build() omits the item barcode so the endpoint auto-issues one when none is supplied.
	 */
	public function test_empty_barcode_is_omitted(): void {
		$fields            = $this->return_fields();
		$fields['barcode'] = '';

		$this->assertArrayNotHasKey( 'barcode', $this->payload( $fields )['items'][0], 'An empty barcode must be dropped, not sent blank.' );
	}

	/**
	 * @testdox build() clamps a zero or missing weight to a minimum of one gram.
	 */
	public function test_weight_is_clamped_to_minimum(): void {
		$fields              = $this->return_fields();
		$fields['weight_gr'] = 0;

		$this->assertSame( 1, $this->payload( $fields )['items'][0]['dimensions']['weight'] );
	}

	/**
	 * @testdox build() falls back to PDF for an unknown output type.
	 */
	public function test_unknown_output_type_falls_back_to_pdf(): void {
		$fields          = $this->return_fields();
		$fields['label'] = array( 'output_type' => 'bmp' );

		$this->assertSame( 'pdf', $this->payload( $fields )['labelSettings']['outputType'] );
	}
}
