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
import { getDeliveryDay, clearSessionData } from '../../utils/session-manager';
import {
	batchSetExtensionData,
	clearDropoffPointExtensionData,
} from '../../utils/extension-data-helper';

/**
 * Helper function to check if a value is empty.
 */
const isEmpty = ( value ) =>
	value === undefined || value === null || value === '';

/**
 * Helper function to compare two addresses.
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

	const [ { extraDeliveryFee, extraDeliveryFeeFormatted }, setFeeState ] =
		useState( () => {
			const saved = getDeliveryDay();
			return {
				extraDeliveryFee: Number( saved.price || 0 ),
				extraDeliveryFeeFormatted: saved.priceFormatted || '',
			};
		} );

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

	const [ carrierBaseCost, setCarrierBaseCost ] = useState(
		() => selectedShippingFee - baseTabs[ 0 ].base - extraDeliveryFee
	);

	const prevShipping = useRef( selectedShippingFee );

	useEffect( () => {
		if ( prevShipping.current === selectedShippingFee ) {
			return;
		}
		prevShipping.current = selectedShippingFee;

		const currentTabBase =
			baseTabs.find( ( tab ) => tab.id === activeTab )?.base || 0;
		const extra = activeTab === 'delivery_day' ? extraDeliveryFee : 0;

		const raw = selectedShippingFee - currentTabBase - extra;
		setCarrierBaseCost( raw < 0 ? 0 : raw );
	}, [ selectedShippingFee ] );

	const tabs = useMemo(
		() =>
			baseTabs.map( ( tab ) => {
				let title =
					tab.id === 'delivery_day'
						? __( 'Delivery', 'postnl-for-woocommerce' )
						: __( 'Pickup', 'postnl-for-woocommerce' );

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

				return { id: tab.id, name: title, base: tab.base };
			} ),
		[ baseTabs, extraDeliveryFee, extraDeliveryFeeFormatted ]
	);

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

	const [ showContainer, setShowContainer ] = useState( false );
	const [ loading, setLoading ] = useState( false );

	const [ deliveryOptions, setDeliveryOptions ] = useState( [] );
	const [ dropoffOptions, setDropoffOptions ] = useState( [] );
	const [ deliveryDaysEnabled, setDeliveryDaysEnabled ] = useState( true );

	const handlePriceChange = useCallback( ( priceData ) => {
		setFeeState( {
			extraDeliveryFee: priceData.numeric || 0,
			extraDeliveryFeeFormatted: priceData.formatted || '',
		} );
	}, [] );

	// To prevent infinite loops if we update the address programmatically
	const isUpdatingAddress = useRef( false );

	// Ref to store the previous shipping address
	const previousShippingAddress = useRef( null );

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

	// Fetch data shipping address
	useEffect( () => {
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
					} else {
						// Response not success or no data: hide container
						setShowContainer( false );
						setDeliveryOptions( [] );
						setDropoffOptions( [] );
					}

					const event = new Event( 'postnl_address_updated' );
					window.dispatchEvent( event );
				} )
				.catch( () => {
					// On error, hide container and clear options
					setShowContainer( false );
					setDeliveryOptions( [] );
					setDropoffOptions( [] );

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
		setShippingAddress,
		updateCustomerData,
	] );

	// Clear session storage if checkout is complete, letterbox, or container hidden
	useEffect( () => {
		if ( isComplete || letterbox || ! showContainer ) {
			clearSessionData();
		}
	}, [ isComplete, letterbox, showContainer ] );

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
