<?php
/**
 * Sandbox tests for Rest_API\V4\Label\Request_Builder.
 *
 * @package PostNLWooCommerce\Tests\Sandbox
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Sandbox;

use Postnl\Sdk\Support\PayloadMapper;
use PostNLWooCommerce\Rest_API\V4\Label\Request_Builder;
use PostNLWooCommerce\Tests\Support\Recording_Http_Client;
use PostNLWooCommerce\Tests\Support\Sandbox_Test_Case;
use PostNLWooCommerce\Tests\Support\Spy_Label_Client_Factory;

/**
 * @coversNothing
 */
class LabelSandboxTest extends Sandbox_Test_Case {

	/**
	 * @testdox A morning order is confirmed by labelconfirm with guaranteedBefore 10:00
	 */
	public function test_morning_label_is_guaranteed_before_10(): void {
		$request = Request_Builder::build( $this->morning_fields() );
		$payload = $request->toArray( PayloadMapper::create() );
		$this->assertSame( '10:00', $payload['services']['deliveryWindow']['guaranteedBefore'] ?? null, 'The built request does not carry guaranteedBefore 10:00.' );

		$recorder = new Recording_Http_Client();
		$factory  = new Spy_Label_Client_Factory( $this->credentials(), $recorder );

		$this->throttle();
		$response = $factory->build( $this->api_key, true )->shipmentDelivery()->labelConfirm( $request );

		$sent = json_decode( (string) $recorder->last_request->getBody(), true );
		$this->assertSame( '10:00', $sent['services']['deliveryWindow']['guaranteedBefore'] ?? null, 'The sent request did not carry guaranteedBefore 10:00.' );

		$items = $response->items()->all();
		$this->assertNotEmpty( $items, 'labelconfirm returned no items.' );
		$this->assertNotEmpty( $items[0]->barcode, 'labelconfirm returned no barcode.' );
	}

	/**
	 * Settings stub exposing the sandbox customer credentials.
	 *
	 * @return object
	 */
	private function credentials(): object {
		return new class( $this->customer_number, $this->customer_code ) {

			/**
			 * Store the credentials.
			 *
			 * @param string $customer_number Sandbox customer number.
			 * @param string $customer_code   Sandbox customer code.
			 */
			public function __construct(
				private string $customer_number,
				private string $customer_code
			) {}

			/**
			 * Sandbox customer number.
			 *
			 * @return string
			 */
			public function get_customer_num() {
				return $this->customer_number;
			}

			/**
			 * Sandbox customer code.
			 *
			 * @return string
			 */
			public function get_customer_code() {
				return $this->customer_code;
			}
		};
	}

	/**
	 * Builder input for a domestic NL parcel with morning delivery.
	 *
	 * @return array
	 */
	private function morning_fields(): array {
		return array(
			'sender'        => array(
				'company'      => 'PostNL sandbox test',
				'street'       => 'Siriusdreef',
				'house_number' => '42',
				'postcode'     => '2132WT',
				'city'         => 'Hoofddorp',
				'country'      => 'NL',
			),
			'receiver'      => array(
				'first_name'   => 'Jan',
				'last_name'    => 'Jansen',
				'street'       => 'Prinses Beatrixlaan',
				'house_number' => '23',
				'postcode'     => '2595AA',
				'city'         => 'Den Haag',
				'country'      => 'NL',
				'email'        => 'test@example.com',
				'phone'        => '0612345678',
			),
			'shipment_type' => 'parcel',
			'weight_gr'     => 1000,
			'reference'     => 'SANDBOX-MORNING',
			'barcode'       => '',
			'label'         => array(
				'output_type' => 'pdf',
				'resolution'  => 200,
			),
			'services'      => array( 'deliveryWindow' => 'morning' ),
		);
	}
}
