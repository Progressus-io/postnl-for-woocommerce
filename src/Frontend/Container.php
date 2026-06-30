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
	 *
	 * @param bool $register_hooks Whether to register the WordPress hooks. The
	 *                             bootstrap instance (Main::get_frontend()) passes
	 *                             true; transient instances created only to reuse
	 *                             helper methods (e.g. the blocks AJAX handler) must
	 *                             pass false, otherwise the global woocommerce_package_rates
	 *                             filters get registered twice and inject_letterbox_rates_for_all_methods
	 *                             runs twice in the same request, duplicating the 24h/48h rates.
	 */
	public function __construct( bool $register_hooks = true ) {
		$this->settings = Settings::get_instance();

		if ( $register_hooks ) {
			$this->init_hooks();
		}
	}

	/**
	 * Collection of hooks when initiation.
	 */
	public function init_hooks() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts_styles' ) );

		add_action( 'woocommerce_review_order_after_shipping', array( $this, 'postnl_fields' ), 10 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_cart_fees' ), 10, 1 );

		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'fill_validated_address' ) );
		add_filter( 'woocommerce_cart_shipping_method_full_label', array( $this, 'add_shipping_method_icon' ), 10, 2 );

		if ( ! Utils::is_blocks_checkout() ) {
			add_filter( 'woocommerce_package_rates', array( $this, 'inject_postnl_base_fees' ), 20, 2 );
		}
		add_filter( 'woocommerce_package_rates', array( $this, 'inject_letterbox_rates_for_all_methods' ), 15, 2 );
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
				'currency'                    => array(
					'symbol'           => html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ),
					'symbolPosition'   => get_option( 'woocommerce_currency_pos', 'left' ),
					'decimalSeparator' => wc_get_price_decimal_separator(),
					'thousandSeparator' => wc_get_price_thousand_separator(),
					'precision'        => wc_get_price_decimals(),
				),
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

		foreach ( $response['DeliveryOptions'] as $delivery_option ) {
			if ( empty( $delivery_option['DeliveryDate'] ) || empty( $delivery_option['Timeframe'] ) ) {
				continue;
			}

			$options = array_map(
				function ( $timeframe ) use ( $non_standard_fees ) {
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
	 * Get the carrier base cost by reading the currently-selected PostNL rate
	 * and subtracting the PostNL tab fee that was injected into it.
	 *
	 * @param array $post_data Checkout post data (used to determine active tab).
	 *
	 * @return float Carrier base cost (≥ 0).
	 */
	private function get_carrier_base_cost( array $post_data ): float {
		$option = $post_data['postnl_option'] ?? '';

		$tab_fee = 0.0;
		if ( 'delivery_day' === $option ) {
			$tab_fee = (float) $this->settings->get_delivery_days_fee();
		} elseif ( 'dropoff_points' === $option ) {
			$tab_fee = (float) $this->settings->get_pickup_delivery_fee();
		}

		$chosen    = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$packages  = WC()->shipping()->get_packages();
		$supported = $this->settings->get_supported_shipping_methods();

		foreach ( $packages as $i => $package ) {
			$method_key = $chosen[ $i ] ?? '';

			if ( ! isset( $package['rates'][ $method_key ] ) ) {
				continue;
			}

			$rate = $package['rates'][ $method_key ];

			if ( in_array( $rate->get_method_id(), $supported, true ) ) {
				// The morning/evening extra fee is folded into the rate; subtract it per-package.
				$extra_fee = 0.0;
				if ( 'delivery_day' === $option ) {
					$extra_fee = (float) ( $package['destination']['postnl_delivery_day_price'] ?? WC()->session->get( 'postnl_delivery_day_price', 0 ) );
				}
				return max( 0.0, (float) $rate->cost - $tab_fee - $extra_fee );
			}
		}

		return 0.0;
	}

	/**
	 * Check whether a supported PostNL shipping method is the chosen method.
	 *
	 * Used to detect PostNL threshold-based free shipping: if a PostNL method is
	 * selected but its carrier base cost is 0, the minimum_for_free_shipping
	 * threshold has been reached and all PostNL fees should be suppressed.
	 *
	 * @return bool
	 */
	private function is_postnl_method_chosen(): bool {
		$chosen    = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$packages  = WC()->shipping()->get_packages();
		$supported = $this->settings->get_supported_shipping_methods();

		foreach ( $packages as $i => $package ) {
			$method_key = $chosen[ $i ] ?? '';
			if ( isset( $package['rates'][ $method_key ] ) && in_array( $package['rates'][ $method_key ]->get_method_id(), $supported, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Add delivery day & Pickup points fields.
	 *
	 * @param  array $post_data  Checkout post input.
	 *
	 * @return void.
	 * @throws \Exception.
	 */
	public function display_fields( $post_data ) {

		$checkout_data = $this->get_checkout_data( $post_data );

		if ( empty( $checkout_data['response'] ) ) {
			return;
		}

		$is_free_shipping  = Utils::is_free_shipping_applied();
		$delivery_day_fee  = (float) $this->settings->get_delivery_days_fee();
		$pickup_fee        = (float) $this->settings->get_pickup_delivery_fee();
		$carrier_base_cost = $is_free_shipping ? 0.0 : $this->get_carrier_base_cost( $checkout_data['post_data'] );

		// PostNL threshold-based free shipping: a PostNL method is selected but its
		// rate was registered with cost = 0 (minimum_for_free_shipping reached).
		if ( ! $is_free_shipping && 0.0 === $carrier_base_cost && $this->is_postnl_method_chosen() ) {
			$is_free_shipping = true;
		}

		$tabs        = $this->get_available_tabs( $checkout_data['response'] );
		$default_tab = $this->settings->get_default_checkout_tab();

		$tab_ids = array_column( $tabs, 'id' );
		if ( ! empty( $tabs ) && ! in_array( $default_tab, $tab_ids, true ) ) {
			$default_tab = $tabs[0]['id'];
		}

		// TODO: generalise to mirror the JS path in client/checkout/postnl-container/block.js
		// (findIndex + splice/unshift). Today this only fires for 'dropoff_points'
		// because there are exactly two tabs and 'delivery_day' is naturally first;
		// adding a third tab without revisiting this branch will silently break
		// reorder in the shortcode checkout while the blocks checkout keeps working.
		if ( 'dropoff_points' === $default_tab && count( $tabs ) > 1 ) {
			$preferred = array_filter( $tabs, static fn( $t ) => $t['id'] === $default_tab );
			$rest      = array_filter( $tabs, static fn( $t ) => $t['id'] !== $default_tab );
			$tabs      = array_merge( array_values( $preferred ), array_values( $rest ) );
		}

		$template_args = array(
			'tabs'              => $tabs,
			'response'          => $checkout_data['response'],
			'post_data'         => $checkout_data['post_data'],
			'default_val'       => $this->get_default_value( $checkout_data['response'], $checkout_data['post_data'] ),
			'letterbox'         => $checkout_data['letterbox'],
			'fields'            => array(
				array(
					'name'  => $this->tab_field,
					'value' => $this->get_tab_field_value( $checkout_data['post_data'] ),
				),
			),
			'pickup_fee'        => $pickup_fee,
			'delivery_day_fee'  => $delivery_day_fee,
			'carrier_base_cost' => $carrier_base_cost,
			'is_free_shipping'  => $is_free_shipping,
			'default_tab'       => $default_tab,
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
			// Mark the address as invalid in the session:
			WC()->session->set( POSTNL_SETTINGS_ID . '_invalid_address_marker', true );
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
	 * Add cart fees.
	 *
	 * Morning/evening delivery fees are folded directly into the shipping rate
	 * cost via inject_postnl_base_fees(), so no separate fee line item is needed.
	 *
	 * @param \WC_Cart $cart Cart object.
	 */
	public function add_cart_fees( $cart ) {
		return;
	}

	/**
	 * Collapse every PostNL-linked shipping method into a single canonical letterbox
	 * option set when the cart is eligible for automatic letterbox delivery (ALA).
	 *
	 * This filter is the single source of truth for the letterbox rate set. When ALA
	 * succeeds it:
	 *   - keeps non-linked carriers (e.g. DHL) untouched, including a non-linked
	 *     Free Shipping method, which stays visible and never affects the letterbox cost;
	 *   - drops every PostNL-linked rate (the PostNL method's own rate, any linked
	 *     Flat Rate instances, and a linked Free Shipping method) and emits ONE canonical
	 *     24h/48h option set in their place, so the choice appears exactly once regardless
	 *     of how many linked methods or instances the zone has. A linked Free Shipping
	 *     method still applies its waiver (the canonical cost drops to 0) but is not shown
	 *     as a separate row.
	 *
	 * PostNL::calculate_shipping() deliberately does NOT emit its own letterbox variants
	 * — it emits a plain PostNL rate that this filter folds into the canonical set, so
	 * there is a single emitter and no duplication.
	 *
	 * @since 5.9.6
	 *
	 * @param array $rates   Shipping rates keyed by rate ID.
	 * @param array $package Shipping package.
	 * @return array
	 */
	public function inject_letterbox_rates_for_all_methods( $rates, $package ) {
		// Only apply for NL base country.
		if ( 'NL' !== Utils::get_base_country() ) {
			return $rates;
		}

		// Check cart eligibility for letterbox.
		if ( ! Utils::is_cart_eligible_auto_letterbox( \WC()->cart ) ) {
			return $rates;
		}

		$letterbox_product_type = $this->settings->get_default_automatic_letterboxparcel_product();

		// Only inject when customer can decide or a specific letterbox type is configured.
		if ( ! in_array( $letterbox_product_type, array( 'customer_decide', 'letterbox', 'letterbox_48' ), true ) ) {
			return $rates;
		}

		// Idempotency guard: if the canonical letterbox set is already present (this filter
		// ran earlier in the same request), do not rebuild it — that would nest a second
		// ':letterbox' suffix onto an existing variant and duplicate the options.
		foreach ( $rates as $rate ) {
			$rate_meta = $rate->get_meta_data();
			if ( isset( $rate_meta['letterbox_type'] ) ) {
				return $rates;
			}
		}

		$supported = $this->settings->get_supported_shipping_methods();

		// Partition rates: non-linked carriers (including a non-linked Free Shipping method)
		// are kept as-is; every PostNL-linked rate is collapsed into one canonical letterbox
		// option set below.
		$kept_rates        = array();
		$linked_rates      = array();
		$has_free_shipping = false;

		foreach ( $rates as $rate_id => $rate ) {
			$method_id = $rate->get_method_id();

			if ( 'free_shipping' === $method_id ) {
				// A Free Shipping method only waives the letterbox cost when the
				// merchant has explicitly linked it to PostNL. When linked, it is
				// collapsed like any other PostNL-linked rate: its waiver effect is
				// applied (the canonical letterbox cost drops to 0) but the method
				// itself is not shown as a separate row alongside the letterbox
				// options. A standalone (non-linked) Free Shipping option is
				// independent: it never zeros out the letterbox prices and is kept
				// visible in checkout.
				if ( in_array( $method_id, $supported, true ) ) {
					$has_free_shipping = true;
				} else {
					$kept_rates[ $rate_id ] = $rate;
				}
				continue;
			}

			if ( ! in_array( $method_id, $supported, true ) ) {
				$kept_rates[ $rate_id ] = $rate;
				continue;
			}

			$linked_rates[ $rate_id ] = $rate;
		}

		// No PostNL-linked rates to collapse — leave the package untouched.
		if ( empty( $linked_rates ) ) {
			return $rates;
		}

		// Free-shipping determination drives both variants to 0. It is derived ONLY
		// from PostNL-linked signals: a linked Free Shipping method being present
		// ($has_free_shipping) or a linked carrier rate at cost 0 (handled in the loop
		// below, where the PostNL free-shipping threshold has been met). A non-linked
		// Free Shipping method — even one the customer has selected — must not waive
		// the letterbox cost, so Utils::is_free_shipping_applied() is intentionally not
		// consulted here (it ignores linkage and would re-introduce that regression).
		$is_free = $has_free_shipping;

		// Cheapest linked carrier cost is the base-cost fallback when no letterbox_fee is set.
		$cheapest_cost = null;
		foreach ( $linked_rates as $rate ) {
			$cost = (float) $rate->get_cost();
			if ( 0.0 === $cost ) {
				// A linked rate at cost 0 means the PostNL free-shipping threshold was met.
				$is_free = true;
			}
			if ( null === $cheapest_cost || $cost < $cheapest_cost ) {
				$cheapest_cost = $cost;
			}
		}
		$cheapest_cost = ( null === $cheapest_cost ) ? 0.0 : $cheapest_cost;

		$letterbox_fee     = $this->settings->get_letterbox_fee();
		$base_cost         = ( null !== $letterbox_fee ) ? (float) $letterbox_fee : $cheapest_cost;
		$base_cost         = $is_free ? 0.0 : $base_cost;
		$effective_fee_24h = $is_free ? 0.0 : (float) $this->settings->get_letterbox_24_fee();

		$rep_rate_id = null;
		$rep_rate    = null;
		foreach ( $linked_rates as $rate_id => $rate ) {
			if ( POSTNL_SETTINGS_ID === $rate->get_method_id() ) {
				$rep_rate_id = $rate_id;
				$rep_rate    = $rate;
				break;
			}
		}
		if ( null === $rep_rate ) {
			$rep_rate_id = array_key_first( $linked_rates );
			$rep_rate    = $linked_rates[ $rep_rate_id ];
		}

		$rep_method_id = $rep_rate->get_method_id();
		$rep_instance  = $rep_rate->get_instance_id();

		// Recalculate shipping taxes for the canonical cost.
		$calc_taxes = function ( $cost ) use ( $rep_rate ) {
			if ( wc_tax_enabled() && 'taxable' === $rep_rate->get_tax_status() ) {
				return \WC_Tax::calc_shipping_tax( $cost, \WC_Tax::get_shipping_tax_rates() );
			}
			return array();
		};

		// Build the canonical letterbox option set ONCE.
		$canonical = array();

		if ( 'customer_decide' === $letterbox_product_type || 'letterbox' === $letterbox_product_type ) {
			$cost_24h = $base_cost + $effective_fee_24h;

			$rate_24h = new \WC_Shipping_Rate(
				$rep_rate_id . ':letterbox',
				Utils::get_letterbox_label_24h(),
				$cost_24h,
				$calc_taxes( $cost_24h ),
				$rep_method_id,
				$rep_instance
			);
			$rate_24h->add_meta_data( 'letterbox_type', 'letterbox' );
			$canonical[ $rep_rate_id . ':letterbox' ] = $rate_24h;
		}

		if ( 'customer_decide' === $letterbox_product_type || 'letterbox_48' === $letterbox_product_type ) {
			$rate_48h = new \WC_Shipping_Rate(
				$rep_rate_id . ':letterbox_48',
				Utils::get_letterbox_label_48h(),
				$base_cost,
				$calc_taxes( $base_cost ),
				$rep_method_id,
				$rep_instance
			);
			$rate_48h->add_meta_data( 'letterbox_type', 'letterbox_48' );
			$canonical[ $rep_rate_id . ':letterbox_48' ] = $rate_48h;
		}

		// Canonical letterbox option(s) first, then the kept rates (non-linked + free_shipping).
		return $canonical + $kept_rates;
	}

	/**
	 * Add the shipping option fees to the shipping methods
	 *
	 * @param array $rates.
	 * @return array
	 */
	public function inject_postnl_base_fees( $rates, $package ) {
		if ( Utils::is_free_shipping_applied() ) {
			return $rates;
		}

		// Letterbox-eligible carts are owned by the variant emitters
		if ( Utils::is_cart_eligible_auto_letterbox( \WC()->cart ) ) {
			return $rates;
		}

		$option = $package['destination']['postnl_option'] ?? WC()->session->get( 'postnl_option', '' );
		if ( '' === $option ) {
			return $rates;
		}

		$pickup_fee   = (float) $this->settings->get_pickup_delivery_fee();
		$base_day_fee = (float) $this->settings->get_delivery_days_fee();
		$supported    = $this->settings->get_supported_shipping_methods();

		foreach ( $rates as $rate_id => $rate ) {
			if ( ! in_array( $rate->get_method_id(), $supported, true ) ) {
				continue;
			}

			// PostNL threshold-based free shipping: the rate was registered with
			// cost = 0 by PostNL::calculate_shipping(). Do not inject any fees.
			if ( 0.0 === (float) $rate->cost ) {
				continue;
			}

			$extra = 0;
			if ( 'dropoff_points' === $option && $pickup_fee > 0 ) {
				$extra = $pickup_fee;
			} elseif ( 'delivery_day' === $option ) {
				// Fold both the tab base fee and any morning/evening extra fee into the rate.
				// Prefer the package destination value (set by add_postnl_option_to_package during
				// AJAX calls) and fall back to the session value for order placement, when
				// $_REQUEST['post_data'] is no longer available.
				$extra  = $base_day_fee;
				$extra += (float) ( $package['destination']['postnl_delivery_day_price'] ?? WC()->session->get( 'postnl_delivery_day_price', 0 ) );
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

		// Blocks-checkout fallback: Extend_Block_Core::postnl_store_api_callback
		// writes postnl_delivery_type/postnl_delivery_fee to session before this
		// filter fires. Mirror that state into $option so the destination injection
		// below runs on the blocks path too — without it, the WC_Shipping package
		// hash never changes on tab switch and add_postnl_fees_to_rates() is never
		// re-invoked against fresh rates. WC ≤ 10.4 accidentally masked this via
		// divergent package shapes in CartController::get_shipping_packages(); that
		// divergence was removed, exposing the gap.
		if ( '' === $option && WC()->session ) {
			$session_type = WC()->session->get( 'postnl_delivery_type', '' );
			if ( 'Pickup' === $session_type ) {
				$option = 'dropoff_points';
			} elseif ( '' !== $session_type ) {
				$option = 'delivery_day';
			}
		}

		if ( '' === $option ) {
			return $packages;
		}

		WC()->session->set( 'postnl_option', $option );

		// Store the morning/evening extra fee in the destination so the shipping
		// rate cache key changes when the selection changes, forcing recalculation.
		// Also persist to session so inject_postnl_base_fees can read it during
		// order placement, when $_REQUEST['post_data'] is no longer available.
		$raw_price = $post_data['postnl_delivery_day_price']
			?? WC()->session->get( 'postnl_delivery_fee', 0 );

		$delivery_day_price = ( 'delivery_day' === $option )
			? (string) (float) $raw_price
			: '0';

		WC()->session->set( 'postnl_delivery_day_price', $delivery_day_price );

		foreach ( $packages as $key => $package ) {
			$packages[ $key ]['destination']['postnl_option']             = $option;
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
