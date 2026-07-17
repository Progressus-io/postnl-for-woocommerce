<?php
/**
 * Backward-compatibility shim for Rest_API\Shipment_and_Return\Item_Info.
 *
 * The implementation has moved to Rest_API\Legacy\Shipment_and_Return\Item_Info.
 * This file registers a class alias so all existing callers that reference
 * PostNLWooCommerce\Rest_API\Shipment_and_Return\Item_Info continue to work unchanged.
 *
 * @package PostNLWooCommerce\Rest_API\Shipment_and_Return
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class_alias(
	'PostNLWooCommerce\\Rest_API\\Legacy\\Shipment_and_Return\\Item_Info',
	'PostNLWooCommerce\\Rest_API\\Shipment_and_Return\\Item_Info'
);
