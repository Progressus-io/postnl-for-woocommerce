<?php
/**
 * Class Rest_API\Shipping\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Smart_Returns;

use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Base_Info;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Smart_Returns
 */
class Item_Info extends Base_Info {

	/**
	 * Order.
	 *
	 * @var \WC_Order
	 */
	protected $order;

	/**
	 * Order ID.
	 *
	 * @var int
	 */
	public $order_id;

	/**
	 * Shipper data of the item info.
	 *
	 * @var array
	 */
	public $customer;

	/**
	 * Shipper data of the item info.
	 *
	 * @var array
	 */
	public $message;

	/**
	 * Store/receiver details.
	 *
	 * @var array
	 */
	public $store;

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param \WC_Order $order WooCommerce order object.
	 */
	public function convert_data_to_args( $order ) {
		$this->order = $order;
	}

	/**
	 * Parses the arguments and sets the instance's properties.
	 */
	public function parse_args() {
		$this->order_id = $this->order->get_ID();
		$this->customer = $this->get_customer_info();
		$this->message  = $this->get_message_info();
		$this->store    = $this->get_store_info();
	}

	/**
	 * Get required customer (shop) info.
	 *
	 * @return array
	 */
	public function get_customer_info() {
		$this->api_args['billing_address'] = array(
			'company'   => $this->order->get_billing_company(),
			'email'     => $this->order->get_billing_email(),
			'phone'     => $this->order->get_billing_phone(),
			'address_1' => $this->order->get_billing_address_1(),
			'address_2' => $this->order->get_billing_address_2(),
			'city'      => $this->order->get_billing_city(),
			'state'     => $this->order->get_billing_state(),
			'country'   => $this->order->get_billing_country(),
			'postcode'  => $this->order->get_billing_postcode(),
		);

		$customer_address = array(
			'company'                    => $this->order->get_shipping_company(),
			'address_1'                  => $this->order->get_shipping_address_1(),
			'address_2'                  => $this->order->get_shipping_address_2(),
			'city'                       => $this->order->get_shipping_city(),
			'state'                      => $this->order->get_shipping_state(),
			'country'                    => $this->order->get_shipping_country(),
			'postcode'                   => $this->order->get_shipping_postcode(),
			'house_number'               => $this->order->get_meta( '_shipping_house_number' ),
			'return_address_1'           => $this->settings->get_return_address_or_reply_no() ? $this->settings->get_return_address_street() : 'Antwoordnummer',
			'return_address_2'           => $this->settings->get_return_address_or_reply_no() ? $this->settings->get_return_address_house_no() : $this->settings->get_return_reply_number(),
			'return_address_house_noext' => $this->settings->get_return_address_house_noext(),
			'return_address_city'        => $this->settings->get_return_address_or_reply_no() ? $this->settings->get_return_city() : $this->settings->get_freepost_city(),
			'return_address_zip'         => $this->settings->get_return_address_or_reply_no() ? $this->settings->get_return_zipcode() : $this->settings->get_freepost_zipcode(),
			'return_customer_code'       => $this->settings->get_return_customer_code(),
		);

		return Address_Utils::split_address( $customer_address );
	}

	/**
	 * Get message content.
	 *
	 * @return array
	 */
	public function get_message_info() {
		return array(
			'id'           => '36209c3d-14d2-478f-85de-abccd84fa790',
			'time_stamp'   => gmdate( 'd-m-Y H:i:s' ),
			'printer_type' => 'GraphicFile|PDF',
		);
	}

	/**
	 * Get store/receiver details.
	 *
	 * @return array
	 */
	public function get_store_info() {
		$store                    = $this->api_args['store_address'];
		$store['location_code']   = $this->settings->get_location_code();
		$store['customer_code']   = $this->settings->get_customer_code();
		$store['customer_number'] = $this->settings->get_customer_num();

		return $store;
	}
}
