/**
 * External dependencies
 */
import {
	useEffect,
	useState,
	useCallback,
	useRef,
	useMemo,
} from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */
import {
	getDeliveryDay,
	setDeliveryDay as saveDeliveryDay,
	clearDeliveryDay,
	clearDropoffPoint,
} from '../../utils/session-manager';
import {
	batchSetExtensionData,
	clearDropoffPointExtensionData,
	clearDeliveryDayExtensionData,
} from '../../utils/extension-data-helper';

/**
 * Empty delivery day state - used for clearing selections.
 */
const EMPTY_DELIVERY_STATE = {
	selectedOption: '',
	deliveryDay: '',
	deliveryDayDate: '',
	deliveryDayFrom: '',
	deliveryDayTo: '',
	deliveryDayPrice: '',
	deliveryDayType: '',
};

export const Block = ( {
	checkoutExtensionData,
	isActive,
	deliveryOptions,
	isDeliveryDaysEnabled,
	onPriceChange = () => {},
	isFreeShipping = false,
	isDataLoaded = true,
} ) => {
	const { setExtensionData } = checkoutExtensionData;

	// Store setExtensionData in a ref to avoid recreating debounced function
	const setExtensionDataRef = useRef( setExtensionData );
	setExtensionDataRef.current = setExtensionData;

	// Create a stable debounced batch update function
	const debouncedBatchUpdate = useMemo(
		() =>
			debounce( ( data ) => {
				batchSetExtensionData( setExtensionDataRef.current, data );
			}, 250 ),
		[]
	);

	// Cleanup debounce on unmount
	useEffect( () => {
		return () => {
			debouncedBatchUpdate.cancel();
		};
	}, [ debouncedBatchUpdate ] );

	// Create a stable debounced extensionCartUpdate to avoid the WooCommerce
	// loading overlay that appears when awaiting extensionCartUpdate directly.
	const debouncedExtensionCartUpdate = useMemo(
		() =>
			debounce( ( price, type ) => {
				const { extensionCartUpdate } = window.wc?.blocksCheckout || {};
				if ( typeof extensionCartUpdate === 'function' ) {
					extensionCartUpdate( {
						namespace: 'postnl',
						data: {
							action: 'update_delivery_fee',
							price,
							type,
						},
					} );
				}
			}, 400 ),
		[]
	);

	// Cleanup debouncedExtensionCartUpdate on unmount
	useEffect( () => {
		return () => {
			debouncedExtensionCartUpdate.cancel();
		};
	}, [ debouncedExtensionCartUpdate ] );

	// Initialize state from centralized session manager
	const [ selection, setSelection ] = useState( () => {
		const saved = getDeliveryDay();
		return {
			selectedOption: saved.value || '',
			deliveryDay: saved.value || '',
			deliveryDayDate: saved.date || '',
			deliveryDayFrom: saved.from || '',
			deliveryDayTo: saved.to || '',
			deliveryDayPrice: saved.price || '',
			deliveryDayType: saved.type || '',
		};
	} );

	// Sync with extension data when selection changes (debounced)
	useEffect( () => {
		debouncedBatchUpdate( {
			deliveryDay: selection.deliveryDay,
			deliveryDayDate: selection.deliveryDayDate,
			deliveryDayFrom: selection.deliveryDayFrom,
			deliveryDayTo: selection.deliveryDayTo,
			deliveryDayPrice: selection.deliveryDayPrice.toString(),
			deliveryDayType: selection.deliveryDayType,
		} );
	}, [ selection, debouncedBatchUpdate ] );

	// Clear all delivery day selections
	const clearSelections = useCallback(
		( clearSession = false ) => {
			setSelection( EMPTY_DELIVERY_STATE );

			if ( clearSession ) {
				clearDeliveryDay();
			}

			// Clear extension data using helper
			clearDeliveryDayExtensionData( setExtensionData );
			onPriceChange( { numeric: 0, formatted: '' } );
		},
		[ setExtensionData, onPriceChange ]
	);

	// Handle tab activation, selection validation, and auto-select first option.
	useEffect( () => {
		// Clear when tab is inactive.
		if ( ! isActive ) {
			clearSelections( true );
			return;
		}

		// Don't act during transitional loading states — avoids clearing a valid
		// selection when the cart store briefly returns empty data mid-update.
		if ( ! isDataLoaded ) {
			return;
		}

		if ( ! Array.isArray( deliveryOptions ) || deliveryOptions.length === 0 ) {
			clearSelections( true );
			return;
		}

		// Build the set of valid option values from the current delivery options.
		const validValues = deliveryOptions.reduce( ( acc, delivery ) => {
			if ( Array.isArray( delivery.options ) ) {
				delivery.options.forEach( ( opt ) => {
					acc.push(
						`${ delivery.date }_${ opt.from }-${ opt.to }_${ opt.price }`
					);
				} );
			}
			return acc;
		}, [] );

		// Keep the current selection if it is still valid (e.g. after an address
		// change that returns the same delivery dates/times).
		if ( selection.selectedOption && validValues.includes( selection.selectedOption ) ) {
			return;
		}

		// No valid selection — auto-select the first available option.
		const firstDelivery = deliveryOptions[ 0 ];
		if (
			Array.isArray( firstDelivery.options ) &&
			firstDelivery.options.length > 0
		) {
			const firstOption = firstDelivery.options[ 0 ];
			const effectivePrice = isFreeShipping ? 0 : firstOption.price || 0;
			const effectivePriceDisplay = isFreeShipping
				? 0
				: ( firstOption.price_display ?? effectivePrice );
			handleOptionChange(
				`${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`,
				firstDelivery.date,
				firstOption.from,
				firstOption.to,
				firstOption.type || 'Unknown',
				effectivePrice,
				isFreeShipping ? '' : firstOption.price_formatted || '',
				effectivePriceDisplay
			);
			onPriceChange( {
				numeric: effectivePriceDisplay,
				formatted: isFreeShipping ? '' : firstOption.price_formatted || '',
			} );
		}
	}, [ isActive, deliveryOptions, isDataLoaded ] ); // eslint-disable-line react-hooks/exhaustive-deps

	const handleOptionChange = (
		value,
		deliveryDate,
		from,
		to,
		type,
		price,
		priceFormatted = '',
		priceDisplay = null
	) => {
		const deliveryDayValue = `${ deliveryDate }_${ from }-${ to }_${ price }`;
		const numericPrice = Number( price );
		// Use tax-inclusive display price for tab fee calculations; fall back to raw price.
		const displayNumericPrice = priceDisplay !== null ? Number( priceDisplay ) : numericPrice;

		// Build selection data once
		const selectionData = {
			selectedOption: value,
			deliveryDay: deliveryDayValue,
			deliveryDayDate: deliveryDate,
			deliveryDayFrom: from,
			deliveryDayTo: to,
			deliveryDayPrice: price,
			deliveryDayType: type,
		};

		// Update local state
		setSelection( selectionData );

		// Save to session manager (different key format)
		saveDeliveryDay( {
			value: deliveryDayValue,
			date: deliveryDate,
			from,
			to,
			price: numericPrice,
			priceFormatted,
			type,
		} );

		// Notify parent of price change (use tax-inclusive display price so it
		// matches the tax-inclusive baseTabs.base values used in the container).
		onPriceChange( { numeric: displayNumericPrice, formatted: priceFormatted } );

		// Clear dropoff point data
		clearDropoffPoint();
		clearDropoffPointExtensionData( setExtensionData );

		// Update cart fees on backend (debounced to avoid WC loading overlay).
		debouncedExtensionCartUpdate( price, type );
	};

	if ( ! Array.isArray( deliveryOptions ) || deliveryOptions.length === 0 ) {
		return null;
	}

	return (
		<div className="postnl-block-container">
			{ deliveryOptions.length > 0 && (
				<ul className="postnl_delivery_day_list postnl_list">
					{ deliveryOptions.map( ( delivery, index ) => {
						return (
							<li key={ index }>
								{ isDeliveryDaysEnabled && (
									<div className="list_title">
										<span>
											{ delivery.display_date }{ ' ' }
											{ delivery.day }
										</span>
									</div>
								) }
								<ul className="postnl_sub_list">
									{ delivery.options.map(
										( option, optionIndex ) => {
											const from = option.from || '';
											const to = option.to || '';
											const optionType =
												option.type || 'Unknown';
											const price = option.price || 0;
											const priceDisplayNumeric = isFreeShipping
												? 0
												: ( option.price_display ?? price );
											const priceDisplayFormatted =
												option.price_formatted || '';
											const value = `${ delivery.date }_${ from }-${ to }_${ price }`;

											const isChecked =
												selection.selectedOption ===
												value;
											const activeClass = isChecked
												? 'active'
												: '';

											let deliveryTime = '';
											if ( optionType === 'Evening' ) {
												deliveryTime = __(
													'Evening',
													'postnl-for-woocommerce'
												);
											} else if (
												optionType === 'Morning' ||
												optionType === '08:00-12:00'
											) {
												deliveryTime = __(
													'Morning',
													'postnl-for-woocommerce'
												);
											}

											return (
												<li
													key={ optionIndex }
													className={ `${ optionType } ${ activeClass }` }
													data-date={ delivery.date }
													data-from={ from }
													data-to={ to }
													data-type={ optionType }
												>
													<label
														className="postnl_sub_radio_label"
														htmlFor={ `delivery_day_${ value }` }
													>
														<input
															type="radio"
															id={ `delivery_day_${ value }` }
															name="delivery_day"
															className="postnl_sub_radio"
															value={ value }
															checked={
																isChecked
															}
															onChange={ () =>
																handleOptionChange(
																	value,
																	delivery.date,
																	from,
																	to,
																	optionType,
																	isFreeShipping
																		? 0
																		: price,
																	isFreeShipping
																		? ''
																		: priceDisplayFormatted,
																	priceDisplayNumeric
																)
															}
														/>
														{ priceDisplayFormatted &&
															price > 0 &&
															! isFreeShipping && (
																<i>
																	+
																	{
																		priceDisplayFormatted
																	}
																</i>
															) }
														<i>{ deliveryTime }</i>
														<span>
															{ ! isDeliveryDaysEnabled
																? __(
																		'As soon as possible',
																		'postnl-for-woocommerce'
																  )
																: `${ from } - ${ to }` }
														</span>
													</label>
												</li>
											);
										}
									) }
								</ul>
							</li>
						);
					} ) }
				</ul>
			) }
		</div>
	);
};
