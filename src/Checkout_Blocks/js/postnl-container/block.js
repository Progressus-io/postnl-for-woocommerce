/**
 * External dependencies
 */
import {useCallback, useEffect, useState} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {getSetting} from '@woocommerce/settings';
import {useDispatch, useSelect} from '@wordpress/data';
import axios from 'axios';
import {Spinner} from '@wordpress/components'; // Added import for Spinner

/**
 * Internal dependencies
 */
// Import child components
import {Block as DeliveryDayBlock} from '../postnl-delivery-day/block';
import {Block as DropoffPointsBlock} from '../postnl-dropoff-points/block';
import debounce from 'lodash/debounce'; // Correct import

export const Block = ({checkoutExtensionData}) => {
	const {setExtensionData} = checkoutExtensionData;

	const tabs = [
		{id: 'delivery_day', name: __('Delivery Day', 'postnl-for-woocommerce')},
		{id: 'dropoff_points', name: __('Dropoff Points', 'postnl-for-woocommerce')},
	];

	// State for the active tab
	const [activeTab, setActiveTab] = useState(tabs[0].id);

	// Get the letterbox status from settings
	const postnlData = getSetting('postnl-for-woocommerce-blocks_data', {});
	const letterbox = postnlData.letterbox || false;
	const { CART_STORE_KEY } = window.wc.wcBlocksData;

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect((select) => {
		const store = select(CART_STORE_KEY); // Correctly using CART_STORE_KEY
		return store ? store.getCustomerData() : {};
	}, [CART_STORE_KEY]);

	// Extract shipping and billing addresses
	const shippingAddress = customerData ? customerData.shippingAddress : null;

	// State to determine whether to show the container
	const [showContainer, setShowContainer] = useState(false);

	// State to track loading
	const [loading, setLoading] = useState(false);

	// Correctly destructure setShippingAddress from useDispatch
	const { setShippingAddress } = useDispatch(CART_STORE_KEY);

	// Create a debounced shipping address state
	const [debouncedShippingAddress, setDebouncedShippingAddress] = useState(shippingAddress);

	// Update debouncedShippingAddress after user stops typing for 1 second
	useEffect(() => {
		const handler = setTimeout(() => {
			setDebouncedShippingAddress(shippingAddress);
		}, 1000); // 1000ms debounce delay

		// Cleanup the timeout if shippingAddress changes before delay completes
		return () => {
			clearTimeout(handler);
		};
	}, [shippingAddress]);

	// Define the debounced function
	const debouncedSetShippingAddress = useCallback(
		debounce((newShippingAddress) => {
			setShippingAddress(newShippingAddress);
		}, 1000), // 1 second delay; adjust as needed
		[setShippingAddress]
	);

	// Cleanup the debounced function on unmount
	useEffect(() => {
		return () => {
			debouncedSetShippingAddress.cancel();
		};
	}, [debouncedSetShippingAddress]);

	// Main useEffect dependent on debouncedShippingAddress
	useEffect(() => {
		// Proceed only if debouncedShippingAddress exists
		if (debouncedShippingAddress) {
			const data = {
				shipping_country: debouncedShippingAddress.country || '',
				shipping_postcode: debouncedShippingAddress.postcode || '',
				shipping_house_number: debouncedShippingAddress['postnl/house_number'] || '',
				shipping_address_2: debouncedShippingAddress.address_2 || '',
				shipping_address_1: debouncedShippingAddress.address_1 || '',
				shipping_city: debouncedShippingAddress.city || '',
				shipping_state: debouncedShippingAddress.state || '',
				shipping_phone: debouncedShippingAddress.phone || '',
				shipping_email: debouncedShippingAddress.email || '',
				shipping_method: 'postnl',
				ship_to_different_address: '1',
			};

			// Check if the shipping country is NL
			if (data.shipping_country !== 'NL') {
				setShowContainer(false);
				// Dispatch event to notify components to clear options
				const event = new Event('postnl_address_updated');
				window.dispatchEvent(event);
				return;
			} else {
				setShowContainer(true);
			}

			// Create URL-encoded form data
			const formData = new URLSearchParams();
			formData.append('action', 'postnl_set_checkout_post_data');
			formData.append('nonce', postnlData.nonce);

			// Append each data field
			Object.keys(data).forEach((key) => {
				formData.append(`data[${key}]`, data[key]);
			});

			// Set loading to true before AJAX request
			setLoading(true);

			// Send the data via AJAX
			axios
				.post(postnlData.ajax_url, formData, {
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
					},
				})
				.then((response) => {
					if (response.data.success) {
						// Check if validated_address is returned
						if (response.data.data.validated_address) {
							const { street, city, house_number } = response.data.data.validated_address;
							const newShippingAddress = {
								...debouncedShippingAddress, // Preserve existing address fields
								address_1: street,           // Update address_1 with validated street
								city: city,                  // Update city with validated city
							};

							if (
								debouncedShippingAddress.address_1 !== street ||
								debouncedShippingAddress.city !== city
							) {
								debouncedSetShippingAddress(newShippingAddress);
							}
						}

						if (
							response.data.data.delivery_options &&
							response.data.data.delivery_options.length > 0
						) {
							setShowContainer(true);
						} else {
							setShowContainer(false);
						}
					} else {
						setShowContainer(false);
					}

					// Dispatch custom event to notify other components
					const event = new Event('postnl_address_updated');
					window.dispatchEvent(event);
				})
				.catch((error) => {
					setShowContainer(false);
					// Dispatch custom event to notify other components
					const event = new Event('postnl_address_updated');
					window.dispatchEvent(event);
				})
				.finally(() => {
					// Set loading to false after AJAX request completes
					setLoading(false);
				});

		} else {
			setShowContainer(false);
		}
	}, [
		debouncedShippingAddress,
		postnlData.ajax_url,
		postnlData.nonce,
		setExtensionData,
		debouncedSetShippingAddress,
	]);

	if (letterbox) {
		return (
			<div className="postnl-letterbox-message">
				{__('These items are eligible for letterbox delivery.', 'postnl-for-woocommerce')}
			</div>
		);
	} else if (loading) {
		return <Spinner/>;
	} else if (!showContainer) {
		return null;
	} else {
		return (
			<div id="postnl_checkout_option" className="postnl_checkout_container">
				<div className="postnl_checkout_tab_container">
					<ul className="postnl_checkout_tab_list">
						{tabs.map((tab) => (
							<li key={tab.id} className={activeTab === tab.id ? 'active' : ''}>
								<label htmlFor={`postnl_option_${tab.id}`} className="postnl_checkout_tab">
									<span>{tab.name}</span>
									<input
										type="radio"
										name="postnl_option"
										id={`postnl_option_${tab.id}`}
										className="postnl_option"
										value={tab.id}
										checked={activeTab === tab.id}
										onChange={() => setActiveTab(tab.id)}
									/>
								</label>
							</li>
						))}
					</ul>
				</div>
				<div className="postnl_checkout_content_container">
					<div
						className={`postnl_content ${activeTab === 'delivery_day' ? 'active' : ''}`}
						id="postnl_delivery_day_content"
					>
						<DeliveryDayBlock
							checkoutExtensionData={checkoutExtensionData}
							isActive={activeTab === 'delivery_day'}
						/>
					</div>
					<div
						className={`postnl_content ${activeTab === 'dropoff_points' ? 'active' : ''}`}
						id="postnl_dropoff_points_content"
					>
						<DropoffPointsBlock
							checkoutExtensionData={checkoutExtensionData}
							isActive={activeTab === 'dropoff_points'}
						/>
					</div>
				</div>
			</div>
		);
	}
};
