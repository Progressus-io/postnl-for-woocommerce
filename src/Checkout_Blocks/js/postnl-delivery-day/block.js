/**
 * External dependencies
 */
import {useEffect, useState, useCallback} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {debounce} from 'lodash';

export const Block = ({checkoutExtensionData, isActive, deliveryOptions}) => {
	const {setExtensionData} = checkoutExtensionData;

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce((namespace, key, value) => {
			setExtensionData(namespace, key, value);
		}, 250),
		[setExtensionData]
	);

	// Initialize state from sessionStorage if available
	const [selectedOption, setSelectedOption] = useState(() => {
		return sessionStorage.getItem('postnl_selected_option') || '';
	});
	const [deliveryDay, setDeliveryDay] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDay') || '';
	});
	const [deliveryDayDate, setDeliveryDayDate] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDayDate') || '';
	});
	const [deliveryDayFrom, setDeliveryDayFrom] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDayFrom') || '';
	});
	const [deliveryDayTo, setDeliveryDayTo] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDayTo') || '';
	});
	const [deliveryDayPrice, setDeliveryDayPrice] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDayPrice') || '';
	});
	const [deliveryDayType, setDeliveryDayType] = useState(() => {
		return sessionStorage.getItem('postnl_deliveryDayType') || '';
	});

	// Sync states with extension data
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDay', deliveryDay);
	}, [deliveryDay, debouncedSetExtensionData]);
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDayDate', deliveryDayDate);
	}, [deliveryDayDate, debouncedSetExtensionData]);
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDayFrom', deliveryDayFrom);
	}, [deliveryDayFrom, debouncedSetExtensionData]);
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDayTo', deliveryDayTo);
	}, [deliveryDayTo, debouncedSetExtensionData]);
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDayPrice', deliveryDayPrice);
	}, [deliveryDayPrice, debouncedSetExtensionData]);
	useEffect(() => {
		debouncedSetExtensionData('postnl', 'deliveryDayType', deliveryDayType);
	}, [deliveryDayType, debouncedSetExtensionData]);

	// Clear all delivery day selections
	const clearSelections = (clearSession = false) => {
		setSelectedOption('');
		if (clearSession) {
			sessionStorage.removeItem('postnl_selected_option');
		}
		setDeliveryDay('');
		setDeliveryDayDate('');
		setDeliveryDayFrom('');
		setDeliveryDayTo('');
		setDeliveryDayPrice('');
		setDeliveryDayType('');
		if (clearSession) {
			sessionStorage.removeItem('postnl_deliveryDay');
			sessionStorage.removeItem('postnl_deliveryDayDate');
			sessionStorage.removeItem('postnl_deliveryDayFrom');
			sessionStorage.removeItem('postnl_deliveryDayTo');
			sessionStorage.removeItem('postnl_deliveryDayPrice');
			sessionStorage.removeItem('postnl_deliveryDayType');
		}
		setExtensionData('postnl', 'deliveryDay', '');
		setExtensionData('postnl', 'deliveryDayDate', '');
		setExtensionData('postnl', 'deliveryDayFrom', '');
		setExtensionData('postnl', 'deliveryDayTo', '');
		setExtensionData('postnl', 'deliveryDayPrice', '');
		setExtensionData('postnl', 'deliveryDayType', '');
	};

	// Determine ASAP mode based on the first delivery option:
	const isASAPMode =
		Array.isArray(deliveryOptions) &&
		deliveryOptions.length > 0 &&
		Array.isArray(deliveryOptions[0].options) &&
		deliveryOptions[0].options.length > 0 &&
		deliveryOptions[0].options[0].type === 'ASAP';

	useEffect(() => {
		if (!isActive || !Array.isArray(deliveryOptions) || deliveryOptions.length === 0) {
			// If tab is not active or no options, clear selections
			clearSelections(true);
			return;
		}
		// If ASAP mode is active, clear selections and do not auto-select.
		if (isASAPMode) {
			clearSelections(true);
			return;
		}

		// If active and no option selected, select the default (first) option if available
		if (isActive && !selectedOption) {
			const firstDelivery = deliveryOptions[0];
			if (Array.isArray(firstDelivery.options) && firstDelivery.options.length > 0) {
				const firstOption = firstDelivery.options[0];
				handleOptionChange(
					`${firstDelivery.date}_${firstOption.from}-${firstOption.to}_${firstOption.price}`,
					firstDelivery.date,
					firstOption.from,
					firstOption.to,
					firstOption.type || 'Unknown',
					firstOption.price || 0
				);
			}
		}
	}, [isActive, deliveryOptions]);

	const handleOptionChange = async (value, deliveryDate, from, to, type, price) => {

		// In ASAP mode, do not update any state or hidden fields.
		if (isASAPMode) {
			setSelectedOption(value);
			sessionStorage.setItem('postnl_selected_option', value);
			return;
		}

		setSelectedOption(value);
		sessionStorage.setItem('postnl_selected_option', value);

		setDeliveryDayDate(deliveryDate);
		sessionStorage.setItem('postnl_deliveryDayDate', deliveryDate);

		setDeliveryDayFrom(from);
		sessionStorage.setItem('postnl_deliveryDayFrom', from);

		setDeliveryDayTo(to);
		sessionStorage.setItem('postnl_deliveryDayTo', to);

		setDeliveryDayPrice(price);
		sessionStorage.setItem('postnl_deliveryDayPrice', price.toString());

		setDeliveryDayType(type);
		sessionStorage.setItem('postnl_deliveryDayType', type);

		const deliveryDayValue = `${deliveryDate}_${from}-${to}_${price}`;
		setDeliveryDay(deliveryDayValue);
		sessionStorage.setItem('postnl_deliveryDay', deliveryDayValue);

		setExtensionData('postnl', 'deliveryDay', deliveryDayValue);
		setExtensionData('postnl', 'deliveryDayDate', deliveryDate);
		setExtensionData('postnl', 'deliveryDayFrom', from);
		setExtensionData('postnl', 'deliveryDayTo', to);
		setExtensionData('postnl', 'deliveryDayPrice', price.toString());
		setExtensionData('postnl', 'deliveryDayType', type);

		// Clear dropoff point data
		sessionStorage.removeItem('postnl_dropoffPoints');
		sessionStorage.removeItem('postnl_dropoffPointsAddressCompany');
		sessionStorage.removeItem('postnl_dropoffPointsAddress1');
		sessionStorage.removeItem('postnl_dropoffPointsAddress2');
		sessionStorage.removeItem('postnl_dropoffPointsCity');
		sessionStorage.removeItem('postnl_dropoffPointsPostcode');
		sessionStorage.removeItem('postnl_dropoffPointsCountry');
		sessionStorage.removeItem('postnl_dropoffPointsPartnerID');
		sessionStorage.removeItem('postnl_dropoffPointsDate');
		sessionStorage.removeItem('postnl_dropoffPointsTime');
		sessionStorage.removeItem('postnl_dropoffPointsDistance');

		setExtensionData('postnl', 'dropoffPoints', '');
		setExtensionData('postnl', 'dropoffPointsAddressCompany', '');
		setExtensionData('postnl', 'dropoffPointsAddress1', '');
		setExtensionData('postnl', 'dropoffPointsAddress2', '');
		setExtensionData('postnl', 'dropoffPointsCity', '');
		setExtensionData('postnl', 'dropoffPointsPostcode', '');
		setExtensionData('postnl', 'dropoffPointsCountry', '');
		setExtensionData('postnl', 'dropoffPointsPartnerID', '');
		setExtensionData('postnl', 'dropoffPointsDate', '');
		setExtensionData('postnl', 'dropoffPointsTime', '');
		setExtensionData('postnl', 'dropoffPointsDistance', null);

		try {
			const {extensionCartUpdate} = window.wc.blocksCheckout || {};
			if (typeof extensionCartUpdate === 'function') {
				await extensionCartUpdate({
					namespace: 'postnl',
					data: {
						action: 'update_delivery_fee',
						price: price,
						type: type,
					},
				});
			}
		} catch (error) {
			// Handle error if needed
		}
	};

	if (!Array.isArray(deliveryOptions) || deliveryOptions.length === 0) {
		return null;
	}

	return (
		<div className="postnl-block-container">
			{deliveryOptions.length > 0 && (
				<div>
					<ul className="postnl_delivery_day_list postnl_list">
						{deliveryOptions.map((delivery, index) => {
							// Determine if the current delivery option is ASAP
							const isASAP =
								Array.isArray(delivery.options) &&
								delivery.options.length > 0 &&
								delivery.options[0].type === 'ASAP';

							return (
								<li key={index}>
									{/* Only render the list title if this is not an ASAP option */}
									{!isASAP && (
										<div className="list_title">
											<span>{`${delivery.date} ${delivery.day}`}</span>
										</div>
									)}
									<ul className="postnl_sub_list">
										{delivery.options.map((option, optionIndex) => {
											const from = option.from || '';
											const to = option.to || '';
											const optionType = option.type || 'Unknown';
											const price = option.price || 0;
											const value = `${delivery.date}_${from}-${to}_${price}`;

											const isChecked = selectedOption === value;
											const isActive = isChecked ? 'active' : '';

											let delivery_time = '';
											if (optionType === 'Evening') {
												delivery_time = __('Evening', 'postnl-for-woocommerce');
											} else if (optionType === 'Morning' || optionType === '08:00-12:00') {
												delivery_time = __('Morning', 'postnl-for-woocommerce');
											}

											return (
												<li
													key={optionIndex}
													className={`${optionType} ${isActive}`}
													data-date={delivery.date}
													data-from={from}
													data-to={to}
													data-type={optionType}
												>
													<label
														className="postnl_sub_radio_label"
														htmlFor={`delivery_day_${value}`}
													>
														<input
															type="radio"
															id={`delivery_day_${value}`}
															name="delivery_day"
															className="postnl_sub_radio"
															value={value}
															checked={isChecked}
															onChange={() =>
																handleOptionChange(
																	value,
																	delivery.date,
																	from,
																	to,
																	optionType,
																	price
																)
															}
														/>
														{price > 0 && <i>+€{price.toFixed(2)}</i>}
														{/* Only render delivery_time if not ASAP */}
														{!isASAP && <i>{delivery_time}</i>}
														{/* For ASAP, show only one label; otherwise, show from-to range */}
														<span>{isASAP ? from : `${from} - ${to}`}</span>
													</label>
												</li>
											);
										})}
									</ul>
								</li>
							);
						})}
					</ul>
					<input type="hidden" name="deliveryDay" id="deliveryDay" value={deliveryDay} />
					<input
						type="hidden"
						name="deliveryDayDate"
						id="deliveryDayDate"
						value={deliveryDayDate}
					/>
					<input
						type="hidden"
						name="deliveryDayFrom"
						id="deliveryDayFrom"
						value={deliveryDayFrom}
					/>
					<input
						type="hidden"
						name="deliveryDayTo"
						id="deliveryDayTo"
						value={deliveryDayTo}
					/>
					<input
						type="hidden"
						name="deliveryDayPrice"
						id="deliveryDayPrice"
						value={deliveryDayPrice}
					/>
					<input
						type="hidden"
						name="deliveryDayType"
						id="deliveryDayType"
						value={deliveryDayType}
					/>
				</div>
			)}
		</div>
	);
};
