/**
 * Mock for @wordpress/components
 */
import { createElement } from '@wordpress/element';

export const Spinner = () => createElement( 'div', { className: 'spinner' } );

export const Button = ( { children, ...props } ) =>
	createElement( 'button', props, children );

export const TextControl = ( props ) =>
	createElement( 'input', { type: 'text', ...props } );

export const SelectControl = ( { options = [], ...props } ) =>
	createElement(
		'select',
		props,
		options.map( ( opt ) =>
			createElement( 'option', { key: opt.value, value: opt.value }, opt.label )
		)
	);

export const CheckboxControl = ( props ) =>
	createElement( 'input', { type: 'checkbox', ...props } );

export const Panel = ( { children, ...props } ) =>
	createElement( 'div', { className: 'panel', ...props }, children );

export const PanelBody = ( { children, ...props } ) =>
	createElement( 'div', { className: 'panel-body', ...props }, children );

export const Notice = ( { children, ...props } ) =>
	createElement( 'div', { className: 'notice', ...props }, children );

export default {
	Spinner,
	Button,
	TextControl,
	SelectControl,
	CheckboxControl,
	Panel,
	PanelBody,
	Notice,
};
