/**
 * External dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';
import { debounce } from "lodash";
import { getSetting } from '@woocommerce/settings';
/**
 * Delivery Day Block Component
 */
export const Block = ({ checkoutExtensionData, isActive  }) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );
	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce((namespace, key, value) => {
			setExtensionData(namespace, key, value);
		}, 1000),
		[setExtensionData]
	);

	const [selectedOption, setSelectedOption] = useState('');
	const [loading, setLoading] = useState(true);
	const [updating, setUpdating] = useState(false);
	const [error, setError] = useState('');

	// Use ref to store delivery options across renders
	const deliveryOptionsRef = useRef([]);

	// State variables for hidden fields based on block.json attributes
	const [deliveryDay, setDeliveryDay] = useState('');
	const [deliveryDayDate, setDeliveryDayDate] = useState('');
	const [deliveryDayFrom, setDeliveryDayFrom] = useState('');
	const [deliveryDayTo, setDeliveryDayTo] = useState('');
	const [deliveryDayPrice, setDeliveryDayPrice] = useState('');
	const [deliveryDayType, setDeliveryDayType] = useState('');


	useEffect(() => {
		setExtensionData('postnl', 'deliveryDay', deliveryDay);
		debouncedSetExtensionData('postnl', 'deliveryDay', deliveryDay);
	}, [deliveryDay, setExtensionData, debouncedSetExtensionData]);
	useEffect(() => {
		setExtensionData('postnl', 'deliveryDayDate', deliveryDayDate);
		debouncedSetExtensionData('postnl', 'deliveryDayDate', deliveryDayDate);
	}, [deliveryDayDate, setExtensionData, debouncedSetExtensionData]);
	useEffect(() => {
		setExtensionData('postnl', 'deliveryDayFrom', deliveryDayFrom);
		debouncedSetExtensionData('postnl', 'deliveryDayFrom', deliveryDayFrom);
	}, [deliveryDayFrom, setExtensionData, debouncedSetExtensionData]);
	useEffect(() => {
		setExtensionData('postnl', 'deliveryDayTo', deliveryDayTo);
		debouncedSetExtensionData('postnl', 'deliveryDayTo', deliveryDayTo);
	}, [deliveryDayTo, setExtensionData, debouncedSetExtensionData]);
	useEffect(() => {
		setExtensionData('postnl', 'deliveryDayPrice', deliveryDayPrice);
		debouncedSetExtensionData('postnl', 'deliveryDayPrice', deliveryDayPrice);
	}, [deliveryDayPrice, setExtensionData, debouncedSetExtensionData]);
	useEffect(() => {
		setExtensionData('postnl', 'deliveryDayType', deliveryDayType);
		debouncedSetExtensionData('postnl', 'deliveryDayType', deliveryDayType);
	}, [deliveryDayType, setExtensionData, debouncedSetExtensionData]);


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
			// Clear hidden fields
			clearSelections();
		}
	}, [ isActive ] );

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setSelectedOption('');
		setExtensionData('postnl_selected_option', '');
		setDeliveryDay('');
		setDeliveryDayDate('');
		setDeliveryDayFrom('');
		setDeliveryDayTo('');
		setDeliveryDayPrice('');
		setDeliveryDayType('');

		setExtensionData('deliveryDay', '');
		setExtensionData('deliveryDayDate', '');
		setExtensionData('deliveryDayFrom', '');
		setExtensionData('deliveryDayTo', '');
		setExtensionData('deliveryDayPrice', '');
		setExtensionData('deliveryDayType', '');
	};

	/**
	 * Fetch delivery options via AJAX
	 */
	const fetchDeliveryOptions = useCallback(() => {
		setUpdating(true);
		setError('');

		const formData = new URLSearchParams();
		formData.append('action', 'postnl_get_delivery_options');
		formData.append('nonce', postnlData.nonce);
		axios
			.post(postnlData.ajax_url, formData, {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			})
			.then((response) => {
				if (response.data.success) {
					const newDeliveryOptions = response.data.data.delivery_options;


					if (!newDeliveryOptions || !Array.isArray(newDeliveryOptions) || newDeliveryOptions.length === 0) {
						// Clear delivery options
						deliveryOptionsRef.current = [];
						clearSelections();
					} else {
						// Store delivery options in ref
						deliveryOptionsRef.current = newDeliveryOptions;
					}
				} else {
					// Handle error
					deliveryOptionsRef.current = [];
					clearSelections();
				}
			})
			.catch((error) => {
				deliveryOptionsRef.current = [];
				clearSelections();
			})
			.finally(() => {
				setUpdating(false);
				setLoading(false);
			});
	}, [setExtensionData]);

	/**
	 * Initial Load: Fetch delivery options or use existing ones
	 */
	useEffect(() => {
		if (deliveryOptionsRef.current.length > 0) {
			// Use existing delivery options
			setLoading(false);
		} else if (window.postnl_ajax_object && window.postnl_ajax_object.deliveryOptions) {
			const initialDeliveryOptions = window.postnl_ajax_object.deliveryOptions;

			if (Array.isArray(initialDeliveryOptions) && initialDeliveryOptions.length > 0) {
				// Store delivery options in ref
				deliveryOptionsRef.current = initialDeliveryOptions;

				const firstDelivery = initialDeliveryOptions[0];
				if (Array.isArray(firstDelivery.options) && firstDelivery.options.length > 0) {
					const firstOption = firstDelivery.options[0];
					const optionType = firstOption.type || 'Unknown';
					const price = firstOption.price || 0;
					const defaultValue = `${firstDelivery.date}_${firstOption.from}-${firstOption.to}_${price}`;
					setSelectedOption(defaultValue);
					setExtensionData('postnl_selected_option', defaultValue);

					setDeliveryDayDate(firstDelivery.date);
					setDeliveryDayFrom(firstOption.from);
					setDeliveryDayTo(firstOption.to);
					setDeliveryDayPrice(price);
					setDeliveryDayType(optionType);

					const deliveryDayValue = `${firstDelivery.date}_${firstOption.from}-${firstOption.to}_${price}`;
					setDeliveryDay(deliveryDayValue);
					setExtensionData('deliveryDay', deliveryDayValue);

					setExtensionData('deliveryDayDate', firstDelivery.date);
					setExtensionData('deliveryDayFrom', firstOption.from);
					setExtensionData('deliveryDayTo', firstOption.to);
					setExtensionData('deliveryDayPrice', price.toString());
					setExtensionData('deliveryDayType', optionType);
				}
			}
			setLoading(false);
		} else {
			fetchDeliveryOptions();
		}
	}, [fetchDeliveryOptions, setExtensionData]);

	/**
	 * Listen for the custom event to fetch updated delivery options
	 */
	useEffect(() => {
		const handleAddressUpdated = () => {
			fetchDeliveryOptions();
		};

		window.addEventListener('postnl_address_updated', handleAddressUpdated);

		return () => {
			window.removeEventListener('postnl_address_updated', handleAddressUpdated);
		};
	}, [fetchDeliveryOptions]);

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
	const handleOptionChange = async (value, deliveryDate, from, to, type, price) => {
		setSelectedOption(value);
		setExtensionData('postnl_selected_option', value);

		setDeliveryDayDate(deliveryDate);
		setDeliveryDayFrom(from);
		setDeliveryDayTo(to);
		setDeliveryDayPrice(price);
		setDeliveryDayType(type);

		const deliveryDayValue = `${deliveryDate}_${from}-${to}_${price}`;
		setDeliveryDay(deliveryDayValue);
		setExtensionData('deliveryDay', deliveryDayValue);

		setExtensionData('deliveryDayDate', deliveryDate);
		setExtensionData('deliveryDayFrom', from);
		setExtensionData('deliveryDayTo', to);
		setExtensionData('deliveryDayPrice', price.toString());
		setExtensionData('deliveryDayType', type);

		// Call extensionCartUpdate to update the cart total
		try {
			const { extensionCartUpdate } = window.wc.blocksCheckout || {};

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
		}
	};

	/**
	 * Render Loading or Updating Spinner
	 */
	if (loading || updating) {
		return <Spinner />;
	}

	/**
	 * Render Error Message
	 */
	if (deliveryOptionsRef.current.length === 0) {
		// No delivery options available; do not render anything
		return null;
	}

	/**
	 * Render the Delivery Options with Hidden Inputs
	 */
	return (
		<div>
			<ul className="postnl_delivery_day_list postnl_list">
				{deliveryOptionsRef.current.map((delivery, index) =>
					Array.isArray(delivery.options) && delivery.options.length > 0 ? (
						<li key={index}>
							<div className="list_title">
								<span>{`${delivery.date} ${delivery.day}`}</span>
							</div>
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
									} else {
										delivery_time = __(optionType, 'postnl-for-woocommerce');
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
											<label className="postnl_sub_radio_label" htmlFor={`delivery_day_${value}`}>
												<input
													type="radio"
													id={`delivery_day_${value}`}
													name="delivery_day"
													className="postnl_sub_radio"
													value={value}
													checked={isChecked}
													onChange={() =>
														handleOptionChange(value, delivery.date, from, to, optionType, price)
													}
												/>
												{price > 0 && <i>+€{price.toFixed(2)}</i>}
												<i>{delivery_time}</i>
												<span>{`${from} - ${to}`}</span>
											</label>
										</li>
									);
								})}
							</ul>
						</li>
					) : null
				)}
			</ul>
			<input type="hidden" name="deliveryDay" id="deliveryDay" value={deliveryDay} />
			<input type="hidden" name="deliveryDayDate" id="deliveryDayDate" value={deliveryDayDate} />
			<input type="hidden" name="deliveryDayFrom" id="deliveryDayFrom" value={deliveryDayFrom} />
			<input type="hidden" name="deliveryDayTo" id="deliveryDayTo" value={deliveryDayTo} />
			<input type="hidden" name="deliveryDayPrice" id="deliveryDayPrice" value={deliveryDayPrice} />
			<input type="hidden" name="deliveryDayType" id="deliveryDayType" value={deliveryDayType} />
		</div>
	);
};
