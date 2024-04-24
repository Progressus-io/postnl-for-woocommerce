<?php
/**
 * Class Rest_API\Barcode\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use PostNLWooCommerce\Helper\Mapping;
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
		$barcode_args     = array_merge( $this->api_args['settings'], $this->api_args['shipping_address'] );
		$this->query_args = Utils::parse_args( $barcode_args, $this->get_query_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 *
	 * @throws \Exception If order id does not exist.
	 */
	public function convert_data_to_args( $post_data ) {
		if ( ! is_a( $post_data['order'], 'WC_Order' ) ) {
			throw new \Exception(
				__( 'Order ID does not exist!', 'postnl-for-woocommerce' )
			);
		}

		$order = $post_data['order'];

		if ( ! empty( $post_data['customer_code'] ) ) {
			$this->api_args['custom']['customer_code'] = $post_data['customer_code'];
		}

		$this->api_args['shipping_address'] = array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'company'    => $order->get_shipping_company(),
			'address_1'  => $order->get_shipping_address_1(),
			'address_2'  => $order->get_shipping_address_2(),
			'city'       => $order->get_shipping_city(),
			'state'      => $order->get_shipping_state(),
			'country'    => $order->get_shipping_country(),
			'postcode'   => $order->get_shipping_postcode(),
		);

		// This will be used to determined if we must use a specific barcode types.
		$this->api_args['backend_data'] = $post_data['saved_data']['backend'] ?? array();
	}

	/**
	 * Set extra API args.
	 */
	public function set_extra_data_to_api_args() {
		$this->set_rest_of_world_args();
		$this->set_custom_customer_code();
	}

	/**
	 * Change or set the args value for rest of the world.
	 */
	public function set_rest_of_world_args() {
		if ( ! $this->is_rest_of_world() ) {
			return;
		}

		$this->api_args['settings']['customer_code'] = $this->settings->get_globalpack_customer_code();
	}

	/**
	 * Change or set the args value for custom customer code.
	 */
	public function set_custom_customer_code() {
		if ( empty( $this->api_args['custom']['customer_code'] ) ) {
			return;
		}

		$this->api_args['settings']['customer_code'] = $this->api_args['custom']['customer_code'];
	}

	/**
	 * Retrieves the args scheme to use with for parsing store address info.
	 *
	 * @return array.
	 */
	protected function get_query_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'customer_code'            => array(
				'error' => __( 'Customer Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_num'             => array(
				'error' => __( 'Customer Number is empty!', 'postnl-for-woocommerce' ),
			),
			'globalpack_barcode_type'  => array(
				'rename'   => 'barcode_type',
				'default'  => '3S',
				'sanitize' => function ( $value ) use ( $self ) {
					if ( ! $self->is_rest_of_world() ) {
						return $self->check_product_barcode_type( '3S' );
					}

					// Use barcode type for specific products.
					$value = $self->check_product_barcode_type( $value );

					return $self->string_length_sanitization( $value, 4 );
				},
			),
			'serie'                    => array(
				'default'  => '000000000-999999999',
				'sanitize' => function ( $serie ) use ( $self ) {

					$barcode_type = $self->check_product_barcode_type( $self->api_args['settings'] );
					if ( in_array( $barcode_type, array( 'RI', 'UE', 'LA' ) ) ) {
						return '00000000-99999999';
					}

					if ( $self->is_europe() ) {
						return '0000000-9999999';
					}

					if ( $self->is_rest_of_world() ) {
						return '0000-9999';
					}

					return $self->string_length_sanitization( $serie, 19 );
				},
			),
			'globalpack_customer_code' => array(
				'sanitize' => function ( $value ) use ( $self ) {
					return $self->string_length_sanitization( $value, 4 );
				},
			),
		);
	}

	/**
	 * Check if the current order is for Rest of the world.
	 *
	 * @return Boolean.
	 */
	public function is_rest_of_world() {
		$to_country  = $this->api_args['shipping_address']['country'];
		$destination = Utils::get_shipping_zone( $to_country );

		return ( 'ROW' === $destination );
	}

	/**
	 * Check if the current order is for Rest of the world.
	 *
	 * @return Boolean.
	 */
	public function is_europe() {
		$to_country  = $this->api_args['shipping_address']['country'];
		$destination = Utils::get_shipping_zone( $to_country );

		return ( 'EU' === $destination );
	}

	/**
	 * Use specific barcode types for some products.
	 *
	 * @param string  $barcode_type Selected GlobalPack barcode type.
	 *
	 * @return string.
	 */
	public function check_product_barcode_type( $barcode_type ) {
		$barcode_types    = Mapping::products_custom_barcode_types();
		$selected_options = array();
		$backend_data     = Utils::get_selected_label_features( $this->api_args['backend_data'] );

		foreach ( $backend_data as $option => $value ) {
			if ( 'yes' === $value ) {
				$selected_options[] = $option;
			}
		}

		if ( ! empty( $selected_options ) ) {
			foreach ( $barcode_types as $type => $options_combinations ) {
				foreach ( $options_combinations as $combination ) {
					sort( $combination );
					sort( $selected_options );
					if ( $selected_options == $combination ) {
						return $type;
					}
				}
			}
		}

		return $barcode_type;
	}
}
