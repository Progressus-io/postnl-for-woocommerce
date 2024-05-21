<?php
/**
 * Class Rest_API\Shipping\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

namespace PostNLWooCommerce\Rest_API\Shipping;

use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Product\Single;

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
	 * Order item data of the item info.
	 *
	 * @var contents
	 */
	public $contents = array();

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
	 * Weight UOM that set in WooCommerce settings.
	 *
	 * @var weight_uom
	 */
	public $weight_uom;
	
	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	protected function parse_args() {
		$this->weight_uom = Utils::get_uom();

		$customer_info = $this->api_args['settings'] + $this->api_args['store_address'];
		$shipment      = $this->api_args['billing_address'] + $this->api_args['order_details'];

		$this->shipment      = Utils::parse_args( $shipment, $this->get_shipment_info_schema() );
		$this->receiver      = Utils::parse_args( $this->api_args['shipping_address'], $this->get_receiver_info_schema() );
		$this->customer      = Utils::parse_args( $customer_info, $this->get_customer_info_schema() );
		$this->shipper       = Utils::parse_args( $this->api_args['store_address'], $this->get_store_info_schema() );
		$this->pickup_points = Utils::parse_args( $this->api_args['frontend_data']['pickup_points'], $this->get_pickup_points_info_schema() );
		$this->delivery_day  = Utils::parse_args( $this->api_args['frontend_data']['delivery_day'], $this->get_delivery_day_info_schema() );
		$this->backend_data  = Utils::parse_args( $this->api_args['backend_data'], $this->get_backend_data_info_schema() );

		if ( ! empty( $this->api_args['order_details']['contents'] ) ) {
			foreach ( $this->api_args['order_details']['contents'] as $item_info ) {
				$this->contents[] = Utils::parse_args( $item_info, $this->get_content_item_info_schema() );
			}
		}
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
				__( 'Order ID does not exist!', 'postnl-for-woocommerce' )
			);
		}

		$order        = $post_data['order'];
		$saved_data   = $post_data['saved_data'];
		$order_weight = $this->calculate_order_weight( $order );

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

		$shipping_address = array(
			'first_name'   => $order->get_shipping_first_name(),
			'last_name'    => $order->get_shipping_last_name(),
			'company'      => $order->get_shipping_company(),
			'address_1'    => $order->get_shipping_address_1(),
			'address_2'    => $order->get_shipping_address_2(),
			'city'         => $order->get_shipping_city(),
			'state'        => $order->get_shipping_state(),
			'country'      => $order->get_shipping_country(),
			'postcode'     => $order->get_shipping_postcode(),
			'house_number' => $order->get_meta( '_shipping_house_number' ),
		);

		// Check the house number.
		$this->api_args['shipping_address'] = Address_Utils::split_address( $shipping_address );

		$this->api_args['backend_data'] = array(
			'delivery_type'         => $saved_data['backend']['delivery_type'] ?? '',
			'insured_shipping'      => $saved_data['backend']['insured_shipping'] ?? '',
			'return_no_answer'      => $saved_data['backend']['return_no_answer'] ?? '',
			'signature_on_delivery' => $saved_data['backend']['signature_on_delivery'] ?? '',
			'only_home_address'     => $saved_data['backend']['only_home_address'] ?? '',
			'num_labels'            => $saved_data['backend']['num_labels'] ?? '',
			'create_return_label'   => $saved_data['backend']['create_return_label'] ?? '',
			'letterbox'             => $saved_data['backend']['letterbox'] ?? '',
			'id_check'              => $saved_data['backend']['id_check'] ?? '',
			'packets'               => $saved_data['backend']['packets'] ?? '',
			'mailboxpacket'         => $saved_data['backend']['mailboxpacket'] ?? '',
			'track_and_trace'       => $saved_data['backend']['track_and_trace'] ?? '',
			'insured_plus'          => $saved_data['backend']['insured_plus'] ?? '',
		);

		// Check mailbox weight limit
		$this->check_mailbox_weight_limit( $this->api_args['backend_data'], $order_weight );

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
			'order_id'       => $order->get_id(),
			'order_number'   => $order->get_order_number(),
			'main_barcode'   => $post_data['main_barcode'],
			'barcodes'       => $post_data['barcodes'],
			'return_barcode' => $post_data['return_barcode'],
			'currency'       => $order->get_currency(),
			'total_weight'   => $order_weight,
			'subtotal'       => $order->get_subtotal(),
		);

		// Check mailbox weight limit.
		$this->check_insurance_amount_limit( $this->api_args['backend_data'], $order->get_subtotal() );

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			if ( ! is_a( $product, 'WC_Product' ) || $product->is_virtual() ) {
				continue;
			}

			$hs_code = ! empty( $product->get_meta( Single::HS_CODE_FIELD ) ) ? $product->get_meta( Single::HS_CODE_FIELD ) : $this->settings->get_hs_tariff_code();
			$origin  = ! empty( $product->get_meta( Single::ORIGIN_FIELD ) ) ? $product->get_meta( Single::ORIGIN_FIELD ) : $this->settings->get_country_origin();

			$content = array(
				'product_id'       => $product->get_id(),
				'qty'              => $item->get_quantity(),
				'sku'              => $product->get_sku(),
				'item_value'       => $item->get_subtotal(),
				'item_description' => $product->get_name(),
				'item_weight'      => $product->get_weight(),
				'hs_code'          => $hs_code,
				'origin'           => $origin,
			);

			$this->api_args['order_details']['contents'][] = $content;
		}
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
		$this->set_order_shipping_product();
		// $this->set_rest_of_world_args();
	}

	/**
	 * Set product code in the order details.
	 */
	public function set_order_shipping_product() {
		$this->api_args['order_details']['shipping_product'] = $this->get_shipping_product();
		$this->api_args['order_details']['product_options']  = $this->get_product_options();
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
	 * Retrieves the args scheme to use with for parsing customer info.
	 *
	 * @return array
	 */
	protected function get_customer_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'location_code'        => array(
				'error' => __( 'Location Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_code'        => array(
				'error' => __( 'Customer Code is empty!', 'postnl-for-woocommerce' ),
			),
			'customer_num'         => array(
				'error' => __( 'Customer Number is empty!', 'postnl-for-woocommerce' ),
			),
			'company'              => array(
				'default' => '',
			),
			'email'                => array(
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
			'return_company'       => array(
				'default' => '',
			),
			'return_address_1'     => array(
				'default'  => '',
				'sanitize' => function( $value ) use ( $self ) {
					if ( 'NL' === $self->api_args['store_address']['country'] ) {
						return 'Antwoordnummer';
					}

					return $self->string_length_sanitization( $value, 95 );
				},
			),
			'return_address_2'     => array(
				'default'  => '',
				'sanitize' => function( $value ) use ( $self ) {
					if ( 'NL' === $self->api_args['store_address']['country'] ) {
						$value = $self->api_args['settings']['return_replynumber'];
					}

					return $self->string_length_sanitization( $value, 35 );
				},
			),
			'return_address_city'  => array(
				'default' => '',
			),
			'return_address_zip'   => array(
				'default' => '',
			),
			'return_customer_code' => array(
				'default' => '',
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
			'order_id'         => array(
				'error' => __( 'Order ID is empty!', 'postnl-for-woocommerce' ),
			),
			'order_number'     => array(
				'error' => __( 'Order number is empty!', 'postnl-for-woocommerce' ),
			),
			'main_barcode'     => array(
				'error' => __( 'Barcode is empty!', 'postnl-for-woocommerce' ),
			),
			'barcodes'         => array(
				'default' => array(),
			),
			'return_barcode'   => array(
				'default' => '',
			),
			'shipping_product' => array(
				'error'    => __( 'Product code is empty!', 'postnl-for-woocommerce' ),
				'validate' => function( $value ) {
					if ( empty( $value ) || ! is_numeric( $value['code'] ) && 4 !== strlen( $value['code'] ) ) {
						throw new \Exception(
							__( 'Wrong format for product code!', 'postnl-for-woocommerce' )
						);
					}
				}
			),
			'product_options' => array(
				'default'  => array(
					'characteristic' => '',
					'option'         => '',
				),
				'sanitize' => function ( $value ) use ( $self ) {
					return array(
						'characteristic' => ! empty( $value['characteristic'] ) ? $self->string_length_sanitization( $value['characteristic'], 3 ) : '',
						'option'         => ! empty( $value['option'] ) ? $self->string_length_sanitization( $value['option'], 3 ) : '',
					);
				},
			),
			'printer_type'    => array(
				'default'  => 'GraphicFile|PDF',
				'sanitize' => function( $value ) use ( $self ) {
					return 'GraphicFile|PDF';
				},
			),
			'total_weight'    => array(
				'error'    => __( 'Total weight is empty!', 'postnl-for-woocommerce' ),
				'sanitize' => function( $value ) use ( $self ) {
					return $self->float_round_sanitization( $value, 2 );
				},
			),
			'subtotal'        => array(
				'default'  => 0,
				'sanitize' => function( $value ) use ( $self ) {
					return $self->float_round_sanitization( $value, 2 );
				},
			),
			'email'           => array(
				'validate' => function( $value ) {
					if ( ! is_email( $value ) ) {
						throw new \Exception(
							__( 'Customer email is not valid!', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'currency'     => array(
				'validate' => function( $value ) {
					if ( ! Utils::check_available_currency( $value ) ) {
						throw new \Exception(
							__( 'Currency is not available!', 'postnl-for-woocommerce' )
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
				'sanitize' => function( $value ) {
					$timestamp = strtotime( $value );
					$date      = gmdate( 'd-m-Y', $timestamp );

					return $date;
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
				'sanitize' => function( $value ) {
					return $value . ':00';
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
				'sanitize' => function( $value ) {
					return $value . ':00';
				},
			),
			'price' => array(
				'default' => '',
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
			'id_check'              => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'packets'              => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'mailboxpacket'              => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'track_and_trace'              => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
			'insured_plus'          => array(
				'default'  => false,
				'sanitize' => function( $picked ) {
					return ( 'yes' === $picked );
				},
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with parser for parsing order content item info.
	 *
	 * @since [*next-version*]
	 *
	 * @return array
	 */
	protected function get_content_item_info_schema() {
		// Closures in PHP 5.3 do not inherit class context.
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'hs_code'          => array(
				'default'  => '',
				'validate' => function( $hs_code ) {
					$length = is_string( $hs_code ) ? strlen( $hs_code ) : 0;

					if ( empty( $length ) ) {
						return;
					}

					if ( $length < 6 || $length > 20 ) {
						throw new \Exception(
							__( 'Item HS Code must be between 6 and 20 characters long', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'item_description' => array(
				'rename'   => 'description',
				'default'  => '',
				'sanitize' => function( $description ) use ( $self ) {
					return $self->string_length_sanitization( $description, 35 );
				},
			),
			'product_id'       => array(
				'error' => __( 'Item "Product ID" is empty!', 'postnl-for-woocommerce' ),
			),
			'sku'              => array(
				'error' => __( 'Item "Product SKU" is empty!', 'postnl-for-woocommerce' ),
			),
			'item_value'       => array(
				'rename'   => 'value',
				'default'  => 0,
				'sanitize' => function( $value ) use ( $self ) {
					return $self->float_round_sanitization( $value, 2 );
				},
			),
			'origin'           => array(
				'default' => Utils::get_base_country(),
			),
			'qty'              => array(
				'validate' => function( $qty ) {

					if ( ! is_numeric( $qty ) || $qty < 1 ) {
						throw new \Exception(
							__( 'Item quantity must be more than 1', 'postnl-for-woocommerce' )
						);
					}
				},
			),
			'item_weight'      => array(
				'rename'   => 'weight',
				'sanitize' => function ( $weight ) use ( $self ) {

					$weight = $self->maybe_convert_to_grams( $weight, $self->weight_uom );
					$weight = ( $weight > 1 ) ? $weight : 1;
					return $weight;
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

		return array_key_first( $this->api_args['frontend_data'] );
	}

	/**
	 * Get product from api args.
	 *
	 * @return array.
	 * @throws \Exception
	 */
	public function get_shipping_product() {
		$checked_features = Utils::get_selected_label_features( $this->api_args['backend_data'] );
		$shipping_feature = $this->get_selected_shipping_features();
		$from_country     = $this->api_args['store_address']['country'];
		$to_country       = $this->api_args['shipping_address']['country'];

		$features = array_keys( $checked_features );
		$code_map = Mapping::products_data();

		$selected_product = array();
		$destination  = Utils::get_shipping_zone( $to_country );

		if ( empty( $code_map[ $from_country ][ $destination ][ $shipping_feature ] ) ) {
			return $selected_product;
		}
		foreach ( $code_map[ $from_country ][ $destination ][ $shipping_feature ] as $product ) {
			if ( empty( $product['combination'] ) && empty( $selected_product ) ) {
				$selected_product = $product;
				continue;
			}
			$is_this_it = true;
			foreach ( $features as $feature ) {
				if ( ! in_array( $feature,  $product['combination']) ) {
					$is_this_it = false;
				}				
			}
			foreach($product['combination'] as $combination){
				if ( ! in_array( $combination,  $features) ) {
					$is_this_it = false;
				}
			}

			if ( $is_this_it ) {
				$selected_product = $product;
			}
		}

		return $selected_product;
	}

	/**
	 * Get product code from api args.
	 *
	 * @return string.
	 * @throws \Exception
	 */
	public function get_product_code() {
		$product = $this->get_shipping_product();

		return $product['code'] ?? '';
	}

	/**
	 * Get product options from api args.
	 *
	 * @return String.
	 */
	public function get_product_options() {
		$option_map   = Mapping::additional_product_options();
		$from_country = $this->api_args['store_address']['country'];
		$to_country   = $this->api_args['shipping_address']['country'];
		$destination  = Utils::get_shipping_zone( $to_country );

		foreach ( $this->api_args as $arg_keys => $arg_data ) {
			if ( empty( $option_map[ $from_country ][ $destination ][ $arg_keys ] ) ) {
				continue;
			}

			foreach ( $arg_data as $index => $data ) {
				if ( empty( $option_map[ $from_country ][ $destination ][ $arg_keys ][ $index ] ) ) {
					continue;
				}

				foreach ( $data as $idx => $val ) {
					if ( ! empty( $option_map[ $from_country ][ $destination ][ $arg_keys ][ $index ][ $idx ][ $val ] ) ) {
						return $option_map[ $from_country ][ $destination ][ $arg_keys ][ $index ][ $idx ][ $val ];
					}
				}
			}
		}

		return array();
	}

	/**
	 * Check mailbox weight limit.
	 *
	 * @param $backend_data  .
	 * @param $order_weight  .
	 *
	 * @return void.
	 * @throws \Exception if the order weight exceeds 2000 grams.
	 */
	protected function check_mailbox_weight_limit( $backend_data, $order_weight ) {
		$is_mailbox = 'yes' === $backend_data['mailboxpacket'] || 'yes' === $backend_data['letterbox'];

		if ( $is_mailbox && 2000 < $order_weight ) {
			throw new \Exception(
				esc_html__( 'Max weight for Mailbox Packet is 2kg!', 'postnl-for-woocommerce' )
			);
		}
	}

	/**
	 * Check Insurance amount limit.
	 *
	 * @param $backend_data  .
	 * @param $order_total  .
	 *
	 * @return void.
	 * @throws \Exception if the order weight exceeds € 5000.
	 */
	protected function check_insurance_amount_limit( $backend_data, $order_total ) {
		$is_non_eu_shipment = $this->is_rest_of_world();
	
		// For non-EU shipments, set the insured amount to €500 if insurance is selected
		if ( $is_non_eu_shipment && 'yes' === $backend_data['insured_shipping'] ) {
			$insured_amount = 500;
		}
	
		// For EU shipments, validate that insurance does not exceed €5000
		elseif ( !$is_non_eu_shipment && 'yes' === $backend_data['insured_shipping'] && $order_total > 5000 ) {
			throw new \Exception(
				__( 'Insurance amount for EU shipments cannot exceed €5000. Your total is: ' . $order_total, 'postnl-for-woocommerce' )
			);
		}
	}

}
