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
	const [ isMobile, setIsMobile ] = useState( false );
	const blockRef                  = useRef( null );

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
		const mobile     = checkIsMobile();
		const shouldShow = ! ( mobile && isSidebar );

		return shouldShow;
	};

	useEffect( () => {
		setIsMobile( checkIsMobile() );
	}, [] );

	useEffect( () => {
		const handleResize = () => {
			setIsMobile( checkIsMobile() );
			setShouldRender( checkShouldRender() );
		};

		window.addEventListener( 'resize', handleResize );
		return () => window.removeEventListener( 'resize', handleResize );
	}, [] );
	
	useEffect( () => {
		setShouldRender( checkShouldRender() );
	}, [ isMobile ] );

	// Watch for DOM changes that might affect sidebar status.
	useEffect( () => {
		if ( ! blockRef.current ) {
			return;
		}

		const observer = new MutationObserver( () => {
			setShouldRender( checkShouldRender() );
		} );

		return () => {
			observer.disconnect();
		};
	}, [ blockRef.current ] );

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
