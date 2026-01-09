/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
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

export const Block = ( {
	checkoutExtensionData,
	isActive,
	deliveryOptions,
	isDeliveryDaysEnabled,
	onPriceChange = () => {},
} ) => {
	const { setExtensionData } = checkoutExtensionData;

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce( ( namespace, key, value ) => {
			setExtensionData( namespace, key, value );
		}, 250 ),
		[ setExtensionData ]
	);

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

	// Sync with extension data when selection changes
	useEffect( () => {
		debouncedSetExtensionData(
			'postnl',
			'deliveryDay',
			selection.deliveryDay
		);
		debouncedSetExtensionData(
			'postnl',
			'deliveryDayDate',
			selection.deliveryDayDate
		);
		debouncedSetExtensionData(
			'postnl',
			'deliveryDayFrom',
			selection.deliveryDayFrom
		);
		debouncedSetExtensionData(
			'postnl',
			'deliveryDayTo',
			selection.deliveryDayTo
		);
		debouncedSetExtensionData(
			'postnl',
			'deliveryDayPrice',
			selection.deliveryDayPrice.toString()
		);
		debouncedSetExtensionData(
			'postnl',
			'deliveryDayType',
			selection.deliveryDayType
		);
	}, [ selection, debouncedSetExtensionData ] );

	// Clear all delivery day selections
	const clearSelections = useCallback(
		( clearSession = false ) => {
			setSelection( {
				selectedOption: '',
				deliveryDay: '',
				deliveryDayDate: '',
				deliveryDayFrom: '',
				deliveryDayTo: '',
				deliveryDayPrice: '',
				deliveryDayType: '',
			} );

			if ( clearSession ) {
				clearDeliveryDay();
			}

			// Clear extension data
			setExtensionData( 'postnl', 'deliveryDay', '' );
			setExtensionData( 'postnl', 'deliveryDayDate', '' );
			setExtensionData( 'postnl', 'deliveryDayFrom', '' );
			setExtensionData( 'postnl', 'deliveryDayTo', '' );
			setExtensionData( 'postnl', 'deliveryDayPrice', '' );
			setExtensionData( 'postnl', 'deliveryDayType', '' );
			onPriceChange( { numeric: 0, formatted: '' } );
		},
		[ setExtensionData, onPriceChange ]
	);

	// Handle tab activation and auto-select first option
	useEffect( () => {
		if (
			! isActive ||
			! Array.isArray( deliveryOptions ) ||
			deliveryOptions.length === 0
		) {
			clearSelections( true );
			return;
		}

		// If active and no option selected, select the default (first) option
		if ( isActive && ! selection.selectedOption ) {
			const firstDelivery = deliveryOptions[ 0 ];
			if (
				Array.isArray( firstDelivery.options ) &&
				firstDelivery.options.length > 0
			) {
				const firstOption = firstDelivery.options[ 0 ];
				handleOptionChange(
					`${ firstDelivery.date }_${ firstOption.from }-${ firstOption.to }_${ firstOption.price }`,
					firstDelivery.date,
					firstOption.from,
					firstOption.to,
					firstOption.type || 'Unknown',
					firstOption.price || 0,
					firstOption.price_formatted || ''
				);
				onPriceChange( {
					numeric: Number( firstOption.price || 0 ),
					formatted: firstOption.price_formatted || '',
				} );
			}
		}
	}, [ isActive, deliveryOptions ] );

	const handleOptionChange = async (
		value,
		deliveryDate,
		from,
		to,
		type,
		price,
		priceFormatted = ''
	) => {
		const deliveryDayValue = `${ deliveryDate }_${ from }-${ to }_${ price }`;

		// Update local state
		setSelection( {
			selectedOption: value,
			deliveryDay: deliveryDayValue,
			deliveryDayDate: deliveryDate,
			deliveryDayFrom: from,
			deliveryDayTo: to,
			deliveryDayPrice: price,
			deliveryDayType: type,
		} );

		// Save to centralized session manager
		saveDeliveryDay( {
			value: deliveryDayValue,
			date: deliveryDate,
			from,
			to,
			price: Number( price ),
			priceFormatted,
			type,
		} );

		// Update extension data immediately (not debounced for user action)
		setExtensionData( 'postnl', 'deliveryDay', deliveryDayValue );
		setExtensionData( 'postnl', 'deliveryDayDate', deliveryDate );
		setExtensionData( 'postnl', 'deliveryDayFrom', from );
		setExtensionData( 'postnl', 'deliveryDayTo', to );
		setExtensionData( 'postnl', 'deliveryDayPrice', price.toString() );
		setExtensionData( 'postnl', 'deliveryDayType', type );

		// Notify parent of price change
		onPriceChange( {
			numeric: Number( price ),
			formatted: priceFormatted,
		} );

		// Clear dropoff point data (using centralized session manager)
		clearDropoffPoint();

		// Clear dropoff extension data
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
		setExtensionData( 'postnl', 'dropoffPointsType', '' );
		setExtensionData( 'postnl', 'dropoffPointsDistance', null );

		// Update cart fees on backend
		try {
			const { extensionCartUpdate } = window.wc?.blocksCheckout || {};
			if ( typeof extensionCartUpdate === 'function' ) {
				await extensionCartUpdate( {
					namespace: 'postnl',
					data: {
						action: 'update_delivery_fee',
						price,
						type,
					},
				} );
			}
		} catch ( error ) {
			// Handle error silently
		}
	};

	if ( ! Array.isArray( deliveryOptions ) || deliveryOptions.length === 0 ) {
		return null;
	}

	return (
		<div className="postnl-block-container">
			{ deliveryOptions.length > 0 && (
				<div>
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
												const priceDisplayFormatted =
													option.price_formatted ||
													'';
												const value = `${ delivery.date }_${ from }-${ to }_${ price }`;

												const isChecked =
													selection.selectedOption ===
													value;
												const activeClass = isChecked
													? 'active'
													: '';

												let deliveryTime = '';
												if (
													optionType === 'Evening'
												) {
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
														data-date={
															delivery.date
														}
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
																		price,
																		priceDisplayFormatted
																	)
																}
															/>
															{ priceDisplayFormatted &&
																price > 0 && (
																	<i>
																		+
																		{
																			priceDisplayFormatted
																		}
																	</i>
																) }
															<i>
																{ deliveryTime }
															</i>
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
					<input
						type="hidden"
						name="deliveryDay"
						id="deliveryDay"
						value={ selection.deliveryDay }
					/>
					<input
						type="hidden"
						name="deliveryDayDate"
						id="deliveryDayDate"
						value={ selection.deliveryDayDate }
					/>
					<input
						type="hidden"
						name="deliveryDayFrom"
						id="deliveryDayFrom"
						value={ selection.deliveryDayFrom }
					/>
					<input
						type="hidden"
						name="deliveryDayTo"
						id="deliveryDayTo"
						value={ selection.deliveryDayTo }
					/>
					<input
						type="hidden"
						name="deliveryDayPrice"
						id="deliveryDayPrice"
						value={ selection.deliveryDayPrice }
					/>
					<input
						type="hidden"
						name="deliveryDayType"
						id="deliveryDayType"
						value={ selection.deliveryDayType }
					/>
				</div>
			) }
		</div>
	);
};
