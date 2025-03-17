/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';

export const Edit = () => {
	const blockProps = useBlockProps();
	return <div { ...blockProps }></div>;
};

export const Save = () => {
	return null;
};
