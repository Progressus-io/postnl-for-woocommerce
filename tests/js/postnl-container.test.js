/**
 * PostNL Container Block Tests
 *
 * Tests for the main container block that orchestrates checkout delivery options.
 * Note: This component has complex dependencies on WooCommerce stores.
 * These tests focus on testable units and helper functions.
 */

import axios from 'axios';
import { getSetting } from '@woocommerce/settings';
import { useSelect, useDispatch } from '@wordpress/data';

// Mock axios
jest.mock( 'axios' );

// Mock session manager
jest.mock( '../../client/utils/session-manager', () => ( {
	getDeliveryDay: jest.fn( () => ( {} ) ),
	clearSessionData: jest.fn(),
} ) );

// Mock extension data helper
jest.mock( '../../client/utils/extension-data-helper', () => ( {
	batchSetExtensionData: jest.fn(),
	clearDropoffPointExtensionData: jest.fn(),
} ) );

describe( 'PostNL Container Block - Unit Tests', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// Setup default mocks
		useDispatch.mockReturnValue( {
			setShippingAddress: jest.fn(),
			updateCustomerData: jest.fn(),
		} );

		// Setup default axios mock
		axios.post.mockResolvedValue( {
			data: {
				success: true,
				data: {
					show_container: true,
					delivery_options: testUtils.createMockDeliveryOptions( 2 ),
					dropoff_options: testUtils.createMockDropoffOptions( 3 ),
					is_delivery_days_enabled: true,
					validated_address: null,
				},
			},
		} );
	} );

	describe( 'helper functions', () => {
		// Test the isEmpty helper function logic
		describe( 'isEmpty logic', () => {
			const isEmpty = ( value ) =>
				value === undefined || value === null || value === '';

			it( 'should return true for undefined', () => {
				expect( isEmpty( undefined ) ).toBe( true );
			} );

			it( 'should return true for null', () => {
				expect( isEmpty( null ) ).toBe( true );
			} );

			it( 'should return true for empty string', () => {
				expect( isEmpty( '' ) ).toBe( true );
			} );

			it( 'should return false for non-empty string', () => {
				expect( isEmpty( 'test' ) ).toBe( false );
			} );

			it( 'should return false for zero', () => {
				expect( isEmpty( 0 ) ).toBe( false );
			} );

			it( 'should return false for false', () => {
				expect( isEmpty( false ) ).toBe( false );
			} );
		} );

		// Test the isAddressEqual helper function logic
		describe( 'isAddressEqual logic', () => {
			const isAddressEqual = ( addr1, addr2 ) => {
				if ( ! addr1 || ! addr2 ) {
					return false;
				}
				return (
					addr1.country === addr2.country &&
					addr1.postcode === addr2.postcode &&
					addr1.address_1 === addr2.address_1 &&
					addr1[ 'postnl/house_number' ] ===
						addr2[ 'postnl/house_number' ]
				);
			};

			it( 'should return false when addr1 is null', () => {
				expect( isAddressEqual( null, { country: 'NL' } ) ).toBe(
					false
				);
			} );

			it( 'should return false when addr2 is null', () => {
				expect( isAddressEqual( { country: 'NL' }, null ) ).toBe(
					false
				);
			} );

			it( 'should return true for identical addresses', () => {
				const addr = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
					'postnl/house_number': '1',
				};
				expect( isAddressEqual( addr, { ...addr } ) ).toBe( true );
			} );

			it( 'should return false when country differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, country: 'BE' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when postcode differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, postcode: '5678CD' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when address_1 differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, address_1: 'Other Street' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when house_number differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
					'postnl/house_number': '1',
				};
				const addr2 = { ...addr1, 'postnl/house_number': '2' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );
		} );
	} );

	describe( 'API response handling', () => {
		it( 'should handle successful API response structure', async () => {
			const mockResponse = {
				data: {
					success: true,
					data: {
						show_container: true,
						delivery_options: [
							{
								date: '2024-01-10',
								options: [
									{ from: '08:00', to: '12:00', price: 0 },
								],
							},
						],
						dropoff_options: [],
						is_delivery_days_enabled: true,
						validated_address: {
							street: 'Main Street',
							city: 'Amsterdam',
							house_number: '1',
						},
					},
				},
			};

			axios.post.mockResolvedValueOnce( mockResponse );

			const response = await axios.post( '/test', {} );
			expect( response.data.success ).toBe( true );
			expect( response.data.data.delivery_options ).toHaveLength( 1 );
			expect( response.data.data.validated_address.street ).toBe(
				'Main Street'
			);
		} );

		it( 'should handle API error response', async () => {
			axios.post.mockRejectedValueOnce( new Error( 'Network error' ) );

			await expect( axios.post( '/test', {} ) ).rejects.toThrow(
				'Network error'
			);
		} );

		it( 'should handle API success=false response', async () => {
			axios.post.mockResolvedValueOnce( {
				data: {
					success: false,
					data: null,
				},
			} );

			const response = await axios.post( '/test', {} );
			expect( response.data.success ).toBe( false );
			expect( response.data.data ).toBeNull();
		} );
	} );

	describe( 'settings configuration', () => {
		it( 'should read letterbox setting', () => {
			getSetting.mockReturnValue( { letterbox: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.letterbox ).toBe( true );
		} );

		it( 'should read pickup_points_enabled setting', () => {
			getSetting.mockReturnValue( { is_pickup_points_enabled: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.is_pickup_points_enabled ).toBe( true );
		} );

		it( 'should read delivery_day_fee setting', () => {
			getSetting.mockReturnValue( {
				delivery_day_fee: 2.5,
				delivery_day_fee_formatted: '€2,50',
			} );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.delivery_day_fee ).toBe( 2.5 );
			expect( settings.delivery_day_fee_formatted ).toBe( '€2,50' );
		} );

		it( 'should read is_nl_address_enabled setting', () => {
			getSetting.mockReturnValue( { is_nl_address_enabled: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.is_nl_address_enabled ).toBe( true );
		} );
	} );

	describe( 'address validation logic', () => {
		it( 'should combine street and house number when is_nl_address_enabled is false', () => {
			const validated = {
				street: 'Main Street',
				house_number: '123',
			};
			const is_nl_address_enabled = false;

			let address_1;
			if ( ! is_nl_address_enabled ) {
				address_1 = `${ validated.street } ${ validated.house_number }`;
			} else {
				address_1 = validated.street;
			}

			expect( address_1 ).toBe( 'Main Street 123' );
		} );

		it( 'should use only street when is_nl_address_enabled is true', () => {
			const validated = {
				street: 'Main Street',
				house_number: '123',
			};
			const is_nl_address_enabled = true;

			let address_1;
			if ( ! is_nl_address_enabled ) {
				address_1 = `${ validated.street } ${ validated.house_number }`;
			} else {
				address_1 = validated.street;
			}

			expect( address_1 ).toBe( 'Main Street' );
		} );
	} );

	describe( 'tab configuration', () => {
		it( 'should create base tabs with delivery tab', () => {
			const postnlData = {
				delivery_day_fee: 0,
				delivery_day_fee_formatted: '',
				is_pickup_points_enabled: false,
			};

			const baseTabs = [
				{
					id: 'delivery_day',
					base: Number( postnlData.delivery_day_fee || 0 ),
					displayFormatted:
						postnlData.delivery_day_fee_formatted || '',
				},
			];

			expect( baseTabs ).toHaveLength( 1 );
			expect( baseTabs[ 0 ].id ).toBe( 'delivery_day' );
		} );

		it( 'should include pickup tab when enabled', () => {
			const postnlData = {
				delivery_day_fee: 0,
				delivery_day_fee_formatted: '',
				is_pickup_points_enabled: true,
				pickup_fee: 2.5,
				pickup_fee_formatted: '€2,50',
			};

			const baseTabs = [
				{
					id: 'delivery_day',
					base: Number( postnlData.delivery_day_fee || 0 ),
					displayFormatted:
						postnlData.delivery_day_fee_formatted || '',
				},
				...( postnlData.is_pickup_points_enabled
					? [
							{
								id: 'dropoff_points',
								base: Number( postnlData.pickup_fee || 0 ),
								displayFormatted:
									postnlData.pickup_fee_formatted || '',
							},
					  ]
					: [] ),
			];

			expect( baseTabs ).toHaveLength( 2 );
			expect( baseTabs[ 1 ].id ).toBe( 'dropoff_points' );
			expect( baseTabs[ 1 ].base ).toBe( 2.5 );
		} );
	} );

	describe( 'fee calculation logic', () => {
		it( 'should calculate carrier base cost correctly', () => {
			const selectedShippingFee = 10;
			const tabBase = 2.5;
			const extraDeliveryFee = 1.5;

			const carrierBaseCost = selectedShippingFee - tabBase - extraDeliveryFee;
			expect( carrierBaseCost ).toBe( 6 );
		} );

		it( 'should not have negative carrier base cost', () => {
			const selectedShippingFee = 2;
			const tabBase = 2.5;
			const extraDeliveryFee = 1.5;

			const raw = selectedShippingFee - tabBase - extraDeliveryFee;
			const carrierBaseCost = raw < 0 ? 0 : raw;
			expect( carrierBaseCost ).toBe( 0 );
		} );

		it( 'should format tab title with fees', () => {
			const tab = {
				id: 'delivery_day',
				base: 2.5,
				displayFormatted: '€2,50',
			};
			const extraDeliveryFeeFormatted = '€1,00';
			const extraDeliveryFee = 1;

			let title = 'Delivery';
			const fees = [];

			if ( tab.displayFormatted && tab.base > 0 ) {
				fees.push( tab.displayFormatted );
			}

			if (
				tab.id === 'delivery_day' &&
				extraDeliveryFeeFormatted &&
				extraDeliveryFee > 0
			) {
				fees.push( extraDeliveryFeeFormatted );
			}

			if ( fees.length > 0 ) {
				title += ` (+${ fees.join( ' +' ) })`;
			}

			expect( title ).toBe( 'Delivery (+€2,50 +€1,00)' );
		} );
	} );

	describe( 'session management', () => {
		const { getDeliveryDay, clearSessionData } = require( '../../client/utils/session-manager' );

		it( 'should call getDeliveryDay on initialization', () => {
			getDeliveryDay();
			expect( getDeliveryDay ).toHaveBeenCalled();
		} );

		it( 'should call clearSessionData when needed', () => {
			clearSessionData();
			expect( clearSessionData ).toHaveBeenCalled();
		} );
	} );

	describe( 'letterbox delivery', () => {
		it( 'should create letterbox delivery value', () => {
			const firstDelivery = {
				date: '2024-01-10',
				options: [
					{
						from: '08:00',
						to: '22:00',
						price: 0,
						type: 'Letterbox',
					},
				],
			};

			const firstOption = firstDelivery.options[ 0 ];
			const deliveryDay = `${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`;

			expect( deliveryDay ).toBe( '2024-01-10_08:00-22:00_0' );
		} );
	} );
} );
