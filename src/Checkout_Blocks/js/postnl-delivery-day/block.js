/**
 * External dependencies
 */
import { useEffect, useState, useCallback, useRef } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';

/**
 * Delivery Day Block Component
 */
export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;
	const [selectedOption, setSelectedOption] = useState('');
	const [loading, setLoading] = useState(true);
	const [updating, setUpdating] = useState(false);
	const [error, setError] = useState('');

	// Use ref to store delivery options across renders
	const deliveryOptionsRef = useRef([]);

	// State variables for hidden fields based on block.json attributes
	const [deliveryDayDate, setDeliveryDayDate] = useState('');
	const [deliveryDayFrom, setDeliveryDayFrom] = useState('');
	const [deliveryDayTo, setDeliveryDayTo] = useState('');
	const [deliveryDayPrice, setDeliveryDayPrice] = useState('');
	const [deliveryDayType, setDeliveryDayType] = useState('');

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setSelectedOption('');
		setExtensionData('postnl_selected_option', '');
		setDeliveryDayDate('');
		setDeliveryDayFrom('');
		setDeliveryDayTo('');
		setDeliveryDayPrice('');
		setDeliveryDayType('');

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
		formData.append('nonce', window.postnl_ajax_object.nonce);

		axios
			.post(window.postnl_ajax_object.ajax_url, formData, {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			})
			.then((response) => {
				if (response.data.success) {
					const newDeliveryOptions = response.data.data.delivery_options;

					if (!newDeliveryOptions) {
						throw new Error('Delivery options are undefined.');
					}

					if (!Array.isArray(newDeliveryOptions)) {
						throw new Error('Delivery options is not an array.');
					}

					// Store delivery options in ref
					deliveryOptionsRef.current = newDeliveryOptions;

					if (newDeliveryOptions.length > 0) {
						const firstDelivery = newDeliveryOptions[0];
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

							setExtensionData('deliveryDayDate', firstDelivery.date);
							setExtensionData('deliveryDayFrom', firstOption.from);
							setExtensionData('deliveryDayTo', firstOption.to);
							setExtensionData('deliveryDayPrice', price.toString());
							setExtensionData('deliveryDayType', optionType);
						} else {
							clearSelections();
						}
					} else {
						clearSelections();
					}
				} else {
					throw new Error(response.data.message || 'Error fetching delivery options.');
				}
			})
			.catch((error) => {
				console.error('AJAX error:', error);
				setError(error.message || 'An unexpected error occurred.');
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
			console.error('Error updating cart:', error);
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
	if (error) {
		return (
			<Notice status="error" isDismissible={false}>
				{__(error, 'postnl-for-woocommerce')}
			</Notice>
		);
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
			<input type="hidden" name="deliveryDayDate" id="deliveryDayDate" value={deliveryDayDate} />
			<input type="hidden" name="deliveryDayFrom" id="deliveryDayFrom" value={deliveryDayFrom} />
			<input type="hidden" name="deliveryDayTo" id="deliveryDayTo" value={deliveryDayTo} />
			<input type="hidden" name="deliveryDayPrice" id="deliveryDayPrice" value={deliveryDayPrice} />
			<input type="hidden" name="deliveryDayType" id="deliveryDayType" value={deliveryDayType} />
		</div>
	);
};
