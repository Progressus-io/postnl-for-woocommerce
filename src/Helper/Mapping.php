<?php
/**
 * Class Mapping file.
 *
 * @package PostNLWooCommerce\Helper
 */

namespace PostNLWooCommerce\Helper;

use PostNLWooCommerce\Helper\Product_Mapper\V1_Mapper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mapping
 *
 * @package PostNLWooCommerce\Mapping
 */
class Mapping {
	/**
	 * Delivery type mapping.
	 *
	 * @return array.
	 */
	public static function delivery_type() {
		return array(
			'NL' => array(
				'NL'  => array(
					'delivery_day_type'   => array(
						'08:00-12:00' => esc_html__( 'Morning Delivery', 'postnl-for-woocommerce' ),
						'Daytime'     => esc_html__( 'Standard Shipment', 'postnl-for-woocommerce' ),
						'Evening'     => esc_html__( 'Evening Delivery', 'postnl-for-woocommerce' ),
					),
					'dropoff_points_type' => array(
						'Pickup' => esc_html__( 'Pickup at PostNL Point', 'postnl-for-woocommerce' ),
					),
				),
				'BE'  => array(
					'delivery_day_type'   => array(
						'Daytime' => esc_html__( 'Standard Shipment Belgium', 'postnl-for-woocommerce' ),
					),
					'dropoff_points_type' => array(
						'Pickup' => esc_html__( 'Pickup at PostNL Point Belgium', 'postnl-for-woocommerce' ),
					),
				),
				'EU'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'ROW' => esc_html__( 'Non-EU Shipment', 'postnl-for-woocommerce' ),
			),
			'BE' => array(
				'BE'  => esc_html__( 'Belgium Domestic', 'postnl-for-woocommerce' ),
				'NL'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'EU'  => esc_html__( 'EU Parcel', 'postnl-for-woocommerce' ),
				'ROW' => esc_html__( 'Non-EU Shipment', 'postnl-for-woocommerce' ),
			),
		);
	}

	/**
	 * Products code & required options mapping.
	 *
	 * @return array[].
	 */
	public static function products_data() {
		return V1_Mapper::products_data();
	}

	/**
	 * Products code & options available for European and GlobalPack Shipments.
	 *
	 * @return array[].
	 */
	public static function EU_ROW_products() {
		return V1_Mapper::EU_ROW_products();
	}

	/**
	 * Products code & options available for European Shipments.
	 *
	 * @return array[].
	 */
	public static function european_shipment_products() {
		return V1_Mapper::european_shipment_products();
	}

	/**
	 * Products code & options available for GlobalPack Shipments.
	 *
	 * @return array[].
	 */
	public static function globalpack_products() {
		return V1_Mapper::globalpack_products();
	}

	/**
	 * Label type mapping.
	 *
	 * @return array.
	 */
	public static function label_type_list() {
		return array(
			'NL' => array(
				// Return label is added here since smart return is not implemented yet.
				// If smart return is implemented, we might need to remove return-label from this list.
				'NL'  => array( 'label', 'return-label', 'buspakjeextra', 'printcodelabel' ),
				'BE'  => array( 'label', 'return-label' ),
				'EU'  => array( 'label' ),
				'ROW' => array( 'cn23', 'cp71', 'label', 'commercialinvoice' ),
			),
			'BE' => array(
				'BE'  => array( 'label' ),
				'NL'  => array( 'label' ),
				'EU'  => array( 'label' ),
				'ROW' => array( 'cn23', 'cp71', 'label', 'commercialinvoice' ),
			),
		);
	}

	/**
	 * Product code mapping.
	 *
	 * @return array.
	 */
	public static function option_available_list() {
		return array(
			'NL' => array(
				'NL'  => array( 'create_return_label', 'num_labels' ),
				'BE'  => array( 'create_return_label', 'num_labels' ),
				'EU'  => array( 'num_labels' ),
				'ROW' => array( 'num_labels' ),
			),
			'BE' => array(
				'BE'  => array( 'num_labels' ),
				'NL'  => array( 'num_labels' ),
				'EU'  => array( 'num_labels' ),
				'ROW' => array( 'num_labels' ),
			),
		);
	}

	/**
	 * Additional Product options mapping.
	 *
	 * @return array.
	 */
	public static function additional_product_options() {
		return V1_Mapper::additional_product_options();
	}

	/**
	 * List of countries that available for checkout feature.
	 *
	 * @return array.
	 */
	public static function available_country_for_checkout_feature() {
		return array(
			'NL' => array(
				'NL' => array( 'pickup_points', 'delivery_day', 'evening_delivery', '08:00-12:00' ),
				'BE' => array( 'pickup_points', 'delivery_day' ),
			),
			'BE' => array(
				'BE' => array( 'pickup_points' ),
				'NL' => array( 'pickup_points' ),
			),
		);
	}

	/**
	 * List of barcodes types used for specific products.
	 *
	 * @return array.
	 */
	public static function products_custom_barcode_types() {
		return array(
			'UE' => array(
				array( 'mailboxpacket' ),
				array( 'packets' ),
			),
			'LA' => array(
				array( 'track_and_trace', 'mailboxpacket' ),
				array( 'track_and_trace', 'packets' ),
				array( 'track_and_trace', 'packets', 'insured_shipping' ),
			),
		);
	}

	/**
	 * Shipment & Return labels options mapping.
	 *
	 * @return array[].
	 */
	public static function shipping_return_labels_options() {
		return V1_Mapper::shipping_return_labels_options();
	}
}
