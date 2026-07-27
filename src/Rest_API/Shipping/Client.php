<?php
/**
 * Backward-compatibility shim for Rest_API\Shipping\Client.
 *
 * The implementation has moved to Rest_API\Legacy\Shipping\Client.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Shipping\Client continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Shipping\\Client',
	'PostNLWooCommerce\\Rest_API\\Shipping\\Client'
);
