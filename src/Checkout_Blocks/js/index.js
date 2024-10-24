/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

// Import block definitions
import './postnl-delivery-day';
import './postnl-dropoff-points';

const render = () => {};

registerPlugin('postnl', {
	render,
	scope: 'woocommerce-checkout',
});
