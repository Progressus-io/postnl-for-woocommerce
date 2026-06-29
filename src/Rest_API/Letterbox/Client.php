<?php
/**
 * Backward-compatibility shim for Rest_API\Letterbox\Client.
 *
 * The implementation has moved to Rest_API\Legacy\Letterbox\Client.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Letterbox\Client continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Letterbox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Letterbox\\Client',
	'PostNLWooCommerce\\Rest_API\\Letterbox\\Client'
);
