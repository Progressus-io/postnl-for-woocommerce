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
	 * Shipper data of the item info.
	 *
	 * @var shipper
	 */
	public $shipper;

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
			'address_1'  => ( ! empty( $post_data['shipping_address_1'] ) ) ? $post_data['shipping_address_1'] : '',
			'address_2'  => ( ! empty( $post_data['shipping_address_2'] ) ) ? $post_data['shipping_address_2'] : '',
			'postcode'   => ( ! empty( $post_data['shipping_postcode'] ) ) ? $post_data['shipping_postcode'] : ''
		);
	}
}
