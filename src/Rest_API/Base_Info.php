<?php
/**
 * Class Rest_API\Base_Info file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Base_Info
 *
 * @package PostNLWooCommerce\Rest_API
 */
abstract class Base_Info {
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
	 * Class constructor.
	 *
	 * @param array $post_data Set of API arguments.
	 */
	public function __construct( $post_data ) {
		$this->settings = Settings::get_instance();

		$this->convert_data_to_args( $post_data );
		$this->set_store_address_data();
		$this->set_settings_data();
		$this->set_extra_data_to_api_args();
		$this->parse_args();
	}

	/**
	 * Parses the arguments and sets the instance's properties.
	 *
	 * @throws Exception If some data in $args did not pass validation.
	 */
	abstract protected function parse_args();

	/**
	 * Method to convert the post data to API args.
	 *
	 * @param Array $post_data Data from post variable in checkout page.
	 */
	abstract public function convert_data_to_args( $post_data );

	/**
	 * Set API args with store address data from WC settings.
	 */
	public function set_store_address_data() {
		$this->api_args['store_address'] = array(
			'company'   => $this->settings->get_return_company_name(),
			'email'     => get_bloginfo( 'admin_email' ),
			'address_1' => WC()->countries->get_base_address(),
			'address_2' => WC()->countries->get_base_address_2(),
			'city'      => WC()->countries->get_base_city(),
			'state'     => WC()->countries->get_base_state(),
			'country'   => WC()->countries->get_base_country(),
			'postcode'  => WC()->countries->get_base_postcode(),
		);
	}

	/**
	 * Set API args with data from the shipping settings.
	 */
	public function set_settings_data() {
		$this->api_args['settings'] = array(
			'location_code'            => $this->settings->get_location_code(),
			'customer_code'            => $this->settings->get_customer_code(),
			'customer_num'             => $this->settings->get_customer_num(),
			'cut_off_time'             => $this->settings->get_cut_off_time(),
			'dropoff_days'             => $this->settings->get_dropoff_days(),
			'excluded_dropoff_days'    => $this->settings->get_excluded_dropoff_days(),
			'pickup_points_enabled'    => $this->settings->is_pickup_points_enabled(),
			'delivery_days_enabled'    => $this->settings->is_delivery_days_enabled(),
			'morning_delivery_enabled' => $this->settings->is_morning_delivery_enabled(),
			'evening_delivery_enabled' => $this->settings->is_evening_delivery_enabled(),
			'transit_time'             => $this->settings->get_transit_time(),
			/* Temporarily hardcoded in Settings::get_number_pickup_points(). */
			'number_pickup_points'     => $this->settings->get_number_pickup_points(),
			'number_delivery_days'     => $this->settings->get_number_delivery_days(),
			'return_company'           => $this->settings->get_return_company_name(),
			'return_replynumber'       => $this->settings->get_return_reply_number(),
			'return_address_1'         => $this->settings->get_return_address(),
			'return_address_2'         => $this->settings->get_return_streetnumber(),
			'return_address_city'      => $this->settings->get_return_city(),
			'return_address_zip'       => $this->settings->get_return_zipcode(),
			'return_customer_code'     => $this->settings->get_return_customer_code(),
			'globalpack_barcode_type'  => $this->settings->get_globalpack_barcode_type(),
			'globalpack_customer_code' => $this->settings->get_globalpack_customer_code(),
			'hs_tariff_code'           => $this->settings->get_hs_tariff_code(),
			'country_origin'           => $this->settings->get_country_origin(),
			'label_format'             => $this->settings->get_label_format(),
			'woocommerce_email'        => $this->settings->is_woocommerce_email_enabled(),
			'woocommerce_email_text'   => $this->settings->get_woocommerce_email_text(),
		);
	}

	/**
	 * Set extra API args.
	 */
	public function set_extra_data_to_api_args() {
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
			'first_name' => array(
				'error'    => __( 'Shipping "First Name" is empty!', 'postnl-for-woocommerce' ),
				'validate' => function( $name ) use ( $self ) {
					if ( empty( $name ) ) {
						throw new \Exception(
							__( 'Shipping "First Name" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $name ) use ( $self ) {
					return $self->string_length_sanitization( $name, 50 );
				},
			),
			'last_name'  => array(
				'default' => '',
				'sanitize' => function( $name ) use ( $self ) {
					return $self->string_length_sanitization( $name, 50 );
				},
			),
			'company'    => array(
				'default' => '',
			),
			'address_1'  => array(
				'error'    => __( 'Shipping "Address 1" is empty!', 'postnl-for-woocommerce' ),
				'validate' => function( $name ) use ( $self ) {
					if ( empty( $name ) ) {
						throw new \Exception(
							__( 'Shipping "Address 1" is empty!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $name ) use ( $self ) {
					return $self->string_length_sanitization( $name, 50 );
				},
			),
			'address_2'  => array(
				'default' => '',
			),
			'city'       => array(
				'error' => __( 'Shipping "City" is empty!', 'postnl-for-woocommerce' ),
			),
			'postcode'   => array(
				'error'    => __( 'Shipping "Postcode" is empty!', 'postnl-for-woocommerce' ),
				'sanitize' => function( $value ) use ( $self ) {
					$value = str_replace( ' ', '', $value );
					return $self->string_length_sanitization( $value, 7 );
				},
			),
			'state'      => array(
				'default' => '',
			),
			'country'    => array(
				'error' => __( 'Shipping "Country" is empty!', 'postnl-for-woocommerce' ),
			),
			'house_number'      => array(
				'default' => '',
			),
		);
	}

	/**
	 * Retrieves the args scheme to use with for parsing store address info.
	 *
	 * @return array
	 */
	protected function get_store_info_schema() {
		// Closures in PHP 5.3 do not inherit class context
		// So we need to copy $this into a lexical variable and pass it to closures manually.
		$self = $this;

		return array(
			'company'   => array(
				'default'  => '',
				'sanitize' => function ( $name ) use ( $self ) {
					return htmlspecialchars_decode( $name );
				},
			),
			'email'     => array(
				'default' => '',
			),
			'address_1' => array(
				'error'    => __( 'Store address 1 is not configured!', 'postnl-for-woocommerce' ),
				'validate' => function( $address_1 ) use ( $self ) {
					if ( empty( $address_1 ) ) {
						throw new \Exception(
							__( 'Store address 1 is not configured!', 'postnl-for-woocommerce' )
						);
					}
				},
				'sanitize' => function( $address_1 ) use ( $self ) {
					return $self->string_length_sanitization( $address_1, 50 );
				},
			),
			'address_2' => array(
				'default' => '',
			),
			'city'      => array(
				'error' => __( 'Store city is not configured!', 'postnl-for-woocommerce' ),
			),
			'postcode'  => array(
				'error'    => __( 'Store postcode is not configured!', 'postnl-for-woocommerce' ),
				'sanitize' => function( $value ) use ( $self ) {
					$value = str_replace( ' ', '', $value );
					return $self->string_length_sanitization( $value, 7 );
				},
			),
			'state'     => array(
				'default' => '',
			),
			'country'   => array(
				'error' => __( 'Store country is not configured!', 'postnl-for-woocommerce' ),
			),
		);
	}

	/**
	 * Sanitization for float value.
	 *
	 * @param Float $float Float value.
	 * @param Int   $numcomma Number of character after the comma.
	 *
	 * @return Float
	 */
	protected function float_round_sanitization( $float, $numcomma ) {
		$float = floatval( $float );
		return round( $float, $numcomma );
	}

	/**
	 * Converts a given weight into grams, if necessary.
	 *
	 * @param float  $weight The weight amount.
	 * @param string $uom The unit of measurement of the $weight parameter.
	 *
	 * @return float The potentially converted weight.
	 */
	protected function maybe_convert_to_grams( $weight, $uom ) {
		return round( wc_get_weight( $weight, Utils::used_api_uom(), $uom ) );
	}

	/**
	 * Get cutoff times value from the settings.
	 *
	 * @return String
	 */
	public function get_current_time() {
		return current_time( 'd-m-Y H:i:s' );
	}

	/**
	 * Sanitization for string length.
	 *
	 * @param String $string String value.
	 * @param Int    $max Maximum of character.
	 *
	 * @return String
	 */
	protected function string_length_sanitization( $string, $max ) {
		$max = intval( $max );

		if ( strlen( $string ) <= $max ) {
			return $string;
		}

		return substr( $string, 0, ( $max - 1 ) );
	}
}
