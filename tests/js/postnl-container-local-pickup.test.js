/**
 * PostNL Container Block: Local pickup.
 *
 * Renders the container against stubbed cart and checkout stores and checks
 * that Local pickup gets no PostNL delivery options, and that switching back
 * to delivery loads them again.
 */

import { render, act } from '@testing-library/react';
import axios from 'axios';
import { useSelect } from '@wordpress/data';
import { getSetting } from '@woocommerce/settings';
import { Block } from '../../client/checkout/postnl-container/block';
import { batchSetExtensionData } from '../../client/utils/extension-data-helper';

jest.mock( 'axios' );

jest.mock( '../../client/checkout/postnl-delivery-day/block', () => ( {
	Block: () =>
		require( 'react' ).createElement( 'div', {
			'data-testid': 'delivery-day',
		} ),
} ) );

jest.mock( '../../client/checkout/postnl-dropoff-points/block', () => ( {
	Block: () =>
		require( 'react' ).createElement( 'div', {
			'data-testid': 'dropoff-points',
		} ),
} ) );

jest.mock( '../../client/utils/session-manager', () => ( {
	clearSessionData: jest.fn(),
} ) );

jest.mock( '../../client/utils/extension-data-helper', () => ( {
	...jest.requireActual( '../../client/utils/extension-data-helper' ),
	batchSetExtensionData: jest.fn(),
	clearAllExtensionData: jest.fn(),
	clearBackendDeliveryFee: jest.fn(),
	clearDropoffPointExtensionData: jest.fn(),
} ) );

/**
 * Checkout state the stubbed stores answer with.
 */
const state = {
	method: 'flat_rate',
	prefersCollection: false,
};

/**
 * Store API shipping rates with the method in `state` selected.
 *
 * @return {Array} Cart shippingRates.
 */
function shippingRates() {
	return [
		{
			shipping_rates: [
				{
					method_id: 'flat_rate',
					selected: 'flat_rate' === state.method,
					price: '595',
					currency_minor_unit: 2,
				},
				{
					method_id: 'pickup_location',
					selected: 'pickup_location' === state.method,
					price: '0',
					currency_minor_unit: 2,
				},
			],
		},
	];
}

const stores = {
	'wc/store/cart': {
		getCartData: () => ( { shippingRates: shippingRates() } ),
		getCartTotals: () => ( { shipping_total: 0 } ),
		getCustomerData: () => ( {
			shippingAddress: {
				country: 'NL',
				postcode: '6591BA',
				address_1: 'Hoofdstraat',
				'postnl/house_number': '1',
			},
		} ),
	},
	'wc/store/checkout': {
		isComplete: () => false,
		prefersCollection: () => state.prefersCollection,
	},
};

const props = { checkoutExtensionData: { setExtensionData: jest.fn() } };

/**
 * Let the container's debounced address lookup run and settle.
 */
async function flushLookup() {
	await act( async () => {
		jest.advanceTimersByTime( 1600 );
	} );
	await act( async () => {
		await Promise.resolve();
	} );
}

describe( 'PostNL Container Block: Local pickup', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		state.method = 'flat_rate';
		state.prefersCollection = false;

		useSelect.mockImplementation( ( callback ) =>
			callback( ( key ) => stores[ key ] )
		);
		getSetting.mockImplementation( ( key, fallback = {} ) =>
			'postnl-for-woocommerce-blocks_data' === key
				? {
						letterbox: false,
						is_pickup_points_enabled: true,
						is_nl_address_enabled: true,
						supported_countries: [ 'NL', 'BE' ],
						ajax_url: '/wp-admin/admin-ajax.php',
						nonce: 'test-nonce',
				  }
				: fallback
		);
		axios.post.mockResolvedValue( {
			data: {
				success: true,
				data: {
					show_container: true,
					is_delivery_days_enabled: true,
					delivery_options: testUtils.createMockDeliveryOptions( 2 ),
					dropoff_options: testUtils.createMockDropoffOptions( 2 ),
				},
			},
		} );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	it( 'loads and shows the PostNL options for a delivery method', async () => {
		const { queryByTestId } = render( <Block { ...props } /> );

		await flushLookup();

		expect( axios.post ).toHaveBeenCalledTimes( 1 );
		expect( queryByTestId( 'delivery-day' ) ).not.toBeNull();
	} );

	it( 'does not look up or show PostNL options when the Local pickup rate is selected', async () => {
		state.method = 'pickup_location';

		const { queryByTestId } = render( <Block { ...props } /> );

		await flushLookup();

		expect( axios.post ).not.toHaveBeenCalled();
		expect( queryByTestId( 'delivery-day' ) ).toBeNull();
	} );

	it( 'does not look up PostNL options when the shopper chose Pickup in the delivery toggle', async () => {
		state.prefersCollection = true;

		render( <Block { ...props } /> );

		await flushLookup();

		expect( axios.post ).not.toHaveBeenCalled();
	} );

	it( 'hides the options on a switch to Local pickup and loads them again on a switch back', async () => {
		const { queryByTestId, rerender } = render( <Block { ...props } /> );
		await flushLookup();
		expect( queryByTestId( 'delivery-day' ) ).not.toBeNull();

		state.method = 'pickup_location';
		rerender( <Block { ...props } /> );
		await flushLookup();

		expect( queryByTestId( 'delivery-day' ) ).toBeNull();
		expect( axios.post ).toHaveBeenCalledTimes( 1 );

		state.method = 'flat_rate';
		rerender( <Block { ...props } /> );
		await flushLookup();

		expect( axios.post ).toHaveBeenCalledTimes( 2 );
		expect( queryByTestId( 'delivery-day' ) ).not.toBeNull();
	} );

	it( 'ignores a lookup that resolves after the switch to Local pickup, so no letterbox delivery day is set', async () => {
		let resolveLookup;
		axios.post.mockReturnValueOnce(
			new Promise( ( resolve ) => {
				resolveLookup = resolve;
			} )
		);

		const { queryByTestId, rerender } = render( <Block { ...props } /> );
		await act( async () => {
			jest.advanceTimersByTime( 1600 );
		} );

		state.method = 'pickup_location';
		rerender( <Block { ...props } /> );

		await act( async () => {
			resolveLookup( {
				data: {
					success: true,
					data: {
						show_container: true,
						is_letterbox: true,
						delivery_options:
							testUtils.createMockDeliveryOptions( 1 ),
					},
				},
			} );
			await Promise.resolve();
		} );

		expect( queryByTestId( 'delivery-day' ) ).toBeNull();
		expect( batchSetExtensionData ).not.toHaveBeenCalled();
	} );
} );
