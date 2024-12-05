/**
 * External dependencies
 */
import { useCallback, useEffect, useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';
import { useDispatch, useSelect } from '@wordpress/data';
import axios from 'axios';

/**
 * Internal dependencies
 */
import { Block as DeliveryDayBlock } from '../postnl-delivery-day/block';
import { Block as DropoffPointsBlock } from '../postnl-dropoff-points/block';
import debounce from 'lodash/debounce';

export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;

	const tabs = [
		{ id: 'delivery_day', name: __('Delivery Day', 'postnl-for-woocommerce') },
		{ id: 'dropoff_points', name: __('Dropoff Points', 'postnl-for-woocommerce') },
	];

	// State for the active tab
	const [activeTab, setActiveTab] = useState(tabs[0].id);

	// Get the letterbox status from settings
	const postnlData = getSetting('postnl-for-woocommerce-blocks_data', {});
	const letterbox = postnlData.letterbox || false;
	const { CART_STORE_KEY } = window.wc.wcBlocksData;

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect(
		(select) => {
			const store = select(CART_STORE_KEY);
			return store ? store.getCustomerData() : {};
		},
		[CART_STORE_KEY]
	);

	// Extract shipping and billing addresses
	const shippingAddress = customerData ? customerData.shippingAddress : null;

	// State to determine whether to show the container
	const [showContainer, setShowContainer] = useState(false);

	const { setShippingAddress, updateCustomerData } = useDispatch(CART_STORE_KEY);

	// Create a debounced shipping address state
	const [debouncedShippingAddress, setDebouncedShippingAddress] = useState(shippingAddress);

	const ADDRESS_DEBOUNCE_DELAY = 1000;

	const SHOW_CONTAINER_DEBOUNCE_DELAY = 2000;

	// Ref to prevent infinite loop when updating shipping address
	const isUpdatingAddress = useRef(false);

	// Update debouncedShippingAddress after user stops typing
	useEffect(() => {
		const handler = setTimeout(() => {
			setDebouncedShippingAddress(shippingAddress);
		}, ADDRESS_DEBOUNCE_DELAY);

		return () => {
			clearTimeout(handler);
		};
	}, [shippingAddress]);

	// Debounced function to update showContainer
	const debouncedSetShowContainer = useCallback(
		debounce((value) => {
			setShowContainer(value);
		}, SHOW_CONTAINER_DEBOUNCE_DELAY),
		[]
	);

	// Main useEffect dependent on debouncedShippingAddress
	useEffect(() => {
		// Prevent running if we're updating the address programmatically
		if (isUpdatingAddress.current) {
			isUpdatingAddress.current = false;
			return;
		}

		// Proceed only if debouncedShippingAddress exists
		if (debouncedShippingAddress) {
			const postcode = debouncedShippingAddress.postcode;
			const houseNumber = debouncedShippingAddress['postnl/house_number'];

			if (!empty(postcode) && !empty(houseNumber)) {
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

				// Create URL-encoded form data
				const formData = new URLSearchParams();
				formData.append('action', 'postnl_set_checkout_post_data');
				formData.append('nonce', postnlData.nonce);

				// Append each data field
				Object.keys(data).forEach((key) => {
					formData.append(`data[${key}]`, data[key]);
				});

				axios
					.post(postnlData.ajax_url, formData, {
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
						},
					})
					.then((response) => {
						if (response.data.success) {
							// Check if validated_address is returned
							if (
								response.data.data.validated_address &&
								response.data.data.validated_address.street &&
								response.data.data.validated_address.city &&
								response.data.data.validated_address.house_number
							) {
								const {street, city, house_number} = response.data.data.validated_address;
								const newShippingAddress = {
									...debouncedShippingAddress, // Preserve existing address fields
									address_1: street, // Update address_1 with validated street
									city: city, // Update city with validated city
									'postnl/house_number': house_number, // Include validated house_number
								};

								if (
									debouncedShippingAddress.address_1 !== street ||
									debouncedShippingAddress.city !== city ||
									debouncedShippingAddress['postnl/house_number'] !== house_number
								) {
									isUpdatingAddress.current = true;
									setShippingAddress(newShippingAddress);
									updateCustomerData(newShippingAddress);
								}
							}

							// Determine whether to show the container based on postcode and house number
							const show = !empty(debouncedShippingAddress.postcode) && !empty(debouncedShippingAddress['postnl/house_number']);
							debouncedSetShowContainer(show);
						} else {
							debouncedSetShowContainer(false);
						}

						// Dispatch custom event to notify other components
						const event = new Event('postnl_address_updated');
						window.dispatchEvent(event);
					})
					.catch((error) => {
						debouncedSetShowContainer(false);
						// Dispatch custom event to notify other components
						const event = new Event('postnl_address_updated');
						window.dispatchEvent(event);
					});
			} else {
				debouncedSetShowContainer(false);
			}
		} else {
			debouncedSetShowContainer(false);
		}
	}, [
		debouncedShippingAddress,
		postnlData.ajax_url,
		postnlData.nonce,
		debouncedSetShowContainer,
	]);

	// Utility function to check if a value is empty
	const empty = (value) => {
		return value === undefined || value === null || value === '';
	};

	if (letterbox) {
		return (
			<div className="postnl-letterbox-message">
				{__('These items are eligible for letterbox delivery.', 'postnl-for-woocommerce')}
			</div>
		);
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
