<?php
/**
 * Class Rest_API/Router file.
 *
 * @package PostNLWooCommerce\Rest_API
 */

namespace PostNLWooCommerce\Rest_API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Router
 *
 * @package PostNLWooCommerce\Rest_API
 */
class Router {

	/**
	 * Flows that may be enabled for the PostNL SDK.
	 *
	 * postcode_check is intentionally absent — it stays on the legacy REST path.
	 *
	 * @var string[]
	 */
	const SUPPORTED_FLOWS = array(
		'barcode',
		'timeframe',
		'pickup_location',
		'checkout',
		'label',
		'return_label',
		'letterbox',
		'shipment_and_return',
		'smart_returns',
	);

	/**
	 * Return whether the SDK is enabled for a given flow.
	 *
	 * Flows not in SUPPORTED_FLOWS always return false without invoking any filter.
	 * Every flow defaults to false; site owners opt in via the filter.
	 *
	 * @param string $flow Flow identifier.
	 * @return bool
	 */
	public static function sdk_enabled_for( string $flow ): bool {
		if ( ! in_array( $flow, self::SUPPORTED_FLOWS, true ) ) {
			return false;
		}

		return (bool) apply_filters( "postnl_sdk_enable_{$flow}", false );
	}
}
