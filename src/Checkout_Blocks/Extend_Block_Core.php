<?php

namespace PostNLWooCommerce\Checkout_Blocks;

use function PostNLWooCommerce\postnl;
use PostNLWooCommerce\Frontend\Delivery_Day;
use PostNLWooCommerce\Frontend\Dropoff_Points;
use PostNLWooCommerce\Frontend\Container;
use PostNLWooCommerce\Frontend\Checkout_Fields;
use PostNLWooCommerce\Rest_API\Checkout;
use PostNLWooCommerce\Rest_API\Postcode_Check;
use PostNLWooCommerce\Helper\Mapping;
use PostNLWooCommerce\Utils;
use PostNLWooCommerce\Address_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Extend_Block_Core {

	/**
	 * Plugin Identifier, unique to each plugin.
	 *
	 * @var string
	 */
	private $name = 'postnl';

	/**
	 * Settings class instance.
	 *
	 * @var Settings
	 */
	protected $settings;

	/**
	 * Bootstraps the class and hooks required data.
	 */
	public function __construct() {

		$this->settings = postnl()->get_shipping_settings();

		// Initialize hooks
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			array(
				$this,
				'save_postnl_checkout_fields',
			),
			10,
			2
		);

		// Fetch PostNL delivery options when the customer's shipping address changes.
		add_action(
			'woocommerce_store_api_cart_update_customer_from_request',
			array( $this, 'handle_address_update' ),
			10,
			2
		);

		// Pre-populate delivery options cache on REST cart load when cache is missing.
		add_action( 'woocommerce_cart_loaded_from_session', array( $this, 'maybe_populate_delivery_cache_on_load' ) );

		// Register the update callback when WooCommerce Blocks is loaded
		add_action( 'init', array( $this, 'register_store_api_callback' ) );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'postnl_add_custom_fee' ) );
		add_filter( 'woocommerce_package_rates', array( $this, 'add_postnl_fees_to_rates' ), 20, 2 );

		if ( $this->settings->is_reorder_nl_address_enabled() ) {
			$this->register_additional_checkout_fields();
		}

		// Validate adress in cart
		add_action( 'woocommerce_store_api_cart_errors', array( $this, 'postnl_validate_address_in_cart' ), 10, 2 );

		/**
		 * Registers AJAX actions.
		 */
		add_action( 'wp_ajax_postnl_set_checkout_post_data', array( $this, 'handle_set_checkout_post_data' ) );
		add_action( 'wp_ajax_nopriv_postnl_set_checkout_post_data', array( $this, 'handle_set_checkout_post_data' ) );
	}


	/**
	 * Validate address at checkout submission.
	 * Only blocks order placement for invalid NL addresses — does not fire on
	 * cart page loads or address update requests.
	 *
	 * Runs a fresh Postcode_Check API call at submission time so the result
	 * reflects the final address rather than a stale session marker that may
	 * have been set during partial address entry.
	 *
	 * @param \WP_Error $errors Cart errors object.
	 * @param \WC_Cart  $cart   Cart object.
	 */
	public function postnl_validate_address_in_cart( $errors, $cart ) {
		// woocommerce_store_api_cart_errors fires on all cart-related Store API
		// requests (GET cart, PATCH update-customer, POST checkout). We only want
		// to block order submission, so restrict to POST requests to the checkout
		// endpoint.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
		$is_post     = 'POST' === strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' );

		if ( ! $is_post || false === strpos( $request_uri, '/checkout' ) ) {
			return;
		}

		// Only validate NL addresses when the setting is enabled.
		$shipping_country = WC()->customer ? WC()->customer->get_shipping_country() : '';
		if ( ! $this->is_address_validation_required_for_country( $shipping_country ) ) {
			return;
		}

		$shipping_postcode = WC()->customer->get_shipping_postcode();
		$house_number      = '';

		if ( $this->settings->is_reorder_nl_address_enabled() ) {
			$house_number = (string) WC()->customer->get_meta( '_wc_shipping/postnl/house_number' );
		}

		// Postcode_Check requires a house number — without it the API always returns empty.
		// When the separate house number field is disabled, or the user has not filled it in,
		// we cannot run a meaningful validation and should not block checkout.
		if ( empty( $house_number ) ) {
			return;
		}

		// Build post_data for Postcode_Check.
		$post_data = array(
			'shipping_country'          => $shipping_country,
			'shipping_postcode'         => $shipping_postcode,
			'shipping_house_number'     => $house_number,
			'shipping_address_1'        => WC()->customer->get_shipping_address(),
			'shipping_address_2'        => WC()->customer->get_shipping_address_2(),
			'shipping_city'             => WC()->customer->get_shipping_city(),
			'shipping_state'            => WC()->customer->get_shipping_state(),
			'ship_to_different_address' => '1',
		);

		$post_data = Address_Utils::set_post_data_address( $post_data );

		try {
			$item_info = new Postcode_Check\Item_Info( $post_data );
			$api_call  = new Postcode_Check\Client( $item_info );
			$response  = $api_call->send_request();

			// Only block submission when the API definitively returns no match.
			// API exceptions (network issues, timeouts) are treated as pass-through
			// so a transient API failure never prevents a legitimate order.
			if ( empty( $response[0] ) ) {
				$errors->add(
					'invalid_address',
					esc_html__( 'This is not a valid address!', 'postnl-for-woocommerce' )
				);
			}
		} catch ( \Exception $e ) {
			// API call failed — do not block the order.
		}
	}

	/**
	 * Register additional checkout fields .
	 */
	public function register_additional_checkout_fields() {
		add_action(
			'init',
			function () {
				woocommerce_register_additional_checkout_field(
					array(
						'id'         => 'postnl/house_number',
						'label'      => __( 'House number', 'postnl-for-woocommerce' ),
						'location'   => 'address',
						'required'   => false,
						'attributes' => array(
							'autocomplete' => 'house_number',
						),
					),
				);
			}
		);
	}

	/**
	 * Fetches PostNL delivery options when the customer's address is updated via the Store API.
	 *
	 * NOTE: Do NOT instantiate Container here — its woocommerce_package_rates and
	 * woocommerce_cart_shipping_packages hooks would fire during calculate_totals()
	 * which runs immediately after this hook, doubling the fee injection.
	 *
	 * @param \WC_Customer    $customer The updated customer object.
	 * @param \WP_REST_Request $request  The REST request.
	 */
	public function handle_address_update( \WC_Customer $customer, \WP_REST_Request $request ) {
		$shipping_country  = $customer->get_shipping_country();
		$shipping_postcode = $customer->get_shipping_postcode();
		$house_number      = '';

		if ( $this->settings->is_reorder_nl_address_enabled() ) {
			$house_number = (string) $customer->get_meta( '_wc_shipping/postnl/house_number' );
		}

		// Check if the destination country is supported.
		$available_countries = Mapping::available_country_for_checkout_feature();
		$store_country       = Utils::get_base_country();

		if ( ! isset( $available_countries[ $store_country ][ $shipping_country ] ) ) {
			WC()->session->__unset( 'postnl_delivery_options_cache' );
			WC()->session->__unset( 'postnl_delivery_options_cache_key' );
			return;
		}

		if ( empty( $shipping_postcode ) ) {
			WC()->session->__unset( 'postnl_delivery_options_cache' );
			WC()->session->__unset( 'postnl_delivery_options_cache_key' );
			return;
		}

		if ( 'NL' === $shipping_country && $this->settings->is_reorder_nl_address_enabled() && empty( $house_number ) ) {
			WC()->session->__unset( 'postnl_delivery_options_cache' );
			WC()->session->__unset( 'postnl_delivery_options_cache_key' );
			return;
		}

		$this->populate_delivery_options_cache(
			$shipping_country,
			$shipping_postcode,
			$house_number,
			$customer->get_shipping_address(),
			$customer->get_shipping_address_2(),
			$customer->get_shipping_city(),
			$customer->get_shipping_state()
		);
	}

	/**
	 * Pre-populate the delivery options cache on initial REST API cart load.
	 * Fires when the cart is loaded from session (before the response is built),
	 * so the data_callback in Extend_Store_Endpoint can read it immediately.
	 */
	public function maybe_populate_delivery_cache_on_load() {
		// Only pre-populate on REST API requests (block checkout page load).
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return;
		}

		// Skip if cache is already populated.
		if ( WC()->session && null !== WC()->session->get( 'postnl_delivery_options_cache', null ) ) {
			return;
		}

		if ( ! WC()->customer ) {
			return;
		}

		$shipping_country  = WC()->customer->get_shipping_country();
		$shipping_postcode = WC()->customer->get_shipping_postcode();
		$house_number      = '';

		if ( $this->settings->is_reorder_nl_address_enabled() ) {
			$house_number = (string) WC()->customer->get_meta( '_wc_shipping/postnl/house_number' );
		}

		// Validate country support.
		$available_countries = Mapping::available_country_for_checkout_feature();
		$store_country       = Utils::get_base_country();

		if ( ! isset( $available_countries[ $store_country ][ $shipping_country ] ) ) {
			return;
		}

		if ( empty( $shipping_postcode ) ) {
			return;
		}

		if ( 'NL' === $shipping_country && $this->settings->is_reorder_nl_address_enabled() && empty( $house_number ) ) {
			return;
		}

		$this->populate_delivery_options_cache(
			$shipping_country,
			$shipping_postcode,
			$house_number,
			WC()->customer->get_shipping_address(),
			WC()->customer->get_shipping_address_2(),
			WC()->customer->get_shipping_city(),
			WC()->customer->get_shipping_state(),
			false // Never run address validation on page-load pre-population.
		);
	}

	/**
	 * Builds and caches PostNL delivery options for the given address.
	 * Shared by handle_address_update() and maybe_populate_delivery_cache_on_load().
	 *
	 * NOTE: Do NOT instantiate Container here — its woocommerce_package_rates and
	 * woocommerce_cart_shipping_packages hooks would fire during calculate_totals()
	 * which runs immediately after this hook, doubling the fee injection.
	 *
	 * @param string $shipping_country   Country code.
	 * @param string $shipping_postcode  Postcode.
	 * @param string $house_number       House number (empty string when not applicable).
	 * @param string $shipping_address   Address line 1.
	 * @param string $shipping_address_2 Address line 2.
	 * @param string $shipping_city      City.
	 * @param string $shipping_state     State/region.
	 * @param bool   $validate_address   Whether to run NL address validation and update the
	 *                                   invalid-address marker. Pass false when pre-populating
	 *                                   on page load so stale session data never touches the
	 *                                   marker set by a real address-change request.
	 */
	private function populate_delivery_options_cache(
		string $shipping_country,
		string $shipping_postcode,
		string $house_number,
		string $shipping_address,
		string $shipping_address_2,
		string $shipping_city,
		string $shipping_state,
		bool $validate_address = true
	): void {
		$cache_key  = md5( $shipping_country . '|' . $shipping_postcode . '|' . $house_number );
		$stored_key = WC()->session->get( 'postnl_delivery_options_cache_key', '' );
		$cache_hit  = $cache_key === $stored_key && null !== WC()->session->get( 'postnl_delivery_options_cache', null );

		// Page-load pre-population: no validation to run, so skip everything if cache is current.
		if ( ! $validate_address && $cache_hit ) {
			return;
		}

		// Build a post_data array compatible with Item_Info constructors.
		$post_data = array(
			'shipping_country'          => $shipping_country,
			'shipping_postcode'         => $shipping_postcode,
			'shipping_house_number'     => $house_number,
			'shipping_address_1'        => $shipping_address,
			'shipping_address_2'        => $shipping_address_2,
			'shipping_city'             => $shipping_city,
			'shipping_state'            => $shipping_state,
			'ship_to_different_address' => '1',
		);

		$post_data = Address_Utils::set_post_data_address( $post_data );

		$validated_address = null;

		if ( $validate_address && $this->is_address_validation_required_for_country( $shipping_country ) ) {
			// Run Postcode_Check only to get the canonical street/city for auto-filling the
			// checkout form. This does NOT set/clear any invalid-address marker — that is
			// done with a fresh API call at submission time in postnl_validate_address_in_cart().
			try {
				$item_info = new Postcode_Check\Item_Info( $post_data );
				$api_call  = new Postcode_Check\Client( $item_info );
				$response  = $api_call->send_request();

				if ( ! empty( $response[0] ) ) {
					$validated_address = array(
						'city'         => $response[0]['city'],
						'street'       => $response[0]['streetName'],
						'house_number' => $response[0]['houseNumber'],
					);
					WC()->session->set(
						POSTNL_SETTINGS_ID . '_validated_address',
						array(
							'city'                      => $response[0]['city'],
							'street'                    => $response[0]['streetName'],
							'house_number'              => $response[0]['houseNumber'],
							'ship_to_different_address' => true,
						)
					);
				} else {
					WC()->session->set( POSTNL_SETTINGS_ID . '_validated_address', array() );
				}
			} catch ( \Exception $e ) {
				// Postcode_Check call failed — proceed without validated address.
			}
		} elseif ( ! $validate_address ) {
			// Page-load pre-population: read validated address from session if available.
			$session_validated = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address', array() );
			if ( ! empty( $session_validated['street'] ) ) {
				$validated_address = array(
					'city'         => $session_validated['city'] ?? '',
					'street'       => $session_validated['street'],
					'house_number' => $session_validated['house_number'] ?? '',
				);
			}
		}

		// Skip the Checkout API call when the delivery options are already cached for this address.
		// On validate_address=true: patch the latest validated_address into the cached data first.
		if ( $cache_hit ) {
			if ( $validate_address ) {
				$cached                      = WC()->session->get( 'postnl_delivery_options_cache' );
				$cached['validated_address'] = $validated_address;
				WC()->session->set( 'postnl_delivery_options_cache', $cached );
			}
			return;
		}

		// Check letterbox eligibility.
		$letterbox = Utils::is_cart_eligible_auto_letterbox( WC()->cart );

		// Call PostNL Checkout API.
		$response = array();
		try {
			$item_info = new Checkout\Item_Info( $post_data );
			$api_call  = new Checkout\Client( $item_info );
			$response  = $api_call->send_request();
		} catch ( \Exception $e ) {
			// API call failed — clear cache and bail.
		}

		if ( empty( $response ) ) {
			WC()->session->__unset( 'postnl_delivery_options_cache' );
			WC()->session->__unset( 'postnl_delivery_options_cache_key' );
			return;
		}

		// Process delivery and dropoff options using the existing frontend classes.
		// These classes only register display hooks which do not fire during Store API cart requests.
		$delivery_day    = new Delivery_Day();
		$dropoff         = new Dropoff_Points();
		$delivery_result = $delivery_day->get_content_data( $response, $post_data );
		$dropoff_result  = $dropoff->get_content_data( $response, $post_data );

		$delivery_options = isset( $delivery_result['delivery_options'] ) ? $delivery_result['delivery_options'] : array();
		$dropoff_options  = isset( $dropoff_result['dropoff_options'] ) ? $dropoff_result['dropoff_options'] : array();
		$show_container   = ! empty( $delivery_options ) || ! empty( $dropoff_options );

		WC()->session->set(
			'postnl_delivery_options_cache',
			array(
				'show_container'           => $show_container,
				'delivery_options'         => $delivery_options,
				'dropoff_options'          => $dropoff_options,
				'is_delivery_days_enabled' => isset( $delivery_result['is_delivery_days_enabled'] ) ? (bool) $delivery_result['is_delivery_days_enabled'] : true,
				'validated_address'        => $validated_address,
				'is_letterbox'             => $letterbox,
			)
		);
		WC()->session->set( 'postnl_delivery_options_cache_key', $cache_key );
	}

	/**
	 * Check if PostNL address validation is required for the given country.
	 *
	 * @param string $country Shipping country code.
	 * @return bool
	 */
	private function is_address_validation_required_for_country( string $country ): bool {
		return 'NL' === $country && $this->settings->is_validate_nl_address_enabled();
	}

	/**
	 * Register the update callback with WooCommerce Store API
	 */
	public function register_store_api_callback() {
		if ( function_exists( 'woocommerce_store_api_register_update_callback' ) ) {
			woocommerce_store_api_register_update_callback(
				array(
					'namespace' => $this->name,
					'callback'  => array( $this, 'postnl_store_api_callback' ),
				)
			);
		}
	}

	/**
	 * Callback function for the Store API update
	 *
	 * @param array $data Data sent from the client.
	 */
	public function postnl_store_api_callback( $data ) {
		if ( isset( $data['action'] ) && 'update_delivery_fee' === $data['action'] ) {
			$price = isset( $data['price'] ) ? floatval( $data['price'] ) : 0;
			$type  = isset( $data['type'] ) ? sanitize_text_field( $data['type'] ) : '';

			// Store the fee amount and type in session
			WC()->session->set( 'postnl_delivery_fee', $price );
			WC()->session->set( 'postnl_delivery_type', $type );
		} else {
			WC()->session->__unset( 'postnl_delivery_fee' );
			WC()->session->__unset( 'postnl_delivery_type' );
		}

		// Clear the WooCommerce shipping rate cache so that add_postnl_fees_to_rates
		// is re-applied with the updated morning/evening fee on the next cart response.
		foreach ( WC()->cart->get_shipping_packages() as $package_key => $package ) {
			WC()->session->__unset( 'shipping_for_package_' . $package_key );
		}
	}

	/**
	 * Adds the delivery fee to the WooCommerce cart.
	 *
	 * @param \WC_Cart $cart The WooCommerce cart object.
	 */
	public function postnl_add_custom_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		// Morning/Evening fees are now baked into the shipping rate cost via
		// add_postnl_fees_to_rates(), so we only need to clear any previously
		// added PostNL cart fee lines to avoid stale entries.
		$new_fees = array();
		foreach ( $cart->get_fees() as $fee ) {
			if ( strpos( $fee->name, 'PostNL' ) === false ) {
				$new_fees[] = $fee;
			}
		}
		$cart->fees_api()->set_fees( $new_fees );
	}

	/**
	 * Checks whether the currently chosen shipping rate has zero cost.
	 *
	 * @return bool
	 */
	private function is_chosen_shipping_free(): bool {
		return Utils::get_chosen_shipping_rate_cost() <= 0.0;
	}

	/**
	 * Add Postnl fees to the supported shipping rates
	 *
	 * @param array $rates
	 * @param array $package
	 * @return array
	 */
	public function add_postnl_fees_to_rates( $rates, $package ) {

		$session_type = WC()->session->get( 'postnl_delivery_type', '' );
		if ( '' === $session_type ) {
			return $rates;
		}

		$supported = $this->settings->get_supported_shipping_methods();

		// If free shipping is available for this package (threshold met or coupon
		// applied), zero out the cost of all supported methods and skip adding fees.
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

		$pickup_fee            = $this->settings->get_pickup_delivery_fee();
		$base_day_fee          = $this->settings->get_delivery_days_fee();
		$morning_evening_fee   = (float) WC()->session->get( 'postnl_delivery_fee', 0 );
		$is_morning_or_evening = in_array( $session_type, array( 'Morning', '08:00-12:00', 'Evening' ), true );

		foreach ( $rates as $rate_id => $rate ) {

			if ( ! in_array( $rate->get_method_id(), $supported, true ) ) {
				continue;
			}

			// Do not add PostNL fees when the shipping rate is already free.
			if ( 0 >= (float) $rate->cost ) {
				continue;
			}

			$extra = 0;

			if ( 'Pickup' === $session_type && $pickup_fee > 0 ) {
				$extra = $pickup_fee;
			} elseif ( 'Pickup' !== $session_type && $base_day_fee > 0 ) {
				$extra = $base_day_fee;
			}

			// Add morning/evening fee directly to the rate so it doesn't
			// appear as a separate cart fee line.
			if ( $is_morning_or_evening && $morning_evening_fee > 0 ) {
				$extra += $morning_evening_fee;
			}

			if ( 0 == $extra ) {
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
	 * Saves the PostNL delivery day or dropoff points fields to the order's metadata.
	 *
	 * @param \WC_Order        $order Order object.
	 * @param \WP_REST_Request $request REST request object.
	 */
	public function save_postnl_checkout_fields( \WC_Order $order, \WP_REST_Request $request ) {

		$postnl_request_data = $request['extensions'][ $this->name ];

		// Extract billing and shipping house numbers with sanitization
		$shipping_house_number = '';

		if ( ! empty( $postnl_request_data['houseNumber'] ) && $this->settings->is_reorder_nl_address_enabled() ) {
			$shipping_house_number = sanitize_text_field( $postnl_request_data['houseNumber'] );
		}
		// Update billing and shipping house numbers
		$order->update_meta_data( '_shipping_house_number', $shipping_house_number );

		/**
		 * Prepare Pickup Data
		 */
		$drop_data = array(
			'frontend' => array(
				'dropoff_points'                   => isset( $postnl_request_data['dropoffPoints'] ) ? sanitize_text_field( $postnl_request_data['dropoffPoints'] ) : '',
				'dropoff_points_address_company'   => isset( $postnl_request_data['dropoffPointsAddressCompany'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddressCompany'] ) : '',
				'dropoff_points_distance'          => isset( $postnl_request_data['dropoffPointsDistance'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDistance'] ) : '',
				'dropoff_points_address_address_1' => isset( $postnl_request_data['dropoffPointsAddress1'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress1'] ) : '',
				'dropoff_points_address_address_2' => isset( $postnl_request_data['dropoffPointsAddress2'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsAddress2'] ) : '',
				'dropoff_points_address_city'      => isset( $postnl_request_data['dropoffPointsCity'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCity'] ) : '',
				'dropoff_points_address_postcode'  => isset( $postnl_request_data['dropoffPointsPostcode'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPostcode'] ) : '',
				'dropoff_points_address_country'   => isset( $postnl_request_data['dropoffPointsCountry'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsCountry'] ) : '',
				'dropoff_points_partner_id'        => isset( $postnl_request_data['dropoffPointsPartnerID'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsPartnerID'] ) : '',
				'dropoff_points_date'              => isset( $postnl_request_data['dropoffPointsDate'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsDate'] ) : '',
				'dropoff_points_time'              => isset( $postnl_request_data['dropoffPointsTime'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsTime'] ) : '',
				'dropoff_points_type'              => isset( $postnl_request_data['dropoffPointsType'] ) ? sanitize_text_field( $postnl_request_data['dropoffPointsType'] ) : '',
			),
		);

		/**
		 * Prepare Delivery Day Data
		 */
		$delivery_day_data = array(
			'frontend' => array(
				'delivery_day'       => isset( $postnl_request_data['deliveryDay'] ) ? sanitize_text_field( $postnl_request_data['deliveryDay'] ) : '',
				'delivery_day_date'  => isset( $postnl_request_data['deliveryDayDate'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayDate'] ) : '',
				'delivery_day_from'  => isset( $postnl_request_data['deliveryDayFrom'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayFrom'] ) : '',
				'delivery_day_to'    => isset( $postnl_request_data['deliveryDayTo'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayTo'] ) : '',
				'delivery_day_price' => isset( $postnl_request_data['deliveryDayPrice'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayPrice'] ) : '',
				'delivery_day_type'  => isset( $postnl_request_data['deliveryDayType'] ) ? sanitize_text_field( $postnl_request_data['deliveryDayType'] ) : '',
			),
		);

		if ( ! empty( $drop_data['frontend']['dropoff_points'] ) ) {
			$order->update_meta_data( '_postnl_order_metadata', $drop_data );
		} elseif ( ! empty( $delivery_day_data['frontend']['delivery_day'] ) ) {
			// Save Delivery Day Data
			$order->update_meta_data( '_postnl_order_metadata', $delivery_day_data );
		}

		/**
		 * Save the order to persist changes
		 */
		$order->save_meta_data();
	}



	/**
	 * Handle AJAX request to set checkout post data and return updated delivery options.
	 */
	public function handle_set_checkout_post_data() {

		// Verify nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'postnl_delivery_day_nonce' ) ) {
			wp_send_json_error( array( 'message' => 'Invalid nonce' ), 400 );
			wp_die();
		}

		// Check if data is provided
		if ( ! isset( $_POST['data'] ) || ! is_array( $_POST['data'] ) ) {
			wp_send_json_error( array( 'message' => 'No data provided.' ), 400 );
			wp_die();
		}

		// Sanitize data
		$sanitized_data = array_map( 'sanitize_text_field', wp_unslash( $_POST['data'] ) );

		$sanitized_data = Address_Utils::set_post_data_address( $sanitized_data );

		$shipping_country = isset( $sanitized_data['shipping_country'] ) ? $sanitized_data['shipping_country'] : '';

		// Check letterbox eligibility
		$letterbox = Utils::is_cart_eligible_auto_letterbox( WC()->cart );

		// Save the house number and postcode on WC customer if provided
		if ( isset( $sanitized_data['shipping_house_number'] ) && isset( $sanitized_data['shipping_postcode'] ) ) {
			WC()->customer->set_shipping_postcode( $sanitized_data['shipping_postcode'] );
			WC()->customer->update_meta_data( '_wc_shipping/postnl/house_number', $sanitized_data['shipping_house_number'] );
			WC()->customer->set_shipping_country( $sanitized_data['shipping_country'] );
			WC()->customer->set_shipping_address_2( $sanitized_data['shipping_address_2'] ?? '' );
			WC()->customer->save();
		}

		// If not NL, clear session and return
		if ( ! in_array( $shipping_country, array( 'NL', 'BE' ), true ) ) {
			Utils::clear_postnl_checkout_session();
			wp_send_json_success(
				array(
					'message'          => 'No delivery options available outside NL.',
					'show_container'   => false,
					'delivery_options' => array(),
					'dropoff_options'  => array(),
				),
				200
			);
			wp_die();
		}

		// Check if required fields are present
		if (
			empty( $sanitized_data['shipping_postcode'] )
			||
			(
				$this->settings->is_reorder_nl_address_enabled()
				&& empty( $sanitized_data['shipping_house_number'] )
				&& 'NL' == $shipping_country
			) ) {
			Utils::clear_postnl_checkout_session();
			wp_send_json_success(
				array(
					'message'          => esc_html__( 'Postcode or house number is missing.', 'postnl-for-woocommerce' ),
					'show_container'   => false,
					'delivery_options' => array(),
					'dropoff_options'  => array(),
				),
				200
			);
			wp_die();
		}

		// Retrieve previously sanitized data from session
		$previous_sanitized_data = WC()->session->get( 'postnl_checkout_post_data' );
		$address_changed         = $previous_sanitized_data !== $sanitized_data;

		if ( $address_changed ) {
			// Clear previous validated address
			WC()->session->__unset( POSTNL_SETTINGS_ID . '_validated_address' );
		}

		// Retrieve validated address from session
		$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );

		$container = new Container();

		// If validation is enabled and address changed or not validated yet
		if ( $container->is_address_validation_required() && 'NL' === $shipping_country && ( $address_changed || empty( $validated_address ) ) ) {
			try {
				$container->validated_address( $sanitized_data );
				// Attempt to fetch validated address again
				$validated_address = WC()->session->get( POSTNL_SETTINGS_ID . '_validated_address' );
			} catch ( \Exception $e ) {

			}
		}

		// Store data in WooCommerce session
		WC()->session->set( 'postnl_checkout_post_data', $sanitized_data );

		// Save address_1 and city if available
		if (
			! empty( $validated_address )
			&& isset( $validated_address['street'], $validated_address['city'] )
			&& $validated_address['street'] !== ''
			&& $validated_address['city'] !== ''
		) {
			// Use validated data
			if ( $this->settings->is_reorder_nl_address_enabled() ) {
				// Separate house number field is enabled, use street only
				WC()->customer->set_shipping_address_1( $validated_address['street'] );
			} else {
				// House number is part of address_1, combine street and house number.
				$house_number = $validated_address['house_number'] ?? '';
				$address_1    = $validated_address['street'] . ' ' . $house_number;
				WC()->customer->set_shipping_address_1( $address_1 );
			}

			WC()->customer->set_shipping_city( $validated_address['city'] );
		} else {
			WC()->customer->set_shipping_address_1( $sanitized_data['shipping_address_1'] ?? '' );
			WC()->customer->set_shipping_city( $sanitized_data['shipping_city'] ?? '' );
			WC()->customer->set_shipping_state( $sanitized_data['shipping_state'] ?? '' );
		}

		// Save the customer data
		WC()->customer->save();

		// Proceed to fetch delivery and dropoff options
		$order_data = $sanitized_data;

		$delivery_day     = new Delivery_Day();
		$dropoff          = new Dropoff_Points();
		$checkout_data    = $container->get_checkout_data( $order_data );
		$delivery_options = $delivery_day->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );
		$dropoff_options  = $dropoff->get_content_data( $checkout_data['response'], $checkout_data['post_data'] );

		// **Letterbox is Eligible**
		if ( $letterbox ) {
			wp_send_json_success(
				array(
					'message'           => 'Eligible for letterbox.',
					'show_container'    => true,
					'validated_address' => $validated_address,
					'delivery_options'  => $delivery_options['delivery_options'],
					'dropoff_options'   => array(),
					'is_letterbox'      => true,
				),
				200
			);
			wp_die();
		}

		// Attempt to retrieve checkout data, delivery, and dropoff options
		try {
			$delivery_options_array = isset( $delivery_options['delivery_options'] ) ? $delivery_options['delivery_options'] : array();
			$dropoff_options_array  = isset( $dropoff_options['dropoff_options'] ) ? $dropoff_options['dropoff_options'] : array();

			// Determine whether to show the container
			if ( empty( $delivery_options_array ) && empty( $dropoff_options_array ) ) {
				Utils::clear_postnl_checkout_session();
				wp_send_json_success(
					array(
						'message'           => 'No delivery or dropoff options available.',
						'show_container'    => false,
						'validated_address' => $validated_address,
						'delivery_options'  => array(),
						'dropoff_options'   => array(),
					),
					200
				);
				wp_die();
			}

			// If there are delivery or dropoff options, show the container
			wp_send_json_success(
				array(
					'message'                  => 'Data saved successfully.',
					'show_container'           => true,
					'validated_address'        => $validated_address,
					'delivery_options'         => $delivery_options_array,
					'dropoff_options'          => $dropoff_options_array,
					'is_delivery_days_enabled' => $delivery_options['is_delivery_days_enabled'],
				),
				200
			);
			wp_die();

		} catch ( \Exception $e ) {
			// If fetching delivery options fails.
			Utils::clear_postnl_checkout_session();
			wp_send_json_success(
				array(
					'message'           => 'Failed to fetch delivery options.',
					'show_container'    => false,
					'validated_address' => $validated_address,
					'delivery_options'  => array(),
					'dropoff_options'   => array(),
				),
				200
			);
			wp_die();
		}
	}
}
