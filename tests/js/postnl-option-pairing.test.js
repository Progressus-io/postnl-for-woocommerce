/**
 * Tests for the option pairing rule engine.
 */
const pairing = require( '../../assets/js/postnl-option-pairing.js' );

const NL_NL_DELIVERY_DAY = [
	[],
	[ 'delivery_code_at_door', 'insured_shipping' ],
	[ 'only_home_address' ],
	[ 'return_no_answer' ],
	[ 'signature_on_delivery' ],
	[ 'return_no_answer', 'only_home_address' ],
	[ 'signature_on_delivery', 'insured_shipping', 'return_no_answer' ],
	[ 'signature_on_delivery', 'only_home_address' ],
	[ 'insured_shipping', 'signature_on_delivery' ],
	[ 'signature_on_delivery', 'return_no_answer' ],
	[ 'signature_on_delivery', 'only_home_address', 'return_no_answer' ],
	[ 'letterbox' ],
	[ 'id_check' ],
	[ 'id_check', 'signature_on_delivery' ],
	[ 'id_check', 'only_home_address' ],
	[ 'id_check', 'only_home_address', 'signature_on_delivery' ],
	[ 'id_check', 'insured_shipping' ],
	[ 'id_check', 'insured_shipping', 'signature_on_delivery' ],
	[ 'id_check', 'insured_shipping', 'only_home_address' ],
	[ 'id_check', 'insured_shipping', 'only_home_address', 'signature_on_delivery' ],
];

const ALL_FLAGS = [
	'id_check',
	'insured_shipping',
	'return_no_answer',
	'signature_on_delivery',
	'only_home_address',
	'letterbox',
	'delivery_code_at_door',
];

describe( 'postnl-option-pairing', () => {
	describe( 'evaluate (NL→NL delivery_day)', () => {
		it( 'enables every flag when nothing is selected', () => {
			const result = pairing.evaluate( [], ALL_FLAGS, NL_NL_DELIVERY_DAY );
			expect( Object.keys( result.disabled ) ).toEqual( [] );
			expect( result.missing ).toEqual( [] );
			expect( result.isComplete ).toBe( true );
		} );

		it( 'disables incompatible flags once "letterbox" is selected', () => {
			const result = pairing.evaluate(
				[ 'letterbox' ],
				ALL_FLAGS,
				NL_NL_DELIVERY_DAY
			);
			expect( result.disabled ).toHaveProperty( 'insured_shipping' );
			expect( result.disabled ).toHaveProperty( 'signature_on_delivery' );
			expect( result.disabled.insured_shipping ).toContain( 'letterbox' );
		} );

		it( 'flags an invalid selection that has no allowed superset', () => {
			const result = pairing.evaluate(
				[ 'letterbox', 'signature_on_delivery' ],
				ALL_FLAGS,
				NL_NL_DELIVERY_DAY
			);
			expect( result.isComplete ).toBe( false );
			expect( result.missing ).toEqual( [] );
		} );

		it( 'recognises an exact valid combination as complete', () => {
			const result = pairing.evaluate(
				[ 'signature_on_delivery', 'insured_shipping' ],
				ALL_FLAGS,
				NL_NL_DELIVERY_DAY
			);
			expect( result.isComplete ).toBe( true );
			expect( result.missing ).toEqual( [] );
		} );

		it( 'surfaces a required companion when selection is a strict prefix', () => {
			const result = pairing.evaluate(
				[ 'delivery_code_at_door' ],
				ALL_FLAGS,
				NL_NL_DELIVERY_DAY
			);
			expect( result.isComplete ).toBe( false );
			expect( result.missing ).toEqual( [ 'insured_shipping' ] );
		} );
	} );

	describe( 'canEnable', () => {
		it( 'returns true when adding the candidate stays within a superset', () => {
			expect(
				pairing.canEnable( [], 'signature_on_delivery', NL_NL_DELIVERY_DAY )
			).toBe( true );
		} );

		it( 'returns false when no allowed combination contains the candidate plus current selection', () => {
			expect(
				pairing.canEnable(
					[ 'letterbox' ],
					'signature_on_delivery',
					NL_NL_DELIVERY_DAY
				)
			).toBe( false );
		} );
	} );

	describe( 'getBlockers', () => {
		it( 'returns the selected flags that prevent the candidate', () => {
			const blockers = pairing.getBlockers(
				[ 'letterbox' ],
				'insured_shipping',
				NL_NL_DELIVERY_DAY
			);
			expect( blockers ).toEqual( [ 'letterbox' ] );
		} );

		it( 'returns all selected flags when the candidate appears in no combination', () => {
			const blockers = pairing.getBlockers(
				[ 'insured_shipping' ],
				'unknown_flag',
				NL_NL_DELIVERY_DAY
			);
			expect( blockers ).toEqual( [ 'insured_shipping' ] );
		} );
	} );

	describe( 'getRequiredCompanions', () => {
		it( 'returns the missing flags that complete the smallest superset', () => {
			expect(
				pairing.getRequiredCompanions(
					[ 'delivery_code_at_door' ],
					NL_NL_DELIVERY_DAY
				)
			).toEqual( [ 'insured_shipping' ] );
		} );

		it( 'returns an empty array when the selection is already an exact match', () => {
			expect(
				pairing.getRequiredCompanions(
					[ 'only_home_address' ],
					NL_NL_DELIVERY_DAY
				)
			).toEqual( [] );
		} );

		it( 'returns an empty array when no superset exists', () => {
			expect(
				pairing.getRequiredCompanions(
					[ 'letterbox', 'signature_on_delivery' ],
					NL_NL_DELIVERY_DAY
				)
			).toEqual( [] );
		} );
	} );
} );
