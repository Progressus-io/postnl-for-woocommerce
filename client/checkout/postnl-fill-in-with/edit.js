/**
 * External dependencies
 */
import { useBlockProps } from '@wordpress/block-editor';

export const Edit = () => {
	const blockProps = useBlockProps();
	return <div { ...blockProps }></div>;
};

export const Save = () => {
	return null;
};
