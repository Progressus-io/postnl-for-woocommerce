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
use PostNLWooCommerce\Helper\Mapping;

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
	 * Pickup points data of the item info.
	 *
	 * @var pickup_points
	 */
	public $pickup_points;

	/**
	 * Delivery day data of the item info.
	 *
	 * @var delivery_day
	 */
	public $delivery_day;

	/**
	 * Saved data from database.
	 *
	 * @var backend_data
	 */
	public $backend_data;

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	protected function parse_args() {
		$customer_info = $this->api_args['settings'] + $this->api_args['store_address'];
		$shipment      = $this->api_args['billing_address'] + $this->api_args['order_details'];

		$this->shipment      = Utils::parse_args( $shipment, $this->get_shipment_info_schema() );
		$this->receiver      = Utils::parse_args( $this->api_args['shipping_address'], $this->get_receiver_info_schema() );
		$this->customer      = Utils::parse_args( $customer_info, $this->get_customer_info_schema() );
		$this->shipper       = Utils::parse_args( $this->api_args['store_address'], $this->get_store_info_schema() );
		$this->pickup_points = Utils::parse_args( $this->api_args['frontend_data']['pickup_points'], $this->get_pickup_points_info_schema() );
		$this->delivery_day  = Utils::parse_args( $this->api_args['frontend_data']['delivery_day'], $this->get_delivery_day_info_schema() );
		$this->backend_data  = Utils::parse_args( $this->api_args['backend_data'], $this->get_backend_data_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 *
	 * @throws \Exception When order ID doesnt exists.
	 */
	public function convert_data_to_args( $post_data ) {
		if ( ! is_a( $post_data['order'], 'WC_Order' ) ) {
			throw new \Exception(
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
			'delivery_day'  => array(
				'value' => $saved_data['frontend']['delivery_day'] ?? '',
				'date'  => $saved_data['frontend']['delivery_day_date'] ?? '',
				'from'  => $saved_data['frontend']['delivery_day_from'] ?? '',
				'to'    => $saved_data['frontend']['delivery_day_to'] ?? '',
				'price' => $saved_data['frontend']['delivery_day_price'] ?? '',
				'type'  => $saved_data['frontend']['delivery_day_type'] ?? '',
			),
			'pickup_points' => array(
				'value'      => $saved_data['frontend']['dropoff_points'] ?? '',
				'company'    => $saved_data['frontend']['dropoff_points_address_company'] ?? '',
				'distance'   => $saved_data['frontend']['dropoff_points_distance'] ?? '',
				'address_1'  => $saved_data['frontend']['dropoff_points_address_address_1'] ?? '',
				'address_2'  => $saved_data['frontend']['dropoff_points_address_address_2'] ?? '',
				'city'       => $saved_data['frontend']['dropoff_points_address_city'] ?? '',
				'postcode'   => $saved_data['frontend']['dropoff_points_address_postcode'] ?? '',
				'country'    => $saved_data['frontend']['dropoff_points_address_country'] ?? '',
				'partner_id' => $saved_data['frontend']['dropoff_points_parther_id'] ?? '',
				'date'       => $saved_data['frontend']['dropoff_points_date'] ?? '',
				'time'       => $saved_data['frontend']['dropoff_points_time'] ?? '',
			),
		);

		$this->api_args['order_details'] = array(
			'order_id'     => $order->get_id(),
			'total_weight' => $this->calculate_order_weight( $order ),
		);
	}

	/**
	 * Is pickup point being selected or not.
	 *
	 * @return Boolean
	 */
	public function is_pickup_points() {
		return ! empty( $this->api_args['frontend_data']['pickup_points']['value'] );
	}

	/**
	 * Is pickup point being selected or not.
	 *
	 * @return Boolean
	 */
	public function is_delivery_day() {
		return ! empty( $this->api_args['frontend_data']['delivery_day']['value'] );
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
						throw new \Exception(
							__( 'Store email is empty!', 'postnl-for-woocommerce' )
						);
					}

					if ( ! is_email( $value ) ) {
						throw new \Exception(
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
						throw new \Exception(
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
						throw new \Exception(
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
	 * Retrieves the args scheme to use with for parsing pickup points info.
	 *
	 * @return array
	 */
	protected function get_delivery_day_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'date'  => array(
				'default'  => '',
				'validate' => function( $date ) use ( $self ) {
					if ( empty( $date ) && $self->is_delivery_day() ) {
						throw new \Exception(
							__( 'Delivery day "Date" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'from'  => array(
				'default'  => '',
				'validate' => function( $hour ) use ( $self ) {
					if ( empty( $hour ) && $self->is_delivery_day() ) {
						throw new \Exception(
							__( 'Delivery day "From" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'to'    => array(
				'default'  => '',
				'validate' => function( $hour ) use ( $self ) {
					if ( empty( $hour ) && $self->is_delivery_day() ) {
						throw new \Exception(
							__( 'Delivery day "To" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'price' => array(
				'default'  => '',
				'validate' => function( $price ) use ( $self ) {
					if ( empty( $price ) && $self->is_delivery_day() ) {
						throw new \Exception(
							__( 'Delivery day "Price" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'type'            => array(
				'default'  => '',
				'validate' => function( $type ) use ( $self ) {
					if ( empty( $type ) && $self->is_delivery_day() ) {
						throw new \Exception(
							__( 'Delivery day "Type" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing pickup points info.
	 *
	 * @return array
	 */
	protected function get_pickup_points_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'company'          => array(
				'validate' => function( $company ) use ( $self ) {
					if ( empty( $company ) && $self->is_pickup_points() ) {
						throw new \Exception(
							__( 'Pickup "Company name" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'address_1'        => array(
				'validate' => function( $address ) use ( $self ) {
					if ( empty( $address ) && $self->is_pickup_points() ) {
						throw new \Exception(
							__( 'Pickup "Address 1" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $address ) use ( $self ) {
					return $self->string_length_sanitization( $address, 50 );
				},
			),
			'address_2'        => array(
				'default' => '',
			),
			'city'             => array(
				'validate' => function( $city ) use ( $self ) {
					if ( empty( $city ) && $self->is_pickup_points() ) {
						throw new \Exception(
							__( 'Pickup Point "City" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'postcode'         => array(
				'validate' => function( $postcode ) use ( $self ) {
					if ( empty( $postcode ) && $self->is_pickup_points() ) {
						throw new \Exception(
							__( 'Pickup Point "Postcode" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'state'            => array(
				'default' => '',
			),
			'country'          => array(
				'validate' => function( $country ) use ( $self ) {
					if ( empty( $country ) && $self->is_pickup_points() ) {
						throw new \Exception(
							__( 'Pickup Point "Country" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing data from database.
	 *
	 * @return array
	 */
	protected function get_backend_data_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'delivery_type'         => array(
				'default'  => 'Standard',
				'sanitize' => function( $type ) {
					return ( 'Evening' === $type ) ? 'Evening' : 'Standard';
				},
			),
			'insured_shipping'      => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'return_no_answer'      => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'signature_on_delivery' => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'only_home_address'     => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'num_labels'            => array(
				'default'  => '1',
				'sanitize' => function( $num ) use ( $self ) {
					$abs_number = abs( intval( $num ) );
					if ( empty( $abs_number ) ) {
						return 1;
					}

					return ( 10 >= $abs_number ) ? $abs_number : 10;
				},
			),
			'create_return_label'   => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'letterbox'             => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
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
	 * Get selected shipping features.
	 *
	 * @return String
	 */
	public function get_selected_shipping_features() {
		foreach ( $this->api_args['frontend_data'] as $parent_name => $parent_data ) {
			foreach ( $parent_data as $data_key => $data_value ) {
				if ( ! empty( $data_value ) ) {
					return $parent_name;
				}
			}
		}
	}

	/**
	 * Get selected features in the order admin.
	 *
	 * @return Array
	 */
	public function get_selected_label_features() {
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
		$checked_features = $this->get_selected_label_features();
		$shipping_feature = $this->get_selected_shipping_features();
		$from_country     = $this->api_args['store_address']['country'];
		$to_country       = $this->api_args['shipping_address']['country'];

		$features = array_keys( $checked_features );
		$code_map = Mapping::product_code();

		$product_code = '';
		$destination  = Utils::get_shipping_zone( $to_country );

		if ( empty( $code_map[ $from_country ][ $destination ][ $shipping_feature ] ) ) {
			return $product_code;
		}

		foreach ( $code_map[ $from_country ][ $destination ][ $shipping_feature ] as $code => $feature_list ) {
			if ( empty( $feature_list ) && empty( $product_code ) ) {
				$product_code = $code;
				continue;
			}

			$is_this_it = true;
			foreach ( $feature_list as $feature ) {
				if ( ! in_array( $feature, $features ) ) {
					$is_this_it = false;
				}
			}

			if ( $is_this_it ) {
				$product_code = $code;
			}
		}

		return $product_code;
	}
}