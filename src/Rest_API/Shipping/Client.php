<?php
/**
 * Class Rest_API\Shipping\Client file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipping;

use PostNLWooCommerce\Rest_API\Base;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Client
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */
class Client extends Base {
	/**
	 * API Endpoint.
	 *
	 * @var string
	 */
	public $endpoint = '/v1/shipment?confirm=true';

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'Customer'  => array(
				'Address'            => array(
					'AddressType' => '02',
					'City'        => $this->item_info->shipper['city'],
					'CompanyName' => $this->item_info->shipper['company'],
					'Countrycode' => $this->item_info->shipper['country'],
					'HouseNr'     => $this->item_info->shipper['address_2'],
					'Street'      => $this->item_info->shipper['address_1'],
					'Zipcode'     => $this->item_info->shipper['postcode'],
				),
				/* Temporarily hardcoded in Settings::get_location_code(). */
				'CollectionLocation' => $this->item_info->customer['location_code'],
				'CustomerCode'       => $this->item_info->customer['customer_code'],
				'CustomerNumber'     => $this->item_info->customer['customer_num'],
				'ContactPerson'      => $this->item_info->customer['company'],
				'Email'              => $this->item_info->customer['email'],
				'Name'               => $this->item_info->customer['company'],
			),
			/** Hardcoded */
			'Message'   => array(
				'MessageID'        => '36209c3d-14d2-478f-85de-abccd84fa790',
				'MessageTimeStamp' => gmdate( 'd-m-Y H:i:s' ),
				'Printertype'      => 'GraphicFile|PDF',
			),
			'Shipments' => $this->get_shipments(),
		);
	}

	/**
	 * Get shipments data.
	 */
	public function get_shipments() {
		$shipments = array();

		$barcode  = $this->generate_barcode();
		$shipment = array(
			'Addresses'           => $this->get_shipment_addresses(),
			'Barcode'             => $barcode,
			'Contacts'            => array(
				array(
					'ContactType' => '01',
					'Email'       => $this->item_info->shipment['email'],
					'SMSNr'       => $this->item_info->shipment['phone'],
				),
			),
			'Dimension'           => array(
				'Weight' => $this->item_info->shipment['total_weight'],
			),
			'Customs'             => $this->get_customs(),
			'ProductCodeDelivery' => $this->item_info->shipment['product_code'],
		);

		if ( $this->item_info->backend_data['insured_shipping'] ) {
			$shipment['Amounts'][] = array(
				'AmountType' => '02',
				'Value'      => $this->get_insurance_value(),
			);
		}

		for ( $i = 1; $i <= $this->item_info->backend_data['num_labels']; $i++ ) {
			if ( $this->item_info->backend_data['num_labels'] > 1 ) {
				$shipment['Groups'][] = array(
					'GroupType'     => '03',
					'GroupCount'    => $this->item_info->backend_data['num_labels'],
					'GroupSequence' => $i,
					'MainBarcode'   => $barcode,
				);
			}
			$shipments[] = $shipment;
		}

		return $shipments;
	}

	/**
	 * Generate barcode.
	 *
	 * @return String
	 */
	public function generate_barcode() {
		return '3S' . $this->item_info->customer['customer_code'] . random_int( 100000000, 999999999 );
	}

	/**
	 * Get insurance value.
	 */
	public function get_insurance_value() {
		return 10;
	}

	/**
	 * Create a customs segment in API request.
	 *
	 * @return Array.
	 */
	public function get_customs() {
		return array(
			'Certificate'            => false,
			'Currency'               => $this->item_info->shipment['currency'],
			'Invoice'                => true,
			'InvoiceNr'              => $this->item_info->shipment['order_id'],
			'License'                => false,
			'TransactionCode'        => '11',
			'TransactionDescription' => 'Sale of goods',
			'Content'                => $this->get_custom_contents(),
		);
	}

	/**
	 * Create a custom contents segment in API request.
	 *
	 * @return Array.
	 */
	public function get_custom_contents() {
		if ( empty( $this->item_info->contents ) ) {
			return array();
		}

		$contents = array();
		foreach ( $this->item_info->contents  as $item ) {
			$contents[] = array(
				'Description'     => $item['description'],
				'Quantity'        => $item['qty'],
				'Weight'          => $item['weight'],
				'Value'           => $item['value'],
				'HSTariffNr'      => $item['hs_code'],
				'CountryOfOrigin' => $item['origin'],
			);
		}

		return $contents;
	}

	/**
	 * Get shipment addresses data.
	 */
	public function get_shipment_addresses() {
		$addresses = array();

		$addresses[] = array(
			'AddressType' => '01',
			'City'        => $this->item_info->receiver['city'],
			'Countrycode' => $this->item_info->receiver['country'],
			'FirstName'   => $this->item_info->receiver['first_name'],
			'HouseNr'     => $this->item_info->receiver['address_2'],
			'HouseNrExt'  => '',
			'Name'        => $this->item_info->receiver['last_name'],
			'Street'      => $this->item_info->receiver['address_1'],
			'Zipcode'     => $this->item_info->receiver['postcode'],
		);

		if ( $this->item_info->is_pickup_points() ) {
			$addresses[] = array(
				'AddressType' => '09',
				'CompanyName' => $this->item_info->pickup_points['company'],
				'City'        => $this->item_info->pickup_points['city'],
				'Countrycode' => $this->item_info->pickup_points['country'],
				'HouseNr'     => $this->item_info->pickup_points['address_2'],
				'HouseNrExt'  => '',
				'Street'      => $this->item_info->pickup_points['address_1'],
				'Zipcode'     => $this->item_info->pickup_points['postcode'],
			);
		}

		return $addresses;
	}
}
