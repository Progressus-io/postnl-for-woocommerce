<?php
/**
 * Backward-compatibility shim for Rest_API\Smart_Returns\Client.
 *
 * The implementation has moved to Rest_API\Legacy\Smart_Returns\Client.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Smart_Returns\Client continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Smart_Returns
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Smart_Returns\\Client',
	'PostNLWooCommerce\\Rest_API\\Smart_Returns\\Client'
);
