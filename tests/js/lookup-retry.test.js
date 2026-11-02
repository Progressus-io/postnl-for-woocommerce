/**
 * Lookup retry budget tests.
 */

import {
	MAX_LOOKUP_RETRIES,
	createLookupRetryState,
	registerFailedLookup,
	resetLookupRetries,
} from '../../client/utils/lookup-retry';

/**
 * Compare two addresses on postcode only.
 *
 * @param {Object|null} a First address.
 * @param {Object|null} b Second address.
 * @return {boolean} Whether both are set and share a postcode.
 */
const samePostcode = ( a, b ) => !! a && !! b && a.postcode === b.postcode;

describe( 'lookup retry budget', () => {
	const address = { country: 'NL', postcode: '1012AB' };

	it( 'allows a failed address to be retried up to the limit', () => {
		const state = createLookupRetryState();

		for ( let i = 0; i < MAX_LOOKUP_RETRIES; i++ ) {
			expect( registerFailedLookup( state, address, samePostcode ) ).toBe(
				true
			);
		}

		expect( registerFailedLookup( state, address, samePostcode ) ).toBe(
			false
		);
	} );

	it( 'starts a fresh budget for a different address', () => {
		const state = createLookupRetryState();

		for ( let i = 0; i <= MAX_LOOKUP_RETRIES; i++ ) {
			registerFailedLookup( state, address, samePostcode );
		}

		expect(
			registerFailedLookup(
				state,
				{ ...address, postcode: '2595AK' },
				samePostcode
			)
		).toBe( true );
	} );

	it( 'restores the budget after a successful lookup', () => {
		const state = createLookupRetryState();

		for ( let i = 0; i <= MAX_LOOKUP_RETRIES; i++ ) {
			registerFailedLookup( state, address, samePostcode );
		}
		resetLookupRetries( state );

		expect( registerFailedLookup( state, address, samePostcode ) ).toBe(
			true
		);
	} );
} );
