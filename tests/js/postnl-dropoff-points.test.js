/**
 * PostNL Dropoff Points Block Tests
 *
 * Tests for the pickup/dropoff points selection component.
 */

import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Block } from '../../client/checkout/postnl-dropoff-points/block';
import {
	getDropoffPoint,
	setDropoffPoint,
	clearDropoffPoint,
	clearDeliveryDay,
} from '../../client/utils/session-manager';
import {
	batchSetExtensionData,
	clearDeliveryDayExtensionData,
	clearDropoffPointExtensionData,
} from '../../client/utils/extension-data-helper';

// Mock session manager
jest.mock( '../../client/utils/session-manager', () => ( {
	getDropoffPoint: jest.fn( () => ( {} ) ),
	setDropoffPoint: jest.fn(),
	clearDropoffPoint: jest.fn(),
	clearDeliveryDay: jest.fn(),
} ) );

// Mock extension data helper
jest.mock( '../../client/utils/extension-data-helper', () => ( {
	batchSetExtensionData: jest.fn(),
	clearDeliveryDayExtensionData: jest.fn(),
	clearDropoffPointExtensionData: jest.fn(),
} ) );

// Mock lodash debounce to execute immediately in tests
jest.mock( 'lodash', () => ( {
	...jest.requireActual( 'lodash' ),
	debounce: ( fn ) => {
		const debouncedFn = ( ...args ) => fn( ...args );
		debouncedFn.cancel = jest.fn();
		return debouncedFn;
	},
} ) );

describe( 'PostNL Dropoff Points Block', () => {
	let mockSetExtensionData;

	beforeEach( () => {
		jest.clearAllMocks();

		mockSetExtensionData = jest.fn();

		// Reset session manager mock to return empty data
		getDropoffPoint.mockReturnValue( {} );
	} );

	const defaultDropoffOptions = [
		{
			partner_id: 'PARTNER0',
			loc_code: 'LOC0',
			name: 'PostNL Point 1',
			address: {
				company: 'Albert Heijn',
				address_1: 'Kalverstraat 1',
				address_2: '',
				city: 'Amsterdam',
				postcode: '1000AA',
				country: 'NL',
			},
			distance: 150,
			date: '2024-01-10',
			time: '09:00-18:00',
			type: 'PNL',
		},
		{
			partner_id: 'PARTNER1',
			loc_code: 'LOC1',
			name: 'PostNL Point 2',
			address: {
				company: 'Jumbo',
				address_1: 'Dam 10',
				address_2: 'Floor 2',
				city: 'Amsterdam',
				postcode: '1000AB',
				country: 'NL',
			},
			distance: 1200,
			date: '2024-01-11',
			time: '08:00-20:00',
			type: 'PNL',
		},
		{
			partner_id: 'PARTNER2',
			loc_code: 'LOC2',
			name: 'PostNL Point 3',
			address: {
				company: 'GAMMA',
				address_1: 'Rokin 50',
				address_2: '',
				city: 'Amsterdam',
				postcode: '1000AC',
				country: 'NL',
			},
			distance: 2500,
			date: '2024-01-10',
			time: '07:00-21:00',
			type: 'Retail',
		},
	];

	const renderComponent = ( props = {} ) => {
		const defaultProps = {
			checkoutExtensionData: {
				setExtensionData: mockSetExtensionData,
			},
			isActive: true,
			dropoffOptions: defaultDropoffOptions,
		};
		return render( <Block { ...defaultProps } { ...props } /> );
	};

	describe( 'initial rendering', () => {
		it( 'should render dropoff points list', () => {
			renderComponent();

			expect(
				document.querySelector( '.postnl_dropoff_points_list' )
			).toBeInTheDocument();
		} );

		it( 'should render all dropoff points', () => {
			renderComponent();

			const points = document.querySelectorAll(
				'.postnl_dropoff_points_list > li'
			);
			expect( points ).toHaveLength( 3 );
		} );

		it( 'should display company names', () => {
			renderComponent();

			expect( screen.getByText( 'Albert Heijn' ) ).toBeInTheDocument();
			expect( screen.getByText( 'Jumbo' ) ).toBeInTheDocument();
			expect( screen.getByText( 'GAMMA' ) ).toBeInTheDocument();
		} );

		it( 'should display addresses', () => {
			renderComponent();

			expect(
				screen.getByText( /Kalverstraat 1.*Amsterdam.*1000AA/i )
			).toBeInTheDocument();
		} );
	} );

	describe( 'distance conversion', () => {
		it( 'should display distance in meters when less than 1km', () => {
			renderComponent();

			expect( screen.getByText( '150 m' ) ).toBeInTheDocument();
		} );

		it( 'should convert distance to km when 1000m or more', () => {
			renderComponent();

			expect( screen.getByText( '1.2 km' ) ).toBeInTheDocument();
			expect( screen.getByText( '2.5 km' ) ).toBeInTheDocument();
		} );
	} );

	describe( 'session persistence', () => {
		it( 'should load saved selection from session on mount', () => {
			getDropoffPoint.mockReturnValue( {
				value: 'PARTNER1-LOC1',
				company: 'Jumbo',
				address1: 'Dam 10',
				city: 'Amsterdam',
			} );

			renderComponent();

			expect( getDropoffPoint ).toHaveBeenCalled();
		} );

		it( 'should mark saved point as checked', () => {
			getDropoffPoint.mockReturnValue( {
				value: 'PARTNER1-LOC1',
				company: 'Jumbo',
			} );

			renderComponent();

			const radio = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			expect( radio ).toBeChecked();
		} );
	} );

	describe( 'auto-selection', () => {
		it( 'should auto-select first point when tab becomes active', async () => {
			renderComponent( { isActive: true } );

			await waitFor( () => {
				expect( setDropoffPoint ).toHaveBeenCalled();
			} );
		} );

		it( 'should not auto-select when there is already a selection', () => {
			getDropoffPoint.mockReturnValue( {
				value: 'PARTNER2-LOC2',
				company: 'GAMMA',
			} );

			renderComponent();

			// The sync should happen but not trigger auto-select of first option
			const firstPointRadio = document.querySelector(
				'input[value="PARTNER0-LOC0"]'
			);
			const selectedRadio = document.querySelector(
				'input[value="PARTNER2-LOC2"]'
			);

			expect( selectedRadio ).toBeChecked();
			expect( firstPointRadio ).not.toBeChecked();
		} );

		it( 'should clear selections when tab becomes inactive', () => {
			renderComponent( { isActive: false } );

			expect( clearDropoffPointExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData
			);
		} );
	} );

	describe( 'point selection', () => {
		it( 'should handle point change', async () => {
			const user = userEvent.setup();
			renderComponent();

			const secondPoint = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( secondPoint );

			expect( setDropoffPoint ).toHaveBeenCalled();
		} );

		it( 'should save all 12 required fields to session', async () => {
			const user = userEvent.setup();
			renderComponent();

			// Clear from auto-selection
			setDropoffPoint.mockClear();

			const secondPoint = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( secondPoint );

			expect( setDropoffPoint ).toHaveBeenCalledWith(
				expect.objectContaining( {
					value: 'PARTNER1-LOC1',
					company: 'Jumbo',
					address1: 'Dam 10',
					address2: 'Floor 2',
					city: 'Amsterdam',
					postcode: '1000AB',
					country: 'NL',
					partnerID: 'PARTNER1',
					date: '2024-01-11',
					time: '08:00-20:00',
					type: 'PNL',
					distance: 1200,
				} )
			);
		} );

		it( 'should update extension data with all fields', async () => {
			const user = userEvent.setup();
			renderComponent();

			// Clear from auto-selection
			batchSetExtensionData.mockClear();

			const secondPoint = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( secondPoint );

			expect( batchSetExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData,
				expect.objectContaining( {
					dropoffPoints: 'PARTNER1-LOC1',
					dropoffPointsAddressCompany: 'Jumbo',
					dropoffPointsAddress1: 'Dam 10',
					dropoffPointsAddress2: 'Floor 2',
					dropoffPointsCity: 'Amsterdam',
					dropoffPointsPostcode: '1000AB',
					dropoffPointsCountry: 'NL',
					dropoffPointsPartnerID: 'PARTNER1',
					dropoffPointsDate: '2024-01-11',
					dropoffPointsTime: '08:00-20:00',
					dropoffPointsType: 'PNL',
					dropoffPointsDistance: 1200,
				} )
			);
		} );

		it( 'should clear delivery day data when dropoff is selected', async () => {
			const user = userEvent.setup();
			renderComponent();

			const point = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( point );

			expect( clearDeliveryDay ).toHaveBeenCalled();
			expect( clearDeliveryDayExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData
			);
		} );

		it( 'should add active class to selected point', async () => {
			const user = userEvent.setup();
			renderComponent();

			const secondPoint = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( secondPoint );

			const selectedLi = secondPoint.closest( 'li' );
			expect( selectedLi ).toHaveClass( 'active' );
		} );
	} );

	describe( 'extensionCartUpdate', () => {
		it( 'should call extensionCartUpdate when point is selected', async () => {
			const mockExtensionCartUpdate = jest.fn( () => Promise.resolve() );
			window.wc.blocksCheckout.extensionCartUpdate =
				mockExtensionCartUpdate;

			const user = userEvent.setup();
			renderComponent();

			// Clear from auto-selection
			mockExtensionCartUpdate.mockClear();

			const point = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( point );

			expect( mockExtensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'postnl',
				data: {
					action: 'update_delivery_fee',
					price: expect.any( Number ),
					type: 'Pickup',
				},
			} );
		} );

		it( 'should handle extensionCartUpdate error gracefully', async () => {
			const mockExtensionCartUpdate = jest.fn( () =>
				Promise.reject( new Error( 'Cart update failed' ) )
			);
			window.wc.blocksCheckout.extensionCartUpdate =
				mockExtensionCartUpdate;

			const user = userEvent.setup();
			renderComponent();

			const point = document.querySelector(
				'input[value="PARTNER1-LOC1"]'
			);
			await user.click( point );

			// Test passes if no error is thrown
			expect( mockExtensionCartUpdate ).toHaveBeenCalled();
		} );
	} );

	describe( 'description message', () => {
		it( 'should show description when any point has show_desc', () => {
			const optionsWithDesc = [
				{
					...defaultDropoffOptions[ 0 ],
					show_desc: true,
				},
			];

			renderComponent( { dropoffOptions: optionsWithDesc } );

			expect(
				screen.getByText( /Receive shipment at home/i )
			).toBeInTheDocument();
		} );

		it( 'should not show description when no points have show_desc', () => {
			renderComponent();

			expect(
				screen.queryByText( /Receive shipment at home/i )
			).not.toBeInTheDocument();
		} );
	} );

	describe( 'edge cases', () => {
		it( 'should handle point with missing address gracefully', () => {
			const optionsWithMissingAddress = [
				{
					partner_id: 'PARTNER0',
					loc_code: 'LOC0',
					name: 'Point without full address',
					address: {
						company: 'Test Shop',
						// Missing address_1, address_2, city, postcode, country
					},
					distance: 100,
					date: '2024-01-10',
					time: '09:00-18:00',
					type: 'PNL',
				},
			];

			// This will test the unsafe property access issue
			// The component should handle this gracefully
			expect( () => {
				renderComponent( { dropoffOptions: optionsWithMissingAddress } );
			} ).not.toThrow();
		} );

		it( 'should handle empty dropoff options', () => {
			renderComponent( { dropoffOptions: [] } );

			// Should render empty list
			expect(
				document.querySelector( '.postnl_dropoff_points_list' )
			).toBeInTheDocument();
			expect(
				document.querySelectorAll( '.postnl_dropoff_points_list > li' )
			).toHaveLength( 0 );
		} );

		it( 'should handle point with null distance', () => {
			const optionsWithNullDistance = [
				{
					...defaultDropoffOptions[ 0 ],
					distance: null,
				},
			];

			renderComponent( { dropoffOptions: optionsWithNullDistance } );

			// Should handle null distance gracefully
			expect(
				document.querySelector( '.postnl_dropoff_points_list' )
			).toBeInTheDocument();
		} );

		it( 'should handle point with string distance', () => {
			const optionsWithStringDistance = [
				{
					...defaultDropoffOptions[ 0 ],
					distance: '500',
				},
			];

			renderComponent( { dropoffOptions: optionsWithStringDistance } );

			expect( screen.getByText( '500 m' ) ).toBeInTheDocument();
		} );

		it( 'should handle when selected point is not in options', async () => {
			// Start with a saved selection for a point that doesn't exist
			getDropoffPoint.mockReturnValue( {
				value: 'NONEXISTENT-POINT',
				company: 'Old Shop',
			} );

			renderComponent();

			// The non-existent point won't be checked (no matching radio button)
			const radio = document.querySelector(
				'input[value="NONEXISTENT-POINT"]'
			);
			expect( radio ).toBeNull();

			// But the existing points should still render
			expect(
				document.querySelector( 'input[value="PARTNER0-LOC0"]' )
			).toBeInTheDocument();
		} );
	} );

	describe( 'data attributes', () => {
		it( 'should set correct data attributes on list items', () => {
			renderComponent();

			const firstPointLi = document.querySelector(
				'li[data-partner_id="PARTNER0"]'
			);

			expect( firstPointLi ).toHaveAttribute( 'data-loc_code', 'LOC0' );
			expect( firstPointLi ).toHaveAttribute( 'data-date', '2024-01-10' );
			expect( firstPointLi ).toHaveAttribute(
				'data-time',
				'09:00-18:00'
			);
			expect( firstPointLi ).toHaveAttribute( 'data-type', 'PNL' );
			expect( firstPointLi ).toHaveAttribute( 'data-distance', '150' );
		} );
	} );
} );
