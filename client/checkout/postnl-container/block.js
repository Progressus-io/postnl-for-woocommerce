/**
 * External dependencies
 */
import {
	useEffect,
	useState,
	useRef,
	useMemo,
	useCallback,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { useDispatch, useSelect } from '@wordpress/data';
import axios from 'axios';
import { Spinner } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { Block as DeliveryDayBlock } from '../postnl-delivery-day/block';
import { Block as DropoffPointsBlock } from '../postnl-dropoff-points/block';
import { clearSessionData } from '../../utils/session-manager';
import {
	batchSetExtensionData,
	clearAllExtensionData,
	clearBackendDeliveryFee,
	clearDropoffPointExtensionData,
	isCountrySupported,
} from '../../utils/extension-data-helper';

/**
 * Format a decimal amount as a WooCommerce-style price string using the
 * currency settings exposed by WooCommerce Blocks via getSetting('currency').
 *
 * @param {number} amount - The amount in full currency units (e.g. 5.99).
 * @return {string} Formatted price string (e.g. "€5,99").
 */
function formatAmount( amount ) {
	const currency = getSetting( 'currency', {} );
	const symbol = currency.symbol || '';
	const position = currency.symbolPosition || 'left';
	const decimal = currency.decimalSeparator || '.';
	const thousand = currency.thousandSeparator || ',';
	const precision = parseInt( currency.precision || 2, 10 );

	const fixed = amount.toFixed( precision );
	const [ intPart, decPart ] = fixed.split( '.' );
	const formattedInt = intPart.replace( /\B(?=(\d{3})+(?!\d))/g, thousand );
	const formattedNum =
		decPart !== undefined
			? `${ formattedInt }${ decimal }${ decPart }`
			: formattedInt;

	switch ( position ) {
		case 'right':
			return `${ formattedNum }${ symbol }`;
		case 'left_space':
			return `${ symbol }\u00a0${ formattedNum }`;
		case 'right_space':
			return `${ formattedNum }\u00a0${ symbol }`;
		default:
			return `${ symbol }${ formattedNum }`;
	}
}

/**
 * Helper function to check if a value is empty.
 * @param value
 */
const isEmpty = ( value ) =>
	value === undefined || value === null || value === '';

/**
 * Helper function to compare two addresses.
 * @param addr1
 * @param addr2
 */
const isAddressEqual = ( addr1, addr2 ) => {
	if ( ! addr1 || ! addr2 ) {
		return false;
	}
	return (
		addr1.country === addr2.country &&
		addr1.postcode === addr2.postcode &&
		addr1.address_1 === addr2.address_1 &&
		addr1[ 'postnl/house_number' ] === addr2[ 'postnl/house_number' ]
	);
};

export const Block = ( { checkoutExtensionData } ) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );

	const letterbox = postnlData.letterbox || false;
	const { CART_STORE_KEY, CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;

	const selectedShippingFee = useSelect(
		( select ) => {
			const store = select( CART_STORE_KEY );
			if ( ! store || ! store.getCartData ) {
				return 0;
			}

			const packages = store.getCartData().shippingRates || [];

			for ( const pkg of packages ) {
				const rates = pkg.shipping_rates || [];
				const chosen = rates.find( ( rate ) => rate && rate.selected );
				if ( chosen && chosen.price !== undefined ) {
					const minor = Number( chosen.currency_minor_unit || 0 );
					const price = parseFloat( chosen.price );
					if ( ! Number.isNaN( price ) ) {
						return price / Math.pow( 10, minor );
					}
				}
			}

			if ( store.getCartTotals ) {
				const totals = store.getCartTotals();
				if ( totals && totals.shipping_total ) {
					return Number( totals.shipping_total );
				}
			}

			return 0;
		},
		[ CART_STORE_KEY ]
	);

	// Initialise to zero — stale session data must NOT seed the initial state,
	// as that was the root cause of the visible tab-price flicker on load.
	const [ extraDeliveryFee, setExtraDeliveryFee ] = useState( 0 );

	// Stable display values from PHP AJAX response — used in the tab formula
	// instead of selectedShippingFee so tab prices don't flicker on tab switch.
	const [ carrierBaseCost, setCarrierBaseCost ] = useState( 0 );
	const [ deliveryDayFeeDisplay, setDeliveryDayFeeDisplay ] = useState( 0 );
	const [ pickupFeeDisplay, setPickupFeeDisplay ] = useState( 0 );

	// Shipping tax ratio: (1 + shipping_tax_rate) when prices include tax, else 1.
	// The WC Store API returns rate.price as the ex-tax cost, so we multiply it
	// by this ratio before subtracting the incl-tax PostNL fees in the back-calc.
	const [ taxRatio, setTaxRatio ] = useState( 1 );

	const baseTabs = useMemo(
		() => {
			const tabs = [
				{
					id: 'delivery_day',
					base: Number( postnlData.delivery_day_fee || 0 ),
					displayFormatted: postnlData.delivery_day_fee_formatted || '',
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
			// Reorder: put the merchant's preferred default tab first in the DOM.
			const preferredIdx = tabs.findIndex(
				( t ) => t.id === postnlData.default_checkout_tab
			);
			if ( preferredIdx > 0 ) {
				const reordered = [ ...tabs ];
				reordered.unshift( reordered.splice( preferredIdx, 1 )[ 0 ] );
				return reordered;
			}
			return tabs;
		},
		[
			postnlData.delivery_day_fee,
			postnlData.delivery_day_fee_formatted,
			postnlData.is_pickup_points_enabled,
			postnlData.pickup_fee,
			postnlData.pickup_fee_formatted,
			postnlData.default_checkout_tab,
		]
	);

	// Default tab: use merchant setting if the tab exists, otherwise fall back to the first available tab.
	const defaultTabId = postnlData.default_checkout_tab || baseTabs[ 0 ].id;
	const initialTabId = baseTabs.find( ( tab ) => tab.id === defaultTabId )
		? defaultTabId
		: baseTabs[ 0 ].id;
	// TODO: activeTab is null for one render frame, causing a brief "no active
	// tab" flash. Eliminating it requires synchronous useState(initialTabId) +
	// a ref-guarded effect for the side-effecting half — non-trivial refactor
	// touching every effect that depends on activeTab. Acceptable today.
	const [ activeTab, setActiveTab ] = useState( null );
	useEffect( () => {
		setActiveTab( initialTabId );
		// Mount-only by design: re-running on initialTabId would re-introduce
		// the premature extensionCartUpdate bug fixed in commit 89519b4
		// (PR #306 / ClickUp 868etp8wa).
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const [ isFreeShipping, setIsFreeShipping ] = useState( false );

	const [ showContainer, setShowContainer ] = useState( false );
	const [ loading, setLoading ] = useState( false );

	// Snapshot of the latest fee state, updated synchronously every render so
	// the selectedShippingFee effect below can read current values without
	// listing them as dependencies (which would cause it to re-run on every fee
	// change rather than only on shipping-method changes).
	const feeSnapRef = useRef( null );
	feeSnapRef.current = {
		activeTab,
		deliveryDayFeeDisplay,
		pickupFeeDisplay,
		extraDeliveryFee,
		isFreeShipping,
		taxRatio,
	};

	// Becomes true after the first AJAX response — prevents the effect below
	// from running before fee amounts are known, which would otherwise derive
	// an incorrect carrier base on initial load.
	const hasAjaxDataRef = useRef( false );

	// When the selected shipping rate changes (user picked a different shipping
	// method), update isFreeShipping and back-calculate carrierBaseCost so tab
	// prices update immediately without waiting for a new address-change AJAX call.
	useEffect( () => {
		if ( ! hasAjaxDataRef.current ) {
			return;
		}

		// When the selected rate has no cost, treat as free shipping for display
		// purposes. This handles both WC native free-shipping methods and PostNL
		// threshold-based free shipping, and covers the case where the user
		// switches methods without triggering a new address-change AJAX call.
		if ( selectedShippingFee <= 0 ) {
			setIsFreeShipping( true );
			setCarrierBaseCost( 0 );
			return;
		}

		// A paid method is now selected — clear the free-shipping flag so prices
		// are shown again, then back-calculate the carrier base cost from the rate.
		setIsFreeShipping( false );

		const {
			activeTab: tab,
			deliveryDayFeeDisplay: ddFee,
			pickupFeeDisplay: pickFee,
			extraDeliveryFee: extra,
			taxRatio: ratio,
		} = feeSnapRef.current;

		// The WC Store API exposes the shipping rate cost WITHOUT tax (rate.cost).
		// Our display fees (ddFee, extra, pickFee) are tax-inclusive values from
		// get_fee_total_price(). Multiplying the ex-tax fee by ratio converts it
		// to the same incl-tax basis so the subtraction recovers the correct
		// carrier base cost for display.
		const injected =
			tab === 'delivery_day'
				? ddFee + extra
				: tab === 'dropoff_points'
				? pickFee
				: 0;

		setCarrierBaseCost( Math.max( 0, selectedShippingFee * ratio - injected ) );
	}, [ selectedShippingFee ] );

	const tabs = useMemo( () => {
		return baseTabs.map( ( tab ) => {
			const label =
				tab.id === 'delivery_day'
					? __( 'Delivery', 'postnl-for-woocommerce' )
					: __( 'Pickup', 'postnl-for-woocommerce' );

			// Suppress fee display while loading, free shipping is active, or
			// before we have received carrier_base_cost from the AJAX response.
			if ( loading || isFreeShipping || carrierBaseCost <= 0 ) {
				return { id: tab.id, name: label, base: tab.base };
			}

			// Stable formula: all values are independent of which tab is active,
			// so tab prices never flicker during tab switches.
			//   carrier_base_cost  — raw carrier rate cost (no PostNL fees)
			//   tabFee             — the tab's base fee from settings (tax-adjusted)
			//   extra              — morning/evening extra, only for delivery_day tab
			const tabFee =
				tab.id === 'delivery_day'
					? deliveryDayFeeDisplay
					: pickupFeeDisplay;
			const extra = tab.id === 'delivery_day' ? extraDeliveryFee : 0;
			const total = carrierBaseCost + tabFee + extra;

			if ( total <= 0 ) {
				return { id: tab.id, name: label, base: tab.base };
			}

			return {
				id: tab.id,
				name: `${ label } (${ formatAmount( total ) })`,
				base: tab.base,
			};
		} );
	}, [
		baseTabs,
		loading,
		isFreeShipping,
		carrierBaseCost,
		deliveryDayFeeDisplay,
		pickupFeeDisplay,
		extraDeliveryFee,
	] );

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect(
		( select ) => {
			const store = select( CART_STORE_KEY );
			return store ? store.getCustomerData() : {};
		},
		[ CART_STORE_KEY ]
	);

	const shippingAddress = customerData ? customerData.shippingAddress : null;
	const { setShippingAddress, updateCustomerData } =
		useDispatch( CART_STORE_KEY );

	const [ deliveryOptions, setDeliveryOptions ] = useState( [] );
	const [ dropoffOptions, setDropoffOptions ] = useState( [] );
	const [ deliveryDaysEnabled, setDeliveryDaysEnabled ] = useState( true );

	useEffect( () => {
		if ( initialTabId !== 'delivery_day' ) {
			clearBackendDeliveryFee();
		}
		// Mount-only: clears any stale backend fee from a prior session when
		// the merchant's default tab isn't delivery_day. Re-running would
		// nuke a legitimate fee a user just selected.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const handlePriceChange = useCallback( ( priceData ) => {
		setExtraDeliveryFee( priceData.numeric || 0 );
	}, [] );

	// To prevent infinite loops if we update the address programmatically
	const isUpdatingAddress = useRef( false );

	// Ref to store the previous shipping address
	const previousShippingAddress = useRef( null );

	/**
	 * Clear all PostNL data: session, extension data, and backend cart fee.
	 */
	const clearAllPostNLData = useCallback( () => {
		clearSessionData();
		previousShippingAddress.current = null;
		clearAllExtensionData( setExtensionData );
		clearBackendDeliveryFee();
	}, [ setExtensionData ] );

	const isComplete = useSelect(
		( select ) => select( CHECKOUT_STORE_KEY ).isComplete(),
		[]
	);
	const currentHouseNumber = shippingAddress?.[ 'postnl/house_number' ] || '';

	useEffect( () => {
		if ( currentHouseNumber && postnlData.is_nl_address_enabled ) {
			setExtensionData( 'postnl', 'houseNumber', currentHouseNumber );
		}
	}, [ shippingAddress, setExtensionData ] );

	// Track previous country to detect transitions to unsupported countries
	const previousCountry = useRef( shippingAddress?.country || '' );
	const supportedCountries = postnlData.supported_countries || [];

	// Fetch data shipping address
	useEffect( () => {
		const country = shippingAddress?.country || '';
		const isSupported = isCountrySupported( country, supportedCountries );
		const wasSupported = isCountrySupported(
			previousCountry.current,
			supportedCountries
		);

		// Update previous country
		previousCountry.current = country;

		// If country is not supported, clear data once and stop all processing
		if ( ! isSupported ) {
			if ( wasSupported ) {
				setShowContainer( false );
				setDeliveryOptions( [] );
				setDropoffOptions( [] );
				clearAllPostNLData();
			}
			return;
		}

		if (
			! shippingAddress ||
			isEmpty( shippingAddress.postcode ) ||
			( shippingAddress.country === 'NL' &&
				postnlData.is_nl_address_enabled &&
				isEmpty( shippingAddress[ 'postnl/house_number' ] ) )
		) {
			// If we have no valid postcode/house number, hide container
			setShowContainer( false );
			return;
		}

		if ( isUpdatingAddress.current ) {
			isUpdatingAddress.current = false;
			return;
		}

		// Check if the shipping address has changed
		if (
			isAddressEqual( previousShippingAddress.current, shippingAddress )
		) {
			return;
		}

		const debounceDelay = 1500;
		const handler = setTimeout( () => {
			// Update the previous shipping address
			previousShippingAddress.current = { ...shippingAddress };

			const data = {
				shipping_country: shippingAddress.country || '',
				shipping_postcode: shippingAddress.postcode || '',
				...( postnlData.is_nl_address_enabled
					? {
							shipping_house_number:
								shippingAddress[ 'postnl/house_number' ] || '',
					  }
					: {} ),
				shipping_address_2: shippingAddress.address_2 || '',
				shipping_address_1: shippingAddress.address_1 || '',
				shipping_city: shippingAddress.city || '',
				shipping_state: shippingAddress.state || '',
				shipping_phone: shippingAddress.phone || '',
				shipping_email: shippingAddress.email || '',
				shipping_method: 'postnl',
				ship_to_different_address: '1',
			};

			const formData = new URLSearchParams();
			formData.append( 'action', 'postnl_set_checkout_post_data' );
			formData.append( 'nonce', postnlData.nonce );

			Object.keys( data ).forEach( ( key ) => {
				formData.append( `data[${ key }]`, data[ key ] );
			} );

			setLoading( true );

			axios
				.post( postnlData.ajax_url, formData, {
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
				} )
				.then( ( response ) => {
					if ( response.data.success && response.data.data ) {
						const respData = response.data.data;

						// If validated_address returned, update shipping address if needed
						if (
							respData.validated_address &&
							respData.validated_address.street &&
							respData.validated_address.city &&
							respData.validated_address.house_number
						) {
							const { street, city, house_number } =
								respData.validated_address;
							const newShippingAddress = {
								...shippingAddress,
								city,
								'postnl/house_number': house_number,
							};

							if ( ! postnlData.is_nl_address_enabled ) {
								newShippingAddress.address_1 = `${ street } ${ house_number }`;
							} else {
								newShippingAddress.address_1 = street;
							}

							if (
								shippingAddress.address_1 !== street ||
								shippingAddress.city !== city ||
								shippingAddress[ 'postnl/house_number' ] !==
									house_number
							) {
								isUpdatingAddress.current = true;
								setShippingAddress( newShippingAddress );
								updateCustomerData( newShippingAddress );
							}
						}

						setDeliveryDaysEnabled(
							respData.is_delivery_days_enabled
						);
						setShowContainer( respData.show_container || false );
						setDeliveryOptions( respData.delivery_options || [] );
						setDropoffOptions( respData.dropoff_options || [] );
						hasAjaxDataRef.current = true;
						setIsFreeShipping( respData.is_free_shipping || false );
						setCarrierBaseCost(
							Number( respData.carrier_base_cost || 0 )
						);
						setDeliveryDayFeeDisplay(
							Number( respData.delivery_day_fee_display || 0 )
						);
						setPickupFeeDisplay(
							Number( respData.pickup_fee_display || 0 )
						);
						setTaxRatio( Number( respData.tax_ratio || 1 ) );

						// Clear all PostNL data when container is hidden
						if ( ! respData.show_container ) {
							clearAllPostNLData();
						}
					} else {
						// Response not success or no data: hide container
						setShowContainer( false );
						setDeliveryOptions( [] );
						setDropoffOptions( [] );
						clearAllPostNLData();
					}

					const event = new Event( 'postnl_address_updated' );
					window.dispatchEvent( event );
				} )
				.catch( () => {
					// On error, hide container and clear options
					setShowContainer( false );
					setDeliveryOptions( [] );
					setDropoffOptions( [] );
					clearAllPostNLData();

					const event = new Event( 'postnl_address_updated' );
					window.dispatchEvent( event );
				} )
				.finally( () => {
					setLoading( false );
				} );
		}, debounceDelay );

		// Cleanup function to cancel the timeout if address changes before debounceDelay
		return () => clearTimeout( handler );
	}, [
		shippingAddress,
		postnlData.ajax_url,
		postnlData.nonce,
		postnlData.is_nl_address_enabled,
		setShippingAddress,
		updateCustomerData,
		clearAllPostNLData,
		supportedCountries,
	] );

	// Clear local data if checkout is complete or letterbox.
	// Note: Backend fee clearing is handled by AJAX response handlers via clearAllPostNLData().
	useEffect( () => {
		if ( isComplete || letterbox ) {
			clearSessionData();
			previousShippingAddress.current = null;
			clearAllExtensionData( setExtensionData );
		}
	}, [ isComplete, letterbox, setExtensionData ] );

	useEffect( () => {
		if ( ! letterbox || ! showContainer || deliveryOptions.length === 0 ) {
			return;
		}
		const firstDelivery = deliveryOptions[ 0 ];
		if (
			! Array.isArray( firstDelivery.options ) ||
			firstDelivery.options.length === 0
		) {
			return;
		}
		const firstOption = firstDelivery.options[ 0 ];

		// Build the combined value (like in DeliveryDayBlock):
		const deliveryDay = `${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`;

		// Set letterbox delivery data using batch helper
		batchSetExtensionData( setExtensionData, {
			deliveryDay,
			deliveryDayDate: firstDelivery.date || '',
			deliveryDayFrom: firstOption.from || '',
			deliveryDayTo: firstOption.to || '',
			deliveryDayPrice: String( firstOption.price || '0' ),
			deliveryDayType: firstOption.type || 'Letterbox',
		} );

		// Clear dropoff point data using helper
		clearDropoffPointExtensionData( setExtensionData );
	}, [ letterbox, showContainer, deliveryOptions, setExtensionData ] );

	return (
		<div
			id="postnl_checkout_option"
			className={ `postnl_checkout_container ${
				loading ? 'loading' : ''
			}` }
			aria-busy={ loading }
		>
			{ loading && (
				<div className="postnl-spinner-overlay">
					<Spinner />
				</div>
			) }

			{ /* Content when not letterbox and showContainer */ }
			{ ! letterbox && showContainer && (
				<>
					<div className="postnl_checkout_tab_container">
						<ul className="postnl_checkout_tab_list">
							{ tabs.map( ( tab ) => (
								<li
									key={ tab.id }
									className={
										activeTab === tab.id ? 'active' : ''
									}
								>
									<label
										htmlFor={ `postnl_option_${ tab.id }` }
										className="postnl_checkout_tab"
									>
										<span>{ tab.name }</span>
										<input
											type="radio"
											name="postnl_option"
											id={ `postnl_option_${ tab.id }` }
											className="postnl_option"
											value={ tab.id }
											checked={ activeTab === tab.id }
											onChange={ () =>
												setActiveTab( tab.id )
											}
										/>
									</label>
								</li>
							) ) }
						</ul>
					</div>
					<div className="postnl_checkout_content_container">
						<div
							className={ `postnl_content ${
								activeTab === 'delivery_day' ? 'active' : ''
							}` }
							id="postnl_delivery_day_content"
						>
							<DeliveryDayBlock
								checkoutExtensionData={ checkoutExtensionData }
								isActive={ activeTab === 'delivery_day' }
								deliveryOptions={ deliveryOptions }
								isDeliveryDaysEnabled={ deliveryDaysEnabled }
								onPriceChange={ handlePriceChange }
								isFreeShipping={ isFreeShipping }
							/>
						</div>
						{ postnlData.is_pickup_points_enabled && (
							<div
								className={ `postnl_content ${
									activeTab === 'dropoff_points'
										? 'active'
										: ''
								}` }
								id="postnl_dropoff_points_content"
							>
								<DropoffPointsBlock
									checkoutExtensionData={
										checkoutExtensionData
									}
									isActive={ activeTab === 'dropoff_points' }
									dropoffOptions={ dropoffOptions }
								/>
							</div>
						) }
					</div>
				</>
			) }

			{ /* Content when letterbox is true */ }
			{ letterbox && showContainer && (
				<div className="postnl-letterbox-message">
					{ __(
						'These items are eligible for letterbox delivery.',
						'postnl-for-woocommerce'
					) }
				</div>
			) }
		</div>
	);
};
