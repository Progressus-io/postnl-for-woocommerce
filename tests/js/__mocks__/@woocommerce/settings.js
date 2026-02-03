/**
 * Mock for @woocommerce/settings
 */
export const getSetting = jest.fn( ( key, defaultValue = {} ) => {
	const settings = {
		'postnl-for-woocommerce-blocks_data': {
			letterbox: false,
			delivery_day_fee: 0,
			delivery_day_fee_formatted: '',
			pickup_fee: 0,
			pickup_fee_formatted: '',
			is_pickup_points_enabled: true,
			is_nl_address_enabled: true,
			ajax_url: '/wp-admin/admin-ajax.php',
			nonce: 'test-nonce',
		},
	};
	return settings[ key ] || defaultValue;
} );

export default { getSetting };
