/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { RadioControl, Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';

/**
 * Utility Functions
 */
const Utils = {
	maybe_convert_km: (distanceInMeters) => {
		if (distanceInMeters >= 1000) {
			return `${(distanceInMeters / 1000).toFixed(1)} km`;
		}
		return `${distanceInMeters} m`;
	}
};

/**
 * Dropoff Points Block Component
 */
export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;
	const [dropoffOptions, setDropoffOptions] = useState([]);
	const [selectedOption, setSelectedOption] = useState('');
	const [loading, setLoading] = useState(true);
	const [updating, setUpdating] = useState(false);
	const [error, setError] = useState('');

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setSelectedOption('');
		setExtensionData('postnl_dropoff_selected_option', '');
		setExtensionData('postnl_dropoff_address_company', '');
		setExtensionData('postnl_dropoff_address1', '');
		setExtensionData('postnl_dropoff_address2', '');
		setExtensionData('postnl_dropoff_city', '');
		setExtensionData('postnl_dropoff_postcode', '');
		setExtensionData('postnl_dropoff_country', '');
		setExtensionData('postnl_dropoff_partner_id', '');
		setExtensionData('postnl_dropoff_date', '');
		setExtensionData('postnl_dropoff_time', '');
		setExtensionData('postnl_dropoff_distance', '');
		setExtensionData('postnl_dropoff_type', '');
	};

	/**
	 * Fetch dropoff options via AJAX
	 */
	const fetchDropoffOptions = useCallback(() => {
		setUpdating(true);
		setError('');

		const formData = new URLSearchParams();
		formData.append('action', 'postnl_get_delivery_options'); // Same handler returns dropoff_options
		formData.append('nonce', window.postnl_ajax_object.nonce);

		console.log('Fetching dropoff options with formData:', formData.toString());

		axios.post(window.postnl_ajax_object.ajax_url, formData, {
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded',
			},
		})
			.then(response => {
				console.log('AJAX response:', response);
				if (response.data.success) {
					const { dropoff_options } = response.data.data;
					console.log('Fetched Dropoff Options:', dropoff_options);

					if (!dropoff_options) {
						throw new Error('Dropoff options are undefined.');
					}

					if (!Array.isArray(dropoff_options)) {
						throw new Error('Dropoff options is not an array.');
					}

					setDropoffOptions(dropoff_options);

					// Set a default selected option if available
					if (dropoff_options.length > 0) {
						const firstDropoff = dropoff_options[0];
						const value = `${firstDropoff.partner_id}-${firstDropoff.loc_code}`;
						setSelectedOption(value);
						setExtensionData('postnl_dropoff_selected_option', value);

						// Set hidden fields via extension data
						setExtensionData('postnl_dropoff_address_company', firstDropoff.address.company);
						setExtensionData('postnl_dropoff_address1', firstDropoff.address.address_1);
						setExtensionData('postnl_dropoff_address2', firstDropoff.address.address_2);
						setExtensionData('postnl_dropoff_city', firstDropoff.address.city);
						setExtensionData('postnl_dropoff_postcode', firstDropoff.address.postcode);
						setExtensionData('postnl_dropoff_country', firstDropoff.address.country);
						setExtensionData('postnl_dropoff_partner_id', firstDropoff.partner_id);
						setExtensionData('postnl_dropoff_date', firstDropoff.date);
						setExtensionData('postnl_dropoff_time', firstDropoff.time);
						setExtensionData('postnl_dropoff_distance', firstDropoff.distance);
						setExtensionData('postnl_dropoff_type', firstDropoff.type);
					} else {
						// If no dropoff options are available, clear selections
						clearSelections();
					}
				} else {
					throw new Error(response.data.message || 'Error fetching dropoff options.');
				}
			})
			.catch(error => {
				console.error('AJAX error:', error);
				setError(error.message || 'An unexpected error occurred.');
			})
			.finally(() => {
				setUpdating(false);
				setLoading(false); // Ensure loading is set to false after fetch
			});
	}, [setExtensionData]);

	/**
	 * Initial Load: Fetch dropoff options via AJAX
	 */
	useEffect(() => {
		fetchDropoffOptions();
	}, [fetchDropoffOptions]);

	/**
	 * Listen for the custom event to fetch updated dropoff options
	 */
	useEffect(() => {
		const handleAddressUpdated = () => {
			console.log('Address updated. Fetching new dropoff options...');
			fetchDropoffOptions();
		};

		window.addEventListener('postnl_address_updated', handleAddressUpdated);

		return () => {
			window.removeEventListener('postnl_address_updated', handleAddressUpdated);
		};
	}, [fetchDropoffOptions]);

	/**
	 * Handle the change of a dropoff option
	 *
	 * @param {string} value - The value of the selected option
	 */
	const handleOptionChange = (value) => {
		setSelectedOption(value);
		setExtensionData('postnl_dropoff_selected_option', value);

		// Find the selected dropoff point.
		const selectedDropoffPoint = dropoffOptions.find((point) => {
			const pointValue = `${point.partner_id}-${point.loc_code}`;
			return pointValue === value;
		});

		// Set hidden field data based on the selected dropoff point, if available.
		if (selectedDropoffPoint) {
			const address = selectedDropoffPoint.address || {};
			setExtensionData('postnl_dropoff_address_company', address.company || '');
			setExtensionData('postnl_dropoff_address1', address.address_1 || '');
			setExtensionData('postnl_dropoff_address2', address.address_2 || '');
			setExtensionData('postnl_dropoff_city', address.city || '');
			setExtensionData('postnl_dropoff_postcode', address.postcode || '');
			setExtensionData('postnl_dropoff_country', address.country || '');
			setExtensionData('postnl_dropoff_partner_id', selectedDropoffPoint.partner_id || '');
			setExtensionData('postnl_dropoff_date', selectedDropoffPoint.date || '');
			setExtensionData('postnl_dropoff_time', selectedDropoffPoint.time || '');
			setExtensionData('postnl_dropoff_distance', selectedDropoffPoint.distance || '');
			setExtensionData('postnl_dropoff_type', selectedDropoffPoint.type || '');
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
			<Notice status="error" isDismissible={ false }>
				{ __( error, 'postnl-for-woocommerce' ) }
			</Notice>
		);
	}

	/**
	 * Render the Dropoff Points
	 */
	return (
		<div className="postnl_content" id="postnl_dropoff_points_content">
			{/* Optional: Display description if needed */}
			{dropoffOptions.some(point => point.show_desc) && (
				<div className="postnl_content_desc">
					{__('Receive shipment at home? Continue ', 'postnl-for-woocommerce')}
					<strong>{__('without', 'postnl-for-woocommerce')}</strong>
					{__(' selecting a Drop-off Point.', 'postnl-for-woocommerce')}
				</div>
			)}
			<ul className="postnl_dropoff_points_list postnl_list">
				{dropoffOptions.map((point, index) => {
					const value = `${point.partner_id}-${point.loc_code}`;
					const address = `${point.address.address_1} ${point.address.address_2}, ${point.address.city}, ${point.address.postcode}`;
					const isChecked = selectedOption === value;
					const isActive = isChecked ? 'active' : '';

					return (
						<li key={index}>
							<div className="list_title">
								<span className="company">{point.address.company}</span>
								<span className="distance">{Utils.maybe_convert_km(point.distance)}</span>
							</div>
							<ul className="postnl_sub_list">
								<li
									className={`${point.type} ${isActive}`}
									data-partner_id={point.partner_id}
									data-loc_code={point.loc_code}
									data-date={point.date}
									data-time={point.time}
									data-distance={point.distance}
									data-type={point.type}
									data-address_company={point.address.company}
									data-address_address_1={point.address.address_1}
									data-address_address_2={point.address.address_2}
									data-address_city={point.address.city}
									data-address_postcode={point.address.postcode}
									data-address_country={point.address.country}
								>
									<label className="postnl_sub_radio_label" htmlFor={`dropoff_points_${value}`}>
										<input
											type="radio"
											id={`dropoff_points_${value}`}
											name="dropoff_points"
											className="postnl_sub_radio"
											value={value}
											checked={isChecked}
											onChange={() => handleOptionChange(value)}
										/>
										{/* Removed price display since 'price' isn't part of dropoff_options */}
										<i>{__(point.type, 'postnl-for-woocommerce')}</i>
										<span>{address}</span>
									</label>
								</li>
							</ul>
						</li>
					);
				})}
			</ul>

		</div>
	);
};
