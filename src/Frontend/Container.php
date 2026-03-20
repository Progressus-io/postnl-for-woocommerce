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
use PostNLWooCommerce\Frontend\Checkout_Fields;

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

		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'postnl_fields' ), 10 );

		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'fill_validated_address' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'add_shipping_method_icon' ), 10, 2 );

		if ( ! Utils::is_blocks_checkout() ) {
			add_filter( 'woocommerce_package_rates', array( $this, 'inject_postnl_base_fees' ), 20, 2 );
		}

		add_filter( 'woocommerce_cart_shipping_packages', array( $this, 'add_postnl_option_to_package' ) );
	}

	/**
	 * Enqueue scripts and style.
	 */
	public function enqueue_scripts_styles() {
		if ( ! is_checkout() && ! is_cart() ) {
			return;
		}

		// Enqueue styles.
		wp_enqueue_style(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/css/fe-checkout.css',
			array( 'postnl-fill-in-button' ),
			POSTNL_WC_VERSION
		);

		// Only enqueue JS for classic checkout.
		if ( Utils::is_blocks_checkout() ) {
			return;
		}

		if ( is_cart() ) {
			return;
		}

		wp_enqueue_script(
			'postnl-fe-checkout',
			POSTNL_WC_PLUGIN_DIR_URL . '/assets/js/fe-checkout.js',
			array( 'jquery' ),
			POSTNL_WC_VERSION,
			true
		);

		$settings = Settings::get_instance();

		wp_localize_script(
			'postnl-fe-checkout',
			'postnlParams',
			array(
				'i18n'                        => array(
					'deliveryDays' => esc_html__( 'Delivery Days', 'postnl-for-woocommerce' ),
					'pickup'       => esc_html__( 'Pickup', 'postnl-for-woocommerce' ),
				),
				'delivery_day_fee_formatted'  => Utils::get_formatted_fee_total_price( $settings->get_delivery_days_fee() ),
				'pickup_fee_formatted'        => Utils::get_formatted_fee_total_price( $settings->get_pickup_delivery_fee() ),
			)
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
	 * @param  array $post_data  Checkout post input.
	 *
	 * @return array.
	 * @throws \Exception If the checkout data process has error.
	 */
	public function get_checkout_data( $post_data ) {
		$item_info = new Checkout\Item_Info( $post_data );
		$api_call  = new Checkout\Client( $item_info );
		$response  = $api_call->send_request();
		$letterbox = Utils::is_cart_eligible_auto_letterbox( \WC()->cart );

		return array(
			'response'  => $response,
			'post_data' => $post_data,
			'letterbox' => $letterbox,
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
		$chosen_rate_cost = Utils::get_chosen_shipping_rate_cost();
		$is_free_shipping = $chosen_rate_cost <= 0;

		foreach ( $response['DeliveryOptions'] as $delivery_option ) {
			if ( empty( $delivery_option['DeliveryDate'] ) || empty( $delivery_option['Timeframe'] ) ) {
				continue;
			}

			$options = array_map(
				function ( $timeframe ) use ( $non_standard_fees, $is_free_shipping ) {
					$type  = array_shift( $timeframe['Options'] );
					$price = isset( $non_standard_fees[ $type ] ) && ! $is_free_shipping ? $non_standard_fees[ $type ]['fee_price'] : 0;

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
				function ( $option ) use ( $non_standard_fees ) {
					return ! isset( $non_standard_fees[ $option['type'] ] );
				}
			);

			if ( empty( $options ) ) {
				continue;
			}

			$timestamp            = strtotime( $delivery_option['DeliveryDate'] );
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
	 * Get a cache key from the relevant shipping address fields.
	 *
	 * @param array $post_data Checkout post data.
	 * @return string MD5 hash of country, postcode, and house number.
	 */
	protected function get_api_cache_key( $post_data ) {
		$country      = isset( $post_data['shipping_country'] ) ? $post_data['shipping_country'] : '';
		$postcode     = isset( $post_data['shipping_postcode'] ) ? $post_data['shipping_postcode'] : '';
		$house_number = isset( $post_data['shipping_house_number'] ) ? $post_data['shipping_house_number'] : '';

		return md5( $country . '|' . $postcode . '|' . $house_number );
	}

	/**
	 * Add delivery day & Pickup points fields.
	 *
	 * @param  array      $post_data     Checkout post input.
	 * @param  array|null $checkout_data Optional pre-fetched API response (response + letterbox).
	 *
	 * @return void.
	 * @throws \Exception.
	 */
	public function display_fields( $post_data, $checkout_data = null ) {
		if ( null === $checkout_data ) {
			$checkout_data = $this->get_checkout_data( $post_data );
		}

		if ( empty( $checkout_data['response'] ) ) {
			return;
		}

		$chosen_rate_cost = Utils::get_chosen_shipping_rate_cost();
		$is_free_shipping = $chosen_rate_cost <= 0;

		$delivery_day_fee = $is_free_shipping ? 0.0 : (float) $this->settings->get_delivery_days_fee();
		$pickup_fee       = $is_free_shipping ? 0.0 : (float) $this->settings->get_pickup_delivery_fee();
		$active_option    = WC()->session ? WC()->session->get( 'postnl_option', 'delivery_day' ) : 'delivery_day';

		// Get morning/evening fee — only relevant when delivery_day tab is active.
		$non_standard_fees   = Base::non_standard_fees_data();
		$is_non_standard     = ! $is_free_shipping
			&& 'delivery_day' === $active_option
			&& ! empty( $post_data['postnl_delivery_day_type'] )
			&& isset( $non_standard_fees[ $post_data['postnl_delivery_day_type'] ] );
		$morning_evening_fee = $is_non_standard ? (float) ( $post_data['postnl_delivery_day_price'] ?? 0 ) : 0.0;

		// Subtract the full injected fee (including morning/evening) from the rate cost
		$injected_fee = ( 'dropoff_points' === $active_option )
			? $pickup_fee
			: $delivery_day_fee + $morning_evening_fee;

		$carrier_base = $is_free_shipping ? 0.0 : max( 0.0, $chosen_rate_cost - $injected_fee );

		$template_args = array(
			'tabs'                         => $this->get_available_tabs( $checkout_data['response'] ),
			'response'                     => $checkout_data['response'],
			'post_data'                    => $checkout_data['post_data'],
			'default_val'                  => $this->get_default_value( $checkout_data['response'], $checkout_data['post_data'] ),
			'letterbox'                    => $checkout_data['letterbox'],
			'fields'                       => array(
				array(
					'name'  => $this->tab_field,
					'value' => $this->get_tab_field_value( $checkout_data['post_data'] ),
				),
			),
			'pickup_fee'                   => $pickup_fee,
			'delivery_day_fee'             => $delivery_day_fee,
			'carrier_base'                 => $carrier_base,
			'delivery_day_total_formatted' => Utils::get_formatted_fee_total_price( $carrier_base + $delivery_day_fee + $morning_evening_fee ),
			'pickup_total_formatted'       => Utils::get_formatted_fee_total_price( $carrier_base + $pickup_fee ),
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
					// Clear PostNL session data when shipping method is not supported.
					Utils::clear_postnl_checkout_session();
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
				// Clear PostNL session data when country is not supported.
				Utils::clear_postnl_checkout_session();
				return;
			}

			$post_data = Address_Utils::set_post_data_address( $post_data );

			if ( empty( $post_data['shipping_postcode'] ) ) {
				// Clear PostNL session data when postcode is missing.
				Utils::clear_postnl_checkout_session();
				return;
			}

			// Validate address.
			if ( $this->is_address_validation_required() ) {
				$is_reorder_nl_address_enabled = $this->settings->is_reorder_nl_address_enabled();

				if ( empty( $post_data['shipping_house_number'] ) && $is_reorder_nl_address_enabled ) {
					// Clear PostNL session data when house number is missing.
					Utils::clear_postnl_checkout_session();
					return;
				} elseif ( empty( $post_data['shipping_house_number'] ) && ! $is_reorder_nl_address_enabled ) {
					throw new \Exception( 'Address does not contain house number!' );
				}

				$this->validated_address( $post_data );
			}

			// Cache raw API response by address to avoid calling PostNL on every update_checkout.
			$cache_key  = $this->get_api_cache_key( $post_data );
			$stored_key = WC()->session->get( 'postnl_checkout_address_key', '' );
			$api_cache  = WC()->session->get( 'postnl_checkout_api_cache', null );

			if ( $cache_key === $stored_key && is_array( $api_cache ) ) {
				$checkout_data = array(
					'response'  => $api_cache['response'],
					'post_data' => $post_data,
					'letterbox' => $api_cache['letterbox'],
				);
			} else {
				$checkout_data = $this->get_checkout_data( $post_data );
				WC()->session->set( 'postnl_checkout_address_key', $cache_key );
				WC()->session->set(
					'postnl_checkout_api_cache',
					array(
						'response'  => $checkout_data['response'],
						'letterbox' => $checkout_data['letterbox'],
					)
				);
			}

			// Display PostNL Delivery day & Pickup points.
			$this->display_fields( $post_data, $checkout_data );

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
			// Mark the address as invalid in the session for checkout validation.
			WC()->session->set( POSTNL_SETTINGS_ID . '_invalid_address_marker', true );
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
			WC()->session->__unset( POSTNL_SETTINGS_ID . '_invalid_address_marker' );
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
	 * ِAdd the shipping option fees to the shipping methods
	 *
	 * @param array $rates.
	 * @return array
	 */
	public function inject_postnl_base_fees( $rates, $package ) {

		$option = $package['destination']['postnl_option'] ?? WC()->session->get( 'postnl_option', '' );

		if ( '' === $option ) {
			return $rates;
		}

		$supported = $this->settings->get_supported_shipping_methods();

		// If free shipping is available for this package, zero out the cost of all
		// supported methods and skip adding PostNL fees.
		$has_free_shipping = false;
		foreach ( $rates as $rate ) {
			if ( 'free_shipping' === $rate->get_method_id() ) {
				$has_free_shipping = true;
				break;
			}
		}

		if ( $has_free_shipping ) {
			foreach ( $rates as $rate_id => $rate ) {
				if ( ! in_array( $rate->get_method_id(), $supported, true ) ) {
					continue;
				}
				$rate->cost  = 0;
				$rate->taxes = array();
			}
			return $rates;
		}

		$pickup_fee   = (float) $this->settings->get_pickup_delivery_fee();
		$base_day_fee = (float) $this->settings->get_delivery_days_fee();

		// Get morning/evening surcharge from package destination, falling back to session
		// for requests that don't carry post_data (page load, order review, place order).
		$morning_evening_fee = 0.0;
		if ( 'delivery_day' === $option ) {
			$non_standard_fees  = Base::non_standard_fees_data();
			$delivery_day_type  = $package['destination']['postnl_delivery_day_type']
				?? WC()->session->get( 'postnl_delivery_day_type', '' );
			$delivery_day_price = $package['destination']['postnl_delivery_day_price']
				?? WC()->session->get( 'postnl_delivery_day_price', 0 );

			if ( ! empty( $delivery_day_type ) && isset( $non_standard_fees[ $delivery_day_type ] ) && $delivery_day_price > 0 ) {
				$morning_evening_fee = (float) $delivery_day_price;
			}
		}

		foreach ( $rates as $rate_id => $rate ) {
			if ( ! in_array( $rate->get_method_id(), $supported, true ) ) {
				continue;
			}

			// Do not add PostNL tab fees on top of free shipping.
			if ( (float) $rate->cost <= 0 ) {
				continue;
			}

			$extra = 0;
			if ( 'dropoff_points' === $option && $pickup_fee > 0 ) {
				$extra = $pickup_fee;
			} elseif ( 'delivery_day' === $option ) {
				$extra = $base_day_fee + $morning_evening_fee;
			}

			if ( $extra <= 0 ) {
				continue;
			}

			$rate->cost += $extra;

			if ( wc_tax_enabled() && 'taxable' === $rate->get_tax_status() ) {
				$tax_rates   = \WC_Tax::get_shipping_tax_rates();
				$rate->taxes = \WC_Tax::calc_shipping_tax( $rate->cost, $tax_rates );
			}
		}

		return $rates;
	}

	/**
	 * Include the selected PostNL option in the shipping package
	 *
	 * @param array $packages Shipping packages.
	 * @return array
	 */
	public function add_postnl_option_to_package( $packages ) {
		$post_data = $this->get_checkout_post_data();
		$option    = $post_data['postnl_option'] ?? '';

		if ( '' === $option ) {
			return $packages;
		}

		WC()->session->set( 'postnl_option', $option );

		$delivery_day_type  = $post_data['postnl_delivery_day_type'] ?? '';
		$delivery_day_price = $post_data['postnl_delivery_day_price'] ?? '';

		// Persist type and price to session so inject_postnl_base_fees() can read them
		// on requests that don't carry post_data (page load, order review, place order).
		// Always overwrite (including empty) so stale values don't persist after tab/option changes.
		WC()->session->set( 'postnl_delivery_day_type', $delivery_day_type );
		WC()->session->set( 'postnl_delivery_day_price', $delivery_day_price );

		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['destination']['postnl_option']             = $option;
			$packages[ $key ]['destination']['postnl_delivery_day_type']  = $delivery_day_type;
			$packages[ $key ]['destination']['postnl_delivery_day_price'] = $delivery_day_price;
		}

		return $packages;
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
	 * @param String            $label String of label html.
	 * @param \WC_Shipping_Rate $method Shipping method object.
	 *
	 * @return string
	 */
	public function add_shipping_method_icon( $label, $method ) {
		if ( POSTNL_SETTINGS_ID === $method->get_method_id() ) {
			$method_title = $method->get_label();
			$label        = '<img src="' . esc_url( trailingslashit( POSTNL_WC_PLUGIN_DIR_URL ) . 'assets/images/postnl-new-brand-logo.png' ) . '" class="postnl_shipping_method_icon" alt="' . $method_title . '" />' . $label;
		}

		return $label;
	}
}
