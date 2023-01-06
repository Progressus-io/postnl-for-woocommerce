<?php
/**
 * Class Rest_API\Checkout\Item_Info file.
 *
 * @package PostNLWooCommerce\Rest_API\Checkout
 */

namespace PostNLWooCommerce\Rest_API\Checkout;

use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Rest_API\Base_Info;
use PostNLWooCommerce\Shipping_Method\Settings;
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

		$settings = $this->api_args['settings'];

		$settings['current_time'] = $this->get_current_time();

		$this->body     = Utils::parse_args( $settings, $this->get_body_info_schema() );
		$this->receiver = Utils::parse_args( $this->api_args['shipping_address'], $this->get_receiver_info_schema() );
		$this->shipper  = Utils::parse_args( $this->api_args['store_address'], $this->get_store_info_schema() );
	}

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 */
	public function convert_data_to_args( $post_data ) {
		$post_data = Address_Utils::set_post_data_address( $post_data );

		$this->api_args['shipping_address'] = array(
			'first_name' => ( ! empty( $post_data['shipping_first_name'] ) ) ? $post_data['shipping_first_name'] : '',
			'last_name'  => ( ! empty( $post_data['shipping_last_name'] ) ) ? $post_data['shipping_last_name'] : '',
			'company'    => ( ! empty( $post_data['shipping_company'] ) ) ? $post_data['shipping_company'] : '',
			'address_1'  => ( ! empty( $post_data['shipping_address_1'] ) ) ? $post_data['shipping_address_1'] : '',
			'address_2'  => ( ! empty( $post_data['shipping_address_2'] ) ) ? $post_data['shipping_address_2'] : '',
			'city'       => ( ! empty( $post_data['shipping_city'] ) ) ? $post_data['shipping_city'] : '',
			'state'      => ( ! empty( $post_data['shipping_state'] ) ) ? $post_data['shipping_state'] : '',
			'country'    => ( ! empty( $post_data['shipping_country'] ) ) ? $post_data['shipping_country'] : '',
			'postcode'   => ( ! empty( $post_data['shipping_postcode'] ) ) ? $post_data['shipping_postcode'] : '',
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing store address info.
	 *
	 * @return array
	 */
	protected function get_body_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'location_code'            => array(
				'default' => '',
			),
			'customer_code'            => array(
				'default' => '',
			),
			'customer_num'             => array(
				'default' => '',
			),
			'current_time'             => array(
				'rename' => 'order_date',
				'error'  => __( 'Order date is empty!', 'postnl-for-woocommerce' ),
			),
			'transit_time' => array(
				'rename' => 'shipping_duration',
				'error'  => __( 'Shipping duration is empty!', 'postnl-for-woocommerce' ),
			),
			'cut_off_time'             => array(
				'default'  => '23:00',
				'validate' => function( $time ) {
					$result = preg_match( '/^(?:2[0-4]|[01][1-9]|10):([0-5][0-9])$/', $time );
					if ( 1 !== $result ) {
						throw new \Exception(
							__( 'Wrong format for cut off time!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $time ) use ( $self ) {
					return $self->string_length_sanitization( $time, 5 );
				},
			),
			'dropoff_days'             => array(
				'default'  => array(),
				'sanitize' => function( $dropoff_days ) use ( $self ) {
					if ( empty( $dropoff_days ) || ! is_array( $dropoff_days ) ) {
						return array();
					}

					$return = array();
					foreach ( $dropoff_days as $day ) {
						$return[] = $self->convert_day_to_number( $day );
					}

					return $return;
				},
			),
			'excluded_dropoff_days'    => array(
				'default'  => array(),
				'sanitize' => function( $dropoff_days ) use ( $self ) {
					if ( empty( $dropoff_days ) || ! is_array( $dropoff_days ) ) {
						return array();
					}

					$return = array();
					foreach ( $dropoff_days as $day ) {
						$return[] = $self->convert_day_to_number( $day );
					}

					return $return;
				},
			),
			'pickup_points_enabled'    => array(
				'default'  => false,
				'sanitize' => function( $enabled ) {
					if ( ! is_bool( $enabled ) ) {
						return false;
					}

					return $enabled;
				},
			),
			'delivery_days_enabled'    => array(
				'default'  => false,
				'sanitize' => function( $enabled ) {
					if ( ! is_bool( $enabled ) ) {
						return false;
					}

					return $enabled;
				},
			),
			'evening_delivery_enabled' => array(
				'default'  => false,
				'sanitize' => function( $enabled ) {
					if ( ! is_bool( $enabled ) ) {
						return false;
					}

					return $enabled;
				},
			),
			'morning_delivery_enabled' => array(
				'default'  => false,
				'sanitize' => function( $enabled ) {
					if ( ! is_bool( $enabled ) ) {
						return false;
					}

					return $enabled;
				},
			),
			'number_pickup_points'     => array(
				'rename'   => 'locations',
				'default'  => 10,
				'validate' => function( $value ) {
					if ( ! is_numeric( $value ) ) {
						throw new \Exception(
							__( 'Locations value is not a number!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $value ) {
					return intval( $value );
				},
			),
			'number_delivery_days'     => array(
				'rename'   => 'days',
				'default'  => 10,
				'sanitize' => function( $value ) {
					return intval( $value );
				},
			),
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
			'address_1' => array(
				'default'  => '',
				'sanitize' => function( $value ) use ( $self ) {
					return $self->string_length_sanitization( $value, 35 );
				},
			),
			'address_2' => array(
				'default'  => '',
				'sanitize' => function( $value ) use ( $self ) {
					return $self->string_length_sanitization( $value, 5 );
				},
			),
			'city'      => array(
				'default' => '',
			),
			'postcode'  => array(
				'error'    => esc_html__( 'Shipping "Postcode" is empty!', 'postnl-for-woocommerce' ),
				'sanitize' => function( $value ) use ( $self ) {
					$value = str_replace( ' ', '', $value );
					return $self->string_length_sanitization( $value, 7 );
				},
			),
			'country'   => array(
				'default'  => '',
				'sanitize' => function( $value ) use ( $self ) {
					return $self->string_length_sanitization( $value, 2 );
				},
			),
		);
	}

	/**
	 * Convert day string to number.
	 *
	 * @param String $day Three character of day name.
	 *
	 * @return String
	 */
	public function convert_day_to_number( $day ) {
		$code = '';

		switch ( $day ) {
			case 'mon':
			case 'monday':
				$code = '01';
				break;

			case 'tue':
			case 'tuesday':
				$code = '02';
				break;

			case 'wed':
			case 'wednesday':
				$code = '03';
				break;

			case 'thu':
			case 'thursday':
				$code = '04';
				break;

			case 'fri':
			case 'friday':
				$code = '05';
				break;

			case 'sat':
			case 'saturday':
				$code = '06';
				break;

			case 'sun':
			case 'sunday':
				$code = '07';
				break;

			default:
				$code = '';
				break;
		}

		return $code;
	}
}
