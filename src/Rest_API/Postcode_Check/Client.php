<?php
/**
 * Backward-compatibility shim for Rest_API\Postcode_Check\Client.
 *
 * The implementation has moved to Rest_API\Legacy\Postcode_Check\Client.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Postcode_Check\Client continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Postcode_Check
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Postcode_Check\\Client',
	'PostNLWooCommerce\\Rest_API\\Postcode_Check\\Client'
);
