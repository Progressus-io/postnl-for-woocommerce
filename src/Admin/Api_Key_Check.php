<?php
/**
 * Class Admin\Api_Key_Check file.
 *
 * Read-only AJAX endpoint that checks a new API key while the merchant is still
 * typing it, so the status row under the field can go green (or red) before they
 * save. It writes no option and stores no validated hash — that only happens on
 * save — and it never logs the key.
 *
 * A successful check mints a real barcode from the merchant's range, so the
 * endpoint is rate limited per user; the browser also memoises checked values
 * and aborts in-flight requests so a quick blur/focus does not fan out calls.
 *
 * @package PostNLWooCommerce\Admin
 */

namespace PostNLWooCommerce\Admin;

use PostNLWooCommerce\Rest_API\Barcode\Key_Validator;
use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Api_Key_Check
 */
class Api_Key_Check {

	const NONCE_ACTION = 'postnl_check_new_api_key';
	const AJAX_ACTION  = 'postnl_check_new_api_key';

	/**
	 * Most checks a single user may run inside a rolling RATE_LIMIT_WINDOW-second
	 * window. Each check that reaches PostNL mints a barcode, so this bounds the
	 * volume; the counter's TTL is refreshed on every hit, so sustained checking
	 * stays capped rather than resetting.
	 */
	const RATE_LIMIT_MAX    = 10;
	const RATE_LIMIT_WINDOW = 60;

	/**
	 * Register the AJAX handler.
	 */
	public function __construct() {
		add_action( 'wp_ajax_' . self::AJAX_ACTION, array( $this, 'handle' ) );
	}

	/**
	 * Validate the posted key and return the status-row payload for it.
	 */
	public function handle() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'postnl-for-woocommerce' ) ), 403 );
		}

		check_ajax_referer( self::NONCE_ACTION, 'nonce' );

		$settings   = Settings::get_instance();
		$is_sandbox = isset( $_POST['environment'] ) && 'sandbox' === sanitize_key( wp_unslash( $_POST['environment'] ) );

		// The key and customer details are taken from the request, not storage, so
		// a merchant filling in all three fields at once is checked against what
		// they just typed rather than the empty saved values.
		$new_key       = isset( $_POST['api_key'] ) ? trim( (string) wp_unslash( $_POST['api_key'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- API key is not free text; trimmed and sent as a header only.
		$customer_code = isset( $_POST['customer_code'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_code'] ) ) : '';
		$customer_num  = isset( $_POST['customer_num'] ) ? sanitize_text_field( wp_unslash( $_POST['customer_num'] ) ) : '';

		$original = $is_sandbox
			? trim( (string) $settings->get_api_key_sandbox() )
			: trim( (string) $settings->get_api_key() );

		// Empty and same-as-old are decidable without touching PostNL. The browser
		// already skips those, but guard here too so a crafted request cannot spend
		// a barcode on them.
		if ( '' === $new_key || $new_key === $original ) {
			wp_send_json_success( $settings->build_new_key_status( $new_key, $original, false, $is_sandbox, false ) );
		}

		// This exact key already passed validation and is saved, so report the
		// green "Valid" state without spending another barcode. Without this, a
		// merchant who just focuses and blurs the pre-filled field would mint a
		// barcode and see the row drop to amber (or red during an outage).
		if ( $settings->is_api_key_new_validated_value( $new_key, $is_sandbox ) ) {
			wp_send_json_success( $settings->build_new_key_status( $new_key, $original, true, $is_sandbox, true ) );
		}

		if ( ! $this->within_rate_limit() ) {
			wp_send_json_error(
				array( 'message' => __( 'Too many checks in a short time. Please wait a moment and try again.', 'postnl-for-woocommerce' ) ),
				429
			);
		}

		$result = Key_Validator::validate( $new_key, $customer_code, $customer_num, $is_sandbox );
		$valid  = ( true === $result );
		$reason = $valid ? Key_Validator::REASON_VALID : $result->get_error_code();

		wp_send_json_success( $settings->build_new_key_status( $new_key, $original, $valid, $is_sandbox, false, $reason ) );
	}

	/**
	 * Whether the current user is under the per-window check limit. Increments the
	 * counter as a side effect when it is.
	 *
	 * @return bool
	 */
	protected function within_rate_limit() {
		$transient = 'postnl_key_check_' . get_current_user_id();
		$count     = (int) get_transient( $transient );

		if ( $count >= self::RATE_LIMIT_MAX ) {
			return false;
		}

		set_transient( $transient, $count + 1, self::RATE_LIMIT_WINDOW );

		return true;
	}
}
