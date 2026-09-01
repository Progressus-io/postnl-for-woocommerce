/**
 * Shipping Method Helper Tests
 *
 * Tests for deciding whether the chosen shipping method is linked to PostNL.
 */

import {
	getMethodIdFromRateId,
	isSupportedShippingMethod,
} from '../../client/utils/shipping-method-helper';

describe( 'Shipping Method Helper', () => {
	describe( 'getMethodIdFromRateId', () => {
		it( 'extracts the method id from a rate id', () => {
			expect( getMethodIdFromRateId( 'flat_rate:3' ) ).toBe(
				'flat_rate'
			);
			expect( getMethodIdFromRateId( 'postnl:1' ) ).toBe( 'postnl' );
		} );

		it( 'returns the whole value when there is no instance id', () => {
			expect( getMethodIdFromRateId( 'free_shipping' ) ).toBe(
				'free_shipping'
			);
		} );

		it( 'returns an empty string for invalid input', () => {
			expect( getMethodIdFromRateId( '' ) ).toBe( '' );
			expect( getMethodIdFromRateId( null ) ).toBe( '' );
			expect( getMethodIdFromRateId( undefined ) ).toBe( '' );
		} );
	} );

	describe( 'isSupportedShippingMethod', () => {
		const supported = [ 'flat_rate', 'postnl' ];

		it( 'returns true for a linked method', () => {
			expect(
				isSupportedShippingMethod( 'flat_rate:3', supported )
			).toBe( true );
			expect( isSupportedShippingMethod( 'postnl:1', supported ) ).toBe(
				true
			);
		} );

		it( 'returns false for a method that is not linked', () => {
			expect(
				isSupportedShippingMethod( 'fakecarrier:2', supported )
			).toBe( false );
		} );

		it( 'does not suppress the widget when no rate is chosen yet', () => {
			expect( isSupportedShippingMethod( '', supported ) ).toBe( true );
		} );

		it( 'does not suppress the widget when nothing is configured', () => {
			expect( isSupportedShippingMethod( 'fakecarrier:2', [] ) ).toBe(
				true
			);
			expect( isSupportedShippingMethod( 'fakecarrier:2' ) ).toBe( true );
		} );
	} );
} );
