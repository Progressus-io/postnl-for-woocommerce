/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';
import axios from 'axios';

/**
 * Shipping Address Block Component
 */
export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;

	// Debounce setting extension data to optimize performance
	const debouncedSetExtensionData = useCallback(
		debounce((namespace, key, value) => {
			setExtensionData(namespace, key, value);
		}, 1000),
		[setExtensionData]
	);

	// State to capture the shipping house number
	const [shippingHouseNumber, setShippingHouseNumber] = useState('');

	// Retrieve customer data from WooCommerce cart store
	const customerData = useSelect((select) => {
		return select('wc/store/cart').getCustomerData();
	}, []);

	// Extract shipping and billing addresses
	const shippingAddress = customerData ? customerData.shippingAddress : null;
	const billingAddress = customerData ? customerData.billingAddress : null;

	// Handle changes to the shipping house number
	useEffect(() => {
		setExtensionData('postnl', 'shippingHouseNumber', shippingHouseNumber);
		debouncedSetExtensionData('postnl', 'shippingHouseNumber', shippingHouseNumber);
	}, [shippingHouseNumber, setExtensionData, debouncedSetExtensionData]);

	// Prepare and send the data object when all required fields are present
	useEffect(() => {
		if (shippingAddress && billingAddress && shippingHouseNumber) {
			const data = {
				shipping_country: shippingAddress.country || '',
				shipping_postcode: shippingAddress.postcode || '',
				shipping_house_number: shippingHouseNumber,
				shipping_address_2: shippingAddress.address_2 || '',
				shipping_address_1: shippingAddress.address_1 || '',
				shipping_city: shippingAddress.city || '',
				shipping_state: shippingAddress.state || '',
				shipping_phone: shippingAddress.phone || '',
				shipping_email: shippingAddress.email || '',
				shipping_method: 'postnl', // Changed from array to string as per PHP handler
				ship_to_different_address: '1', // Hard-coded
			};

			// Create URL-encoded form data
			const formData = new URLSearchParams();
			formData.append('action', 'postnl_set_checkout_post_data');
			formData.append('nonce', window.postnl_ajax_object.nonce);

			// Append each data field
			Object.keys(data).forEach((key) => {
				formData.append(`data[${key}]`, data[key]);
			});

			// Send the data via AJAX
			axios.post(window.postnl_ajax_object.ajax_url, formData, {
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
			})
				.then(response => {
					if (response.data.success) {
						console.log('Checkout post data saved successfully');
						// Dispatch custom event to notify Delivery Day block
						window.dispatchEvent(new Event('postnl_address_updated'));
					} else {
						console.error('Error saving checkout post data:', response.data.message);
					}
				})
				.catch(error => {
					console.error('AJAX error:', error);
				});

			// Optionally, set extension data if needed
			setExtensionData('postnl_shipping_country', data.shipping_country);
			setExtensionData('postnl_shipping_postcode', data.shipping_postcode);
			setExtensionData('postnl_shipping_house_number', data.shipping_house_number);
			setExtensionData('postnl_shipping_address_2', data.shipping_address_2);
			setExtensionData('postnl_shipping_address_1', data.shipping_address_1);
			setExtensionData('postnl_shipping_city', data.shipping_city);
			setExtensionData('postnl_shipping_state', data.shipping_state);
			setExtensionData('postnl_shipping_phone', data.shipping_phone);
			setExtensionData('postnl_shipping_email', data.shipping_email);
			setExtensionData('postnl_shipping_method', data.shipping_method); // Store as string
			setExtensionData('postnl_ship_to_different_address', data.ship_to_different_address);

			// Log the prepared data for verification
			console.log('Prepared Shipping Address Data:', data);
		}
	}, [shippingAddress, billingAddress, shippingHouseNumber, setExtensionData]);

	return (
		<div className="wc-block-components-text-input">
			{/* Shipping House Number Input */}
			<TextControl
				id="shipping_house_number"
				placeholder={__('Enter your house number', 'postnl-for-woocommerce')}
				value={shippingHouseNumber}
				onChange={(value) => setShippingHouseNumber(value)}
			/>
		</div>
	);
};
