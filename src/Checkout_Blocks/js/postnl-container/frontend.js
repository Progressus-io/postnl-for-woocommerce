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
	const isMobile = typeof window !== 'undefined' && window.innerWidth <= 768;

	useEffect( () => {
		if ( ! blockRef.current ) {
			return;
		}

		const isSidebar = blockRef.current.closest(
			'.wc-block-components-sidebar'
		);

		if ( isMobile && isSidebar ) {
			setShouldRender( false );
		}
	}, [ isMobile ] );

	if ( ! shouldRender ) {
		return null;
	}

	return (
		<div ref={ blockRef }>
			<Block { ...props } />
		</div>
	);
};

registerCheckoutBlock( {
	metadata,
	component: BlockWrapper,
} );
