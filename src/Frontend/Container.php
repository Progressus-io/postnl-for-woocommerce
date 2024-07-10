<?php
/**
 * Class Frontend/Container file.
 *
 * @package PostNLWooCommerce\Frontend
 */

namespace PostNLWooCommerce\Frontend;

use PostNLWooCommerce\Address_Utils;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Rest_API\Checkout;
use PostNLWooCommerce\Rest_API\Postcode_Check;
use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Helper\Mapping;

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

		$this->init_hooks();
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'postnl_fields' ),10 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ), 10, 1 );

		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'fill_validated_address' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'add_shipping_method_icon' ), 10, 2 );
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
	 * @param  array  $post_data  Checkout post input.
	 *
	 * @return array.
	 * @throws \Exception If the checkout data process has error.
	 */
	public function get_checkout_data( $post_data ) {
		$item_info = new Checkout\Item_Info( $post_data );
		$api_call  = new Checkout\Client( $item_info );
		$response  = $api_call->send_request();
		$letterbox = Utils::is_eligible_auto_letterbox( \WC()->cart );

		return array(
			'response'  => $response,
			'post_data' => $post_data,
			'letterbox' => $letterbox
		);
	}

	/**
	 * Get default value for NL -> NL if nothing is picked.
	 *
	 * @param Array $response Response from checkout API.
	 * @param Array $post_data Submitted post input.
	 *
	 * @return Array.
	 */
	public function get_default_value( $response, $post_data ) {
		$default_val = array(
			'val'   => '',
			'day'   => '',
			'date'  => '',
			'from'  => '',
			'to'    => '',
			'type'  => '',
			'price' => '',
		);

		if ( empty( $response['DeliveryOptions'] ) ) {
			return $default_val;
		}

		$non_standard_fees = Base::non_standard_fees_data();

		foreach ( $response['DeliveryOptions'] as $delivery_option ) {
			if ( empty( $delivery_option['DeliveryDate'] ) || empty( $delivery_option['Timeframe'] ) ) {
				continue;
			}

			$options = array_map(
				function( $timeframe ) use ( $non_standard_fees ) {
					$type  = array_shift( $timeframe['Options'] );
					$price = isset( $non_standard_fees[ $type ] ) ? $non_standard_fees[ $type ]['fee_price'] : 0;

					return array(
						'from'  => Utils::get_hour_min( $timeframe['From'] ),
						'to'    => Utils::get_hour_min( $timeframe['To'] ),
						'type'  => $type,
						'price' => $price,
					);
				},
				$delivery_option['Timeframe']
			);

			$options = array_filter(
				$options,
				function( $option ) use ( $non_standard_fees ) {
					return ! isset( $non_standard_fees[ $option['type'] ] );
				}
			);

			if ( empty( $options ) ) {
				continue;
			}

			$timestamp = strtotime( $delivery_option['DeliveryDate'] );
			$default_val['day']   = gmdate( 'l', $timestamp );
			$default_val['date']  = gmdate( 'Y-m-d', $timestamp );
			$default_val['from']  = $options[0]['from'];
			$default_val['to']    = $options[0]['to'];
			$default_val['type']  = $options[0]['type'];
			$default_val['price'] = $options[0]['price'];
			$default_val['val']   = sanitize_title( $default_val['date'] . '_' . $default_val['from'] . '-' . $default_val['to'] . '_' . $default_val['price'] );

			return $default_val;
		}

		return $default_val;
	}

	/**
	 * Add delivery day & Pickup points fields.
	 *
	 * @param  array  $post_data  Checkout post input.
	 *
	 * @return void.
	 * @throws \Exception.
	 */
	public function display_fields( $post_data ) {
		// Only display the fields if these two options are enabled in the settings.
		if ( ! $this->settings->is_delivery_days_enabled() && ! $this->settings->is_pickup_points_enabled() ) {
			return;
		}

		$checkout_data = $this->get_checkout_data( $post_data );

		if ( empty( $checkout_data['response'] ) ) {
			return;
		}

		$template_args = array(
			'tabs'        => $this->get_available_tabs( $checkout_data['response'] ),
			'response'    => $checkout_data['response'],
			'post_data'   => $checkout_data['post_data'],
			'default_val' => $this->get_default_value( $checkout_data['response'], $checkout_data['post_data'] ),
			'letterbox'   => $checkout_data['letterbox'],
			'fields'      => array(
				array(
					'name'  => $this->tab_field,
					'value' => $this->get_tab_field_value( $checkout_data['post_data'] ),
				),
			),
		);

		wc_get_template( 'checkout/postnl-container.php', $template_args, '', POSTNL_WC_PLUGIN_DIR_PATH . '/templates/' );
	}

	/**
	 * Check address and display fields.
	 *
	 * @return void.
	 */
	public function postnl_fields() {
		try {
			$post_data = $this->get_checkout_post_data();

			if ( empty( $post_data ) ) {
				return;
			}

			$sipping_methods = $this->settings->get_supported_shipping_methods();

			foreach ( $post_data as $post_key => $post_value ) {
				if ( 'shipping_method' === $post_key && ! in_array( Utils::get_cart_shipping_method_id( $post_value[0] ), $sipping_methods ) ) {
					return;
				}
			}

			$available_country = Mapping::available_country_for_checkout_feature();
			$store_country     = Utils::get_base_country();

			// To fix cache issues, check billing country if it is the same address for shipping.
			if ( ! empty( $post_data['ship_to_different_address'] ) ) {
				$receiver_country = ! empty( $post_data['shipping_country'] ) ? $post_data['shipping_country'] : '';
			} else {
				$receiver_country = ! empty( $post_data['billing_country'] ) ? $post_data['billing_country'] : '';
			}

			if ( ! isset( $available_country[ $store_country ][ $receiver_country ] ) ) {
				return;
			}

			$post_data = Address_Utils::set_post_data_address( $post_data );

			//Validate address.
			if ( $this->is_address_validation_required() ) {
				if ( empty( $post_data['shipping_postcode'] ) ) {
					return;
				}

				if ( empty( $post_data['shipping_house_number'] ) && $this->settings->is_reorder_nl_address_enabled() ) {
					return;
				} elseif ( empty( $post_data['shipping_house_number'] ) && ! $this->settings->is_reorder_nl_address_enabled() ) {
					throw new \Exception( 'Address does not contain house number!' );
				}

				$this->validated_address( $post_data );
			}

			// Display PostNL Delivery day & Pickup points.
			$this->display_fields( $post_data );

		} catch ( \Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
		}
	}

	/**
	 * Check address by PostNL Checkout Rest API.
	 *
	 * @param Array $post_data Checkout post data.
	 */
	public function validated_address( $post_data ) {
		$item_info = new Postcode_Check\Item_Info( $post_data );
		$api_call  = new Postcode_Check\Client( $item_info );
		$response  = $api_call->send_request();

		if ( empty( $response[0] ) ) {
			// Clear validated address.
			WC()->session->set( POSTNL_SETTINGS_ID . '_validated_address', array() );

			// Add notice without blocking checkout call.
			wc_add_notice( esc_html__( 'This is not a valid address!', 'postnl-for-woocommerce' ), 'notice' );
		} else {
			// Set validated address.
			WC()->session->set(
				POSTNL_SETTINGS_ID . '_validated_address',
				array(
					'city'                      => $response[0]['city'],
					'street'                    => $response[0]['streetName'],
					'house_number'              => $response[0]['houseNumber'],
					'ship_to_different_address' => ! empty( $post_data['ship_to_different_address'] ),
				)
			);
		}
	}

	/**
	 * Fill checkout form fields after address validation.
	 *
	 * @param Array $fragments Cart fragments.
	 *
	 * @return mixed
	 */
	public function fill_validated_address( $fragments ) {
		if ( ! $this->settings->is_validate_nl_address_enabled() ) {
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

		$non_standard_fees        = Base::non_standard_fees_data();
		$is_non_standard_delivery = ! empty( $post_data['postnl_delivery_day_type'] ) && isset( $non_standard_fees[ $post_data['postnl_delivery_day_type'] ] );

		if ( ! empty( $post_data['postnl_delivery_day_price'] ) && 'delivery_day' === $post_data['postnl_option'] && $is_non_standard_delivery ) {
			$cart->add_fee( $non_standard_fees[ $post_data['postnl_delivery_day_type'] ]['fee_name'], wc_format_decimal( $post_data['postnl_delivery_day_price'] ) );
		}
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

		if ( 'NL' !== Address_Utils::get_customer_billing_country() && 'NL' !== Address_Utils::get_customer_shipping_country() ) {
			return false;
		}

		return true;
	}

	/**
	 * Replace shipping method title with Icon.
	 *
	 * @param String             $label String of label html.
	 * @param WC_Shipping_Method $method Shipping method object.
	 *
	 * @return mixed|string
	 */
	public function add_shipping_method_icon( $label, $method ) {
		if ( POSTNL_SETTINGS_ID === $method->method_id ) {
			$method_title = $method->get_label();
			$label        = '<img src="' . esc_url( trailingslashit( POSTNL_WC_PLUGIN_DIR_URL ) . 'assets/images/postnl-logo-40x40.png' ) . '" class="postnl_shipping_method_icon" alt="' . $method_title . '" />' . $label;
		}

		return $label;
	}
}
