/**
 * External dependencies
 */
import { registerCheckoutBlock } from '@woocommerce/blocks-checkout';
import { useEffect, useState, useRef } from '@wordpress/element';
/**
 * Internal dependencies
 */
import { Block } from './block';
import metadata from './block.json';

/**
 * Wrapper component that checks if block should render
 * @param props
 */
const BlockWrapper = ( props ) => {
	const [ shouldRender, setShouldRender ] = useState( true );
	const blockRef = useRef( null );

	const checkIsMobile = () => {
		return typeof window !== 'undefined' && window.innerWidth <= 768;
	};

	const checkShouldRender = () => {
		if ( ! blockRef.current ) {
			return true;
		}

		const isSidebar = blockRef.current.closest(
			'.wc-block-components-sidebar'
		);
		const mobile = checkIsMobile();

		// Hide if mobile AND inside sidebar.
		return ! ( mobile && isSidebar );
	};

	// Check on mount and when ref is attached.
	useEffect( () => {
		setShouldRender( checkShouldRender() );
	}, [] );

	// Handle resize events (including orientation changes).
	useEffect( () => {
		const handleResize = () => {
			setShouldRender( checkShouldRender() );
		};

		window.addEventListener( 'resize', handleResize );
		return () => window.removeEventListener( 'resize', handleResize );
	}, [] );

	// Always render the wrapper div to keep the ref attached.
	// Only conditionally render the Block inside.
	return (
		<div ref={ blockRef } style={ shouldRender ? {} : { display: 'none' } }>
			{ shouldRender && <Block { ...props } /> }
		</div>
	);
};

registerCheckoutBlock( {
	metadata,
	component: BlockWrapper,
} );
