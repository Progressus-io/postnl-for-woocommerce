/**
 * External dependencies
 */
import { registerPlugin } from '@wordpress/plugins';

// Import block definitions
import './postnl-container';

const render = () => {};
registerPlugin( 'postnl', {
	render,
	scope: 'woocommerce-checkout',
} );
