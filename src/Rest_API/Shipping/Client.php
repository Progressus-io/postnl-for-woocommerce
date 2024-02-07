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
	public $endpoint = '/v1/shipment';

	/**
	 * Function for composing API request in the URL for GET request.
	 *
	 * @return Array.
	 */
	public function compose_url_params() {
		return array(
			'confirm' => 'true',
		);
	}

	/**
	 * Function for composing API request.
	 */
	public function compose_body_request() {
		return array(
			'Customer'  => array(
				'Address'            => $this->get_customer_address(),
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
				'Printertype'      => $this->item_info->shipment['printer_type'],
			),
			'Shipments' => $this->get_shipments(),
		);
	}

	/**
	 * Get customer address information for Rest API.
	 *
	 * @return Array
	 */
	public function get_customer_address() {
		return array(
			'AddressType' => '02',
			'City'        => $this->item_info->shipper['city'],
			'CompanyName' => $this->item_info->shipper['company'],
			'Countrycode' => $this->item_info->shipper['country'],
			'HouseNr'     => $this->item_info->shipper['address_2'],
			'Street'      => $this->item_info->shipper['address_1'],
			'Zipcode'     => $this->item_info->shipper['postcode'],
		);
	}

	/**
	 * Get shipments data.
	 */
	public function get_shipments() {
		$shipments = array();

		$shipment = array(
			'Addresses'           => $this->get_shipment_addresses(),
			'Barcode'             => $this->item_info->shipment['main_barcode'],
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
			'ProductCodeDelivery' => $this->item_info->shipment['shipping_product']['code'],
			'Reference'           => $this->item_info->shipment['order_number'],
		);

		if ( $this->item_info->is_delivery_day() ) {
			$shipment['DeliveryDate'] = $this->item_info->delivery_day['date'] . ' ' . $this->item_info->delivery_day['from'];
		}

		if ( ! empty( $this->item_info->shipment['product_options']['characteristic'] ) ) {
			$shipment['ProductOptions'] = array(
				array(
					'Characteristic' => $this->item_info->shipment['product_options']['characteristic'],
					'Option'         => $this->item_info->shipment['product_options']['option'],
				),
			);
		}

		// Add the required product options.
		if ( ! empty( $this->item_info->shipment['shipping_product']['options'] ) ) {
			$shipment['ProductOptions'] = $shipment['ProductOptions'] ?? array();

			foreach ( $this->item_info->shipment['shipping_product']['options'] as $option ) {
				$shipment['ProductOptions'][] = array(
					'Characteristic' => $option['characteristic'],
					'Option'         => $option['option'],
				);
			}
		}

		if ( ! empty( $this->item_info->shipment['return_barcode'] ) ) {
			$shipment['ReturnBarcode'] = $this->item_info->shipment['return_barcode'];
		}

		if ( $this->item_info->backend_data['insured_shipping'] ) {
			$shipment['Amounts'][] = array(
				'AmountType' => '02',
				'Currency'   => $this->item_info->shipment['currency'],
				'Value'      => $this->item_info->shipment['subtotal'],
			);
		}

		for ( $i = 1; $i <= $this->item_info->backend_data['num_labels']; $i++ ) {
			if ( $this->item_info->backend_data['num_labels'] > 1 ) {
				$shipment['Barcode'] = $this->item_info->shipment['barcodes'][ ( $i - 1 ) ];
				$shipment['Groups']  = array(
					array(
						'GroupType'     => '03',
						'GroupCount'    => $this->item_info->backend_data['num_labels'],
						'GroupSequence' => $i,
						'MainBarcode'   => $this->item_info->shipment['main_barcode'],
					),
				);
			}
			$shipments[] = $shipment;
		}

		return $shipments;
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
			// Hardcoded.
			'TransactionCode'        => '11',
			'TransactionDescription' => 'Sale of goods',
			'ShipmentType'           => 'Commercial Goods',
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
			'CompanyName' => $this->item_info->receiver['company'],
			'City'        => $this->item_info->receiver['city'],
			'Countrycode' => $this->item_info->receiver['country'],
			'FirstName'   => $this->item_info->receiver['first_name'],
			'HouseNr'     => $this->item_info->receiver['house_number'],
			'HouseNrExt'  => $this->item_info->receiver['address_2'],
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

		if ( $this->item_info->shipment['return_barcode'] ) {
			$addresses[] = array(
				'AddressType' => '08',
				'City'        => $this->item_info->customer['return_address_city'],
				'CompanyName' => $this->item_info->customer['return_company'],
				'Countrycode' => $this->item_info->shipper['country'],
				'HouseNr'     => $this->item_info->customer['return_address_2'],
				'Street'      => $this->item_info->customer['return_address_1'],
				'Zipcode'     => $this->item_info->customer['return_address_zip'],
			);
		}

		return $addresses;
	}
}
