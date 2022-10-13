<?php
/**
 * Class Frontend/Container file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Checkout;
use PostNLWooCommerce\Rest_API\Postcode_Check;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Delivery_Day
 *
 * @package PostNLWooCommerce\Frontend
 */
class Container {
	/**
	 * Settings class instance.
	 *
	 * @var PostNLWooCommerce\Shipping_Method\Settings
	 */
	protected $settings;

	/**
	 * Template file name.
	 *
	 * @var string
	 */
	public $template_file;

	/**
	 * Tab field name.
	 *
	 * @var tab_field
	 */
	protected $tab_field = POSTNL_SETTINGS_ID . '_option';

	/**
	 * Init and hook in the integration.
	 */
	public function __construct() {
		$this->settings = Settings::get_instance();

		$this->set_template_file();
		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'display_fields' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ), 10, 1 );

		add_filter( 'woocommerce_shipping_' . POSTNL_SETTINGS_ID . '_is_available', array( $this, 'is_shipping_method_available' ), 10, 2 );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'add_shipping_method_icon' ), 10, 2 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'fill_validated_address' ) );
	}

	/**
	 * Enqueue scripts and style.
	 */
	public function enqueue_scripts_styles() {
		// Enqueue styles.
		wp_enqueue_style(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/fe-checkout.css',
			array(),
			POSTNL_WC_VERSION
		);

		// Enqueue scripts.
		wp_enqueue_script(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/fe-checkout.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);
	}

	/**
	 * Set the template filename.
	 */
	public function set_template_file() {
		$this->template_file = 'checkout/postnl-container.php';
	}

	/**
	 * Get enabled tabs.
	 *
	 * @param array $response Response from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_available_tabs( $response ) {
		return apply_filters( 'postnl_frontend_checkout_tab', array(), $response );
	}

	/**
	 * Get tab field value.
	 *
	 * @param array $post_data Array of global _POST data.
	 *
	 * @return String
	 */
	public function get_tab_field_value( $post_data ) {
		return ( ! empty( $post_data[ $this->tab_field ] ) ) ? $post_data[ $this->tab_field ] : '';
	}

	/**
	 * Get checkout $_POST['post_data'].
	 *
	 * @return array
	 */
	public function get_checkout_post_data() {
		if ( empty( $_REQUEST['post_data'] ) ) {
			return array();
		}

		$post_data = array();

		parse_str( sanitize_text_field( wp_unslash( urldecode( $_REQUEST['post_data'] ) ) ), $post_data );

		return $post_data;
	}

	/**
	 * Get data from PostNL Checkout Rest API.
	 *
	 * @return array
	 */
	public function get_checkout_data() {
		try {
			$post_data = $this->get_checkout_post_data();

			if ( empty( $post_data ) ) {
				return array();
			}

			$post_data = Utils::set_post_data_address( $post_data );

			if ( ! in_array( $post_data['shipping_country'], Utils::get_available_country(), true ) ) {
				return array();
			}

			foreach ( $post_data as $post_key => $post_value ) {
				if ( false !== strpos( $post_key, 'shipping_method' ) && false === strpos( $post_value[0], POSTNL_SETTINGS_ID ) ) {
					return array();
				}
			}

			// Validate address if required
			if ( $this->is_address_validation_required() ) {

				if ( ! isset( $post_data['shipping_postcode'] ) || '' === $post_data['shipping_postcode'] ) {
					return array();
				}

				if ( empty( $post_data['shipping_house_number'] ) && $this->settings->is_reorder_nl_address_enabled() ) {
					return array();
				} elseif ( empty( $post_data['shipping_house_number'] ) && ! $this->settings->is_reorder_nl_address_enabled() ) {
					throw new \Exception( 'Address does not contain house number!' );
				}

				$this->validated_address( $post_data );
			}

			$item_info = new Checkout\Item_Info( $post_data );
			$api_call  = new Checkout\Client( $item_info );
			$response  = $api_call->send_request();

			return array(
				'response'  => $response,
				'post_data' => $post_data,
			);
		} catch ( \Exception $e ) {
			return array(
				'response' => array(),
				'error'    => $e->getMessage(),
			);
		}
	}

	/**
	 * Add delivery day fields.
	 */
	public function display_fields() {
		// Only display the fields if these two options are enabled in the settings.
		if ( ! $this->settings->is_delivery_days_enabled() && ! $this->settings->is_pickup_points_enabled() ) {
			return;
		}

		$checkout_data = $this->get_checkout_data();

		if ( ! empty( $checkout_data['error'] ) ) {
			wc_add_notice( $checkout_data['error'], 'error' );
		}

		if ( empty( $checkout_data['response'] ) ) {
			return;
		}

		$template_args = array(
			'tabs'      => $this->get_available_tabs( $checkout_data['response'] ),
			'response'  => $checkout_data['response'],
			'post_data' => $checkout_data['post_data'],
			'fields'    => array(
				array(
					'name'  => $this->tab_field,
					'value' => $this->get_tab_field_value( $checkout_data['post_data'] ),
				),
			),
		);

		wc_get_template( $this->template_file, $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}

	/**
	 * Check address by PostNL Checkout Rest API.
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function validated_address( $post_data ) {
		$item_info = new Postcode_Check\Item_Info( $post_data );
		$api_call  = new Postcode_Check\Client( $item_info );
		$response  = $api_call->send_request();

		if ( empty( $response[0] ) ) {
			// Clear validated address.
			WC()->session->set( POSTNL_SETTINGS_ID . '_validated_address', [] );

			// Add notice without blocking checkout call
			wc_add_notice( esc_html__( 'This is not a valid address!', 'postnl-for-woocommerce' ), 'notice' );
		} else {
			// Set validated address.
			WC()->session->set( POSTNL_SETTINGS_ID . '_validated_address', [
				'city'                      => $response[0]['city'],
				'street'                    => $response[0]['streetName'],
				'house_number'              => $response[0]['houseNumber'],
				'ship_to_different_address' => ! empty( $post_data['ship_to_different_address'] )
			] );
		}
	}

	/**
	 * Fill checkout form fields after address validation.
	 *
	 * @param $fragments
	 *
	 * @return mixed
	 */
	public function fill_validated_address( $fragments ) {
		if ( ! $this->settings->is_reorder_nl_address_enabled() ) {
			return $fragments;
		}

		$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );

		if ( ! is_array( $validated_address ) || empty( $validated_address ) ) {
			return $fragments;
		}

		if ( $validated_address['ship_to_different_address'] ) {
			$address_type = 'shipping';
		} else {
			$address_type = 'billing';
		}

		// Fill Address 1 with street name & house number if fields reordering disabled.
		if ( ! $this->settings->is_reorder_nl_address_enabled() ) {
			$address_1 = $validated_address['street'] . ' ' . $validated_address['house_number'];
		} else {
			$address_1 = $validated_address['street'];
		}
		$fragments[ '#' . $address_type . '_address_1' ] = '<input type="text" class="input-text " name="' . $address_type . '_address_1" id="' . $address_type . '_address_1" value="' . $address_1 . '" autocomplete="address-line1">';

		$fragments[ '#' . $address_type . '_city' ] = '<input type="text" class="input-text " name="' . $address_type . '_city" id="' . $address_type . '_city" placeholder="" value="' . $validated_address['city'] . '" autocomplete="address-level2">';

		return $fragments;
	}

	/**
	 * Add cart fees.
	 *
	 * @param WC_Cart $cart Cart object.
	 */
	public function add_cart_fees( $cart ) {
		$post_data = $this->get_checkout_post_data();

		if ( empty( $post_data ) ) {
			return;
		}

		if ( ! empty( $post_data['postnl_delivery_day_price'] ) && 'delivery_day' === $post_data['postnl_option'] ) {
			$evening_fee = Base::evening_fee_data();
			$cart->add_fee( $evening_fee['fee_name'], wc_format_decimal( $post_data['postnl_delivery_day_price'] ) );
		}
	}

	/**
	 * Check if the shipping method is available for the shipping country.
	 *
	 * @param Boolean $available Default value for shipping method availability.
	 * @param Array   $package   Current package in the cart.
	 *
	 * @return Boolean.
	 */
	public function is_shipping_method_available( $available, $package ) {
		if ( ! empty( $package['destination']['country'] ) && 'BE' !== $package['destination']['country'] && 'BE' === WC()->countries->get_base_country() ) {
			return false;
		}

		return $available;
	}

	/**
	 * Replace shipping method title with Icon.
	 *
	 * @param $label
	 * @param $method
	 *
	 * @return mixed|string
	 */
	public function add_shipping_method_icon( $label, $method ) {
		if( POSTNL_SETTINGS_ID === $method->method_id ) {
			$method_title   = $method->get_label();
			$label          = '<img src="'. esc_url( trailingslashit( POSTNL_WC_PLUGIN_DIR_URL ) . 'assets/images/postnl-logo-40x40.png' ) .'" class="postnl_shipping_method_icon" alt="'. $method_title .'" />' . $label;
		}

		return $label;
  }
  
	 * Get Cart Shipping country
	 *
	 * @return string|null
	 */
	public function get_shipping_country() {
		return WC()->customer->get_shipping_country();
	}

	/**
	 * Get Cart Billing country
	 *
	 * @return string|null
	 */
	public function get_billing_country() {
		return WC()->customer->get_billing_country();
	}

	/**
	 * Check if address validation required.
	 *
	 * @return bool
	 */
	public function is_address_validation_required() {
		if ( ! $this->settings->is_validate_nl_address_enabled() ) {
			return false;
		}

		if ( 'NL' !== $this->get_billing_country() && 'NL' !== $this->get_shipping_country() ) {
			return false;
		}

		return true;
	}
}
