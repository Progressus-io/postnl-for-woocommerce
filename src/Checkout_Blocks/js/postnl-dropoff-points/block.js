/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
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
	},
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

	// State variables for hidden fields based on block.json attributes
	const [dropoffPointsAddressCompany, setDropoffPointsAddressCompany] = useState('');
	const [dropoffPointsAddress1, setDropoffPointsAddress1] = useState('');
	const [dropoffPointsAddress2, setDropoffPointsAddress2] = useState('');
	const [dropoffPointsCity, setDropoffPointsCity] = useState('');
	const [dropoffPointsPostcode, setDropoffPointsPostcode] = useState('');
	const [dropoffPointsCountry, setDropoffPointsCountry] = useState('');
	const [dropoffPointsPartnerID, setDropoffPointsPartnerID] = useState('');
	const [dropoffPointsDate, setDropoffPointsDate] = useState('');
	const [dropoffPointsTime, setDropoffPointsTime] = useState('');
	const [dropoffPointsDistance, setDropoffPointsDistance] = useState('');

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setSelectedOption('');
		setExtensionData('selectedOption', '');

		setDropoffPointsAddressCompany('');
		setDropoffPointsAddress1('');
		setDropoffPointsAddress2('');
		setDropoffPointsCity('');
		setDropoffPointsPostcode('');
		setDropoffPointsCountry('');
		setDropoffPointsPartnerID('');
		setDropoffPointsDate('');
		setDropoffPointsTime('');
		setDropoffPointsDistance('');

		setExtensionData('dropoffPointsAddressCompany', '');
		setExtensionData('dropoffPointsAddress1', '');
		setExtensionData('dropoffPointsAddress2', '');
		setExtensionData('dropoffPointsCity', '');
		setExtensionData('dropoffPointsPostcode', '');
		setExtensionData('dropoffPointsCountry', '');
		setExtensionData('dropoffPointsPartnerID', '');
		setExtensionData('dropoffPointsDate', '');
		setExtensionData('dropoffPointsTime', '');
		setExtensionData('dropoffPointsDistance', '');
	};

	/**
	 * Fetch dropoff options via AJAX
	 */
	const fetchDropoffOptions = useCallback(() => {
		setUpdating(true);
		setError('');

		const formData = new URLSearchParams();
		formData.append('action', 'postnl_get_delivery_options'); // Adjust if you have a different AJAX action
		formData.append('nonce', window.postnl_ajax_object.nonce);

		axios
			.post(window.postnl_ajax_object.ajax_url, formData, {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			})
			.then((response) => {
				if (response.data.success) {
					const dropoffOptions = response.data.data.dropoff_options;

					if (!dropoffOptions) {
						throw new Error('Dropoff options are undefined.');
					}

					if (!Array.isArray(dropoffOptions)) {
						throw new Error('Dropoff options is not an array.');
					}

					setDropoffOptions(dropoffOptions);

					// Set a default selected option if available
					if (dropoffOptions.length > 0) {
						const firstDropoff = dropoffOptions[0];
						const value = `${firstDropoff.partner_id}-${firstDropoff.loc_code}`;
						setSelectedOption(value);
						setExtensionData('selectedOption', value);

						// Update hidden fields and extension data
						updateHiddenFields(firstDropoff);
					} else {
						// If no dropoff options are available, clear selections
						clearSelections();
					}
				} else {
					throw new Error(response.data.message || 'Error fetching dropoff options.');
				}
			})
			.catch((error) => {
				console.error('AJAX error:', error);
				setError(error.message || 'An unexpected error occurred.');
			})
			.finally(() => {
				setUpdating(false);
				setLoading(false); // Ensure loading is set to false after fetch
			});
	}, [setExtensionData]);

	/**
	 * Update hidden fields and extension data based on selected dropoff point
	 */
	const updateHiddenFields = (dropoffPoint) => {
		const address = dropoffPoint.address || {};

		setDropoffPointsAddressCompany(address.company || '');
		setDropoffPointsAddress1(address.address_1 || '');
		setDropoffPointsAddress2(address.address_2 || '');
		setDropoffPointsCity(address.city || '');
		setDropoffPointsPostcode(address.postcode || '');
		setDropoffPointsCountry(address.country || '');
		setDropoffPointsPartnerID(dropoffPoint.partner_id || '');
		setDropoffPointsDate(dropoffPoint.date || '');
		setDropoffPointsTime(dropoffPoint.time || '');
		setDropoffPointsDistance(dropoffPoint.distance || '');

		setExtensionData('dropoffPointsAddressCompany', address.company || '');
		setExtensionData('dropoffPointsAddress1', address.address_1 || '');
		setExtensionData('dropoffPointsAddress2', address.address_2 || '');
		setExtensionData('dropoffPointsCity', address.city || '');
		setExtensionData('dropoffPointsPostcode', address.postcode || '');
		setExtensionData('dropoffPointsCountry', address.country || '');
		setExtensionData('dropoffPointsPartnerID', dropoffPoint.partner_id || '');
		setExtensionData('dropoffPointsDate', dropoffPoint.date || '');
		setExtensionData('dropoffPointsTime', dropoffPoint.time || '');
		setExtensionData('dropoffPointsDistance', dropoffPoint.distance || '');
	};

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
		setExtensionData('selectedOption', value);

		// Find the selected dropoff point.
		const selectedDropoffPoint = dropoffOptions.find((point) => {
			const pointValue = `${point.partner_id}-${point.loc_code}`;
			return pointValue === value;
		});

		// Update hidden fields and extension data
		if (selectedDropoffPoint) {
			updateHiddenFields(selectedDropoffPoint);
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
	 * Render the Dropoff Points with Hidden Inputs
	 */
	return (
		<div>
			{dropoffOptions.some((point) => point.show_desc) && (
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

			<input type="hidden" name="dropoffPointsAddressCompany" id="dropoffPointsAddressCompany" value={dropoffPointsAddressCompany} />
			<input type="hidden" name="dropoffPointsAddress1" id="dropoffPointsAddress1" value={dropoffPointsAddress1} />
			<input type="hidden" name="dropoffPointsAddress2" id="dropoffPointsAddress2" value={dropoffPointsAddress2} />
			<input type="hidden" name="dropoffPointsCity" id="dropoffPointsCity" value={dropoffPointsCity} />
			<input type="hidden" name="dropoffPointsPostcode" id="dropoffPointsPostcode" value={dropoffPointsPostcode} />
			<input type="hidden" name="dropoffPointsCountry" id="dropoffPointsCountry" value={dropoffPointsCountry} />
			<input type="hidden" name="dropoffPointsPartnerID" id="dropoffPointsPartnerID" value={dropoffPointsPartnerID} />
			<input type="hidden" name="dropoffPointsDate" id="dropoffPointsDate" value={dropoffPointsDate} />
			<input type="hidden" name="dropoffPointsTime" id="dropoffPointsTime" value={dropoffPointsTime} />
			<input type="hidden" name="dropoffPointsDistance" id="dropoffPointsDistance" value={dropoffPointsDistance} />
		</div>
	);
};
