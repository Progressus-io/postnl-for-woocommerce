/**
 * Shipping Method Helper
 *
 * Utilities for deciding whether the chosen blocks-checkout shipping method is
 * one PostNL is linked to. Mirrors the classic checkout gate in
 * Frontend\Container::postnl_fields().
 */

/**
 * Extract the WooCommerce shipping method id from a Store API rate id.
 *
 * Rate ids are formatted `${method_id}:${instance_id}` (e.g. "flat_rate:3"),
 * and the supported-methods list is keyed on the method id, not the instance.
 *
 * @param {string} rateId - The Store API rate id.
 * @return {string} The method id, or '' when it cannot be determined.
 */
export function getMethodIdFromRateId( rateId ) {
	if ( ! rateId || typeof rateId !== 'string' ) {
		return '';
	}
	return rateId.split( ':' )[ 0 ];
}

/**
 * Check whether the chosen shipping rate is one PostNL is linked to.
 *
 * Returns true (do not hide) when nothing is configured or no rate is chosen
 * yet, so the widget is never suppressed prematurely.
 *
 * @param {string} rateId           - The selected Store API rate id.
 * @param {Array}  supportedMethods - Method ids PostNL is linked to.
 * @return {boolean} True if the chosen method is supported.
 */
export function isSupportedShippingMethod( rateId, supportedMethods = [] ) {
	if (
		! Array.isArray( supportedMethods ) ||
		supportedMethods.length === 0
	) {
		return true;
	}
	const methodId = getMethodIdFromRateId( rateId );
	if ( ! methodId ) {
		return true;
	}
	return supportedMethods.includes( methodId );
}

export default {
	getMethodIdFromRateId,
	isSupportedShippingMethod,
};
