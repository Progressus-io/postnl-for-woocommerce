<?php
/**
 * Class Rest_API\Postcode_Check\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Postcode_Check
 */

namespace PostNLWooCommerce\Rest_API\Postcode_Check;

use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */
class Item_Info extends Base_Info {
	/**
	 * API args.
	 *
	 * @var api_args
	 */
	protected $api_args;


	/**
	 * Receiver data of the item info.
	 *
	 * @var receiver
	 */
	public $receiver;

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @throws \Exception If some data in $args did not pass validation.
	 */
	protected function parse_args() {
		$this->receiver = Utils::parse_args( $this->api_args['shipping_address'], $this->get_receiver_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 */
	public function convert_data_to_args( $post_data ) {

		$this->api_args['shipping_address'] = array(
			'address_2'     => $post_data['shipping_address_2'] ?? '',
			'house_number'  => $post_data['shipping_house_number'] ?? '',
			'postcode'      => $post_data['shipping_postcode'] ?? ''
		);

	}

	/**
	 * Retrieves the args scheme to use with for parsing shipping address info.
	 *
	 * @return array
	 */
	protected function get_receiver_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'first_name'     => array(
				'default'   => ''
			),
			'address_1'     => array(
				'default'   => ''
			),
			'address_2'     => array(
				'default'   => ''
			),
			'city'     => array(
				'default'   => ''
			),
			'house_number'  => array(
				'error'     => __( 'Shipping "House number" is empty!', 'postnl-for-woocommerce' ),
			),
			'postcode'      => array(
				'error' => __( 'Shipping "Postcode" is empty!', 'postnl-for-woocommerce' ),
			),
		);
	}
}
