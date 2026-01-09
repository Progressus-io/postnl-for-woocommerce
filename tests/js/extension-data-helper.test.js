/**
 * Extension Data Helper Tests
 *
 * Tests for the batched setExtensionData utility functions.
 */

import {
	batchSetExtensionData,
	clearDeliveryDayExtensionData,
	clearDropoffPointExtensionData,
} from '../../client/utils/extension-data-helper';

describe( 'Extension Data Helper', () => {
	let mockSetExtensionData;

	beforeEach( () => {
		mockSetExtensionData = jest.fn();
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	describe( 'batchSetExtensionData', () => {
		it( 'should call setExtensionData for each key-value pair', () => {
			const data = {
				deliveryDay: '2024-01-15_08:00-12:00_2.50',
				deliveryDayDate: '2024-01-15',
				deliveryDayFrom: '08:00',
			};

			batchSetExtensionData( mockSetExtensionData, data );

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 3 );
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDay',
				'2024-01-15_08:00-12:00_2.50'
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayDate',
				'2024-01-15'
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayFrom',
				'08:00'
			);
		} );

		it( 'should handle empty data object', () => {
			batchSetExtensionData( mockSetExtensionData, {} );

			expect( mockSetExtensionData ).not.toHaveBeenCalled();
		} );

		it( 'should handle single key-value pair', () => {
			batchSetExtensionData( mockSetExtensionData, {
				testKey: 'testValue',
			} );

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 1 );
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'testKey',
				'testValue'
			);
		} );

		it( 'should handle null and undefined values', () => {
			const data = {
				nullValue: null,
				undefinedValue: undefined,
				emptyString: '',
			};

			batchSetExtensionData( mockSetExtensionData, data );

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 3 );
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'nullValue',
				null
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'undefinedValue',
				undefined
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'emptyString',
				''
			);
		} );

		it( 'should handle numeric values', () => {
			const data = {
				price: 2.5,
				count: 0,
				negative: -1,
			};

			batchSetExtensionData( mockSetExtensionData, data );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'price',
				2.5
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'count',
				0
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'negative',
				-1
			);
		} );
	} );

	describe( 'clearDeliveryDayExtensionData', () => {
		it( 'should clear all 6 delivery day fields', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 6 );
		} );

		it( 'should set deliveryDay to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDay',
				''
			);
		} );

		it( 'should set deliveryDayDate to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayDate',
				''
			);
		} );

		it( 'should set deliveryDayFrom to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayFrom',
				''
			);
		} );

		it( 'should set deliveryDayTo to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayTo',
				''
			);
		} );

		it( 'should set deliveryDayPrice to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayPrice',
				''
			);
		} );

		it( 'should set deliveryDayType to empty string', () => {
			clearDeliveryDayExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'deliveryDayType',
				''
			);
		} );
	} );

	describe( 'clearDropoffPointExtensionData', () => {
		it( 'should clear all 12 dropoff point fields', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 12 );
		} );

		it( 'should set dropoffPoints to empty string', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPoints',
				''
			);
		} );

		it( 'should set dropoffPointsAddressCompany to empty string', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsAddressCompany',
				''
			);
		} );

		it( 'should set all address fields to empty strings', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsAddress1',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsAddress2',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsCity',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsPostcode',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsCountry',
				''
			);
		} );

		it( 'should set partner and timing fields to empty strings', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsPartnerID',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsDate',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsTime',
				''
			);
			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsType',
				''
			);
		} );

		it( 'should set dropoffPointsDistance to null', () => {
			clearDropoffPointExtensionData( mockSetExtensionData );

			expect( mockSetExtensionData ).toHaveBeenCalledWith(
				'postnl',
				'dropoffPointsDistance',
				null
			);
		} );
	} );

	describe( 'integration scenarios', () => {
		it( 'should work with consecutive calls', () => {
			// First set some data
			batchSetExtensionData( mockSetExtensionData, {
				deliveryDay: 'test',
				deliveryDayDate: '2024-01-15',
			} );

			// Then clear it
			clearDeliveryDayExtensionData( mockSetExtensionData );

			// Total calls: 2 (initial) + 6 (clear) = 8
			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 8 );
		} );

		it( 'should allow switching between delivery day and dropoff point', () => {
			// Select delivery day
			batchSetExtensionData( mockSetExtensionData, {
				deliveryDay: '2024-01-15_08:00-12:00_2.50',
			} );

			// Clear dropoff points when delivery is selected
			clearDropoffPointExtensionData( mockSetExtensionData );

			// 1 (delivery day) + 12 (clear dropoff) = 13
			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 13 );
		} );

		it( 'should handle rapid successive calls', () => {
			for ( let i = 0; i < 5; i++ ) {
				batchSetExtensionData( mockSetExtensionData, {
					key: `value${ i }`,
				} );
			}

			expect( mockSetExtensionData ).toHaveBeenCalledTimes( 5 );
		} );
	} );
} );
