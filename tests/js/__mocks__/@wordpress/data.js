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
} ) );

export default { useSelect, useDispatch };
