/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { Spinner, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import axios from 'axios';
import { debounce } from 'lodash';
import { getSetting } from '@woocommerce/settings';

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
export const Block = ({ checkoutExtensionData, isActive }) => {
	const { setExtensionData } = checkoutExtensionData;
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce((namespace, key, value) => {
			setExtensionData(namespace, key, value);
		}, 1000),
		[setExtensionData]
	);

	const [dropoffOptions, setDropoffOptions] = useState([]);
	const [dropoffPoints, setDropoffPoints] = useState(''); // Empty by default
	const [loading, setLoading] = useState(true);
	const [updating, setUpdating] = useState(false);
	const [error, setError] = useState('');

	const [dropoffPointsAddressCompany, setDropoffPointsAddressCompany] = useState('');
	const [dropoffPointsAddress1, setDropoffPointsAddress1] = useState('');
	const [dropoffPointsAddress2, setDropoffPointsAddress2] = useState('');
	const [dropoffPointsCity, setDropoffPointsCity] = useState('');
	const [dropoffPointsPostcode, setDropoffPointsPostcode] = useState('');
	const [dropoffPointsCountry, setDropoffPointsCountry] = useState('');
	const [dropoffPointsPartnerID, setDropoffPointsPartnerID] = useState('');
	const [dropoffPointsDate, setDropoffPointsDate] = useState('');
	const [dropoffPointsTime, setDropoffPointsTime] = useState('');
	const [dropoffPointsDistance, setDropoffPointsDistance] = useState(null);


	useEffect(() => {
		setExtensionData('postnl', 'dropoffPoints', dropoffPoints);
		debouncedSetExtensionData('postnl', 'dropoffPoints', dropoffPoints);
	}, [dropoffPoints, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsAddressCompany', dropoffPointsAddressCompany);
		debouncedSetExtensionData('postnl', 'dropoffPointsAddressCompany', dropoffPointsAddressCompany);
	}, [dropoffPointsAddressCompany, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsAddress1', dropoffPointsAddress1);
		debouncedSetExtensionData('postnl', 'dropoffPointsAddress1', dropoffPointsAddress1);
	}, [dropoffPointsAddress1, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsAddress2', dropoffPointsAddress2);
		debouncedSetExtensionData('postnl', 'dropoffPointsAddress2', dropoffPointsAddress2);
	}, [dropoffPointsAddress2, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsCity', dropoffPointsCity);
		debouncedSetExtensionData('postnl', 'dropoffPointsCity', dropoffPointsCity);
	}, [dropoffPointsCity, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsPostcode', dropoffPointsPostcode);
		debouncedSetExtensionData('postnl', 'dropoffPointsPostcode', dropoffPointsPostcode);
	}, [dropoffPointsPostcode, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsCountry', dropoffPointsCountry);
		debouncedSetExtensionData('postnl', 'dropoffPointsCountry', dropoffPointsCountry);
	}, [dropoffPointsCountry, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsPartnerID', dropoffPointsPartnerID);
		debouncedSetExtensionData('postnl', 'dropoffPointsPartnerID', dropoffPointsPartnerID);
	}, [dropoffPointsPartnerID, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsDate', dropoffPointsDate);
		debouncedSetExtensionData('postnl', 'dropoffPointsDate', dropoffPointsDate);
	}, [dropoffPointsDate, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsTime', dropoffPointsTime);
		debouncedSetExtensionData('postnl', 'dropoffPointsTime', dropoffPointsTime);
	}, [dropoffPointsTime, setExtensionData, debouncedSetExtensionData]);

	useEffect(() => {
		setExtensionData('postnl', 'dropoffPointsDistance', dropoffPointsDistance);
		debouncedSetExtensionData('postnl', 'dropoffPointsDistance', dropoffPointsDistance);
	}, [dropoffPointsDistance, setExtensionData, debouncedSetExtensionData]);

	/**
	 * useEffect to handle tab activation
	 */
	useEffect(() => {
		if (!isActive) {
			// Tab is inactive
			// Clear hidden fields
			clearSelections();
		}
		// When tab becomes active, do not select any option by default
		// Hidden fields remain empty until user selects an option
	}, [isActive]);

	/**
	 * Helper function to clear selections
	 */
	const clearSelections = () => {
		setDropoffPoints('');
		setDropoffPointsAddressCompany('');
		setDropoffPointsAddress1('');
		setDropoffPointsAddress2('');
		setDropoffPointsCity('');
		setDropoffPointsPostcode('');
		setDropoffPointsCountry('');
		setDropoffPointsPartnerID('');
		setDropoffPointsDate('');
		setDropoffPointsTime('');
		setDropoffPointsDistance(null); // Use null for numeric field

		setExtensionData('dropoffPoints', '');
		setExtensionData('dropoffPointsAddressCompany', '');
		setExtensionData('dropoffPointsAddress1', '');
		setExtensionData('dropoffPointsAddress2', '');
		setExtensionData('dropoffPointsCity', '');
		setExtensionData('dropoffPointsPostcode', '');
		setExtensionData('dropoffPointsCountry', '');
		setExtensionData('dropoffPointsPartnerID', '');
		setExtensionData('dropoffPointsDate', '');
		setExtensionData('dropoffPointsTime', '');
		setExtensionData('dropoffPointsDistance', null); // Use null for numeric field
	};

	/**
	 * Fetch dropoff options via AJAX
	 */
	const fetchDropoffOptions = useCallback(() => {
		setUpdating(true);
		setError('');

		const formData = new URLSearchParams();
		formData.append('action', 'postnl_get_delivery_options'); // Adjust if you have a different AJAX action
		formData.append('nonce', postnlData.nonce);

		axios
			.post(postnlData.ajax_url, formData, {
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

					// Do not select any option by default
					// Clear selections to ensure hidden fields are empty
					clearSelections();
				} else {
					throw new Error(response.data.message || 'Error fetching dropoff options.');
				}
			})
			.catch((error) => {
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
		setDropoffPointsDistance(Number(dropoffPoint.distance) || null); // Ensure numeric value or null

		setExtensionData('dropoffPointsAddressCompany', address.company || '');
		setExtensionData('dropoffPointsAddress1', address.address_1 || '');
		setExtensionData('dropoffPointsAddress2', address.address_2 || '');
		setExtensionData('dropoffPointsCity', address.city || '');
		setExtensionData('dropoffPointsPostcode', address.postcode || '');
		setExtensionData('dropoffPointsCountry', address.country || '');
		setExtensionData('dropoffPointsPartnerID', dropoffPoint.partner_id || '');
		setExtensionData('dropoffPointsDate', dropoffPoint.date || '');
		setExtensionData('dropoffPointsTime', dropoffPoint.time || '');
		setExtensionData('dropoffPointsDistance', Number(dropoffPoint.distance) || null); // Ensure numeric value or null
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
		setDropoffPoints(value);
		setExtensionData('dropoffPoints', value);

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
					const isChecked = dropoffPoints === value;
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
										<i>{__(point.type, 'postnl-for-woocommerce')}</i>
										<span>{address}</span>
									</label>
								</li>
							</ul>
						</li>
					);
				})}
			</ul>

			{/* Hidden Inputs */}
			<input type="hidden" name="dropoffPoints" id="dropoffPoints" value={dropoffPoints} />
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
