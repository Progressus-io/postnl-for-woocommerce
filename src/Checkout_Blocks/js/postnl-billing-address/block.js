/**
 * External dependencies
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import { TextControl,SelectControl, TextareaControl,RadioControl } from '@wordpress/components'; // Ensure you import TextControl correctly
import { __ } from '@wordpress/i18n';
import { debounce } from 'lodash';

/**
 * Internal dependencies
 */

export const Block = ({ checkoutExtensionData }) => {
	const { setExtensionData } = checkoutExtensionData;
	const debouncedSetExtensionData = useCallback(
		debounce((namespace, key, value) => {
			setExtensionData(namespace, key, value);
		}, 1000),
		[setExtensionData]
	);

	const [billingHouseNumber, setbillingHouseNumber] = useState('');

	useEffect(() => {
		setExtensionData('postnl', 'billingHouseNumber', billingHouseNumber);
		debouncedSetExtensionData('postnl', 'billingHouseNumber', billingHouseNumber);
	}, [setExtensionData, billingHouseNumber, debouncedSetExtensionData]);


	return (
		<div className="wc-block-components-text-input">
			<TextControl
				id={
					'billing_house_number'
				}
				placeholder={__('House number', 'postnl-for-woocommerce')}
				value={billingHouseNumber}
				onChange={(value) => setbillingHouseNumber(value)}
			/>

		</div>
	);
};
