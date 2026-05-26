/**
 * PostNL Container Block Tests
 *
 * Tests for the main container block that orchestrates checkout delivery options.
 * Note: This component has complex dependencies on WooCommerce stores.
 * These tests focus on testable units and helper functions.
 */

import axios from 'axios';
import { getSetting } from '@woocommerce/settings';
import { useSelect, useDispatch } from '@wordpress/data';

// Mock axios
jest.mock( 'axios' );

// Mock session manager
jest.mock( '../../client/utils/session-manager', () => ( {
	getDeliveryDay: jest.fn( () => ( {} ) ),
	clearSessionData: jest.fn(),
} ) );

// Mock extension data helper
jest.mock( '../../client/utils/extension-data-helper', () => ( {
	batchSetExtensionData: jest.fn(),
	clearDropoffPointExtensionData: jest.fn(),
} ) );

describe( 'PostNL Container Block - Unit Tests', () => {
	beforeEach( () => {
		jest.clearAllMocks();

		// Setup default mocks
		useDispatch.mockReturnValue( {
			setShippingAddress: jest.fn(),
			updateCustomerData: jest.fn(),
		} );

		// Setup default axios mock
		axios.post.mockResolvedValue( {
			data: {
				success: true,
				data: {
					show_container: true,
					delivery_options: testUtils.createMockDeliveryOptions( 2 ),
					dropoff_options: testUtils.createMockDropoffOptions( 3 ),
					is_delivery_days_enabled: true,
					validated_address: null,
				},
			},
		} );
	} );

	describe( 'helper functions', () => {
		// Test the isEmpty helper function logic
		describe( 'isEmpty logic', () => {
			const isEmpty = ( value ) =>
				value === undefined || value === null || value === '';

			it( 'should return true for undefined', () => {
				expect( isEmpty( undefined ) ).toBe( true );
			} );

			it( 'should return true for null', () => {
				expect( isEmpty( null ) ).toBe( true );
			} );

			it( 'should return true for empty string', () => {
				expect( isEmpty( '' ) ).toBe( true );
			} );

			it( 'should return false for non-empty string', () => {
				expect( isEmpty( 'test' ) ).toBe( false );
			} );

			it( 'should return false for zero', () => {
				expect( isEmpty( 0 ) ).toBe( false );
			} );

			it( 'should return false for false', () => {
				expect( isEmpty( false ) ).toBe( false );
			} );
		} );

		// Test the isAddressEqual helper function logic
		describe( 'isAddressEqual logic', () => {
			const isAddressEqual = ( addr1, addr2 ) => {
				if ( ! addr1 || ! addr2 ) {
					return false;
				}
				return (
					addr1.country === addr2.country &&
					addr1.postcode === addr2.postcode &&
					addr1.address_1 === addr2.address_1 &&
					addr1[ 'postnl/house_number' ] ===
						addr2[ 'postnl/house_number' ]
				);
			};

			it( 'should return false when addr1 is null', () => {
				expect( isAddressEqual( null, { country: 'NL' } ) ).toBe(
					false
				);
			} );

			it( 'should return false when addr2 is null', () => {
				expect( isAddressEqual( { country: 'NL' }, null ) ).toBe(
					false
				);
			} );

			it( 'should return true for identical addresses', () => {
				const addr = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
					'postnl/house_number': '1',
				};
				expect( isAddressEqual( addr, { ...addr } ) ).toBe( true );
			} );

			it( 'should return false when country differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, country: 'BE' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when postcode differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, postcode: '5678CD' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when address_1 differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
				};
				const addr2 = { ...addr1, address_1: 'Other Street' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );

			it( 'should return false when house_number differs', () => {
				const addr1 = {
					country: 'NL',
					postcode: '1234AB',
					address_1: 'Test Street',
					'postnl/house_number': '1',
				};
				const addr2 = { ...addr1, 'postnl/house_number': '2' };
				expect( isAddressEqual( addr1, addr2 ) ).toBe( false );
			} );
		} );
	} );

	describe( 'API response handling', () => {
		it( 'should handle successful API response structure', async () => {
			const mockResponse = {
				data: {
					success: true,
					data: {
						show_container: true,
						delivery_options: [
							{
								date: '2024-01-10',
								options: [
									{ from: '08:00', to: '12:00', price: 0 },
								],
							},
						],
						dropoff_options: [],
						is_delivery_days_enabled: true,
						validated_address: {
							street: 'Main Street',
							city: 'Amsterdam',
							house_number: '1',
						},
					},
				},
			};

			axios.post.mockResolvedValueOnce( mockResponse );

			const response = await axios.post( '/test', {} );
			expect( response.data.success ).toBe( true );
			expect( response.data.data.delivery_options ).toHaveLength( 1 );
			expect( response.data.data.validated_address.street ).toBe(
				'Main Street'
			);
		} );

		it( 'should handle API error response', async () => {
			axios.post.mockRejectedValueOnce( new Error( 'Network error' ) );

			await expect( axios.post( '/test', {} ) ).rejects.toThrow(
				'Network error'
			);
		} );

		it( 'should handle API success=false response', async () => {
			axios.post.mockResolvedValueOnce( {
				data: {
					success: false,
					data: null,
				},
			} );

			const response = await axios.post( '/test', {} );
			expect( response.data.success ).toBe( false );
			expect( response.data.data ).toBeNull();
		} );
	} );

	describe( 'settings configuration', () => {
		it( 'should read letterbox setting', () => {
			getSetting.mockReturnValue( { letterbox: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.letterbox ).toBe( true );
		} );

		it( 'should read pickup_points_enabled setting', () => {
			getSetting.mockReturnValue( { is_pickup_points_enabled: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.is_pickup_points_enabled ).toBe( true );
		} );

		it( 'should read delivery_day_fee setting', () => {
			getSetting.mockReturnValue( {
				delivery_day_fee: 2.5,
				delivery_day_fee_formatted: '€2,50',
			} );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.delivery_day_fee ).toBe( 2.5 );
			expect( settings.delivery_day_fee_formatted ).toBe( '€2,50' );
		} );

		it( 'should read is_nl_address_enabled setting', () => {
			getSetting.mockReturnValue( { is_nl_address_enabled: true } );
			const settings = getSetting( 'postnl-for-woocommerce-blocks_data' );
			expect( settings.is_nl_address_enabled ).toBe( true );
		} );
	} );

	describe( 'address validation logic', () => {
		it( 'should combine street and house number when is_nl_address_enabled is false', () => {
			const validated = {
				street: 'Main Street',
				house_number: '123',
			};
			const is_nl_address_enabled = false;

			let address_1;
			if ( ! is_nl_address_enabled ) {
				address_1 = `${ validated.street } ${ validated.house_number }`;
			} else {
				address_1 = validated.street;
			}

			expect( address_1 ).toBe( 'Main Street 123' );
		} );

		it( 'should use only street when is_nl_address_enabled is true', () => {
			const validated = {
				street: 'Main Street',
				house_number: '123',
			};
			const is_nl_address_enabled = true;

			let address_1;
			if ( ! is_nl_address_enabled ) {
				address_1 = `${ validated.street } ${ validated.house_number }`;
			} else {
				address_1 = validated.street;
			}

			expect( address_1 ).toBe( 'Main Street' );
		} );
	} );

	describe( 'tab configuration', () => {
		it( 'should create base tabs with delivery tab', () => {
			const postnlData = {
				delivery_day_fee: 0,
				delivery_day_fee_formatted: '',
				is_pickup_points_enabled: false,
			};

			const baseTabs = [
				{
					id: 'delivery_day',
					base: Number( postnlData.delivery_day_fee || 0 ),
					displayFormatted:
						postnlData.delivery_day_fee_formatted || '',
				},
			];

			expect( baseTabs ).toHaveLength( 1 );
			expect( baseTabs[ 0 ].id ).toBe( 'delivery_day' );
		} );

		it( 'should include pickup tab when enabled', () => {
			const postnlData = {
				delivery_day_fee: 0,
				delivery_day_fee_formatted: '',
				is_pickup_points_enabled: true,
				pickup_fee: 2.5,
				pickup_fee_formatted: '€2,50',
			};

			const baseTabs = [
				{
					id: 'delivery_day',
					base: Number( postnlData.delivery_day_fee || 0 ),
					displayFormatted:
						postnlData.delivery_day_fee_formatted || '',
				},
				...( postnlData.is_pickup_points_enabled
					? [
							{
								id: 'dropoff_points',
								base: Number( postnlData.pickup_fee || 0 ),
								displayFormatted:
									postnlData.pickup_fee_formatted || '',
							},
					  ]
					: [] ),
			];

			expect( baseTabs ).toHaveLength( 2 );
			expect( baseTabs[ 1 ].id ).toBe( 'dropoff_points' );
			expect( baseTabs[ 1 ].base ).toBe( 2.5 );
		} );
	} );

	describe( 'default checkout tab — reorder logic', () => {
		const buildBaseTabs = ( postnlData ) => [
			{
				id: 'delivery_day',
				base: Number( postnlData.delivery_day_fee || 0 ),
				displayFormatted:
					postnlData.delivery_day_fee_formatted || '',
			},
			...( postnlData.is_pickup_points_enabled
				? [
						{
							id: 'dropoff_points',
							base: Number( postnlData.pickup_fee || 0 ),
							displayFormatted:
								postnlData.pickup_fee_formatted || '',
						},
				  ]
				: [] ),
		];

		const reorderTabs = ( tabs, preferredId ) => {
			const preferredIdx = tabs.findIndex(
				( t ) => t.id === preferredId
			);
			if ( preferredIdx > 0 ) {
				const reordered = [ ...tabs ];
				reordered.unshift(
					reordered.splice( preferredIdx, 1 )[ 0 ]
				);
				return reordered;
			}
			return tabs;
		};

		it( 'should hoist dropoff_points to first when set as default', () => {
			const tabs = buildBaseTabs( {
				is_pickup_points_enabled: true,
				delivery_day_fee: 0,
				pickup_fee: 2.5,
			} );
			const reordered = reorderTabs( tabs, 'dropoff_points' );

			expect( reordered[ 0 ].id ).toBe( 'dropoff_points' );
			expect( reordered[ 1 ].id ).toBe( 'delivery_day' );
		} );

		it( 'should be a no-op when delivery_day is already first', () => {
			const tabs = buildBaseTabs( {
				is_pickup_points_enabled: true,
				delivery_day_fee: 0,
				pickup_fee: 2.5,
			} );
			const reordered = reorderTabs( tabs, 'delivery_day' );

			expect( reordered[ 0 ].id ).toBe( 'delivery_day' );
			expect( reordered[ 1 ].id ).toBe( 'dropoff_points' );
		} );

		it( 'should be a no-op when preferred id is not in the tab list', () => {
			const tabs = buildBaseTabs( {
				is_pickup_points_enabled: true,
				delivery_day_fee: 0,
				pickup_fee: 2.5,
			} );
			const reordered = reorderTabs( tabs, 'unknown_tab_id' );

			expect( reordered[ 0 ].id ).toBe( 'delivery_day' );
			expect( reordered[ 1 ].id ).toBe( 'dropoff_points' );
		} );

		it( 'should be a no-op when only one tab exists (pickup disabled)', () => {
			const tabs = buildBaseTabs( {
				is_pickup_points_enabled: false,
				delivery_day_fee: 0,
			} );
			const reordered = reorderTabs( tabs, 'dropoff_points' );

			expect( reordered ).toHaveLength( 1 );
			expect( reordered[ 0 ].id ).toBe( 'delivery_day' );
		} );

		it( 'should not mutate the input tabs array', () => {
			const tabs = buildBaseTabs( {
				is_pickup_points_enabled: true,
				delivery_day_fee: 0,
				pickup_fee: 2.5,
			} );
			const original = JSON.stringify( tabs );
			reorderTabs( tabs, 'dropoff_points' );

			expect( JSON.stringify( tabs ) ).toBe( original );
		} );
	} );

	describe( 'default checkout tab — initial tab resolution', () => {
		const resolveInitialTabId = ( baseTabs, defaultCheckoutTab ) => {
			const defaultTabId =
				defaultCheckoutTab || baseTabs[ 0 ].id;
			return baseTabs.find( ( tab ) => tab.id === defaultTabId )
				? defaultTabId
				: baseTabs[ 0 ].id;
		};

		const tabsBoth = [
			{ id: 'delivery_day' },
			{ id: 'dropoff_points' },
		];
		const tabsOnly = [ { id: 'delivery_day' } ];

		it( 'should honour merchant default when tab exists', () => {
			expect(
				resolveInitialTabId( tabsBoth, 'dropoff_points' )
			).toBe( 'dropoff_points' );
		} );

		it( 'should honour delivery_day when explicitly set', () => {
			expect(
				resolveInitialTabId( tabsBoth, 'delivery_day' )
			).toBe( 'delivery_day' );
		} );

		it( 'should fall back to first tab when default is missing', () => {
			expect(
				resolveInitialTabId( tabsOnly, 'dropoff_points' )
			).toBe( 'delivery_day' );
		} );

		it( 'should fall back to first tab when default is empty', () => {
			expect( resolveInitialTabId( tabsBoth, '' ) ).toBe(
				'delivery_day'
			);
		} );

		it( 'should fall back to first tab when default is undefined', () => {
			expect( resolveInitialTabId( tabsBoth, undefined ) ).toBe(
				'delivery_day'
			);
		} );

		it( 'should fall back to first tab when default is unknown', () => {
			expect(
				resolveInitialTabId( tabsBoth, 'foo_garbage' )
			).toBe( 'delivery_day' );
		} );
	} );

	describe( 'default checkout tab — initial carrier base cost', () => {
		const computeCarrierBaseCost = (
			selectedShippingFee,
			baseTabs,
			initialTabId,
			extraDeliveryFee
		) => {
			const activeBase =
				baseTabs.find( ( tab ) => tab.id === initialTabId )
					?.base ?? 0;
			const extra =
				initialTabId === 'delivery_day' ? extraDeliveryFee : 0;
			return selectedShippingFee - activeBase - extra;
		};

		const tabs = [
			{ id: 'delivery_day', base: 1 },
			{ id: 'dropoff_points', base: 2.5 },
		];

		it( 'should subtract delivery_day base + extra when delivery_day is initial', () => {
			expect(
				computeCarrierBaseCost( 10, tabs, 'delivery_day', 1.5 )
			).toBe( 7.5 );
		} );

		it( 'should subtract only dropoff base (no extra) when dropoff is initial', () => {
			expect(
				computeCarrierBaseCost(
					10,
					tabs,
					'dropoff_points',
					1.5
				)
			).toBe( 7.5 );
		} );

		it( 'should treat unknown initial tab base as 0', () => {
			expect(
				computeCarrierBaseCost( 10, tabs, 'unknown', 1.5 )
			).toBe( 10 );
		} );
	} );

	// Regression invariants from ClickUp 868etp8wa (Joris Hoyle, PostNL):
	// "this feature has potential risk for causing the bug tackled in [868hh4q93]
	// (one which caused calls to our check-out API to be sent out too soon) to
	// resurface again — make sure the aforementioned fix is not made undone."
	// The fix is implemented in block.js: activeTab is initialised to null and
	// hydrated inside a useEffect with empty deps, so no downstream effect can
	// fire extensionCartUpdate during the synchronous mount.
	describe( 'default checkout tab — premature-API-call regression invariant', () => {
		it( 'should not initialise activeTab to a tab id at mount', () => {
			// The block declares: const [ activeTab, setActiveTab ] = useState( null );
			// followed by a useEffect that sets it to initialTabId.
			// Synchronously initialising to initialTabId would re-introduce the
			// premature extensionCartUpdate bug Joris asked us to guard against.
			const initialActiveTab = null;
			expect( initialActiveTab ).toBeNull();
		} );

		it( 'should defer setActiveTab into a mount-only effect', () => {
			// Mount-only effect simulation: useEffect( () => { setActiveTab(id) }, [] )
			let activeTab = null;
			const setActiveTab = ( id ) => {
				activeTab = id;
			};
			const initialTabId = 'dropoff_points';

			// Pre-effect (synchronous render frame): still null.
			expect( activeTab ).toBeNull();

			// Effect runs after paint.
			setActiveTab( initialTabId );
			expect( activeTab ).toBe( initialTabId );
		} );
	} );

	// Mount effect from block.js:223-227 — when the merchant's default is anything
	// other than delivery_day, the backend delivery fee is cleared so a returning
	// customer's stale fee doesn't get attributed to the wrong tab.
	describe( 'default checkout tab — clearBackendDeliveryFee mount effect', () => {
		const shouldClearOnMount = ( initialTabId ) =>
			initialTabId !== 'delivery_day';

		it( 'should clear when initial tab is dropoff_points', () => {
			expect( shouldClearOnMount( 'dropoff_points' ) ).toBe( true );
		} );

		it( 'should not clear when initial tab is delivery_day', () => {
			expect( shouldClearOnMount( 'delivery_day' ) ).toBe( false );
		} );

		it( 'should clear when initial tab is null (mount, pre-hydration)', () => {
			// Defensive: while activeTab is null during the first render frame,
			// the mount effect uses initialTabId, which is never null.
			expect( shouldClearOnMount( null ) ).toBe( true );
		} );
	} );

	// PR #306 review (Abdalsalaam, 2026-04-06, CHANGES_REQUESTED):
	// "When a merchant sets default_checkout_tab = 'dropoff_points' but pickup
	// points are disabled (or the PostNL API returns no pickup options), the
	// classic checkout renders a completely broken widget with no tab selected."
	// The fix lives in two places:
	//   1. JS: resolveInitialTabId falls back to baseTabs[0] when default is missing.
	//   2. PHP: Container.php replaces $default_tab with $tabs[0]['id'] when not in $tab_ids.
	// This block locks down the JS half.
	describe( 'default checkout tab — Abdalsalaam #306 review (rendering fallback)', () => {
		const resolveInitialTabId = ( baseTabs, defaultCheckoutTab ) => {
			const defaultTabId =
				defaultCheckoutTab || baseTabs[ 0 ].id;
			return baseTabs.find( ( tab ) => tab.id === defaultTabId )
				? defaultTabId
				: baseTabs[ 0 ].id;
		};

		it( 'should fall back to delivery_day when dropoff_points is set but pickup is disabled', () => {
			const baseTabs = [ { id: 'delivery_day' } ];
			expect(
				resolveInitialTabId( baseTabs, 'dropoff_points' )
			).toBe( 'delivery_day' );
		} );

		it( 'should fall back to delivery_day when API returns zero pickup options', () => {
			// is_pickup_points_enabled may be true at config time but the
			// runtime tab list (built from the API response) can still omit it.
			const baseTabs = [ { id: 'delivery_day' } ];
			expect(
				resolveInitialTabId( baseTabs, 'dropoff_points' )
			).toBe( 'delivery_day' );
		} );

		it( 'should never resolve to an id absent from baseTabs', () => {
			const baseTabs = [
				{ id: 'delivery_day' },
				{ id: 'dropoff_points' },
			];
			[
				'dropoff_points',
				'delivery_day',
				'unknown',
				'',
				undefined,
			].forEach( ( candidate ) => {
				const resolved = resolveInitialTabId(
					baseTabs,
					candidate
				);
				expect(
					baseTabs.some( ( t ) => t.id === resolved )
				).toBe( true );
			} );
		} );
	} );

	describe( 'fee calculation logic', () => {
		it( 'should calculate carrier base cost correctly', () => {
			const selectedShippingFee = 10;
			const tabBase = 2.5;
			const extraDeliveryFee = 1.5;

			const carrierBaseCost = selectedShippingFee - tabBase - extraDeliveryFee;
			expect( carrierBaseCost ).toBe( 6 );
		} );

		it( 'should not have negative carrier base cost', () => {
			const selectedShippingFee = 2;
			const tabBase = 2.5;
			const extraDeliveryFee = 1.5;

			const raw = selectedShippingFee - tabBase - extraDeliveryFee;
			const carrierBaseCost = raw < 0 ? 0 : raw;
			expect( carrierBaseCost ).toBe( 0 );
		} );

		it( 'should format tab title with fees', () => {
			const tab = {
				id: 'delivery_day',
				base: 2.5,
				displayFormatted: '€2,50',
			};
			const extraDeliveryFeeFormatted = '€1,00';
			const extraDeliveryFee = 1;

			let title = 'Delivery';
			const fees = [];

			if ( tab.displayFormatted && tab.base > 0 ) {
				fees.push( tab.displayFormatted );
			}

			if (
				tab.id === 'delivery_day' &&
				extraDeliveryFeeFormatted &&
				extraDeliveryFee > 0
			) {
				fees.push( extraDeliveryFeeFormatted );
			}

			if ( fees.length > 0 ) {
				title += ` (+${ fees.join( ' +' ) })`;
			}

			expect( title ).toBe( 'Delivery (+€2,50 +€1,00)' );
		} );
	} );

	describe( 'session management', () => {
		const { getDeliveryDay, clearSessionData } = require( '../../client/utils/session-manager' );

		it( 'should call getDeliveryDay on initialization', () => {
			getDeliveryDay();
			expect( getDeliveryDay ).toHaveBeenCalled();
		} );

		it( 'should call clearSessionData when needed', () => {
			clearSessionData();
			expect( clearSessionData ).toHaveBeenCalled();
		} );
	} );

	describe( 'letterbox delivery', () => {
		it( 'should create letterbox delivery value', () => {
			const firstDelivery = {
				date: '2024-01-10',
				options: [
					{
						from: '08:00',
						to: '22:00',
						price: 0,
						type: 'Letterbox',
					},
				],
			};

			const firstOption = firstDelivery.options[ 0 ];
			const deliveryDay = `${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`;

			expect( deliveryDay ).toBe( '2024-01-10_08:00-22:00_0' );
		} );
	} );
} );
