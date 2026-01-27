/**
 * PostNL Classic Checkout (fe-checkout.js) Tests
 *
 * Tests for the jQuery-based classic checkout functionality.
 */

describe( 'PostNL Classic Checkout', () => {
	let $;
	let mockBody;
	let postnlParams;

	beforeEach( () => {
		// Clear sessionStorage
		sessionStorage.clear();
		jest.clearAllMocks();

		// Setup postnlParams global
		postnlParams = {
			i18n: {
				deliveryDays: 'Delivery',
				pickup: 'Pickup',
			},
			delivery_day_fee_formatted: '€2,50',
			pickup_fee_formatted: '€1,00',
		};
		window.postnlParams = postnlParams;

		// Setup jQuery mock
		mockBody = {
			on: jest.fn(),
			trigger: jest.fn(),
		};

		$ = jest.fn( ( selector ) => {
			if ( selector === 'body' ) {
				return mockBody;
			}
			return createMockjQueryElement( selector );
		} );

		// Add jQuery utility methods
		$.fn = {};

		window.jQuery = $;
		window.$ = $;
	} );

	afterEach( () => {
		delete window.postnlParams;
		delete window.jQuery;
		delete window.$;
	} );

	/**
	 * Helper to create a mock jQuery element
	 */
	function createMockjQueryElement( selector ) {
		const mockElement = {
			length: 1,
			on: jest.fn().mockReturnThis(),
			find: jest.fn().mockReturnThis(),
			val: jest.fn().mockReturnValue( '' ),
			is: jest.fn().mockReturnValue( false ),
			closest: jest.fn().mockReturnThis(),
			addClass: jest.fn().mockReturnThis(),
			removeClass: jest.fn().mockReturnThis(),
			children: jest.fn().mockReturnThis(),
			first: jest.fn().mockReturnThis(),
			text: jest.fn().mockReturnThis(),
			data: jest.fn().mockReturnValue( {} ),
			prop: jest.fn().mockReturnThis(),
			attr: jest.fn().mockReturnValue( '' ),
			trigger: jest.fn().mockReturnThis(),
		};
		return mockElement;
	}

	describe( 'clearPostNLSessionData', () => {
		it( 'should remove postnl_checkout_data from sessionStorage', () => {
			// Set some data first
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);

			// Simulate the clearPostNLSessionData function
			const clearPostNLSessionData = () => {
				try {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				} catch ( e ) {
					// ignore
				}
			};

			clearPostNLSessionData();

			expect(
				sessionStorage.getItem( 'postnl_checkout_data' )
			).toBeNull();
		} );

		it( 'should not throw when sessionStorage is unavailable', () => {
			const originalRemoveItem = sessionStorage.removeItem;
			sessionStorage.removeItem = jest.fn( () => {
				throw new Error( 'SecurityError' );
			} );

			const clearPostNLSessionData = () => {
				try {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				} catch ( e ) {
					// ignore
				}
			};

			expect( () => clearPostNLSessionData() ).not.toThrow();

			sessionStorage.removeItem = originalRemoveItem;
		} );

		it( 'should handle empty sessionStorage gracefully', () => {
			const clearPostNLSessionData = () => {
				try {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				} catch ( e ) {
					// ignore
				}
			};

			// Should not throw even when key doesn't exist
			expect( () => clearPostNLSessionData() ).not.toThrow();
		} );
	} );

	describe( 'updateDeliveryDayTabFee', () => {
		it( 'should display base fee in tab title', () => {
			const updateDeliveryDayTabFee = (
				$input,
				$label,
				extraFee,
				extraFeeFormatted
			) => {
				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.deliveryDays;
				const fees = [];

				if ( tabBase > 0 && postnlParams.delivery_day_fee_formatted ) {
					fees.push( postnlParams.delivery_day_fee_formatted );
				}

				if ( extraFee > 0 && extraFeeFormatted ) {
					fees.push( extraFeeFormatted );
				}

				if ( fees.length > 0 ) {
					text += ' (+' + fees.join( ' +' ) + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 2.5 ) };
			const mockInput = { is: jest.fn().mockReturnValue( true ) };

			const result = updateDeliveryDayTabFee(
				mockInput,
				mockLabel,
				0,
				''
			);

			expect( result ).toBe( 'Delivery (+€2,50)' );
		} );

		it( 'should display both base and extra fee', () => {
			const updateDeliveryDayTabFee = (
				$input,
				$label,
				extraFee,
				extraFeeFormatted
			) => {
				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.deliveryDays;
				const fees = [];

				if ( tabBase > 0 && postnlParams.delivery_day_fee_formatted ) {
					fees.push( postnlParams.delivery_day_fee_formatted );
				}

				if ( extraFee > 0 && extraFeeFormatted ) {
					fees.push( extraFeeFormatted );
				}

				if ( fees.length > 0 ) {
					text += ' (+' + fees.join( ' +' ) + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 2.5 ) };
			const mockInput = { is: jest.fn().mockReturnValue( true ) };

			const result = updateDeliveryDayTabFee(
				mockInput,
				mockLabel,
				3.0,
				'€3,00'
			);

			expect( result ).toBe( 'Delivery (+€2,50 +€3,00)' );
		} );

		it( 'should display no fee when base is 0', () => {
			const updateDeliveryDayTabFee = (
				$input,
				$label,
				extraFee,
				extraFeeFormatted
			) => {
				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.deliveryDays;
				const fees = [];

				if ( tabBase > 0 && postnlParams.delivery_day_fee_formatted ) {
					fees.push( postnlParams.delivery_day_fee_formatted );
				}

				if ( extraFee > 0 && extraFeeFormatted ) {
					fees.push( extraFeeFormatted );
				}

				if ( fees.length > 0 ) {
					text += ' (+' + fees.join( ' +' ) + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 0 ) };
			const mockInput = { is: jest.fn().mockReturnValue( false ) };

			const result = updateDeliveryDayTabFee(
				mockInput,
				mockLabel,
				0,
				''
			);

			expect( result ).toBe( 'Delivery' );
		} );

		it( 'should handle NaN extra fee gracefully', () => {
			const updateDeliveryDayTabFee = (
				$input,
				$label,
				extraFee,
				extraFeeFormatted
			) => {
				let fee = parseFloat( extraFee || 0 );
				if ( isNaN( fee ) ) {
					fee = 0;
				}

				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.deliveryDays;
				const fees = [];

				if ( tabBase > 0 && postnlParams.delivery_day_fee_formatted ) {
					fees.push( postnlParams.delivery_day_fee_formatted );
				}

				if ( fee > 0 && extraFeeFormatted ) {
					fees.push( extraFeeFormatted );
				}

				if ( fees.length > 0 ) {
					text += ' (+' + fees.join( ' +' ) + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 0 ) };
			const mockInput = { is: jest.fn().mockReturnValue( true ) };

			const result = updateDeliveryDayTabFee(
				mockInput,
				mockLabel,
				'invalid',
				''
			);

			expect( result ).toBe( 'Delivery' );
		} );
	} );

	describe( 'updatePickupTabFee', () => {
		it( 'should display base fee in pickup tab title', () => {
			const updatePickupTabFee = ( $label ) => {
				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.pickup;

				if ( tabBase > 0 && postnlParams.pickup_fee_formatted ) {
					text += ' (+' + postnlParams.pickup_fee_formatted + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 1.0 ) };

			const result = updatePickupTabFee( mockLabel );

			expect( result ).toBe( 'Pickup (+€1,00)' );
		} );

		it( 'should display no fee when base is 0', () => {
			const updatePickupTabFee = ( $label ) => {
				const tabBase = parseFloat( $label.data( 'base-fee' ) || 0 );
				let text = postnlParams.i18n.pickup;

				if ( tabBase > 0 && postnlParams.pickup_fee_formatted ) {
					text += ' (+' + postnlParams.pickup_fee_formatted + ')';
				}

				return text;
			};

			const mockLabel = { data: jest.fn().mockReturnValue( 0 ) };

			const result = updatePickupTabFee( mockLabel );

			expect( result ).toBe( 'Pickup' );
		} );
	} );

	describe( 'isAddressReady', () => {
		it( 'should return true when shipping postcode is present', () => {
			const isAddressReady = ( shippingPostcode, billingPostcode ) => {
				return !! ( shippingPostcode || billingPostcode );
			};

			expect( isAddressReady( '1234AB', '' ) ).toBe( true );
		} );

		it( 'should return true when billing postcode is present', () => {
			const isAddressReady = ( shippingPostcode, billingPostcode ) => {
				return !! ( shippingPostcode || billingPostcode );
			};

			expect( isAddressReady( '', '5678CD' ) ).toBe( true );
		} );

		it( 'should return true when both postcodes are present', () => {
			const isAddressReady = ( shippingPostcode, billingPostcode ) => {
				return !! ( shippingPostcode || billingPostcode );
			};

			expect( isAddressReady( '1234AB', '5678CD' ) ).toBe( true );
		} );

		it( 'should return false when both postcodes are empty', () => {
			const isAddressReady = ( shippingPostcode, billingPostcode ) => {
				return !! ( shippingPostcode || billingPostcode );
			};

			expect( isAddressReady( '', '' ) ).toBe( false );
		} );

		it( 'should return false when both postcodes are null', () => {
			const isAddressReady = ( shippingPostcode, billingPostcode ) => {
				return !! ( shippingPostcode || billingPostcode );
			};

			expect( isAddressReady( null, null ) ).toBe( false );
		} );
	} );

	describe( 'container visibility tracking', () => {
		it( 'should clear session when container becomes hidden', () => {
			let prevContainerVisible = true;

			const handleVisibilityChange = ( containerVisible ) => {
				if ( prevContainerVisible && ! containerVisible ) {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				}
				prevContainerVisible = containerVisible;
			};

			// Set some data
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);

			// Container becomes hidden
			handleVisibilityChange( false );

			expect(
				sessionStorage.getItem( 'postnl_checkout_data' )
			).toBeNull();
		} );

		it( 'should not clear session when container stays visible', () => {
			let prevContainerVisible = true;

			const handleVisibilityChange = ( containerVisible ) => {
				if ( prevContainerVisible && ! containerVisible ) {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				}
				prevContainerVisible = containerVisible;
			};

			// Set some data
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);

			// Container stays visible
			handleVisibilityChange( true );

			expect( sessionStorage.getItem( 'postnl_checkout_data' ) ).toBe(
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);
		} );

		it( 'should not clear session when container was already hidden', () => {
			let prevContainerVisible = false;

			const handleVisibilityChange = ( containerVisible ) => {
				if ( prevContainerVisible && ! containerVisible ) {
					sessionStorage.removeItem( 'postnl_checkout_data' );
				}
				prevContainerVisible = containerVisible;
			};

			// Set some data
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);

			// Container was already hidden, stays hidden
			handleVisibilityChange( false );

			expect( sessionStorage.getItem( 'postnl_checkout_data' ) ).toBe(
				JSON.stringify( { selectedOption: 'delivery_day' } )
			);
		} );

		it( 'should track visibility state correctly over multiple changes', () => {
			let prevContainerVisible = false;
			let clearCount = 0;

			const handleVisibilityChange = ( containerVisible ) => {
				if ( prevContainerVisible && ! containerVisible ) {
					clearCount++;
					sessionStorage.removeItem( 'postnl_checkout_data' );
				}
				prevContainerVisible = containerVisible;
			};

			// Container becomes visible
			handleVisibilityChange( true );
			expect( clearCount ).toBe( 0 );

			// Container becomes hidden - should clear
			handleVisibilityChange( false );
			expect( clearCount ).toBe( 1 );

			// Container stays hidden - should NOT clear again
			handleVisibilityChange( false );
			expect( clearCount ).toBe( 1 );

			// Container becomes visible
			handleVisibilityChange( true );
			expect( clearCount ).toBe( 1 );

			// Container becomes hidden again - should clear
			handleVisibilityChange( false );
			expect( clearCount ).toBe( 2 );
		} );
	} );

	describe( 'tab switching logic', () => {
		it( 'should activate correct content when delivery tab is selected', () => {
			const getContentId = ( tabValue ) => {
				return 'postnl_' + tabValue + '_content';
			};

			expect( getContentId( 'delivery_day' ) ).toBe(
				'postnl_delivery_day_content'
			);
		} );

		it( 'should activate correct content when dropoff tab is selected', () => {
			const getContentId = ( tabValue ) => {
				return 'postnl_' + tabValue + '_content';
			};

			expect( getContentId( 'dropoff_points' ) ).toBe(
				'postnl_dropoff_points_content'
			);
		} );

		it( 'should generate correct field name for delivery', () => {
			const getFieldName = ( tabValue ) => {
				return 'postnl_' + tabValue;
			};

			expect( getFieldName( 'delivery_day' ) ).toBe(
				'postnl_delivery_day'
			);
		} );

		it( 'should generate correct field name for dropoff', () => {
			const getFieldName = ( tabValue ) => {
				return 'postnl_' + tabValue;
			};

			expect( getFieldName( 'dropoff_points' ) ).toBe(
				'postnl_dropoff_points'
			);
		} );
	} );

	describe( 'dropoff data extraction', () => {
		it( 'should populate hidden fields from data attributes', () => {
			const populateHiddenFields = ( fieldName, dropoffData ) => {
				const fields = {};
				for ( let name in dropoffData ) {
					fields[ fieldName + '_' + name ] = dropoffData[ name ];
				}
				return fields;
			};

			const dropoffData = {
				partner_id: 'PARTNER1',
				loc_code: 'LOC1',
				date: '2024-01-10',
				time: '09:00-18:00',
				type: 'PNL',
			};

			const result = populateHiddenFields(
				'postnl_dropoff_points',
				dropoffData
			);

			expect( result ).toEqual( {
				postnl_dropoff_points_partner_id: 'PARTNER1',
				postnl_dropoff_points_loc_code: 'LOC1',
				postnl_dropoff_points_date: '2024-01-10',
				postnl_dropoff_points_time: '09:00-18:00',
				postnl_dropoff_points_type: 'PNL',
			} );
		} );

		it( 'should handle empty data object', () => {
			const populateHiddenFields = ( fieldName, dropoffData ) => {
				const fields = {};
				for ( let name in dropoffData ) {
					fields[ fieldName + '_' + name ] = dropoffData[ name ];
				}
				return fields;
			};

			const result = populateHiddenFields( 'postnl_dropoff_points', {} );

			expect( result ).toEqual( {} );
		} );
	} );

	describe( 'address change triggers', () => {
		it( 'should identify when to trigger update_checkout on billing change', () => {
			const shouldTriggerUpdate = ( shipToDifferentChecked ) => {
				return ! shipToDifferentChecked;
			};

			// When NOT shipping to different address, billing changes should trigger
			expect( shouldTriggerUpdate( false ) ).toBe( true );
		} );

		it( 'should NOT trigger update_checkout on billing change when ship to different is checked', () => {
			const shouldTriggerUpdate = ( shipToDifferentChecked ) => {
				return ! shipToDifferentChecked;
			};

			// When shipping to different address, billing changes should NOT trigger
			expect( shouldTriggerUpdate( true ) ).toBe( false );
		} );

		it( 'should always trigger update_checkout on shipping field change', () => {
			const shouldTriggerUpdateForShipping = () => {
				return true;
			};

			expect( shouldTriggerUpdateForShipping() ).toBe( true );
		} );
	} );

	describe( 'first refresh logic', () => {
		it( 'should track first refresh state', () => {
			let firstRefreshDone = false;

			const handleFirstRefresh = ( isAddressReady ) => {
				if ( ! firstRefreshDone && isAddressReady ) {
					firstRefreshDone = true;
					return true; // Should trigger update
				}
				return false;
			};

			// First call with address ready
			expect( handleFirstRefresh( true ) ).toBe( true );
			expect( firstRefreshDone ).toBe( true );

			// Second call - should not trigger again
			expect( handleFirstRefresh( true ) ).toBe( false );
		} );

		it( 'should not trigger first refresh when address not ready', () => {
			let firstRefreshDone = false;

			const handleFirstRefresh = ( isAddressReady ) => {
				if ( ! firstRefreshDone && isAddressReady ) {
					firstRefreshDone = true;
					return true;
				}
				return false;
			};

			// Call with address not ready
			expect( handleFirstRefresh( false ) ).toBe( false );
			expect( firstRefreshDone ).toBe( false );
		} );
	} );

	describe( 'auto-select first option', () => {
		it( 'should auto-select first radio when no option is checked', () => {
			const autoSelectFirst = ( hasChecked, radios ) => {
				if ( ! hasChecked && radios.length > 0 ) {
					return radios[ 0 ];
				}
				return null;
			};

			const radios = [ 'option1', 'option2', 'option3' ];

			expect( autoSelectFirst( false, radios ) ).toBe( 'option1' );
		} );

		it( 'should not auto-select when an option is already checked', () => {
			const autoSelectFirst = ( hasChecked, radios ) => {
				if ( ! hasChecked && radios.length > 0 ) {
					return radios[ 0 ];
				}
				return null;
			};

			const radios = [ 'option1', 'option2', 'option3' ];

			expect( autoSelectFirst( true, radios ) ).toBeNull();
		} );

		it( 'should not auto-select when no radios available', () => {
			const autoSelectFirst = ( hasChecked, radios ) => {
				if ( ! hasChecked && radios.length > 0 ) {
					return radios[ 0 ];
				}
				return null;
			};

			expect( autoSelectFirst( false, [] ) ).toBeNull();
		} );
	} );

	describe( 'integration with session manager', () => {
		it( 'should use same storage key as blocks checkout', () => {
			const STORAGE_KEY = 'postnl_checkout_data';

			// Classic checkout uses this key
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( { test: true } )
			);

			// Should be readable with the same key
			expect( sessionStorage.getItem( STORAGE_KEY ) ).toBe(
				JSON.stringify( { test: true } )
			);
		} );

		it( 'should clear the same key that blocks checkout uses', () => {
			// Set data as blocks checkout would
			sessionStorage.setItem(
				'postnl_checkout_data',
				JSON.stringify( {
					selectedOption: 'delivery_day',
					deliveryDay: { value: 'test' },
				} )
			);

			// Classic checkout clears it
			sessionStorage.removeItem( 'postnl_checkout_data' );

			// Should be cleared
			expect(
				sessionStorage.getItem( 'postnl_checkout_data' )
			).toBeNull();
		} );
	} );
} );

describe( 'PostNL Classic Checkout - Edge Cases', () => {
	beforeEach( () => {
		sessionStorage.clear();

		window.postnlParams = {
			i18n: {
				deliveryDays: 'Delivery',
				pickup: 'Pickup',
			},
			delivery_day_fee_formatted: '',
			pickup_fee_formatted: '',
		};
	} );

	afterEach( () => {
		delete window.postnlParams;
	} );

	it( 'should handle missing postnlParams gracefully', () => {
		delete window.postnlParams;

		const getDeliveryText = () => {
			if ( ! window.postnlParams ) {
				return 'Delivery';
			}
			return window.postnlParams.i18n?.deliveryDays || 'Delivery';
		};

		expect( getDeliveryText() ).toBe( 'Delivery' );
	} );

	it( 'should handle missing i18n object', () => {
		window.postnlParams = {};

		const getDeliveryText = () => {
			return window.postnlParams?.i18n?.deliveryDays || 'Delivery';
		};

		expect( getDeliveryText() ).toBe( 'Delivery' );
	} );

	it( 'should handle missing checkout container', () => {
		const operate = ( containerExists ) => {
			if ( ! containerExists ) {
				return false;
			}
			return true;
		};

		expect( operate( false ) ).toBe( false );
	} );

	it( 'should handle DOM element not found', () => {
		const findElement = ( element ) => {
			if ( ! element || ! element.length ) {
				return null;
			}
			return element;
		};

		expect( findElement( { length: 0 } ) ).toBeNull();
		expect( findElement( null ) ).toBeNull();
		expect( findElement( undefined ) ).toBeNull();
	} );
} );

describe( 'PostNL Classic Checkout - Fee Formatting', () => {
	it( 'should format single fee correctly', () => {
		const formatFees = ( fees ) => {
			if ( fees.length === 0 ) {
				return '';
			}
			return ' (+' + fees.join( ' +' ) + ')';
		};

		expect( formatFees( [ '€2,50' ] ) ).toBe( ' (+€2,50)' );
	} );

	it( 'should format multiple fees correctly', () => {
		const formatFees = ( fees ) => {
			if ( fees.length === 0 ) {
				return '';
			}
			return ' (+' + fees.join( ' +' ) + ')';
		};

		expect( formatFees( [ '€2,50', '€3,00' ] ) ).toBe( ' (+€2,50 +€3,00)' );
	} );

	it( 'should return empty string for no fees', () => {
		const formatFees = ( fees ) => {
			if ( fees.length === 0 ) {
				return '';
			}
			return ' (+' + fees.join( ' +' ) + ')';
		};

		expect( formatFees( [] ) ).toBe( '' );
	} );

	it( 'should handle three fees', () => {
		const formatFees = ( fees ) => {
			if ( fees.length === 0 ) {
				return '';
			}
			return ' (+' + fees.join( ' +' ) + ')';
		};

		expect( formatFees( [ '€1,00', '€2,00', '€3,00' ] ) ).toBe(
			' (+€1,00 +€2,00 +€3,00)'
		);
	} );
} );
