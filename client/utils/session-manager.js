/**
 * PostNL Session Manager
 *
 * Centralized management of all PostNL session data.
 * This eliminates scattered sessionStorage calls and provides a single source of truth.
 *
 * @since 5.9.2
 */

const STORAGE_KEY = 'postnl_checkout_data';

/**
 * Default empty state structure.
 */
const DEFAULT_STATE = {
	selectedOption: '', // 'delivery_day' | 'dropoff_points' | ''
	deliveryDay: {
		value: '',
		date: '',
		from: '',
		to: '',
		price: 0,
		priceFormatted: '',
		type: '',
	},
	dropoffPoint: {
		value: '',
		company: '',
		address1: '',
		address2: '',
		city: '',
		postcode: '',
		country: '',
		partnerID: '',
		date: '',
		time: '',
		type: '',
		distance: '',
	},
};

/**
 * Get all PostNL session data.
 *
 * @return {Object} The session data or default state.
 */
export function getSessionData() {
	try {
		const stored = sessionStorage.getItem( STORAGE_KEY );
		if ( stored ) {
			return { ...DEFAULT_STATE, ...JSON.parse( stored ) };
		}
	} catch ( e ) {
		// sessionStorage unavailable or invalid JSON
	}
	return { ...DEFAULT_STATE };
}

/**
 * Save all PostNL session data.
 *
 * @param {Object} data The data to save.
 */
export function setSessionData( data ) {
	try {
		const current = getSessionData();
		const merged = { ...current, ...data };
		sessionStorage.setItem( STORAGE_KEY, JSON.stringify( merged ) );
	} catch ( e ) {
		// sessionStorage unavailable
	}
}

/**
 * Update delivery day data.
 *
 * @param {Object} deliveryDay The delivery day data.
 */
export function setDeliveryDay( deliveryDay ) {
	const current = getSessionData();
	setSessionData( {
		...current,
		selectedOption: 'delivery_day',
		deliveryDay: { ...current.deliveryDay, ...deliveryDay },
	} );
}

/**
 * Update dropoff point data.
 *
 * @param {Object} dropoffPoint The dropoff point data.
 */
export function setDropoffPoint( dropoffPoint ) {
	const current = getSessionData();
	setSessionData( {
		...current,
		selectedOption: 'dropoff_points',
		dropoffPoint: { ...current.dropoffPoint, ...dropoffPoint },
	} );
}

/**
 * Get the currently selected option.
 *
 * @return {string} 'delivery_day', 'dropoff_points', or ''
 */
export function getSelectedOption() {
	return getSessionData().selectedOption;
}

/**
 * Get delivery day data.
 *
 * @return {Object} The delivery day data.
 */
export function getDeliveryDay() {
	return getSessionData().deliveryDay;
}

/**
 * Get dropoff point data.
 *
 * @return {Object} The dropoff point data.
 */
export function getDropoffPoint() {
	return getSessionData().dropoffPoint;
}

/**
 * Clear all PostNL session data.
 * This is the ONLY place where session clearing should happen.
 */
export function clearSessionData() {
	try {
		sessionStorage.removeItem( STORAGE_KEY );
	} catch ( e ) {
		// sessionStorage unavailable
	}
}

/**
 * Clear only delivery day data (when switching to dropoff).
 */
export function clearDeliveryDay() {
	const current = getSessionData();
	setSessionData( {
		...current,
		deliveryDay: { ...DEFAULT_STATE.deliveryDay },
	} );
}

/**
 * Clear only dropoff point data (when switching to delivery).
 */
export function clearDropoffPoint() {
	const current = getSessionData();
	setSessionData( {
		...current,
		dropoffPoint: { ...DEFAULT_STATE.dropoffPoint },
	} );
}

/**
 * Check if there's any active selection.
 *
 * @return {boolean} True if user has made a selection.
 */
export function hasSelection() {
	const data = getSessionData();
	return (
		data.selectedOption !== '' &&
		( data.deliveryDay.value !== '' || data.dropoffPoint.value !== '' )
	);
}

export default {
	getSessionData,
	setSessionData,
	setDeliveryDay,
	setDropoffPoint,
	getSelectedOption,
	getDeliveryDay,
	getDropoffPoint,
	clearSessionData,
	clearDeliveryDay,
	clearDropoffPoint,
	hasSelection,
};
