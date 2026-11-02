/**
 * PostNL delivery-options lookup retry budget.
 *
 * The blocks container skips a lookup for an address it already fetched. A failed
 * lookup is allowed a few more attempts (one per later cart update) before that
 * address is treated as settled, so a one-off timeout does not hide PostNL's
 * options for the rest of the checkout, and a lookup that keeps failing cannot
 * loop forever.
 */

/**
 * Number of extra attempts a failed lookup gets for the same address.
 */
export const MAX_LOOKUP_RETRIES = 2;

/**
 * Create an empty retry state.
 *
 * @return {{address: Object|null, count: number}} Retry state.
 */
export const createLookupRetryState = () => ( { address: null, count: 0 } );

/**
 * Record a failed lookup and report whether the address may be fetched again.
 *
 * A failure for a different address starts a fresh budget. The state object is
 * updated in place so it can live in a React ref.
 *
 * @param {{address: Object|null, count: number}} state         Retry state.
 * @param {Object}                                address       Address whose lookup failed.
 * @param {Function}                              isSameAddress Address comparator.
 * @param {number}                                maxRetries    Retry budget per address.
 * @return {boolean} True when the address may be fetched again.
 */
export const registerFailedLookup = (
	state,
	address,
	isSameAddress,
	maxRetries = MAX_LOOKUP_RETRIES
) => {
	if ( ! isSameAddress( state.address, address ) ) {
		state.address = { ...address };
		state.count = 0;
	}

	if ( state.count >= maxRetries ) {
		return false;
	}

	state.count += 1;
	return true;
};

/**
 * Reset the retry budget after a lookup succeeds.
 *
 * @param {{address: Object|null, count: number}} state Retry state.
 */
export const resetLookupRetries = ( state ) => {
	state.address = null;
	state.count = 0;
};
