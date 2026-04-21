<?php
/**
 * Class Rest_API\Barcode\Key_Validator file.
 *
 * Sends a minimal Barcode API request with a candidate API key to verify that
 * the key is accepted by PostNL. Used when a merchant enters a "New API Key"
 * in the settings and we want to confirm it works before activating it.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use PostNLWooCommerce\Main;
use PostNLWooCommerce\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Key_Validator
 */
class Key_Validator {

	/**
	 * Validate a production API key by calling the PostNL Barcode endpoint.
	 *
	 * @param string $api_key       The API key to test.
	 * @param string $customer_code Customer code from settings.
	 * @param string $customer_num  Customer number from settings.
	 *
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function validate( $api_key, $customer_code, $customer_num ) {
		$api_key       = trim( (string) $api_key );
		$customer_code = trim( (string) $customer_code );
		$customer_num  = trim( (string) $customer_num );

		if ( '' === $api_key ) {
			return new \WP_Error( 'postnl_empty_key', __( 'API key is empty.', 'postnl-for-woocommerce' ) );
		}

		if ( '' === $customer_code || '' === $customer_num ) {
			return new \WP_Error(
				'postnl_missing_customer_data',
				__( 'Customer Code and Customer Number are required to validate the new API key.', 'postnl-for-woocommerce' )
			);
		}

		$endpoint = POSTNL_WC_PROD_API_URL . '/shipment/v1_1/barcode';
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
					'accept'       => 'application/json',
					'Content-Type' => 'application/json',
					'SourceSystem' => '35',
				),
			)
		);

		$logger = Main::get_logger();
		if ( $logger ) {
			$logger->write( 'PostNL new API key validation request.' );
		}

		if ( is_wp_error( $response ) ) {
			return new \WP_Error( 'postnl_key_http_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'postnl_key_unauthorized', __( 'The API key was rejected by PostNL.', 'postnl-for-woocommerce' ) );
		}

		if ( is_array( $data ) ) {
			if ( ! empty( $data['fault'] ) ) {
				return new \WP_Error( 'postnl_key_fault', $data['fault']['faultstring'] ?? __( 'Unknown API fault.', 'postnl-for-woocommerce' ) );
			}
			if ( ! empty( $data['Errors'] ) ) {
				$first = array_shift( $data['Errors'] );
				return new \WP_Error( 'postnl_key_error', $first['Description'] ?? $first['ErrorMsg'] ?? __( 'Unknown API error.', 'postnl-for-woocommerce' ) );
			}
			if ( ! empty( $data['Error'] ) ) {
				return new \WP_Error( 'postnl_key_error', $data['Error']['ErrorMessage'] ?? __( 'Unknown API error.', 'postnl-for-woocommerce' ) );
			}
		}

		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error( 'postnl_key_http_status', sprintf( __( 'Unexpected HTTP status %d from PostNL.', 'postnl-for-woocommerce' ), $code ) );
		}

		if ( is_array( $data ) && isset( $data['Barcode'] ) ) {
			return true;
		}

		return new \WP_Error( 'postnl_key_unexpected', __( 'Unexpected response from PostNL Barcode API.', 'postnl-for-woocommerce' ) );
	}
}
