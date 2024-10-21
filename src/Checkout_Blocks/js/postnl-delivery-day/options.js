/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const options = [
	{
		label: __('Try again another day', 'postnl-for-woocommerce'),
		value: 'try-again',
	},
	{
		label: __( 'Leave with neighbour', 'postnl-for-woocommerce' ),
		value: 'leave-with-neighbour',
	},
	{
		label: __( 'Leave in shed', 'postnl-for-woocommerce' ),
		value: 'leave-in-shed',
	},
	{
		label: __( 'Other', 'postnl-for-woocommerce' ),
		value: 'other',
	},
	/**
	 * [frontend-step-01]
	 * 📝 Add more options using the same format as above. Ensure one option has the key "other".
	 */
];
