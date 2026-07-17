<?php
/**
 * Backward-compatibility shim for Rest_API\Barcode\Client.
 *
 * The implementation has moved to Rest_API\Legacy\Barcode\Client.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Barcode\Client continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Barcode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Barcode\\Client',
	'PostNLWooCommerce\\Rest_API\\Barcode\\Client'
);
