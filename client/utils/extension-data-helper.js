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
 * @param {Object} data - Object with key-value pairs to set
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

export default {
	batchSetExtensionData,
	clearDeliveryDayExtensionData,
	clearDropoffPointExtensionData,
};
