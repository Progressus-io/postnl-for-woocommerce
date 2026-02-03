/**
 * Tests for PostNL Session Manager
 *
 * These tests verify the centralized session management utility works correctly.
 */

import {
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
} from '../../client/utils/session-manager';

describe( 'PostNL Session Manager', () => {
	beforeEach( () => {
		// Clear sessionStorage before each test
		sessionStorage.clear();
		jest.clearAllMocks();
	} );

	describe( 'getSessionData', () => {
		it( 'should return default state when no data exists', () => {
			const data = getSessionData();

			expect( data ).toEqual( {
				selectedOption: '',
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
			} );
		} );

		it( 'should return stored data when it exists', () => {
			const testData = {
				selectedOption: 'delivery_day',
				deliveryDay: {
					value: '2024-01-10_08:00-12:00_2.5',
					date: '2024-01-10',
					from: '08:00',
					to: '12:00',
					price: 2.5,
					priceFormatted: '2,50',
					type: 'Morning',
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
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( testData )
			);

			const data = getSessionData();

			expect( data.selectedOption ).toBe( 'delivery_day' );
			expect( data.deliveryDay.value ).toBe( '2024-01-10_08:00-12:00_2.5' );
			expect( data.deliveryDay.price ).toBe( 2.5 );
		} );

		it( 'should merge stored data with defaults for missing fields', () => {
			const partialData = {
				selectedOption: 'delivery_day',
			};
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( partialData )
			);

			const data = getSessionData();

			expect( data.selectedOption ).toBe( 'delivery_day' );
			expect( data.deliveryDay ).toBeDefined();
			expect( data.deliveryDay.value ).toBe( '' );
		} );

		it( 'should handle invalid JSON gracefully', () => {
			sessionStorage.setItem( 'postnl_checkout_data', 'invalid-json' );

			const data = getSessionData();

			expect( data.selectedOption ).toBe( '' );
		} );
	} );

	describe( 'setSessionData', () => {
		it( 'should save data to sessionStorage', () => {
			const testData = {
				selectedOption: 'dropoff_points',
			};

			setSessionData( testData );

			const stored = JSON.parse(
				sessionStorage.getItem( 'postnl_checkout_data' )
			);
			expect( stored.selectedOption ).toBe( 'dropoff_points' );
		} );

		it( 'should merge with existing data', () => {
			setSessionData( { selectedOption: 'delivery_day' } );
			setSessionData( {
				deliveryDay: { value: 'test-value', date: '2024-01-10' },
			} );

			const data = getSessionData();

			expect( data.selectedOption ).toBe( 'delivery_day' );
			expect( data.deliveryDay.value ).toBe( 'test-value' );
		} );
	} );

	describe( 'setDeliveryDay', () => {
		it( 'should set delivery day data and update selectedOption', () => {
			setDeliveryDay( {
				value: '2024-01-10_08:00-12:00_2.5',
				date: '2024-01-10',
				from: '08:00',
				to: '12:00',
				price: 2.5,
				priceFormatted: '2,50',
				type: 'Morning',
			} );

			const data = getSessionData();

			expect( data.selectedOption ).toBe( 'delivery_day' );
			expect( data.deliveryDay.value ).toBe( '2024-01-10_08:00-12:00_2.5' );
			expect( data.deliveryDay.date ).toBe( '2024-01-10' );
			expect( data.deliveryDay.from ).toBe( '08:00' );
			expect( data.deliveryDay.to ).toBe( '12:00' );
			expect( data.deliveryDay.price ).toBe( 2.5 );
			expect( data.deliveryDay.type ).toBe( 'Morning' );
		} );

		it( 'should merge with existing delivery day data', () => {
			setDeliveryDay( { date: '2024-01-10' } );
			setDeliveryDay( { price: 3.0 } );

			const data = getSessionData();

			expect( data.deliveryDay.date ).toBe( '2024-01-10' );
			expect( data.deliveryDay.price ).toBe( 3.0 );
		} );
	} );

	describe( 'setDropoffPoint', () => {
		it( 'should set dropoff point data and update selectedOption', () => {
			setDropoffPoint( {
				value: 'PARTNER1-LOC1',
				company: 'Test Shop',
				address1: 'Shop Street 1',
				city: 'Amsterdam',
				postcode: '1000AA',
				country: 'NL',
				partnerID: 'PARTNER1',
			} );

			const data = getSessionData();

			expect( data.selectedOption ).toBe( 'dropoff_points' );
			expect( data.dropoffPoint.value ).toBe( 'PARTNER1-LOC1' );
			expect( data.dropoffPoint.company ).toBe( 'Test Shop' );
			expect( data.dropoffPoint.city ).toBe( 'Amsterdam' );
		} );
	} );

	describe( 'getSelectedOption', () => {
		it( 'should return empty string when nothing selected', () => {
			expect( getSelectedOption() ).toBe( '' );
		} );

		it( 'should return delivery_day when delivery day is set', () => {
			setDeliveryDay( { value: 'test' } );
			expect( getSelectedOption() ).toBe( 'delivery_day' );
		} );

		it( 'should return dropoff_points when dropoff point is set', () => {
			setDropoffPoint( { value: 'test' } );
			expect( getSelectedOption() ).toBe( 'dropoff_points' );
		} );
	} );

	describe( 'getDeliveryDay', () => {
		it( 'should return delivery day data', () => {
			setDeliveryDay( {
				value: 'test-value',
				date: '2024-01-10',
				price: 2.5,
			} );

			const deliveryDay = getDeliveryDay();

			expect( deliveryDay.value ).toBe( 'test-value' );
			expect( deliveryDay.date ).toBe( '2024-01-10' );
			expect( deliveryDay.price ).toBe( 2.5 );
		} );
	} );

	describe( 'getDropoffPoint', () => {
		it( 'should return dropoff point data', () => {
			setDropoffPoint( {
				value: 'PARTNER1-LOC1',
				company: 'Test Shop',
			} );

			const dropoffPoint = getDropoffPoint();

			expect( dropoffPoint.value ).toBe( 'PARTNER1-LOC1' );
			expect( dropoffPoint.company ).toBe( 'Test Shop' );
		} );
	} );

	describe( 'clearSessionData', () => {
		it( 'should remove all PostNL session data', () => {
			setDeliveryDay( { value: 'test' } );
			setDropoffPoint( { value: 'test2' } );

			clearSessionData();

			expect( sessionStorage.getItem( 'postnl_checkout_data' ) ).toBeNull();
			expect( getSelectedOption() ).toBe( '' );
		} );
	} );

	describe( 'clearDeliveryDay', () => {
		it( 'should clear only delivery day data', () => {
			setDeliveryDay( {
				value: 'test-value',
				date: '2024-01-10',
				price: 2.5,
			} );

			clearDeliveryDay();

			const data = getSessionData();
			expect( data.deliveryDay.value ).toBe( '' );
			expect( data.deliveryDay.date ).toBe( '' );
			expect( data.deliveryDay.price ).toBe( 0 );
		} );

		it( 'should preserve dropoff point data when clearing delivery day', () => {
			setDropoffPoint( { value: 'PARTNER1-LOC1', company: 'Test Shop' } );
			setDeliveryDay( { value: 'test-value' } );

			clearDeliveryDay();

			const data = getSessionData();
			expect( data.dropoffPoint.value ).toBe( 'PARTNER1-LOC1' );
			expect( data.dropoffPoint.company ).toBe( 'Test Shop' );
		} );
	} );

	describe( 'clearDropoffPoint', () => {
		it( 'should clear only dropoff point data', () => {
			setDropoffPoint( {
				value: 'PARTNER1-LOC1',
				company: 'Test Shop',
			} );

			clearDropoffPoint();

			const data = getSessionData();
			expect( data.dropoffPoint.value ).toBe( '' );
			expect( data.dropoffPoint.company ).toBe( '' );
		} );

		it( 'should preserve delivery day data when clearing dropoff point', () => {
			setDeliveryDay( { value: 'test-value', date: '2024-01-10' } );
			setDropoffPoint( { value: 'PARTNER1-LOC1' } );

			clearDropoffPoint();

			const data = getSessionData();
			expect( data.deliveryDay.value ).toBe( 'test-value' );
			expect( data.deliveryDay.date ).toBe( '2024-01-10' );
		} );
	} );

	describe( 'hasSelection', () => {
		it( 'should return false when no selection made', () => {
			expect( hasSelection() ).toBe( false );
		} );

		it( 'should return false when selectedOption set but no value', () => {
			setSessionData( { selectedOption: 'delivery_day' } );
			expect( hasSelection() ).toBe( false );
		} );

		it( 'should return true when delivery day has value', () => {
			setDeliveryDay( { value: 'test-value' } );
			expect( hasSelection() ).toBe( true );
		} );

		it( 'should return true when dropoff point has value', () => {
			setDropoffPoint( { value: 'PARTNER1-LOC1' } );
			expect( hasSelection() ).toBe( true );
		} );
	} );

	describe( 'Edge cases', () => {
		it( 'should handle sessionStorage being unavailable', () => {
			// Mock sessionStorage to throw
			const originalSetItem = sessionStorage.setItem;
			sessionStorage.setItem = jest.fn( () => {
				throw new Error( 'QuotaExceededError' );
			} );

			// Should not throw
			expect( () => setDeliveryDay( { value: 'test' } ) ).not.toThrow();

			sessionStorage.setItem = originalSetItem;
		} );

		it( 'should handle concurrent updates', () => {
			// Simulate rapid updates
			setDeliveryDay( { value: 'value1' } );
			setDeliveryDay( { value: 'value2' } );
			setDeliveryDay( { value: 'value3' } );

			const data = getSessionData();
			expect( data.deliveryDay.value ).toBe( 'value3' );
		} );

		it( 'should handle switching between delivery day and dropoff', () => {
			setDeliveryDay( { value: 'delivery-value', date: '2024-01-10' } );
			expect( getSelectedOption() ).toBe( 'delivery_day' );

			setDropoffPoint( { value: 'dropoff-value', company: 'Shop' } );
			expect( getSelectedOption() ).toBe( 'dropoff_points' );

			// Both data should be preserved
			const data = getSessionData();
			expect( data.deliveryDay.value ).toBe( 'delivery-value' );
			expect( data.dropoffPoint.value ).toBe( 'dropoff-value' );
		} );
	} );
} );
