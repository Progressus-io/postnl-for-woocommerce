import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getSetting } from '@woocommerce/settings';

export const FillBlock = ( { checkoutExtensionData } ) => {
	const { setExtensionData } = checkoutExtensionData || {};
	const postnlData = getSetting( 'postnl-for-woocommerce-blocks_data', {} );
	const [ showButton, setShowButton ] = useState( false );

	useEffect( () => {
		if ( postnlData?.fill_in_with_postnl_settings?.is_fill_in_with_postnl_enabled ) {
			setShowButton( true );
			// Register your data under the “postnl” key
			if ( setExtensionData ) {
				setExtensionData( 'postnl', {
					// you can fill these from form inputs later
					houseNumber: '',
					dropoffPoints: '',
					deliveryDay: '',
				} );
			}
		}
	}, [ postnlData, setExtensionData ] );

	if ( ! showButton ) {
		return null;
	}

	const redirectUri = postnlData?.fill_in_with_postnl_settings?.redirect_uri || '#';
	const title       = __( 'Fill in with PostNL', 'postnl-for-woocommerce' );
	const description = __( 'Your name and address are automatically filled in via your PostNL account. That saves you from having to fill in the form!', 'postnl-for-woocommerce' );

	return (
		<div className="button--postnl-container">
			<a
				type="button"
				id="postnl-login-button"
				aria-label={ title }
				href={ redirectUri }
			>
				{/* … your SVG and spans … */}
				<span id="postnl-login-button__text">{ title }</span>
			</a>
			<p>{ description }</p>
		</div>
	);
};
