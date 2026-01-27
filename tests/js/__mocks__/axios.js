/**
 * Mock for axios
 */
const axios = {
	post: jest.fn( () =>
		Promise.resolve( {
			data: {
				success: true,
				data: {
					show_container: true,
					delivery_options: [],
					dropoff_options: [],
					is_delivery_days_enabled: true,
					validated_address: null,
				},
			},
		} )
	),
	get: jest.fn( () => Promise.resolve( { data: {} } ) ),
	create: jest.fn( function () {
		return this;
	} ),
	defaults: {
		headers: {
			common: {},
		},
	},
};

export default axios;
