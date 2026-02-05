/**
 * Babel Configuration
 *
 * Used for Jest tests and build process.
 */
module.exports = function ( api ) {
	api.cache( true );

	return {
		presets: [ '@wordpress/babel-preset-default' ],
	};
};
