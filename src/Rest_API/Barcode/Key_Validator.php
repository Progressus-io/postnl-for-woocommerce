<?php
/**
 * Class Rest_API\Barcode\Key_Validator file.
 *
 * Sends a minimal Barcode API request with a candidate API key to verify that
 * the key is accepted by PostNL. Used when a merchant enters a new API key in
 * the settings and we want to confirm it works before switching over to it.
 *
 * The call deliberately goes to the V1 Barcode endpoint with a plain apikey
 * header rather than through the PostNL SDK: the SDK is a dev-only dependency
 * (it raises the PHP floor to 8.2 and is stripped from the release build), so a
 * merchant build has no SDK to route to. This validator runs on every settings
 * save, so it must work on a plain PHP 7.4 install with no SDK present.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use PostNLWooCommerce\Main;
use PostNLWooCommerce\Shipping_Method\Settings;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Key_Validator
 */
class Key_Validator {

	/**
	 * Validation reasons. A single value describes the outcome so the save path
	 * and the AJAX endpoint classify a response identically and can never tell a
	 * merchant two different things about the same call.
	 */
	const REASON_VALID       = 'valid';
	const REASON_MISSING     = 'missing';
	const REASON_INVALID     = 'invalid';
	const REASON_UNREACHABLE = 'unreachable';
	const REASON_REJECTED    = 'rejected';

	/**
	 * Validate an API key by calling the PostNL Barcode endpoint.
	 *
	 * The environment is taken from the caller rather than the stored setting so
	 * that a key entered while switching environments is validated against the
	 * host it will actually be used on.
	 *
	 * @param string $api_key       The API key to test.
	 * @param string $customer_code Customer code from settings.
	 * @param string $customer_num  Customer number from settings.
	 * @param bool   $is_sandbox    Whether to test against the sandbox environment.
	 *
	 * @return true|\WP_Error True when the key is valid, otherwise a WP_Error whose
	 *                        code is one of the REASON_* slugs.
	 */
	public static function validate( $api_key, $customer_code, $customer_num, $is_sandbox = false ) {
		$api_key       = trim( (string) $api_key );
		$customer_code = trim( (string) $customer_code );
		$customer_num  = trim( (string) $customer_num );

		if ( '' === $customer_code || '' === $customer_num ) {
			return self::error( self::REASON_MISSING );
		}

		if ( '' === $api_key ) {
			return self::error( self::REASON_INVALID );
		}

		$base     = $is_sandbox ? POSTNL_WC_SANDBOX_API_URL : POSTNL_WC_PROD_API_URL;
		$endpoint = $base . '/shipment/v1_1/barcode';
		$range    = Utils::get_barcode_range( '3S', '' );

		$url = add_query_arg(
			array(
				'Type'           => '3S',
				'Serie'          => '000000000-999999999',
				'CustomerCode'   => $customer_code,
				'CustomerNumber' => $customer_num,
				'Range'          => $range,
			),
			$endpoint
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array(
					'apikey'       => $api_key,
					'NewKey'       => Settings::get_instance()->get_new_key_header_value(),
					'accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'SourceSystem' => '35',
				),
			)
		);

		$logger = Main::get_logger();
		if ( $logger ) {
			$logger->write( 'PostNL new API key validation request (V1 barcode).' );
		}

		$reason = self::classify( $response );

		return self::REASON_VALID === $reason ? true : self::error( $reason );
	}

	/**
	 * Classify a Barcode API response into a reason.
	 *
	 * The HTTP status code decides first; the body is consulted only to tell a
	 * genuine rejection (a fault or Errors payload returned on a 2xx) apart from
	 * a real barcode. Reading the body first is what made a 429 or 503 carrying
	 * an Apigee fault look like a bad key, so status wins here.
	 *
	 * @param array|\WP_Error $response Result of wp_remote_get().
	 *
	 * @return string One of the REASON_* slugs.
	 */
	protected static function classify( $response ) {
		if ( is_wp_error( $response ) ) {
			return self::REASON_UNREACHABLE;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return self::REASON_INVALID;
		}

		if ( $code >= 200 && $code < 300 ) {
			if ( is_array( $data ) && ( ! empty( $data['fault'] ) || ! empty( $data['Errors'] ) || ! empty( $data['Error'] ) ) ) {
				return self::REASON_INVALID;
			}

			if ( is_array( $data ) && isset( $data['Barcode'] ) ) {
				return self::REASON_VALID;
			}

			// A 2xx with no barcode means PostNL never actually minted one, so we
			// have no proof the key works — treat it as "could not check", not bad.
			return self::REASON_UNREACHABLE;
		}

		if ( 429 === $code || $code >= 500 ) {
			return self::REASON_UNREACHABLE;
		}

		// Any other 4xx: PostNL understood the request and refused it, most often
		// because the Customer Code or Number does not go with this key.
		return self::REASON_REJECTED;
	}

	/**
	 * Build the WP_Error for a non-valid reason, carrying the reason as its code
	 * and the merchant-facing sentence as its message.
	 *
	 * @param string $reason One of the REASON_* slugs.
	 *
	 * @return \WP_Error
	 */
	protected static function error( $reason ) {
		return new \WP_Error( $reason, self::reason_message( $reason ) );
	}

	/**
	 * Merchant-facing sentence for a validation reason. Kept as the single source
	 * so the save-time notice and the on-blur endpoint word the same outcome the
	 * same way.
	 *
	 * @param string $reason One of the REASON_* slugs.
	 *
	 * @return string
	 */
	public static function reason_message( $reason ) {
		switch ( $reason ) {
			case self::REASON_MISSING:
				return __( 'Fill in your Customer Code and Customer Number first, then check the key again.', 'postnl-for-woocommerce' );

			case self::REASON_REJECTED:
				return __( 'PostNL could not process the check. This usually means the Customer Code or Customer Number does not match this key.', 'postnl-for-woocommerce' );

			case self::REASON_UNREACHABLE:
				return __( 'We could not reach PostNL to check the key. Please try again in a few minutes.', 'postnl-for-woocommerce' );

			case self::REASON_INVALID:
			default:
				return __( 'The newly entered API key is invalid. Please check the key and enter it again.', 'postnl-for-woocommerce' );
		}
	}
}
