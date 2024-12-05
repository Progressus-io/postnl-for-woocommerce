/**
 * External dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';
import { debounce } from 'lodash';
import { getSetting } from '@woocommerce/settings';

/**
 * Delivery Day Block Component
 */
export const Block = ( { checkoutExtensionData, isActive } ) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce( ( namespace, key, value ) => {
			setExtensionData( namespace, key, value );
		}, 1000 ),
		[ setExtensionData ]
	);

	// Initialize state from sessionStorage if available
	const [ selectedOption, setSelectedOption ] = useState( () => {
		return sessionStorage.getItem( 'postnl_selected_option' ) || '';
	} );
	const [ loading, setLoading ] = useState( true );
	const [ updating, setUpdating ] = useState( false );

	// Use ref to store delivery options across renders
	const deliveryOptionsRef = useRef( [] );

	// State variables for hidden fields based on block.json attributes
	const [ deliveryDay, setDeliveryDay ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDay' ) || '';
	} );
	const [ deliveryDayDate, setDeliveryDayDate ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDayDate' ) || '';
	} );
	const [ deliveryDayFrom, setDeliveryDayFrom ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDayFrom' ) || '';
	} );
	const [ deliveryDayTo, setDeliveryDayTo ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDayTo' ) || '';
	} );
	const [ deliveryDayPrice, setDeliveryDayPrice ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDayPrice' ) || '';
	} );
	const [ deliveryDayType, setDeliveryDayType ] = useState( () => {
		return sessionStorage.getItem( 'postnl_deliveryDayType' ) || '';
	} );

	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDay', deliveryDay );
		debouncedSetExtensionData( 'postnl', 'deliveryDay', deliveryDay );
	}, [ deliveryDay, setExtensionData, debouncedSetExtensionData ] );
	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDayDate', deliveryDayDate );
		debouncedSetExtensionData( 'postnl', 'deliveryDayDate', deliveryDayDate );
	}, [ deliveryDayDate, setExtensionData, debouncedSetExtensionData ] );
	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDayFrom', deliveryDayFrom );
		debouncedSetExtensionData( 'postnl', 'deliveryDayFrom', deliveryDayFrom );
	}, [ deliveryDayFrom, setExtensionData, debouncedSetExtensionData ] );
	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDayTo', deliveryDayTo );
		debouncedSetExtensionData( 'postnl', 'deliveryDayTo', deliveryDayTo );
	}, [ deliveryDayTo, setExtensionData, debouncedSetExtensionData ] );
	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDayPrice', deliveryDayPrice );
		debouncedSetExtensionData( 'postnl', 'deliveryDayPrice', deliveryDayPrice );
	}, [ deliveryDayPrice, setExtensionData, debouncedSetExtensionData ] );
	useEffect( () => {
		setExtensionData( 'postnl', 'deliveryDayType', deliveryDayType );
		debouncedSetExtensionData( 'postnl', 'deliveryDayType', deliveryDayType );
	}, [ deliveryDayType, setExtensionData, debouncedSetExtensionData ] );

	/**
	 * useEffect to handle tab activation
	 */
	useEffect( () => {
		if ( isActive ) {
			// Tab is active
			// If no option is selected, select the default option
			if ( ! selectedOption && deliveryOptionsRef.current.length > 0 ) {
				const firstDelivery = deliveryOptionsRef.current[ 0 ];
				if ( Array.isArray( firstDelivery.options ) && firstDelivery.options.length > 0 ) {
					const firstOption = firstDelivery.options[ 0 ];
					handleOptionChange(
						`${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`,
						firstDelivery.date,
						firstOption.from,
						firstOption.to,
						firstOption.type || 'Unknown',
						firstOption.price || 0
					);
				}
			}
		} else {
			// Tab is inactive
			// Clear hidden fields and extension data, but keep sessionStorage
			clearSelections();
		}
	}, [ isActive ] );

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = ( clearSession = false ) => {
		setSelectedOption( '' );
		if ( clearSession ) {
			sessionStorage.removeItem( 'postnl_selected_option' );
		}
		setDeliveryDay( '' );
		setDeliveryDayDate( '' );
		setDeliveryDayFrom( '' );
		setDeliveryDayTo( '' );
		setDeliveryDayPrice( '' );
		setDeliveryDayType( '' );
		if ( clearSession ) {
			sessionStorage.removeItem( 'postnl_deliveryDay' );
			sessionStorage.removeItem( 'postnl_deliveryDayDate' );
			sessionStorage.removeItem( 'postnl_deliveryDayFrom' );
			sessionStorage.removeItem( 'postnl_deliveryDayTo' );
			sessionStorage.removeItem( 'postnl_deliveryDayPrice' );
			sessionStorage.removeItem( 'postnl_deliveryDayType' );
		}
		setExtensionData( 'postnl', 'deliveryDay', '' );
		setExtensionData( 'postnl', 'deliveryDayDate', '' );
		setExtensionData( 'postnl', 'deliveryDayFrom', '' );
		setExtensionData( 'postnl', 'deliveryDayTo', '' );
		setExtensionData( 'postnl', 'deliveryDayPrice', '' );
		setExtensionData( 'postnl', 'deliveryDayType', '' );
	};

	/**
	 * Fetch delivery options via AJAX
	 */
	const fetchDeliveryOptions = useCallback( () => {
		setUpdating( true );

		const formData = new URLSearchParams();
		formData.append( 'action', 'postnl_get_delivery_options' );
		formData.append( 'nonce', postnlData.nonce );
		axios
			.post( postnlData.ajax_url, formData, {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			} )
			.then( ( response ) => {
				if ( response.data.success ) {
					const newDeliveryOptions = response.data.data.delivery_options;

					if (
						! newDeliveryOptions ||
						! Array.isArray( newDeliveryOptions ) ||
						newDeliveryOptions.length === 0
					) {
						// Clear delivery options
						deliveryOptionsRef.current = [];
						clearSelections( true ); // Clear sessionStorage
					} else {
						// Store delivery options in ref
						deliveryOptionsRef.current = newDeliveryOptions;

						// Check if the selected option still exists
						const matchingOption = findMatchingOption( selectedOption, newDeliveryOptions );
						if ( ! matchingOption ) {
							// Selected option no longer valid, clear selection
							clearSelections( true ); // Clear sessionStorage
						}
					}
				} else {
					// Handle error
					deliveryOptionsRef.current = [];
					clearSelections( true ); // Clear sessionStorage
				}
			} )
			.catch( ( error ) => {
				deliveryOptionsRef.current = [];
				clearSelections( true ); // Clear sessionStorage
			} )
			.finally( () => {
				setUpdating( false );
				setLoading( false );
			} );
	}, [ selectedOption ] );

	/**
	 * Helper function to find matching option
	 */
	const findMatchingOption = ( selectedValue, deliveryOptions ) => {
		for ( const delivery of deliveryOptions ) {
			if ( Array.isArray( delivery.options ) ) {
				for ( const option of delivery.options ) {
					const from = option.from || '';
					const to = option.to || '';
					const price = option.price || 0;
					const value = `${ delivery.date }_${ from }-${ to }_${ price }`;
					if ( value === selectedValue ) {
						return { delivery, option };
					}
				}
			}
		}
		return null;
	};

	/**
	 * Initial Load: Fetch delivery options or use existing ones
	 */
	useEffect( () => {
		if ( deliveryOptionsRef.current.length > 0 ) {
			// Use existing delivery options
			setLoading( false );
		} else {
			fetchDeliveryOptions();
		}
	}, [ fetchDeliveryOptions ] );

	/**
	 * Listen for the custom event to fetch updated delivery options
	 */
	useEffect( () => {
		const handleAddressUpdated = () => {
			fetchDeliveryOptions();
		};

		window.addEventListener( 'postnl_address_updated', handleAddressUpdated );

		return () => {
			window.removeEventListener( 'postnl_address_updated', handleAddressUpdated );
		};
	}, [ fetchDeliveryOptions ] );

	/**
	 * Handle the change of a delivery option
	 *
	 * @param {string} value - The value of the selected option
	 * @param {string} deliveryDate - The delivery date
	 * @param {string} from - Start time
	 * @param {string} to - End time
	 * @param {string} type - Type (Morning/Evening)
	 * @param {number} price - Price of the option
	 */
	const handleOptionChange = async ( value, deliveryDate, from, to, type, price ) => {
		setSelectedOption( value );
		sessionStorage.setItem( 'postnl_selected_option', value );

		setDeliveryDayDate( deliveryDate );
		sessionStorage.setItem( 'postnl_deliveryDayDate', deliveryDate );

		setDeliveryDayFrom( from );
		sessionStorage.setItem( 'postnl_deliveryDayFrom', from );

		setDeliveryDayTo( to );
		sessionStorage.setItem( 'postnl_deliveryDayTo', to );

		setDeliveryDayPrice( price );
		sessionStorage.setItem( 'postnl_deliveryDayPrice', price.toString() );

		setDeliveryDayType( type );
		sessionStorage.setItem( 'postnl_deliveryDayType', type );

		const deliveryDayValue = `${ deliveryDate }_${ from }-${ to }_${ price }`;
		setDeliveryDay( deliveryDayValue );
		sessionStorage.setItem( 'postnl_deliveryDay', deliveryDayValue );

		setExtensionData( 'postnl', 'deliveryDay', deliveryDayValue );
		setExtensionData( 'postnl', 'deliveryDayDate', deliveryDate );
		setExtensionData( 'postnl', 'deliveryDayFrom', from );
		setExtensionData( 'postnl', 'deliveryDayTo', to );
		setExtensionData( 'postnl', 'deliveryDayPrice', price.toString() );
		setExtensionData( 'postnl', 'deliveryDayType', type );

		// Also, clear dropoff point data
		sessionStorage.removeItem( 'postnl_dropoffPoints' );
		sessionStorage.removeItem( 'postnl_dropoffPointsAddressCompany' );
		sessionStorage.removeItem( 'postnl_dropoffPointsAddress1' );
		sessionStorage.removeItem( 'postnl_dropoffPointsAddress2' );
		sessionStorage.removeItem( 'postnl_dropoffPointsCity' );
		sessionStorage.removeItem( 'postnl_dropoffPointsPostcode' );
		sessionStorage.removeItem( 'postnl_dropoffPointsCountry' );
		sessionStorage.removeItem( 'postnl_dropoffPointsPartnerID' );
		sessionStorage.removeItem( 'postnl_dropoffPointsDate' );
		sessionStorage.removeItem( 'postnl_dropoffPointsTime' );
		sessionStorage.removeItem( 'postnl_dropoffPointsDistance' );

		setExtensionData( 'postnl', 'dropoffPoints', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddressCompany', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddress1', '' );
		setExtensionData( 'postnl', 'dropoffPointsAddress2', '' );
		setExtensionData( 'postnl', 'dropoffPointsCity', '' );
		setExtensionData( 'postnl', 'dropoffPointsPostcode', '' );
		setExtensionData( 'postnl', 'dropoffPointsCountry', '' );
		setExtensionData( 'postnl', 'dropoffPointsPartnerID', '' );
		setExtensionData( 'postnl', 'dropoffPointsDate', '' );
		setExtensionData( 'postnl', 'dropoffPointsTime', '' );
		setExtensionData( 'postnl', 'dropoffPointsDistance', null );

		// Call extensionCartUpdate to update the cart total
		try {
			const { extensionCartUpdate } = window.wc.blocksCheckout || {};

			if ( typeof extensionCartUpdate === 'function' ) {
				await extensionCartUpdate( {
					namespace: 'postnl',
					data: {
						action: 'update_delivery_fee',
						price: price,
						type: type,
					},
				} );
			}
		} catch ( error ) {
			// Handle error
		}
	};

	/**
	 * Render Loading or Updating Spinner
	 */
	if ( loading || updating ) {
		return <Spinner />;
	}

	/**
	 * Render Error Message
	 */
	if ( deliveryOptionsRef.current.length === 0 ) {
		// No delivery options available; do not render anything
		return null;
	}

	/**
	 * Render the Delivery Options with Hidden Inputs
	 */
	return (
		<div>
			<ul className="postnl_delivery_day_list postnl_list">
				{ deliveryOptionsRef.current.map( ( delivery, index ) =>
					Array.isArray( delivery.options ) && delivery.options.length > 0 ? (
						<li key={ index }>
							<div className="list_title">
								<span>{ `${ delivery.date } ${ delivery.day }` }</span>
							</div>
							<ul className="postnl_sub_list">
								{ delivery.options.map( ( option, optionIndex ) => {
									const from = option.from || '';
									const to = option.to || '';
									const optionType = option.type || 'Unknown';
									const price = option.price || 0;
									const value = `${ delivery.date }_${ from }-${ to }_${ price }`;

									const isChecked = selectedOption === value;
									const isActive = isChecked ? 'active' : '';

									let delivery_time = '';
									if ( optionType === 'Evening' ) {
										delivery_time = __( 'Evening', 'postnl-for-woocommerce' );
									} else if ( optionType === 'Morning' || optionType === '08:00-12:00' ) {
										delivery_time = __( 'Morning', 'postnl-for-woocommerce' );
									} else {
										delivery_time = __( optionType, 'postnl-for-woocommerce' );
									}

									return (
										<li
											key={ optionIndex }
											className={ `${ optionType } ${ isActive }` }
											data-date={ delivery.date }
											data-from={ from }
											data-to={ to }
											data-type={ optionType }
										>
											<label className="postnl_sub_radio_label" htmlFor={ `delivery_day_${ value }` }>
												<input
													type="radio"
													id={ `delivery_day_${ value }` }
													name="delivery_day"
													className="postnl_sub_radio"
													value={ value }
													checked={ isChecked }
													onChange={ () =>
														handleOptionChange( value, delivery.date, from, to, optionType, price )
													}
												/>
												{ price > 0 && <i>+€{ price.toFixed( 2 ) }</i> }
												<i>{ delivery_time }</i>
												<span>{ `${ from } - ${ to }` }</span>
											</label>
										</li>
									);
								} ) }
							</ul>
						</li>
					) : null
				) }
			</ul>
			<input type="hidden" name="deliveryDay" id="deliveryDay" value={ deliveryDay } />
			<input type="hidden" name="deliveryDayDate" id="deliveryDayDate" value={ deliveryDayDate } />
			<input type="hidden" name="deliveryDayFrom" id="deliveryDayFrom" value={ deliveryDayFrom } />
			<input type="hidden" name="deliveryDayTo" id="deliveryDayTo" value={ deliveryDayTo } />
			<input type="hidden" name="deliveryDayPrice" id="deliveryDayPrice" value={ deliveryDayPrice } />
			<input type="hidden" name="deliveryDayType" id="deliveryDayType" value={ deliveryDayType } />
		</div>
	);
};
