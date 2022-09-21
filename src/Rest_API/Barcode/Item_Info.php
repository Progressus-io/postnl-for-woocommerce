<?php
/**
 * Class Rest_API\Barcode\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
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
	 * Query args of the item info.
	 *
	 * @var array
	 */
	public $query_args;

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @throws \Exception If some data in $args did not pass validation.
	 */
	protected function parse_args() {
		$barcode_args     = array_merge( $this->api_args['settings'], $this->api_args['barcode_args'] );
		$this->query_args = Utils::parse_args( $barcode_args, $this->get_query_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 */
	public function convert_data_to_args( $post_data ) {

		$this->api_args['barcode_args'] = array(
			'type'  => ( ! empty( $post_data['type'] ) ) ? $post_data['type'] : '',
			'serie' => ( ! empty( $post_data['serie'] ) ) ? $post_data['serie'] : '',
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing store address info.
	 *
	 * @return array
	 */
	protected function get_query_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'customer_code' => array(
				'error' => __( 'Customer Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_num'  => array(
				'error' => __( 'Customer Number is empty!', 'postnl-for-woocommerce' ),
			),
			'type'          => array(
				'default'  => '3S',
				'validate' => function( $type ) {
					$available_type = Utils::get_available_barcode_type();
					if ( ! in_array( $type, $available_type, true ) ) {
						throw new \Exception(
							// translators: %1$s is a barcode type.
							sprintf( esc_html__( 'Barcode type: %1$s is not available!', 'postnl-for-woocommerce' ), $type )
						);
					}
				},
			),
			'serie'         => array(
				'default'  => '000000000-999999999',
				'sanitize' => function( $serie ) use ( $self ) {
					return $self->string_length_sanitization( $serie, 19 );
				},
			),
		);
	}
}
