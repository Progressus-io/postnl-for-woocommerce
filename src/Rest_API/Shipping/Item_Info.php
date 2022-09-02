<?php
/**
 * Class Rest_API\Shipping\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipping;

use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Item_Info
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
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
	 * Shipment of the item info.
	 *
	 * @var shipment
	 */
	public $shipment;

	/**
	 * Customer info data of the item info.
	 *
	 * @var customer
	 */
	public $customer;

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
	 * @throws Exception If some data in $args did not pass validation.
	 */
	protected function parse_args() {
		$customer_info = $this->api_args['settings'] + $this->api_args['store_address'];
		$shipment      = $this->api_args['billing_address'] + $this->api_args['order_details'];

		$this->shipment = Utils::parse_args( $shipment, $this->get_shipment_info_schema() );
		$this->receiver = Utils::parse_args( $this->api_args['shipping_address'], $this->get_receiver_info_schema() );
		$this->customer = Utils::parse_args( $customer_info, $this->get_customer_info_schema() );
		$this->shipper  = Utils::parse_args( $this->api_args['store_address'], $this->get_store_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 *
	 * @throws Exception When order ID doesnt exists.
	 */
	public function convert_data_to_args( $post_data ) {
		if ( ! is_a( $post_data['order'], 'WC_Order' ) ) {
			throw new Exception(
				__( 'Order ID does not exists!', 'postnl-for-woocommerce' )
			);
		}

		$order      = $post_data['order'];
		$saved_data = $post_data['saved_data'];

		$this->api_args['billing_address'] = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'company'    => $order->get_billing_company(),
			'email'      => $order->get_billing_email(),
			'phone'      => $order->get_billing_phone(),
			'address_1'  => $order->get_billing_address_1(),
			'address_2'  => $order->get_billing_address_2(),
			'city'       => $order->get_billing_city(),
			'state'      => $order->get_billing_state(),
			'country'    => $order->get_billing_country(),
			'postcode'   => $order->get_billing_postcode(),
		);

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

		$this->api_args['backend_data'] = array(
			'delivery_type'         => $saved_data['backend']['delivery_type'] ?? '',
			'insured_shipping'      => $saved_data['backend']['insured_shipping'] ?? '',
			'return_no_answer'      => $saved_data['backend']['return_no_answer'] ?? '',
			'signature_on_delivery' => $saved_data['backend']['signature_on_delivery'] ?? '',
			'only_home_address'     => $saved_data['backend']['only_home_address'] ?? '',
			'num_labels'            => $saved_data['backend']['num_labels'] ?? '',
			'create_return_label'   => $saved_data['backend']['create_return_label'] ?? '',
			'letterbox'             => $saved_data['backend']['letterbox'] ?? '',
		);

		$this->api_args['frontend_data'] = array(
			'delivery_day'   => array(
				'value' => $saved_data['frontend']['delivery_day'] ?? '',
				'date'  => $saved_data['frontend']['delivery_day_date'] ?? '',
				'from'  => $saved_data['frontend']['delivery_day_from'] ?? '',
				'to'    => $saved_data['frontend']['delivery_day_to'] ?? '',
				'price' => $saved_data['frontend']['delivery_day_price'] ?? '',
				'type'  => $saved_data['frontend']['delivery_day_type'] ?? '',
			),
			'dropoff_points' => array(
				'value'    => $saved_data['frontend']['dropoff_points'] ?? '',
				'company'  => $saved_data['frontend']['dropoff_points_company'] ?? '',
				'distance' => $saved_data['frontend']['dropoff_points_distance'] ?? '',
				'address'  => $saved_data['frontend']['dropoff_points_address'] ?? '',
				'id'       => $saved_data['frontend']['dropoff_points_id'] ?? '',
				'date'     => $saved_data['frontend']['dropoff_points_date'] ?? '',
				'time'     => $saved_data['frontend']['dropoff_points_time'] ?? '',
			),
		);

		$this->api_args['order_details'] = array(
			'order_id'     => $order->get_id(),
			'total_weight' => $this->calculate_order_weight( $order ),
		);
	}

	/**
	 * Set extra API args.
	 */
	public function set_extra_data_to_api_args() {
		$this->api_args['order_details']['product_code'] = $this->get_product_code();
	}

	/**
	 * Retrieves the args scheme to use with for parsing customer info.
	 *
	 * @return array
	 */
	protected function get_customer_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'location_code'            => array(
				'error' => __( 'Location Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_code'            => array(
				'error' => __( 'Customer Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_num'             => array(
				'error' => __( 'Customer Number is empty!', 'postnl-for-woocommerce' ),
			),
			'company'             => array(
				'default' => '',
			),
			'email'             => array(
				'validate' => function( $value ) {
					if ( empty( $value ) ) {
						throw new Exception(
							__( 'Store email is empty!', 'postnl-for-woocommerce' )
						);
					}

					if ( ! is_email( $value ) ) {
						throw new Exception(
							__( 'Wrong format for store email!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing store address info.
	 *
	 * @return array
	 */
	protected function get_shipment_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'product_code' => array(
				'error'    => __( 'Product code is empty!', 'postnl-for-woocommerce' ),
				'validate' => function( $value ) {
					if ( ! is_numeric( $value ) && 4 !== strlen( $value ) ) {
						throw new Exception(
							__( 'Wrong format for product code!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $value ) use ( $self ) {
					return $self->string_length_sanitization( $value, 4 );
				},
			),
			'total_weight' => array(
				'error'    => __( 'Total weight is empty!', 'postnl-for-woocommerce' ),
				'sanitize' => function( $value ) use ( $self ) {
					return $self->float_round_sanitization( $value, 2 );
				},
			),
			'email'        => array(
				'validate' => function( $value ) {
					if ( ! is_email( $value ) ) {
						throw new Exception(
							__( 'Customer email is not valid!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'phone'        => array(
				'default' => '',
			),
		);
	}

	/**
	 * Calculate total weight in one order.
	 *
	 * @param WC_Order $order Order object.
	 *
	 * @return Float Total weight.
	 */
	protected function calculate_order_weight( $order ) {
		$total_weight = 0;

		if ( ! is_a( $order, 'WC_Order' ) ) {
			return apply_filters( 'postnl_order_weight', $total_weight, $order );
		}

		$ordered_items = $order->get_items();

		if ( empty( $ordered_items ) || ! is_array( $ordered_items ) ) {
			return apply_filters( 'postnl_order_weight', $total_weight, $order );
		}

		foreach ( $ordered_items as $key => $item ) {
			$product = $item->get_product();

			if ( ! is_a( $product, 'WC_Product' ) || $product->is_virtual() ) {
				continue;
			}

			$product_weight = $product->get_weight();
			$quantity       = $item->get_quantity();

			if ( $product_weight ) {
				$total_weight += ( $quantity * $product_weight );
			}
		}

		$total_weight = Utils::maybe_convert_weight( $total_weight );

		return apply_filters( 'postnl_order_weight', $total_weight, $order );
	}

	/**
	 * Get selected features in the order admin.
	 *
	 * @return Array
	 */
	public function get_selected_features() {
		return array_filter(
			$this->api_args['backend_data'],
			function( $value ) {
				return ( 'yes' === $value );
			}
		);
	}

	/**
	 * Get product code from api args.
	 *
	 * @return String.
	 */
	public function get_product_code() {
		$checked_features = $this->get_selected_features();

		$features = array_keys( $checked_features );

		$product_code = '3085';

		if ( in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3385';
		}

		if ( in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3090';
		}

		if ( in_array( 'return_no_answer', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3390';
		}

		if ( in_array( 'insured_shipping', $features, true ) ) {
			$product_code = '3087';
		}

		if ( in_array( 'insured_shipping', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3094';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) ) {
			$product_code = '3189';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3089';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3096';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3389';
		}

		if ( in_array( 'letterbox', $features, true ) ) {
			$product_code = '2928';
		}

		return $product_code;
	}

	/**
	 * Get product code that based on NL.
	 *
	 * @return String
	 */
	public function get_product_code_nl() {
		$checked_features = $this->get_selected_features();

		$features = array_keys( $checked_features );

		$product_code = '3085';

		if ( in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3385';
		}

		if ( in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3090';
		}

		if ( in_array( 'return_no_answer', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3390';
		}

		if ( in_array( 'insured_shipping', $features, true ) ) {
			$product_code = '3087';
		}

		if ( in_array( 'insured_shipping', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3094';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) ) {
			$product_code = '3189';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) ) {
			$product_code = '3089';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'only_home_address', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3096';
		}

		if ( in_array( 'signature_on_delivery', $features, true ) && in_array( 'return_no_answer', $features, true ) ) {
			$product_code = '3389';
		}

		if ( in_array( 'letterbox', $features, true ) ) {
			$product_code = '2928';
		}

		return $product_code;
	}
}
