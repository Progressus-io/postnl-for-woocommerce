<?php
/**
 * Unit tests for Rest_API\V4\Returns\Response_Mapper.
 *
 * @package PostNLWooCommerce\Tests\Rest_API\V4\Returns
 */

declare( strict_types = 1 );

namespace PostNLWooCommerce\Tests\Rest_API\V4\Returns;

use Brain\Monkey\Functions;
use Postnl\Sdk\Client\ResponseMeta;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\ResponseData\V4\Label;
use Postnl\Sdk\ResponseData\V4\LabelsCollection;
use Postnl\Sdk\ResponseData\V4\ReturnShippingItem;
use Postnl\Sdk\ResponseData\V4\ReturnShippingItemsCollection;
use Postnl\Sdk\ResponseData\V4\WarningsCollection;
use Postnl\Sdk\Service\ReturnShipment\V4\Response\GenerateReturnResponseInterface;
use PostNLWooCommerce\Rest_API\V4\Returns\Response_Mapper;
use PostNLWooCommerce\Tests\UnitTestCase;

/**
 * @covers \PostNLWooCommerce\Rest_API\V4\Returns\Response_Mapper
 */
class Response_MapperTest extends UnitTestCase {

	/**
	 * Wrap return items in a stub return/generate response exposing items().
	 *
	 * @param ReturnShippingItem ...$items Items to expose.
	 * @return GenerateReturnResponseInterface
	 */
	private function response( ReturnShippingItem ...$items ): GenerateReturnResponseInterface {
		$collection = new ReturnShippingItemsCollection( $items );

		return new class( $collection ) implements GenerateReturnResponseInterface {
			public function __construct( private ReturnShippingItemsCollection $items ) {}

			public function items(): ReturnShippingItemsCollection {
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
	 * @testdox first_return_item() returns the single return item from the response.
	 */
	public function test_first_return_item_returns_item(): void {
		$item = new ReturnShippingItem( barcode: '3SDEVCRET1' );

		$this->assertSame( $item, Response_Mapper::first_return_item( $this->response( $item ) ) );
	}

	/**
	 * @testdox first_return_item() returns null when the response carries no return item.
	 */
	public function test_first_return_item_returns_null_when_empty(): void {
		$this->assertNull( Response_Mapper::first_return_item( $this->response() ) );
	}

	/**
	 * @testdox get_barcode() prefers the item barcode.
	 */
	public function test_get_barcode_prefers_item_barcode(): void {
		$item = new ReturnShippingItem( barcode: '3SDEVCRET9', returnBarcode: '3SRETURN9' );

		$this->assertSame( '3SDEVCRET9', Response_Mapper::get_barcode( $item, 'fallback' ) );
	}

	/**
	 * @testdox get_barcode() falls back to the returnBarcode when the item barcode is absent.
	 */
	public function test_get_barcode_falls_back_to_return_barcode(): void {
		$item = new ReturnShippingItem( barcode: null, returnBarcode: '3SRETURN9' );

		$this->assertSame( '3SRETURN9', Response_Mapper::get_barcode( $item, 'fallback' ) );
	}

	/**
	 * @testdox get_barcode() uses the supplied fallback when the response omits both barcodes.
	 */
	public function test_get_barcode_uses_fallback(): void {
		$item = new ReturnShippingItem( barcode: null, returnBarcode: null );

		$this->assertSame( 'fallback-barcode', Response_Mapper::get_barcode( $item, 'fallback-barcode' ) );
	}

	/**
	 * @testdox get_partner_barcode()/get_partner_id() capture the international partner refs.
	 */
	public function test_partner_refs_captured(): void {
		$item = new ReturnShippingItem( barcode: '3SDEVCRET1', partnerId: 'DHL', partnerBarcode: 'CD123456785NL' );

		$this->assertSame( 'CD123456785NL', Response_Mapper::get_partner_barcode( $item ) );
		$this->assertSame( 'DHL', Response_Mapper::get_partner_id( $item ) );
	}

	/**
	 * @testdox get_labels() returns only non-empty labels.
	 */
	public function test_get_labels_filters_empty(): void {
		$full  = new Label( label: base64_encode( 'PDF-BYTES' ), outputType: LabelOutputType::PDF, labelType: 'Return Label' );
		$empty = new Label( label: null );
		$item  = new ReturnShippingItem( labels: new LabelsCollection( array( $full, $empty ) ) );

		$labels = Response_Mapper::get_labels( $item );

		$this->assertCount( 1, $labels );
		$this->assertSame( $full, $labels[0] );
	}

	/**
	 * @testdox decode_content() base64-decodes the label document bytes.
	 */
	public function test_decode_content_decodes_base64(): void {
		$label = new Label( label: base64_encode( 'PDF-BYTES' ), outputType: LabelOutputType::PDF );

		$this->assertSame( 'PDF-BYTES', Response_Mapper::decode_content( $label ) );
	}

	/**
	 * @testdox to_label_record() builds a return-label record in the legacy meta shape tagged as V4.
	 */
	public function test_to_label_record_shape(): void {
		Functions\when( 'current_time' )->justReturn( 1700000000 );

		$record = Response_Mapper::to_label_record( '3SDEVCRET1', '/uploads/postnl/return-label.pdf' );

		$this->assertSame(
			array(
				'type'        => 'return-label',
				'barcode'     => '3SDEVCRET1',
				'created_at'  => 1700000000,
				'filepath'    => '/uploads/postnl/return-label.pdf',
				'api_version' => 'v4',
			),
			$record
		);
	}
}
