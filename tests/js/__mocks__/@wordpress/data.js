/**
 * Mock for @wordpress/data
 */
export const useSelect = jest.fn( ( callback ) => {
	const mockSelect = () => ( {
		getCartData: () => ( { shippingRates: [] } ),
		getCartTotals: () => ( { shipping_total: 0 } ),
		getCustomerData: () => ( {
			shippingAddress: {
				country: 'NL',
				postcode: '1234AB',
				address_1: 'Test Street 1',
				'postnl/house_number': '1',
			},
		} ),
		isComplete: () => false,
	} );
	return callback( mockSelect );
} );

export const useDispatch = jest.fn( () => ( {
	setShippingAddress: jest.fn(),
	updateCustomerData: jest.fn(),
	setBillingAddress: jest.fn(),
	createErrorNotice: jest.fn(),
} ) );

export const combineReducers = jest.fn( ( reducers ) => reducers );

export const createReduxStore = jest.fn( () => ( {} ) );

export const register = jest.fn();

export const createSelector = jest.fn( ( ...args ) => {
	const lastArg = args[ args.length - 1 ];
	return typeof lastArg === 'function' ? lastArg : () => {};
} );

export const createRegistrySelector = jest.fn( ( callback ) => callback );

export const select = jest.fn( () => ( {
	getCartData: () => ( { shippingRates: [] } ),
	getCartTotals: () => ( { shipping_total: 0 } ),
	getCustomerData: () => ( {
		shippingAddress: {
			country: 'NL',
			postcode: '1234AB',
			address_1: 'Test Street 1',
		},
	} ),
	isComplete: () => false,
} ) );

export const dispatch = jest.fn( () => ( {
	setShippingAddress: jest.fn(),
	updateCustomerData: jest.fn(),
} ) );

export default {
	useSelect,
	useDispatch,
	combineReducers,
	createReduxStore,
	register,
	createSelector,
	createRegistrySelector,
	select,
	dispatch,
};
