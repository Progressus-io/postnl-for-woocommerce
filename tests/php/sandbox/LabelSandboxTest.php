<?php
/**
 * Sandbox tests for Rest_API\V4\Label\Request_Builder.
 *
 * @package PostNLWooCommerce\Tests\Sandbox
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Sandbox;

use GuzzleHttp\Client;
use Postnl\Sdk\Client\ClientBuilder;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\V4\Label\Request_Builder;
use PostNLWooCommerce\Tests\Support\Sandbox_Test_Case;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @coversNothing
 */
class LabelSandboxTest extends Sandbox_Test_Case {

	/**
	 * @testdox A morning order is confirmed by labelconfirm with guaranteedBefore 10:00
	 */
	public function test_morning_label_is_guaranteed_before_10(): void {
		$recorder = new Recording_Http_Client();
		$factory  = new Recording_Client_Factory( $this, $recorder );

		$this->throttle();
		$response = $factory->build( $this->api_key, true )->shipmentDelivery()->labelConfirm( Request_Builder::build( $this->morning_fields() ) );

		$sent = json_decode( (string) $recorder->last_request->getBody(), true );
		$this->assertSame( '10:00', $sent['services']['deliveryWindow']['guaranteedBefore'] ?? null, 'The request did not carry guaranteedBefore 10:00.' );

		$items = $response->items()->all();
		$this->assertNotEmpty( $items, 'labelconfirm returned no items.' );
		$this->assertNotEmpty( $items[0]->barcode, 'labelconfirm returned no barcode.' );
	}

	/**
	 * Customer number for the recording factory.
	 *
	 * @return string
	 */
	public function get_customer_num() {
		return $this->customer_number;
	}

	/**
	 * Customer code for the recording factory.
	 *
	 * @return string
	 */
	public function get_customer_code() {
		return $this->customer_code;
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

/**
 * Client_Factory that routes requests through a recording HTTP client.
 */
class Recording_Client_Factory extends Client_Factory { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * HTTP client the SDK sends through.
	 *
	 * @var ClientInterface
	 */
	private ClientInterface $http_client;

	/**
	 * Recording_Client_Factory constructor.
	 *
	 * @param object          $settings    Object exposing the customer credentials.
	 * @param ClientInterface $http_client HTTP client the SDK sends through.
	 */
	public function __construct( object $settings, ClientInterface $http_client ) {
		parent::__construct( $settings );
		$this->http_client = $http_client;
	}

	/**
	 * Configure the builder as production does, with the recording HTTP client.
	 *
	 * @param string $v4_key          PostNL V4 API key.
	 * @param bool   $is_sandbox      Whether to target the sandbox.
	 * @param string $customer_number Customer number.
	 * @param string $customer_code   Customer code.
	 * @return ClientBuilder
	 */
	protected function make_builder( string $v4_key, bool $is_sandbox, string $customer_number, string $customer_code ): ClientBuilder {
		return parent::make_builder( $v4_key, $is_sandbox, $customer_number, $customer_code )->withHttpClient( $this->http_client );
	}
}

/**
 * PSR-18 client that sends through Guzzle and keeps the last request.
 */
class Recording_Http_Client implements ClientInterface { // phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound

	/**
	 * Last request sent.
	 *
	 * @var RequestInterface|null
	 */
	public ?RequestInterface $last_request = null;

	/**
	 * Send the request and remember it.
	 *
	 * @param RequestInterface $request Outgoing request.
	 * @return ResponseInterface
	 */
	public function sendRequest( RequestInterface $request ): ResponseInterface {
		$this->last_request = $request;

		return ( new Client( array( 'http_errors' => false ) ) )->send( $request );
	}
}
