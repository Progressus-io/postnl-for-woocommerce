<?php
/**
 * Class Rest_API\Barcode\Key_Validator file.
 *
 * Confirms that a candidate "New API Key" is accepted by PostNL's V4 API before
 * the plugin starts routing traffic through it. The call deliberately goes to a
 * V4 endpoint through the SDK: the whole point of the new key field is to prove
 * the merchant holds a V4-capable key, so validating against V1 (as an ordinary
 * apikey header request would) could pass a key that has no V4 access and leave
 * every V4-flagged flow failing after the switch.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

namespace PostNLWooCommerce\Rest_API\Barcode;

use Postnl\Sdk\Exception\AuthExceptionInterface;
use Postnl\Sdk\Exception\Retry\RetryExhaustedException;
use Postnl\Sdk\Exception\RetryableExceptionInterface;
use Postnl\Sdk\Service\Barcode\V4\Request\BarcodeRequest;
use PostNLWooCommerce\Main;
use PostNLWooCommerce\Rest_API\SDK\Client_Factory;
use PostNLWooCommerce\Rest_API\SDK\Exception_Converter;
use PostNLWooCommerce\Shipping_Method\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Key_Validator
 */
class Key_Validator {

	/**
	 * Validate an API key by generating a barcode through the PostNL V4 SDK.
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
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function validate( $api_key, $customer_code, $customer_num, $is_sandbox = false ) {
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

		$logger = Main::get_logger();
		if ( $logger ) {
			$logger->write( 'PostNL new API key validation request (V4 barcode).' );
		}

		try {
			$client  = ( new Client_Factory( Settings::get_instance() ) )->build( $api_key, (bool) $is_sandbox );
			$request = new BarcodeRequest(
				customerNumber: $customer_num,
				customerCode: $customer_code,
				serieStart: '000000000',
				serieEnd: '999999999',
				numberOfBarcodes: 1
			);

			$client->barcodes()->generateBarcode( $request );
		} catch ( AuthExceptionInterface $e ) {
			// The key was refused at authentication — it is not a valid V4 key.
			return new \WP_Error( 'postnl_key_unauthorized', __( 'The API key was rejected by PostNL.', 'postnl-for-woocommerce' ) );
		} catch ( \Throwable $e ) {
			// Transport failures / retryable 5xx are not proof the key is bad, so the
			// caller keeps any previously-validated state rather than disabling a
			// working key on a network blip. Everything else is a definitive rejection
			// (the key authenticated but the request was refused), surfaced to the merchant.
			if ( $e instanceof RetryExhaustedException
				|| ( $e instanceof RetryableExceptionInterface && $e->isRetryable() ) ) {
				return new \WP_Error( 'postnl_key_http_error', Exception_Converter::convert( $e )->getMessage() );
			}

			return new \WP_Error( 'postnl_key_error', Exception_Converter::convert( $e )->getMessage() );
		}

		return true;
	}
}
