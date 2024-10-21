/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { RadioControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';

/**
 * Delivery Day Block Component
 */
export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;
	const [deliveryOptions, setDeliveryOptions] = useState([]);
	const [selectedOption, setSelectedOption] = useState('');
	const [loading, setLoading] = useState(true);
	const [updating, setUpdating] = useState(false);
	const [error, setError] = useState('');

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setSelectedOption('');
		setExtensionData('postnl_selected_option', '');
		setExtensionData('postnl_delivery_day_date', '');
		setExtensionData('postnl_delivery_day_from', '');
		setExtensionData('postnl_delivery_day_to', '');
		setExtensionData('postnl_delivery_day_type', '');
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


		axios.post(window.postnl_ajax_object.ajax_url, formData, {
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
		})
			.then(response => {
				if (response.data.success) {
					const newDeliveryOptions = response.data.data.delivery_options; // Corrected path

					if (!newDeliveryOptions) {
						throw new Error('Delivery options are undefined.');
					}

					if (!Array.isArray(newDeliveryOptions)) {
						throw new Error('Delivery options is not an array.');
					}

					setDeliveryOptions(newDeliveryOptions);

					// Set a default selected option if available
					if (newDeliveryOptions.length > 0) {
						const firstDelivery = newDeliveryOptions[0];
						if (Array.isArray(firstDelivery.options) && firstDelivery.options.length > 0) { // Updated here
							const firstOption = firstDelivery.options[0]; // Updated here
							const optionType = firstOption.type || 'Unknown'; // Updated here
							const price = firstOption.price || 0; // Added price
							const defaultValue = `${firstDelivery.date}_${firstOption.from}-${firstOption.to}_${optionType}`; // Updated here
							setSelectedOption(defaultValue);
							setExtensionData('postnl_selected_option', defaultValue);

							// Set hidden fields via extension data
							setExtensionData('postnl_delivery_day_date', firstDelivery.date); // Updated here
							setExtensionData('postnl_delivery_day_from', firstOption.from); // Updated here
							setExtensionData('postnl_delivery_day_to', firstOption.to); // Updated here
							setExtensionData('postnl_delivery_day_type', optionType); // Updated here
							// Assuming price is handled separately or is part of 'optionType'
						} else {
							// If no options are available, clear selections
							clearSelections();
						}
					} else {
						// If no delivery options are available, clear selections
						clearSelections();
					}
				} else {
					throw new Error(response.data.message || 'Error fetching delivery options.');
				}
			})
			.catch(error => {
				console.error('AJAX error:', error);
				setError(error.message || 'An unexpected error occurred.');
			})
			.finally(() => {
				setUpdating(false);
			});
	}, [setExtensionData]);

	/**
	 * Initial Load: Set delivery options from localized data
	 */
	useEffect(() => {
		if (window.postnl_ajax_object && window.postnl_ajax_object.deliveryOptions) {
			const initialDeliveryOptions = window.postnl_ajax_object.deliveryOptions;

			if (Array.isArray(initialDeliveryOptions) && initialDeliveryOptions.length > 0) {
				setDeliveryOptions(initialDeliveryOptions);

				const firstDelivery = initialDeliveryOptions[0];
				if (Array.isArray(firstDelivery.options) && firstDelivery.options.length > 0) { // Updated here
					const firstOption = firstDelivery.options[0]; // Updated here
					const optionType = firstOption.type || 'Unknown';
					const price = firstOption.price || 0;
					const defaultValue = `${firstDelivery.date}_${firstOption.from}-${firstOption.to}_${optionType}`; // Updated here
					setSelectedOption(defaultValue);
					setExtensionData('postnl_selected_option', defaultValue);

					// Set hidden fields via extension data
					setExtensionData('postnl_delivery_day_date', firstDelivery.date); // Updated here
					setExtensionData('postnl_delivery_day_from', firstOption.from); // Updated here
					setExtensionData('postnl_delivery_day_to', firstOption.to); // Updated here
					setExtensionData('postnl_delivery_day_type', optionType); // Updated here
					// Assuming price is handled separately or is part of 'optionType'
				}
			}
			setLoading(false);
		} else {
			setLoading(false);
		}
	}, [setExtensionData]);

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
	 */
	const handleOptionChange = (value, deliveryDate, from, to, type) => {
		setSelectedOption(value);
		setExtensionData('postnl_selected_option', value);
		setExtensionData('postnl_delivery_day_date', deliveryDate);
		setExtensionData('postnl_delivery_day_from', from);
		setExtensionData('postnl_delivery_day_to', to);
		setExtensionData('postnl_delivery_day_type', type);
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
			<Notice status="error" isDismissible={ false }>
				{ __( error, 'postnl-for-woocommerce' ) }
			</Notice>
		);
	}

	/**
	 * Render the Delivery Options
	 */
	return (
		<div className="postnl_content" id="postnl_delivery_day_content">
			<ul className="postnl_delivery_day_list postnl_list">
				{deliveryOptions.map((delivery, index) => (
					Array.isArray(delivery.options) && delivery.options.length > 0 && (
						<li key={index}>
							<div className="list_title">
								<span>{`${delivery.date} ${delivery.day}`}</span>
							</div>
							<ul className="postnl_sub_list">
								{delivery.options.map((option, optionIndex) => {
									// Ensure option properties exist
									const from = option.from || '';
									const to = option.to || '';
									const optionType = option.type || 'Unknown';
									const price = option.price || 0;
									const value = `${delivery.date}_${from}-${to}_${optionType}`;

									const isChecked = selectedOption === value || (index === 0 && optionIndex === 0);
									const isActive = selectedOption === value ? 'active' : '';

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
													onChange={() => handleOptionChange(value, delivery.date, from, to, optionType)}
												/>
												{/* Display additional information if needed */}
												{price > 0 && <i>+€{price.toFixed(2)}</i>}
												<i>{delivery_time}</i>
												<span>{`${from} - ${to}`}</span>
											</label>
										</li>
									);
								})}
							</ul>
						</li>
					)
				))}
			</ul>
		</div>
	);
};
