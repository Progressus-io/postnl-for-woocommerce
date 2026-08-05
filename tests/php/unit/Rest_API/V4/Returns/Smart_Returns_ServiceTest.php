<?php
/**
 * Unit tests for Rest_API\V4\Returns\Smart_Returns_Service::map_fields().
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Returns;

use PostNLWooCommerce\Rest_API\V4\Returns\Smart_Returns_Service;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Returns\Smart_Returns_Service
 */
class Smart_Returns_ServiceTest extends UnitTestCase {

	/**
	 * The consumer address + return-to-home receiver as the legacy
	 * Smart_Returns\Item_Info::$customer array exposes it.
	 *
	 * @return array
	 */
	private function customer(): array {
		return array(
			'company'                    => '',
			'address_1'                  => 'Main Street',
			'address_2'                  => 'B',
			'house_number'               => '9',
			'city'                       => 'Amsterdam',
			'state'                      => '',
			'country'                    => 'NL',
			'postcode'                   => '1234AB',
			'return_address_1'           => 'Siriusdreef',
			'return_address_2'           => '42',
			'return_address_house_noext' => '',
			'return_address_city'        => 'Hoofddorp',
			'return_address_zip'         => '2132WT',
			'return_customer_code'       => 'DEVC',
		);
	}

	/**
	 * The merchant store info as Smart_Returns\Item_Info::$store exposes it.
	 *
	 * @return array
	 */
	private function store(): array {
		return array(
			'company' => 'My Shop',
			'country' => 'NL',
			'email'   => 'shop@example.com',
		);
	}

	/**
	 * The consumer contact read from the order.
	 *
	 * @return array
	 */
	private function consumer(): array {
		return array(
			'first_name' => 'Jan',
			'last_name'  => 'Jansen',
			'email'      => 'buyer@example.com',
			'phone'      => '0612345678',
		);
	}

	/**
	 * @testdox map_fields() always requests retailPrint with a PNG output for inline display.
	 */
	public function test_forces_retail_print_png(): void {
		$fields = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' );

		$this->assertSame( 'retailPrint', $fields['print_method'] );
		$this->assertSame( 'png', $fields['label']['output_type'] );
		$this->assertSame( '', $fields['barcode'], 'No barcode is pre-issued; return/generate auto-issues one.' );
		$this->assertSame( 'ORDER-1001', $fields['reference'] );
	}

	/**
	 * @testdox map_fields() maps the consumer (order recipient) as the return sender.
	 */
	public function test_sender_is_the_consumer(): void {
		$sender = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' )['sender'];

		$this->assertSame( 'Main Street', $sender['street'] );
		$this->assertSame( '9', $sender['house_number'] );
		$this->assertSame( 'B', $sender['house_number_ext'] );
		$this->assertSame( '1234AB', $sender['postcode'] );
		$this->assertSame( 'Amsterdam', $sender['city'] );
		$this->assertSame( 'NL', $sender['country'] );
		$this->assertSame( 'Jan', $sender['first_name'] );
		$this->assertSame( 'Jansen', $sender['last_name'] );
		$this->assertSame( 'buyer@example.com', $sender['email'] );
		$this->assertSame( '0612345678', $sender['phone'] );
	}

	/**
	 * @testdox map_fields() maps a return-to-home receiver from the merchant return address.
	 */
	public function test_receiver_is_the_return_to_home_address(): void {
		$receiver = Smart_Returns_Service::map_fields( $this->customer(), $this->store(), $this->consumer(), 'ORDER-1001' )['receiver'];

		$this->assertSame( 'My Shop', $receiver['company'] );
		$this->assertSame( 'Siriusdreef', $receiver['street'] );
		$this->assertSame( '42', $receiver['house_number'] );
		$this->assertSame( '2132WT', $receiver['postcode'] );
		$this->assertSame( 'Hoofddorp', $receiver['city'] );
		$this->assertSame( 'NL', $receiver['country'] );
	}

	/**
	 * @testdox map_fields() encodes a reply-number (Antwoordnummer) receiver from the return address.
	 */
	public function test_receiver_encodes_reply_number(): void {
		$customer                     = $this->customer();
		$customer['return_address_1'] = 'Antwoordnummer';
		$customer['return_address_2'] = '12345';
		$customer['return_address_city'] = 'Amsterdam';
		$customer['return_address_zip']  = '1000AA';

		$receiver = Smart_Returns_Service::map_fields( $customer, $this->store(), $this->consumer(), 'ORDER-1001' )['receiver'];

		$this->assertSame( 'Antwoordnummer', $receiver['street'] );
		$this->assertSame( '12345', $receiver['house_number'] );
		$this->assertSame( '1000AA', $receiver['postcode'] );
	}
}
