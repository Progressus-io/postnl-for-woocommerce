<?php
/**
 * Backward-compatibility shim for Rest_API\Shipping\Item_Info.
 *
 * The implementation has moved to Rest_API\Legacy\Shipping\Item_Info.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Shipping\Item_Info continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Shipping
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Shipping\\Item_Info',
	'PostNLWooCommerce\\Rest_API\\Shipping\\Item_Info'
);
