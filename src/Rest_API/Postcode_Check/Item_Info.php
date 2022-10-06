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
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * Body of the item info.
	 *
	 * @var body
	 */
	public $body;


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
		$post_data = Utils::set_post_data_address( $post_data );

		$this->api_args['shipping_address'] = array(
			'address_1'     => ( ! empty( $post_data['shipping_address_1'] ) ) ? $post_data['billing_address_1'] : '',
			'house_number'  => ( ! empty( $post_data['shipping_house_number'] ) ) ? $post_data['billing_house_number'] : '',
			'postcode'      => ( ! empty( $post_data['shipping_postcode'] ) ) ? $post_data['billing_postcode'] : ''
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
			'address_1'     => array(
				'error' => __( 'Shipping "Address 1" is empty!', 'postnl-for-woocommerce' )
			),
			'house_number'  => array(
				'error'     => __( 'Shipping "House number" is empty!', 'postnl-for-woocommerce' ),
				'default'   => '',
			),
			'postcode'      => array(
				'error' => __( 'Shipping "Postcode" is empty!', 'postnl-for-woocommerce' ),
			),
		);
	}
}
