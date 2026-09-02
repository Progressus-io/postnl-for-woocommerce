<?php
/**
 * Unit tests for Rest_API\V4\Label\Response_Mapper.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Label
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Label;

use Brain\Monkey\Functions;
use Postnl\Sdk\Client\ResponseMeta;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\ResponseData\V4\LabelsCollection;
use Postnl\Sdk\ResponseData\V4\WarningsCollection;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseInterface;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItem;
use Postnl\Sdk\Service\ShipmentDelivery\Response\ShipmentDeliveryResponseItemsCollection;
use PostNLWooCommerce\Rest_API\V4\Label\Response_Mapper;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Label\Response_Mapper
 */
class Response_MapperTest extends UnitTestCase {

	/**
	 * Wrap shipment items in a stub labelconfirm response exposing items().
	 *
	 * @param ShipmentDeliveryResponseItem ...$items Items to expose.
	 * @return ShipmentDeliveryResponseInterface
	 */
	private function response( ShipmentDeliveryResponseItem ...$items ): ShipmentDeliveryResponseInterface {
		$collection = new ShipmentDeliveryResponseItemsCollection( $items );

		return new class( $collection ) implements ShipmentDeliveryResponseInterface {
			public function __construct( private ShipmentDeliveryResponseItemsCollection $items ) {}

			public function items(): ShipmentDeliveryResponseItemsCollection {
				return $this->items;
			}

			public function meta(): ResponseMeta {
				throw new \LogicException( 'meta() is not exercised by these tests.' );
			}

			public function warnings(): WarningsCollection {
				throw new \LogicException( 'warnings() is not exercised by these tests.' );
			}
		};
	}

	/**
	 * @testdox first_shipment_item() returns the single shipment item from the response.
	 */
	public function test_first_shipment_item_returns_item(): void {
		$item     = new ShipmentDeliveryResponseItem( barcode: '3SDEVC1' );
		$response = $this->response( $item );

		$this->assertSame( $item, Response_Mapper::first_shipment_item( $response ) );
	}

	/**
	 * @testdox first_shipment_item() returns null when the response carries no shipment.
	 */
	public function test_first_shipment_item_returns_null_when_empty(): void {
		$this->assertNull( Response_Mapper::first_shipment_item( $this->response() ) );
	}

	/**
	 * @testdox all_shipment_items() returns every collo item in order for a multi-collo shipment.
	 */
	public function test_all_shipment_items_returns_all_collos(): void {
		$first  = new ShipmentDeliveryResponseItem( barcode: '3SDEVC1' );
		$second = new ShipmentDeliveryResponseItem( barcode: '3SDEVC2' );
		$third  = new ShipmentDeliveryResponseItem( barcode: '3SDEVC3' );

		$items = Response_Mapper::all_shipment_items( $this->response( $first, $second, $third ) );

		$this->assertCount( 3, $items );
		$this->assertSame( '3SDEVC1', $items[0]->barcode );
		$this->assertSame( '3SDEVC3', $items[2]->barcode );
	}

	/**
	 * @testdox all_shipment_items() returns an empty array when the response carries no shipment.
	 */
	public function test_all_shipment_items_empty_when_no_shipment(): void {
		$this->assertSame( array(), Response_Mapper::all_shipment_items( $this->response() ) );
	}

	/**
	 * @testdox get_barcode() captures the barcode auto-issued on the response item.
	 */
	public function test_get_barcode_captured_from_response(): void {
		$item = new ShipmentDeliveryResponseItem( barcode: '3SDEVC9876543' );

		$this->assertSame( '3SDEVC9876543', Response_Mapper::get_barcode( $item, 'fallback' ) );
	}

	/**
	 * @testdox get_barcode() falls back when the response item has no barcode.
	 */
	public function test_get_barcode_uses_fallback_when_missing(): void {
		$item = new ShipmentDeliveryResponseItem( barcode: null );

		$this->assertSame( 'fallback-barcode', Response_Mapper::get_barcode( $item, 'fallback-barcode' ) );
	}

	/**
	 * @testdox get_labels() returns only non-empty labels.
	 */
	public function test_get_labels_filters_empty(): void {
		$full  = new Label( label: base64_encode( 'PDF-BYTES' ), outputType: LabelOutputType::PDF, labelType: 'Label' );
		$empty = new Label( label: null );
		$item  = new ShipmentDeliveryResponseItem( labels: new LabelsCollection( array( $full, $empty ) ) );

		$labels = Response_Mapper::get_labels( $item );

		$this->assertCount( 1, $labels );
		$this->assertSame( $full, $labels[0] );
	}

	/**
	 * @testdox get_labels() returns an empty array when the item has no labels.
	 */
	public function test_get_labels_empty_when_no_labels(): void {
		$this->assertSame( array(), Response_Mapper::get_labels( new ShipmentDeliveryResponseItem() ) );
	}

	/**
	 * @testdox decode_content() base64-decodes the label document bytes.
	 */
	public function test_decode_content_decodes_base64(): void {
		$label = new Label( label: base64_encode( 'PDF-BYTES' ), outputType: LabelOutputType::PDF );

		$this->assertSame( 'PDF-BYTES', Response_Mapper::decode_content( $label ) );
	}

	/**
	 * @testdox decode_content() returns an empty string when the label has no content.
	 */
	public function test_decode_content_empty_when_absent(): void {
		$this->assertSame( '', Response_Mapper::decode_content( new Label( label: null ) ) );
	}

	/**
	 * @testdox decode_content() rejects malformed base64 instead of decoding it to garbage bytes.
	 */
	public function test_decode_content_rejects_malformed_base64(): void {
		$malformed = '!!!not-base64!!!';
		$label     = new Label( label: $malformed, outputType: LabelOutputType::PDF, labelType: 'Label' );

		// Guard: non-strict decoding silently yields bytes, so the assertions below
		// pin strict mode rather than merely restating what base64_decode() does.
		$this->assertNotSame( '', (string) base64_decode( $malformed, false ) );

		$this->assertNotEmpty(
			Response_Mapper::get_labels( new ShipmentDeliveryResponseItem( labels: new LabelsCollection( array( $label ) ) ) ),
			'A malformed label still passes the isEmpty() filter, so strict decoding is the only guard.'
		);
		$this->assertSame(
			'',
			Response_Mapper::decode_content( $label ),
			'Malformed content must decode to an empty string so the caller skips it rather than writing a corrupt label file.'
		);
	}

	/**
	 * @testdox get_partner_barcode() and get_partner_id() capture the international partner refs.
	 */
	public function test_partner_refs_captured_from_response(): void {
		$item = new ShipmentDeliveryResponseItem( barcode: '3SDEVC1', partnerId: 'DHL', partnerBarcode: 'CD123456785NL' );

		$this->assertSame( 'CD123456785NL', Response_Mapper::get_partner_barcode( $item ) );
		$this->assertSame( 'DHL', Response_Mapper::get_partner_id( $item ) );
	}

	/**
	 * @testdox get_partner_barcode() and get_partner_id() return empty strings for a domestic item.
	 */
	public function test_partner_refs_empty_for_domestic(): void {
		$item = new ShipmentDeliveryResponseItem( barcode: '3SDEVC1' );

		$this->assertSame( '', Response_Mapper::get_partner_barcode( $item ) );
		$this->assertSame( '', Response_Mapper::get_partner_id( $item ) );
	}

	/**
	 * @testdox to_label_record() builds a record in the legacy meta shape tagged as V4.
	 */
	public function test_to_label_record_shape(): void {
		Functions\when( 'current_time' )->justReturn( 1700000000 );

		$record = Response_Mapper::to_label_record( 'label', '3SDEVC1', '/uploads/postnl/label.pdf' );

		$this->assertSame(
			array(
				'type'        => 'label',
				'barcode'     => '3SDEVC1',
				'created_at'  => 1700000000,
				'filepath'    => '/uploads/postnl/label.pdf',
				'api_version' => 'v4',
			),
			$record
		);
	}

	/**
	 * @testdox to_label_record() adds the partner barcode and id for an international label.
	 */
	public function test_to_label_record_includes_partner_refs(): void {
		Functions\when( 'current_time' )->justReturn( 1700000000 );

		$record = Response_Mapper::to_label_record( 'label', '3SDEVC1', '/uploads/postnl/label.pdf', 'CD123456785NL', 'DHL' );

		$this->assertSame( 'CD123456785NL', $record['partner_barcode'] );
		$this->assertSame( 'DHL', $record['partner_id'] );
	}

	/**
	 * @testdox to_label_record() omits partner keys when no partner refs are supplied.
	 */
	public function test_to_label_record_omits_empty_partner_refs(): void {
		Functions\when( 'current_time' )->justReturn( 1700000000 );

		$record = Response_Mapper::to_label_record( 'label', '3SDEVC1', '/uploads/postnl/label.pdf' );

		$this->assertArrayNotHasKey( 'partner_barcode', $record );
		$this->assertArrayNotHasKey( 'partner_id', $record );
	}
}
