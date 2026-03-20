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
import {
	formatPrice,
	getCurrencyFromPriceResponse,
} from '@woocommerce/price-format';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { Block as DeliveryDayBlock } from '../postnl-delivery-day/block';
import { Block as DropoffPointsBlock } from '../postnl-dropoff-points/block';
import { clearSessionData, getDeliveryDay } from '../../utils/session-manager';
import {
	batchSetExtensionData,
	clearAllExtensionData,
	clearBackendDeliveryFee,
	clearDropoffPointExtensionData,
	isCountrySupported,
} from '../../utils/extension-data-helper';

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

	const { CART_STORE_KEY, CHECKOUT_STORE_KEY } = window.wc.wcBlocksData;

	const { selectedShippingFee, selectedMethodId, cartDataLoaded } = useSelect(
		( select ) => {
			const store = select( CART_STORE_KEY );
			if ( ! store || ! store.getCartData ) {
				return {
					selectedShippingFee: 0,
					selectedMethodId: '',
					cartDataLoaded: false,
				};
			}

			const hasLoaded =
				typeof store.hasFinishedResolution === 'function'
					? store.hasFinishedResolution( 'getCartData', [] )
					: true;

			const packages = store.getCartData().shippingRates || [];
			const taxDisplayIncl = postnlData.tax_display_incl || false;

			for ( const pkg of packages ) {
				const rates = pkg.shipping_rates || [];
				const chosen = rates.find( ( rate ) => rate && rate.selected );
				if ( chosen && chosen.price !== undefined ) {
					const minor = Number( chosen.currency_minor_unit || 0 );
					const price = parseFloat( chosen.price );
					const taxes = parseFloat( chosen.taxes || 0 );
					if ( ! Number.isNaN( price ) ) {
						const displayPrice = taxDisplayIncl
							? price + taxes
							: price;
						return {
							selectedShippingFee:
								displayPrice / Math.pow( 10, minor ),
							selectedMethodId: chosen.method_id || '',
							cartDataLoaded: hasLoaded,
						};
					}
				}
			}

			if ( store.getCartTotals ) {
				const totals = store.getCartTotals();
				if ( totals && totals.shipping_total ) {
					return {
						selectedShippingFee: Number( totals.shipping_total ),
						selectedMethodId: '',
						cartDataLoaded: hasLoaded,
					};
				}
			}

			return {
				selectedShippingFee: 0,
				selectedMethodId: '',
				cartDataLoaded: hasLoaded,
			};
		},
		[ CART_STORE_KEY ]
	);

	// True when the selected method is one that has PostNL tab fees baked in.
	const supportedMethods = postnlData.supported_shipping_methods || [ 'postnl' ];
	const isSupportedMethod = supportedMethods.includes( selectedMethodId );

	const currency = useMemo(
		() => getCurrencyFromPriceResponse( getSetting( 'currency_data', {} ) ),
		[]
	);

	const baseTabs = useMemo(
		() => [
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
		],
		[
			postnlData.delivery_day_fee,
			postnlData.delivery_day_fee_formatted,
			postnlData.is_pickup_points_enabled,
			postnlData.pickup_fee,
			postnlData.pickup_fee_formatted,
		]
	);

	const [ activeTab, setActiveTab ] = useState( baseTabs[ 0 ].id );

	const isFreeShipping = cartDataLoaded && selectedShippingFee === 0;

	const [ extraDeliveryFee, setExtraDeliveryFee ] = useState( () => {
		const saved = getDeliveryDay();
		return Number( saved.price || 0 );
	} );

	// Tracks the extra fee AND active tab the server has confirmed (both baked
	// into selectedShippingFee). Only sync when selectedShippingFee changes so
	// carrierBase stays stable while any cart update is in-flight.
	const pendingExtraFeeRef = useRef( Number( getDeliveryDay().price || 0 ) );
	const pendingActiveTabRef = useRef( baseTabs[ 0 ].id );
	const [ confirmedExtraFee, setConfirmedExtraFee ] = useState(
		() => Number( getDeliveryDay().price || 0 )
	);
	const [ confirmedActiveTab, setConfirmedActiveTab ] = useState(
		() => baseTabs[ 0 ].id
	);

	// When the cart store updates (server round-trip complete), lock in both
	// the pending extra fee and the pending active tab so all tab titles
	// recalculate together with the newly confirmed selectedShippingFee.
	useEffect( () => {
		if ( cartDataLoaded ) {
			setConfirmedExtraFee( pendingExtraFeeRef.current );
			setConfirmedActiveTab( pendingActiveTabRef.current );
		}
	}, [ selectedShippingFee, cartDataLoaded ] );

	const handleTabChange = useCallback( ( tabId ) => {
		pendingActiveTabRef.current = tabId;
		setActiveTab( tabId );
	}, [] );

	const handlePriceChange = useCallback( ( priceData ) => {
		const newFee = priceData.numeric || 0;
		pendingExtraFeeRef.current = newFee;
		setExtraDeliveryFee( newFee );
	}, [] );

	const tabs = useMemo( () => {
		// Don't calculate until cart data is fully loaded to avoid flicker.
		if ( ! cartDataLoaded ) {
			return baseTabs.map( ( tab ) => ( {
				id: tab.id,
				name:
					tab.id === 'delivery_day'
						? __( 'Delivery', 'postnl-for-woocommerce' )
						: __( 'Pickup', 'postnl-for-woocommerce' ),
				base: tab.base,
			} ) );
		}

		// Use confirmedActiveTab + confirmedExtraFee (both server-confirmed) so
		// carrierBase stays stable while any cart update is in-flight.
		const confirmedTabBase = isSupportedMethod
			? baseTabs.find( ( t ) => t.id === confirmedActiveTab )?.base || 0
			: 0;
		const confirmedActiveExtra = isSupportedMethod
			? confirmedTabBase +
			( confirmedActiveTab === 'delivery_day' ? confirmedExtraFee : 0 )
			: 0;
		const carrierBase = Math.max( 0, selectedShippingFee - confirmedActiveExtra );
		const minorUnit = currency.minorUnit ?? 2;
		const multiplier = Math.pow( 10, minorUnit );

		return baseTabs.map( ( tab ) => {
			let title =
				tab.id === 'delivery_day'
					? __( 'Delivery', 'postnl-for-woocommerce' )
					: __( 'Pickup', 'postnl-for-woocommerce' );

			if ( selectedShippingFee > 0 ) {
				const extra = tab.id === 'delivery_day' ? extraDeliveryFee : 0;
				const tabTotal = carrierBase + tab.base + extra;

				if ( tabTotal > 0 ) {
					const totalFormatted = formatPrice(
						Math.round( tabTotal * multiplier ),
						currency
					);
					title += ` (${ totalFormatted })`;
				} else if ( tab.base > 0 ) {
					title += ` (+${ tab.displayFormatted })`;
				}
			}

			return { id: tab.id, name: title, base: tab.base };
		} );
	}, [
		baseTabs,
		confirmedActiveTab,
		selectedShippingFee,
		isSupportedMethod,
		currency,
		extraDeliveryFee,
		confirmedExtraFee,
		cartDataLoaded,
	] );

	// Retrieve customer data from WooCommerce cart store.
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

	// Refs for tracking address fill-in and container visibility transitions.
	const prevValidatedAddress = useRef( null );
	const prevEffectiveShowContainer = useRef( false );

	/**
	 * Clear all PostNL data: session, extension data, and backend cart fee.
	 */
	const clearAllPostNLData = useCallback( () => {
		clearSessionData();
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


	// Read PostNL delivery options from the WooCommerce Cart Store API extensions.
	// Populated server-side by handle_address_update() via
	// woocommerce_store_api_cart_update_customer_from_request.
	//
	// We serialise to JSON so useSelect can detect changes by value rather than
	// reference. Without this, WooCommerce returns a new object on every selector
	// call even when the data hasn't changed, causing an infinite render loop.
	const postnlExtensionsJson = useSelect(
		( select ) => {
			const store = select( CART_STORE_KEY );
			if ( ! store || ! store.getCartData ) {
				return null;
			}
			const ext = store.getCartData()?.extensions?.postnl;
			return ext ? JSON.stringify( ext ) : null;
		},
		[ CART_STORE_KEY ]
	);
	const postnlExtensions = useMemo(
		() => ( postnlExtensionsJson ? JSON.parse( postnlExtensionsJson ) : null ),
		[ postnlExtensionsJson ]
	);

	const supportedCountries = postnlData.supported_countries || [];
	const country = shippingAddress?.country || '';
	const isSupported = isCountrySupported( country, supportedCountries );

	// Derive display state from cart extensions.
	const showContainer       = postnlExtensions?.showContainer || false;
	const deliveryOptions     = postnlExtensions?.deliveryOptions || [];
	const dropoffOptions      = postnlExtensions?.dropoffOptions || [];
	const deliveryDaysEnabled = postnlExtensions?.deliveryDaysEnabled ?? true;
	const validatedAddress    = postnlExtensions?.validatedAddress || null;
	// Prefer dynamic extensions value; fall back to PHP static value for initial render.
	const isLetterbox = ( postnlExtensions?.isLetterbox ?? postnlData.letterbox ) || false;

	// Hide container immediately on the client if country is unsupported.
	const effectiveShowContainer = isSupported && showContainer;

	// Fill in validated NL address fields when PostNL confirms the address.
	useEffect( () => {
		if ( ! validatedAddress || ! validatedAddress.street ) {
			return;
		}

		const prev = prevValidatedAddress.current;
		if (
			prev &&
			prev.street === validatedAddress.street &&
			prev.city === validatedAddress.city &&
			prev.house_number === validatedAddress.house_number
		) {
			return;
		}

		prevValidatedAddress.current = validatedAddress;

		const { street, city, house_number } = validatedAddress;
		const newShippingAddress = { ...shippingAddress, city };

		if ( ! postnlData.is_nl_address_enabled ) {
			newShippingAddress.address_1 = `${ street } ${ house_number }`;
		} else {
			newShippingAddress.address_1 = street;
			if ( house_number ) {
				newShippingAddress[ 'postnl/house_number' ] = house_number;
			}
		}

		if (
			shippingAddress.address_1 !== newShippingAddress.address_1 ||
			shippingAddress.city !== city
		) {
			setShippingAddress( newShippingAddress );
			updateCustomerData( newShippingAddress );
		}
	}, [ validatedAddress ] ); // eslint-disable-line react-hooks/exhaustive-deps

	// Clear all PostNL data when the container transitions from visible to hidden.
	useEffect( () => {
		if ( prevEffectiveShowContainer.current && ! effectiveShowContainer ) {
			clearAllPostNLData();
		}
		prevEffectiveShowContainer.current = effectiveShowContainer;
	}, [ effectiveShowContainer, clearAllPostNLData ] );

	// Clear local data if checkout is complete or letterbox eligibility detected.
	useEffect( () => {
		if ( isComplete || isLetterbox ) {
			clearSessionData();
			clearAllExtensionData( setExtensionData );
		}
	}, [ isComplete, isLetterbox, setExtensionData ] );

	useEffect( () => {
		if ( ! isLetterbox || ! effectiveShowContainer || deliveryOptions.length === 0 ) {
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
	}, [ isLetterbox, effectiveShowContainer, deliveryOptions, setExtensionData ] );

	return (
		<div
			id="postnl_checkout_option"
			className="postnl_checkout_container"
		>
			{ /* Content when not letterbox and showContainer */ }
			{ ! isLetterbox && effectiveShowContainer && (
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
												handleTabChange( tab.id )
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
								isFreeShipping={ isFreeShipping }
								isDataLoaded={ cartDataLoaded }
								onPriceChange={ handlePriceChange }
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
			{ isLetterbox && effectiveShowContainer && (
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