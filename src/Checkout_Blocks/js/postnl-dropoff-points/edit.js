/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export const Edit = () => {
	const blockProps = useBlockProps();
	return (
		<div {...blockProps}>
			<h4>{__('PostNL Dropof point Options', 'postnl-for-woocommerce')}</h4>
		</div>
	);
};

export const Save = () => {
	return (null);
};
