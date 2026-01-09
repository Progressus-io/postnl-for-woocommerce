/**
 * Mock for @wordpress/i18n
 */
export const __ = ( text ) => text;
export const _n = ( single, plural, number ) => ( number === 1 ? single : plural );
export const sprintf = ( format, ...args ) => {
	let result = format;
	args.forEach( ( arg, index ) => {
		result = result.replace( `%${ index + 1 }$s`, arg );
		result = result.replace( '%s', arg );
		result = result.replace( '%d', arg );
	} );
	return result;
};

export default { __, _n, sprintf };
