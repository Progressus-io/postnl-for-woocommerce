/**
 * External dependencies
 */
import {registerCheckoutBlock} from '@woocommerce/blocks-checkout';
import {useEffect, useState} from '@wordpress/element';
import {__} from '@wordpress/i18n';
import {getSetting} from '@woocommerce/settings';
import {useSelect} from '@wordpress/data';
import axios from 'axios';
import {Spinner} from '@wordpress/components'; // Added import for Spinner

/**
 * Internal dependencies
 */
// Import child components
import {Block as DeliveryDayBlock} from '../postnl-delivery-day/block';
import {Block as DropoffPointsBlock} from '../postnl-dropoff-points/block';

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

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect((select) => {
		return select('wc/store/cart').getCustomerData();
	}, []);

	// Extract shipping and billing addresses
	const shippingAddress = customerData ? customerData.shippingAddress : null;

	// State to determine whether to show the container
	const [showContainer, setShowContainer] = useState(false);

	// State to track loading
	const [loading, setLoading] = useState(false);


	// Handle AJAX submission when all required fields are present
	useEffect(() => {
		if (shippingAddress) {
			const data = {
				shipping_country: shippingAddress.country || '',
				shipping_postcode: shippingAddress.postcode || '',
				shipping_house_number: shippingAddress['postnl/house_number'] || '',
				shipping_address_2: shippingAddress.address_2 || '',
				shipping_address_1: shippingAddress.address_1 || '',
				shipping_city: shippingAddress.city || '',
				shipping_state: shippingAddress.state || '',
				shipping_phone: shippingAddress.phone || '',
				shipping_email: shippingAddress.email || '',
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

					if (
						response.data.success &&
						response.data.data &&
						response.data.data.delivery_options &&
						response.data.data.delivery_options.length > 0
					) {
						setShowContainer(true);
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

			setExtensionData('postnl_shipping_country', data.shipping_country);
			setExtensionData('postnl_shipping_postcode', data.shipping_postcode);
			setExtensionData('postnl_shipping_house_number', data.shipping_house_number);
			setExtensionData('postnl_shipping_address_2', data.shipping_address_2);
			setExtensionData('postnl_shipping_address_1', data.shipping_address_1);
			setExtensionData('postnl_shipping_city', data.shipping_city);
			setExtensionData('postnl_shipping_state', data.shipping_state);
			setExtensionData('postnl_shipping_phone', data.shipping_phone);
			setExtensionData('postnl_shipping_email', data.shipping_email);
			setExtensionData('postnl_shipping_method', data.shipping_method);
			setExtensionData('postnl_ship_to_different_address', data.ship_to_different_address);
		} else {
			setShowContainer(false);
		}
	}, [shippingAddress, postnlData.ajax_url, postnlData.nonce, setExtensionData]);

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
