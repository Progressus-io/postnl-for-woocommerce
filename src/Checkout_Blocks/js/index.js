/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

// Import block definitions
import './postnl-container';
import './postnl-fill-in-with';

const render = () => {};
registerPlugin( 'postnl', {
	render,
	scope: 'woocommerce-checkout',
} );
