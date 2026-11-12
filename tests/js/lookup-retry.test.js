/**
 * Lookup retry budget tests.
 */

import {
	MAX_LOOKUP_RETRIES,
	createLookupRetryState,
	registerFailedLookup,
	registerLookupResponse,
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

	it( 'spends the budget on a lookup_failed response', () => {
		const state = createLookupRetryState();
		const failed = { show_container: false, lookup_failed: true };

		for ( let i = 0; i < MAX_LOOKUP_RETRIES; i++ ) {
			expect(
				registerLookupResponse( state, failed, address, samePostcode )
			).toBe( true );
		}

		expect(
			registerLookupResponse( state, failed, address, samePostcode )
		).toBe( false );
	} );

	it( 'resets the budget on a response without lookup_failed', () => {
		const state = createLookupRetryState();

		for ( let i = 0; i <= MAX_LOOKUP_RETRIES; i++ ) {
			registerFailedLookup( state, address, samePostcode );
		}

		expect(
			registerLookupResponse(
				state,
				{ show_container: false },
				address,
				samePostcode
			)
		).toBe( false );
		expect( registerFailedLookup( state, address, samePostcode ) ).toBe(
			true
		);
	} );
} );
