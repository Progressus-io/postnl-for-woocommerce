/**
 * PostNL Delivery Day Block Tests
 *
 * Tests for the delivery day selection component.
 */

import { render, screen, waitFor, act } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { Block } from '../../client/checkout/postnl-delivery-day/block';
import {
	getDeliveryDay,
	setDeliveryDay,
	clearDeliveryDay,
	clearDropoffPoint,
} from '../../client/utils/session-manager';
import {
	batchSetExtensionData,
	clearDropoffPointExtensionData,
	clearDeliveryDayExtensionData,
} from '../../client/utils/extension-data-helper';

// Mock session manager
jest.mock( '../../client/utils/session-manager', () => ( {
	getDeliveryDay: jest.fn( () => ( {} ) ),
	setDeliveryDay: jest.fn(),
	clearDeliveryDay: jest.fn(),
	clearDropoffPoint: jest.fn(),
} ) );

// Mock extension data helper
jest.mock( '../../client/utils/extension-data-helper', () => ( {
	batchSetExtensionData: jest.fn(),
	clearDropoffPointExtensionData: jest.fn(),
	clearDeliveryDayExtensionData: jest.fn(),
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

describe( 'PostNL Delivery Day Block', () => {
	let mockSetExtensionData;
	let mockOnPriceChange;

	beforeEach( () => {
		jest.clearAllMocks();

		mockSetExtensionData = jest.fn();
		mockOnPriceChange = jest.fn();

		// Reset session manager mock to return empty data
		getDeliveryDay.mockReturnValue( {} );
	} );

	const defaultDeliveryOptions = testUtils.createMockDeliveryOptions( 2 );

	const renderComponent = ( props = {} ) => {
		const defaultProps = {
			checkoutExtensionData: {
				setExtensionData: mockSetExtensionData,
			},
			isActive: true,
			deliveryOptions: defaultDeliveryOptions,
			isDeliveryDaysEnabled: true,
			onPriceChange: mockOnPriceChange,
		};
		return render( <Block { ...defaultProps } { ...props } /> );
	};

	describe( 'initial rendering', () => {
		it( 'should render delivery options list', () => {
			renderComponent();

			expect(
				document.querySelector( '.postnl_delivery_day_list' )
			).toBeInTheDocument();
		} );

		it( 'should render all delivery options', () => {
			renderComponent();

			const options = document.querySelectorAll(
				'.postnl_delivery_day_list > li'
			);
			expect( options ).toHaveLength( 2 );
		} );

		it( 'should display date and day for each option', () => {
			renderComponent();

			expect( screen.getByText( /January 10/i ) ).toBeInTheDocument();
			expect( screen.getByText( /January 11/i ) ).toBeInTheDocument();
		} );

		it( 'should display time slots', () => {
			renderComponent();

			expect( screen.getAllByText( /08:00 - 12:00/i ) ).toHaveLength( 2 );
			expect( screen.getAllByText( /17:00 - 22:00/i ) ).toHaveLength( 2 );
		} );

		it( 'should return null when no delivery options', () => {
			const { container } = renderComponent( { deliveryOptions: [] } );

			expect( container.firstChild ).toBeNull();
		} );

		it( 'should return null when deliveryOptions is not an array', () => {
			const { container } = renderComponent( {
				deliveryOptions: null,
			} );

			expect( container.firstChild ).toBeNull();
		} );
	} );

	describe( 'session persistence', () => {
		it( 'should load saved selection from session on mount', () => {
			getDeliveryDay.mockReturnValue( {
				value: '2024-01-10_08:00-12:00_2.5',
				date: '2024-01-10',
				from: '08:00',
				to: '12:00',
				price: 2.5,
				priceFormatted: '2,50',
				type: 'Morning',
			} );

			renderComponent();

			expect( getDeliveryDay ).toHaveBeenCalled();
		} );

		it( 'should mark saved option as checked', () => {
			getDeliveryDay.mockReturnValue( {
				value: '2024-01-10_08:00-12:00_2.5',
				date: '2024-01-10',
				from: '08:00',
				to: '12:00',
				price: 2.5,
				type: 'Morning',
			} );

			renderComponent();

			const radio = document.querySelector(
				'input[value="2024-01-10_08:00-12:00_2.5"]'
			);
			expect( radio ).toBeChecked();
		} );
	} );

	describe( 'auto-selection', () => {
		it( 'should auto-select first option when tab becomes active', async () => {
			renderComponent( { isActive: true } );

			await waitFor( () => {
				expect( setDeliveryDay ).toHaveBeenCalled();
			} );
		} );

		it( 'should not auto-select when there is already a selection', () => {
			getDeliveryDay.mockReturnValue( {
				value: '2024-01-10_17:00-22:00_3',
				date: '2024-01-10',
				from: '17:00',
				to: '22:00',
				price: 3,
				type: 'Evening',
			} );

			renderComponent();

			// The first call is syncing the existing selection
			expect( batchSetExtensionData ).toHaveBeenCalled();
		} );

		it( 'should clear selections when tab becomes inactive', () => {
			renderComponent( { isActive: false, deliveryOptions: [] } );

			expect( clearDeliveryDayExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData
			);
		} );

		it( 'should notify parent of price change when auto-selecting', async () => {
			renderComponent();

			await waitFor( () => {
				expect( mockOnPriceChange ).toHaveBeenCalledWith( {
					numeric: 2.5,
					formatted: '2,50',
				} );
			} );
		} );
	} );

	describe( 'option selection', () => {
		it( 'should handle option change', async () => {
			const user = userEvent.setup();
			renderComponent();

			const eveningOption = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( eveningOption );

			expect( setDeliveryDay ).toHaveBeenCalled();
		} );

		it( 'should save selection to session manager', async () => {
			const user = userEvent.setup();
			renderComponent();

			const eveningOption = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( eveningOption );

			expect( setDeliveryDay ).toHaveBeenCalledWith(
				expect.objectContaining( {
					date: '2024-01-10',
					from: '17:00',
					to: '22:00',
					type: 'Evening',
				} )
			);
		} );

		it( 'should update extension data on selection', async () => {
			const user = userEvent.setup();
			renderComponent();

			const morningOption = document.querySelector(
				'input[value="2024-01-10_08:00-12:00_2.5"]'
			);
			await user.click( morningOption );

			expect( batchSetExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData,
				expect.objectContaining( {
					deliveryDayDate: '2024-01-10',
					deliveryDayFrom: '08:00',
					deliveryDayTo: '12:00',
				} )
			);
		} );

		it( 'should clear dropoff point data when delivery is selected', async () => {
			const user = userEvent.setup();
			renderComponent();

			const option = document.querySelector(
				'input[value="2024-01-10_08:00-12:00_2.5"]'
			);
			await user.click( option );

			expect( clearDropoffPoint ).toHaveBeenCalled();
			expect( clearDropoffPointExtensionData ).toHaveBeenCalledWith(
				mockSetExtensionData
			);
		} );

		it( 'should notify parent of price change', async () => {
			const user = userEvent.setup();
			renderComponent();

			// Clear previous calls from auto-selection
			mockOnPriceChange.mockClear();

			const eveningOption = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( eveningOption );

			expect( mockOnPriceChange ).toHaveBeenCalledWith( {
				numeric: 3,
				formatted: '3,00',
			} );
		} );

		it( 'should add active class to selected option', async () => {
			const user = userEvent.setup();
			renderComponent();

			const eveningOption = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( eveningOption );

			const selectedLi = eveningOption.closest( 'li' );
			expect( selectedLi ).toHaveClass( 'active' );
		} );
	} );

	describe( 'display formatting', () => {
		it( 'should display Morning label for morning options', () => {
			renderComponent();

			expect( screen.getAllByText( 'Morning' ) ).toHaveLength( 2 );
		} );

		it( 'should display Evening label for evening options', () => {
			renderComponent();

			expect( screen.getAllByText( 'Evening' ) ).toHaveLength( 2 );
		} );

		it( 'should display price when price > 0', () => {
			renderComponent();

			expect( screen.getAllByText( /\+2,50/i ) ).toHaveLength( 2 );
			expect( screen.getAllByText( /\+3,00/i ) ).toHaveLength( 2 );
		} );

		it( 'should not display date/day when isDeliveryDaysEnabled is false', () => {
			renderComponent( { isDeliveryDaysEnabled: false } );

			expect( screen.queryByText( /January 10/i ) ).not.toBeInTheDocument();
			expect( screen.queryByText( /Monday/i ) ).not.toBeInTheDocument();
		} );

		it( 'should show "As soon as possible" when isDeliveryDaysEnabled is false', () => {
			renderComponent( { isDeliveryDaysEnabled: false } );

			expect(
				screen.getAllByText( /As soon as possible/i )
			).toHaveLength( 4 ); // 2 days x 2 options
		} );
	} );

	describe( 'extensionCartUpdate', () => {
		it( 'should call extensionCartUpdate when option is selected', async () => {
			const mockExtensionCartUpdate = jest.fn( () => Promise.resolve() );
			window.wc.blocksCheckout.extensionCartUpdate =
				mockExtensionCartUpdate;

			const user = userEvent.setup();
			renderComponent();

			const option = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( option );

			expect( mockExtensionCartUpdate ).toHaveBeenCalledWith( {
				namespace: 'postnl',
				data: {
					action: 'update_delivery_fee',
					price: 3,
					type: 'Evening',
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

			// Should not throw
			renderComponent();

			const option = document.querySelector(
				'input[value="2024-01-10_17:00-22:00_3"]'
			);
			await user.click( option );

			// Test passes if no error is thrown
			expect( mockExtensionCartUpdate ).toHaveBeenCalled();
		} );
	} );

	describe( 'edge cases', () => {
		it( 'should handle options with missing price', () => {
			const optionsWithMissingPrice = [
				{
					date: '2024-01-10',
					display_date: 'January 10',
					day: 'Monday',
					options: [
						{
							from: '08:00',
							to: '12:00',
							type: 'Morning',
							// No price
						},
					],
				},
			];

			renderComponent( { deliveryOptions: optionsWithMissingPrice } );

			// Should render without crashing
			expect(
				document.querySelector( '.postnl_delivery_day_list' )
			).toBeInTheDocument();
		} );

		it( 'should handle options with missing time', () => {
			const optionsWithMissingTime = [
				{
					date: '2024-01-10',
					display_date: 'January 10',
					day: 'Monday',
					options: [
						{
							// No from/to
							type: 'Standard',
							price: 0,
						},
					],
				},
			];

			renderComponent( { deliveryOptions: optionsWithMissingTime } );

			// Should display empty time range
			expect( screen.getByText( '-' ) ).toBeInTheDocument();
		} );

		it( 'should handle delivery with empty options array', () => {
			const emptyOptionsDelivery = [
				{
					date: '2024-01-10',
					display_date: 'January 10',
					day: 'Monday',
					options: [],
				},
			];

			renderComponent( { deliveryOptions: emptyOptionsDelivery } );

			// Should render the date but no sub-options
			expect(
				document.querySelectorAll( '.postnl_sub_list li' )
			).toHaveLength( 0 );
		} );
	} );
} );
