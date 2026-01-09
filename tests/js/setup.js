/**
 * Jest Test Setup
 *
 * This file runs before each test file.
 */

// Mock sessionStorage
const sessionStorageMock = ( () => {
	let store = {};
	return {
		getItem: jest.fn( ( key ) => store[ key ] || null ),
		setItem: jest.fn( ( key, value ) => {
			store[ key ] = value.toString();
		} ),
		removeItem: jest.fn( ( key ) => {
			delete store[ key ];
		} ),
		clear: jest.fn( () => {
			store = {};
		} ),
		get length() {
			return Object.keys( store ).length;
		},
		key: jest.fn( ( index ) => Object.keys( store )[ index ] || null ),
		// Helper to get raw store for testing
		__getStore: () => store,
		__setStore: ( newStore ) => {
			store = { ...newStore };
		},
	};
} )();

Object.defineProperty( window, 'sessionStorage', {
	value: sessionStorageMock,
} );

// Mock WooCommerce blocks checkout API
window.wc = {
	blocksCheckout: {
		extensionCartUpdate: jest.fn( () => Promise.resolve() ),
	},
	wcBlocksData: {
		CART_STORE_KEY: 'wc/store/cart',
		CHECKOUT_STORE_KEY: 'wc/store/checkout',
	},
};

// Mock WooCommerce settings
window.wcSettings = {
	getSetting: jest.fn( () => ( {} ) ),
};

// Note: Module mocks are in __mocks__/ directory:
// - @wordpress/i18n is mocked in __mocks__/@wordpress/i18n.js
// - @wordpress/data is mocked in __mocks__/@wordpress/data.js
// - @woocommerce/settings is mocked in __mocks__/@woocommerce/settings.js

// Reset mocks before each test
beforeEach( () => {
	jest.clearAllMocks();
	sessionStorageMock.clear();
	sessionStorageMock.__setStore( {} );
} );

// Global test utilities
global.testUtils = {
	/**
	 * Helper to simulate session storage with predefined data
	 */
	setSessionData: ( data ) => {
		sessionStorageMock.__setStore( data );
	},

	/**
	 * Helper to get current session storage state
	 */
	getSessionData: () => {
		return sessionStorageMock.__getStore();
	},

	/**
	 * Helper to create mock delivery options
	 */
	createMockDeliveryOptions: ( count = 2 ) => {
		return Array.from( { length: count }, ( _, i ) => ( {
			date: `2024-01-${ 10 + i }`,
			display_date: `January ${ 10 + i }`,
			day: [ 'Monday', 'Tuesday', 'Wednesday' ][ i % 3 ],
			options: [
				{
					from: '08:00',
					to: '12:00',
					type: 'Morning',
					price: 2.5,
					price_formatted: '2,50',
				},
				{
					from: '17:00',
					to: '22:00',
					type: 'Evening',
					price: 3.0,
					price_formatted: '3,00',
				},
			],
		} ) );
	},

	/**
	 * Helper to create mock dropoff options
	 */
	createMockDropoffOptions: ( count = 3 ) => {
		return Array.from( { length: count }, ( _, i ) => ( {
			partner_id: `PARTNER${ i }`,
			loc_code: `LOC${ i }`,
			name: `PostNL Point ${ i + 1 }`,
			address: {
				company: `Shop ${ i + 1 }`,
				address_1: `Shop Street ${ i + 1 }`,
				address_2: '',
				city: 'Amsterdam',
				postcode: `1000A${ i }`,
				country: 'NL',
			},
			distance: `${ ( i + 1 ) * 100 }m`,
			date: `2024-01-${ 10 + i }`,
			time: '09:00-18:00',
			type: 'PNL',
		} ) );
	},
};
