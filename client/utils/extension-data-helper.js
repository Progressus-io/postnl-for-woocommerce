/**
 * Extension Data Helper
 *
 * Provides batched setExtensionData calls to reduce re-renders.
 */

/**
 * Batch set multiple extension data keys at once.
 * This reduces the number of React state updates.
 *
 * @param {Function} setExtensionData - The setExtensionData function from checkoutExtensionData
 * @param {Object}   data             - Object with key-value pairs to set
 */
export function batchSetExtensionData( setExtensionData, data ) {
	Object.entries( data ).forEach( ( [ key, value ] ) => {
		setExtensionData( 'postnl', key, value );
	} );
}

/**
 * Clear all delivery day extension data.
 *
 * @param {Function} setExtensionData - The setExtensionData function
 */
export function clearDeliveryDayExtensionData( setExtensionData ) {
	batchSetExtensionData( setExtensionData, {
		deliveryDay: '',
		deliveryDayDate: '',
		deliveryDayFrom: '',
		deliveryDayTo: '',
		deliveryDayPrice: '',
		deliveryDayType: '',
	} );
}

/**
 * Clear all dropoff point extension data.
 *
 * @param {Function} setExtensionData - The setExtensionData function
 */
export function clearDropoffPointExtensionData( setExtensionData ) {
	batchSetExtensionData( setExtensionData, {
		dropoffPoints: '',
		dropoffPointsAddressCompany: '',
		dropoffPointsAddress1: '',
		dropoffPointsAddress2: '',
		dropoffPointsCity: '',
		dropoffPointsPostcode: '',
		dropoffPointsCountry: '',
		dropoffPointsPartnerID: '',
		dropoffPointsDate: '',
		dropoffPointsTime: '',
		dropoffPointsType: '',
		dropoffPointsDistance: null,
	} );
}

/**
 * Clear all PostNL extension data (both delivery day and dropoff points).
 *
 * @param {Function} setExtensionData - The setExtensionData function
 */
export function clearAllExtensionData( setExtensionData ) {
	clearDeliveryDayExtensionData( setExtensionData );
	clearDropoffPointExtensionData( setExtensionData );
}

/**
 * Trigger cart update to clear backend delivery fee.
 * Uses WooCommerce Blocks' extensionCartUpdate API.
 */
export function clearBackendDeliveryFee() {
	const { extensionCartUpdate } = window.wc?.blocksCheckout || {};
	if ( typeof extensionCartUpdate === 'function' ) {
		extensionCartUpdate( {
			namespace: 'postnl',
			data: { action: 'clear_delivery_fee' },
		} );
	}
}

/**
 * Check if a country is supported by PostNL.
 *
 * @param {string} country          - The country code to check
 * @param {Array}  supportedCountries - Array of supported country codes
 * @return {boolean} True if the country is supported
 */
export function isCountrySupported( country, supportedCountries = [] ) {
	return supportedCountries.includes( country );
}

export default {
	batchSetExtensionData,
	clearDeliveryDayExtensionData,
	clearDropoffPointExtensionData,
	clearAllExtensionData,
	clearBackendDeliveryFee,
	isCountrySupported,
};
